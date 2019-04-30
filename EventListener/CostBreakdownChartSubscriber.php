<?php
/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use MauticPlugin\MauticMediaBundle\Report\CostBreakdownChart;
use MauticPlugin\MauticMediaBundle\Report\Dates;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Adds the "Cost Breakdown" and "Revenue Breakdown" charts on the Campaign
 * view page.
 */
class CostBreakdownChartSubscriber extends CommonSubscriber
{
    /**
     * @var CostBreakdownChart
     */
    private $chart;

    /**
     * @var Session
     */
    private $session;

    /**
     * CostBreakdownChartSubscriber constructor.
     *
     * @param EntityManager $em
     */
    public function __construct(CostBreakdownChart $costBreakdownChart, Session $session)
    {
        $this->chart   = $costBreakdownChart;
        $this->session = $session;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectReportWidgets', -1],
        ];
    }

    /**
     * @param CustomContentEvent $event
     */
    public function injectReportWidgets(CustomContentEvent $event)
    {
        if ('MauticCampaignBundle:Campaign:details.html.php' == $event->getViewName()) {
            switch ($event->getContext()) {
            case 'left.section.top':

                $vars  = $event->getVars();
                $dates = new Dates($this->request, $this->session);

                $event->addTemplate(
                    'MauticMediaBundle:Charts:cost_breakdown_chart.html.php',
                    [
                        'costBreakdown' => $this->chart->getChart(
                            $vars['campaign']->getId(),
                            $dates->getFrom(),
                            $dates->getTo()
                        ),
                    ]
                );
                break;
            }
        }
    }
}
