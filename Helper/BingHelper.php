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
use Microsoft\BingAds\Auth\ApiEnvironment;
use Microsoft\BingAds\Auth\AuthorizationData;
use Microsoft\BingAds\Auth\OAuthDesktopMobileAuthCodeGrant;
use Microsoft\BingAds\Auth\OAuthTokens;
use Microsoft\BingAds\Auth\OAuthWebAuthCodeGrant;
use Microsoft\BingAds\Auth\ServiceClient;
use Microsoft\BingAds\Auth\ServiceClientType;
use Microsoft\BingAds\V12\Bulk\Date;
use Microsoft\BingAds\V12\CustomerManagement\GetUserRequest;
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
 * Requires ClientId, ClientSecret, Token (Developer Token)
 *
 * https://docs.microsoft.com/en-us/bingads/reporting-service/reporting-service-reference?view=bingads-12
 * https://github.com/BingAds/BingAds-PHP-SDK
 */
class BingHelper extends CommonProviderHelper
{
    /** @var ServiceClient AKA ReportingProxy */
    private $bingServiceClient;

    /** @var array */
    private $bingServices = [];

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array|void
     */
    public function pullData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        // @todo - If a failure, refresh.
        // $this->refreshToken();

        // @todo - Get active accounts.
        $accounts = $this->getActiveAccounts($dateFrom, $dateTo);

        // @todo - For each account get campaigns.

        // @todo - For each campaign, pull hourly ad data.

        $data = $this->getAdPerformanceReportRequest($dateFrom, $dateTo);

        // $accounts = $this->getAllActiveAccounts($dateFrom, $dateTo);
        // $this->output->writeln(
        //     MediaAccount::PROVIDER_BING.' - Found '.count(
        //         $accounts
        //     ).' active accounts in media account '.$this->mediaAccount->getId().'.'
        // );
        //

