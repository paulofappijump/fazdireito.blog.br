<?php
    // get the license status data
    $license    = get_option(WPIL_OPTION_LICENSE_KEY, '');
    $status     = get_option(WPIL_OPTION_LICENSE_STATUS);
    $last_error = get_option(WPIL_OPTION_LICENSE_LAST_ERROR, '');

    // get the current licensing state
    $licensing_state;
    if(empty($license) && empty($last_error) || ('invalid' === $status && 'Deactivated manually' === $last_error)){
        $licensing_state = 'not_activated';
    }elseif(!empty($license) && 'valid' === $status){
        $licensing_state = 'activated';
    }else{
        $licensing_state = 'error';
    }

    // create titles for the license statuses
    $status_titles   = array(
        'not_activated' => __('License Not Active', 'wpil'),
        'activated'     => __('License Active', 'wpil'),
        'error'         => __('License Error', 'wpil')
    );

    // create some helpful text to tell the user what's going on
    $status_messages = array(
        'not_activated' => __('Please enter your Link Whisper License Key to activate Link Whisper.', 'wpil'),
        'activated'     => __('Congratulations! Your Link Whisper License Key has been confirmed and Link Whisper is now active!', 'wpil'),
        'error'         => $last_error
    );

    // get if the user has enabled site interlinking
    $site_linking_enabled = get_option('wpil_link_external_sites', false);

    // get if the user has limited the number of links per post
    $max_links_per_post = get_option('wpil_max_links_per_post', 0);

    // get if the user has limited the number of inbound links per post
    $max_inbound_links_per_post = get_option('wpil_max_inbound_links_per_post', 0);

    // get the max age of posts that links will be inserted in
    $max_linking_age = get_option('wpil_max_linking_age', 0);

    // get the max age of posts that links will be inserted in
    $max_suggestion_count = get_option('wpil_max_suggestion_count', 0);

    // get if we're not tracking user ips with the click tracking
    $disable_ip_tracking = get_option('wpil_disable_click_tracking_info_gathering', false);

    // get the section skip type
    $skip_type = Wpil_Settings::getSkipSectionType();

    // get if we're filtering staging
    $filter_staging_url = !empty(get_option('wpil_filter_staging_url', false));

    // get the content formatting level
    $formatting_level = Wpil_Settings::getContentFormattingLevel();

    // get if the user is ignoring any tags from linking
    $ignored_linking_tags = Wpil_Settings::getIgnoreLinkingTags();
