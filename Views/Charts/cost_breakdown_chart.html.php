<?php
/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

?> 
<div class="pa-md">
    <div class="row">
        <div class="col-sm-12">
            <div class="panel">
                <div class="panel-body box-layout">
                    <div class="col-md-3 va-m">
                        <h5 class="text-white dark-md fw-sb mb-xs">
                            <span class="fa fa-line-chart"></span> 
                            <?php echo $view['translator']->trans('mautic.media.widget.campaign_cost_breakdown_title'); ?>
                        </h5>
                    </div>
                    <div class="col-md-9 va-m">
                    </div>
                </div>
                <div class="pt-0 pl-15 pb-10 pr-15">
<?php
if (is_array($costBreakdown) && !empty($costBreakdown)) {
    echo $view->render(
        'MauticCoreBundle:Helper:chart.html.php',
        ['chartData' => $costBreakdown, 'chartType' => 'line', 'chartHeight' => 300]
    );
} else {
    ?>
    <h3>No media accounts/campaigns mapped</h3>
    <p>This campaign has no media spending mapped to it for this date range.
    <br>
    This can be mapped for charting by an media manager or administrator <a href="/s/media">here</a>.</p>
<?php
}
?>
                </div>
             </div>
         </div>
    </div>
</div>
