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
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;
use MauticPlugin\MauticMediaBundle\Helper\CampaignSettingsHelper;
use MauticPlugin\MauticMediaBundle\Helper\CommonProviderHelper;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
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
        $mediaAccountId        = (int) InputHelper::clean($request->request->get('mediaAccountId'));
        $provider              = InputHelper::clean($request->request->get('provider'));
        $campaignSettingsField = html_entity_decode(InputHelper::clean($request->request->get('campaignSettings')));

        // Get all our Mautic internal campaigns.
        /** @var CampaignRepository */
        $campaignRepository = $this->get('mautic.campaign.model.campaign')->getRepository();
        $args               = [
            'orderBy'    => 'c.name',
            'orderByDir' => 'ASC',
        ];
        $campaigns          = $campaignRepository->getEntities($args);
        $campaignsField     = [
            [
                'value' => 0,
                'title' => count($campaigns) ? '-- No Campaign Mapped --' : '-- Please create a Campaign --',
            ],
        ];
        $campaignNames      = [];
        foreach ($campaigns as $campaign) {
            $id               = $campaign->getId();
            $published        = $campaign->isPublished();
            $name             = $campaign->getName();
            $category         = $campaign->getCategory();
            $category         = $category ? $category->getName() : '';
            $campaignsField[] = [
                'name'  => $name,
                'title' => htmlspecialchars_decode(
                    $name.($category ? '  ('.$category.')' : '').(!$published ? '  (unpublished)' : '')
                ),
                'value' => $id,
            ];
            // Adding periods to the end such that an unpublished campaign will be less likely to match against
            // a published campaign of the same name.
            $campaignNames[$id] = htmlspecialchars_decode($name).(!$published ? '.' : '');
        }

        // Get all recent and active campaigns and accounts from the provider.
        /** @var StatRepository $statRepository */
        $statRepository       = $this->get('mautic.media.model.media')->getStatRepository();
        $data                 = $statRepository->getProviderAccountsWithCampaigns($mediaAccountId, $provider);
        $providerAccountField = [
            [
                'value' => 0,
                'title' => count($data['accounts']) ? '-- Select an Account --' : '-- Please create an Account --',
            ],
        ];
        foreach ($data['accounts'] as $value => $title) {
            $providerAccountField[] = [
                'title' => htmlspecialchars_decode($title),
                'value' => (string) $value,
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
                'title' => htmlspecialchars_decode($title),
                'value' => (string) $value,
            ];
        }

        $campaignSettingsHelper = new CampaignSettingsHelper($campaignNames, $campaignSettingsField, $data);

        return $this->sendJsonResponse(
            [
                'success'           => true,
                'campaigns'         => $campaignsField,
                'providerAccounts'  => $providerAccountField,
                'providerCampaigns' => $providerCampaignField,
                'campaignSettings'  => $campaignSettingsHelper->getAutoUpdatedCampaignSettings(),
            ]
        );
    }

    /**
     * Begin an OAuth2 request with one of the supported providers.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    protected function startAuthAction(Request $request)
    {
        $mediaAccountId       = (int) InputHelper::clean($request->request->get('mediaAccountId'));
        $provider             = (string) InputHelper::clean($request->request->get('provider'));
        $providerAccountId    = (string) InputHelper::clean($request->request->get('accountId'));
        $providerClientId     = (string) InputHelper::clean($request->request->get('clientId'));
        $providerClientSecret = (string) InputHelper::clean($request->request->get('clientSecret'));
        $providerToken        = (string) InputHelper::clean($request->request->get('token'));
        $providerRefreshToken = (string) InputHelper::clean($request->request->get('refreshToken'));

        /** @var MediaAccountModel $model */
        $model = $this->get('mautic.media.model.media');
        // Load settings from DB just for a complete entity, if pre-existing.
        if ($mediaAccountId) {
            /** @var MediaAccount $mediaAccount */
            $mediaAccount = $model->getRepository()->getEntity($mediaAccountId);
        } else {
            $mediaAccount = new MediaAccount();
        }
        // Overlay browser session variable values.
        $mediaAccount->setProvider($provider);
        $mediaAccount->setAccountId($providerAccountId);
        $mediaAccount->setClientId($providerClientId);
        $mediaAccount->setClientSecret($providerClientSecret);
        $mediaAccount->setToken($providerToken);
        $mediaAccount->setRefreshToken($providerRefreshToken);

        /** @var CommonProviderHelper $providerHelper */
        $providerHelper = $model->getProviderHelper($mediaAccount);

        /** @var Router $router */
        $router      = $this->get('router');
        $redirectUri = $router->generate(
            'mautic_media_auth_callback',
            ['provider' => $mediaAccount->getProvider()],
            Router::ABSOLUTE_URL
        );

        // @todo - Temporary measure.
        $redirectUri = str_replace('http://', 'https://', $redirectUri);

        return $this->sendJsonResponse(
            [
                'success' => true,
                'authUri' => $providerHelper->getAuthUri($redirectUri),
            ]
        );
    }
}
