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

use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;

/**
 * Class MediaAccountAccessTrait.
 */
trait MediaAccountAccessTrait
{
    /**
     * Determines if the user has access to the mediaAccount.
     *
     * @param        $mediaAccountId
     * @param        $action
     * @param bool   $isPlugin
     * @param string $integration
     *
     * @return MediaAccount
     */
    protected function checkMediaAccountAccess($mediaAccountId, $action, $isPlugin = false, $integration = '')
    {
        if (!$mediaAccountId instanceof MediaAccount) {
            //make sure the user has view access to this mediaAccount
            $mediaAccountModel = $this->getModel('mediaAccount');
            $mediaAccount      = $mediaAccountModel->getEntity((int) $mediaAccountId);
        } else {
            $mediaAccount   = $mediaAccountId;
            $mediaAccountId = $mediaAccount->getId();
        }

        if (null === $mediaAccount || !$mediaAccount->getId()) {
            if (method_exists($this, 'postActionRedirect')) {
                //set the return URL
                $page      = $this->get('session')->get(
                    $isPlugin ? 'mautic.'.$integration.'.page' : 'mautic.mediaAccount.page',
                    1
                );
                $returnUrl = $this->generateUrl(
                    $isPlugin ? 'mautic_plugin_timeline_index' : 'mautic_contact_index',
                    ['page' => $page]
                );

                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => ['page' => $page],
                        'contentTemplate' => $isPlugin ? 'MauticMediaBundle:MediaAccount:pluginIndex' : 'MauticMediaBundle:MediaAccount:index',
                        'passthroughVars' => [
                            'activeLink'    => $isPlugin ? '#mautic_plugin_timeline_index' : '#mautic_contact_index',
                            'mauticContent' => 'mediaAccountTimeline',
                        ],
                        'flashes'         => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.mediaAccount.mediaAccount.error.notfound',
                                'msgVars' => ['%id%' => $mediaAccountId],
                            ],
                        ],
                    ]
                );
            } else {
                return $this->notFound('mautic.contact.error.notfound');
            }
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'media:items:'.$action.'own',
            'media:items:'.$action.'other',
            $mediaAccount->getPermissionUser()
        )
        ) {
            return $this->accessDenied();
        } else {
            return $mediaAccount;
        }
    }

    /**
     * Returns mediaAccounts the user has access to.
     *
     * @param $action
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function checkAllAccess($action, $limit)
    {
        /** @var MediaAccountModel $model */
        $model = $this->getModel('mediaAccount');

        //make sure the user has view access to mediaAccounts
        $repo = $model->getRepository();

        // order by lastactive, filter
        $mediaAccounts = $repo->getEntities(
            [
                'filter'         => [],
                'oderBy'         => 'r.last_active',
                'orderByDir'     => 'DESC',
                'limit'          => $limit,
                'hydration_mode' => 'HYDRATE_ARRAY',
            ]
        );

        if (null === $mediaAccounts) {
            return $this->accessDenied();
        }

        foreach ($mediaAccounts as $mediaAccount) {
            if (!$this->get('mautic.security')->hasEntityAccess(
                'media:items:'.$action.'own',
                'media:items:'.$action.'other',
                $mediaAccount['createdBy']
            )
            ) {
                unset($mediaAccount);
            }
        }

        return $mediaAccounts;
    }
}
