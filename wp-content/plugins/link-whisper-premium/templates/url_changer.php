<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('URL Changer','wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil_url_changer_table">
                    <form>
                        <input type="hidden" name="page" value="link_whisper_url_changer" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <form method="post" id="add_url_change_form">
                        <div method="post" style="float:left;">
                            <input type="text" name="old" placeholder="Old URL">
                            <input type="text" name="new" placeholder="New URL">
                            <input type="submit" value="Add URL" class="button-primary">
                        </div>
                    </form>
                    <div style="clear: both"></div>
                    <a href="javascript:void(0)" class="button-primary" id="wpil_url_changer_reset_button"><?php _e('Refresh Changed URL Report', 'wpil'); ?></a>
                    <?php if (!$reset) : ?>
                        <div class="table">
                            <?php $table->display(); ?>
                        </div>
                    <?php endif; ?>
                    <div class="progress" <?=$reset?'style="display:block"':''?>>
                        <h4 class="progress_panel_msg"><?php _e('Synchronizing your data..','wpil'); ?></h4>
                        <div class="progress_panel loader">
                            <div class="progress_count"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var wpil_url_changer_nonce = '<?=wp_create_nonce($user->ID . 'wpil_url_changer')?>';
    var is_wpil_url_changer_reset = <?=$reset?'true':'false'?>;
</script>