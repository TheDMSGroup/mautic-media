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
 * Class CampaignSettingsHelper.
 */
class CampaignSettingsHelper
{
    /** @var CampaignMapHelper */
    private $campaignMapHelper;

    /** @var \stdClass */
    private $campaignSettingsField;

    /** @var array */
    private $providerAccountsWithCampaigns;

    /** @var array */
    private $campaignNames = [];

    /** @var array */
    private $accountCampaignMap = [];

    /** @var array */
    private $searchedCampaignNames = [];

    /**
     * CampaignSettingsHelper constructor.
     *
     * @param array  $campaignNames
     * @param string $campaignSettingsField
     * @param array  $providerAccountsWithCampaigns
     *
     * @throws \Exception
     */
    public function __construct(
        $campaignNames = [],
        $campaignSettingsField = '',
        $providerAccountsWithCampaigns = []
    ) {
        $this->campaignNames                 = $campaignNames;
        $this->campaignMapHelper             = new CampaignMapHelper();
        $this->providerAccountsWithCampaigns = $providerAccountsWithCampaigns;
        $this->setCampaignSettingsField($campaignSettingsField);
    }

    /**
     * @param $campaignSettingsField
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setCampaignSettingsField($campaignSettingsField)
    {
        if (!is_object($campaignSettingsField)) {
            $jsonHelper            = new JSONHelper();
            $campaignSettingsField = $jsonHelper->decodeObject(
                $campaignSettingsField,
                'CampaignSettings'
            );
        }
        $this->campaignSettingsField = $campaignSettingsField;

        return $this;
    }

    /**
     * @param $campaignNames
     */
    public function setCampaignNames($campaignNames)
    {
        $this->campaignNames = $campaignNames;
    }

    /**
     * @param $providerAccountsWithCampaigns
     */
    public function setProviderAccountsWithCampaigns($providerAccountsWithCampaigns)
    {
        $this->providerAccountsWithCampaigns = $providerAccountsWithCampaigns;
    }

    /**
     * A simple array for finding the mapping of a provider account/campaign to an internal one.
     *
     *      $this->accountCampaignMap[ProviderAccountId][ProviderCampaignId] = CampaignId (multiple enabled)
     *      $this->accountCampaignMap[ProviderAccountId] = CampaignId (single mode)
     *
     * @param string $providerAccountId
     * @param string $providerCampaignId
     * @param string $providerAccountName
     * @param string $providerCampaignName
     *
     * @return array|mixed
     */
    public function getAccountCampaignMap(
        $providerAccountId = '',
        $providerCampaignId = '',
        $providerAccountName = '',
        $providerCampaignName = ''
    ) {
        $result = [];
        if (!$this->accountCampaignMap) {
            $this->updateAccountCampaignMap();
        }
        if (
            !isset($this->accountCampaignMap[$providerAccountId])
            && $providerAccountName
            && $providerCampaignName
            && !isset($searchedCampaignNames[$providerCampaignName])
        ) {
            $searchedCampaignNames[$providerCampaignName] = true;
            // A new Campaign Name was detected that has no data yet.
            $this->providerAccountsWithCampaigns['campaigns'][$providerCampaignId] = $providerCampaignName;
            $this->providerAccountsWithCampaigns['accounts'][$providerAccountId]   = $providerAccountName;
            if (!isset($this->providerAccountsWithCampaigns['hierarchy'][$providerAccountId])) {
                $this->providerAccountsWithCampaigns['hierarchy'][$providerAccountId] = [];
            }
            $this->providerAccountsWithCampaigns['hierarchy'][$providerAccountId][] = $providerCampaignId;
            echo '-'.$providerCampaignName;
            $this->updateAccountCampaignMap();
        }

        if (empty($providerAccountId)) {
            $result = $this->accountCampaignMap;
        } else {
            if (isset($this->accountCampaignMap[$providerAccountId])) {
                if (is_array($this->accountCampaignMap[$providerAccountId])) {
                    // Multiple mode
                    if (
                        !empty($providerCampaignId)
                        && isset($this->accountCampaignMap[$providerAccountId][$providerCampaignId])
                    ) {
                        $result = $this->accountCampaignMap[$providerAccountId][$providerCampaignId];
                    }
                } else {
                    // Single mode
                    $result = $this->accountCampaignMap[$providerAccountId];
                }
            }
        }

        return $result;
    }

