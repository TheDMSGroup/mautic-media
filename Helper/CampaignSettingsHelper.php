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

    /** @var EntityManager */
    private $em;

    /**
     * CampaignSettingsHelper constructor.
     *
     * @param array              $campaignNames
     * @param                    $campaignSettingsField
     * @param array              $providerAccountsWithCampaigns
     * @param EntityManager|null $em
     */
    public function __construct(
        $campaignNames = [],
        $campaignSettingsField,
        $providerAccountsWithCampaigns = [],
        $em = null
    ) {
        $this->campaignNames                 = $campaignNames;
        $this->campaignMapHelper             = new CampaignMapHelper();
        $this->campaignSettingsField         = $campaignSettingsField;
        $this->providerAccountsWithCampaigns = $providerAccountsWithCampaigns;
        $this->em                            = $em;
    }

    /**
     * @param $campaignNames
     */
    public function setCampaignNames($campaignNames)
    {
        $this->campaignNames = $campaignNames;
    }

    /**
     * @param $campaignSettingsField
     */
    public function setCampaignSettingsField($campaignSettingsField)
    {
        $this->campaignSettingsField = $campaignSettingsField;
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
     * @param null $providerAccountId
     * @param null $providerCampaignId
     *
     * @return array|mixed
     */
    public function getAccountCampaignMap($providerAccountId = null, $providerCampaignId = null)
    {
        $result = [];
        if (!$this->accountCampaignMap) {
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
                            if (isset($account->campaign) && !empty($account->campaign->campaignId)) {
                                if (!isset($this->accountCampaignMap[$account->providerAccountId])) {
                                    $this->accountCampaignMap[$account->providerAccountId] = [];
                                }
                                if (!isset($this->accountCampaignMap[$account->providerAccountId])) {
                                    $this->accountCampaignMap[$account->providerAccountId] = $account->campaign->campaignId;
                                }
                            }
                        }
                    }
                }
            }
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

    /**
     * @return \stdClass
     */
    public function getAutoUpdatedCampaignSettings()
    {
        // Update the campaign mapper with the latest list of campaign names.
        $this->campaignMapHelper->setCampaignNames($this->getCampaignNames());

        foreach ($this->providerAccountsWithCampaigns['hierarchy'] as $providerAccountId => $providerCampaigns) {
            // Make sure this account is included.
            $newAccount = true;
            if (!isset($this->campaignSettingsField->accounts)) {
                $this->campaignSettingsField->accounts = [];
            }
            foreach ($this->campaignSettingsField->accounts as &$accountObj) {
                if (
                    isset($accountObj->providerAccountId)
                    && $accountObj->providerAccountId == $providerAccountId
                ) {
                    $newAccount = false;
                    break;
                }
            }
            if ($newAccount) {
                $accountObj                    = new \stdClass();
                $accountObj->providerAccountId = (string) $providerAccountId;
                $accountObj->campaigns         = [];
                // Guess the primary campaign (default) based on the name of the account.
                $accountObj->campaign = $this->campaignMapHelper->guess(
                    $this->providerAccountsWithCampaigns['accounts'][$providerAccountId]
                );
                $accountObj->multiple = 0;
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

    /**
     * @return array
     */
    private function getCampaignNames()
    {
        if (!$this->campaignNames && $this->em) {
            /** @var CampaignRepository */
            $campaignRepository = $this->em->get('mautic.campaign.model.campaign')->getRepository();
            $args               = [
                'orderBy'    => 'c.name',
                'orderByDir' => 'ASC',
            ];
            $campaigns          = $campaignRepository->getEntities($args);
            foreach ($campaigns as $campaign) {
                $id        = $campaign->getId();
                $published = $campaign->isPublished();
                $name      = $campaign->getName();
                // Adding periods to the end such that an unpublished campaign will be less likely to match against
                // a published campaign of the same name.
                $this->campaignNames[$id] = htmlspecialchars_decode($name).(!$published ? '.' : '');
            }
        }

        return $this->campaignNames;
    }
}
