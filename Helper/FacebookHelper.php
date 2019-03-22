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
use MauticPlugin\MauticMediaBundle\Entity\Summary;

/**
 * Class FacebookHelper.
 *
 * https://developers.facebook.com/docs/marketing-api/sdks/#install-facebook-sdks
 */
class FacebookHelper extends CommonProviderHelper
{
    /** @var int Number of rate limit errors after which we abort. */
    public static $rateLimitMaxErrors = 100000;

    /** @var int Number of seconds to sleep between looping API operations. */
    public static $betweenOpSleep = .5;

    /** @var int Number of seconds to sleep when we hit API rate limits. */
    public static $rateLimitSleep = 60;

    /** @var string */
    public static $ageDataIsFinal = '48 hours';

    /** @var bool */
    private static $facebookImplicitFetch = true;

    /** @var Api */
    private $facebookApi;

    /** @var User */
    private $facebookUser;

    /** @var array */
    private $facebookInsightJobs = [];

    /** @var array */
    private $facebookInsightAccounts = [];

    /**
     * @return $this|CommonProviderHelper
     */
    public function pullData()
    {
        try {
            $this->authenticate();

            // Using the active accounts, go backwards through time one day at a time to pull hourly data.
            $date   = $this->getDateTo();
            $oneDay = new \DateInterval('P1D');
            while ($date >= $this->getDateFrom()) {
                /** @var AdAccount $account */
                foreach ($this->getActiveAccounts($date, $date) as $account) {
                    $spend       = 0;
                    $accountData = $account->getData();
                    $timezone    = new \DateTimeZone($accountData['timezone_name']);
                    $since       = clone $date;
                    $until       = clone $date;
                    // Since we are pulling one day at a time, these can be the same day for this provider.
                    $since->setTimeZone($timezone);
                    $until->setTimeZone($timezone);
                    $this->output->write(
                        MediaAccount::PROVIDER_FACEBOOK.' - Pulling hourly data - '.
                        $since->format('Y-m-d').' - '.
                        $accountData['name']
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
                        'limit'      => self::$pageLimit,
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
                        $accountData['id'],
                        $fields,
                        $params,
                        function ($accountInsightData) use (&$spend, $timezone, $accountData) {
                            $data = array_merge($accountData, array_filter($accountInsightData));
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
                                (string) $data['id'],
                                (string) $data['campaign_id'],
                                (string) $data['name'],
                                (string) $data['campaign_name']
                            );
                            if (is_int($campaignId)) {
                                $stat->setCampaignId($campaignId);
                            }

                            $provider = MediaAccount::PROVIDER_FACEBOOK;
                            $stat->setProvider($provider);

                            $stat->setProviderAccountId($data['id']);
                            $stat->setproviderAccountName($data['name']);

                            $stat->setProviderCampaignId($data['campaign_id']);
                            $stat->setProviderCampaignName($data['campaign_name']);

                            $stat->setProviderAdsetId($data['adset_id']);
                            $stat->setproviderAdsetName($data['adset_name']);

                            $stat->setProviderAdId($data['ad_id']);
                            $stat->setproviderAdName($data['ad_name']);

                            $stat->setCurrency($data['currency']);
                            $stat->setSpend(floatval($data['spend']));
                            $stat->setCpm(floatval($data['cpm']));
                            $stat->setCpc(floatval($data['cpc']));
                            $stat->setCtr(floatval($data['ctr']));
                            $stat->setImpressions(intval($data['impressions']));
                            $stat->setClicks(intval($data['clicks']));

                            $this->addStatToQueue($stat, $spend);
                        },
                        // No longer queing jobs in order to ensure lock-up of summary data. Could be re-enabled in future.
                        false
                    );
                    $spend = round($spend, 2);

                    // Create/Update summary data (final and completion may be changed).
                    $summary = new Summary();
                    $summary->setMediaAccountId($this->mediaAccount->getId());
                    $summary->setDateAdded($since);
                    $summary->setDateModified(new \DateTime());
                    $summary->setProvider(MediaAccount::PROVIDER_FACEBOOK);
                    $summary->setProviderAccountId($accountData['id']);
                    $summary->setProviderAccountName($accountData['name']);
                    $summary->setCpm(floatval($accountData['cpm']));
                    $summary->setCpc(floatval($accountData['cpc']));
                    $summary->setCtr(floatval($accountData['ctr']));
                    $summary->setClicks(intval($accountData['clicks']));
                    $summary->setCurrency($accountData['currency']);
                    $summary->setSpend(floatval($accountData['spend']));
                    $summary->setImpressions(intval($accountData['impressions']));
                    $complete = $spend >= $accountData['spend'];
                    $summary->setComplete($complete);
                    $endOfDate = clone $until;
                    $endOfDate->setTime(23, 59, 59);
                    $final = $complete && ($endOfDate < new \DateTime(self::$ageDataIsFinal));
                    $summary->setFinal($final);
                    $summary->setFinalDate($summary->getDateAdded()->modify('+'.self::$ageDataIsFinal));
                    $summary->setProviderDate($since->format(\DateTime::ISO8601));
                    $this->addSummaryToQueue($summary);

                    $this->output->writeln(
                        ' - '.$accountData['currency'].' '.$spend.' - '.($complete ? 'complete' : 'incomplete').' - '.($final ? 'final' : 'not final')
                    );
                }
                $date->sub($oneDay);
            }
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            // Unexpected error.
            sleep(self::$rateLimitSleep);
        }
        $this->saveQueue();
        try {
            $this->processInsightJobs();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        $this->outputErrors(MediaAccount::PROVIDER_FACEBOOK);

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
            if (256 == $this->output->getVerbosity()) {
                $this->facebookApi->setLogger(new \FacebookAds\Logger\CurlLogger());
            }

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
     * Find Ad accounts this user has access to with activity in this date range to reduce overall API call count.
     * Build summary data from the daily data.
     *
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getActiveAccounts(\DateTime $dateFrom, \DateTime $dateTo)
    {
        $accounts = [];
        /* @var AdAccount $account */
        $this->getAllAdAccounts(
            function ($account) use (&$accounts, $dateTo, $dateFrom) {
                $spend       = 0;
                $accountData = $account->getData();

                $this->output->write(
                    MediaAccount::PROVIDER_FACEBOOK.' - Checking activity - '.
                    $dateFrom->format('Y-m-d').($dateFrom === $dateTo ? '' : ' ~ '.$dateTo->format('Y-m-d')).' - '.
                    $accountData['name']
                );
                $timezone = new \DateTimeZone($accountData['timezone_name']);
                $since    = clone $dateFrom;
                $until    = clone $dateTo;
                $since->setTimeZone($timezone);
                $until->setTimeZone($timezone);
                $fields = [
                    'spend',
                    'cpm',
                    'cpc',
                    'ctr',
                    'impressions',
                    'clicks',
                ];
                $params = [
                    'limit'      => self::$pageLimit,
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
                // Should only have a single callback per account.
                $this->getInsights(
                    $account,
                    $accountData['id'],
                    $fields,
                    $params,
                    function ($accountInsightData) use (
                        $account,
                        $since,
                        $accountData,
                        &$spend,
                        &$accounts
                    ) {
                        $accountData = array_merge($accountData, array_filter($accountInsightData));
                        if (!empty($accountData['spend']) && $accountData['spend'] > 0) {
                            $spend += $accountData['spend'];

                            // Add new insight data to the $account object data for later correlation.
                            $account->setDataWithoutValidation($accountData);
                            $accounts[] = $account;

                            if ($this->getDateFrom() === $this->getDateTo()) {
                                $summary = new Summary();
                                $summary->setMediaAccountId($this->mediaAccount->getId());
                                $summary->setDateAdded($since);
                                $summary->setDateModified(new \DateTime());
                                $summary->setProvider(MediaAccount::PROVIDER_FACEBOOK);
                                $summary->setProviderAccountId($accountData['id']);
                                $summary->setProviderAccountName($accountData['name']);
                                $summary->setCpm(floatval($accountData['cpm']));
                                $summary->setCpc(floatval($accountData['cpc']));
                                $summary->setCtr(floatval($accountData['ctr']));
                                $summary->setClicks(intval($accountData['clicks']));
                                $summary->setCurrency($accountData['currency']);
                                $summary->setSpend(floatval($accountData['spend']));
                                $summary->setImpressions(intval($accountData['impressions']));
                                // Set to false by default until we've correlated the result.
                                $summary->setComplete(false);
                                $summary->setFinal(false);
                                $summary->setFinalDate($summary->getDateAdded()->modify('+'.self::$ageDataIsFinal));
                                $summary->setProviderDate($since->format(\DateTime::ISO8601));
                                $this->addSummaryToQueue($summary);
                            }
                        }
                    },
                    false
                );
                $this->output->writeln(' - '.$accountData['currency'].' '.$spend);
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
     * Get ALL accounts with minimal data upon which we can filter out invalid accounts on a second call.
     *
     * @param $callback
     *
     * @return $this
     *
     * @throws \Exception
     */
    private function getAllAdAccounts($callback)
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
        // This call cannot be filtered by params.
        $params = [
            'limit' => self::$pageLimit,
        ];

        do {
            try {
                if (!$cursor) {
                    /** @var \FacebookAds\Cursor $cursor */
                    $cursor = $this->facebookUser->getAdAccounts($fields, $params);
                    $cursor->setUseImplicitFetch(self::$facebookImplicitFetch);
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
                    $this->output->write(':');
                    sleep(self::$rateLimitSleep);
                } else {
                    // Unexpected error
                    $this->output->write('!');
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
     * @param null      $cursor
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
        $queueJobs = false,
        $cursor = null
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
            return $this->queueInsightJob($account, $accountId, $fields, $params, $callback, $cursor);
        }

        do {
            try {
                if (!$cursor) {
                    /** @var \FacebookAds\Cursor $cursor */
                    $cursor = $account->getInsights($fields, $params);
                    $cursor->setUseImplicitFetch(self::$facebookImplicitFetch);
                }
                $data = $cursor->current();
                if ($data) {
                    $data = $data->getData();
                    // echo $data['date_start'].' '.$data['hourly_stats_aggregated_by_advertiser_time_zone'].' --- '.$data['adset_name'].PHP_EOL;
                    if ($data) {
                        if ($callback($data)) {
                            $cursor->next();
                            sleep(self::$betweenOpSleep);
                            break;
                        }
                    }
                }
                if (
                    !empty($headers['x-ad-account-usage'])
                    && ($limits = json_decode($headers['x-ad-account-usage'], true))
                    && (
                        (!empty($limits['app_id_util_pct']) && $limits['app_id_util_pct'] > 90)
                        || (!empty($limits['acc_id_util_pct']) && $limits['acc_id_util_pct'] > 90)
                    )
                ) {
                    // Preemptive sleep before we hit the rate limit.
                    $this->saveQueue();
                    $this->output->write('.');
                    sleep(self::$rateLimitSleep / 2);
                } else {
                    sleep(self::$betweenOpSleep);
                }
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
                        return $this->queueInsightJob($account, $accountId, $fields, $params, $callback, $cursor);
                    } else {
                        // We've already been rate limited, let's add delays now so that we don't miss our cursor.
                        $this->saveQueue();
                        $this->output->write(':');
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
     * @param      $account
     * @param      $accountId
     * @param      $fields
     * @param      $params
     * @param      $callback
     * @param null $cursor
     *
     * @return $this
     */
    private function queueInsightJob($account, $accountId, $fields, $params, $callback, $cursor = null)
    {
        if (!$accountId) {
            $this->output->writeln('Account ID missing.');

            return $this;
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
        $job->cursor                             = $cursor ? clone $cursor : null;
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
                        false,
                        $j->cursor
                    );
                }
            }
        }
    }
}
