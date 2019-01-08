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
        $jsonHelper       = new JSONHelper();
        $mediaAccountId   = (int) InputHelper::clean($request->request->get('mediaAccountId'));
        $mediaProvider    = InputHelper::clean($request->request->get('mediaProvider'));
        $campaignSettings = $jsonHelper->decodeObject(
            InputHelper::clean($request->request->get('campaignSettings')),
            'CampaignSettings'
        );

        // Get all our Mautic internal campaigns.
        $campaigns = [];
        /** @var CampaignRepository */
        $campaignRepository = $this->get('mautic.campaign.model.campaign')->getRepository();
        foreach ($campaignRepository->getEntities() as $campaign) {
            $id                                     = $campaign->getId();
            $published                              = $campaign->isPublished();
            $name                                   = $campaign->getName();
            $category                               = $campaign->getCategory();
            $category                               = $category ? $category->getName() : '';
            $campaigns[$name.'_'.$category.'_'.$id] = [
                'category'  => $category,
                'published' => $published,
                'name'      => $name,
                'title'     => $name.($category ? '  ('.$category.')' : '').(!$published ? '  (unpublished)' : ''),
                'value'     => $id,
            ];
        }
        $campaigns['   '] = [
            'value' => 0,
            'title' => count($campaigns) ? '-- Select a Campaign --' : '-- Please create a Campaign --',
        ];
        ksort($campaigns);
        $campaigns = array_values($campaigns);

        // Get all the third-party provider campaigns.
        $providerCampaigns = [];
        /** @var StatRepository $statRepository */
        $statRepository = $this->get('mautic.media.model.media')->getStatRepository();
        foreach ($statRepository->getProviderAccounts($mediaAccountId, $mediaProvider) as $id => $name) {
            $providerCampaigns[$name.'_'.$id] = [
                'name'  => $name,
                'title' => $name,
                'value' => $id,
            ];
        }
        ksort($providerCampaigns);
        $providerCampaigns = array_values($providerCampaigns);

        // Up
        foreach ($providerCampaigns as $providerCampaign) {
            $foundProviderCampaign = false;
            if (isset($campaignSettings->campaigns) && is_array($campaignSettings->campaigns)) {
                foreach ($campaignSettings->campaigns as $row) {
                    if (
                        isset($row->providerCampaignId)
                        && $row->providerCampaignId == $providerCampaign['value']
                    ) {
                        // This provider campaign is already in the map.
                        $foundProviderCampaign = true;
                        break;
                    }
                }
            }
            if (!$foundProviderCampaign) {
                if (!isset($campaignSettings->campaigns) || !is_array($campaignSettings->campaigns)) {
                    $campaignSettings->campaigns = [];
                }
                $obj                     = new \stdClass();
                $obj->providerCampaignId = $providerCampaign['value'];
                // @todo - implement guesswork magic.
                $obj->campaignId               = 0;
                $campaignSettings->campaigns[] = $obj;
            }
        }

        return $this->sendJsonResponse(
            [
                'campaigns'         => $campaigns,
                'providerCampaigns' => $providerCampaigns,
                'campaignSettings'  => $campaignSettings,
            ]
        );
    }
}
