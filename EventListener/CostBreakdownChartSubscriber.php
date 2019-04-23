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
use MauticPlugin\MauticFocusBundle\Entity\StatRepository;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\HttpFoundation\Session\Session;
use MauticPlugin\MauticMediaBundle\Report\Dates;
use MauticPlugin\MauticMediaBundle\Report\CostBreakdownChart;

/**
 * Adds the "Cost Breakdown" and "Revenue Breakdown" charts on the Campaign
 * view page.
 */
class CostBreakdownChartSubscriber extends CommonSubscriber
{

    /**
     * CostBreakdownChartSubscriber constructor.
     *
     * @param EntityManager    $em
     * @param Session          $session
     */
    public function __construct(
        EntityManager $em,
        Session $session
    ) {
        $this->em               = $em;
        $this->session          = $session;
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
        if ($event->getViewName() == 'MauticCampaignBundle:Campaign:details.html.php') {
            switch ($event->getContext()) {

            case 'left.section.top':

                
                $dates = new Dates($this->request, $this->session);
                $vars = $event->getVars();

                $costBreakdown = new CostBreakdownChart($this->em->getRepository('MauticMediaBundle:Stat'), $this->em);
                
                $event->addTemplate(
                    'MauticMediaBundle:Charts:cost_breakdown_chart.html.php',
                    [
                        'costBreakdown' => $costBreakdown->getChart(
                            $vars['campaign']->getId(),
                            $dates->getFrom(),
                            $dates->getTo()
                        )
                    ]
                );
                break;
            
            }
        }
    }
}
