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

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use stdClass;

/**
 * Class SnapchatHelper.
 *
 * https://developers.snapchat.com/api/docs/?shell#metrics-and-supported-granularities
 *
 * Note: There is currently no SDK for Snapchat. Their Ads API is brand new, so we'll be doing a lot of guzzle here.
 */
class SnapchatHelper extends CommonProviderHelper
{
    /** @var string */
    private static $snapchatScope = 'snapchat-marketing-api';

    /** @var string */
    private static $snapchatAuthorizationUri = 'https://accounts.snapchat.com/login/oauth2/authorize';

    /** @var string */
    private static $snapchatAccessTokenUri = 'https://accounts.snapchat.com/login/oauth2/access_token';

    /** @var string */
    private static $snapchatApiBaseUri = 'https://adsapi.snapchat.com/v1';

    /** @var string Snapchat requires rounded hour times, but otherwise ISO 8601. */
    private static $snapchateDateFormat = 'Y-m-d\TH:00:00.000-00:00';

    /** @var string */
    public $provider = MediaAccount::PROVIDER_SNAPCHAT;

    /** @var Client */
    private $snapchatClient;

    /** @var array */
    private $snapchatGuzzleSettings = [
        'allow_redirects' => [
            'max'       => 5,
            'strict'    => false,
            'referer'   => false,
            'protocols' => ['https', 'http'],
        ],
        'connect_timeout' => 5,
        'cookies'         => true,
        'http_errors'     => false,
        'synchronous'     => true,
        'verify'          => false,
        'timeout'         => 30,
        'version'         => 1.1,
        'headers'         => null,
    ];

    /** @var JSONHelper */
    private $jsonHelper;

    /** @var array Ads by adaccount for fast reference. */
    private $adCache = [];

    /** @var array */
    private $campaignCache = [];

    /** @var array */
    private $adSquadCache = [];

    /**
     * @param $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri = '')
    {
        $result = '';
        $state  = $this->session->get('mautic.media.helper.snapchat.state', $this->getState());
        if (
            $state
            && $redirectUri
            && !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
        ) {
            $this->session->set('mautic.media.helper.snapchat.state', $state);
            $params = [
                'response_type' => 'code',
                'client_id'     => $this->providerClientId,
                'redirect_uri'  => $redirectUri,
                'scope'         => self::$snapchatScope,
                'state'         => $state,
            ];
            $result = self::$snapchatAuthorizationUri.'?'.http_build_query($params);
        }

        return $result;
    }

    /**
     * @param $params
     *
     * @return bool|MediaAccount|string
     */
    public function authCallback($params)
    {
        $success = false;
        if (
            !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
            && !empty($params['code'])
            && !empty($params['state'])
            && $params['state'] == $this->session->get('mautic.media.helper.snapchat.state')
        ) {
            $success = $this->refreshToken($params['code']);
        }

        return $success;
    }

    /**
     * @param null $code
     *
     * @return |null
     */
    private function refreshToken($code = null)
    {
        $success = false;
        if (
            !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
        ) {
            if ($code) {
                $params = [
                    'form_params' => [
                        'code'          => $code,
                        'client_id'     => $this->providerClientId,
                        'client_secret' => $this->providerClientSecret,
                        'grant_type'    => 'authorization_code',
                    ],
                ];
            } elseif (!empty($this->providerRefreshToken)) {
                $params = [
                    'form_params' => [
                        'refresh_token' => $this->providerRefreshToken,
                        'client_id'     => $this->providerClientId,
                        'client_secret' => $this->providerClientSecret,
                        'grant_type'    => 'refresh_token',
                    ],
                ];
            }
        }

        if (isset($params)) {
            $request = $this->getClient()->post(self::$snapchatAccessTokenUri, $params);
            if (200 == $request->getStatusCode()) {
                $json = $request->getBody()->getContents();
                try {
                    $object = $this->getJsonHelper()->decodeObject($json);
                    if (!empty($object->access_token)) {
                        $this->providerToken = $object->access_token;
                        $this->mediaAccount->setToken($this->providerToken);
                        $success = true;
                    }
                    if (!empty($object->refresh_token)) {
                        $this->providerRefreshToken = $object->refresh_token;
                        $this->mediaAccount->setRefreshToken($this->providerRefreshToken);
                        $success = true;
                    }
                    if ($success) {
                        $this->saveMediaAccount();
                    }
                } catch (Exception $e) {
                    $this->errors[] = $e->getMessage();
                }
            }
        }

        return $success;
    }