?>
<div class="wrap wpil_styles" id="settings_page">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php _e('Link Whisper Settings', 'wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
                <a class="nav-tab nav-tab-active" id="wpil-general-settings" href="#"><?php _e('General Settings', 'wpil'); ?></a>
                <a class="nav-tab " id="wpil-content-ignoring-settings" href="#"><?php _e('Content Ignoring', 'wpil'); ?></a>
                <a class="nav-tab " id="wpil-advanced-settings" href="#"><?php _e('Advanced Settings', 'wpil'); ?></a>
                <a class="nav-tab " id="wpil-licensing" href="#"><?php _e('Licensing', 'wpil'); ?></a>
            </h2>
            <div id="post-body-content" style="position: relative;">
                <?php
                    // if the user has authed GSC, check the status
                    if(Wpil_Settings::HasGSCCredentials()){
                        Wpil_SearchConsole::refresh_auth_token();
                        $authenticated = Wpil_SearchConsole::is_authenticated();
                        $gsc_profile = Wpil_SearchConsole::get_site_profile();
                        $profile_not_found = get_option('wpil_gsc_profile_not_easily_found', false);
                    }else{
                        $authenticated = false;
                        $gsc_profile = false;
                        $profile_not_found = false;
                    }
                ?>
                <?php if (isset($_REQUEST['success']) && !isset($_REQUEST['access_valid'])) : ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php _e('The Link Whisper Settings have been updated successfully!', 'wpil'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if($message = get_transient('wpil_gsc_access_status_message')){
                    if($message['status']){
                        if(!empty($gsc_profile)){?>
                            <div class="notice update notice-success" id="wpil_message" >
                                <p><?php echo esc_html($message['text']); ?></p>
                            </div><?php
                        }
                    }else{?>
                        <div class="notice update notice-error" id="wpil_message" >
                        <p><?php echo esc_html($message['text']); ?></p>
                    </div>
                    <?php
                    }
                    ?>
                <?php } ?>
                <?php if(isset($_REQUEST['broken_link_scan_cancelled']) && $message = get_transient('wpil_clear_error_checker_message')){ ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(isset($_REQUEST['database_creation_activated']) && $message = get_transient('wpil_database_creation_message')){ ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(isset($_REQUEST['database_update_activated']) && $message = get_transient('wpil_database_update_message')){ ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(array_key_exists('user_data_deleted', $_REQUEST) && $message = get_transient('wpil_user_data_delete_message')){ ?>
                    <?php if(!empty($_REQUEST['user_data_deleted'])){ ?>
                    <div class="notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                    <?php }else{ ?>
                    <div class="notice update notice-error" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                    <?php } ?>
                <?php } ?>
                <?php if(!empty($authenticated) && empty($gsc_profile)){?>
                    <div class="notice update notice-error" id="wpil_message" >
                        <p><?php _e('Connection Error: Either the selected Google account doesn\'t have Search Console access for this site, or Link Whisper is having trouble selecting this site. If you\'re sure the selected account has access to this site\'s GSC data, please select this site\'s profile from the "Select Site Profile From Search Console List" option.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(!extension_loaded('mbstring')){?>
                    <div class="notice update notice-error" id="wpil_message" >
                        <p><?php _e('Dependency Missing: Multibyte String.', 'wpil'); ?></p>
                        <p><?php _e('The Multibyte String PHP extension is not active on your site. Link Whisper uses this extension to process text when making suggestions. Without this extension, Link Whisper will not be able to make suggestions.', 'wpil'); ?></p>
                        <p><?php _e('Please contact your hosting provider about enabling the Multibyte String PHP extension.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(!extension_loaded('zlib') && !extension_loaded('Bz2')){?>
                    <div class="notice update notice-error" id="wpil_message" >
                        <p><?php _e('Dependency Missing: Data Compression Library.', 'wpil'); ?></p>
                        <p><?php _e('Link Whisper hasn\'t detected a useable compression library on this site. Link Whisper uses compression libraries to reduce how much memory is used when generating suggestions.', 'wpil'); ?></p>
                        <p><?php _e('It will try to generate suggestions without compressing the suggestion data. If Link Whisper runs out of memory, the suggestion loading will hang in place indefinitely.', 'wpil'); ?></p>
                        <p><?php _e('If you experience this, please contact your hosting provider about enabling either the "Zlib" compression library, or the "Bzip2" compression library.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <form name="frmSaveSettings" id="frmSaveSettings" action='' method='post'>
                    <?php wp_nonce_field('wpil_save_settings','wpil_save_settings_nonce'); ?>
                    <input type="hidden" name="hidden_action" value="wpil_save_settings" />
                    <table class="form-table">
                        <tbody>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Link Whisper created internal links open in new tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_2_links_open_new_tab" value="0" />
                                    <input type="checkbox" name="wpil_2_links_open_new_tab" <?=get_option('wpil_2_links_open_new_tab')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to set all links that it creates pointing to pages on this site to open in a new tab.', 'wpil');
                                            echo '<br /><br />';
                                            _e('Changing this setting will not update existing links.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                            $open_external = get_option('wpil_external_links_open_new_tab', null);
                            // if open external isn't set, use the other link option
                            $open_external = ($open_external === null) ? get_option('wpil_2_links_open_new_tab'): $open_external;
                        ?>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Link Whisper created external links open in new tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_external_links_open_new_tab" value="0" />
                                    <input type="checkbox" name="wpil_external_links_open_new_tab" <?=$open_external==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to set all links that it creates pointing to external sites to open in a new tab.', 'wpil');
                                            echo '<br /><br />';
                                            _e('Changing this setting will not update existing links.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Ignore Numbers', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_2_ignore_numbers" value="0" />
                                <input type="checkbox" name="wpil_2_ignore_numbers" <?=get_option('wpil_2_ignore_numbers')==1?'checked':''?> value="1" />
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Selected Language', 'wpil'); ?></td>
                            <td>
                                <select id="wpil-selected-language" name="wpil_selected_language">
                                    <?php
                                        $languages = Wpil_Settings::getSupportedLanguages();
                                        $selected_language = Wpil_Settings::getSelectedLanguage();
                                    ?>
                                    <?php foreach($languages as $language_key => $language_name) : ?>
                                        <option value="<?php echo $language_key; ?>" <?php selected($language_key, $selected_language); ?>><?php echo $language_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="wpil-currently-selected-language" value="<?php echo $selected_language; ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-1" value="<?php echo esc_attr__('Changing Link Whisper\'s language will replace the current Words to be Ignored with a new list of words.', 'wpil') ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-2" value="<?php echo esc_attr__('If you\'ve added any words to the Words to be Ignored area, this will erase them.', 'wpil') ?>">
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Words to be Ignored', 'wpil'); ?></td>
                            <td>
                                <?php
                                    $lang_data = array();
                                    foreach(Wpil_Settings::getAllIgnoreWordLists() as $lang_id => $words){
                                        $lang_data[$lang_id] = $words;
                                    }
                                ?>
                                <textarea id='ignore_words_textarea' class='regular-text' style="float:left;" rows=10><?php echo esc_textarea(implode("\n", $lang_data[$selected_language])); ?></textarea>
                                <input type="hidden" name='ignore_words' id='ignore_words' value="<?php echo base64_encode(implode("\n", $lang_data[$selected_language])); ?>">
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will ignore these words when making linking suggestions. Please enter each word on a new line', 'wpil'); ?></div>
                                </div>
                                <input type="hidden" id="wpil-available-language-word-lists" value="<?php echo esc_attr( wp_json_encode($lang_data, JSON_UNESCAPED_UNICODE) ); ?>">
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Pages to Completely Ignore from Link Whisper.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_pages_completely' id='wpil_ignore_pages_completely' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_pages_completely', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will completely ignore posts and category pages listed in this field.', 'wpil');
                                        echo '<br /><br />';
                                        _e('No suggestions will be made TO or FROM the pages listed, no links will be scanned from them, and no autolinks created in them.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a page, enter its URL in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('After entering a URL, you may want to run a link scan to refresh the link data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Don\'t Show Suggestion Ignored Posts in the Reports', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_dont_show_ignored_posts" value="0" />
                                    <input type="checkbox" name="wpil_dont_show_ignored_posts" <?=get_option('wpil_dont_show_ignored_posts')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('Checking this will tell Link Whisper to hide pages that have been ignored so they don\'t show up in the Reports.', 'wpil');?>
                                            <br />
                                            <br />
                                            <?php _e('This will apply to pages that have been listed in the "Posts to be Ignored" and "Categories of posts to be Ignored" fields.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('Pages listed in other ignoring fields will not be affected.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Posts to be Ignored for Suggestions', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_links' id='wpil_ignore_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_links')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Link Whisper will not use posts listed here in the suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Outbound linking suggestions will not be made TO these posts. And Inbound linking suggestions will not be made FROM these posts', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Categories of posts to be Ignored for Suggestions', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_categories' id='wpil_ignore_categories' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_categories')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Link Whisper will not suggest posts from categories listed in this field.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Outbound linking suggestions will not be made TO posts in the listed categories. And Inbound linking suggestions will not be made FROM posts in the listed categories.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('To ignore an entire category, enter the category\'s full url on it\'s own line in the text area', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Posts to be Ignored<br>for Auto-Linking and URL Changer', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_keywords_posts' id='wpil_ignore_keywords_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_keywords_posts')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will not insert auto-links or change URLs on posts entered in this field. To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Posts to be Ignored from Orphaned Posts Report', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_orphaned_posts' id='wpil_ignore_orphaned_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_orphaned_posts', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will not show the listed posts on the Orphaned Posts report. To ignore a post, enter a post\'s full url on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php if(class_exists('ACF')){ ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('ACF Fields to be Ignored', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_acf_fields' id='wpil_ignore_acf_fields' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_acf_fields', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Link Whisper will not process content in the ACF fields listed here. To ignore a field, enter each field\'s name on it\'s own line in the text area', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This will entirely ignore the field, so it won\'t show up in reports, be processed for autolinks, or be scanned during the suggestion process.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Links to Ignore Clicks on', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_click_links' id='wpil_ignore_click_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_click_links', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px;">
                                        <?php 
                                        _e('Link Whisper will not track clicks on links listed here.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a link by URL, enter it\'s URL. The effects apply across the site, so all links with matching URLs will be ignored. Each URL must go on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a link by anchor text, enter it\'s anchor text. The effects apply across the site, so all links with matching anchor texts will be ignored. Each anchor text must go on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Don\'t add "nofollow" to links with these domains.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_nofollow_ignore_domains' id='wpil_nofollow_ignore_domains' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_nofollow_ignore_domains', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px;">
                                        <?php 
                                        _e('Link Whisper will not add the "nofollow" attributes to links pointing to domains listed in this field.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a domain, enter it in this field. The effects apply across the site, so all links with matching domain will not have "nofollow" added.', 'wpil');
                                        echo '<br /><br />';
                                        _e('Ignoring a domain will not remove "nofollow" from links that have had it manually added. This setting is only used when "Set external links to nofollow" is activated', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Links to be ignored from the reports.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_links_to_ignore' id='wpil_links_to_ignore' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_links_to_ignore', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will ignore the links listed in this field and won\'t show them in the Links Report or other linking stat areas.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a link, enter it in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('Wildcard matching can be performed by using the * character on the end of the link that you want to match. So for example, entering "https://example.com/*" would match links like "https://example.com/example-page-1", "https://example.com/category/examples" and "https://example.com/example-pages/example-page-2"', 'wpil');
                                        echo '<br /><br />';
                                        _e('After entering a link, you will need to run a link scan to refresh the stored data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Elements to Ignore by CSS Class.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_elements_by_class' id='wpil_ignore_elements_by_class' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_elements_by_class', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will ignore HTML tags that contain CSS classes listed in this field. It won\'t extract links, or make linking suggestions, from elements that have the listed CSS classes.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a class, enter it in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('Wildcard matching can be performed by using the * character on the end of the class that you want to match. So for example, entering "exam*" would match classes like "example", "examples", and "examination"', 'wpil');
                                        echo '<br /><br />';
                                        _e('After entering a class, you may want to run a link scan to refresh the link data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('HTML Tags to Ignore from Linking.', 'wpil'); ?></td>
                            <td>
                                <select multiple name='wpil_ignore_tags_from_linking[]' class="wpil-setting-multiselect" id='wpil_ignore_tags_from_linking' style="width: 800px;float:left;">
                                <?php
                                    foreach(Wpil_Settings::getPossibleIgnoreLinkingTags() as $possible_ignore_tag){
                                        echo '<option value="' . $possible_ignore_tag . '" ' . (in_array($possible_ignore_tag, $ignored_linking_tags, true) ? 'selected="selected"': '') . '>' . $possible_ignore_tag . '</option>';
                                    } 
                                ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will not create links in any HTML tag selected in this dropdown', 'wpil');
                                        echo '<br /><br />';
                                        _e('This will apply to both the Suggestions and the Autolinking.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row'><?php _e('Shortcodes to Ignore by Name.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_shortcodes_by_name' id='wpil_ignore_shortcodes_by_name' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_shortcodes_by_name', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will ignore any shortcodes listed in this field. It won\'t extract links from the listed shortcodes, or create links in any text content of the shortcode.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a shortcode, enter it\'s name (without square brackets) in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('So for example, to ignore the WordPress [caption][/caption] shortcode, enter "caption" (without quotes) on it\'s own line in the field', 'wpil');
                                        echo '<br /><br />';
                                        _e('After entering a shortcode, you may want to run a link scan to refresh any stored link data based on shortcodes.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Only suggest outbound links to these posts', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_suggest_to_outbound_posts' id='wpil_suggest_to_outbound_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_suggest_to_outbound_posts', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will only suggest outbound links to the listed posts. Please enter each link on it\'s own line in the text area. If you do not want to limit suggestions to specific posts, leave this empty', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Domains Marked as Sponsored', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_sponsored_domains' id='wpil_sponsored_domains' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_sponsored_domains', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php 
                                        _e('Link Whisper will add the rel="sponsored" attribute to all links from domains listed in this field.', 'wpil');
                                        echo '<br /><br />';
                                        _e('Please enter each domain on it\'s own line in the field.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Domains Marked as NoFollow', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_nofollow_domains' id='wpil_nofollow_domains' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(implode("\n", Wpil_Settings::getNofollowDomains())); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php 
                                        _e('Link Whisper will add the rel="nofollow" attribute to all links that point to domains listed in this field.', 'wpil');
                                        echo '<br /><br />';
                                        _e('Please enter each domain on it\'s own line in the field.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Mark Links as External', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_marked_as_external' id='wpil_marked_as_external' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_marked_as_external')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will recognize these links as external on the Report page. Please enter each link on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Mark Domains as Internal', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_domains_marked_as_internal' id='wpil_domains_marked_as_internal' style="width: 800px;float:left;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_domains_marked_as_internal')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Link Whisper will recognize links with these domains as internal on the Report page. Please enter each domain on it\'s own line in the text area as it appears in your browser', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Custom Fields to Process', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_custom_fields_to_process' id='wpil_custom_fields_to_process' style="width: 800px;float:left;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_custom_fields_to_process')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Link Whisper will scan custom-content fields listed here for links and to see if it can create links in the content fields.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Advanced Custom Fields are automatically scanned, so there\'s no need to list them here.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Please enter each field name on it\'s own line in the text area.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('All internal links open in new tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_open_all_internal_new_tab" value="0" />
                                    <input type="checkbox" name="wpil_open_all_internal_new_tab" <?=get_option('wpil_open_all_internal_new_tab')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to filter post content before displaying to make the links to other pages on this site open in new tabs.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will cause existing links, and those not created with Link Whisper to open in new tabs.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This works best with the default WordPress content editors and may not work with some page builders', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('All external links open in new tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_open_all_external_new_tab" value="0" />
                                    <input type="checkbox" name="wpil_open_all_external_new_tab" <?=get_option('wpil_open_all_external_new_tab')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to filter post content before displaying to make the links to external sites open in new tabs.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will cause existing links, and those not created with Link Whisper to open in new tabs.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This works best with the default WordPress content editors and may not work with some page builders', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row js-force-open-new-tabs">
                            <td scope='row'><?php _e('Use JS to force opening in new tabs', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_js_open_new_tabs" value="0" />
                                    <input type="checkbox" name="wpil_js_open_new_tabs" <?=get_option('wpil_js_open_new_tabs')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to use frontend scripting to set links to open in new tabs.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This is mainly intended for cases where the options for setting links to open in new tabs aren\'t working. (This can happen with some page builders.)', 'wpil');
                                            echo '<br /><br />';
                                            _e('Only links in the content areas will open in new tabs, navigation links will not be affected', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will cause the Link Whisper Frontend script to be added to most pages if it isn\'t already there.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('All internal links open in the same tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_open_all_internal_same_tab" value="0" />
                                    <input type="checkbox" name="wpil_open_all_internal_same_tab" <?=get_option('wpil_open_all_internal_same_tab')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to filter post content before displaying to make the links to other pages on this site open in same tab that the user is on.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will cause existing links, and those not created with Link Whisper to open in the current tab.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This works best with the default WordPress content editors and may not work with some page builders', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('All external links open in the same tab', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_open_all_external_same_tab" value="0" />
                                    <input type="checkbox" name="wpil_open_all_external_same_tab" <?=get_option('wpil_open_all_external_same_tab')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to filter post content before displaying to make the links to external sites open in same tab that the user is on.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will cause existing links, and those not created with Link Whisper to open in the current tab.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This works best with the default WordPress content editors and may not work with some page builders', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Relative Links Mode', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_insert_links_as_relative" value="0" />
                                    <input type="checkbox" name="wpil_insert_links_as_relative" <?=!empty(get_option('wpil_insert_links_as_relative', false))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to insert all suggested links as relative links instead of absolute links.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will also allow the URL Changer to change links into relative ones if the "New URL" is relative.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Prevent Two-Way Linking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_prevent_two_way_linking" value="0" />
                                    <input type="checkbox" name="wpil_prevent_two_way_linking" <?=!empty(get_option('wpil_prevent_two_way_linking', false))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will keep Link Whisper from creating two-way linking relationships.', 'wpil');
                                            echo '<br /><br />';
                                            _e('If for example post "A" has a link to post "B", this setting will prevent Link Whisper from suggesting a link from post "B" to post "A".', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Post Types to Process', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php
                                                _e('This setting controls the post types that Link Whisper is active in.', 'wpil');
                                                echo '<br /><br />';
                                                _e('Link Whisper will create links in the selected post types, scan the post types for links, and will operate all of Link Whisper\'s Advanced Functionality in the post types.', 'wpil');
                                                echo '<br /><br />';
                                                _e('After changing the post type selection, please go to the Report page and click the "Run a Link Scan" button to clear the old link data.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <?php foreach ($types_available as $type => $label) : ?>
                                        <input type="checkbox" name="wpil_2_post_types[]" value="<?=$type?>" <?=in_array($type, $types_active)?'checked':''?>><label><?=ucfirst($label)?></label><br>
                                    <?php endforeach; ?>
                                    <input type="hidden" name="wpil_2_show_all_post_types" value="0">
                                    <input type="checkbox" name="wpil_2_show_all_post_types" value="1" <?=!empty(get_option('wpil_2_show_all_post_types', false))?'checked':''?>><label><?php _e('Show Non-Public Post Types', 'wpil'); ?></label><br>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Only Point Suggestions to Specific Post Types', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_limit_suggestions_to_post_types" value="0" />
                                    <input type="checkbox" name="wpil_limit_suggestions_to_post_types" <?=get_option('wpil_limit_suggestions_to_post_types')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            _e('Checking this will tell Link Whisper to only suggest links that point to posts belonging to specific post types.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will only limit the suggestions in the Suggested Links panels. It won\'t affect the Autolinking or URL Changer', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row wpil-suggestion-post-type-limit-setting <?php echo (empty(get_option('wpil_limit_suggestions_to_post_types', false))) ? 'hide-setting': '';?>">
                            <td scope='row'><?php _e('Post Types to Point Suggestions to', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('Link Whisper will only offer suggestions that point to posts in the selected post types.', 'wpil'); ?>
                                            <br /><br />
                                            <?php _e('Only post types that Link Whisper is set to process will be listed here. If you don\'t see a post type listed here, please try selecting it in the "Post Types to Create Links For" setting.', 'wpil'); ?>
                                        </div>
                                    </div>
                                    <?php foreach ($types_available as $type => $label) : ?>
                                        <?php 
                                            $class = 'wpil-suggestion-limit-type-' . $type;
                                            $class .= !in_array($type, $types_active) ? ' hide-setting': ''; 
                                        ?>
                                        <input type="checkbox" name="wpil_suggestion_limited_post_types[]" value="<?=$type?>" <?php echo in_array($type, $suggestion_types_active)?'checked':''?> class="<?php echo $class; ?>"><label class="<?php echo $class; ?>"><?=ucfirst($label)?></label><br class="<?php echo $class; ?>">
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Term Types to Process', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php
                                                _e('This setting controls the term types that Link Whisper is active in.', 'wpil');
                                                echo '<br /><br />';
                                                _e('Link Whisper will create links in the selected term\'s archive pages, scan the term\'s archive pages for links, and will operate all of Link Whisper\'s Advanced Functionality in the term\'s archive pages.', 'wpil');
                                                echo '<br /><br />';
                                                _e('After changing the term type selection, please go to the Report page and click the "Run a Link Scan" button to clear the old link data.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <?php foreach ($term_types_available as $type) : ?>
                                        <input type="checkbox" name="wpil_2_term_types[]" value="<?=$type?>" <?=in_array($type, $term_types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Post Statuses to Create Links For', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('After changing the post status selection, please go to the Report page and click the "Run a Link Scan" button to clear the old link data.', 'wpil'); ?></div>
                                    </div>
                                    <?php foreach ($statuses_available as $status) : ?>
                                        <?php
                                            $status_obj = get_post_status_object($status);
                                            $stat = (!empty($status_obj)) ? $status_obj->label: ucfirst($post_status);
                                        ?>
                                        <input type="checkbox" name="wpil_2_post_statuses[]" value="<?=$status?>" <?=in_array($status, $statuses_active)?'checked':''?>><label><?=$stat?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><span><?php _e('Number of', 'wpil'); ?></span>
                                <select name="wpil_skip_section_type" class="wpil-setting-inline-select">
                                    <option value="sentences"<?php selected($skip_type, 'sentences');?>><?php _e('Sentences', 'wpil'); ?></option>
                                    <option value="paragraphs"<?php selected($skip_type, 'paragraphs');?>><?php _e('Paragraphs', 'wpil'); ?></option>
                                </select>
                                <span><?php _e('to Skip', 'wpil');?></span>
                            </td>
                            <td>
                                <select name="wpil_skip_sentences" style="float:left; max-width:100px">
                                    <?php for($i = 0; $i <= 10; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i==Wpil_Settings::getSkipSentences() ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div><?php _e('Link Whisper will not suggest links for this number of sentences or paragraphs appearing at the beginning of a post.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php _e('Max Outbound Links Per Post', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_max_links_per_post" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_links_per_post ? 'selected' : '' ?>><?php _e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_links_per_post ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        _e('This is the max number of links that you want your posts to have.', 'wpil'); 
                                        echo '<br /><br />';
                                        _e('When a post has reached the link limit, Link Whisper will not suggest adding more links to the post\'s content, and will not add more links to it via the Auto-Linking functionality.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php _e('Max Inbound Links Per Post', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_max_inbound_links_per_post" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_inbound_links_per_post ? 'selected' : '' ?>><?php _e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_inbound_links_per_post ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        _e('This is the max number of inbound links that you want your posts to have.', 'wpil'); 
                                        echo '<br /><br />';
                                        _e('When a post has reached the link limit, Link Whisper will not suggest adding more inbound links pointing to the post.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row'><?php _e('Add Destination Post Title to Links', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_add_destination_title" value="0" />
                                    <input type="checkbox" name="wpil_add_destination_title" <?=!empty(get_option('wpil_add_destination_title', false))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -250px 0 0 30px;">
                                            <?php 
                                            _e('Checking this will tell Link Whisper to insert the title of the post it\'s linking to in the link\'s title attribute.', 'wpil');
                                            echo '<br /><br />';
                                            _e('This will allow users to mouse over links to see what post is being linked to.', 'wpil');
                                            echo '<br /><br />';
                                            _e('The post title is added when links are created and changing this setting will not affect existing links.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php _e('Don\'t Suggest Links to Posts Older Than', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_max_linking_age" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_linking_age ? 'selected' : '' ?>><?php _e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_linking_age ? 'selected' : '' ?>><?php printf( _n( '%s year', '%s years', $i, 'wpil' ), $i ); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        _e('Link Whisper won\'t suggest links from posts that were published before the date limit.', 'wpil');
                                        echo '<br /><br />';
                                        _e('This only applies to the suggestion-based links, the Auto Links have date limiting based on their creation rules.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php _e('Max Number of Suggestions to Display', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_max_suggestion_count" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_suggestion_count ? 'selected' : '' ?>><?php _e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_suggestion_count ? 'selected' : '' ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        _e('This is the maximum number of suggestions that Link Whisper will show you at once in the Suggestion Panels.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php if(class_exists('ACF')){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Disable Linking for Advanced Custom Fields', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_acf" value="0" />
                                <div style="max-width: 80px;">
                                    <input type="checkbox" name="wpil_disable_acf" <?=get_option('wpil_disable_acf', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float: right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin-left: 30px; margin-top: -20px;">
                                            <p><i><?php _e('Checking this will tell Link Whisper to not process any data created by Advanced Custom Fields.', 'wpil'); ?></i></p>
                                            <p><i><?php _e('This will speed up the suggestion making and data saving, but will not update the ACF data.', 'wpil'); ?></i></p>
                                            <p><i><?php _e('If you don\'t see Advanced Custom Fields in your Installed Plugins list, it may be included as a component in a plugin or your theme.', 'wpil'); ?></i></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Disable Broken Link Check Cron Task', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_broken_link_cron_check" value="0" />
                                <div style="max-width: 80px;">
                                    <input type="checkbox" name="wpil_disable_broken_link_cron_check" <?=get_option('wpil_disable_broken_link_cron_check', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float: right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin-left: 30px; margin-top: -20px;">
                                            <p><?php _e('Checking this will disable the cron task that broken link checker runs.', 'wpil'); ?></p>
                                            <p><?php _e('This will disable the scanning for new broken links and the re-checking of suspected broken links.', 'wpil'); ?></p>
                                            <p><?php _e('You can still manually activate the broken link scan by going to the Error Report and clicking "Scan for Broken Links" button.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Count Non-Content Links', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_show_all_links" value="0" />
                                    <input type="checkbox" name="wpil_show_all_links" <?=get_option('wpil_show_all_links')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Turning this on will cause menu links, footer links, sidebar links, comment links, and links from widgets to be displayed in the link reports.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Count Related Post Links', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_count_related_post_links" value="0" />
                                    <input type="checkbox" name="wpil_count_related_post_links" <?=get_option('wpil_count_related_post_links')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('Turning this on will tell Link Whisper to scan and process links in related post areas that are separate from the post content.', 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e('Currently supports links generated by YARPP.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Include Comment Links In Links Report', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_show_comment_links" value="0" />
                                <input type="checkbox" name="wpil_show_comment_links" <?=!empty(get_option('wpil_show_comment_links', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to include links from comments in the Links Report.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('If you have "Count Non-Content Links" active, you won\'t need to activate this because comment links are already being included in the report.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Ignore Links From Latest Post Widgets', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_ignore_latest_posts" value="0" />
                                <input type="checkbox" name="wpil_ignore_latest_posts" <?=!empty(get_option('wpil_ignore_latest_posts', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to ignore links from known Latest Post elements so the links aren\'t used in the Links Report.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Content Formatting Level in Link Scan', 'wpil'); ?></td>
                            <td>
                                <input type="range" name="wpil_content_formatting_level" min="0" max="2" value="<?php echo $formatting_level; ?>">
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 340px;">
                                        <?php _e('The setting controls how much content formatting Link Whisper does with content when searching it for links.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('By default, Link Whisper fully formats the content with WordPress\'s "the_content" filter so it\'s closer to what a visitor would see.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('But for some themes and page builders, this causes issues with links. And the answer is to reduce how much Link Whisper formats the content.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Setting this to "Only Shortcodes" will render the shortcodes in post content, but otherwise leave the content unchanged. Setting it to "No Formatting" will disable the formatting entirely.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                                <div>
                                    <span style="<?php echo ($formatting_level === 0) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-0"><?php _e('No Formatting', 'wpil'); ?></span>
                                    <span style="<?php echo ($formatting_level === 1) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-1"><?php _e('Only Shortcodes', 'wpil'); ?></span>
                                    <span style="<?php echo ($formatting_level === 2) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-2"><?php _e('Full Formatting', 'wpil'); ?></span>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Update "Post Modified" Date when Links Created', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_update_post_edit_date" value="0" />
                                <input type="checkbox" name="wpil_update_post_edit_date" <?=!empty(get_option('wpil_update_post_edit_date', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to update the "Post Modified" date when you insert create links in a post.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('By default, Link Whisper doesn\'t change the "Post Modified" date when creating links.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Force Suggested Links to be HTTPS', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_force_https_links" value="0" />
                                <input type="checkbox" name="wpil_force_https_links" <?=!empty(get_option('wpil_force_https_links', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper that the links it suggests should always be HTTPS.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Link Whisper uses your site\'s "WordPress Address (URL)" setting to determine if the links it suggests should be HTTP or HTTPS.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('But sometimes, a site configuration setting will cause Link Whisper to use HTTP when it should be HTTPS.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This setting will force Link Whisper to always suggest HTTPS links.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Full HTML Suggestions', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_full_html_suggestions" value="0" />
                                    <input type="checkbox" name="wpil_full_html_suggestions" <?=get_option('wpil_full_html_suggestions')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Turning this on will tell Link Whisper to display the raw HTML version of the link suggestions under the suggestion box.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Manually Trigger Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_manually_trigger_suggestions" value="0" />
                                <input type="checkbox" name="wpil_manually_trigger_suggestions" <?=get_option('wpil_manually_trigger_suggestions')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php _e('Checking this option will stop Link Whisper from automatically generating suggestions when you open the post edit or Inbound Suggestion pages. Instead, Link Whisper will wait until you click the "Get Suggestions" button in the suggestion panel.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Disable Outbound Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_outbound_suggestions" value="0" />
                                <input type="checkbox" name="wpil_disable_outbound_suggestions" <?=get_option('wpil_disable_outbound_suggestions')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php _e('Checking this option will prevent Link Whisper from doing suggestion scans inside post edit screens.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Make Suggestion Filtering Persistent', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_make_suggestion_filtering_persistent" value="0" />
                                <input type="checkbox" name="wpil_make_suggestion_filtering_persistent" <?=get_option('wpil_make_suggestion_filtering_persistent')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div>
                                        <?php _e('Checking this option will tell Link Whisper to make the Suggestion Filtering Options persistent between page loads.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('So if, for example, you set the suggestions to be limited to posts in the same categories as the current post. Link Whisper will remember that setting and will use it in future suggestion runs.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Connect to Google Search Console', 'wpil'); ?></td>
                            <td>
                                <?php
                                $authorized = get_option('wpil_gsc_app_authorized', false);
                                $has_custom = !empty(get_option('wpil_gsc_custom_config', false)) ? true : false;
                                $auth_message = (!$has_custom) ? __('Authorize Link Whisper', 'wpil'): __('Authorize Your App', 'wpil');
                                if(empty($authorized)){ ?>
                                    <div class="wpil_gsc_app_inputs">
                                        <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_access_code" class="wpil_gsc_get_authorize" type="text" name="wpil_gsc_access_code"/>
                                        <label for="wpil_gsc_access_code" class="wpil_gsc_get_authorize"><a class="wpil_gsc_enter_app_creds wpil_gsc_button button-primary"><?php _e('Authorize', 'wpil'); ?></a></label>
                                        <a style="margin-top:5px;" class="wpil-get-gsc-access-token button-primary" href="<?php echo Wpil_Settings::getGSCAuthUrl(); ?>"><?php echo $auth_message; ?></a>
                                        <?php /*
                                        <a <?php echo ($has_custom) ? 'style="display:none"': ''; ?> class="wpil_gsc_switch_app wpil_gsc_button enter-custom button-primary button-purple"><?php _e('Connect with Custom App', 'wpil'); ?></a>
                                        <a <?php echo ($has_custom) ? '': 'style="display:none"'; ?> class="wpil_gsc_clear_app_creds button-primary button-purple" data-nonce="<?php echo wp_create_nonce('clear-gsc-creds'); ?>"><?php _e('Clear Custom App Credentials', 'wpil'); ?></a>
                                        */ ?>
                                    </div>
                                    <?php /*
                                    <div style="display:none;" class="wpil_gsc_custom_app_inputs">
                                        <p><i><?php _e('To create a Google app to connect with, please follow this guide. TODO: Write article', 'wpil'); ?></i></p>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_app_name" class="connect-custom-app" type="text" name="wpil_gsc_custom_app_name"/>
                                            <label for="wpil_gsc_custom_app_name"><?php _e('App Name', 'wpil'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_client_id" class="connect-custom-app" type="text" name="wpil_gsc_custom_client_id"/>
                                            <label for="wpil_gsc_custom_client_id"><?php _e('Client Id', 'wpil'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_client_secret" class="connect-custom-app" type="text" name="wpil_gsc_custom_client_secret"/>
                                            <label for="wpil_gsc_custom_client_secret"><?php _e('Client Secret', 'wpil'); ?></label>
                                        </div>
                                        <a style="margin: 0 0 10px 0;" class="wpil_gsc_enter_app_creds wpil_gsc_button button-primary"><?php _e('Save App Credentials', 'wpil'); ?></a>
                                        <br />
                                        <a class="wpil_gsc_switch_app wpil_gsc_button enter-standard button-primary button-purple"><?php _e('Connect with Link Whisper App', 'wpil'); ?></a>
                                    </div>
                                    */ ?>
                                <?php }else{ ?>
                                    <a class="wpil-gsc-deactivate-app button-primary"  data-nonce="<?php echo wp_create_nonce('disconnect-gsc'); ?>"><?php _e('Deactivate', 'wpil'); ?></a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php if(!empty($authenticated) && empty($gsc_profile) && $profile_not_found){ /* TODO: add a debug setting to force the display of this field */?>
                            <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Select Site Profile From Search Console List', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_manually_select_gsc_profile" style="float:left; max-width:400px">
                                    <option value="0"><?php _e('Select Profile', 'wpil'); ?>
                                <?php foreach(Wpil_SearchConsole::get_profiles() as $key => $profile){ ?>
                                    <option value="<?=esc_attr($key)?>" <?=(!empty($gsc_profile) && $key === $gsc_profile) ? 'selected="selected"': '';?>><?=esc_html($profile)?></option>
                                <?php } ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div><?php echo sprintf(__('Please select the correct listing for this site. The listing that matches your site\'s current URL or looks like "sc-domain:%s" is usually the correct one.', 'wpil'), wp_parse_url(get_home_url(), PHP_URL_HOST)); ?></div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php if($authorized){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Disable Automatic Search Console Updates', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_search_update" value="0" />
                                <input type="checkbox" name="wpil_disable_search_update" <?=get_option('wpil_disable_search_update', false)==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php _e('Link Whisper automatically scans for GSC updates via WordPress Cron. Turning this off will stop Link Whisper from performing the scan.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Auto Select Top GSC Keywords', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_autotag_gsc_keywords" value="0" />
                                    <input type="checkbox" name="wpil_autotag_gsc_keywords" <?=get_option('wpil_autotag_gsc_keywords', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('Turning this on will tell Link Whisper to automatically select the top GSC Keywords based on either impressions or clicks.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('After changing this setting, please refresh the Target Keywords. The auto-selection process only activates during the Target Keyword Scan to save system resources.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo (empty(get_option('wpil_autotag_gsc_keywords', false))) ? 'hide-setting': '';?>">
                            <td scope='row'><?php _e('Auto Select GSC Keywords Basis', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:170px;">
                                    <?php $auto_select_basis = get_option('wpil_autotag_gsc_keyword_basis', 'impressions'); ?>
                                    <select name="wpil_autotag_gsc_keyword_basis" style="float:left; max-width:400px">
                                        <option value="impressions" <?php echo (('impressions' === $auto_select_basis) ? 'selected="selected"': ''); ?>><?php _e('Impressions', 'wpil'); ?>
                                        <option value="clicks" <?php echo (('clicks' === $auto_select_basis) ? 'selected="selected"': ''); ?>><?php _e('Clicks', 'wpil'); ?>
                                    </select>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('How should Link Whisper decide which GSC keywords to auto select?', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('Should it pick the GSC keywords that have the most impressions, or the most clicks?', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo (empty(get_option('wpil_autotag_gsc_keywords', false))) ? 'hide-setting': '';?>">
                            <td scope='row'><?php _e('Number of GSC Keywords to Auto Select', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:170px;">
                                    <?php $auto_select_count = (int)get_option('wpil_autotag_gsc_keyword_count', 0); ?>
                                    <select name="wpil_autotag_gsc_keyword_count" style="float:left; max-width:400px">
                                        <option value="0" <?php echo ((0 === $auto_select_count) ? 'selected="selected"': ''); ?>><?php _e('Don\'t Auto Select', 'wpil'); ?>
                                    <?php for($i = 1; $i < 20; $i++){
                                        echo '<option value="' . $i . '" ' . (($i === $auto_select_count) ? 'selected="selected"': '') . '>' . $i . '</option>';
                                        } ?>
                                    </select>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('How many GSC Keywords should Link Whisper automatically set to active?', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php if(defined('WPSEO_VERSION')){?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Only Create Outbound Links to Yoast Cornerstone Content', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_link_to_yoast_cornerstone" value="0" />
                                    <input type="checkbox" name="wpil_link_to_yoast_cornerstone" <?=get_option('wpil_link_to_yoast_cornerstone', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php _e('Turning this on will tell Link Whisper to restrict the outbound link suggestions to posts marked as Yoast Cornerstone content.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Only make suggestions based on Target Keywords', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_only_match_target_keywords" value="0" />
                                <input type="checkbox" name="wpil_only_match_target_keywords" <?=!empty(get_option('wpil_only_match_target_keywords', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Checking this will tell Link Whisper to only show suggestions that have matches based on the current post\'s Target Keywords.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Target Keyword Sources', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <?php
                                        $target_keyword_sources = array_reverse(Wpil_TargetKeyword::get_available_keyword_sources());
                                        $active_keyword_sources = Wpil_Settings::getSelectedKeywordSources();
                                        $source_display_names   = Wpil_TargetKeyword::get_keyword_name_list();
                                    ?>
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div>
                                            <?php _e('The toggle in this section allow you to select what Target Keyword sources it will extract data from.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('The Custom Keywords are always enabled because they are manually entered.', 'wpil'); ?>
                                        </div>
                                    </div>
                                    <?php foreach ($target_keyword_sources as $source) : ?>
                                            <input type="checkbox" name="wpil_selected_target_keyword_sources[]" value="<?=$source?>" <?=in_array($source, $active_keyword_sources)?'checked':''?> <?php echo ($source === 'custom') ? 'disabled="disabled"': ''; ?>><label><?=$source_display_names[$source]?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Add rel="noreferrer" to Created Links', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_add_noreferrer" value="0" />
                                <input type="checkbox" name="wpil_add_noreferrer" <?=!empty(get_option('wpil_add_noreferrer', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('Checking this will tell Link Whisper to add the noreferrer attribute to the links it creates. Adding this attribute will cause all clicks on inserted links to be counted as direct traffic on analytics systems.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Set external links to nofollow', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_add_nofollow" value="0" />
                                <input type="checkbox" name="wpil_add_nofollow" <?=!empty(get_option('wpil_add_nofollow', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to add the "nofollow" attribute to all external links it creates and the external links created with the WordPress editors.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('However, this does not apply to links to sites you\'ve interlinked via Link Whisper\'s "Interlink External Sites" settings.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Links to those sites won\'t have "nofollow" added.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Point Suggestions From Staging to Live Site', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_filter_staging_url" value="0" />
                                <input type="checkbox" name="wpil_filter_staging_url" <?=$filter_staging_url?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 260px;">
                                        <?php _e('Checking this will tell Link Whisper that it\'s active on a staging site and that it should change the "home URL" portion of suggested links so they match the home URL of the live site.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This only applies to suggested links when they\'re created, existing links won\'t be changed. Also, this setting won\'t affect Autolinks or Custom Links.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Checking this will also set the link scanner to calculate inbound & outbound link stats based on the live site\'s domain, instead of the staging site\'s domain. So the stats on the staging site may change.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo ($filter_staging_url) ? '': 'hide-setting';?>">
                            <td scope='row'><?php _e('Live Site Home URL', 'wpil'); ?></td>
                            <td>
                                <input type="text" name="wpil_live_site_url" placeholder="<?=esc_attr('https://example.com/')?>" value="<?=esc_attr(get_option('wpil_live_site_url', ''))?>" style="width: 600px" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 260px;">
                                        <?php _e('This is the home URL for the live site.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Link Whisper will replace the "home URL" portion of suggested links on the staging site with this home URL. That way, links created on the staging site will be pointing to pages on the live site\'s domain.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php echo sprintf(__('If this is the live site, then the URL should be: %s', 'wpil'), esc_attr(get_home_url())); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo ($filter_staging_url) ? '': 'hide-setting';?>">
                            <td scope='row'><?php _e('Staging Site Home URL', 'wpil'); ?></td>
                            <td>
                                <input type="text" name="wpil_staging_site_url" placeholder="<?=esc_attr('https://staging.example.com/')?>" value="<?=esc_attr(get_option('wpil_staging_site_url', ''))?>" style="width: 600px" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('This is the home URL for the staging site.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Link Whisper will replace this "home URL" portion in suggested links on the staging site with the home URL for the live site.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php echo sprintf(__('If this is the staging site, then the URL should be: %s', 'wpil'), esc_attr(get_home_url())); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Ignore Image URLs', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_ignore_image_urls" value="0" />
                                <input type="checkbox" name="wpil_ignore_image_urls" <?=!empty(get_option('wpil_ignore_image_urls', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to ignore image URLs in the Links Report.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This will include image URLs inside anchor href attributes.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Include Image src URLs in Links Report', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_include_image_src" value="0" />
                                <input type="checkbox" name="wpil_include_image_src" <?=!empty(get_option('wpil_include_image_src', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to include image src URLs in the Links Report.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('The image URLs will be show up in the Outbound Internal link counts for the posts that they\'re in. They will not contribute to the Inbound Internal link counts for any posts.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        (<?php _e('This is the URL that\'s used in &lt;img&gt; tags. By default, Link Whisper scans image-related URls in &lt;a&gt; tags.', 'wpil'); ?>)</div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Remove Inner HTML When Deleting Links', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_delete_link_inner_html" value="0" />
                                <input type="checkbox" name="wpil_delete_link_inner_html" <?=!empty(get_option('wpil_delete_link_inner_html', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 260px; display: none;">
                                        <?php _e('Checking this will tell Link Whisper to remove any HTML tags from link anchor text when links are deleted.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This is helpful when links use bold or italic tags for styling, and you don\'t want to leave these in the page when deleting the links.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        (<?php _e('One thing to be careful of is if the anchor has the opening tag but not the closing tag, deleting the link will leave behind the closing tag and this could mess up the page.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('EX: Deleting &lt;a href="example.com"&gt;&lt;strong&gt;testing&lt;/a&gt;&lt;/strong&gt; will leave behind the "&lt;/strong&gt;" tag, and this may change what content is bolded on the page', 'wpil'); ?>)</div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Use SEO Titles in Reports', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_use_seo_titles" value="0" />
                                <input type="checkbox" name="wpil_use_seo_titles" <?=!empty(get_option('wpil_use_seo_titles', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will tell Link Whisper to use the post or term\'s SEO title instead of the post title in the reports.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This will only change how the post is titled in the reports, it won\'t change the suggestions generated for the post.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row wpil-partial-title-setting">
                            <td scope='row'><?php _e('Only Use Part of a Post Title When Making Suggestions', 'wpil'); ?></td>
                            <td>
                                <?php $partial_title = get_option('wpil_get_partial_titles', false); ?>
                                <select name="wpil_get_partial_titles" style="float:left; max-width:400px">
                                    <option value="0" <?php echo ((empty($partial_title)) ? 'selected="selected"': ''); ?>><?php _e('Use the Full Title', 'wpil'); ?>
                                    <option value="1" <?php echo (($partial_title === '1') ? 'selected="selected"': ''); ?>><?php _e('Use First Few Words', 'wpil'); ?>
                                    <option value="2" <?php echo (($partial_title === '2') ? 'selected="selected"': ''); ?>><?php _e('Use Last Few Words', 'wpil'); ?>
                                    <option value="3" <?php echo (($partial_title === '3') ? 'selected="selected"': ''); ?>><?php _e('Use Words Before Delimiter', 'wpil'); ?>
                                    <option value="4" <?php echo (($partial_title === '4') ? 'selected="selected"': ''); ?>><?php _e('Use Words After Delimiter', 'wpil'); ?>
                                </select>
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('This option will tell Link Whisper to only use a section of the words in a post title when making suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('This can improve suggestions if you have non post-specific words in the title.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('You can select either a number of words from the start or end of the post title for use in suggestions, or you can choose to split the title on a delimiting character and use the front or back end of the title for suggestions.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo ($partial_title === '1' || $partial_title === '2') ? '': 'hide-setting';?>">
                            <td scope='row'><?php _e('Number of Title Words to Use', 'wpil'); ?></td>
                            <td>
                                <?php $word_count = get_option('wpil_partial_title_word_count', 0); ?>
                                <select name="wpil_partial_title_word_count" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$word_count ? 'selected' : '' ?>><?php _e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 2; $i <= 25; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$word_count ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Link Whisper will use the number of words you set here when making suggestions and will ignore the rest in the title.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('If you\'ve selected "Use First Few Words", Link Whisper will use the words from the start of the title.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('If you\'ve selected "Use Last Few Words", Link Whisper will use the words from the end of the title.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo ($partial_title === '3' || $partial_title === '4') ? '': 'hide-setting';?>">
                            <td scope='row'><?php _e('Delimiter Character to Split the Title on', 'wpil'); ?></td>
                            <td>
                                <?php $split_char = get_option('wpil_partial_title_split_char', '')?>
                                <input type="text" name="wpil_partial_title_split_char" style="width:400px;" <?php echo ($split_char !== '') ? 'value="' . esc_attr($split_char) . '"': 'placeholder="' . __('Enter The Character To Split Titles on.', 'wpil') . '"';?> />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('The delimiting character is a consistent character that you use in post titles to separate the post\'s name/title from a static tagline that\'s applied to all posts.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('For example, if you have a post called "9 Great Things you Need to Have", and your site has a tagline of "Greatest Things Online", you could combine them into a full title of: "9 Great Things you Need to Have - Greatest Things Online"', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('In this case, the \'-\' character is the delimiter between the post\'s "name" and the site\'s static tagline.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                        <?php if(current_user_can('activate_plugins')){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Interlink External Sites', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_link_external_sites" value="0" />
                                <input type="checkbox" name="wpil_link_external_sites" <?=$site_linking_enabled==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this will allow you to make links to external sites that you own via the outbound suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('All sites must have Link Whisper installed and be in the same licensing plan.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <a href="https://linkwhisper.com/knowledge-base/how-to-make-link-suggestions-between-sites/" target="_blank"><?php _e('Read more...', 'wpil'); ?></a>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php $access_code = get_option('wpil_link_external_sites_access_code', false); ?>
                        <tr class="wpil-site-linking-setting-row wpil-advanced-settings wpil-setting-row" <?php echo ($site_linking_enabled === '1') ? '': 'style="display:none;"'; ?>>
                            <td scope='row'><?php _e('Site Interlinking Access Code', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_link_external_sites_access_code" value="0" />
                                <input type="text" name="wpil_link_external_sites_access_code" style="width:400px;" <?php echo (!empty($access_code)) ? 'value="' . $access_code . '"': 'placeholder="' . __('Enter Access Code', 'wpil') . '"';?> />
                                <a href="#" class="wpil-generate-id-code button-primary" data-wpil-id-code="1" data-wpil-base-id-string="<?php echo Wpil_SiteConnector::generate_random_id_string(); ?>"><?php _e('Generate Code', 'wpil'); ?></a>
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php _e('This code is used to secure the connection between all linked sites. Use the same code on all sites you want to link', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <?php if(!empty($access_code)){ ?>
                        <tr class="wpil-linked-sites-row wpil-site-linking-setting-row wpil-advanced-settings wpil-setting-row" <?php echo ($site_linking_enabled === '1') ? '': 'style="display:none;"'; ?>>
                            <td scope='row'><?php _e('Home Urls of Linked Sites', 'wpil'); ?></td>
                            <td class="wpil-linked-sites-cell">
                                <?php
                                    $unregister_text = __('Unregister Site', 'wpil');
                                    $remove_text    = __('Remove Site', 'wpil');
                                    $import_text   = __('Import Post Data', 'wpil');
                                    $refresh_text = __('Refresh Post Data', 'wpil');
                                    $import_loadingbar = '<div class="progress_panel loader site-import-loader" style="display: none;"><div class="progress_count" style="width:100%">' . __('Importing Post Data', 'wpil') . '</div></div>';
                                    $link_site_text = __('Attempt Site Linking', 'wpil');
                                    $disable_external_linking = __('Disable Suggestions', 'wpil');
                                    $enable_external_linking = __('Enable Suggestions', 'wpil');
                                    $sites = Wpil_SiteConnector::get_registered_sites();
                                    $linked_sites = Wpil_SiteConnector::get_linked_sites();
                                    $disabled_suggestion_sites = get_option('wpil_disable_external_site_suggestions', array());

                                    foreach($sites as $site){
                                        // if the site has been linked
                                        if(in_array($site, $linked_sites, true)){
                                            $button_text = (Wpil_SiteConnector::check_for_stored_data($site)) ? $refresh_text: $import_text;
                                            $suggestions_disabled = isset($disabled_suggestion_sites[$site]);
                                            echo '<div class="wpil-linked-site-input">
                                                    <input type="text" name="wpil_linked_site_url[]" style="width:600px" value="' . $site . '" />
                                                    <label>
                                                        <a href="#" class="wpil-refresh-post-data button-primary site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'download-site-data-nonce') . '">' . $button_text . '</a>
                                                        <a href="#" class="wpil-external-site-suggestions-toggle button-primary site-linking-button" data-suggestions-enabled="' . ($suggestions_disabled ? 0: 1) . '" data-site-url="' . esc_url($site) . '" data-enable-text="' . $enable_external_linking . '" data-disable-text="' . $disable_external_linking . '" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'toggle-external-site-suggestions-nonce') . '">' . ($suggestions_disabled ? $enable_external_linking: $disable_external_linking) . '</a>
                                                        <a href="#" class="wpil-unlink-site-button button-primary button-purple site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unlink-site-nonce') . '">' . $remove_text . '</a>
                                                        ' . $import_loadingbar . '
                                                    </label>
                                                </div>';
                                        }else{
                                            // if the site hasn't been linked, but only registered
                                            echo '<div class="wpil-linked-site-input">
                                                    <input type="text" name="wpil_linked_site_url[]" style="width:600px" value="' . $site . '" />
                                                    <label>
                                                        <a href="#" class="wpil-link-site-button button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'link-site-nonce') . '">' . $link_site_text . '</a>
                                                        <a href="#" class="wpil-unregister-site-button button-primary button-purple site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unregister-site-nonce') . '">' . $unregister_text . '</a>
                                                    </label>
                                                </div>';
                                        }
                                    }
                                    echo '<div class="wpil-linked-site-add-button-container">
                                            <a href="#" class="button-primary wpil-linked-site-add-button">' . __('Add Site Row', 'wpil') . '</a>
                                        </div>';

                                    echo '<div class="wpil-linked-site-input template-input hidden">
                                            <input type="text" name="wpil_linked_site_url[]" style="width:600px;" />
                                            <label>
                                                <a href="#" class="wpil-register-site-button button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'register-site-nonce') . '">' . __('Register Site', 'wpil') . '</a>
                                            </label>
                                        </div>';
                                ?>
                                <input type="hidden" id="wpil-site-linking-initial-loading-message" value="<?php echo esc_attr__('Importing Post Data', 'wpil'); ?>">
                            </td>
                        </tr>
                        <tr class="wpil-linked-sites-row wpil-site-linking-setting-row wpil-advanced-settings wpil-setting-row" <?php echo ($site_linking_enabled === '1') ? '': 'style="display:none;"'; ?>>
                            <td scope='row'><?php _e('Disable Automatic Interlinked Site Updating', 'wpil'); ?></td>
                            <td class="wpil-linked-sites-cell">
                                <input type="hidden" name="wpil_disable_external_site_updating" value="0" />
                                <input type="checkbox" name="wpil_disable_external_site_updating" <?=!empty(get_option('wpil_disable_external_site_updating', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin-top: -195px;">
                                        <?php _e('Checking this will disable the notifications that Link Whisper sends to the interlinked sites when you update or delete a post.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('These notifications keep the linked sites up to date about content changes on this site, but they also slow down post updating/deleting.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php _e('Disabling the notifications will speed up post updating and deleting, but over time the data on linked sites will become out of date and need to be manually updated.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php }else{ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Interlink External Sites', 'wpil'); ?></td>
                            <td>
                                <p><i><?php _e('Only admins can access the site linking settings.', 'wpil'); ?></i></p>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Delete Click Data Older Than', 'wpil'); ?></td>
                            <td>
                                <div style="display: flex;">
                                    <select name="wpil_delete_old_click_data" style="float:left;">
                                        <?php $day_count = get_option('wpil_delete_old_click_data', '0'); ?>
                                        <option value="0" <?php selected('0', $day_count) ?>><?php _e('Never Delete'); ?></option>
                                        <option value="1" <?php selected('1', $day_count) ?>><?php _e('1 Day'); ?></option>
                                        <option value="3" <?php selected('3', $day_count) ?>><?php _e('3 Days'); ?></option>
                                        <option value="7" <?php selected('7', $day_count) ?>><?php _e('7 Days'); ?></option>
                                        <option value="14" <?php selected('14', $day_count) ?>><?php _e('14 Days'); ?></option>
                                        <option value="30" <?php selected('30', $day_count) ?>><?php _e('30 Days'); ?></option>
                                        <option value="180" <?php selected('180', $day_count) ?>><?php _e('180 Days'); ?></option>
                                        <option value="365" <?php selected('365', $day_count) ?>><?php _e('1 Year'); ?></option>
                                    </select>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -50px 0 0 30px;">
                                            <?php _e("Link Whisper will delete tracked clicks that are older than this setting.", 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e("By default, Link Whisper doesn't delete tracked click data.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Disable Click Tracking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_disable_click_tracking" value="0" />
                                    <input type="checkbox" name="wpil_disable_click_tracking" <?=get_option('wpil_disable_click_tracking', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -180px 0 0 30px;">
                                            <?php _e("Activating this will disable the Click Tracking and will remove the Click Report from the Dashboard", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("The Click Tracking uses the Link Whisper Frontend script to track visitor clicks. So disabling this and having the \"Use JS to force opening in new tabs\" off will remove the script.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Don\'t Collect User-Identifying Information with Click Tracking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_disable_click_tracking_info_gathering" value="0" />
                                    <input type="checkbox" name="wpil_disable_click_tracking_info_gathering" <?=$disable_ip_tracking==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -230px 0 0 30px;">
                                            <?php _e("Activating this will set the Click Tracking to not collect information that could be used to identify a user", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("By default when a user clicks a link, Link Whisper collects the IP address of the visitor.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("If the visitor has an account on the site, then Link Whisper collects their user id too.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("With collection disabled, this data will not be saved.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Track Link Clicks on all Elements', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_track_all_element_clicks" value="0" />
                                    <input type="checkbox" name="wpil_track_all_element_clicks" <?=get_option('wpil_track_all_element_clicks', 0)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -280px 0 0 30px; width: 270px;">
                                            <?php _e("Activating this will set the Click Tracking to track link clicks on all parts of a page.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("By default, only clicks in the post content areas are tracked so you can easily see how your in-content links are performing.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("But when this setting is active, Link Whisper will track clicks in your page header, footer, sidebars & menus as well as widget areas.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("To help identify where in the page the link was clicked, the Detailed Click Report pages will show a 'location' stat for each click.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php if(Wpil_ClickTracker::check_for_stored_visitor_data()){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo (empty($disable_ip_tracking)) ? 'hide-setting': '';?>">
                            <td scope='row'><?php _e('Delete all stored visitor data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_delete_stored_visitor_data" value="0" />
                                    <input type="checkbox" name="wpil_delete_stored_visitor_data" value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -80px 0 0 30px;">
                                            <?php _e("Activating this will tell Link Whisper to delete all visitor data that it has stored.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("Currently, the only visitor data stored is used in the Click Report.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php _e('Delete all Link Whisper Data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_delete_all_data" value="0" />
                                    <input type="checkbox" class="danger-zone" name="wpil_delete_all_data" <?=get_option('wpil_delete_all_data', false)==1?'checked':''?> value="1" />
                                    <input type="hidden" class="wpil-delete-all-data-message" value="<?php echo sprintf(__('Activating this will tell Link Whisper to delete ALL link Whisper related data when the plugin is deleted. %s This will remove all settings and stored data. Links inserted into content by Link Whisper will still exist. %s Undoing actions like URL changes will be impossible since the records of what the url used to be will be deleted as well. %s Please only activate this option if you\'re sure you want to delete all data.', 'wpil'), '&lt;br&gt;&lt;br&gt;', '&lt;br&gt;&lt;br&gt;', '&lt;br&gt;&lt;br&gt;'); ?>">
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -100px 0 0 30px;">
                                            <?php _e("Activating this will tell Link Whisper to delete ALL link Whisper related data when the plugin is deleted.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e("Please only activate this option if you're sure you want to delete ALL link Whisper data.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'>
                                <span class="settings-carrot">
                                    <?php _e('Debug Settings', 'wpil'); ?>
                                </span>
                            </td>
                            <td class="setting-control-container">
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_2_debug_mode" value="0" />
                                    <input type='checkbox' name="wpil_2_debug_mode" <?=get_option('wpil_2_debug_mode')==1?'checked':''?> value="1" />
                                    <label><?php _e('Enable Debug Mode?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php _e('If you\'re having errors, or it seems that data is missing, activating Debug Mode may be useful in diagnosing the problem.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('Enabling Debug Mode will cause your site to display any errors or code problems it\'s expiriencing instead of hiding them from view.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('These error notices may be visible to your site\'s visitors, so it\'s recommended to only use this for limited periods of time.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('(If you are already debugging with WP_DEBUG, then there\'s no need to activate this.)', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_option_update_reporting_data_on_save" value="0" />
                                    <input type='checkbox' name="wpil_option_update_reporting_data_on_save" <?=get_option('wpil_option_update_reporting_data_on_save')==1?'checked':''?> value="1" />
                                    <label><?php _e('Run a check for un-indexed posts on each post save?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php _e('Checking this will tell Link Whisper to look for any posts that haven\'t been indexed for the link reports every time a post is saved.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('In most cases this isn\'t necessary, but if you\'re finding that some of your posts aren\'t displaying in the reports screens, this may fix it.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('One word of caution: If you have many un-indexed posts on the site, this may cause memory / timeout errors.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_include_post_meta_in_support_export" value="0" />
                                    <input type='checkbox' name="wpil_include_post_meta_in_support_export" <?=get_option('wpil_include_post_meta_in_support_export')==1?'checked':''?> value="1" />
                                    <label><?php _e('Include post meta in support data export?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php _e('Checking this will tell Link Whisper to include additional post data in the data for support export.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This isn\'t needed for most support cases. It\'s most commonly used for troubleshooting issues with page builders', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_clear_error_checker_process" value="0" />
                                    <input type='checkbox' name="wpil_clear_error_checker_process" <?=get_option('wpil_clear_error_checker_process')==1?'checked':''?> value="1" />
                                    <label><?php _e('Cancel active Broken Link scans?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php _e('Checking this will tell Link Whisper to cancel any active Broken Link scans and allow you to access the Error Report table.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This can be helpful when the Broken Link scan gets stuck, but it may not solve the underlying issue.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('Please close any tabs that have an active Broken Link scan running before activating this option.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_force_database_update" value="0" />
                                    <input type='checkbox' name="wpil_force_database_update" value="1" />
                                    <label><?php echo sprintf(__('Re-run the database table %s routine?', 'wpil'), '<strong>update</strong>'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -230px 0 0 30px;">
                                            <p><?php _e('Checking this will tell Link Whisper re-run the database table update process.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This process is supposed to automatically run when the plugin is updated, but sometimes it gets interrupted.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This can help when you have errors saying that certain columns do not exist in database tables.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_force_create_database_tables" value="0" />
                                    <input type='checkbox' name="wpil_force_create_database_tables" value="1" />
                                    <label><?php echo sprintf(__('Re-run the database table %s routine?', 'wpil'), '<strong>creation</strong>'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <p><?php _e('Checking this will tell Link Whisper re-run the database table creation process.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This process is supposed to automatically run when the plugin is updated, but sometimes it gets interrupted.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php _e('This can help when you have errors saying that certain database tables do not exist.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-licensing wpil-setting-row">
                            <td>
                                <div class="wrap wpil_licensing_wrap postbox">
                                    <div class="wpil_licensing_container">
                                        <div class="wpil_licensing" style="">
                                            <h2 class="wpil_licensing_header hndle ui-sortable-handle">
                                                <span>Link Whisper Licensing</span>
                                            </h2>
                                            <div class="wpil_licensing_content inside">
                                                <?php settings_fields('wpil_license'); ?>
                                                <input type="hidden" id="wpil_license_action_input" name="hidden_action" value="activate_license" disabled="disabled">
                                                <table class="form-table">
                                                    <tbody>
                                                        <tr>
                                                            <td class="wpil_license_table_title"><?php _e('License Key:', 'wpil');?></td>
                                                            <td><input id="wpil_license_key" name="wpil_license_key" type="text" class="regular-text" value="" /></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="wpil_license_table_title"><?php _e('License Status:', 'wpil');?></td>
                                                            <td><span class="wpil_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_titles[$licensing_state]); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="wpil_license_table_title"><?php _e('License Message:', 'wpil');?></td>
                                                            <td><span class="wpil_licensing_status_text <?php echo esc_attr($licensing_state); ?>"><?php echo esc_attr($status_messages[$licensing_state]); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="wpil_license_table_title"><?php _e('Installed Version:', 'wpil');?></td>
                                                            <td><span class="wpil_licensing_status_text"><?php echo esc_html(Wpil_License::get_subscription_version_message()); ?></span></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <?php wp_nonce_field( 'wpil_activate_license_nonce', 'wpil_activate_license_nonce' ); ?>
                                                <div class="wpil_licensing_version_number"><?php echo Wpil_Base::showVersion(); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <p class='submit wpil-setting-button save-settings'>
                        <input type='submit' name='btnsave' id='btnsave' value='Save Settings' class='button-primary' />
                    </p>
                    <p class='submit wpil-setting-button activate-license' style="display:none">
                        <button type="submit" class="button button-primary wpil_licensing_activation_button"><?php _e('Activate License', 'wpil'); ?></button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>