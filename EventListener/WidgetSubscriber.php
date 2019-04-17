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

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;

/**
 * Adds the "Cost Breakdown" and "Revenue Breakdown" widgets on the Campaign
 * View page.
 */
class WidgetSubscriber extends CommonSubscriber
{

    /**
     * Views to inject the widgets into.
     * @var array
     */
    private $views = ['MauticCampaignBundle:Campaign:details.html.php'];

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
        if (in_array($event->getViewName(), $this->views) {
        }
    }
}
