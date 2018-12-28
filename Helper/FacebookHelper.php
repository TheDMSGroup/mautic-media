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
use FacebookAds\Logger\CurlLogger;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdAccountUser;


/**
 * Class FacebookHelper.
 *
 * https://developers.facebook.com/docs/marketing-api/sdks/#install-facebook-sdks
 */
class FacebookHelper
{

    /** @var \Facebook\Facebook */
    private $client;

    /** @var User */
    private $user;

    /** @var string */
    private $accountId;

    public function __construct($account_id, $client_id, $client_secret, $token)
    {
        $this->accountId = $account_id;

        Api::init($client_id, $client_secret, $token);
        $this->client = Api::instance();
        $this->client->setLogger(new CurlLogger());
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function pullData()
    {

        $this->authenticate();

        $fields = [
            'account_id',
            'account_name',
            'ad_id',
            'ad_name',
            'adset_id',
            'adset_name',
            'campaign_id',
            'campaign_name',
            'spend',
            'cpp',
            'cpm',
        ];
        $params = [
            'level'      => 'ad',
            'filtering'  => [],
            'breakdowns' => ['hourly_stats_aggregated_by_advertiser_time_zone'],
            'time_range' => ['since' => '2018-12-27', 'until' => '2018-12-28'],
        ];

        // Retrieve all Ad accounts the current user has access to.
        $accounts = $this->user->getAdAccounts();
        if (!$accounts) {
            throw new \Exception('Could not get accounts for '.$this->accountId);
        }
        foreach ($accounts as $account) {
            $accountData = $account->getData();
            if (!$accountData || !isset($accountData['id'])) {
                throw new \Exception('Could not retrieve an adAccountId under '.$this->accountId);
            }
            $account  = new AdAccount($accountData['id']);
            $insights = $account->getInsights($fields, $params);
            foreach ($insights as $insight) {
                $tmp = 1;
            }
            return null;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    private function authenticate()
    {
        // Authenticate and get the primary user ID.
        $me = $this->client->call('/me')->getContent();
        if (!$me || !isset($me['id'])) {
            throw new \Exception(
                'Cannot discern Facebook user for account '.$this->accountId.'. You likely need to reauthenticate.'
            );
        }
        $this->user = new AdAccountUser($me['id']);
    }
}