    private function updateAccountCampaignMap()
    {
        $obj = $this->getAutoUpdatedCampaignSettings();
        if (isset($obj->accounts)) {
            foreach ($obj->accounts as $account) {
                if (!empty($account->providerAccountId)) {
                    if (
                        isset($account->multiple)
                        && $account->multiple
                    ) {
                        // Multiple mode
                        if (isset($account->campaigns)) {
                            foreach ($account->campaigns as $campaign) {
                                if (
                                    !empty($campaign->providerCampaignId)
                                    && !empty($campaign->campaignId)
                                ) {
                                    if (!isset($this->accountCampaignMap[$account->providerAccountId])) {
                                        $this->accountCampaignMap[$account->providerAccountId] = [];
                                    }
                                    if (!isset($this->accountCampaignMap[$account->providerAccountId][$campaign->providerCampaignId])) {
                                        $this->accountCampaignMap[$account->providerAccountId][$campaign->providerCampaignId] = $campaign->campaignId;
                                    }
                                }
                            }
                        }
                    } else {
                        // Single mode
                        if (!empty($account->campaignId)) {
                            if (!isset($this->accountCampaignMap[$account->providerAccountId])) {
                                $this->accountCampaignMap[$account->providerAccountId] = $account->campaignId;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return \stdClass
     */
    public function getAutoUpdatedCampaignSettings()
    {
        // Update the campaign mapper with the latest list of campaign names.
        $this->campaignMapHelper->setCampaignNames($this->campaignNames);

        foreach ($this->providerAccountsWithCampaigns['hierarchy'] as $providerAccountId => $providerCampaigns) {
            // Make sure this account is included.
            $newAccount = true;
            if (!isset($this->campaignSettingsField->accounts)) {
                $this->campaignSettingsField->accounts = [];
            } else {
                foreach ($this->campaignSettingsField->accounts as &$accountObj) {
                    if (
                        isset($accountObj->providerAccountId)
                        && $accountObj->providerAccountId == $providerAccountId
                    ) {
                        $newAccount = false;
                        break;
                    }
                }
            }
            if ($newAccount) {
                $accountObj                    = new \stdClass();
                $accountObj->providerAccountId = (string) $providerAccountId;
                $accountObj->campaigns         = [];
                // Guess the primary campaign (default) based on the name of the account.
                $accountObj->campaignId = $this->campaignMapHelper->guess(
                    $this->providerAccountsWithCampaigns['accounts'][$providerAccountId]
                );
                $accountObj->multiple   = 0;
            }
            if (isset($accountObj)) {
                // Make sure all campaigns are included in the account
                foreach ($providerCampaigns as $providerCampaignId) {
                    $newCampaign = true;
                    foreach ($accountObj->campaigns as &$campaignObj) {
                        if (
                            isset($campaignObj->providerCampaignId)
                            && $campaignObj->providerCampaignId == $providerCampaignId
                        ) {
                            $newCampaign = false;
                            break;
                        }
                    }
                    // Only add campaigns not already mapped
                    if ($newCampaign) {
                        $campaignObj                     = new \stdClass();
                        $campaignObj->providerCampaignId = (string) $providerCampaignId;
                    }
                    if (isset($campaignObj)) {
                        if (!empty($campaignObj) && empty($campaignObj->campaignId)) {
                            // Guess the campaign based on the provider campaign name.
                            $campaignObj->campaignId = $this->campaignMapHelper->guess(
                                $this->providerAccountsWithCampaigns['campaigns'][$providerCampaignId]
                            );
                        }
                        if ($newCampaign) {
                            $accountObj->campaigns[] = $campaignObj;
                        }
                    }
                }
                if ($newAccount) {
                    $this->campaignSettingsField->accounts[] = $accountObj;
                }
            }
        }

        return $this->campaignSettingsField;
    }
}
