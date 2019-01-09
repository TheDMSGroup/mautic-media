<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Controller;

use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\InputHelper;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;
use MauticPlugin\MauticMediaBundle\Helper\JSONHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    /**
     * Retrieve a list of campaigns for use in drop-downs for a specific Media Account.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    protected function getCampaignMapAction(Request $request)
    {
        $jsonHelper            = new JSONHelper();
        $mediaAccountId        = (int) InputHelper::clean($request->request->get('mediaAccountId'));
        $mediaProvider         = InputHelper::clean($request->request->get('mediaProvider'));
        $campaignSettingsField = $jsonHelper->decodeObject(
            InputHelper::clean($request->request->get('campaignSettings')),
            'CampaignSettings'
        );

        // Get all our Mautic internal campaigns.
        $campaigns = [];
        /** @var CampaignRepository */
        $campaignRepository = $this->get('mautic.campaign.model.campaign')->getRepository();
        $args               = [
            'orderBy'    => 'c.name',
            'orderByDir' => 'ASC',
        ];
        $campaigns      = $campaignRepository->getEntities($args);
        $campaignsField = [[
            'value' => 0,
            'title' => count($campaigns) ? '-- Select a Campaign --' : '-- Please create a Campaign --',
        ]];
        foreach ($campaigns as $campaign) {
            $id                                     = $campaign->getId();
            $published                              = $campaign->isPublished();
            $name                                   = $campaign->getName();
            $category                               = $campaign->getCategory();
            $category                               = $category ? $category->getName() : '';
            $campaignsField[]                       = [
                'category'  => $category,
                'published' => $published,
                'name'      => $name,
                'title'     => $name.($category ? '  ('.$category.')' : '').(!$published ? '  (unpublished)' : ''),
                'value'     => $id,
            ];
        }

        // Get all recent and active campaigns and accounts from the provider.
        /** @var StatRepository $statRepository */
        $statRepository       = $this->get('mautic.media.model.media')->getStatRepository();
        $data                 = $statRepository->getProviderAccountsWithCampaigns($mediaAccountId, $mediaProvider);
        $providerAccountField = [
            [
                'value' => 0,
                'title' => count($data['accounts']) ? '-- Select an Account --' : '-- Please create an Account --',
            ],
        ];
        foreach ($data['accounts'] as $value => $title) {
            $providerAccountField[] = [
                'title' => $title,
                'value' => $value,
            ];
        }
        $providerCampaignField = [
            [
                'value' => 0,
                'title' => count($data['campaigns']) ? '-- Select a Campaign --' : '-- Please create a Campaign --',
            ],
        ];
        foreach ($data['campaigns'] as $value => $title) {
            $providerCampaignField[] = [
                'title' => $title,
                'value' => $value,
            ];
        }

        // Update the CampaignSettings (the map between provider and internal campaigns).
        foreach ($data['map'] as $providerAccountId => $providerCampaigns) {
            // Make sure this account is included.
            $accountFound = false;
            if (!isset($campaignSettingsField->accounts)) {
                $campaignSettingsField->accounts = [];
            }
            foreach ($campaignSettingsField->accounts as &$accountObj) {
                if (
                    isset($accountObj->providerAccountId)
                    && $accountObj->providerAccountId == $providerAccountId
                ) {
                    $accountFound = true;
                    break;
                }
            }
            if (!$accountFound) {
                $accountObj                     = new \stdClass();
                $accountObj->providerAccountId  = (string) $providerAccountId;
                $accountObj->mapping            = new \stdClass();
                $accountObj->mapping->campaigns = [];
            }
            if (isset($accountObj)) {
                // Make sure all campaigns are included in the account
                foreach ($providerCampaigns as $providerCampaignId) {
                    $campaignFound = false;
                    foreach ($accountObj->mapping->campaigns as &$campaignObj) {
                        if (
                            isset($campaignObj->providerCampaignId)
                            && $campaignObj->providerCampaignId == $providerCampaignId
                        ) {
                            $campaignFound = true;
                            break;
                        }
                    }
                    // Only add campaigns not already mapped
                    if (!$campaignFound) {
                        $campaignObj                     = new \stdClass();
                        $campaignObj->providerCampaignId = (string) $providerCampaignId;
                    }
                    if (isset($campaignObj)) {
                        // @todo - Automatic guessing goes here.
                        if (!empty($campaignObj) && empty($campaignObj->campaignId)) {
                            $campaignObj->campaignId = '1';
                        }
                        if (!$campaignFound) {
                            $accountObj->mapping->campaigns[] = $campaignObj;
                        }
                    }
                }
                if (!$accountFound) {
                    $campaignSettingsField->accounts[] = $accountObj;
                }
            }
        }

        return $this->sendJsonResponse(
            [
                'campaigns'         => $campaignsField,
                'providerAccounts'  => $providerAccountField,
                'providerCampaigns' => $providerCampaignField,
                'campaignSettings'  => $campaignSettingsField,
            ]
        );
    }
}
