<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Error Report','wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_error">
                    <br clear="all">
                    <?php $table->display(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var admin_url = '<?=admin_url()?>';
</script>