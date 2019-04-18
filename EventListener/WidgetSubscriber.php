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

/**
 * Adds the "Cost Breakdown" and "Revenue Breakdown" widgets on the Campaign
 * View page.
 */
class WidgetSubscriber extends CommonSubscriber
{

    /**
     * WidgetSubscriber constructor.
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
     * Views to inject the widgets into.
     * @var array
     */
    private $views = [''];

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectReportWidgets', 0],
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
                // this should be in some date parsing class, tooken from
                // CustomContentSubscriber in MauticLedgerPlugin
                $postDateRange = $this->request->request->get('daterange', []); // POST vars
                if (empty($postDateRange)) {
                    /** @var \DateTime[] $dateRange */
                    $sessionDateFrom = $this->session->get('mautic.daterange.form.from'); // session Vars
                    $sessionDateTo   = $this->session->get('mautic.daterange.form.to');
                    if (empty($sessionDateFrom) && empty($sessionDateTo)) {
                        $dateRange = $this->dashboardModel->getDefaultFilter(); // App Default setting
                        $dateFrom  = new \DateTime($dateRange['date_from']);
                        $dateTo    = new \DateTime($dateRange['date_to']);
                    } else {
                        $dateFrom = new \DateTime($sessionDateFrom);
                        $dateTo   = new \DateTime($sessionDateTo);
                    }
                } else {
                    // convert POST strings to DateTime Objects
                    $dateFrom = new \DateTime($postDateRange['date_from']);
                    $dateTo   = new \DateTime($postDateRange['date_to']);
                    $this->session->set('mautic.daterange.form.from', $postDateRange['date_from']);
                    $this->session->set('mautic.daterange.form.to', $postDateRange['date_to']);
                }

                $dateFrom->setTime(0, 0, 0);
                $dateTo->setTime(23, 59, 59);

                $vars = $event->getVars();
                $statRepo = $this->em->getRepository('MauticMediaBundle:Stat');
                dump($vars['campaign']->getId());
                $costBreakdown = $statRepo->getCostBreakdown($vars['campaign']->getId(), $dateFrom, $dateTo);
                dump($costBreakdown);
                $event->addTemplate('MauticMediaBundle:Charts:cost_breakdown_chart.html.php', $costBreakdown);
                break;
            
            }
        }
    }
}