    /**
     * Get the Snapchat Client.
     */
    private function getClient()
    {
        if (!$this->snapchatClient) {
            $this->snapchatClient = new Client($this->snapchatGuzzleSettings);
        }

        return $this->snapchatClient;
    }

    /**
     * @return JSONHelper
     */
    private function getJsonHelper()
    {
        if (!$this->jsonHelper) {
            $this->jsonHelper = new JSONHelper();
        }

        return $this->jsonHelper;
    }

    /**
     * @return $this|CommonProviderHelper
     */
    public function pullData()
    {
        try {
            $accounts = $this->getAllActiveAccounts($this->getDateFrom(), $this->getDateTo());
            $this->output->writeln(
                $this->provider.' - Found '.count(
                    $accounts
                ).' active accounts in media account '.$this->mediaAccount->getId().'.'
            );

            $date   = $this->getDateTo();
            $oneDay = new DateInterval('P1D');
            while ($date >= $this->getDateFrom()) {
                /** @var AdAccount $account */
                foreach ($accounts as $account) {
                    $isComplete       = true;
                    $spend            = 0;
                    $clicksTotal      = 0;
                    $impressionsTotal = 0;
                    $timezone         = new DateTimeZone($account->timezone);
                    $since            = clone $date;
                    $until            = clone $date;
                    $this->output->write(
                        $this->provider.' - Pulling hourly data - '.
                        $since->format(DateTime::ISO8601).' - '.
                        $account->name
                    );
                    $since->setTimeZone($timezone);
                    $until->setTimeZone($timezone)->add($oneDay);
                    foreach ($this->getActiveCampaigns(
                        $account->id,
                        $this->getDateFrom(),
                        $this->getDateTo()
                    ) as $campaign) {
                        $adStats = $this->getCampaignStats($campaign->id, $since, $until);
                        if (empty($adStats)) {
                            $isComplete = false;
                        }
                        foreach ($adStats as $adStat) {
                            if (!$adStat) {
                                continue;
                            }
                            $stat = new Stat();
                            $stat->setMediaAccountId($this->mediaAccount->getId());

                            $stat->setDateAdded((new DateTime($adStat->start_time)));

                            $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
                                (string) $account->id,
                                (string) $campaign->id,
                                (string) $account->name,
                                (string) $campaign->name
                            );
                            if (is_int($campaignId)) {
                                $stat->setCampaignId($campaignId);
                            }

                            $stat->setProvider($this->provider);

                            $stat->setProviderAccountId($account->id);
                            $stat->setproviderAccountName($account->name);

                            $stat->setProviderCampaignId($campaign->id);
                            $stat->setProviderCampaignName($campaign->name);

                            // Since the stats API doesn't contain other data, we need to pull names sepperately.
                            $adDetails = $this->getAdDetails($account->id, $adStat->id);
                            if (isset($adDetails->ad_squad_id)) {
                                $stat->setProviderAdsetId($adDetails->ad_squad_id);
                                $adSquadDetails = $this->getAdSquadDetails($campaign->id, $adDetails->ad_squad_id);
                                if (isset($adSquadDetails->name)) {
                                    $stat->setproviderAdsetName($adSquadDetails->name);
                                }
                            }

                            $stat->setProviderAdId($adStat->id);
                            if (isset($adDetails->name)) {
                                $stat->setproviderAdName($adDetails->name);
                            }

                            // Definitions:
                            // CPM is total cost for 1k impressions.
                            //      CPM = cost * 1000 / impressions
                            // CPC is the cost per action.
                            //      CPC = cost / clicks
                            // CTR is the click through rate.
                            //      CTR = (clicks / impressions) * 100
                            // For our purposes we are considering swipes as clicks for Snapchat.
                            $clicks      = isset($adStat->swipes) ? intval($adStat->swipes) : 0;
                            $impressions = intval($adStat->impressions);
                            $cost        = floatval($adStat->spend) / 1000000;
                            $cpm         = $impressions ? (($cost * 1000) / $impressions) : 0;
                            $cpc         = $clicks ? ($cost / $clicks) : 0;
                            $ctr         = $impressions ? (($clicks / $impressions) * 100) : 0;
                            $stat->setCurrency($account->currency);
                            $stat->setSpend($cost);
                            $stat->setCpm($cpm);
                            $stat->setCpc($cpc);
                            $stat->setCtr($ctr);
                            $stat->setImpressions($impressions);
                            $impressionsTotal += $impressions;
                            $stat->setClicks($clicks);
                            $clicksTotal += $clicks;

                            $this->addStatToQueue($stat, $spend);
                        }
                    }
                    $spend = round($spend, 2);
                    $this->createSummary(
                        $account->id,
                        $account->name,
                        $account->currency,
                        $date,
                        $spend,
                        $clicksTotal,
                        $impressionsTotal,
                        // @todo - Add validation for completion for Snapchat data sets.
                        $isComplete
                    );
                }
                $date->sub($oneDay);
            }
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        $this->saveQueue();
        $this->outputErrors();

        return $this;
    }

