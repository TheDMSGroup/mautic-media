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

use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;

/**
 * Trait MediaAccountDetailsTrait.
 */
trait MediaAccountDetailsTrait
{

    /**
     * @param MediaAccount $mediaAccount
     * @param array|null   $filters
     * @param array|null   $orderBy
     * @param int          $page
     * @param int          $limit
     *
     * @return array
     */
    protected function getAuditlogs(
        MediaAccount $mediaAccount,
        array $filters = null,
        array $orderBy = null,
        $page = 1,
        $limit = 25
    ) {
        $session = $this->get('session');

        if (null == $filters) {
            $filters = $session->get(
                'mautic.media.'.$mediaAccount->getId().'.auditlog.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }

        if (null == $orderBy) {
            if (!$session->has('mautic.media.'.$mediaAccount->getId().'.auditlog.orderby')) {
                $session->set('mautic.media.'.$mediaAccount->getId().'.auditlog.orderby', 'al.dateAdded');
                $session->set('mautic.media.'.$mediaAccount->getId().'.auditlog.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.media.'.$mediaAccount->getId().'.auditlog.orderby'),
                $session->get('mautic.media.'.$mediaAccount->getId().'.auditlog.orderbydir'),
            ];
        }

        // Audit Log
        /** @var AuditLogModel $auditlogModel */
        $auditlogModel = $this->getModel('core.auditLog');

        $logs     = $auditlogModel->getLogForObject(
            'media',
            $mediaAccount->getId(),
            $mediaAccount->getDateAdded()
        );
        $logCount = count($logs);

        $types = [
            'delete'     => $this->translator->trans('mautic.media.event.delete'),
            'create'     => $this->translator->trans('mautic.media.event.create'),
            'identified' => $this->translator->trans('mautic.media.event.identified'),
            'ipadded'    => $this->translator->trans('mautic.media.event.ipadded'),
            'merge'      => $this->translator->trans('mautic.media.event.merge'),
            'update'     => $this->translator->trans('mautic.media.event.update'),
        ];

        return [
            'events'   => $logs,
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $types,
            'total'    => $logCount,
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => ceil($logCount / $limit),
        ];
    }
}
