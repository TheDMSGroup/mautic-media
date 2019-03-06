<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Helper;

use FacebookAds\Api;
use FacebookAds\Http\Exception\AuthorizationException;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdAccountUser;
use FacebookAds\Object\User;
use FacebookAds\Object\Values\ReachFrequencyPredictionStatuses;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;

/**
 * Class FacebookHelper.
 *
 * https://developers.facebook.com/docs/marketing-api/sdks/#install-facebook-sdks
 */
class FacebookHelper extends CommonProviderHelper
{
    /** @var int Number of rate limit errors after which we abort. */
    public static $rateLimitMaxErrors = 100;

    /** @var int Number of seconds to sleep between looping API operations. */
    public static $betweenOpSleep = .25;

    /** @var int Number of seconds to sleep when we hit API rate limits. */
    public static $rateLimitSleep = 60;

    /** @var Api */
    private $facebookApi;

    /** @var User */
    private $facebookUser;

    /** @var array */
    private $facebookInsightJobs = [];

    /** @var array */
    private $facebookInsightAccounts = [];

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return $this|array
     */
    public function pullData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        // Cursor::setDefaultUseImplicitFetch(true);
        try {
            $this->authenticate();
            $accounts = $this->getActiveAccounts($dateFrom, $dateTo);
            if (!$accounts) {
                return $this->stats;
            }

            // Using the active accounts, go backwards through time one day at a time to pull hourly data.
            $date   = clone $dateTo;
            $oneDay = new \DateInterval('P1D');
            while ($date >= $dateFrom) {
                /** @var AdAccount $account */
                foreach ($accounts as $account) {
                    $spend    = 0;
                    $self     = $account->getData();
                    $timezone = new \DateTimeZone($self['timezone_name']);
                    $since    = clone $date;
                    $until    = clone $date;
                    // Since we are pulling one day at a time, these can be the same day for this provider.
                    $since->setTimeZone($timezone);
                    $until->setTimeZone($timezone);
                    $this->output->write(
                        MediaAccount::PROVIDER_FACEBOOK.' - Pulling hourly data - '.
                        $since->format('Y-m-d').' - '.
                        $self['name']
                    );

                    // Specify the time_range in the relative timezone of the Ad account to make sure we get back the data we need.
                    $fields = [
                        'ad_id',
                        'ad_name',
                        'adset_id',
                        'adset_name',
                        'campaign_id',
                        'campaign_name',
                        'spend',
                        'cpm',
                        'cpc',
                        'ctr',
                        'impressions',
                        'clicks',
                    ];
                    $params = [
                        'level'      => 'ad',
                        'filtering'  => [
                            [
                                'field'    => 'spend',
                                'operator' => 'GREATER_THAN',
                                'value'    => '0',
                            ],
                        ],
                        'breakdowns' => [
                            'hourly_stats_aggregated_by_advertiser_time_zone',
                        ],
                        'sort'       => [
                            // 'date_start_descending',
                            'hourly_stats_aggregated_by_advertiser_time_zone_descending',
                        ],
                        'time_range' => [
                            'since' => $since->format('Y-m-d'),
                            'until' => $until->format('Y-m-d'),
                        ],
                    ];
                    $this->applyPresetDateRanges($params, $timezone);
                    $this->getInsights(
                        $account,
                        $self['account_id'],
                        $fields,
                        $params,
                        function ($data) use (&$spend, $timezone, $self) {
                            // Convert the date to our standard.
                            $time = substr($data['hourly_stats_aggregated_by_advertiser_time_zone'], 0, 8);
                            $date = \DateTime::createFromFormat(
                                'Y-m-d H:i:s',
                                $data['date_start'].' '.$time,
                                $timezone
                            );
                            $stat = new Stat();
                            $stat->setMediaAccountId($this->mediaAccount->getId());

                            $stat->setDateAdded($date);

                            $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
                                (string) $self['id'],
                                (string) $data['campaign_id'],
                                (string) $self['name'],
                                (string) $data['campaign_name']
                            );
                            if (is_int($campaignId)) {
                                $stat->setCampaignId($campaignId);
                            }

                            $provider = MediaAccount::PROVIDER_FACEBOOK;
                            $stat->setProvider($provider);

                            $stat->setProviderAccountId($self['id']);
                            $stat->setproviderAccountName($self['name']);

                            $stat->setProviderCampaignId($data['campaign_id']);
                            $stat->setProviderCampaignName($data['campaign_name']);

                            $stat->setProviderAdsetId($data['adset_id']);
                            $stat->setproviderAdsetName($data['adset_name']);

                            $stat->setProviderAdId($data['ad_id']);
                            $stat->setproviderAdName($data['ad_name']);

                            $stat->setCurrency($self['currency']);
                            $stat->setSpend(floatval($data['spend']));
                            $stat->setCpm(floatval($data['cpm']));
                            $stat->setCpc(floatval($data['cpc']));
                            $stat->setCtr(floatval($data['ctr']));
                            $stat->setImpressions(intval($data['impressions']));
                            $stat->setClicks(intval($data['clicks']));

                            $this->addStatToQueue($stat, $spend);
                        },
                        true
                    );
                    $this->output->writeln(' - '.$self['currency'].' '.$spend);
                }
                $date->sub($oneDay);
            }

