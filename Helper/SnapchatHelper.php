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

use GuzzleHttp\Client;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;

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
        'connect_timeout' => 1,
        'cookies'         => true,
        'http_errors'     => false,
        'synchronous'     => true,
        'verify'          => false,
        'timeout'         => 20,
        'version'         => 1.1,
        'headers'         => null,
    ];

    /** @var JSONHelper */
    private $jsonHelper;

    /**
     * @param $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri = '')
    {
        $result = '';
        $state  = $this->session->get('mautic.media.helper.snapchat.state', $this->createState());
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
     * @return bool|\MauticPlugin\MauticMediaBundle\Entity\MediaAccount|string
     */
    public function authCallback($params)
    {
        $result = false;
        if (
            !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
            && !empty($params['code'])
            && !empty($params['state'])
            && $params['state'] == $this->session->get('mautic.media.helper.snapchat.state')
        ) {
            $result = $this->refreshToken($params['code']);
        }

        return $result;
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
                    $this->saveMediaAccount();
                } catch (\Exception $e) {
                    $this->errors[] = $e->getMepullDatassage();
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
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     *
     * @throws \Exception
     */
    public function pullData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        $organizations = $this->getOrganizations();
        $this->output->writeln(
            MediaAccount::PROVIDER_SNAPCHAT.' - Found '.count(
                $organizations
            ).' organizations for media account '.$this->mediaAccount->getId().'.'
        );

        foreach ($organizations as $organization) {
            if (isset($organization->id)) {
                $accounts = $this->getAdAccounts($organization->id);
                $this->output->writeln(
                    MediaAccount::PROVIDER_SNAPCHAT.' - Found '.count(
                        $accounts
                    ).' accounts in organization '.$organization->name.' ('.$organization->id.').'
                );
                foreach ($accounts as $account) {
                    $ads = $this->getAds($account->id);
                    $this->output->writeln(
                        MediaAccount::PROVIDER_SNAPCHAT.' - Found '.count(
                            $ads
                        ).' ads under ad account '.$account->name.'.'
                    );
                }

            }
        }

        return;
    }

    /**
     * @return mixed|null
     */
    private function getOrganizations()
    {
        return $this->getRequest('/me/organizations?with_ad_accounts?true', 'organizations');
    }

    /**
     * @param string $path
     * @param string $object
     * @param int    $limit
     * @param array  $options
     *
     * @return mixed|null
     */
    private function getRequest($path = '/', $object = '', $limit = 1000, $options = [])
    {
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
                (!$results || !$done)
                // Errors below the limit.
                && count($this->errors) < self::$rateLimitMaxErrors
            ) {
                // Apply standard headers to all requests.
                if (!isset($options['headers'])) {
                    $options['headers'] = [];
                }
                $options['headers']['Content-Type']  = 'application/json';
                $options['headers']['Authorization'] = 'Bearer '.$this->providerToken;
                if ($limit) {
                    if (!isset($options['query'])) {
                        $options['query'] = [];
                    }
                    if (!isset($options['query']['limit'])) {
                        $options['query']['limit'] = $limit;
                    }
                }

                // Make the request
                $request = $this->getClient()->get($uri, $options);
                $status  = $request->getStatusCode();
                if (200 == $status) {
                    // Decode the JSON
                    $json   = $request->getBody()->getContents();
                    $result = $this->getJsonHelper()->decodeObject($json);

                    // Unwrap the desired object and append it to $results.
                    if ($object) {
                        if ($result && isset($result->$object)) {
                            if (
                                substr($object, -1, 1) == 's'
                                && ($subobject = substr($object, 0, strlen($object) - 1))
                                && isset($result->$object[0])
                                && isset($result->$object[0]->$subobject)
                            ) {
                                foreach ($result->$object as $obj) {
                                    if (isset($obj->$subobject)) {
                                        $results[] = $obj->$subobject;
                                    }
                                }
                            } else {
                                $results[] = $result->$object;
                            }
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
                } elseif (401 == $status) {
                    $this->refreshToken();
                    sleep(self::$betweenOpSleep);
                } else {
                    sleep(self::$betweenOpSleep);
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            sleep(self::$betweenOpSleep);
        }

        return $results;
    }

    /**
     * @param string $organizationId
     *
     * @return mixed|null
     */
    private function getAdAccounts($organizationId)
    {
        return $this->getRequest('/organizations/'.$organizationId.'/adaccounts', 'adaccounts');
    }

    /**
     * @param string $adAccountId
     *
     * @return mixed|null
     */
    private function getAds($adAccountId)
    {
        return $this->getRequest('/adaccounts/'.$adAccountId.'/ads', 'ads', 1000);
    }

}
