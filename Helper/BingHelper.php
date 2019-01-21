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
        /** @var OAuthWebAuthCodeGrant|null $authorization */
        $authorization = $this->session->get('mautic.media.helper.bing.auth');
        if (!$authorization || !isset($authorization->Authentication)) {
            if ($redirectUri && $this->providerClientId && $this->providerClientSecret && $this->providerToken) {
                /** @var OAuthWebAuthCodeGrant $authentication */
                $authentication = (new OAuthWebAuthCodeGrant())
                    ->withClientId($this->providerClientId)
                    ->withClientSecret($this->providerClientSecret)
                    // @todo - change to prod
                    ->withEnvironment(ApiEnvironment::Sandbox)
                    ->withRedirectUri($redirectUri)
                    ->withState(uniqid('mautic_', true));

                /** @var AuthorizationData $authorization */
                $authorization = (new AuthorizationData())
                    ->withAuthentication($authentication)
                    ->withDeveloperToken($this->providerToken);

                $this->session->set('mautic.media.helper.bing.auth', $authorization);
                $this->session->set('mautic.media.helper.bing.state', $authorization->Authentication->State);
            }
        }
        if (isset($authorization->Authentication)) {
            $result = $authorization->Authentication->GetAuthorizationEndpoint();
        }

        return $result;
    }
}