    /**
     * Get all accounts from all organizations that are potentially active within the time frame.
     *
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     *
     * @return array
     *
     * @throws Exception
     */
    private function getAllActiveAccounts(
        DateTime $dateFrom,
        DateTime $dateTo
    ) {
        $accounts = [];
        foreach ($this->getOrganizations() as $organization) {
            if (isset($organization->id)) {
                foreach ($this->getRequest(
                    '/organizations/'.$organization->id.'/adaccounts',
                    'adaccounts'
                ) as $account) {
                    if (
                        // Account Paused or closed before this date range.
                        ('ACTIVE' != $account->status && (new DateTime($account->updated_at)) < $dateFrom)
                        // Created after this date range.
                        || (new DateTime($account->created_at)) > $dateTo
                    ) {
                        continue;
                    }

                    $accounts[] = $account;
                }
            }
        }

        return $accounts;
    }

    /**
     * @return mixed|null
     */
    private function getOrganizations()
    {
        return $this->getRequest('/me/organizations', 'organizations', ['with_ad_accounts' => 'true']);
    }

    /**
     * @param string $path
     * @param string $object
     * @param array  $params
     * @param bool   $limit
     * @param null   $callback
     *
     * @return array
     */
    private function getRequest(
        $path = '/',
        $object = '',
        $params = [],
        $limit = true,
        $callback = null
    ) {
        $result  = null;
        $status  = null;
        $done    = false;
        $results = [];
        $uri     = self::$snapchatApiBaseUri.$path;
        try {
            if (!$this->providerToken) {
                $this->refreshToken();
            }
            while (
                // No results or more to come.
                (!$results && !$done)
                // Errors below the limit.
                && count($this->errors) < self::$rateLimitMaxErrors
            ) {
                // Apply standard headers to all requests.
                $options = [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer '.$this->providerToken,
                    ],
                    'query'   => $limit ? array_merge(['limit' => self::$pageLimit], $params) : $params,
                ];

                // Make the request
                $request = $this->getClient()->get($uri, $options);
                $status  = $request->getStatusCode();
                if (200 == $status) {
                    // Decode the JSON
                    $json   = $request->getBody()->getContents();
                    $result = $this->getJsonHelper()->decodeObject($json);

                    // Unwrap the desired object and append it to $results.
                    if ($object) {
                        if (
                            $result
                            && isset($result->$object)
                            && 's' == substr($object, -1, 1)
                            && ($subobject = substr($object, 0, strlen($object) - 1))
                            && !empty($result->$object[0])
                            && !empty($result->$object[0]->$subobject)
                        ) {
                            foreach ($result->$object as $obj) {
                                if (!empty($obj->$subobject)) {
                                    $results[] = $obj->$subobject;
                                }
                            }
                        } else {
                            // No objects found.
                            $done = true;
                        }
                    } else {
                        $results[] = $result;
                    }

                    // If there are more, keep looping.
                    if ($result
                        && isset($result->paging)
                        && isset($result->paging->next_link)
                    ) {
                        $done = false;
                        $uri  = $result->paging->next_link;
                    } else {
                        $done = true;
                    }

                    // Run callback if defined.
                    if (is_callable($callback)) {
                        if ($callback($results)) {
                            break;
                        }
                    }
                } elseif (401 == $status) {
                    $this->refreshToken();
                    sleep(self::$betweenOpSleep);
                } else {
                    // We're not sure to handle this, nop.
                    $done = true;
                    sleep(self::$betweenOpSleep);
                }
            }
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            sleep(self::$betweenOpSleep);
        }

