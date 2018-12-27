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

use Mautic\CoreBundle\Event\CommonEvent;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;

/**
 * Class MediaAccountEvent.
 */
class MediaAccountEvent extends CommonEvent
{
    /**
     * @param MediaAccount $media
     * @param bool|false   $isNew
     */
    public function __construct(MediaAccount $media, $isNew = false)
    {
        $this->entity = $media;
        $this->isNew  = $isNew;
    }

    /**
     * Returns the MediaAccount entity.
     *
     * @return MediaAccount
     */
    public function getMediaAccount()
    {
        return $this->entity;
    }

    /**
     * Sets the MediaAccount entity.
     *
     * @param MediaAccount $media
     */
    public function setMediaAccount(MediaAccount $media)
    {
        $this->entity = $media;
    }
}
