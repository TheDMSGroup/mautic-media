<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Event;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class MediaAccountTimelineEvent.
 */
class MediaAccountStatEvent extends Event
{
    /** @var int */
    protected $eventId;

    /** @var int */
    protected $campaignId;

    /** @var MediaAccount */
    protected $mediaAccount;

    /** @var int */
    protected $contact;

    /** @var EntityManager */
    protected $em;

    /**
     * MediaAccountStatEvent constructor.
     *
     * @param MediaAccount $mediaAccount
     * @param int          $campaignId
     * @param int          $eventId
     * @param Contact      $contact
     */
    public function __construct(
        $mediaAccount,
        $campaignId,
        $eventId,
        $contact,
        $em
    ) {
        $this->mediaAccount = $mediaAccount;
        $this->campaignId   = $campaignId;
        $this->eventId      = $eventId;
        $this->contact      = $contact;
        $this->em           = $em;
    }

    /**
     * @return MediaAccount
     */
    public function getMediaAccount()
    {
        return $this->mediaAccount;
    }

    /**
     * @return int
     */
    public function getCampaignId()
    {
        return $this->campaignId;
    }

    /**
     * @return int|int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @return int
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }
}
