<h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
    <?php $type = (isset($_GET['type']) && !empty($_GET['type'])) ? $_GET['type']: ''; ?>
    <a class="nav-tab <?=empty($type)?'nav-tab-active':''?>" id="general-tab" href="<?=admin_url('admin.php?page=link_whisper')?>"><?php  _e( "Dashboard", 'wpil' )?></a>
    <?php if(WPIL_STATUS_HAS_RUN_SCAN){ ?>
    <?php 
        // get any filter settings from the user's report selection and apply the settings to the Link Report tab url
        $filter_settings = get_user_meta(get_current_user_id(), 'wpil_filter_settings', true);
        $filter_vars = '';
        if(isset($filter_settings['report'])){
            $filtering = array();
            if(isset($filter_settings['report']['post_type']) && !empty($filter_settings['report']['post_type'])){
                $filtering['post_type'] = $filter_settings['report']['post_type'];
            }

            if(isset($filter_settings['report']['category']) && !empty($filter_settings['report']['category'])){
                $filtering['category'] = $filter_settings['report']['category'];
            }

            if(isset($filter_settings['report']['location']) && !empty($filter_settings['report']['location'])){
                $filtering['location'] = $filter_settings['report']['location'];
            }

            if(!empty($filtering)){
                $filter_vars = '&' . http_build_query($filtering);
            }
        } 
    ?>
    <a class="nav-tab <?=($type == 'links')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=link_whisper&type=links' . $filter_vars)?>"><?php  _e( "Links Report", 'wpil' )?> </a>
    <a class="nav-tab <?=($type == 'domains')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>"><?php  _e( "Domains Report", 'wpil' )?> </a>
    <?php if(empty(get_option('wpil_disable_click_tracking', false))){ ?>
    <a class="nav-tab <?=($type == 'clicks')?'nav-tab-active':''?>" id="post_types-tab" href="<?=admin_url('admin.php?page=link_whisper&type=clicks')?>"><?php  _e( "Clicks Report", 'wpil' )?> </a>
    <?php } ?>
    <a class="nav-tab <?=($type == 'error')?'nav-tab-active':''?>" id="post_types-tab" href="<?=admin_url('admin.php?page=link_whisper&type=error')?>"><?php  _e( "Error Report", 'wpil' )?> </a>

    <?php if($type == 'error'){ ?>
    <form action='' method="post" id="wpil_error_reset_data_form">
        <input type="hidden" name="reset" value="1">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_error_reset_data'); ?>">
        <a href="javascript:void(0)" class="button-primary csv_button" data-type="error" id="wpil_cvs_export_button" <?php echo isset($_GET['codes']) && !empty($_GET['codes']) ? 'data-codes="' . implode(',', array_map(function($code){ return (int)$code; }, explode(',', $_GET['codes']))) . '"': ''; ?> data-file-name="<?php esc_attr_e('error-code-export.csv', 'wpil'); ?>">Export to CSV</a>
        <button type="submit" class="button-primary"><?php _e('Scan for Broken Links', 'wpil'); ?></button>
    </form>
    <?php }elseif($type==='clicks'){?>
    <form action='' method="post" id="wpil_clear_clicks_data_form">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_clear_clicks_data'); ?>">
        <button type="submit" class="button-primary">Erase Click Data</button>
    </form>
    <?php }else{ ?>
    <form action='' method="post" id="wpil_report_reset_data_form">
        <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_reset_report_data'); ?>">
        <?php if (!empty($_GET['type'])) : ?>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>" id="wpil_cvs_export_button"  data-file-name="<?php esc_attr_e('detailed-link-export.csv', 'wpil'); ?>">Detailed Export to CSV</a>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=$_GET['type']?>_summary" id="wpil_cvs_export_button"  data-file-name="<?php esc_attr_e('summary-link-export.csv', 'wpil'); ?>">Summary Export to CSV</a>
        <?php endif; ?>
        <button type="submit" class="button-primary">Run a Link Scan</button>
    </form>
    <?php } ?>
    <?php } // end link table exist check
    ?>
</h2>