        return $results;
    }

    /**
     * @param $adAccountId
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function getActiveCampaigns($adAccountId, DateTime $dateFrom, DateTime $dateTo)
    {
        $campaigns = [];
        if (!isset($this->campaignCache[$adAccountId])) {
            $campaigns                         = $this->getRequest(
                '/adaccounts/'.$adAccountId.'/campaigns',
                'campaigns'
            );
            $this->campaignCache[$adAccountId] = $campaigns;
        }

        foreach ($this->campaignCache[$adAccountId] as $campaign) {
            if (
                // Paused or closed before this date range.
                ('ACTIVE' != $campaign->status && (new DateTime($campaign->updated_at)) < $dateFrom)
                // Created after this date range.
                || (new DateTime($campaign->created_at)) > $dateTo
            ) {
                continue;
            }
            $campaigns[] = $campaign;
        }

        return $campaigns;
    }

    /**
     * Get hourly campaign statistics down to the ad level.
     *
     * @param $campaignId
     * @param $since
     * @param $until
     *
     * @return array
     *
     * @throws Exception
     */
    private function getCampaignStats(
        $campaignId,
        $since,
        $until
    ) {
        $adStats = [];
        $fields  = [
            'impressions',
            'spend',
            'swipes',
            // 'conversion_add_cart',
            // 'conversion_add_cart_swipe_up',
            // 'conversion_add_cart_view',
            // 'conversion_purchases',
            // 'conversion_purchases_swipe_up',
            // 'conversion_purchases_view',
        ];
        $params  = [
            'breakdown'                   => 'ad',
            'granularity'                 => 'HOUR',
            'fields'                      => implode(',', $fields),
            // We will typically not be pulling data for 28 days in arrears, so pull one day attributions only.
            'swipe_up_attribution_window' => '1_DAY',
            'view_attribution_window'     => '1_DAY',
            'start_time'                  => $since->format(self::$snapchateDateFormat),
            'end_time'                    => $until->format(self::$snapchateDateFormat),
        ];

        // Stats come back in an odd shape compared to other entities, let's flatten that down to a light weight array.
        $result = $this->getRequest('/campaigns/'.$campaignId.'/stats', 'timeseries_stats', $params, false);
        foreach ($result as $statObj) {
            if (!empty($statObj->breakdown_stats->ad)) {
                foreach ($statObj->breakdown_stats->ad as $ad) {
                    if (isset($ad->timeseries)) {
                        foreach ($ad->timeseries as $timeset) {
                            $adStat             = new stdClass();
                            $adStat->id         = $ad->id;
                            $adStat->start_time = $timeset->start_time;
                            foreach ($fields as $field) {
                                $adStat->{$field} = $timeset->stats->{$field};
                            }
                            $adStats[] = $adStat;
                        }
                    }
                }
            }
        }

        return $adStats;
    }

    /**
     * @param string $adAccountId
     *
     * @return mixed|null
     */
    private function getAdDetails($adAccountId, $adId)
    {
        if (!isset($this->adCache[$adAccountId])) {
            $ads = $this->getRequest('/adaccounts/'.$adAccountId.'/ads', 'ads');
            foreach ($ads as $ad) {
                $this->adCache[$adAccountId][$ad->id] = $ad;
            }
        }

        return isset($this->adCache[$adAccountId][$adId]) ? $this->adCache[$adAccountId][$adId] : null;
    }

    /**
     * @param $campaignId
     * @param $adSquadId
     *
     * @return |null
     */
    private function getAdSquadDetails($campaignId, $adSquadId)
    {
        if (!isset($this->adSquadCache[$campaignId])) {
            $adSquads = $this->getRequest('/campaigns/'.$campaignId.'/adsquads', 'adsquads');
            foreach ($adSquads as $adSquad) {
                $this->adSquadCache[$campaignId][$adSquad->id] = $adSquad;
            }
        }

        return isset($this->adSquadCache[$campaignId][$adSquadId]) ? $this->adSquadCache[$campaignId][$adSquadId] : null;
    }
}
