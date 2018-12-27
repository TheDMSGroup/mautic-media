<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if ('index' == $tmpl) {
    $view->extend('MauticMediaBundle:MediaAccount:index.html.php');
}
?>

<?php if (count($items)): ?>
    <div class="table-responsive page-list">
        <table class="table table-hover table-striped table-bordered media-list" id="mediaTable">
            <thead>
            <tr>
                <?php
                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'checkall'        => 'true',
                        'target'          => '#mediaTable',
                        'routeBase'       => 'media',
                        'templateButtons' => [
                            'delete' => $permissions['plugin:media:items:delete'],
                        ],
                    ]
                );

                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'sessionVar' => 'media',
                        'orderBy'    => 'f.name',
                        'text'       => 'mautic.core.name',
                        'class'      => 'col-media-name',
                        'default'    => true,
                    ]
                );

                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'sessionVar' => 'media',
                        'orderBy'    => 'c.title',
                        'text'       => 'mautic.core.category',
                        'class'      => 'visible-md visible-lg col-media-category',
                    ]
                );

                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'sessionVar' => 'media',
                        'orderBy'    => 'f.type',
                        'text'       => 'mautic.media.form.provider',
                        'class'      => 'visible-md visible-lg col-media-provider',
                    ]
                );

                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'sessionVar' => 'media',
                        'orderBy'    => 'f.id',
                        'text'       => 'mautic.core.id',
                        'class'      => 'visible-md visible-lg col-media-id',
                    ]
                );
                ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <?php /** @var $item \MauticPlugin\MauticMediaBundle\Entity\MediaAccount */ ?>
                <tr>
                    <td>
                        <?php
                        echo $view->render(
                            'MauticCoreBundle:Helper:list_actions.html.php',
                            [
                                'item'            => $item,
                                'templateButtons' => [
                                    'edit'   => $view['security']->hasEntityAccess(
                                        $permissions['plugin:media:items:editown'],
                                        $permissions['plugin:media:items:editother'],
                                        $item->getCreatedBy()
                                    ),
                                    'clone'  => $permissions['plugin:media:items:create'],
                                    'delete' => $view['security']->hasEntityAccess(
                                        $permissions['plugin:media:items:deleteown'],
                                        $permissions['plugin:media:items:deleteother'],
                                        $item->getCreatedBy()
                                    ),
                                ],
                                'routeBase'       => 'media',
                            ]
                        );
                        ?>
                    </td>
                    <td>
                        <div>
                            <?php echo $view->render(
                                'MauticCoreBundle:Helper:publishstatus_icon.html.php',
                                ['item' => $item, 'model' => 'media']
                            ); ?>
                            <a data-toggle="ajax" href="<?php echo $view['router']->path(
                                'mautic_media_action',
                                ['objectId' => $item->getId(), 'objectAction' => 'view']
                            ); ?>">
                                <?php echo $item->getName(); ?>
                            </a>
                        </div>
                        <?php if ($description = $item->getDescription()): ?>
                            <div class="text-muted mt-4">
                                <small><?php echo $description; ?></small>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="visible-md visible-lg">
                        <?php $category = $item->getCategory(); ?>
                        <?php $catName  = ($category) ? $category->getTitle() : $view['translator']->trans(
                            'mautic.core.form.uncategorized'
                        ); ?>
                        <?php $color = ($category) ? '#'.$category->getColor() : 'inherit'; ?>
                        <span style="white-space: nowrap;"><span class="label label-default pa-4"
                                                                 style="border: 1px solid #d5d5d5; background: <?php echo $color; ?>;"> </span> <span><?php echo $catName; ?></span></span>
                    </td>
                    <td class="visible-md visible-lg"><?php echo $view['translator']->trans(
                            'mautic.media.form.provider.'.$item->getProvider()
                        ); ?></td>
                    <td class="visible-md visible-lg"><?php echo $item->getId(); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-footer">
        <?php echo $view->render(
            'MauticCoreBundle:Helper:pagination.html.php',
            [
                'totalItems' => count($items),
                'page'       => $page,
                'limits'     => $limit,
                'baseUrl'    => $view['router']->path('mautic_media_index'),
                'sessionVar' => 'media',
            ]
        ); ?>
    </div>
<?php else: ?>
    <?php echo $view->render(
        'MauticCoreBundle:Helper:noresults.html.php',
        ['tip' => 'mautic.media.noresults.tip']
    ); ?>
<?php endif; ?>
