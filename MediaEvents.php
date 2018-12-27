<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle;

/**
 * Class MediaEvents.
 *
 * Events available for MauticMediaBundle
 */
final class MediaEvents
{
    /**
     * The mautic.media_post_delete event is dispatched after a media is deleted.
     *
     * The event listener receives a MauticPlugin\MauticMediaBundle\Event\MediaEvent instance.
     *
     * @var string
     */
    const POST_DELETE = 'mautic.media_post_delete';

    /**
     * The mautic.media_post_save event is dispatched right after a media is persisted.
     *
     * The event listener receives a MauticPlugin\MauticMediaBundle\Event\MediaEvent instance.
     *
     * @var string
     */
    const POST_SAVE = 'mautic.media_post_save';

    /**
     * The mautic.media_pre_delete event is dispatched before a media is deleted.
     *
     * The event listener receives a MauticPlugin\MauticMediaBundle\Event\MediaEvent instance.
     *
     * @var string
     */
    const PRE_DELETE = 'mautic.media_pre_delete';

    /**
     * The mautic.media_pre_save event is dispatched right before a media is persisted.
     *
     * The event listener receives a MauticPlugin\MauticMediaBundle\Event\MediaEvent instance.
     *
     * @var string
     */
    const PRE_SAVE = 'mautic.media_pre_save';

    /**
     * The mautic.media_stat_save event is dispatched after a Contact Client Stat is saved.
     *
     * The event listener receives a MauticPlugin\MauticMediaBundle\Event\ContactClientStatEvent instance.
     *
     * @var string
     */
    const STAT_SAVE = 'mautic.media_stat_save';

    /**
     * The mautic.media_timeline_on_generate event is dispatched when generating a timeline view.
     *
     * The event listener receives a
     * MauticPlugin\MauticMediaBundle\Event\LeadTimelineEvent instance.
     *
     * @var string
     */
    const TIMELINE_ON_GENERATE     = 'mautic.media_timeline_on_generate';

    const TRANSACTIONS_ON_GENERATE = 'mautic.media_transactions_on_generate';
}
