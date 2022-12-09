<?php

/**
 * Export controller
 */
class Wpil_Export
{

    private static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function getInstance()
    {
        if (null === self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Export data
     */
    function export($post)
    {
        // exit if this isn't the admin
        if(!is_admin()){
            return;
        }

        $data = self::getExportData($post);
        $data = json_encode($data, JSON_PRETTY_PRINT);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        //create filename
        if ($post->type == 'term') {
            $term = get_term($post->id);
            $filename = $post->id . '-' . $host . '-' . $term->slug . '.json';
        } else {
            $post_slug = get_post_field('post_name', $post->id);
            $filename = $post->id . '-' . $host . '-' . $post_slug . '.json';
        }

        //download export file
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-type: application/json');
        echo $data;
        exit;
    }

    /**
     * Get post data, links and settings for export
     *
     * @param $post_id
     * @return array
     */
    public static function getExportData($post)
    {
        // detach any hooks known to cause problems in the loading
        Wpil_Base::remove_problem_hooks(true);

        $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
        $beaver_content = get_post_meta($post->id, '_fl_builder_data', true);
        $elementor_content = get_post_meta($post->id, '_elementor_data', true);
        $enfold_content = get_post_meta($post->id, '_aviaLayoutBuilderCleanData', true);
        $old_oxygen_content = get_post_meta($post->id, 'ct_builder_shortcodes', true);
        $new_oxygen_content = get_post_meta($post->id, 'ct_builder_json', true);

        set_transient('wpil_transients_enabled', 'true', 600);
        $transient_enabled = (!empty(get_transient('wpil_transients_enabled'))) ? true: false;

        //export settings
        $settings = [];
        foreach (Wpil_Settings::$keys as $key) {
            $settings[$key] = get_option($key, null);
        }
        $settings['ignore_words'] = get_option('wpil_2_ignore_words', null);

        $res = [
            'v' => strip_tags(Wpil_Base::showVersion()),
            'created' => date('c'),
            'post_id' => $post->id,
            'type' => $post->type,
            'wp_post_type' => $post->getRealType(),
            'post_terms' => $post->getPostTerms(),
            'post_links_last_update' => ($post->type === 'post') ? get_post_meta($post->id, 'wpil_sync_report2_time', true): get_term_meta($post->id, 'wpil_sync_report2_time', true),
            'has_run_scan' => get_option('wpil_has_run_initial_scan'),
            'last_scan_run' => get_option('wpil_scan_last_run_time', 'Not Yet Activated'),
            'keyword_reset_last_run' => get_option('wpil_keyword_reset_last_run_time', 'Not Yet Activated'),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'processable_post_count' => Wpil_Report::get_total_post_count(),
            'total_database_posts' => self::get_database_post_count(),
            'url' => $post->getLinks()->view,
            'title' => $post->getTitle(),
            'content' => $post->getContent(false),
            'processed_content' => Wpil_Report::process_content($post->getContent(false), $post),
            'shortcode_processed' => do_shortcode($post->getContent(false)),
            'clean_content' => $post->getCleanContent(),
            'thrive_content' => $thrive_content,
            'beaver_content' => $beaver_content,
            'elementor_content' => $elementor_content,
            'enfold_content' => $enfold_content,
            'oxygen_shortcodes' => $old_oxygen_content,
            'oxygen_json' => $new_oxygen_content,
            'wp_theme' => print_r(wp_get_theme(), true),
            'target_keywords' => Wpil_TargetKeyword::get_active_keywords_by_post_ids($post->id, $post->type),
            'target_keywords_sources' => Wpil_TargetKeyword::get_available_keyword_sources(),
            'transients_enabled' => $transient_enabled,
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => phpversion(),
            'mb_string_active' => extension_loaded('mbstring'),
            'curl_active' => function_exists('curl_init'),
            'curl_version' => (function_exists('curl_version')) ? curl_version(): false,
            'relevent_wp_constants' => Wpil_Settings::get_wp_constants(),
            'using_custom_htaccess' => Wpil_Toolbox::is_using_custom_htaccess(),
            'license_type' => Wpil_License::getItemId(),
            'registered_sites' => Wpil_SiteConnector::get_registered_sites(),
            'linked_sites' => Wpil_SiteConnector::get_linked_sites(),
            'ACF_active' => class_exists('ACF'),
            'gsc_constants_defined' => !empty(Wpil_SearchConsole::get_key()) && !empty(Wpil_SearchConsole::get_salt()),
            'gsc_authed' => Wpil_Settings::HasGSCCredentials(),
            'table_statuses' => self::get_table_data(),
            'active_plugins' => get_option('active_plugins', array()),
            'settings' => $settings
        ];

        // if we're including meta in the export or ACF is active
        if(!empty(get_option('wpil_include_post_meta_in_support_export')) || class_exists('ACF')){
            $res['post_meta'] = ($post->type === 'post') ? get_post_meta($post->id, '', true) : get_term_meta($post->id, '', true);
        }

        // add reporting data to export
        $keys = [
            WPIL_LINKS_OUTBOUND_INTERNAL_COUNT,
            WPIL_LINKS_INBOUND_INTERNAL_COUNT,
            WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT,
        ];

        $report = [];
        foreach($keys as $key) {
            if ($post->type == 'term') {
                $report[$key] = get_term_meta($post->id, $key, true);
                $report[$key.'_data'] = get_term_meta($post->id, $key.'_data', true);
            } else {
                $report[$key] = get_post_meta($post->id, $key, true);
                $report[$key.'_data'] = get_post_meta($post->id, $key.'_data', true);
            }
        }

        if ($post->type == 'term') {
            $report['wpil_sync_report3'] = get_term_meta($post->id, 'wpil_sync_report3', true);
        } else {
            $report['wpil_sync_report3'] = get_post_meta($post->id, 'wpil_sync_report3', true);
        }

        $res['report'] = $report;
        $res['phrases'] = Wpil_Suggestion::getPostSuggestions($post, null, true, null, null, rand(0, time()));
        $res['site_plugins'] = get_plugins();

        return $res;
    }

    public static function get_table_data(){
        global $wpdb;
        // create a list of all possible tables
        $tables = Wpil_Base::getDatabaseTableList();

        // set up the list for the table data
        $table_results = array();

        $create_table = "Create Table";

        // go over the list of tables
        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $results = $wpdb->get_results("SHOW CREATE TABLE {$table}");
                if(!empty($results) && isset($results[0]) && isset($results[0]->Table) && isset($results[0]->$create_table)){
                    $results = array(
                        "Table" => str_ireplace($wpdb->prefix, 'PREFIX_', $results[0]->Table),
                        "Create Table" => str_ireplace($wpdb->prefix, 'PREFIX_', $results[0]->$create_table)
                    );
                }

                $table_results[] = $results;
            }else{
                $table_results[] = 'The "' . str_ireplace($wpdb->prefix, 'PREFIX_', $table) . '" table doesn\'t exist';
            }
        }

        return $table_results;
    }

