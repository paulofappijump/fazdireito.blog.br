<div class="wrap wpil-report-page wpil_styles">
    <style type="text/css">
        .wpil-content{
            padding: 0px;
        }
        <?php
            $sources = Wpil_TargetKeyword::get_active_keyword_sources();
            $num = count($sources);

            switch ($num) {
                case '6':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(600% + 100px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        right: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        right: calc(400% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-5 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-5 .create-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        right: calc(500% + 100px);
                    }
                    <?php
                break;
                case '5':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(500% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        right: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .create-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        right: calc(400% + 80px);
                    }
                    <?php
                break;
                case '4':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(400% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        right: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .create-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        right: calc(300% + .;/60px);
                    }
                    <?php
                break;
                case '3':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(300% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(300% + 40px);
                        position: relative;
                        right: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .create-post-keywords{
                        width: calc(300% + 40px);
                        position: relative;
                        right: calc(200% + 40px);
                    }
                    <?php
                break;
                case '2':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(200% + 20px);
                        position: relative;
                        right: 0;
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .create-post-keywords{
                        width: calc(200% + 20px);
                        position: relative;
                        right: calc(100% + 20px);
                    }
                    <?php
                break;
                case '1':
                    ?>
                    .column-custom .wpil-content{
                        width: calc(100% - 10px);
                    }
                    .column-custom .create-post-keywords{
                        width: 100%;
                    }
                    <?php
                break;
            }
            ?>
    </style>
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Target Keywords', 'wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil_target_keyword_table">
                    <form>
                        <input type="hidden" name="page" value="link_whisper_target_keywords" />
                        <?php $table->search_box('Search', 'search'); ?>
                    </form>
                    <div style="clear: both"></div>
                    <input type="hidden" id="wpil_target_keyword_gsc_authenticated" value="<?php echo (Wpil_Settings::HasGSCCredentials()) ? 1: 0; ?>">
                    <input type="hidden" id="wpil_target_keyword_reset_notice" value="<?php _e('Please confirm refreshing the target keywords. If you\'ve authenticated the connection to Google Search Console, this will refresh the keyword data.', 'wpil'); ?>" >
                    <input type="hidden" id="wpil_target_keyword_gsc_not_authtext_a" value="<?php _e('Link Whisper can not connect to Google Search Console because it has not been authorized yet.', 'wpil'); ?>">
                    <input type="hidden" id="wpil_target_keyword_gsc_not_authtext_b" value="<?php _e('Please go to the Link Whisper Settings and authorize access.', 'wpil'); ?>">
                    <div class="wpil_help" style="float:right">
                        <i class="dashicons dashicons-editor-help"></i>
                        <div>
                            <?php _e('Clicking Refresh Target Keywords will clear and re-import any Yoast or Rank Math keywords, and all inactive Google Search Console keywords.', 'wpil'); ?>
                            <br />
                            <br />
                            <?php _e('If you have just installed Link Whisper, authorized the GSC connect, or don\'t see Yoast/Rank Math keywords, please click this button.', 'wpil'); ?>
                        </div>
                    </div>
                    <a href="javascript:void(0)" class="button-primary" id="wpil_target_keyword_reset_button"><?php _e('Refresh Target Keywords', 'wpil'); ?></a>
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
    var wpil_target_keyword_nonce = '<?=wp_create_nonce($user->ID . 'wpil_target_keyword')?>';
    var is_wpil_target_keyword_reset = <?=$reset?'true':'false'?>;
    var admin_url = '<?=admin_url()?>';
</script>