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

/**
 * Class SnapchatHelper.
 *
 * https://developers.snapchat.com/api/docs/
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
        $state = $this->session->get('mautic.media.helper.snapchat.state', $this->createState());
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
}
