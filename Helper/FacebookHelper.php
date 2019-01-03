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
use FacebookAds\Cursor;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdAccountUser;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use Symfony\Component\Console\Output\OutputInterface;


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
    private $providerAccountId;

    /** @var string */
    private $mediaAccountId;

    /** @var Output */
    private $output;

    /**
     * FacebookHelper constructor.
     *
     * @param                 $mediaAccountId
     * @param                 $providerAccountId
     * @param                 $providerClientId
     * @param                 $providerClientSecret
     * @param                 $providerToken
     * @param OutputInterface $output
     */
    public function __construct(
        $mediaAccountId,
        $providerAccountId,
        $providerClientId,
        $providerClientSecret,
        $providerToken,
        OutputInterface $output
    ) {
        // DO NOT COMMIT
        $providerAccountId = '313654038840837';
        $providerToken     = 'EAAGEgprZBDWUBAJgUA8VqW4wt7E0RHumsEqa4JOIoHCHdeRRZBUAFq6NSeDQueJBuHZAp1wh92sMB33mjzQPUxhMrxGTMuRhoBcBdYpATZBBs9gs6bturePHYbkb1zOtkNBi3DZBYFS5W2uCX3IT0sQEWi64Jqj8zPCpiefEjQQZDZD';

        $this->mediaAccountId    = $mediaAccountId;
        $this->output            = $output;
        $this->providerAccountId = $providerAccountId;

        Api::init($providerClientId, $providerClientSecret, $providerToken);
        $this->client = Api::instance();
        // $this->client->setLogger(new CurlLogger());
        Cursor::setDefaultUseImplicitFetch(true);
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function pullData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        $this->authenticate();

        // Fields to retrieve and what we want to call them internally
        $fields = [
            // 'ad_id',
            // 'ad_name',
            // 'adset_id',
            // 'adset_name',
            'campaign_id',
            'campaign_name',
            'spend',
            'cpp',
            'cpm',
            // 'clicks'
        ];
        $params = [
            // We can go down to Ad level if needed...
            'level'      => 'campaign',
            // Filter out any data without costs associated.
            'filtering'  => [
                [
                    'field'    => 'spend',
                    'operator' => 'GREATER_THAN',
                    'value'    => '0',
                ],
            ],
            // Hourly breakdown is the most granular available.
            'breakdowns' => [
                'hourly_stats_aggregated_by_advertiser_time_zone',
            ],
            // We'll set time_range later when we know the appropriate timezone.
        ];

        // Loop through all Ad accounts this user has access to.
        $stats = [];
        /** @var AdAccount $account */
        foreach ($this->user->getAdAccounts() as $account) {
            $spend       = 0;
            $accountData = $account->getSelf(['id', 'timezone_name', 'name', 'currency'])->getData();
            $this->output->write(
                'Pulling from Facebook - '.$accountData['name'].' - '.$dateFrom->format(
                    'Y-m-d'
                ).' to '.$dateTo->format('Y-m-d')
            );
            $timezone = new \DateTimeZone($accountData['timezone_name']);
            $since    = clone $dateFrom;
            $until    = clone $dateTo;
            $since->setTimeZone($timezone);
            $until->setTimeZone($timezone);
            // Specify the time_range in the relative timezone of the Ad account to make sure we get back the data we need.
            $params['time_range'] = [
                'since' => $since->format('Y-m-d'),
                'until' => $until->format('Y-m-d'),
            ];
            foreach ($account->getInsights($fields, $params) as $insight) {
                $data = $insight->getData();
                // Convert the date to our standard.
                $time = substr($data['hourly_stats_aggregated_by_advertiser_time_zone'], 0, 8);
                $date = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $data['date_start'].' '.$time,
                    $timezone
                );
                $stat = new Stat();
                $stat->setProvider(MediaAccount::PROVIDER_FACEBOOK);
                // @todo - To be mapped based on settings of the Media Account.
                // $stat->setCampaignId(0);
                $stat->setProviderCampaignId(!empty($data['campaign_id']) ? $data['campaign_id'] : '');
                $stat->setProviderCampaignName(!empty($data['campaign_name']) ? $data['campaign_name'] : '');
                $stat->setProviderAccountId($accountData['id']);
                $stat->setproviderAccountName($accountData['name']);
                $stat->setDateAdded($date);
                $stat->setSpend(!empty($data['spend']) ? floatval($data['spend']) : 0);
                $stat->setCpm(!empty($data['cpm']) ? floatval($data['cpm']) : 0);
                $stat->setCpc(!empty($data['cpc']) ? floatval($data['cpc']) : 0);
                $stats[] = $stat;
                $spend   += $data['spend'];
            }
            $this->output->writeln(' - '.$accountData['currency'].' '.$spend);
        }

        return $stats;
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
                'Cannot discern Facebook user for account '.$this->providerproviderAccountId.'. You likely need to reauthenticate.'
            );
        }
        $this->output->writeln('Logged in to Facebook as '.strip_tags($me['name']));
        $this->user = new AdAccountUser($me['id']);
    }
}
