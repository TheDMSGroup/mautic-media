<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name'        => 'Media',
    'description' => 'Pulls cost data from media advertising services.',
    'version'     => '1.0',
    'author'      => 'Mautic',

    'routes'   => [
        'main' => [
            'mautic_media_index'  => [
                'path'       => '/media/{page}',
                'controller' => 'MauticMediaBundle:Media:index',
            ],
            'mautic_media_action' => [
                'path'         => '/media/{objectAction}/{objectId}',
                'controller'   => 'MauticMediaBundle:Media:execute',
                'requirements' => [
                    'objectAction' => '\w+',
                    'objectId'     => '\w+',
                ],
            ],
        ],
    ],
    'services' => [
        'events'       => [
            'mautic.media.subscriber.stat'  => [
                'class'     => 'MauticPlugin\MauticMediaBundle\EventListener\StatSubscriber',
                'arguments' => [
                    'mautic.media.model.media',
                ],
            ],
            'mautic.media.subscriber.media' => [
                'class'     => 'MauticPlugin\MauticMediaBundle\EventListener\MadiaSubscriber',
                'arguments' => [
                    'router',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.form.helper.token',
                    'mautic.media.model.media',
                ],
            ],
            'mautic.media.stats.subscriber' => [
                'class'     => 'MauticPlugin\MauticMediaBundle\EventListener\StatsSubscriber',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'forms'        => [
            'mautic.media.form.type.mediashow_list' => [
                'class'     => 'MauticPlugin\MauticMediaBundle\Form\Type\MadiaShowType',
                'arguments' => 'router',
                'alias'     => 'mediashow_list',
            ],
            'mautic.media.form.type.media_list'     => [
                'class'     => 'MauticPlugin\MauticMediaBundle\Form\Type\MadiaListType',
                'arguments' => 'mautic.media.model.media',
                'alias'     => 'media_list',
            ],
            'mautic.media.form.type.media'          => [
                'class'     => 'MauticPlugin\MauticMediaBundle\Form\Type\MadiaType',
                'alias'     => 'media',
                'arguments' => 'mautic.security',
            ],
            'mautic.media.form.type.chartfilter'    => [
                'class'     => 'MauticPlugin\MauticMediaBundle\Form\Type\ChartFilterType',
                'arguments' => 'mautic.factory',
                'alias'     => 'chartfilter',
            ],
        ],
        'models'       => [
            'mautic.media.model.media' => [
                'class'     => 'MauticPlugin\MauticMediaBundle\Model\MediaAccount',
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.helper.templating',
                    'event_dispatcher',
                    'mautic.lead.model.lead',
                ],
            ],
        ],
        'integrations' => [
            'mautic.media.integration' => [
                'class' => 'MauticPlugin\MauticMediaBundle\Integration\MediaIntegration',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.media' => [
                'route'     => 'mautic_media_index',
                'access'    => 'plugin:media:items:view',
                'id'        => 'mautic_media_root',
                'iconClass' => 'fa-money',
                // 'priority'  => 35,
                'checks'    => [
                    'integration' => [
                        'Media' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],

    'categories' => [
        'plugin:media' => 'mautic.media',
    ],
];