            $this->processInsightJobs();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->outputErrors(MediaAccount::PROVIDER_FACEBOOK);
        }
        $this->saveQueue();

        return $this;
    }

    /**
     * @throws \Exception
     */
    private function authenticate()
    {
        if (!$this->facebookApi || !$this->facebookUser) {
            // Check mandatory credentials.
            if (!trim($this->providerClientId) || !trim($this->providerClientSecret) || !trim($this->providerToken)) {
                throw new \Exception(
                    'Missing credentials for this media account '.$this->providerAccountId.'.'
                );
            }

            // Configure the client session.
            Api::init($this->providerClientId, $this->providerClientSecret, $this->providerToken);
            $this->facebookApi = Api::instance();
            // $this->facebookApi->setLogger(new \FacebookAds\Logger\CurlLogger());

            // Authenticate and get the primary user ID in the same call.
            $me = $this->facebookApi->call('/me')->getContent();
            if (!$me || !isset($me['id'])) {
                throw new \Exception(
                    'Cannot discern Facebook user for account '.$this->providerAccountId.'. You likely need to reauthenticate.'
                );
            }
            $this->output->writeln('Logged in to Facebook as '.strip_tags($me['name']));
            $this->facebookUser = new AdAccountUser($me['id']);
        }
    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getActiveAccounts(\DateTime $dateFrom, \DateTime $dateTo)
    {
        // Find Ad accounts this user has access to with activity in this date range to reduce overall API call count.

        $accounts = [];
        /* @var AdAccount $account */
        $this->getAdAccounts(
            function ($account) use (&$accounts, $dateTo, $dateFrom) {
                // For each account, do a quick search for spend activity in the global date range.
                $spend = 0;
                $self  = $account->getData();
                $this->output->write(
                    MediaAccount::PROVIDER_FACEBOOK.' - Checking for activity - '.
                    $dateFrom->format('Y-m-d').' ~ '.$dateTo->format('Y-m-d').' - '.
                    $self['name']
                );
                $timezone = new \DateTimeZone($self['timezone_name']);
                $since    = clone $dateFrom;
                $until    = clone $dateTo;
                $since->setTimeZone($timezone);
                $until->setTimeZone($timezone);
                $fields = [
                    'campaign_id',
                    'campaign_name',
                    'spend',
                ];
                $params = [
                    'level'      => 'account',
                    'filtering'  => [
                        [
                            'field'    => 'spend',
                            'operator' => 'GREATER_THAN',
                            'value'    => '0',
                        ],
                    ],
                    'time_range' => [
                        'since' => $since->format('Y-m-d'),
                        'until' => $until->format('Y-m-d'),
                    ],
                ];
                $this->applyPresetDateRanges($params, $timezone);
                $this->getInsights(
                    $account,
                    $self['account_id'],
                    $fields,
                    $params,
                    function ($data) use (&$spend, $account, &$accounts) {
                        if (!empty($data['spend'])) {
                            $spend += $data['spend'];
                            $accounts[] = $account;
                        }
                    },
                    false
                );
                $this->output->writeln(' - '.$self['currency'].' '.$spend);
            }
        );
        $this->output->writeln(
            MediaAccount::PROVIDER_FACEBOOK.' - Found '.count(
                $accounts
            ).' accounts active for media account '.$this->mediaAccount->getId().'.'
        );

        return $accounts;
    }

    /**
     * @param $callback
     *
     * @return $this
     *
     * @throws \Exception
     */
    private function getAdAccounts($callback)
    {
        if (!is_callable($callback)) {
            throw new \Exception('Callback is not callable.');
        }
        $cursor = null;
        $fields = [
            'id',
            'timezone_name',
            'name',
            'currency',
        ];
        $params = [];

        do {
            try {
                if (!$cursor) {
                    /** @var \FacebookAds\Cursor $cursor */
                    $cursor = $this->facebookUser->getAdAccounts($fields, $params);
                    $cursor->setUseImplicitFetch(true);
                }

                $data = $cursor->current();
                if ($data && $data->getData()) {
                    if ($callback($data)) {
                        $cursor->next();
                        sleep(self::$betweenOpSleep);
                        break;
                    }
                }
                sleep(self::$betweenOpSleep);
                $cursor->next();
            } catch (\Exception $e) {
                $code           = $e->getCode();
                $this->errors[] = $e->getMessage();
                if (count($this->errors) > self::$rateLimitMaxErrors) {
                    throw new \Exception('Too many request errors.');
                }
                if (
                    $e instanceof AuthorizationException
                    && ReachFrequencyPredictionStatuses::MINIMUM_REACH_NOT_AVAILABLE === $code
                ) {
                    $this->output->write('.');
                    sleep(self::$rateLimitSleep);
                }
            }
        } while (!$cursor || $cursor->valid());

        return $this;
    }

    /**
     * Convert appropriate custom time ranges to the preset date ranges that facebook provides if we find a match.
     * This makes the API calls less costly in terms of rate limiting.
     *
     * @param $params
     * @param $timezone
     *
     * @throws \Exception
     */
    private function applyPresetDateRanges(&$params, $timezone)
    {
        // Use date_presets if available.
        if (
            isset($params['time_range'])
            && isset($params['time_range']['since'])
            && isset($params['time_range']['until'])
        ) {
            $today     = (new \DateTime('today', $timezone))->format('Y-m-d');
            $yesterday = (new \DateTime('yesterday', $timezone))->format('Y-m-d');
            if (
                $params['time_range']['since'] === $params['time_range']['until']
                && $params['time_range']['until'] === $today
            ) {
                $params['date_preset'] = 'today';
            } elseif (
                $params['time_range']['since'] === $params['time_range']['until']
                && $params['time_range']['until'] === $yesterday
            ) {
                $params['date_preset'] = 'yesterday';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-1 sunday', $timezone))
                        ->format('Y-m-d'))
                && $params['time_range']['until'] === $today
            ) {
                $params['date_preset'] = 'this_week_sun_today';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('last monday', $timezone))
                        ->format('Y-m-d'))
                && $params['time_range']['until'] === $today
            ) {
                $params['date_preset'] = 'this_week_mon_today';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-2 sunday', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('last saturday', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_week_sun_sat';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-2 monday', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('last sunday', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_week_mon_sun';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('this month midnight', $timezone))
                        ->format('Y-m-d'))
                && $params['time_range']['until'] === $today
            ) {
                $params['date_preset'] = 'this_month';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('last month midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('last day of last month midnight', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_month';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-3 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_3d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-7 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_7d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-14 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_14d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-28 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_28d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-30 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_30d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('-90 days midnight', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('midnight -1 minute', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_90d';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('january 1', $timezone))
                        ->format('Y-m-d'))
                && $params['time_range']['until'] === $today
            ) {
                $params['date_preset'] = 'this_year';
            } elseif (
                ($params['time_range']['since'] === (new \DateTime('january 1 last year', $timezone))
                        ->format('Y-m-d'))
                && ($params['time_range']['until'] === (new \DateTime('january 1 -1 day', $timezone))
                        ->format('Y-m-d'))
            ) {
                $params['date_preset'] = 'last_year';
            }
            if (isset($params['date_preset'])) {
                unset($params['time_range']);
            }
        }
    }

    /**
     * @param AdAccount $account
     * @param           $accountId
     * @param           $fields
     * @param           $params
     * @param           $callback
     * @param bool      $queueJobs
     *
     * @return $this|FacebookHelper
     *
     * @throws \Exception
     */
    private function getInsights(
        AdAccount $account,
        $accountId,
        $fields,
        $params,
        $callback,
        $queueJobs = false
    ) {
        if (!is_callable($callback)) {
            throw new \Exception('Callback is not callable.');
        }

        if (
            $queueJobs
            && isset($this->facebookInsightJobs[$accountId])
        ) {
            // We've already hit the rate limit for this account.
            // Wait till we hit the end of the queue to pick this up later.
            return $this->queueInsightJob($account, $accountId, $fields, $params, $callback);
        }

        $cursor = null;
        do {
            try {
                if (!$cursor) {
                    /** @var \FacebookAds\Cursor $cursor */
                    $cursor = $account->getInsights($fields, $params);
                    $cursor->setUseImplicitFetch(true);
                }
                $data = $cursor->current();
                if ($data) {
                    $data = $data->getData();
                    if ($data) {
                        if ($callback($data)) {
                            $cursor->next();
                            sleep(self::$betweenOpSleep);
                            break;
                        }
                    }
                }
                sleep(self::$betweenOpSleep);
                $cursor->next();
            } catch (\Exception $e) {
                $code           = $e->getCode();
                $this->errors[] = $e->getMessage();
                if (
                    $e instanceof AuthorizationException
                    && ReachFrequencyPredictionStatuses::MINIMUM_REACH_NOT_AVAILABLE === $code
                ) {
                    if ($queueJobs) {
                        // We'll nope out till we come back to the queue later.
                        // $this->output->write('.');
                        return $this->queueInsightJob($account, $accountId, $fields, $params, $callback);
                    } else {
                        // We've already been rate limited once, let's add delays now.
                        $this->saveQueue();
                        $this->output->write('.');
                        sleep(self::$rateLimitSleep);
                    }
                }
                if (count($this->errors) > self::$rateLimitMaxErrors) {
                    throw new \Exception('Too many request errors.');
                }
            }
        } while (!$cursor || $cursor->valid());

        return $this;
    }

    /**
     * @param $account
     * @param $accountId
     * @param $fields
     * @param $params
     * @param $callback
     *
     * @return $this
     */
    private function queueInsightJob($account, $accountId, $fields, $params, $callback)
    {
        if (!$accountId) {
            return;
        }

        // Queue this request to be made later after all non-limited requests are done.
        if (!isset($this->facebookInsightJobs[$accountId])) {
            $this->output->write(' (rate limited) ');
            $this->output->writeln('');
            $this->facebookInsightJobs[$accountId] = [];
        }
        if (!isset($this->facebookInsightAccounts[$accountId])) {
            $this->facebookInsightAccounts[$accountId] = $account;
        }
        $job                                     = new \stdClass();
        $job->fields                             = $fields;
        $job->params                             = $params;
        $job->callback                           = $callback;
        $this->facebookInsightJobs[$accountId][] = $job;

        return $this;
    }

    /**
     * Process job queue that was populated by hitting rate limits on a per-account basis.
     *
     * @throws \Exception
     */
    private function processInsightJobs()
    {
        if (!empty($this->facebookInsightJobs)) {
            $this->output->write(
                MediaAccount::PROVIDER_FACEBOOK.' - Processing all requests that had to be queued due to rate limits.'
            );
            foreach ($this->facebookInsightJobs as $accountId => $jobs) {
                foreach ($jobs as $id => $job) {
                    $j = clone $job;
                    unset($this->facebookInsightJobs[$accountId][$id]);
                    $this->getInsights(
                        $this->facebookInsightAccounts[$accountId],
                        $accountId,
                        $j->fields,
                        $j->params,
                        $j->callback,
                        false
                    );
                }
            }
        }
    }
}
