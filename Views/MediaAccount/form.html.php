<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'media');

$header = ($entity->getId())
    ?
    $view['translator']->trans(
        'mautic.media.edit',
        ['%name%' => $view['translator']->trans($entity->getName())]
    )
    :
    $view['translator']->trans('mautic.media.new');
$view['slots']->set('headerTitle', $header);

echo $view['assets']->includeScript(
    'plugins/MauticMediaBundle/Assets/build/media.min.js',
    'mediaOnLoad',
    'mediaOnLoad'
);
echo $view['assets']->includeStylesheet('plugins/MauticMediaBundle/Assets/build/media.min.css');

echo $view['form']->start($form);

$callbackUri = $view->escape(
    $view['router']->generate('mautic_media_auth_callback', ['provider' => $entity->getProvider()], 0)
);
// @todo - Temporary measure.
$callbackUri = str_replace('http://', 'https://', $callbackUri);
?>

<!-- start: box layout -->
<div class="box-layout">

    <!-- tab container -->
    <div class="col-md-9 bg-white height-auto bdr-l media-left">
        <div class="">
            <ul class="nav nav-tabs pr-md pl-md mt-10">
                <li class="active">
                    <a href="#details" role="tab" data-toggle="tab" class="media-tab">
                        <i class="fa fa-cog fa-lg pull-left"></i><?php echo $view['translator']->trans(
                            'mautic.media.form.group.details'
                        ); ?>
                    </a>
                </li>
                <li>
                    <a href="#campaigns" role="tab" data-toggle="tab" class="media-tab">
                        <i class="fa fa-clock-o fa-lg pull-left"></i><?php echo $view['translator']->trans(
                            'mautic.media.form.group.campaigns'
                        ); ?>
                    </a>
                </li>
                <li>
                    <a href="#credentials" role="tab" data-toggle="tab" class="media-tab">
                        <i class="fa fa-key fa-lg pull-left"></i><?php echo $view['translator']->trans(
                            'mautic.media.form.group.credentials'
                        ); ?>
                    </a>
                </li>
            </ul>
        </div>
        <div class="tab-content">
            <!-- pane -->
            <div class="tab-pane fade in active bdr-rds-0 bdr-w-0" id="details">
                <div class="pa-md">
                    <div class="form-group mb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['name']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['provider']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['description']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade bdr-rds-0 bdr-w-0" id="credentials">
                <div class="pa-md">
                    <div class="form-group mb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['account_id']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['client_id']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['client_secret']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['token']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['refresh_token']); ?>
                            </div>
                        </div>
                        <div class="row" id="authButton">
                            <div class="col-md-12">
                                <div class="well">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <?php echo $view['translator']->trans('mautic.integration.callbackuri'); ?>
                                            <br/>
                                            <input id="media-callback-uri" type="text" readonly onclick="this.setSelectionRange(0, this.value.length);" value="<?php echo $callbackUri; ?>" class="form-control"/>
                                            <br/>
                                            <?php echo $view['form']->widget($form['authButton']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <hr class="mnr-md mnl-md"> -->
                    </div>
                </div>
            </div>
            <div class="tab-pane fade bdr-rds-0 bdr-w-0" id="campaigns">
                <div class="pa-md">
                    <div class="form-group mb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['campaign_settings']); ?>
                            </div>
                        </div>
                        <span id="media-campaigns-loading">
                                <?php echo $view['translator']->trans(
                                    'mautic.media.form.campaign_settings.loading'
                                ); ?>
                            <i class="fa fa-spinner fa-spin"></i>
                        </span>
                        <span id="media-campaigns-empty" class="hide">
                            <?php echo $view['translator']->trans(
                                'mautic.media.form.campaign_settings.empty'
                            ); ?>
                        </span>
                        <!-- <hr class="mnr-md mnl-md"> -->
                    </div>
                </div>
            </div>
            <!--/ #pane -->
        </div>
    </div>
    <!--/ tab container -->

    <!-- container -->
    <div class="col-md-3 bg-white height-auto media-right">
        <div class="pr-lg pl-lg pt-md pb-md">
            <?php
            echo $view['form']->row($form['category']);
            echo $view['form']->row($form['isPublished']);
            echo $view['form']->row($form['publishUp']);
            echo $view['form']->row($form['publishDown']);
            ?>
        </div>
    </div>
    <!--/ container -->
</div>
<!--/ box layout -->

<?php echo $view['form']->end($form); ?>
<input type="hidden" name="entityId" id="entityId" value="<?php echo $entity->getId(); ?>"/>
<script>
    mauticLang['mautic.media.form.provider.bing.account_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.bing.account_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.bing.client_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.bing.client_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.bing.client_secret'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.bing.client_secret'
    ); ?>';
    mauticLang['mautic.media.form.provider.bing.token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.bing.token'
    ); ?>';
    mauticLang['mautic.media.form.provider.bing.refresh_token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.bing.refresh_token'
    ); ?>';
    mauticLang['mautic.media.form.provider.facebook.account_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.facebook.account_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.facebook.client_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.facebook.client_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.facebook.client_secret'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.facebook.client_secret'
    ); ?>';
    mauticLang['mautic.media.form.provider.facebook.token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.facebook.token'
    ); ?>';
    mauticLang['mautic.media.form.provider.facebook.refresh_token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.facebook.refresh_token'
    ); ?>';
    mauticLang['mautic.media.form.provider.google.account_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.google.account_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.google.client_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.google.client_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.google.client_secret'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.google.client_secret'
    ); ?>';
    mauticLang['mautic.media.form.provider.google.token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.google.token'
    ); ?>';
    mauticLang['mautic.media.form.provider.google.refresh_token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.google.refresh_token'
    ); ?>';
    mauticLang['mautic.media.form.provider.snapchat.account_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.snapchat.account_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.snapchat.client_id'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.snapchat.client_id'
    ); ?>';
    mauticLang['mautic.media.form.provider.snapchat.client_secret'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.snapchat.client_secret'
    ); ?>';
    mauticLang['mautic.media.form.provider.snapchat.token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.snapchat.token'
    ); ?>';
    mauticLang['mautic.media.form.provider.snapchat.refresh_token'] = '<?php echo $view['translator']->trans(
        'mautic.media.form.provider.snapchat.refresh_token'
    ); ?>';
</script>
