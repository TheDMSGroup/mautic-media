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

use Guzzle\Http\Client;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use Microsoft\BingAds\Auth\ApiEnvironment;
use Microsoft\BingAds\Auth\AuthorizationData;
use Microsoft\BingAds\Auth\OAuthTokens;
use Microsoft\BingAds\Auth\OAuthWebAuthCodeGrant;
use Microsoft\BingAds\Auth\ServiceClient;
use Microsoft\BingAds\Auth\ServiceClientType;
use Microsoft\BingAds\V12\Bulk\Date;
use Microsoft\BingAds\V12\CustomerManagement\FindAccountsRequest;
use Microsoft\BingAds\V12\CustomerManagement\GetAccountRequest;
use Microsoft\BingAds\V12\CustomerManagement\GetUserRequest;
use Microsoft\BingAds\V12\Reporting\AccountReportScope;
use Microsoft\BingAds\V12\Reporting\AdPerformanceReportColumn;
use Microsoft\BingAds\V12\Reporting\AdPerformanceReportRequest;
use Microsoft\BingAds\V12\Reporting\PollGenerateReportRequest;
use Microsoft\BingAds\V12\Reporting\ReportAggregation;
use Microsoft\BingAds\V12\Reporting\ReportFormat;
use Microsoft\BingAds\V12\Reporting\ReportRequestStatusType;
use Microsoft\BingAds\V12\Reporting\ReportTime;
use Microsoft\BingAds\V12\Reporting\ReportTimeZone;
use Microsoft\BingAds\V12\Reporting\SubmitGenerateReportRequest;

/**
 * Class BingHelper.
 *
 * https://docs.microsoft.com/en-us/bingads/reporting-service/reporting-service-reference?view=bingads-12
 * https://github.com/BingAds/BingAds-PHP-SDK
 */
class BingHelper extends CommonProviderHelper
{
    /** @var string */
    public static $provider = MediaAccount::PROVIDER_BING;

    /** @var string https://help.bingads.microsoft.com/#apex/3/en/54480/2 */
    public static $ageSpendBecomesFinal = '48 hour';

    /** @var array */
    private $bingServices = [];

    /** @var int */
    private $bingCustomerId;

    /** @var int */
    private $bingAccountId;

    /** @var \DateTimeZone */
    private $timezone;

    /** @var string */
    private $timezoneMs;

