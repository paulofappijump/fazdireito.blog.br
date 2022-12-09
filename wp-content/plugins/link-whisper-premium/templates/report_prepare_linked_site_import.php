<div class="wrap wpil-loading-screen">
    <h1 class="wp-heading-inline"><?php _e("Internal Links Report","wpil"); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div>
                    <h3><?php _e('Importing Site Data...', 'wpil'); ?></h3>
                    <span class="wpil-loading-status-message">
                        <?php _e('Please don\'t close this tab otherwise the process will stop and have to be continued later.', 'wpil'); ?>
                    </span>
                </div>
                <?php 
                $sites = Wpil_SiteConnector::get_linked_sites(); 
                foreach($sites as $site){ ?>
                <div class="syns_div wpil_report_need_prepare processing" data-linked-url="<?php echo esc_url($site); ?>" data-page="0" data-saved="0" data-total="0" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'download-site-data-nonce'); ?>">
                    <h4 class="progress_panel_msg"><?php echo esc_html($site); ?></h4>
                    <div class="progress_panel">
                        <div class="progress_count" style="width: 0%"><span class="wpil-loading-status"></span></div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>