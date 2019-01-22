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
    private static $snapchatAuthUri = 'https://accounts.snapchat.com/login/oauth2/authorize';

    /**
     * @param $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri)
    {
        $result = '';
        $this->session->set('mautic.media.helper.snapchat.auth', null);

        $authorization = $this->session->get('mautic.media.helper.snapchat.auth');
        if (!$authorization || !isset($authorization->Authentication) || !$authorization->RefreshToken) {
            if ($redirectUri && $this->providerClientId && $this->providerClientSecret) {
                $authentication               = new \stdClass();
                $authentication->ClientId     = $this->providerClientId;
                $authentication->ClientSecret = $this->providerClientId;
                $authentication->RedirectUri  = $redirectUri;
                $authentication->State        = $this->createState();

                $authorization                 = new \stdClass();
                $authorization->Authentication = $authentication;
                $authorization->RefreshToken   = null;

                $this->session->set('mautic.media.helper.snapchat.auth', $authorization);
                $this->session->set('mautic.media.helper.snapchat.state', $authorization->Authentication->State);
            }
        }
        if (isset($authorization->Authentication)) {
            $params = [
                'response_type' => 'code',
                'client_id'     => $authorization->Authentication->ClientId,
                'redirect_uri'  => $authorization->Authentication->RedirectUri,
                'scope'         => self::$snapchatScope,
                'state'         => $authorization->Authentication->State,
            ];
            $result = self::$snapchatAuthUri.'?'.http_build_query($params);
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
        $result        = false;
        $authorization = $this->session->get('mautic.media.helper.snapchat.auth');
        if (
            $authorization
            && isset($authorization->Authentication)
            && !empty($params['code'])
            && !empty($params['state'])
            && $params['state'] == $authorization->Authentication->State
        ) {
            if (
                $authorization->RefreshToken !== $params['code']
                || $this->mediaAccount->getRefreshToken() !== $params['code']
            ) {
                $authorization->RefreshToken = $params['code'];
                $this->session->set('mautic.media.helper.snapchat.auth', $authorization);

                $this->mediaAccount->setRefreshToken($params['code']);
                $this->saveMediaAccount();
            }

            $result = true;
        }

        return $result;
    }
}
