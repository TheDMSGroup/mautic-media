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

use Microsoft\BingAds\Auth\ApiEnvironment;
use Microsoft\BingAds\Auth\AuthorizationData;
use Microsoft\BingAds\Auth\OAuthWebAuthCodeGrant;

/**
 * Class BingHelper.
 *
 * Requires ClientId, ClientSecret, Token (Developer Token)
 *
 * https://docs.microsoft.com/en-us/bingads/reporting-service/reporting-service-reference?view=bingads-12
 * https://github.com/BingAds/BingAds-PHP-SDK
 */
class BingHelper extends CommonProviderHelper
{
    /**
     * @param $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri)
    {
        $result = '';
        $state  = $this->session->get('mautic.media.helper.bing.state', $this->createState());
        if (
            $state
            && $redirectUri
            && !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
        ) {
            /** @var OAuthWebAuthCodeGrant $authentication */
            $authentication = (new OAuthWebAuthCodeGrant())
                ->withClientId($this->providerClientId)
                ->withClientSecret($this->providerClientSecret)
                // @todo - change to prod
                ->withEnvironment(ApiEnvironment::Production)
                ->withRedirectUri($redirectUri)
                ->withState($state);

            /** @var AuthorizationData $authorization */
            $authorization = (new AuthorizationData())
                ->withAuthentication($authentication)
                ->withDeveloperToken($this->providerToken);

            $this->session->set('mautic.media.helper.bing.auth', $authorization);
            $this->session->set('mautic.media.helper.bing.state', $authorization->Authentication->State);
            if (isset($authorization->Authentication)) {
                $result = $authorization->Authentication->GetAuthorizationEndpoint();
            }
        }

        return $result;
    }

    /**
     * @param $params
     *
     * @return bool
     */
    public function authCallback($params)
    {
        $success = false;
        if (
            !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
            && !empty($params['code'])
            && !empty($params['state'])
            && !empty($params['uri'])
            && $params['state'] == $this->session->get('mautic.media.helper.bing.state')
            /* @var AuthorizationData $authorization */
            && ($authorization = $this->session->get('mautic.media.helper.bing.auth'))
        ) {
            try {
                $tokens = $authorization->Authentication->RequestOAuthTokensByResponseUri($params['uri']);
                if (!empty($tokens->AccessToken)) {
                    $this->providerToken = $tokens->AccessToken;
                    $this->mediaAccount->setToken($this->providerToken);
                    $success = true;
                }
                if (!empty($tokens->RefreshToken)) {
                    $this->providerRefreshToken = $tokens->RefreshToken;
                    $this->mediaAccount->setRefreshToken($tokens->RefreshToken);
                    $success = true;
                }
                if ($success) {
                    $this->saveMediaAccount();
                }
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $success;
    }

    /**
     * @param null $code
     *
     * @return |null
     */
    private function refreshToken($uri = null)
    {
        $success = false;
        // if (
        //     !empty($this->providerClientId)
        //     && !empty($this->providerClientSecret)
        // ) {
        //     if ($code) {
        //         $params = [
        //             'form_params' => [
        //                 'code'          => $code,
        //                 'client_id'     => $this->providerClientId,
        //                 'client_secret' => $this->providerClientSecret,
        //                 'grant_type'    => 'authorization_code',
        //             ],
        //         ];
        //     } elseif (!empty($this->providerRefreshToken)) {
        //         $params = [
        //             'form_params' => [
        //                 'refresh_token' => $this->providerRefreshToken,
        //                 'client_id'     => $this->providerClientId,
        //                 'client_secret' => $this->providerClientSecret,
        //                 'grant_type'    => 'refresh_token',
        //             ],
        //         ];
        //     }
        // }
        //
        // if (isset($params)) {
        //     $request = $this->getClient()->post(self::$snapchatAccessTokenUri, $params);
        //     if (200 == $request->getStatusCode()) {
        //         $json = $request->getBody()->getContents();
        //         try {
        //             $object = $this->getJsonHelper()->decodeObject($json);
        //             if (!empty($object->access_token)) {
        //                 $this->providerToken = $object->access_token;
        //                 $this->mediaAccount->setToken($this->providerToken);
        //                 $success = true;
        //             }
        //             if (!empty($object->refresh_token)) {
        //                 $this->providerRefreshToken = $object->refresh_token;
        //                 $this->mediaAccount->setRefreshToken($this->providerRefreshToken);
        //                 $success = true;
        //             }
        //             $this->saveMediaAccount();
        //         } catch (\Exception $e) {
        //             $this->errors[] = $e->getMepullDatassage();
        //         }
        //     }
        // }
        //
        // return $success;
    }
}
