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

use Mautic\AssetBundle\Helper\TokenHelper as AssetTokenHelper;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\FormBundle\Helper\TokenHelper as FormTokenHelper;
use Mautic\PageBundle\Helper\TokenHelper as PageTokenHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticMediaBundle\Entity\EventRepository;
use MauticPlugin\MauticMediaBundle\Event\MediaEvent;
use MauticPlugin\MauticMediaBundle\Event\MediaTimelineEvent;
use MauticPlugin\MauticMediaBundle\MediaEvents;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MediaSubscriber.
 */
class MediaAccountSubscriber extends CommonSubscriber
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var IpLookupHelper
     */
    protected $ipHelper;

    /**
     * @var AuditLogModel
     */
    protected $auditLogModel;

    /**
     * @var TrackableModel
     */
    protected $trackableModel;

    /**
     * @var PageTokenHelper
     */
    protected $pageTokenHelper;

    /**
     * @var AssetTokenHelper
     */
    protected $assetTokenHelper;

    /**
     * @var FormTokenHelper
     */
    protected $formTokenHelper;

    /**
     * @var MediaAccountModel
     */
    protected $mediaModel;

    /** @var PageModel */
    protected $pageModel;

    /**
     * MediaSubscriber constructor.
     *
     * @param RouterInterface   $router
     * @param IpLookupHelper    $ipLookupHelper
     * @param AuditLogModel     $auditLogModel
     * @param TrackableModel    $trackableModel
     * @param PageTokenHelper   $pageTokenHelper
     * @param AssetTokenHelper  $assetTokenHelper
     * @param FormTokenHelper   $formTokenHelper
     * @param MediaAccountModel $mediaModel
     */
    public function __construct(
        RouterInterface $router,
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        TrackableModel $trackableModel,
        PageTokenHelper $pageTokenHelper,
        AssetTokenHelper $assetTokenHelper,
        FormTokenHelper $formTokenHelper,
        MediaAccountModel $mediaModel
    ) {
        $this->router           = $router;
        $this->ipHelper         = $ipLookupHelper;
        $this->auditLogModel    = $auditLogModel;
        $this->trackableModel   = $trackableModel;
        $this->pageTokenHelper  = $pageTokenHelper;
        $this->assetTokenHelper = $assetTokenHelper;
        $this->formTokenHelper  = $formTokenHelper;
        $this->mediaModel       = $mediaModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            MediaEvents::POST_SAVE   => ['onMediaPostSave', 0],
            MediaEvents::POST_DELETE => ['onMediaDelete', 0],
        ];
    }

    /**
     * Add an entry to the audit log.
     *
     * @param MediaEvent $event
     */
    public function onMediaPostSave(MediaEvent $event)
    {
        $entity = $event->getMedia();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'    => 'media',
                'object'    => 'media',
                'objectId'  => $entity->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
            ];
            $this->auditLogModel->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log.
     *
     * @param MediaEvent $event
     */
    public function onMediaDelete(MediaEvent $event)
    {
        $entity = $event->getMedia();
        $log    = [
            'bundle'    => 'media',
            'object'    => 'media',
            'objectId'  => $entity->deletedId,
            'action'    => 'delete',
            'details'   => ['name' => $entity->getName()],
            'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
        ];
        $this->auditLogModel->writeToLog($log);
    }

}
