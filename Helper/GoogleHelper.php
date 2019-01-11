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

use Doctrine\ORM\EntityManager;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\Reporting\v201809\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDefinition;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDefinitionDateRangeType;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDownloader;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\AdsApi\AdWords\v201809\cm\DateRange;
use Google\AdsApi\AdWords\v201809\cm\Paging;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201809\cm\ReportDefinitionReportType;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerService;
use Google\AdsApi\Common\Configuration;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GoogleHelper.
 */
class GoogleHelper
{
    /** @var int Number of rate limit errors after which we abort. */
    public static $rateLimitMaxErrors = 5;

    /** @var int Number of seconds to sleep between looping API operations. */
    public static $betweenOpSleep = .2;

    /** @var int Number of seconds to sleep when we hit API rate limits. */
    public static $rateLimitSleep = 10;

    /** @var int */
    public static $pageLimit = 500;

    /** @var AdWordsSession */
    private $client;

    /** @var AdWordsSessionBuilder */
    private $sessionBuilder;

    // /** @var User */
    // private $user;

    /** @var string */
    private $providerAccountId;

    /** @var string */
    private $mediaAccountId;

    /** @var OutputInterface */
    private $output;

    /** @var array */
    private $errors = [];

    /** @var EntityManager */
    private $em;

    /** @var array */
    private $stats = [];

    /** @var CampaignSettingsHelper */
    private $campaignSettingsHelper;

