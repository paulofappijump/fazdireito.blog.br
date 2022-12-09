<div class="wrap wpil-report-page wpil_styles">
    <?=Wpil_Base::showVersion();
    $codes = Wpil_Dashboard::getAllErrorCodes();
    $codes = (!empty($codes)) ? '&codes=' . implode(',', $codes) : '';
    ?>
    <h1 class="wp-heading-inline">Dashboard</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <?php if(WPIL_STATUS_HAS_RUN_SCAN){ ?>
                <div id="report_dashboard">
                    <div class="box">
                        <div class="title">Links Stats</div>
                        <div class="body" id="report_stats">
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=links')?>"><i class="dashicons dashicons-format-aside"></i><span>Posts Crawled</span><?=Wpil_Dashboard::getPostCount()?></a>
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=links')?>"><i class="dashicons dashicons-admin-links"></i><span>Links Found</span><?=Wpil_Dashboard::getLinksCount()?></a>
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=links&orderby=wpil_links_inbound_internal_count&order=desc')?>"><i class="dashicons dashicons-arrow-left-alt"></i><span>Internal Links</span><?=Wpil_Dashboard::getInternalLinksCount()?></a>
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=links&orphaned=1')?>"><i class="dashicons dashicons-dismiss"></i><span>Orphaned Posts</span><?=Wpil_Dashboard::getOrphanedPostsCount()?></a>
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=error' . $codes)?>"><i class="dashicons dashicons-admin-tools"></i><span>Broken Links</span><?=Wpil_Dashboard::getBrokenLinksCount()?></a>
                            <a href="<?=admin_url('admin.php?page=link_whisper&type=error&codes=404')?>"><i class="dashicons dashicons-search"></i><span>404 errors</span><?=Wpil_Dashboard::get404LinksCount()?></a>
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Most linked to <a href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>">domains</a></div>
                        <div class="body" id="report_dashboard_domains">
                            <?php
                                $i=0;
                                $prev = isset($domains[0]->cnt) ? $domains[0]->cnt : 0;
                            ?>
                            <?php foreach ($domains as $domain) : ?>
                                <?php if ($prev != $domain->cnt) { $i++; $prev = $domain->cnt; } ?>
                                <div>
                                    <div class="count"><?=$domain->cnt?></div>
                                    <div class="host"><?=$domain->host?></div>
                                </div>
                                <div class="line line<?=$i?>"><span style="width: <?=(($domain->cnt/$top_domain)*100)?>%"></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="box">
                        <div class="title">Internal vs External links</div>
                        <div class="body">
                            <div id="wpil_links_chart" style="width: 320px;height: 320px;"></div>
                            <input type="hidden" name="total_links_count" value="<?=Wpil_Dashboard::getLinksCount()?>">
                            <input type="hidden" name="internal_links_count" value="<?=Wpil_Dashboard::getInternalLinksCount()?>">
                        </div>
                    </div>
                </div>
                <?php
                }else{ ?>
                <div class="run-first-scan-wrapper">
                    <div class="run-first-scan-container">
                        <div>
                            <p style="font-weight: 600; font-size: 20px !important;">
                                <?php
                                _e('To complete the first time set up, please run a link scan.', 'wpil');
                                ?>
                            </p>
                            <p style="font-weight: 600; font-size: 17px !important;">
                                <?php
                                _e('Link Whisper needs this scan to show you link metrics and to provide advanced functionality like error checking and autolinking.', 'wpil');
                                ?>
                            </p>
                            <p style="font-weight: 600; font-size: 17px !important;">
                                <?php
                                _e('If you have already run a scan, please run a new scan to complete the update. Your settings won\'t be affected, Link Whisper just needs to make sure the database is up to date.', 'wpil');
                                ?>
                            </p>
                            <form action='' method="post" id="wpil_report_reset_data_form" style="float:none;margin-top:50px;">
                                <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_reset_report_data'); ?>">
                                <?php if (!empty($_GET['type'])) : ?>
                                    <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=esc_attr($_GET['type'])?>" id="wpil_cvs_export_button" data-file-name="<?php esc_attr_e('detailed-link-export.csv', 'wpil'); ?>">Detailed Export to CSV</a>
                                    <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=esc_attr($_GET['type'])?>_summary" id="wpil_cvs_export_button" data-file-name="<?php esc_attr_e('summary-link-export.csv', 'wpil'); ?>">Summary Export to CSV</a>
                                <?php endif; ?>
                                <button type="submit" class="button-primary initial-scan-button"><?php _e('Run Link Scan');?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php }?>
            </div>
        </div>
    </div>
</div>