        //
        // $date   = clone $dateTo;
        // $oneDay = new \DateInterval('P1D');
        // while ($date >= $dateFrom) {
        //     /** @var AdAccount $account */
        //     foreach ($accounts as $account) {
        //         $spend    = 0;
        //         $timezone = new \DateTimeZone($account->timezone);
        //         $since    = clone $date;
        //         $until    = clone $date;
        //         $this->output->write(
        //             MediaAccount::PROVIDER_BING.' - Pulling hourly data - '.
        //             $since->format('Y-m-d').' - '.
        //             $account->name
        //         );
        //         $since->setTimeZone($timezone);
        //         $until->setTimeZone($timezone)->add($oneDay);
        //         foreach ($this->getActiveCampaigns($account->id, $dateFrom, $dateTo) as $campaign) {
        //             $adStats = $this->getCampaignStats($campaign->id, $since, $until);
        //             foreach ($adStats as $adStat) {
        //                 if (!$adStat) {
        //                     continue;
        //                 }
        //                 $stat = new Stat();
        //                 $stat->setMediaAccountId($this->mediaAccount->getId());
        //
        //                 $stat->setDateAdded((new \DateTime($adStat->start_time)));
        //
        //                 $campaignId = $this->campaignSettingsHelper->getAccountCampaignMap(
        //                     (string) $account->id,
        //                     (string) $campaign->id,
        //                     (string) $account->name,
        //                     (string) $campaign->name
        //                 );
        //                 if (is_int($campaignId)) {
        //                     $stat->setCampaignId($campaignId);
        //                 }
        //
        //                 $provider = MediaAccount::PROVIDER_SNAPCHAT;
        //                 $stat->setProvider($provider);
        //
        //                 $stat->setProviderAccountId($account->id);
        //                 $stat->setproviderAccountName($account->name);
        //
        //                 $stat->setProviderCampaignId($campaign->id);
        //                 $stat->setProviderCampaignName($campaign->name);
        //
        //                 // Since the stats API doesn't contain other data, we need to pull names sepperately.
        //                 $adDetails = $this->getAdDetails($account->id, $adStat->id);
        //                 if (isset($adDetails->ad_squad_id)) {
        //                     $stat->setProviderAdsetId($adDetails->ad_squad_id);
        //                     $adSquadDetails = $this->getAdSquadDetails($campaign->id, $adDetails->ad_squad_id);
        //                     if (isset($adSquadDetails->name)) {
        //                         $stat->setproviderAdsetName($adSquadDetails->name);
        //                     }
        //                 }
        //
        //                 $stat->setProviderAdId($adStat->id);
        //                 if (isset($adDetails->name)) {
        //                     $stat->setproviderAdName($adDetails->name);
        //                 }
        //
        //                 // Definitions:
        //                 // CPM is total cost for 1k impressions.
        //                 //      CPM = cost * 1000 / impressions
        //                 // CPC is the cost per action.
        //                 //      CPC = cost / clicks
        //                 // CTR is the click through rate.
        //                 //      CTR = (clicks / impressions) * 100
        //                 // For our purposes we are considering swipes as clicks for Snapchat.
        //                 $clicks      = isset($adStat->swipes) ? $adStat->swipes : 0;
        //                 $impressions = intval($adStat->impressions);
        //                 $cost        = floatval($adStat->spend) / 1000000;
        //                 $cpm         = $impressions ? (($cost * 1000) / $impressions) : 0;
        //                 $cpc         = $clicks ? ($cost / $clicks) : 0;
        //                 $ctr         = $impressions ? (($clicks / $impressions) * 100) : 0;
        //                 $stat->setCurrency($account->currency);
        //                 $stat->setSpend($cost);
        //                 $stat->setCpm($cpm);
        //                 $stat->setCpc($cpc);
        //                 $stat->setCtr($ctr);
        //                 $stat->setImpressions($impressions);
        //                 $stat->setClicks($clicks);
        //
        //                 $this->addStatToQueue($stat, $spend);
        //             }
        //         }
        //         $this->output->writeln(' - '.$account->currency.' '.$spend);
        //     }
        //     $date->sub($oneDay);
        // }
    }

    private function getActiveAccounts(\DateTime $dateFrom, \DateTime $dateTo)
    {
        $user = $this->getUser();

        // @todo - Use the example to get all accounts from the primary user. With Pagination support.
        return;
        // $campaignClient = $this->getServiceClient(ServiceClientType::CampaignManagementVersion12);
        //
        //
        // // Set the GetUser request parameter to an empty user identifier to get the current
        // // authenticated Bing Ads user, and then search for all accounts the user may access.
        //
        // $user = CustomerManagementExampleHelper::GetUser(null, true)->User;
        //
        // // Search for the Bing Ads accounts that the user can access.
        //
        // $pageInfo        = new Paging();
        // $pageInfo->Index = 0;    // The first page
        // $pageInfo->Size  = 100;   // The first 100 accounts for this page of results
        //
        // $predicate           = new Predicate();
        // $predicate->Field    = "UserId";
        // $predicate->Operator = PredicateOperator::Equals;
        // $predicate->Value    = $user->Id;
        //
        // $accounts = CustomerManagementExampleHelper::SearchAccounts(
        //     [$predicate],
        //     null,
        //     $pageInfo
        // )->Accounts;
        //
        // foreach ($accounts->AdvertiserAccount as $account) {
        //     $GLOBALS['AuthorizationData']->AccountId  = $account->Id;
        //     $GLOBALS['AuthorizationData']->CustomerId = $account->ParentCustomerId;
        //
        //     CustomerManagementExampleHelper::OutputAdvertiserAccount($account);
        //
        //     // Optionally you can find out which pilot features the customer is able to use.
        //     // Each account could belong to a different customer, so use the customer ID in each account.
        //     $featurePilotFlags = CustomerManagementExampleHelper::GetCustomerPilotFeatures(
        //         $account->ParentCustomerId
        //     )->FeaturePilotFlags;
        //     print "Customer Pilot Flags:\n";
        //     CustomerManagementExampleHelper::OutputArrayOfInt($featurePilotFlags);
        //     $getCampaignsByAccountIdResponse = CampaignManagementExampleHelper::GetCampaignsByAccountId(
        //         $account->Id,
        //         AuthHelper::CampaignTypes,
        //         CampaignAdditionalField::ExperimentId
        //     );
        //     CampaignManagementExampleHelper::OutputArrayOfCampaign($getCampaignsByAccountIdResponse->Campaigns);
        // }
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    private function getUser()
    {
        $request = new GetUserRequest();

        // Get self, and all linked accounts.
        $request->UserId                  = null;
        $request->IncludeLinkedAccountIds = true;

        return $this->attemptRequest(ServiceClientType::CustomerManagementVersion12, 'GetUser', $request);
    }

    /**
     * @param $serviceType
     * @param $method
     * @param $request
     *
     * @return |null
     */
    private function attemptRequest($serviceType, $method, $request)
    {
        ini_set('soap.wsdl_cache_enabled', '0');
        ini_set('soap.wsdl_cache_ttl', '0');
        $result = null;
        do {
            try {
                $attempt = $this->getServiceClient($serviceType)
                    ->GetService()
                    ->{$method}(
                        $request
                    );
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
        if (!isset($this->bingServices[$type])) {
            $this->bingServices[$type] = new ServiceClient(
                $type,
                $this->getAuthData(),
                ApiEnvironment::Production
            );
        } else {
            $this->bingServices[$type]->SetAuthorizationData($this->getAuthData());
        }

        return $this->bingServices[$type];
    }

    /**
     * @throws \Exception
     */
    private function getAuthData()
    {
        $authorization = $this->session->get('mautic.media.helper.bing.auth');
        if (!$authorization && $this->refreshToken()) {
            $authorization = $this->session->get('mautic.media.helper.bing.auth');
        }
        if (!$authorization) {
            throw new \Exception('Unable to get a fresh Refresh Token.');
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
            !empty($this->providerClientId)
            && !empty($this->providerClientSecret)
            && !empty($this->providerRefreshToken)
        ) {
            // /** @var OAuthWebAuthCodeGrant $authentication */
            $authentication = (new OAuthWebAuthCodeGrant())
                ->withClientId($this->providerClientId)
                ->withRefreshToken($this->providerRefreshToken)
                ->withClientSecret($this->providerClientSecret)
                ->withEnvironment(ApiEnvironment::Production);

            // $authentication = (new OAuthWebAuthCodeGrant())
            //     ->withClientId($this->providerClientId)
            //     ->withClientSecret($this->providerClientSecret)
            //     ->withEnvironment(ApiEnvironment::Production);

            /** @var OAuthDesktopMobileAuthCodeGrant $authentication */
            // $authentication = (new OAuthDesktopMobileAuthCodeGrant())
            //     ->withClientId($this->providerClientId)
            //     ->withRefreshToken($this->providerRefreshToken)
            //     ->withClientSecret($this->providerClientSecret)
            //     ->withEnvironment(ApiEnvironment::Production);

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
            $this->mediaAccount->setRefreshToken($tokens->RefreshToken);
            $success = true;
        }
        if ($success) {
            $this->saveMediaAccount();
        }

        return $success;
    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function getAdPerformanceReportRequest(\DateTime $dateFrom, \DateTime $dateTo)
    {
        /** @var AdPerformanceReportRequest $request */
        $report                         = new AdPerformanceReportRequest();
        $report->Format                 = ReportFormat::Csv;
        $report->ReportName             = $this->getReportName();
        $report->ExcludeColumnHeaders   = true;
        $report->ExcludeReportHeader    = true;
        $report->ExcludeReportFooter    = true;
        $report->ReturnOnlyCompleteData = true;
        $report->Aggregation            = ReportAggregation::Hourly;
        $report->Columns                = [
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
            // AdPerformanceReportColumn::Cpm,
            AdPerformanceReportColumn::Ctr,
            AdPerformanceReportColumn::Impressions,
            AdPerformanceReportColumn::Clicks,
        ];
        //  $report->Filter = new AccountPerformanceReportFilter();
        //  $report->Filter->DeviceType = array (
        //      DeviceTypeReportFilter::Computer,
        //      DeviceTypeReportFilter::SmartPhone
        //  );
        // $report->Scope               = new AccountReportScope();
        // $report->Scope->AccountIds   = [];
        // $report->Scope->AccountIds[] = $this->providerAccountId;
        $report->Time                              = new ReportTime();
        $report->Time->CustomDateRangeStart        = new Date();
        $report->Time->CustomDateRangeStart->Year  = $dateFrom->format('Y');
        $report->Time->CustomDateRangeStart->Month = $dateFrom->format('m');
        $report->Time->CustomDateRangeStart->Day   = $dateFrom->format('d');
        $report->Time->CustomDateRangeEnd          = new Date();
        $report->Time->CustomDateRangeEnd->Year    = $dateTo->format('Y');
        $report->Time->CustomDateRangeEnd->Month   = $dateTo->format('m');
        $report->Time->CustomDateRangeEnd->Day     = $dateTo->format('d');
        // Default to UTC because Microsoft is weird.
        $report->Time->ReportTimeZone = ReportTimeZone::GreenwichMeanTimeDublinEdinburghLisbonLondon;

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

        $result = $this->submitReportAndDownloadWhenDone($report);

        return $result;
    }

    /**
     * @param $report
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function submitReportAndDownloadWhenDone(\SoapVar $report)
    {
        $result                       = null;
        $reportSubmission             = null;
        $reportRequest                = new SubmitGenerateReportRequest();
        $reportRequest->ReportRequest = $report;

        $reportSubmission = $this->attemptRequest(
            ServiceClientType::ReportingVersion12,
            'SubmitGenerateReport',
            $reportRequest
        );

        if (!empty($reportSubmission->ReportRequestId)) {
            $request                  = new PollGenerateReportRequest();
            $request->ReportRequestId = $reportSubmission->ReportRequestId;

            $start            = time();
            $maxTimeInSeconds = 60 * 5;
            do {
                $reportStatus = $this->attemptRequest(
                    ServiceClientType::ReportingVersion12,
                    'PollGenerateReport',
                    $request
                );
                if (!$reportStatus) {
                    $this->errors[] = 'Could not get report status';
                } elseif (ReportRequestStatusType::Error != $reportStatus->Status) {
                    $this->errors[] = 'Report status came back as an error';
                } elseif (ReportRequestStatusType::Success != $reportStatus->Status) {
                    if (isset($reportStatus->ReportDownloadUrl)) {
                        if (null == $reportStatus->ReportDownloadUrl) {
                            $this->errors[] = 'Report URL not returned by status call';
                        } else {
                            $result = $this->downloadReport($reportStatus->ReportDownloadUrl);
                            if (!$result) {
                                $this->errors[] = 'Report download failed';
                            }
                        }
                    }
                }
                echo '.';
                sleep(self::$rateLimitSleep / 60);
            } while (
                !$result
                && count($this->errors) < self::$rateLimitMaxErrors
                && time() - $start < $maxTimeInSeconds
            );
        }

        return $result;
    }

    /**
     * @param $reportUrl
     *
     * @return \Guzzle\Http\EntityBodyInterface|null
     */
    private function downloadReport($reportUrl)
    {
        $data     = null;
        $client   = new Client();
        $response = $client->get($reportUrl);
        if ('200' == $response->getResponse()->getStatusCode()) {
            $data = $response->getResponseBody();
        }

        return $data;
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
                $tokens  = $authorization->Authentication->RequestOAuthTokensByResponseUri($params['uri']);
                $success = $this->saveTokens($tokens);
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $success;
    }
}
