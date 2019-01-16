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
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\Reporting\v201809\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDefinition;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDefinitionDateRangeType;
use Google\AdsApi\AdWords\Reporting\v201809\ReportDownloader;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\AdsApi\AdWords\v201809\cm\DateRange;
use Google\AdsApi\AdWords\v201809\cm\Paging;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201809\cm\ReportDefinitionReportType;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\mcm\Customer;
use Google\AdsApi\AdWords\v201809\mcm\ManagedCustomerService;
use Google\AdsApi\Common\Configuration;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use Psr\Log\NullLogger;
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

    /** @var AdWordsSessionBuilder */
    private $sessionBuilder;

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

    /** @var array */
    private $configuration = [];

    /** @var array */
    private $sessions = [];

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
        $providerRefreshToken,
        OutputInterface $output,
        EntityManager $em,
        CampaignSettingsHelper $campaignSettingsHelper
    ) {
        $this->mediaAccountId         = $mediaAccountId;
        $this->providerAccountId      = $providerAccountId;
        $this->output                 = $output;
        $this->em                     = $em;
        $this->campaignSettingsHelper = $campaignSettingsHelper;

        $this->configuration = new Configuration(
            [
                'ADWORDS'           => [
                    'developerToken' => $providerToken,
                    // 'clientCustomerId' => $providerCustomerId,
                    'userAgent'      => 'Mautic',
                    // 'isPartialFailure'            => false,
                    // 'includeUtilitiesInUserAgent' => true,
                ],
                'ADWORDS_REPORTING' => [
                    'isSkipReportHeader'       => true,
                    'isSkipColumnHeader'       => true,
                    'isSkipReportSummary'      => true,
                    'isUseRawEnumValues'       => true,
                    'isIncludeZeroImpressions' => false,
                ],
                'OAUTH2'            => [
                    'clientId'     => $providerClientId,
                    'clientSecret' => $providerClientSecret,
                    'refreshToken' => $providerRefreshToken,
                    // 'accessToken'  => $providerToken
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
        $fields = [
            'Date',
            'HourOfDay',
            // 'AdId',
            // 'AdName',
            'AdGroupId',
            'AdGroupName',
            'CampaignId',
            'CampaignName',
            'Cost', // In micros
            'AverageCpm', // In micros
            'AverageCpc', // In micros
            // CPP can be estimated by AverageCpm divided by 1000
            'Ctr',
            'Impressions',
            'Clicks',
            // 'CostPerConversion', Currently excluding CpCo, since we'd need to decide a timeframe.
            // Cannot discern reach, we should likely deprecate it.
        ];
        try {
            $customers = $this->getAllManagedCustomers();
            if (!$customers) {
                throw new \Exception(
                    'No Google Ads customer accounts accessible by media account '.$this->providerAccountId.'.'
                );
            }
            $this->output->writeln(
                MediaAccount::PROVIDER_GOOGLE.' - Found '.count(
                    $customers
                ).' accounts active for media account '.$this->mediaAccountId.'.'
            );

            // Using the active accounts, go backwards through time one day at a time to pull hourly data.
            $date   = clone $dateTo;
            $oneDay = new \DateInterval('P1D');
            while ($date >= $dateFrom) {
                foreach ($customers as $customerId => $customer) {
                    $spend = 0;
                    /** @var Customer $customer */
                    $timezone = new \DateTimeZone($customer->getDateTimeZone());
                    $since    = clone $date;
                    $until    = clone $date;
                    $since->setTimeZone($timezone);
                    $until->setTimeZone($timezone)->add($oneDay);
                    $this->output->write(
                        MediaAccount::PROVIDER_GOOGLE.' - Pulling hourly data - '.
                        $since->format('Y-m-d').' - '.
                        $customer->getName()
                    );

                    $reportDefinition = new ReportDefinition();
                    $selector         = new Selector();
                    $selector->setFields($fields);
                    $selector->setDateRange((new DateRange($since->format('Ymd'), $until->format('Ymd'))));
                    $reportDefinition->setDateRangeType(
                        ReportDefinitionDateRangeType::CUSTOM_DATE
                    );
                    $selector->setPredicates(
                        [
                            new Predicate(
                                'Cost',
                                PredicateOperator::GREATER_THAN,
                                ['0']
                            ),
                        ]
                    );

                    // Sorting is not currently supported for reports.
                    // $selector->setOrdering(
                    //     [
                    //         (new OrderBy('Date', SortOrder::DESCENDING)),
                    //         (new OrderBy('HourOfDay', SortOrder::DESCENDING)),
                    //     ]
                    // );
                    $reportDefinition->setSelector($selector);
                    $reportDefinition->setReportName(
                        'Mautic auto-generated report #'.uniqid()
                    );
                    $reportDefinition->setReportType(
                        ReportDefinitionReportType::ADGROUP_PERFORMANCE_REPORT
                    );
                    $reportDefinition->setDownloadFormat(DownloadFormat::GZIPPED_CSV);

                    // Construct an API session for the specified client customer ID.
                    $session          = $this->getSession($customerId);
                    $reportDownloader = new ReportDownloader($session);
                    $retryCount       = 0;
                    $doContinue       = true;
                    do {
                        ++$retryCount;
                        try {
                            $reportString = $reportDownloader->downloadReport($reportDefinition)->getAsString();
                            if ($reportString
                                && DownloadFormat::GZIPPED_CSV === $reportDefinition->getDownloadFormat()
                            ) {
                                $reportString = gzdecode($reportString);
                            }
                            // Step backwards through the report.
                            if ($reportString) {
                                foreach (array_reverse(explode("\n", $reportString)) as $line) {
                                    if (!$line) {
                                        continue;
                                    }
                                    $data = array_combine($fields, array_slice(str_getcsv($line), 0, count($fields)));
                                    if (!$data) {
                                        continue;
                                    }
                                    $date = \DateTime::createFromFormat(
                                        'Y-m-d H:i:s',
                                        $data['Date'].' '.$data['HourOfDay'].':00:00',
                                        $timezone
                                    );
                                    $stat = new Stat();
                                    $stat->setMediaAccountId($this->mediaAccountId);

                                    $stat->setDateAdded($date);

                                    $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
                                        (string) $customerId,
                                        $data['CampaignId'],
                                        (string) $customer->getName(),
                                        $data['CampaignName']
                                    );
                                    if (is_int($campaignId)) {
                                        $stat->setCampaignId($campaignId);
                                    }

                                    $provider = MediaAccount::PROVIDER_GOOGLE;
                                    $stat->setProvider($provider);

                                    $stat->setProviderAccountId($customerId);
                                    $stat->setproviderAccountName($customer->getName());

                                    $stat->setProviderCampaignId($data['CampaignId']);
                                    $stat->setProviderCampaignName($data['CampaignName']);

                                    $stat->setProviderAdsetId($data['AdGroupId']);
                                    $stat->setproviderAdsetName($data['AdGroupName']);

                                    // Google doesn't provide ad-level spend on an hourly basis, so we will use adgroups instead.
                                    // $stat->setProviderAdId('');
                                    // $stat->setproviderAdName('');

                                    $stat->setCurrency($customer->getCurrencyCode());
                                    $stat->setSpend(floatval($data['Cost']) / 1000000);
                                    $stat->setCpm(floatval($data['AverageCpm']) / 1000000);
                                    $stat->setCpc(floatval($data['AverageCpc']) / 1000000);
                                    $stat->setCpp(floatval($data['AverageCpm']) / 1000000 / 1000);
                                    $stat->setCtr(floatval($data['Ctr']));
                                    $stat->setImpressions(intval($data['Impressions']));
                                    $stat->setClicks(intval($data['Clicks']));

                                    // $stat->setReach(intval($data['reach']));

                                    // Don't bother saving stat records without valuable data.
                                    if (
                                        $stat->getSpend()
                                        || $stat->getCpm()
                                        || $stat->getCpc()
                                        || $stat->getCpp()
                                        || $stat->getCtr()
                                        // || $stat->getImpressions()
                                        // || $stat->getClicks()
                                        // || $stat->getReach()
                                    ) {
                                        // Uniqueness to match the unique_by_ad constraint.
                                        $key               = implode(
                                            '|',
                                            [
                                                $date->getTimestamp(),
                                                $stat->getProvider(),
                                                $stat->getMediaAccountId(),
                                                $stat->getProviderAdsetId(),
                                                $stat->getProviderAdId(),
                                            ]
                                        );
                                        $this->stats[$key] = $stat;
                                        if (0 === count($this->stats) % 100) {
                                            $this->saveQueue();
                                        }
                                        $spend += $stat->getSpend();
                                    }
                                }
                            }

                            $doContinue = false;
                        } catch (ApiException $e) {
                            $this->saveQueue();
                            if (null === $e->getErrors() && $retryCount < self::$rateLimitMaxErrors) {
                                $this->output->write('.');
                                sleep(self::$rateLimitSleep);
                            } else {
                                throw new \Exception('Too many request errors.');
                            }
                        }
                    } while (true === $doContinue);
                    $this->output->writeln(' - '.$customer->getCurrencyCode().' '.$spend);
                }
                $date->sub($oneDay);
            }
        } catch (\Exception $e) {
            $this->output->writeln('<error>'.MediaAccount::PROVIDER_GOOGLE.' - '.$e->getMessage().'</error>');
        }
        $this->saveQueue();

        return $this->stats;
    }

    /**
     * Retrieves all the customers under a manager account.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getAllManagedCustomers()
    {
        $customers = [];
        /** @var AdWordsServices $managedCustomerService */
        $managedCustomerService = (new AdWordsServices())->get(
            $this->getSession(),
            ManagedCustomerService::class
        );
        $selector               = new Selector();
        $selector->setFields(['CustomerId', 'CurrencyCode', 'DateTimeZone', 'Name']);
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
            if (null !== $page->getEntries()) {
                $totalNumEntries = $page->getTotalNumEntries();
                foreach ($page->getEntries() as $customer) {
                    $customers[$customer->getCustomerId()] = $customer;
                }
            }
            $selector->getPaging()->setStartIndex(
                $selector->getPaging()->getStartIndex() + self::$pageLimit
            );
        } while ($selector->getPaging()->getStartIndex() < $totalNumEntries);

        return $customers;
    }

    /**
     * Authenticate and aquire a session.
     *
     * @param string $customerId
     *
     * @return mixed|null
     *
     * @throws \Exception
     */
    private function getSession($customerId = '')
    {
        if (!isset($this->sessions[$customerId])) {
            // Check mandatory credentials.
            if (
                !$this->configuration->getConfiguration('developerToken', 'ADWORDS')
                || !$this->configuration->getConfiguration('clientId', 'OAUTH2')
                || !$this->configuration->getConfiguration('clientSecret', 'OAUTH2')
                || !$this->configuration->getConfiguration('refreshToken', 'OAUTH2')
            ) {
                throw new \Exception(
                    'Missing credentials for this media account '.$this->mediaAccountId.'.'
                );
            }

            try {
                if (!$this->sessionBuilder) {
                    $oAuth2Credential     = (new OAuth2TokenBuilder())
                        ->from($this->configuration)
                        ->build();
                    $this->sessionBuilder = (new AdWordsSessionBuilder())
                        ->from($this->configuration)
                        ->withOAuth2Credential($oAuth2Credential);

                    // Hide log output.
                    $logger = new NullLogger();
                    $this->sessionBuilder->withSoapLogger($logger)
                        ->withReportDownloaderLogger($logger);
                }
                if ($customerId) {
                    $this->sessions[$customerId] = $this->sessionBuilder->withClientCustomerId($customerId)->build();
                } else {
                    $this->sessions[$customerId] = $this->sessionBuilder->build();
                }
            } catch (\Exception $e) {
                if ($e instanceof \InvalidArgumentException) {
                    throw new \Exception(
                        'Missing credentials for this media account '.$this->mediaAccountId.'. '.$e->getMessage()
                    );
                } else {
                    throw new \Exception(
                        'Cannot establish Google session for media account '.$this->mediaAccountId.'. '.$e->getMessage()
                    );
                }
            }
        }

        return isset($this->sessions[$customerId]) ? $this->sessions[$customerId] : null;
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
}