    /**
     * GoogleHelper constructor.
     *
     * @param                        $mediaAccountId
     * @param                        $providerAccountId
     * @param                        $providerClientId
     * @param                        $providerClientSecret
     * @param                        $providerToken
     * @param OutputInterface        $output
     * @param EntityManager          $em
     * @param CampaignSettingsHelper $campaignSettingsHelper
     */
    public function __construct(
        $mediaAccountId,
        $providerAccountId,
        $providerClientId,
        $providerClientSecret,
        $providerToken,
        OutputInterface $output,
        EntityManager $em,
        CampaignSettingsHelper $campaignSettingsHelper
    ) {
        $this->mediaAccountId         = $mediaAccountId;
        $this->providerAccountId      = $providerAccountId;
        $this->output                 = $output;
        $this->em                     = $em;
        $this->campaignSettingsHelper = $campaignSettingsHelper;



        $configuration    = new Configuration(
            [
                'ADWORDS'           => [
                    // 'developerToken'   => $providerDeveloperToken,
                    // 'clientCustomerId' => $providerCustomerId,
                    'userAgent' => 'Mautic',
                    // 'isPartialFailure'            => false,
                    // 'includeUtilitiesInUserAgent' => true,
                ],
                'ADWORDS_REPORTING' => [
                    // 'isSkipReportHeader'          => false,
                    // 'isSkipColumnHeader'          => false,
                    // 'isSkipReportSummary'         => false,
                    // 'isUseRawEnumValues'          => false,
                ],
                'OAUTH2'            => [
                    'clientId'     => $providerClientId,
                    'clientSecret' => $providerClientSecret,
                    // 'refreshToken' => $providerRefreshToken,
                    'accessToken'  => $providerToken
                    // 'jsonKeyFilePath'             => 'INSERT_ABSOLUTE_PATH_TO_OAUTH2_JSON_KEY_FILE_HERE',
                    // 'scopes'                      => 'https://www.googleapis.com/auth/adwords',
                    // 'impersonatedEmail'           => 'INSERT_EMAIL_OF_ACCOUNT_TO_IMPERSONATE_HERE',
                ],
                'SOAP'              => [
                    // 'compressionLevel'            => null
                ],
                'CONNECTION'        => [
                    // 'proxy'                       => 'protocol://user:pass@host:port',
                    'enableReportingGzip' => true,
                ],
                'LOGGING'           => [
                    // 'soapLogFilePath'             => 'path/to/your/soap.log',
                    // 'soapLogLevel'                => 'INFO',
                    // 'reportDownloaderLogFilePath' => 'path/to/your/report-downloader.log',
                    // 'reportDownloaderLogLevel'    => 'INFO',
                    // 'batchJobsUtilLogFilePath'    => 'path/to/your/bjutil.log',
                    // 'batchJobsUtilLogLevel'       => 'INFO',
                ],
            ]
        );
        $oAuth2Credential = (new OAuth2TokenBuilder())->from($configuration)->build();
        $this->sessionBuilder     = (new AdWordsSessionBuilder())->from($configuration)->withOAuth2Credential(
            $oAuth2Credential
        );


        // $filePath = sprintf(
        //     '%s.csv',
        //     tempnam(sys_get_temp_dir(), 'criteria-report-')
        // );
        // self::runExample($this->client, $filePath);

        // Api::init($providerClientId, $providerClientSecret, $providerToken);
        // $this->client = Api::instance();

        // $this->client->setLogger(new \FacebookAds\Logger\CurlLogger());
        // Cursor::setDefaultUseImplicitFetch(true);
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
        try {

            // Create selector.
            $selector = new Selector();
            // @todo - Correlate against Facebook fields, see if we have the best lineup.
            $selector->setFields(
                [
                    'CampaignId',
                    'CampaignName',
                    'AdGroupId',
                    'AdGroupName',
                    'Id',
                    'LongHeadline',
                    'Labels',
                    'Date',
                    'Impressions',
                    'Clicks',
                    'Cost',
                    'CostPerConversion',
                ]
            );
            $dateRange = new DateRange($dateFrom->format('Ymd'), $dateTo->format('Ymd'));
            $selector->setDateRange($dateRange);

            // Create report definition.
            $reportDefinition = new ReportDefinition();
            $reportDefinition->setSelector($selector);
            $reportDefinition->setReportName(
                'Mautic auto-generated report #'.uniqid()
            );
            $reportDefinition->setDateRangeType(
                ReportDefinitionDateRangeType::CUSTOM_DATE
            );
            $reportDefinition->setReportType(
                ReportDefinitionReportType::ADGROUP_PERFORMANCE_REPORT
            );
            $reportDefinition->setDownloadFormat(DownloadFormat::GZIPPED_CSV);



            // // Download report.
            // $reportDownloader       = new ReportDownloader($this->client);
            // $reportSettingsOverride = (new ReportSettingsBuilder())->includeZeroImpressions(false)->build();
            // $reportDownloadResult   = $reportDownloader->downloadReport(
            //     $reportDefinition,
            //     $reportSettingsOverride
            // );
            // $reportString           = $reportDownloadResult->getAsString();
            // die($reportString);


            $customerIds = self::getAllManagedCustomerIds();
            printf(
                "Downloading reports for %d managed customers.\n",
                count($customerIds)
            );


            $successfulReports = [];
            $failedReports = [];
            foreach ($customerIds as $customerId) {
                // Construct an API session for the specified client customer ID.
                $session = $this->sessionBuilder->withClientCustomerId($customerId)->build();
                $reportDownloader = new ReportDownloader($session);
                $retryCount = 0;
                $doContinue = true;
                do {
                    $retryCount++;
                    try {
                        // Optional: If you need to adjust report settings just for this one
                        // request, you can create and supply the settings override here.
                        // Otherwise, default values from the configuration file
                        // (adsapi_php.ini) are used.
                        $reportSettingsOverride = (new ReportSettingsBuilder())->includeZeroImpressions(false)->build();
                        $reportDownloadResult = $reportDownloader->downloadReport(
                            $reportDefinition,
                            $reportSettingsOverride
                        );
                        $reportString = $reportDownloadResult->getAsString();
                        $successfulReports[$customerId] = $reportString;
                        $doContinue = false;
                    } catch (ApiException $e) {
                        printf(
                            "Report attempt #%d for client customer ID %d was not downloaded due to: %s\n",
                            $retryCount,
                            $customerId,
                            $e->getMessage()
                        );
                        if ($e->getErrors() === null && $retryCount < self::$rateLimitMaxErrors) {
                            $sleepTime = $retryCount * self::$rateLimitSleep;
                            printf(
                                "Sleeping %d seconds before retrying report for client customer ID %d.\n",
                                $sleepTime,
                                $customerId
                            );
                            sleep($sleepTime);
                        } else {
                            printf(
                                "Report request failed for client customer ID %d.\n",
                                $customerId
                            );
                            $failedReports[$customerId] = '';
                            $doContinue = false;
                        }
                    }
                } while ($doContinue === true);
            }
            print "All downloads completed. Results:\n";
            print "Successful reports:\n";
            foreach ($successfulReports as $customerId => $filePath) {
                printf("\tClient ID %d => '%s'\n", $customerId, $filePath);
            }
            print "Failed reports:\n";
            foreach ($failedReports as $customerId => $filePath) {
                printf("\tClient ID %d => '%s'\n", $customerId, $filePath);
            }
            print "End of results.\n";


            // $this->authenticate();
            // $accounts = $this->getActiveAccounts($dateFrom, $dateTo);
            // if (!$accounts) {
            //     return $this->stats;
            // }
            //
            // // Using the active accounts, go backwards through time one day at a time to pull hourly data.
            // $date   = clone $dateTo;
            // $oneDay = new \DateInterval('P1D');
            // while ($date >= $dateFrom) {
            //     /** @var AdAccount $account */
            //     foreach ($accounts as $account) {
            //         $spend    = 0;
            //         $self     = $account->getData();
            //         $timezone = new \DateTimeZone($self['timezone_name']);
            //         $since    = clone $date;
            //         $until    = clone $date;
            //         $this->output->write(
            //             MediaAccount::PROVIDER_FACEBOOK.' - Pulling hourly data - '.
            //             $since->format('Y-m-d').' - '.
            //             $self['name']
            //         );
            //         $since->setTimeZone($timezone);
            //         $until->setTimeZone($timezone)->add($oneDay);
            //
            //         // Specify the time_range in the relative timezone of the Ad account to make sure we get back the data we need.
            //         $fields = [
            //             'ad_id',
            //             'ad_name',
            //             'adset_id',
            //             'adset_name',
            //             'campaign_id',
            //             'campaign_name',
            //             'spend',
            //             'cpm',
            //             'cpc',
            //             'cpp', // Always null at ad level?
            //             'ctr',
            //             'impressions',
            //             'clicks',
            //             'reach', // Always null at ad level?
            //         ];
            //         $params = [
            //             'level'      => 'ad',
            //             // 'filtering'  => [
            //             //     [
            //             //         'field'    => 'spend',
            //             //         'operator' => 'GREATER_THAN',
            //             //         'value'    => '0',
            //             //     ],
            //             // ],
            //             'breakdowns' => [
            //                 'hourly_stats_aggregated_by_advertiser_time_zone',
            //             ],
            //             'time_range' => [
            //                 'since' => $since->format('Y-m-d'),
            //                 'until' => $until->format('Y-m-d'),
            //             ],
            //         ];
            //         $this->getInsights(
            //             $account,
            //             $fields,
            //             $params,
            //             function ($data) use (&$spend, $timezone, $self) {
            //                 // Convert the date to our standard.
            //                 $time = substr($data['hourly_stats_aggregated_by_advertiser_time_zone'], 0, 8);
            //                 $date = \DateTime::createFromFormat(
            //                     'Y-m-d H:i:s',
            //                     $data['date_start'].' '.$time,
            //                     $timezone
            //                 );
            //                 $stat = new Stat();
            //                 $stat->setMediaAccountId($this->mediaAccountId);
            //
            //                 $stat->setDateAdded($date);
            //
            //                 $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
            //                     $self['id'],
            //                     $data['campaign_id']
            //                 );
            //                 if (is_int($campaignId)) {
            //                     $stat->setCampaignId($campaignId);
            //                 }
            //
            //                 $provider = MediaAccount::PROVIDER_FACEBOOK;
            //                 $stat->setProvider($provider);
            //
            //                 $stat->setProviderAccountId($self['id']);
            //                 $stat->setproviderAccountName($self['name']);
            //
            //                 $stat->setProviderCampaignId($data['campaign_id']);
            //                 $stat->setProviderCampaignName($data['campaign_name']);
            //
            //                 $stat->setProviderAdsetId($data['adset_id']);
            //                 $stat->setproviderAdsetName($data['ad_name']);
            //
            //                 $stat->setProviderAdId($data['ad_id']);
            //                 $stat->setproviderAdName($data['ad_name']);
            //
            //                 $stat->setCurrency($self['currency']);
            //                 $stat->setSpend(floatval($data['spend']));
            //                 $stat->setCpm(floatval($data['cpm']));
            //                 $stat->setCpc(floatval($data['cpc']));
            //                 $stat->setCpp(floatval($data['cpp']));
            //                 $stat->setCtr(floatval($data['ctr']));
            //                 $stat->setImpressions(intval($data['impressions']));
            //                 $stat->setClicks(intval($data['clicks']));
            //                 $stat->setReach(intval($data['reach']));
            //
            //                 // Don't bother saving stat records without valuable data.
            //                 if (
            //                     $stat->getSpend()
            //                     || $stat->getCpm()
            //                     || $stat->getCpc()
            //                     || $stat->getCpp()
            //                     || $stat->getCtr()
            //                     || $stat->getImpressions()
            //                     || $stat->getClicks()
            //                     || $stat->getReach()
            //                 ) {
            //                     // Uniqueness to match the unique_by_ad constraint.
            //                     $key               = implode(
            //                         '|',
            //                         [
            //                             $date->getTimestamp(),
            //                             $provider,
            //                             $this->mediaAccountId,
            //                             $data['ad_id'],
            //                         ]
            //                     );
            //                     $this->stats[$key] = $stat;
            //                     if (0 === count($this->stats) % 100) {
            //                         $this->saveQueue();
            //                     }
            //                     $spend += $data['spend'];
            //                 }
            //             }
            //         );
            //         $this->output->writeln(' - '.$self['currency'].' '.$spend);
            //     }
            //     $date->sub($oneDay);
            // }
        } catch (\Exception $e) {
            $this->output->writeln('<error>'.MediaAccount::PROVIDER_FACEBOOK.' - '.$e->getMessage().'</error>');
        }
        $this->saveQueue();

        return $this->stats;
    }

