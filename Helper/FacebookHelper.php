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
 * Class FacebookHelper.
 *
 * https://developers.facebook.com/docs/marketing-api/sdks/#install-facebook-sdks
 */
class FacebookHelper
{

    /** @var \Facebook\Facebook */
    private $client;

    /**
     * FacebookHelper constructor.
     *
     * @param $client_id
     * @param $client_secret
     * @param $token
     *
     * @throws \Facebook\Exceptions\FacebookSDKException
     */
    public function __construct($client_id, $client_secret, $token)
    {
        $this->client = new \Facebook\Facebook(
            [
                'app_id'                => $client_id,
                'app_secret'            => $client_secret,
                'default_graph_version' => 'v3.2',
                'default_access_token'  => $token,
            ]
        );
    }

    public function pullData()
    {
    }
}
