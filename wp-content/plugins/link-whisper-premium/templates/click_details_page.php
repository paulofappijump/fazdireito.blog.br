<div class="wrap wpil-report-page wpil_styles" id="detailed_clicks_page">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e("Click Details", "wpil"); ?></h1>
    <a href="<?=esc_url($return_url)?>" class="page-title-action return_to_report"><?php _e('Return to Report','wpil'); ?></a>
    <h2><?php echo sprintf(__('Showing click details for: %s', 'wpil'), $sub_title); ?></h2>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php _e('Link Whisper Click Chart', 'wpil'); ?></span></h2>
                    <div class="inside">
                        <div id="link-click-detail-chart">
                            <input type="hidden" id="link-click-detail-data" value="<?php echo esc_attr(json_encode($click_chart_data)); ?>">
                            <input type="hidden" id="link-click-detail-data-range" value="<?php echo esc_attr(json_encode( array('start' => date('F d, Y', $start_date), 'end' => date('F d, Y', $end_date ) ) )); ?>">
                        </div>
                    </div>
                </div>
                <div class="wpil-click-detail-controls">
                    <div class="inside" style="margin: 0 0 30px 0; display: inline-block;">
                        <label for="wpil-click-detail-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px 2px; display: block; display: inline-block;"><?php _e('Filter Clicks by Date', 'wpil'); ?></label><br/>
                        <input id="wpil-click-detail-daterange" type="text" name="daterange" class="wpil-date-range-filter" value="<?php echo date('m/d/Y', $start_date) . ' - ' . date('m/d/Y', $end_date); ?>">
                    </div>
                    <div id="keywords" style="margin-top: 10px;">
                        <form action="" method="post">
                            <label for="keywords_field">Search by Keyword</label>
                            <textarea name="keywords" id="keywords_field"><?=!empty($_POST['keywords'])?sanitize_textarea_field($_POST['keywords']):''?></textarea>
                            <button type="submit" class="button-primary">Search</button>
                        </form>
                    </div>
                </div>
                <div id="wpil_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php _e('Link Whisper Click Data Table', 'wpil'); ?></span></h2>
                    <div class="inside">
                    <?php
                        $table = new Wpil_Table_DetailedClick();
                        $table->prepare_items();
                        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_detailed_clicks.php';
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
