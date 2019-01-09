<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use MauticPlugin\MauticContactLedgerBundle\Event\ChartDataAlterEvent;
use MauticPlugin\MauticContactLedgerBundle\MauticContactLedgerEvents;

/**
 * Class ChartDataSubscriber
 */
class ChartDataSubscriber extends CommonSubscriber
{
    /**
     * @var MediaAccountModel
     */
    protected $model;

    /**
     * ChartDataSubscribe constructor.
     *
     * @param MediaAccountModel $model
     */
    public function __construct(MediaAccountModel $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [MauticContactLedgerEvents::CHART_DATA_ALTER => ['onChartDataAlter', 0]];
    }

    /**
     * @param ChartDataAlterEvent $event
     */
    public function onChartDataAlter(ChartDataAlterEvent $event)
    {
        switch ($event->getChartName()) {
            case 'campaign.revenue.chart':
                $this->modifyCampaignrevenueChart($event);
                break;

            case 'campaign.revenue.datatable':
                //do nothing right now
                break;
        }
    }

    /**
     * @param ChartDataAlterEvent $event
     */
    private function modifyCampaignRevenueChart($event)
    {
        $params = $event->getParams();
        $data   = $event->getData();
    }
}