    /**
     * Counts how many posts are in the posts table.
     **/
    public static function get_database_post_count(){
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts}");
        return !empty($count) ? (int)$count: 0;
    }

    /**
     * Export table data to CSV
     */
    public static function ajax_csv()
    {
        $type = !empty($_POST['type']) ? $_POST['type'] : null;
        $count = !empty($_POST['count']) ? $_POST['count'] : null;

        if (!$type || !$count) {
            wp_send_json([
                    'error' => [
                    'title' => __('Request Error', 'wpil'),
                    'text'  => __('Bad request. Please try again later', 'wpil')
                ]
            ]);
        }

        // get the directory that we'll be writing the export to
        $dir = false;
        $dir_url = false;
        if(is_writable(WP_INTERNAL_LINKING_PLUGIN_DIR)){
            // if it's possible, write to the plugin directory
            $dir = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/';
            $dir_url = WP_INTERNAL_LINKING_PLUGIN_URL . 'includes/';
        }else{
            // if writing to the plugin directory isn't possible, try for the uploads folder
            $uploads = wp_upload_dir(null, false);
            if(!empty($uploads) && isset($uploads['basedir']) && is_writable($uploads['basedir'])){
                if(wp_mkdir_p(trailingslashit($uploads['basedir']). 'link-whisper-premium/exports')){
                    $dir = trailingslashit($uploads['basedir']). 'link-whisper-premium/exports/';
                    $dir_url = trailingslashit($uploads['baseurl']). 'link-whisper-premium/exports/';
                }
            }
        }

        // if we aren't able to write to any directories
        if(empty($dir)){
            // tell the user about it
            wp_send_json([
                'error' => [
                    'title' => __('File Permission Error', 'wpil'),
                    'text'  => __('The uploads folder isn\'t writable by Link Whisper. Please contact your host or webmaster about making the "/uploads/link-whisper-premium/" folder writable.', 'wpil') // we're defaulting to the uploads folder here since it's the easiest one to support
                ]
            ]);
        }

        if ($count == 1) {
            $fp = fopen($dir . $type . '_export.csv', 'w');
            switch ($type) {
                case 'links':
                    if(!empty(Wpil_Settings::HasGSCCredentials())){
                        $header = "Title,Type,Category,Tags,Published,Organic Traffic,AVG Position,Source Page URL - (The page we are linking from),Outbound Link URL,Outbound Link Anchor,Inbound Link Page Source URL,Inbound Link Anchor\n";
                    }else{
                        $header = "Title,Type,Category,Tags,Published,Source Page URL - (The page we are linking from),Outbound Link URL,Outbound Link Anchor,Inbound Link Page Source URL,Inbound Link Anchor\n";
                    }
                    break;
                case 'links_summary':
                    if(!empty(Wpil_Settings::HasGSCCredentials())){
                        $header = "Title,URL,Type,Category,Tags,Published,Organic Traffic,AVG Position,Inbound internal links,Outbound internal links,Outbound external links\n";
                    }else{
                        $header = "Title,URL,Type,Category,Tags,Published,Inbound internal links,Outbound internal links,Outbound external links\n";
                    }
                    break;
                case 'domains':
                    $header = "Domain,Post URL,Anchor Text,Anchor URL,Post Edit Link\n";
                    break;
                case 'domains_summary':
                    $header = "Domain,Post Count,Link Count\n";
                    break;
                case 'error':
                    $header = "Post,Broken URL,Type,Status,Discovered\n";
                    break;
            }
            fwrite($fp, $header);
        } else {
            $fp = fopen($dir . $type . '_export.csv', 'a');
        }

        //get data
        $data = '';
        $func = 'csv_' . $type;
        if (method_exists('Wpil_export', $func)) {
            $data = self::$func($count);
        }

        //send finish response
        if (empty($data)) {
            header('Content-type: text/csv');
            header('Content-disposition: attachment; filename=' . $type . '_export.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            wp_send_json([
                'fileExists' => file_exists($dir . $type . '_export.csv'),
                'filename' => $dir_url . $type . '_export.csv'
            ]);
        }

        //write to file
        fwrite($fp, $data);

        wp_send_json([
            'filename' => '',
            'type' => $type,
            'count' => $count
        ]);

        die;
    }

    /**
     * Prepare links data for export
     *
     * @return string
     */
    public static function csv_links($count)
    {
        $links = Wpil_Report::getData($count, '', 'ASC', '', 500);
        $authed = Wpil_Settings::HasGSCCredentials();
        $data = '';
        $post_url_cache = array();
        foreach ($links['data'] as $link) {
            if (!empty($link['post']->getTitle())) {
                $inbound_internal  = $link['post']->getInboundInternalLinks();
                $outbound_internal = $link['post']->getOutboundInternalLinks();
                $outbound_external = $link['post']->getOutboundExternalLinks();
                $outbound_links = array_merge($outbound_internal, $outbound_external);
                if($authed){
                    $organic_traffic = $link['post']->get_organic_traffic()->clicks;
                    $position = $link['post']->get_organic_traffic()->position;
                }

                // if there's more inbound internal links than outbound links
                $diff = count($outbound_links) - count($inbound_internal);
                if($diff < 0){
                    for ($j = 0; $j < max(abs($diff), 1); $j++) {
                        $outbound_links[] = false;
                    }
                }

                for ($i = 0; $i < max(count($outbound_links), 1); $i++) {
                    $post = $link['post'];
                    $cats = array();
                    foreach($post->getPostTerms(array('hierarchical' => true)) as $term){
                        $cats[] = $term->name;
                    }
                    $category = (!empty($cats)) ? '"' . addslashes(implode(', ', $cats)) . '"' : '';
    
                    // get any terms
                    $tags = array();
                    foreach($post->getPostTerms(array('hierarchical' => false)) as $term){
                        $tags[] = $term->name;
                    }
                    $tag = (!empty($tags)) ? '"' . addslashes(implode(', ', $tags)) . '"' : '';

                    $inbound_post_source_url = '';
                    if(!empty($inbound_internal[$i])){
                        $inbnd_id = $inbound_internal[$i]->post->id;
                        if(!isset($post_url_cache[$inbnd_id])){
                            $post_url_cache[$inbnd_id] = wp_make_link_relative($inbound_internal[$i]->post->getLinks()->view);
                        }
                        $inbound_post_source_url = $post_url_cache[$inbnd_id];
                    }

                    $item = [
                        !$i ? '"' . addslashes($post->getTitle()) . '"' : '',
                        !$i ? $post->getType() : '',
                        !$i ? '"' . $link['date'] . '"' : '',
                        wp_make_link_relative($post->getLinks()->view),
                        !empty($outbound_links[$i]) ? (
                            Wpil_Link::isInternal($outbound_links[$i]->url) ? wp_make_link_relative($outbound_links[$i]->url) : $outbound_links[$i]->url
                        ) : '',
                        !empty($outbound_links[$i]) ? '"' . addslashes(substr(trim(strip_tags($outbound_links[$i]->anchor)), 0, 100)) . '"' : '',
                        $inbound_post_source_url,
                        !empty($inbound_internal[$i]) ? '"' . addslashes(substr(trim(strip_tags($inbound_internal[$i]->anchor)), 0, 100)) . '"' : '',
                    ];

                    if($authed){
                        $data .= $item[0] . "," . $item[1] . "," . $category . "," . $tag . "," . $item[2] . "," . $organic_traffic . "," . $position . "," . $item[3] . "," . $item[4] . "," . $item[5] .  "," . $item[6] . "," . $item[7] . "\n";
                    }else{
                        $data .= $item[0] . "," . $item[1] . "," . $category . "," . $tag . "," . $item[2] . "," . $item[3] . "," . $item[4] . "," . $item[5] .  "," . $item[6] . "," . $item[7] . "\n";
                    }
                }
            }
        }

        return $data;
    }

    public static function csv_links_summary($count)
    {
        $links = Wpil_Report::getData($count, '', 'ASC', '', 500);
        $authed = Wpil_Settings::HasGSCCredentials();
        $data = '';
        foreach ($links['data'] as $link) {
            if (!empty($link['post']->getTitle())) {
                //prepare data
                $post = $link['post'];
                $title = '"' . addslashes($post->getTitle()) . '"';
                $url = wp_make_link_relative($post->getLinks()->view);
                $type = $post->getType();
                // get the post's categories
                $cats = array();
                foreach($post->getPostTerms(array('hierarchical' => true)) as $term){
                    $cats[] = $term->name;
                }
                $category = (!empty($cats)) ? '"' . addslashes(implode(', ', $cats)) . '"' : '';

                // get any terms
                $tags = array();
                foreach($post->getPostTerms(array('hierarchical' => false)) as $term){
                    $tags[] = $term->name;
                }
                $tag = (!empty($tags)) ? '"' . addslashes(implode(', ', $tags)) . '"' : '';

                $date = '"' . $link['date'] . '"';
                $ii_count = $post->getInboundInternalLinks(true);
                $oi_count = $post->getOutboundInternalLinks(true);
                $oe_count = $post->getOutboundExternalLinks(true);
                if($authed){
                    $data .= $title . "," . $url . "," . $type . "," . $category . "," . $tag . "," . $date . "," . $post->get_organic_traffic()->clicks . "," . $post->get_organic_traffic()->position . "," . $ii_count . "," . $oi_count . "," . $oe_count . "\n";
                }else{
                    $data .= $title . "," . $url . "," . $type . "," . $category . "," . $tag . "," . $date . "," . $ii_count . "," . $oi_count . "," . $oe_count . "\n";
                }
            }
        }

        return $data;
    }

    /**
     * Prepare domains data for export
     *
     * @return string
     */
    public static function csv_domains($count)
    {
        $domains = Wpil_Dashboard::getDomainsData(500, $count, '');
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $max = max(count($domain['posts']), count($domain['links']), 1);
            for ($i=0; $i < $max; $i++) {
                $post = $domain['links'][$i]->post;
                $item = [
                    $domain['host'],
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->view) : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->anchor : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->url : '',
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->edit) : '',
                ];

                $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
            }
        }

        return $data;
    }

    /**
     * Prepare domains summary data for export
     *
     * @param $count
     * @return string
     */
    public static function csv_domains_summary($count)
    {
        $domains = Wpil_Dashboard::getDomainsData(500, $count, '');
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $data .= $domain['host'] . "," . count($domain['posts']) . "," . count($domain['links']) . "\n";
        }

        return $data;
    }

    /**
     * Prepare errors data for export
     *
     * @return string
     */
    public static function csv_error($count)
    {
        $links = Wpil_Error::getData(500, $count);
        $data = '';
        foreach ($links['links'] as $link) {
            $item = [
                '"' . addslashes($link->post_title) . '"',
                $link->url,
                $link->internal ? 'internal' : 'external',
                $link->code . ' ' . Wpil_Error::getCodeMessage($link->code),
                date('d M Y (H:i)', strtotime($link->created))
            ];
            $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
        }

        return $data;
    }

    /**
     * Exports suggestion data in CSV or Excel formats.
     * Using a separate method from the ajax_csv since this handles data from the frontend,
     * and I want to keep things less complicated on that front.
     **/
    public static function ajax_export_suggestion_data(){
        Wpil_Base::verify_nonce('export-suggestions-' . $_POST['export_data']['id']);

        if(empty($_POST['export_data']) || empty($_POST['export_data']['id'])){
            wp_send_json(array('error' => array('title' => __('No Suggestion Data', 'wpil'), 'text' => __('The suggestion data wasn\'t able to be downloaded. Please reload the page and try again', 'wpil'))));
        }

        // decode the data
        $_POST['export_data']['data'] = json_decode(stripslashes($_POST['export_data']['data']), true);

        if(!empty(json_last_error())){
            wp_send_json(array('error' => array('title' => __('Data Error', 'wpil'), 'text' => __('There was a problem in processing the suggestion data. Please reload the page and try again', 'wpil'))));
        }

        if($_POST['export_data']['export_type'] === 'csv'){
            self::create_csv_suggestion_export($_POST['export_data']);
        }elseif($_POST['export_data']['export_type'] === 'excel'){
            self::create_excel_suggestion_export();
        }
    }

    public static function create_csv_suggestion_export($data){
        $gsc_authed = Wpil_Settings::HasGSCCredentials();
        $options = get_user_meta(get_current_user_id(), 'report_options', true); 
        $show_traffic = (isset($options['show_traffic'])) ? ( ($options['show_traffic'] == 'off') ? false : true) : false;


        if($data['suggestion_type'] === 'outbound'){
            $source_post = new Wpil_Model_Post((int)$data['id'], sanitize_text_field($data['type']));
            $filename = $source_post->id . '-' . $source_post->getSlug(false) . '_outbound-suggestions.csv';
        }elseif($data['suggestion_type'] === 'inbound'){
            $destination_post = new Wpil_Model_Post((int)$data['id'], sanitize_text_field($data['type']));
            $filename = $destination_post->id . '-' . $destination_post->getSlug(false) . '_inbound-suggestions.csv';
        }

        $header = "Source Post Title, Source Post URL, Source Sentence Text, Suggested Anchor Text, Destination Post Title, Destination Post URL";
        if($gsc_authed && $show_traffic){
            $header .= ", Source Post GSC Clicks, Source Post GSC Impressions, Source Post GSC Average Position, Source Post GSC CTR";
        }
        $header .= "\n";

        // get the directory that we'll be writing the export to
        $dir = false;
        $dir_url = false;
        if(is_writable(WP_INTERNAL_LINKING_PLUGIN_DIR)){
            // if it's possible, write to the plugin directory
            $dir = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/';
            $dir_url = WP_INTERNAL_LINKING_PLUGIN_URL . 'includes/';
        }else{
            // if writing to the plugin directory isn't possible, try for the uploads folder
            $uploads = wp_upload_dir(null, false);
            if(!empty($uploads) && isset($uploads['basedir']) && is_writable($uploads['basedir'])){
                if(wp_mkdir_p(trailingslashit($uploads['basedir']). 'link-whisper-premium/exports')){
                    $dir = trailingslashit($uploads['basedir']). 'link-whisper-premium/exports/';
                    $dir_url = trailingslashit($uploads['baseurl']). 'link-whisper-premium/exports/';
                }
            }
        }

        // if we aren't able to write to any directories
        if(empty($dir)){
            // tell the user about it
            wp_send_json([
                'error' => [
                    'title' => __('File Permission Error', 'wpil'),
                    'text'  => __('The uploads folder isn\'t writable by Link Whisper. Please contact your host or webmaster about making the "/uploads/link-whisper-premium/" folder writable.', 'wpil') // we're defaulting to the uploads folder here since it's the easiest one to support
                ]
            ]);
        }

        $fp = fopen($dir . 'suggestion_export.csv', 'w');

        fwrite($fp, $header);

        //get data
        $export_data = '';
        $post_cache = array();
        foreach($data['data'] as $link_data){
            foreach ($link_data['links'] as $dat) {
                $cache_id = $dat['id'] . '_' . $dat['type'];
                if($data['suggestion_type'] === 'outbound'){
                    if(isset($post_cache[$cache_id])){
                        $destination_post = $post_cache[$cache_id];
                    }else{
                        $destination_post = new Wpil_Model_Post($dat['id'], $dat['type']);
                    }
                }else{
                    if(isset($post_cache[$cache_id])){
                        $source_post = $post_cache[$cache_id];
                    }else{
                        $source_post = new Wpil_Model_Post($dat['id'], $dat['type']);
                    }
                }

                $dat['sentence'] = trim(strip_tags($dat['sentence_with_anchor'])); // for some reason, the custom sentence doesn't always get picked up. So we'll run with the sentence with anchor
                $dat['sentence_with_anchor'] = trim(stripslashes($dat['sentence_with_anchor']));

                $link = Wpil_Post::getSentenceWithAnchor($dat);
                $source_sentence_text = strip_tags($link);
                preg_match('|<a[^>]*>(.*?)<\/a>|i', $link, $anchor_text);
                $anchor_text = (isset($anchor_text[1]) && !empty($anchor_text[1])) ? strip_tags($anchor_text[1]) : '';

                // Source Post Title, Source Post URL, Source Sentence Text, Suggested Anchor Text, Destination Post Title, Destination Post URL
                $item = array(
                    $source_post->getTitle(),
                    str_replace('&amp;', '&', $source_post->getLinks()->view),
                    $source_sentence_text,
                    $anchor_text,
                    $destination_post->getTitle(),
                    str_replace('&amp;', '&', $destination_post->getLinks()->view)
                );

                // if GSC is authed and the user wants to see GSC data
                if($gsc_authed && $show_traffic){
                    $item[] = $source_post->get_organic_traffic()->clicks;
                    $item[] = $source_post->get_organic_traffic()->impressions;
                    $item[] = $source_post->get_organic_traffic()->position;
                    $item[] = $source_post->get_organic_traffic()->ctr;
                }

                $export_data .= implode(',', $item) . "\n";

                // cache the post if it's not already cached
                if(!isset($post_cache[$cache_id])){
                    $post_cache[$cache_id] = ($data['suggestion_type'] === 'outbound') ? $destination_post: $source_post;
                }
            }
        }

        //write to file
        fwrite($fp, $export_data);
        fclose($fp);

        //send finish response
        header('Content-disposition: attachment; filename=suggestion_export.csv');

        wp_send_json([
            'filename' => $dir_url . 'suggestion_export.csv',
            'nicename' => $filename
        ]);
    }

    public static function create_excel_suggestion_export(){
        
    }
}
