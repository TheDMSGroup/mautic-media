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

use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdsInsights;
use FacebookAds\Api;
use FacebookAds\Logger\CurlLogger;

/**
 * Class FacebookHelper.
 *
 * https://developers.facebook.com/docs/marketing-api/sdks/#install-facebook-sdks
 */
class FacebookHelper
{

    /** @var \Facebook\Facebook */
    private $client;

    /** @var @var AdUser */
    private $user;

    public function __construct($client_id, $client_secret, $token)
    {
        Api::init($client_id, $client_secret, $token);
        $this->client = Api::instance();
        $this->client->setLogger(new CurlLogger());
    }

    public function pullData()
    {
        $fields = [
            'spend',
            'cost_per_result',
            'cpp',
            'cpm',
            'actions:rsvp',
            'cost_per_action_type:checkin',
            'cost_per_action_type:receive_offer',
            'cost_per_action_type:rsvp',
            'cost_per_action_type:photo_view',
            'cost_per_action_type:post',
            'cost_per_action_type:post_reaction',
            'cost_per_action_type:post_engagement',
            'cost_per_action_type:comment',
            'cost_per_action_type:like',
            'cost_per_action_type:page_engagement',
            'campaign_group_id',
            'campaign_id',
            'adgroup_id',
            'adgroup_name',
            'account_id',
            'account_name',
            'campaign_group_name',
            'campaign_name',
            'schedule',
        ];
        $params = [
            'level'      => 'ad',
            'filtering'  => [],
            'breakdowns' => ['days_1', 'hourly_stats_aggregated_by_advertiser_time_zone', 'ad_id'],
            'time_range' => ['since' => '2018-12-27', 'until' => '2018-12-28'],
        ];
        // echo json_encode((new AdAccount($ad_account_id))->getInsights(
        //     $fields,
        //     $params
        // )->getResponse()->getContent(), JSON_PRETTY_PRINT);

    }
}