    /**
     * @return $this|CommonProviderHelper
     */
    public function pullData()
    {
        // microstump reports all billing (spend) data in Pacific time only, so we must use this timezone.
        $this->timezone = new \DateTimeZone('America/Los_Angeles');

        // microspark invented their own timezone names. how inventive.
        $this->timezoneMs = ReportTimeZone::PacificTimeUSCanadaTijuana;

        try {
            $totals = $this->pullDataInBatches();
            $this->output->writeLn('');
            foreach ($totals as $accountId => $days) {
                foreach ($days as $data) {
                    $localDate = clone $data['providerDate'];
                    $localDate->setTimezone($this->timezone);
                    $this->output->write(
                        self::$provider.' - Hourly data result - '.
                        $localDate->format(\DateTime::ISO8601).' - '.
                        $data['accountName']
                    );
                    $this->createSummary(
                        $accountId,
                        $data['accountName'],
                        $data['currencyCode'],
                        $data['providerDate'],
                        $data['spend'],
                        $data['clicksTotal'],
                        $data['impressionsTotal'],
                        true
                    );
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        $this->saveQueue();
        $this->outputErrors();

        return $this;
    }

    /**
     * Pull reports in batches by customer ID.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function pullDataInBatches()
    {
        $totals    = [];
        $customers = $this->getAllManagedCustomers();
        foreach ($customers as $customerId => $accounts) {
            $this->bingCustomerId = $customerId;
            $currencyCode         = 'USD';
            // microstump reports all billing (spend) data in Pacific time only, so for things to line up, we must use this timezone.
            $since = $this->getDateFrom($this->timezone);
            $until = $this->getDateTo($this->timezone);
            $this->output->write(
                self::$provider.' - Pulling hourly data for '.count($accounts).' accounts.'
            );
            $this->getAdPerformanceReportRequest(
                $since,
                $until,
                array_keys($accounts),
                function ($adStat) use (&$spend, &$currencyCode, &$totals) {
                    if (!$adStat) {
                        return;
                    }
                    $stat = new Stat();
                    $stat->setMediaAccountId($this->mediaAccount->getId());

                    // microsham invented their own date format for this. It's wonderful.
                    $dateAdded = \DateTime::createFromFormat(
                        'n/j/Y 12:00:00 \A\M\|G',
                        $adStat->TimePeriod,
                        $this->timezone
                    );
                    if (!$dateAdded) {
                        throw new \Exception('Unparsable date: '.$adStat->TimePeriod);
                    }
                    $stat->setDateAdded($dateAdded);

                    $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
                        $adStat->AccountId,
                        $adStat->CampaignId,
                        $adStat->AccountName,
                        $adStat->CampaignName
                    );
                    if (is_int($campaignId)) {
                        $stat->setCampaignId($campaignId);
                    }

                    $stat->setProvider(self::$provider);

                    $stat->setProviderAccountId($adStat->AccountId);
                    $stat->setproviderAccountName($adStat->AccountName);

                    $stat->setProviderCampaignId($adStat->CampaignId);
                    $stat->setProviderCampaignName($adStat->CampaignName);

                    $stat->setProviderAdsetId($adStat->AdGroupId);
                    $stat->setproviderAdsetName($adStat->AdGroupName);

                    $stat->setProviderAdId($adStat->AdId);
                    $stat->setproviderAdName($adStat->AdTitle);

                    // Definitions:
                    // CPM is total cost for 1k impressions.
                    //      CPM = cost * 1000 / impressions
                    // CPC is the cost per action.
                    //      CPC = cost / clicks
                    // CTR is the click through rate.
                    //      CTR = (clicks / impressions) * 100
                    // For our purposes we are considering swipes as clicks for Snapchat.
                    $clicks      = intval($adStat->Clicks);
                    $impressions = intval($adStat->Impressions);
                    $cost        = floatval($adStat->Spend);
                    $cpm         = $impressions ? (($cost * 1000) / $impressions) : 0;
                    $stat->setCpm($cpm);
                    $stat->setCpc(floatval($adStat->CostPerConversion));
                    $stat->setCtr(floatval($adStat->Ctr));
                    $stat->setClicks($clicks);
                    if (!empty($adStat->CurrencyCode)) {
                        $currencyCode = $adStat->CurrencyCode;
                    }
                    $stat->setCurrency($adStat->CurrencyCode);
                    $stat->setSpend($cost);
                    $stat->setImpressions($impressions);

                    $this->addStatToQueue($stat, $spend);

                    // We're doing many accounts at once to save time, so we'll need an array of that data by account
                    // for the sake of the summary entities.
                    $a = $stat->getProviderAccountId();
                    if (!isset($totals[$a])) {
                        $totals[$a] = [];
                    }
                    $d = $dateAdded->format('Ymd');
                    if (!isset($totals[$a][$d])) {
                        $totals[$a][$d] = [
                            'accountName'      => $stat->getProviderAccountName(),
                            'currencyCode'     => $currencyCode,
                            'providerDate'     => $dateAdded,
                            'spend'            => 0,
                            'clicksTotal'      => 0,
                            'impressionsTotal' => 0,
                        ];
                    }
                    $totals[$a][$d]['spend'] += $stat->getSpend();
                    $totals[$a][$d]['clicksTotal'] += $stat->getClicks();
                    $totals[$a][$d]['impressionsTotal'] += $stat->getImpressions();
                }
            );
        }

        return $totals;
    }

    /**
     * Authenticates the user and returns an array of managed customers and linked accounts.
     * $customers[<customerId>] = [<accountId>,<accountId>].
     *
     * @throws \Exception
     */
    private function getAllManagedCustomers()
    {
        $accountCount = 0;
        $customers    = [];

        // Authenticate as a user and also pull in linked accounts.
        $user = $this->getUser(true);
        if (!$user || !isset($user->User->Name->FirstName)) {
            throw new \Exception(
                'Could not authenticate as a user in Bing. Make sure the account has access to Bing ads.'
            );
        }
        $this->output->writeln(
            'Logged in to Bing as '.strip_tags($user->User->Name->FirstName).
            ' '.strip_tags($user->User->Name->LastName).
            ' ('.strip_tags($user->User->UserName).')'
        );
        // microsong gives us only linked accounts with a role assignment, so we must search to get the rest. how fun.
        if (isset($user->CustomerRoles->CustomerRole)) {
            foreach ($user->CustomerRoles->CustomerRole as $role) {
                // Get linked accounts.
                if (isset($role->CustomerId)) {
                    $accounts = $this->getAllActiveAccounts($role->CustomerId);
                    if ($accounts) {
                        $customers[$role->CustomerId] = $accounts;
                        $accountCount += count($accounts);
                    }
                }
            }
        }
        if (!$customers) {
            throw new \Exception(
                'No Bing Ads customer accounts accessible by media account '.$this->providerAccountId.'.'
            );
        }
        $this->output->writeln(
            self::$provider.' - Found '.$accountCount.
            ' active accounts in media account '.$this->mediaAccount->getId().'.'
        );

        return $customers;
    }

    /**
     * @param bool $includeLinkedAccounts
     *
     * @return |null
     */
    private function getUser($includeLinkedAccounts = false)
    {
        $request                          = new GetUserRequest();
        $request->IncludeLinkedAccountIds = $includeLinkedAccounts;

        return $this->attemptRequest(ServiceClientType::CustomerManagementVersion12, 'GetUser', $request);
    }

    /**
     * @param $serviceType
     * @param $method
     * @param $request
     *
     * @return mixed
     */
    private function attemptRequest($serviceType, $method, $request)
    {
        ini_set('soap.wsdl_cache_enabled', '0');
        ini_set('soap.wsdl_cache_ttl', '0');
        $result = null;
        do {
            try {
                /** @var ServiceClient $proxy */
                $proxy   = $this->getServiceClient($serviceType);
                $attempt = $proxy->GetService()->{$method}($request);
                if ($attempt) {
                    $result = $attempt;
                }
            } catch (\Exception $e) {
                if (
                    $e instanceof \SoapFault
                    && isset($e->detail->AdApiFaultDetail->Errors->AdApiError->Message)
                ) {
                    $this->errors[] = $e->detail->AdApiFaultDetail->Errors->AdApiError->Message;
                } else {
                    $this->errors[] = $e->getMessage();
                }
                if (
                    isset($e->detail->AdApiFaultDetail->Errors->AdApiError->Code)
                    && '105' == $e->detail->AdApiFaultDetail->Errors->AdApiError->Code
                ) {
                    // Failed authentication, let's try to refresh our token before trying again.
                    $this->session->set('mautic.media.helper.bing.auth', null);
                }
            }
        } while (
            !$result
            && count($this->errors) < self::$rateLimitMaxErrors
        );

        return $result;
    }

    /**
     * @param $type
     *
     * @return ServiceClient
     *
     * @throws \Exception
     */
    private function getServiceClient($type)
    {
        $authorization = $this->getAuthData();
        if (!isset($this->bingServices[$type])) {
            $this->bingServices[$type] = new ServiceClient(
                $type,
                $authorization,
                $authorization->Authentication->Environment
            );
        } else {
            /* ServiceClient $this->bingServices[$type] */
            $this->bingServices[$type]->SetAuthorizationData($authorization);
        }

        return $this->bingServices[$type];
    }

    /**
     * @throws \Exception
     */
    private function getAuthData()
    {
        /** @var AuthorizationData $authorization */
        $authorization = $this->session->get('mautic.media.helper.bing.auth');
        if (!$authorization && $this->refreshToken()) {
            $authorization = $this->session->get('mautic.media.helper.bing.auth');
        }
        if (!$authorization) {
            throw new \Exception('Unable to get a fresh Refresh Token.');
        }
        if ($this->bingAccountId) {
            $authorization->AccountId = $this->bingAccountId;
        }
        if ($this->bingCustomerId) {
            $authorization->CustomerId = $this->bingCustomerId;
        }

        return $authorization;
    }

    /**
     * @param null $code
     * @param bool $force
     *
     * @return bool
     */
    private function refreshToken()
    {
        $success = false;
        if (
            !empty($this->providerAccountId)
            && !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
            && !empty($this->providerRefreshToken)
        ) {
            /** @var OAuthWebAuthCodeGrant $authentication */
            $authentication = (new OAuthWebAuthCodeGrant())
                ->withClientId($this->providerClientId)
                ->withClientSecret($this->providerClientSecret)
                ->withEnvironment(ApiEnvironment::Production);

            /** @var AuthorizationData $authorization */
            $authorization = (new AuthorizationData())
                ->withAuthentication($authentication)
                ->withDeveloperToken($this->providerAccountId);

            /* @var AuthorizationData $authorization */
            $tokens = $authorization->Authentication->RequestOAuthTokensByRefreshToken($this->providerRefreshToken);
            if ($tokens) {
                $this->session->set('mautic.media.helper.bing.auth', $authorization);
                $success = $this->saveTokens($tokens);
            }
        }

        return $success;
    }

    /**
     * @param $tokens
     *
     * @return bool
     */
    private function saveTokens(OAuthTokens $tokens)
    {
        $success = false;
        if (!empty($tokens->AccessToken) && $tokens->AccessToken !== $this->providerToken) {
            $this->providerToken = $tokens->AccessToken;
            $this->mediaAccount->setToken($this->providerToken);
            $success = true;
        }
        if (!empty($tokens->RefreshToken) && $tokens->RefreshToken !== $this->providerRefreshToken) {
            $this->providerRefreshToken = $tokens->RefreshToken;
            $this->mediaAccount->setRefreshToken($this->providerRefreshToken);
            $success = true;
        }
        if ($success) {
            $this->saveMediaAccount();
        }

        return $success;
    }

    /**
     * @return mixed
     */
    private function getAllActiveAccounts($customerId)
    {
        $accounts            = [];
        $request             = new FindAccountsRequest();
        $request->CustomerId = $customerId;
        $request->TopN       = 5000;

        // microsobs doesn't give us a way to get the active accounts based on a date range, so we must get them all.
        $data = $this->attemptRequest(ServiceClientType::CustomerManagementVersion12, 'FindAccounts', $request);
        if (isset($data->AccountsInfo->AccountInfo)) {
            foreach ($data->AccountsInfo->AccountInfo as $account) {
                $accounts[$account->Id] = $account;
            }
        }

        return $accounts;
    }

    /**
     * @param \DateTime $since
     * @param \DateTime $until
     * @param           $accountIds
     * @param           $callback
     *
     * @return array|false|null
     *
     * @throws \Exception
     */
    private function getAdPerformanceReportRequest(\DateTime $since, \DateTime $until, $accountIds, $callback)
    {
        $columns = [
            AdPerformanceReportColumn::TimePeriod,
            AdPerformanceReportColumn::AccountId,
            AdPerformanceReportColumn::AccountName,
            AdPerformanceReportColumn::CampaignId,
            AdPerformanceReportColumn::CampaignName,
            AdPerformanceReportColumn::AdGroupId,
            AdPerformanceReportColumn::AdGroupName,
            AdPerformanceReportColumn::AdId,
            AdPerformanceReportColumn::AdTitle,
            AdPerformanceReportColumn::CurrencyCode,
            AdPerformanceReportColumn::Spend,
            AdPerformanceReportColumn::CostPerConversion,
            // Cpm is not provided.
            AdPerformanceReportColumn::Ctr,
            AdPerformanceReportColumn::Impressions,
            AdPerformanceReportColumn::Clicks,
        ];

        /** @var AdPerformanceReportRequest $request */
        $report                       = new AdPerformanceReportRequest();
        $report->Format               = ReportFormat::Csv;
        $report->ReportName           = $this->getReportName();
        $report->ExcludeColumnHeaders = true;
        $report->ExcludeReportHeader  = true;
        $report->ExcludeReportFooter  = true;
        // We will allow incomplete data (for current date).
        $report->ReturnOnlyCompleteData = false;
        $report->Aggregation            = ReportAggregation::Hourly;
        $report->Columns                = $columns;
        //  $report->Filter = new AccountPerformanceReportFilter();
        //  $report->Filter->DeviceType = array (
        //      DeviceTypeReportFilter::Computer,
        //      DeviceTypeReportFilter::SmartPhone
        //  );
        if ($accountIds) {
            $report->Scope             = new AccountReportScope();
            $report->Scope->AccountIds = $accountIds;
        }

        $report->Time                              = new ReportTime();
        $report->Time->CustomDateRangeStart        = new Date();
        $report->Time->CustomDateRangeStart->Year  = (int) $since->format('Y');
        $report->Time->CustomDateRangeStart->Month = (int) $since->format('m');
        $report->Time->CustomDateRangeStart->Day   = (int) $since->format('d');
        $report->Time->CustomDateRangeEnd          = new Date();
        $report->Time->CustomDateRangeEnd->Year    = (int) $until->format('Y');
        $report->Time->CustomDateRangeEnd->Month   = (int) $until->format('m');
        $report->Time->CustomDateRangeEnd->Day     = (int) $until->format('d');

        $report->Time->ReportTimeZone = $this->timezoneMs;

        // Sorting? What do you think this is, literally any other report generator? You'll get your zips and like em.
        // $report->Sort = [];
        // $keywordPerformanceReportSort = new KeywordPerformanceReportSort();
        // $keywordPerformanceReportSort->SortColumn = KeywordPerformanceReportColumn::Clicks;
        // $keywordPerformanceReportSort->SortOrder = SortOrder::Ascending;
        // $report->Sort[] = $keywordPerformanceReportSort;

        $report = new \SoapVar(
            $report,
            SOAP_ENC_OBJECT,
            'AdPerformanceReportRequest',
            $this->getServiceClient(ServiceClientType::ReportingVersion12)->GetNamespace()
        );

        return $this->submitReportAndDownloadWhenDone($report, $columns, $callback);
    }

    /**
     * @param \SoapVar $report
     * @param array    $columns
     * @param callable $callback
     *
     * @return array|false|null
     *
     * @throws \Exception
     */
    private function submitReportAndDownloadWhenDone(\SoapVar $report, $columns, $callback)
    {
        // microshaft must create the report offline then provide us a link (maybe). it's super convenient.
        $result                       = null;
        $reportRequest                = new SubmitGenerateReportRequest();
        $reportRequest->ReportRequest = $report;
        $reportSubmission             = $this->attemptRequest(
            ServiceClientType::ReportingVersion12,
            'SubmitGenerateReport',
            $reportRequest
        );

        // Poll for a few minutes, till either the report is provided or until we fail in misery and defeat.
        if (!empty($reportSubmission->ReportRequestId)) {
            $request                  = new PollGenerateReportRequest();
            $request->ReportRequestId = $reportSubmission->ReportRequestId;

            $start            = time();
            $maxTimeInSeconds = 60 * 30;
            do {
                $reportStatus = $this->attemptRequest(
                    ServiceClientType::ReportingVersion12,
                    'PollGenerateReport',
                    $request
                );
                if (
                    !$reportStatus
                    || !isset($reportStatus->ReportRequestStatus->Status)
                ) {
                    $this->errors[] = 'Could not get report status';
                    $this->output->write('.');
                    sleep(self::$rateLimitSleep / 15);
                } elseif (ReportRequestStatusType::Pending == $reportStatus->ReportRequestStatus->Status) {
                    // Report is being generated, hang on for a while.
                    $this->output->write('.');
                    sleep(self::$rateLimitSleep / 15);
                } elseif (ReportRequestStatusType::Error == $reportStatus->ReportRequestStatus->Status) {
                    $this->errors[] = 'Report had an error on the provider side';
                    sleep(self::$betweenOpSleep);
                } elseif (ReportRequestStatusType::Success == $reportStatus->ReportRequestStatus->Status) {
                    if (!empty($reportStatus->ReportRequestStatus->ReportDownloadUrl)) {
                        $result = $this->downloadReport(
                            $reportStatus->ReportRequestStatus->ReportDownloadUrl,
                            $columns,
                            $callback
                        );
                        if (!$result) {
                            $this->errors[] = 'Report download failed';
                        }
                    } else {
                        // There was no data to report, and thus no report to download.
                        $result = [];
                    }
                } else {
                    // This API is a disgrace to it's ancestors.
                    $this->errors[] = 'Unexpected report status.';
                    sleep(self::$betweenOpSleep);
                }
            } while (
                null === $result
                && count($this->errors) < self::$rateLimitMaxErrors
                && time() - $start < $maxTimeInSeconds
            );
        }

        return $result;
    }

    /**
     * @param string   $reportUrl
     * @param array    $columns
     * @param callable $callback
     *
     * @return array|false|null
     *
     * @throws \Exception
     */
    private function downloadReport($reportUrl, $columns, $callback)
    {
        $success = false;
        // microslug gives us a zip file that must be saved before decompression. it's lovely.
        $zipFile = tempnam(sys_get_temp_dir(), 'mautic-bing-download');
        $handle  = fopen($zipFile, 'w');
        $client  = new Client(
            '', [
                Client::CURL_OPTIONS => [
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_FILE'           => $handle,
                ],
            ]
        );
        if (!$client->get($reportUrl)->send()) {
            throw new \Exception('Could not download zip from Bing.');
        }
        fclose($handle);

        // microscar gives us a zip file that contains an unpredictable file contents. it's perfect.
        $destPath = $zipFile.'.unzipped';
        $zip      = new \ZipArchive();
        $file     = $zip->open($zipFile);
        if (true === $file) {
            if (!$zip->extractTo($destPath)) {
                throw new \Exception('Could not extract zip downloaded from Bing.');
            }
            $zip->close();
            // Should find one or more CSV files in here...
            foreach (scandir($destPath) as $file) {
                if (!is_dir($file)) {
                    if (false !== ($csv = fopen($destPath.'/'.$file, 'r'))) {
                        while (false !== ($data = fgetcsv($csv))) {
                            $adStat = new \stdClass();
                            foreach ($columns as $i => $column) {
                                // microshirt has inserted utf8 data in here for some reason. it's neat.
                                $adStat->{$column} = isset($data[$i]) ? trim(utf8_decode($data[$i]), '"?') : '';
                            }
                            $callback($adStat);
                        }
                        fclose($csv);
                    }
                }
            }
            $success = true;
        } else {
            throw new \Exception('Could not open zip downloaded from Bing.');
        }

        // No longer need temporary files.
        unlink($zipFile);
        $this->rrmdir($destPath);

        return $success;
    }

    /**
     * Hide all traces that we had to download files from microspit.
     *
     * @param $dir
     */
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ('.' != $object && '..' != $object) {
                    if (is_dir($dir.'/'.$object)) {
                        $this->rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @param $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri)
    {
        $result = '';
        $state  = $this->session->get('mautic.media.helper.bing.state', $this->getState());
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
                ->withEnvironment(ApiEnvironment::Production)
                ->withRedirectUri($redirectUri)
                ->withState($state);

            /** @var AuthorizationData $authorization */
            $authorization = (new AuthorizationData())
                ->withAuthentication($authentication)
                ->withDeveloperToken($this->providerAccountId);

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
            && ($authorization = $this->session->get('mautic.media.helper.bing.auth'))
        ) {
            try {
                $tokens = $authorization->Authentication->RequestOAuthTokensByResponseUri($params['uri']);
                if ($tokens) {
                    $this->session->set('mautic.media.helper.bing.auth', $authorization);
                    $success = $this->saveTokens($tokens);
                }
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $success;
    }

    /**
     * @param $accountId
     *
     * @return mixed
     */
    private function getAccount($accountId)
    {
        $request            = new GetAccountRequest();
        $request->AccountId = $accountId;

        return $this->attemptRequest(ServiceClientType::CustomerManagementVersion12, 'GetAccount', $request);
    }
}