    /**
     * Retrieves all the customer IDs under a manager account.
     *
     * @return array
     */
    private function getAllManagedCustomerIds()
    {
        $customerIds            = [];
        $managedCustomerService = (new AdWordsServices())->get($this->sessionBuilder->build(), ManagedCustomerService::class);
        $selector               = new Selector();
        $selector->setFields(['CustomerId']);
        $selector->setPaging(new Paging(0, self::$pageLimit));
        $selector->setPredicates(
            [
                new Predicate(
                    'CanManageClients',
                    PredicateOperator::EQUALS,
                    ['false']
                ),
            ]
        );
        $totalNumEntries = 0;
        do {
            $page = $managedCustomerService->get($selector);
            if ($page->getEntries() !== null) {
                $totalNumEntries = $page->getTotalNumEntries();
                foreach ($page->getEntries() as $customer) {
                    $customerIds[] = $customer->getCustomerId();
                }
            }
            $selector->getPaging()->setStartIndex(
                $selector->getPaging()->getStartIndex() + self::$pageLimit
            );
        } while ($selector->getPaging()->getStartIndex() < $totalNumEntries);

        return $customerIds;
    }

    /**
     * Save all the stat entities in queue.
     */
    private function saveQueue()
    {
        if ($this->stats) {
            if (!$this->em->isOpen()) {
                $this->em = $this->em->create(
                    $this->em->getConnection(),
                    $this->em->getConfiguration(),
                    $this->em->getEventManager()
                );
            }
            $this->em->getRepository('MauticMediaBundle:Stat')
                ->saveEntities($this->stats);

            $this->stats = [];
            $this->em->clear(Stat::class);
        }
    }

    /**
     * @throws \Exception
     */
    private function authenticate()
    {


        // Authenticate and get the primary user ID.
        // $me = $this->client->call('/me')->getContent();
        // if (!$me || !isset($me['id'])) {
        //     throw new \Exception(
        //         'Cannot discern Facebook user for account '.$this->providerAccountId.'. You likely need to reauthenticate.'
        //     );
        // }
        // $this->output->writeln('Logged in to Facebook as '.strip_tags($me['name']));
        // $this->user = new AdAccountUser($me['id']);

        return $this->user;
    }
}
