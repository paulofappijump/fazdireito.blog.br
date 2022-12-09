<?php

/**
 * Class for managing the keywords the user wants to target for specific posts
 */
class Wpil_TargetKeyword{

    /**
     * Show table page
     */
    public static function init()
    {
        $user = wp_get_current_user();
        $reset = false; //!empty(get_option('wpil_url_changer_reset')); // todo change for target keywords
        $table = new Wpil_Table_TargetKeyword();
        $table->prepare_items();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/target_keywords.php';
    }

    public static function register(){
        add_action('wp_ajax_wpil_target_keyword_reset', array(__CLASS__, 'ajax_target_keyword_reset'));
        add_action('wp_ajax_wpil_target_keyword_selected_update', array(__CLASS__, 'ajax_target_keyword_selected_update'));
        add_action('wp_ajax_wpil_create_custom_target_keyword', array(__CLASS__, 'ajax_create_custom_target_keyword'));
        add_action('wp_ajax_wpil_delete_custom_target_keyword', array(__CLASS__, 'ajax_delete_custom_target_keyword'));
        add_action('wp_ajax_wpil_save_inbound_target_keyword_visibility', array(__CLASS__, 'ajax_save_inbound_target_keyword_visibility'));
        add_action('wp_ajax_wpil_save_inbound_link_stats_visibility', array(__CLASS__, 'ajax_save_inbound_link_stats_visibility'));
        add_filter('screen_settings', array(__CLASS__, 'show_screen_options'), 11, 2);
        add_filter('set_screen_option_target_keyword_options', array(__CLASS__, 'saveOptions'), 12, 3);
        add_action('save_post', array(__CLASS__, 'update_keywords_on_post_save'), 99, 3);
        self::init_cron();
    }

    public static function init_cron(){
        if(empty(get_option('wpil_disable_search_update', false))){
            add_filter('cron_schedules', array(__CLASS__, 'add_gsc_query_interval'));
            add_action('admin_init', array(__CLASS__, 'schedule_gsc_query'));
            add_action('wpil_search_console_update', array(__CLASS__, 'do_scheduled_gsc_query'));
        }

        register_deactivation_hook(__FILE__, array(__CLASS__, 'clear_cron_schedules'));
    }

    public static function show_screen_options($settings, $screen_obj){

        $screen = get_current_screen();
        $options = get_user_meta(get_current_user_id(), 'target_keyword_options', true);

        // exit if we're not on the target keywords page
        if(!is_object($screen) || $screen->id != 'link-whisper_page_link_whisper_target_keywords'){
            return $settings;
        }

        // Check if the screen options have been saved. If so, use the saved value. Otherwise, use the default values.
        if ( $options ) {
            $show_categories = !empty($options['show_categories']) && $options['show_categories'] != 'off';
            $show_type = !empty($options['show_type']) && $options['show_type'] != 'off';
            $show_date = !empty($options['show_date']) && $options['show_date'] != 'off';
            $per_page = !empty($options['per_page']) ? $options['per_page'] : 20 ;
            $show_traffic = !empty($options['show_traffic']) && $options['show_traffic'] != 'off';
            $remove_obviated_keywords = !empty($options['remove_obviated_keywords']) && $options['remove_obviated_keywords'] != 'off';
        } else {
            $show_categories = true;
            $show_date = true;
            $show_type = false;
            $per_page = 20;
            $show_traffic = true;
            $remove_obviated_keywords = false;
        }

        //get apply button
        $button = get_submit_button( __( 'Apply', 'wp-screen-options-framework' ), 'primary large', 'screen-options-apply', false );

        //show HTML form
        ob_start();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/target_keyword_options.php';
        return ob_get_clean();
    }

    public static function saveOptions($status, $option, $value) {
        if(!wp_verify_nonce($_POST['screenoptionnonce'], 'screen-options-nonce')){
            return;
        }

        if ($option == 'target_keyword_options') {
            $value = [];
            if (isset( $_POST['target_keyword_options'] ) && is_array( $_POST['target_keyword_options'] )) {
                if (!isset($_POST['target_keyword_options']['show_categories'])) {
                    $_POST['target_keyword_options']['show_categories'] = 'off';
                }
                if (!isset($_POST['target_keyword_options']['show_type'])) {
                    $_POST['target_keyword_options']['show_type'] = 'off';
                }
                if (!isset($_POST['target_keyword_options']['show_date'])) {
                    $_POST['target_keyword_options']['show_date'] = 'off';
                }
                $value = $_POST['target_keyword_options'];
            }

            return $value;
        }

        return $status;
    }

    /**
     * Updates Yoast and Rank Math keywords on post save.
     **/
    public static function update_keywords_on_post_save($post_id, $post = null, $updated = null){
        $selected_keyword_sources = self::get_active_keyword_sources();

        // if yoast is active
        if(in_array('yoast', $selected_keyword_sources, true)){
            // delete the existing yoast keywords
            self::delete_keyword_by_type($post_id, 'post', 'yoast-keyword');
            // obtain the current post keywords
            $yoast_keywords = self::get_yoast_post_keywords_by_id($post_id, 'post');
            // save them to the db
            if(!empty($yoast_keywords)){
                $save_data = array();
                foreach($yoast_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $post_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'yoast-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if rank math is active
        if(in_array('rank-math', $selected_keyword_sources, true)){
            // delete the existing rank math keywords
            self::delete_keyword_by_type($post_id, 'post', 'rank-math-keyword');
            // obtain the current post keywords
            $rm_keywords = self::get_rank_math_post_keywords_by_id($post_id, 'post');
            // save them to the db
            if(!empty($rm_keywords)){
                $save_data = array();
                foreach($rm_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $post_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'rank-math-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if All In One SEO is active
        if(in_array('aioseo', $selected_keyword_sources, true)){
            // delete the existing AIOSEO keywords
            self::delete_keyword_by_type($post_id, 'post', 'aioseo-keyword');
            // obtain the current post keywords
            $aio_keywords = self::get_aioseo_post_keywords_by_id($post_id, 'post');
            // save them to the db
            if(!empty($aio_keywords)){
                $save_data = array();
                foreach($aio_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $post_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'aioseo-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if SEOPress is active
        if(in_array('seopress', $selected_keyword_sources, true)){
            // delete the existing SEOPress keywords
            self::delete_keyword_by_type($post_id, 'post', 'seopress-keyword');
            // obtain the current post keywords
            $aio_keywords = self::get_seopress_post_keywords_by_id($post_id, 'post');
            // save them to the db
            if(!empty($aio_keywords)){
                $save_data = array();
                foreach($aio_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $post_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'seopress-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if Post Content keywords are active
        if(in_array('post-content', $selected_keyword_sources, true)){
            // delete the existing Post Content keywords
            self::delete_keyword_by_type($post_id, 'post', 'post-content-keyword');
            // obtain the current post keywords
            $post_keywords = self::get_post_content_keywords_by_id($post_id, 'post');
            // save them to the db
            if(!empty($post_keywords)){
                $save_data = array();
                foreach($post_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $post_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'post-content-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }
    }

    /**
     * Updates Yoast and Rank Math keywords on term save.
     **/
    public static function update_keywords_on_term_save($term_id){
        $selected_keyword_sources = self::get_active_keyword_sources();

        // if yoast is active
        if(in_array('yoast', $selected_keyword_sources, true)){
            // delete the existing yoast keywords
            self::delete_keyword_by_type($term_id, 'term', 'yoast-keyword');
            // obtain the current post keywords
            $yoast_keywords = self::get_yoast_post_keywords_by_id($term_id, 'term');
            // save them to the db
            if(!empty($yoast_keywords)){
                $save_data = array();
                foreach($yoast_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $term_id,
                        'post_type'     => 'term',
                        'keyword_type'  => 'yoast-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if rank math is active
        if(in_array('rank-math', $selected_keyword_sources, true)){
            // delete the existing rank math keywords
            self::delete_keyword_by_type($term_id, 'term', 'rank-math-keyword');
            // obtain the current post keywords
            $rm_keywords = self::get_rank_math_post_keywords_by_id($term_id, 'term');
            // save them to the db
            if(!empty($rm_keywords)){
                $save_data = array();
                foreach($rm_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $term_id,
                        'post_type'     => 'term',
                        'keyword_type'  => 'rank-math-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // if aioseo is active
        if(in_array('aioseo', $selected_keyword_sources, true)){
            // delete the existing rank math keywords
            self::delete_keyword_by_type($term_id, 'term', 'aioseo-keyword');
            // obtain the current post keywords
            $aio_keywords = self::get_aioseo_post_keywords_by_id($term_id, 'term');
            // save them to the db
            if(!empty($aio_keywords)){
                $save_data = array();
                foreach($aio_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $term_id,
                        'post_type'     => 'term',
                        'keyword_type'  => 'aioseo-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }

        // SEOPress doesn't support target keywords for terms. So we don't need to include a saver for term keywords.

        // if Post Content keywords are active
        if(in_array('post-content', $selected_keyword_sources, true)){
            // delete the existing Post Content keywords
            self::delete_keyword_by_type($term_id, 'term', 'post-content-keyword');
            // obtain the current post keywords
            $post_keywords = self::get_post_content_keywords_by_id($term_id, 'term');
            // save them to the db
            if(!empty($post_keywords)){
                $save_data = array();
                foreach($post_keywords as $dat){
                    $save_data[] = array(
                        'post_id'       => $term_id,
                        'post_type'     => 'post',
                        'keyword_type'  => 'post-content-keyword',
                        'keywords'      => $dat->keyword,
                        'checked'       => 1,
                        'impressions'   => 0,
                        'clicks'        => 0
                    );
                }

                self::save_target_keyword_data($save_data);
            }
        }
    }

    public static function add_gsc_query_interval($schedules){
        $schedules['wpil_14_days'] = array(
            'interval' => DAY_IN_SECONDS * 14,
            'display' => __('Every Fourteen Days', 'wpil')
        );

        $schedules['wpil_10_minutes'] = array(
            'interval' => MINUTE_IN_SECONDS * 10,
            'display' => __('Every Ten Minutes', 'wpil')
        );
        return $schedules;
    }

    public static function schedule_gsc_query(){
        if(!wp_get_schedule('wpil_search_console_update')){
            wp_schedule_event(time(), 'wpil_14_days', 'wpil_search_console_update');
        }
    }

    public static function schedule_gsc_process_run(){
        if(!wp_get_schedule('wpil_search_console_update_step')){
            wp_schedule_event(time(), 'wpil_10_minutes', 'wpil_search_console_update_step');
        }
    }

    public static function clear_cron_schedules(){
        $timestamp = wp_next_scheduled('wpil_search_console_update');
        if(!empty($timestamp)){
            wp_unschedule_event($timestamp, 'wpil_search_console_update');
        }

        $timestamp = wp_next_scheduled('wpil_search_console_update_step');
        if(!empty($timestamp)){
            wp_unschedule_event($timestamp, 'wpil_search_console_update_step');
        }
    }

    /**
     * Removes just the process runner schedule from the wp_cron queueW
     **/
    public static function clear_gsc_process_run_schedule(){
        $timestamp = wp_next_scheduled('wpil_search_console_update_step');
        if(!empty($timestamp)){
            wp_unschedule_event($timestamp, 'wpil_search_console_update_step');
        }
    }

    /**
     * Clears the active cron schedules and any stored cron transients.
     **/
    public static function reset_cron_process(){
        // clear the cron tasks
        self::clear_cron_schedules();
        // unset the data transients
        delete_transient('wpil_gsc_query_completed');
        delete_transient('wpil_gsc_query_row');
        delete_transient('wpil_gsc_query_row_increment');
    }

    /**
     * 
     **/
    public static function do_scheduled_gsc_query(){
        // if the auto GSC query update has been disabled or GSC isn't authorized
        if(!empty(get_option('wpil_disable_search_update', false)) || empty(Wpil_SearchConsole::is_authenticated())){
            // clear the existing schedule
            self::clear_cron_schedules();
            // and exit
            return;
        }

        $process_complete = false;

        // check if the query stage has been completed
        $query_completed = get_transient('wpil_gsc_query_completed');

        // if the processing hasn't been completed, query for more data
        if(empty($query_completed)){
            // get the row that the query will start at
            $starting_row = get_transient('wpil_gsc_query_row');
            if(empty($starting_row)){
                $starting_row = 0;
                // clear the old GSC data since this is a new scan
                Wpil_SearchConsole::setup_search_console_table();
            }

            // set the row
            $data = array('gsc_row' => $starting_row);

            // refresh the access token
            Wpil_SearchConsole::refresh_auth_token();

            // query for a batch of data
            $data = self::incremental_query_gsc_data($data, $starting_row, 33, 3);

            // update the query row
            set_transient('wpil_gsc_query_row', $data['gsc_row'], DAY_IN_SECONDS);

            // get the incremental step of the data call
            $incremental_step = get_transient('wpil_gsc_query_row_increment');
            if(empty($incremental_step)){
                $incremental_step = 0;
            }

            // set if the query is complete
            if(isset($data['state']) && $data['state'] === 'gsc_process' && $incremental_step >= 10){
                set_transient('wpil_gsc_query_completed', true, DAY_IN_SECONDS);
            }

        }else{
            // if the query has been completed, process the data
            $data = array('state' => 'gsc_process');
            $data = self::process_gsc_data($data, microtime(true));

            // if we're done processing the gas data
            if($data['state'] !== 'gsc_process'){
                // mark the process as complete
                $process_complete = true;
                // and erase the search console data
                Wpil_SearchConsole::setup_search_console_table();
            }
        }

        // if the processing isn't complete, set up for another run in 10 mins
        if(empty($process_complete)){
            self::schedule_gsc_process_run();
        }else{
            // if the processing is complete, remove the process cron task
            self::clear_gsc_process_run_schedule();
        }

    }

    public static function getData($per_page, $page, $search, $orderby = '', $order = ''){
        global $wpdb;

        self::prepareTable();
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;
        $order = ('desc' === $order || 'DESC' === $order) ? $order = 'desc': 'asc';

        // if no order is given or it's by post, query for a page of posts
        if(empty($orderby) || 'post_title' === $orderby || 'date' === $orderby){

            $post_data = Wpil_Report::getData($page, $orderby, $order, $search, $per_page);

            if(!empty($post_data) && isset($post_data['data'])){
                $ids = array();
                foreach($post_data['data'] as $dat){
                    $ids[] = $dat['post']->id;
                }
            }else{
                return array();
            }

            return $post_data;
        }else{
    
            //$search = !empty($search) ? $wpdb->prepare(" AND (keywords LIKE '%%%s%%') ", $search) : '';

            $data = self::query_keyword_posts($order, $limit, $orderby);

            return $data;
        }
    }

    public static function query_keyword_posts($order, $limit, $orderby = 'ID'){
        global $wpdb;
        $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        $options = get_user_meta(get_current_user_id(), 'target_keyword_options', true);
        $options2 = get_user_meta(get_current_user_id(), 'report_options', true);   // get the report settings so we can ignore the posts here too
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $hide_noindex = (isset($options2['hide_noindex'])) ? ( ($options2['hide_noindex'] == 'off') ? false : true) : false;
        $process_terms = !empty(Wpil_Settings::getTermTypes());
        $post_types = "'" . implode("','", Wpil_Settings::getPostTypes()) . "'";

        $statuses_query = Wpil_Query::postStatuses('p');
        $report_term_ids = Wpil_Query::reportTermIds(false, $hide_noindex);

        // hide ignored
        $ignored_posts = '';
        $ignored_terms = '';
        if($show_categories && Wpil_Settings::hideIgnoredPosts()){
            $ignored_terms = Wpil_Query::ignoredTermIds();
        }

        $ignored_posts = Wpil_Query::get_all_report_ignored_post_ids('p', array('hide_noindex' => $hide_noindex));

        switch($orderby){
            case 'gsc':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'gsc-keyword' THEN 1 END)";
                break;
            case 'yoast':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'yoast-keyword' THEN 1 END)";
                break;
            case 'rank-math':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'rank-math-keyword' THEN 1 END)";
                break;
            case 'aioseo':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'aioseo-keyword' THEN 1 END)";
                break;
            case 'seopress':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'seopress-keyword' THEN 1 END)";
                break;
            case 'custom':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'custom-keyword' THEN 1 END)";
                break;
            case 'post-content':
                $orderby = "COUNT(CASE WHEN `keyword_type` = 'post-content-keyword' THEN 1 END)";
                break;
            case 'organic_traffic':
                $orderby = "SUM(`clicks`)";
                break;
            default:
                $orderby = 'ID';
                break;
        }

        $filtered_type = Wpil_Filter::linksPostType();
        if(!empty($filtered_type)){
            $post_types = " AND p.post_type = '$filtered_type' ";
            $term_types = " AND tt.taxonomy = '$filtered_type' ";
        }else{
            $taxonomies = Wpil_Settings::getTermTypes();
            $post_types = " AND p.post_type IN ($post_types) ";
            $term_types = " AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') ";
        }

        $query = "SELECT `ID`, a.post_type, `keyword_type`, `keywords`, `checked`, $orderby as county FROM 
        (SELECT p.ID, 'post' AS post_type FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE 1=1 $statuses_query $ignored_posts $post_types AND pm.meta_key = 'wpil_sync_report3' AND pm.meta_value = '1' ";
        if ($show_categories && $process_terms && !empty($report_term_ids)) {
            $query .= " UNION
            SELECT t.term_id as `ID`, 'term' as `post_type`  
            FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
            WHERE t.term_id in ($report_term_ids) $ignored_terms $term_types ";
        }

        $query .= ") a LEFT JOIN {$target_keyword_table} k ON k.post_id = a.ID GROUP BY ID ORDER BY `county` {$order} {$limit}";

        $results = $wpdb->get_results($query);

        $query = "SELECT COUNT(*) as counted FROM 
        (SELECT p.ID, p.post_type FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE 1 = 1 $statuses_query $ignored_posts $post_types AND pm.meta_key = 'wpil_sync_report3' AND pm.meta_value = '1' ";

        if ($show_categories && $process_terms && !empty($report_term_ids)) {
            $query .= " UNION
            SELECT t.term_id as `ID`, tt.taxonomy as `post_type`  
            FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
            WHERE t.term_id in ($report_term_ids) $ignored_terms $term_types";
        }

        $query .= ") b";

        $count = $wpdb->get_var($query);

        return array('total_items' => $count, 'data' => $results);
    }

    /**
     * Create the target keyword table if it hasn't already been created
     * Contains the aggregate keyword data from all sources: GSC, Custom Keywords, Yoast, Rank Math
     **/
    public static function prepareTable(){
        global $wpdb;
        $wpil_target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpil_target_keyword_table}'");
        if ($table != $wpil_target_keyword_table) {
            $wpil_target_keyword_table_query = "CREATE TABLE IF NOT EXISTS {$wpil_target_keyword_table} (
                                        keyword_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        post_id bigint(20) unsigned,
                                        post_type varchar(10),
                                        keyword_type varchar(255),
                                        keywords text,
                                        checked tinyint(1),
                                        impressions bigint(20) UNSIGNED DEFAULT 0,
                                        clicks bigint(20) UNSIGNED DEFAULT 0,
                                        ctr float,
                                        position float,
                                        save_date datetime,
                                        auto_checked tinyint(1) DEFAULT 0,
                                        PRIMARY KEY (keyword_index),
                                        INDEX (post_id),
                                        INDEX (post_type),
                                        INDEX (keyword_type)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_target_keyword_table_query);

            if (strpos($wpdb->last_error, 'Index column size too large') !== false) {
                $wpil_target_keyword_table_query = str_replace(array('INDEX (keyword_type)'), array('INDEX (keyword_type(191))'), $wpil_target_keyword_table_query);
                dbDelta($wpil_target_keyword_table_query);
            }
        }
    }

    /**
     * Resets the target keywords.
     **/
    public static function ajax_target_keyword_reset(){
        global $wpdb;
        $start = microtime(true);

        Wpil_Base::verify_nonce('wpil_target_keyword');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // if this is the first run
        if(isset($_POST['reset']) && 'true' === $_POST['reset']){
            // clear the target keyword table
            self::clear_target_keyword_table();
            // clear the stored GSC data
            Wpil_SearchConsole::setup_search_console_table();
            // refresh the access token
            Wpil_SearchConsole::refresh_auth_token();
            // delete any existing processing data
            delete_option('wpil_target_keyword_processing_data');
            // clear any cron transients and reset the cron schedule
            self::reset_cron_process();
            // set a flag to show when the keywords have been reset
            update_option('wpil_keyword_reset_last_run_time', date('Y-m-d H:i:s', (time())));
        }

        $default = array();

        // set the data default state
        $default['state'] = self::determine_processing_stage('started');

        if($default['state'] === 'gsc_query'){
            $default['gsc_row'] = 0;
            $default['gsc_empty_row_calls'] = 0;
        }

        // get the processing data
        $data = get_option('wpil_target_keyword_processing_data', $default);

        // determine what process to perform
        switch($data['state']){
            case 'gsc_query':
                // query for GSC row data
                $data = self::query_gsc_data($data, $start);
            break;
            case 'gsc_process':
                // process the GSC row data
                $data = self::process_gsc_data($data, $start);
            break;
            case 'gsc_keyword_tag':
                // process the GSC row data
                $data = self::autocheck_gsc_keywords($data, $start);
            break;
            case 'yoast_process':
                // process the Yoast keyword data
                $data = self::process_yoast_data($data, $start);
            break;
            case 'rank_math_process':
                // process the Rank Math keyword data
                $data = self::process_rank_math_data($data, $start);
            break;
            case 'aioseo_process':
                // process the AIOSEO keyword data
                $data = self::process_aioseo_data($data, $start);
            break;
            case 'seopress_process':
                // process the SEOPress keyword data
                $data = self::process_seopress_data($data, $start);
            break;
            case 'post_keyword_process':
                // process the post keyword data
                $data = self::process_post_content_keywords_data($data, $start);
            break;
            case 'custom_process':
                // process the GSC row data
//                $data = self::process_custom_keywords($data, $start); // we don't have to process the custom keywords now since we move them when the reset is run. So they're never deleted
                // clear the search console table since we know we're done with it now
                Wpil_SearchConsole::setup_search_console_table();
                // trigger complete on the custom keywords
                $data['state'] = 'complete';
            break;
            default:
                $data['state'] = 'complete';
            break;
        }

        if('complete' === $data['state']){
            delete_option('wpil_target_keyword_processing_data');
            wp_send_json(array('finish' => true));
        }else{
            update_option('wpil_target_keyword_processing_data', $data);
            wp_send_json($data);
        }
    }

    /**
     * Determines what leg of the processing we're on based on the supplied state.
     * @param string $current_state The current state of the processing.
     * @return string $state The state that we've been able to figure out.
     **/
    public static function determine_processing_stage($current_state){

        $processes = array(
            'started' => 0,
            'gsc_query' => 1,
            'gsc_process' => 2,
            'gsc_keyword_tag' => 3,
            'yoast_process' => 4,
            'rank_math_process' => 5,
            'aioseo_process' => 6,
            'seopress_process' => 7,
            'post_keyword_process' => 8,
            'custom_process' => 9,
        );

        $selected_sources = self::get_active_keyword_sources();
        $process_number = isset($processes[$current_state]) ? $processes[$current_state]: 0;
        $authed = ($process_number <= 3) ? Wpil_SearchConsole::is_authenticated(): false; // if still in the GSC section, check for auth. Otherwise, default to false
        $state = '';

        if($authed && $process_number < 1){
            $state = 'gsc_query';
        }elseif($authed && $process_number < 2){
            $state = 'gsc_process';
        }elseif($authed && $process_number < 3 && get_option('wpil_autotag_gsc_keywords', false)){
            $state = 'gsc_keyword_tag';
        }elseif(in_array('yoast', $selected_sources, true) && $process_number < 4){
            // move on to processing the yoast keywords
            $state = 'yoast_process';
        }elseif(in_array('rank-math', $selected_sources, true) && $process_number < 5){
            // move on to processing the rank math keywords
            $state = 'rank_math_process';
        }elseif(in_array('aioseo', $selected_sources, true) && $process_number < 6){
            // move on to processing the aioseo keywords
            $state = 'aioseo_process';
        }elseif(in_array('seopress', $selected_sources, true) && $process_number < 7){
            // move on to processing the seopress keywords
            $state = 'seopress_process';
        }elseif(in_array('post-content', $selected_sources, true) && $process_number < 8){
            // move on to processing the post content keywords
            $state = 'post_keyword_process';
        }else{
            // move on to processing the custom keywords
            $state = 'custom_process';
        }

        return $state;
    }
    
    /**
     * Gets the date that we'll be querying gsc keywords for.
     * @param bool $return_newest (Optional) Return the newest processable date.
     * @return string|bool Returns the date string that data will be queried for, or returns false if no date is available.
     **/
    public static function get_query_date($return_newest = false){
        // get the stored dates
        $processed_dates = get_option('wpil_keyword_query_dates', array(
            'oldest' => false,
            'newest' => false));

        $current_time = current_time('timestamp', true);

        // create the max possible dates that we can get GSC data from
        $min = gmdate('Y-m-d', $current_time - (DAY_IN_SECONDS * 3));
        $max = gmdate('Y-m-d', $current_time - (DAY_IN_SECONDS * 33));

        // if we're supposed to just return the newest date, return it
        if($return_newest){
            // if there isn't a newest date, return the min
            return (isset($processed_dates['newest']) && !empty($processed_dates['newest'])) ? $processed_dates['newest']: $min;
        }

        if((empty($processed_dates['oldest']) && empty($processed_dates['newest']))){
            $start_date = $min;
        }elseif($processed_dates['newest'] < $min){
            $start_date = $processed_dates['newest'];
        }elseif($processed_dates['oldest'] > $max){
            $start_date = $processed_dates['oldest'];
        }else{
            $start_date = false;
        }

        return $start_date;
    }

    /**
     * Updates the stored processed date information.
     * Automatically advances the date on the assumption that the processing for the date is complete.
     * Should only be called when the processing is complete for a date.
     **/
    public static function update_query_date(){
        // get the stored dates
        $processed_dates = get_option('wpil_keyword_query_dates', array(
            'oldest' => false,
            'newest' => false));

        $current_time = current_time('timestamp', true);

        // create the max possible dates that we can get GSC data from
        $min = gmdate('Y-m-d', $current_time - (DAY_IN_SECONDS * 3));
        $max = gmdate('Y-m-d', $current_time - (DAY_IN_SECONDS * 33));

        if((empty($processed_dates['oldest']) && empty($processed_dates['newest']))){
            $processed_dates['oldest'] = $min;
            $processed_dates['newest'] = $min;
        }elseif($processed_dates['newest'] < $min){
            $time = date_create_from_format('!Y-m-d', $processed_dates['newest']);
            $time->modify("+1 day");
            $processed_dates['newest'] = $time->format('Y-m-d');
        }elseif($processed_dates['oldest'] > $max){
            $time = date_create_from_format('!Y-m-d', $processed_dates['oldest']);
            $time->modify("-1 day");
            $processed_dates['oldest'] = $time->format('Y-m-d');
        }

        update_option('wpil_keyword_query_dates', $processed_dates);
    }

    /**
     * Creates the displayed message for when we're actively downloading the GSC data via Ajax!
     * Now also used for the GSC event log!
     * @param array $data The status of processing data used for managing the process
     **/
    public static function get_processing_status_message($data){
        $date = self::get_query_date();

        // if there's no date
        if(empty($date)){
            // we're done!
            return __('The processing is complete!', 'rank-logic');
        }

        // in the (hopeful) majority of cases though, we'll have to build the message instead
        $message = '';

        // get the processing state
        $state = self::get_processing_state_number($data['state']);

        // first, get where we are in the processing
        switch($state){
            case 0:
                $message .= __('Starting the processing for ', 'rank-logic');
            break;
            case 1:
                $message .= __('Downloading keyword data for ', 'rank-logic');
            break;
            case 2:
                $message .= __('Downloading page data for ', 'rank-logic');
            break;
            case 3:
                $message .= __('Processing GSC data for ', 'rank-logic');
            break;
            case 4:
                $message .= __('Finishing up data processing for ', 'rank-logic');
            break;
        }

        // next get the date that we're processing
        $message .= date(get_option('date_format'), strtotime($date));

        // todo rexamine later. It seems like a good idea, but it looks messy on the frontend
        /*if(false){
            // if we've been through most of the empty calls, let the customer know that we should be getting close
            if($state < 3 && isset($data['gsc_empty_row_calls']) && $data['gsc_empty_row_calls'] > (self::$max_empty_row_calls * 0.75)){
                $message .= ('. (' . __('No data from GSC for a little while, we should be almost done with this date.', 'rank-logic') . ')');
            }elseif($state < 3 && isset($data['gsc_empty_row_calls']) && $data['gsc_empty_row_calls'] > (self::$max_empty_row_calls * 0.5)){
                // if we've gone through half the calls, let the customer know
                $message .= ('. (' . __('GSC is sending us less data, we might be getting close.', 'rank-logic') . ')');
            }
        }*/

        if($state < 3 && isset($data['gsc_row']) && $data['gsc_row'] > 0){
            $message .= ('. (' . sprintf(__('%d queried so far', 'rank-logic'), $data['queried_count']) . ')');
        }


        return $message;
    }

    /**
     * Erases the target keyword data from the target keyword table
     **/
    private static function clear_target_keyword_table(){
        global $wpdb;
        $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        $create_table   = "CREATE TABLE IF NOT EXISTS {$target_keyword_table}_temp LIKE {$target_keyword_table}";
        $insert_data    = "INSERT INTO {$target_keyword_table}_temp (`post_id`, `post_type`, `keyword_type`, `keywords`, `checked`) SELECT `post_id`, `post_type`, `keyword_type`, `keywords`, `checked` FROM {$target_keyword_table} WHERE (`checked` = 1 AND `keyword_type` = 'gsc-keyword') OR `keyword_type` = 'custom-keyword'";
        $rename_table   = "RENAME TABLE {$target_keyword_table} TO {$target_keyword_table}_old, {$target_keyword_table}_temp to {$target_keyword_table}";
        $drop_table     = "DROP TABLE {$target_keyword_table}_old";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($create_table);
        $wpdb->query("TRUNCATE TABLE {$target_keyword_table}_temp");
        dbDelta($insert_data); // insert pulls the data that we want to save from the keyword table into the temp table
        $wpdb->query($rename_table);
        $wpdb->query($drop_table);
    }

    /**
     * Queries for rows of GSC data and saves the returned rows to the DB.
     * Returns an updated version of the process data.
     **/
    public static function query_gsc_data($data = array(), $start = 0, $start_days_ago = 33, $end_days_ago = 3){
        // start querying for GSC records
        $start_date = (!empty($start_days_ago)) ? date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * intval($start_days_ago))) : date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 33));
        $end_date   = (!empty($end_days_ago)) ? date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * intval($end_days_ago))) : date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 3));
        $scan_range = array('start_date' => $start_date, 'end_date' => $end_date);
        $query_limit = 5000;
        $call_log = array();
        while(true){

            // begin dialing Google for the data
            for($i = 0; $i < 2; $i++){
                $rows = Wpil_SearchConsole::query_console_data($start_date, $end_date, array('query', 'page'), $query_limit, $data['gsc_row']); // todo make the query vars some kind of legend so the saver has a more definite arg map.

                // if we have row data, exit the loop for processing
                if(!empty($rows)){
                    break;
                }

                // if there was no response, wait a short amount of time and try again
                usleep(500000);
            }

            $call_log[] = array('row' => $data['gsc_row'], 'call_count' => $i, 'gsc_empty_row_calls' => $data['gsc_empty_row_calls']);

            if(!empty($rows)){
                // save results to DB
                Wpil_SearchConsole::save_row_data($rows, $scan_range);
                // increment the row count
                $data['gsc_row']++;
                // set any empty row call counts back to zero
                $data['gsc_empty_row_calls'] = 0;
            }elseif($data['gsc_empty_row_calls'] < 5){
                $data['gsc_empty_row_calls']++;
                break;
            }else{
                $data['state'] = 'gsc_process';
                break;
            }

            if(Wpil_Base::overTimeLimit(15, 30)){
                break;
            }
        }

        // keep track of how many times google had to be phoned
        $data['call_log'] = $call_log;

        return $data;
    }

    /**
     * Queries for rows of GSC data and saves the returned rows to the DB.
     * Returns an updated version of the process data.
     * Only does it's querying one request and one row at a time to take as little time as possible.
     **/
    public static function incremental_query_gsc_data($data = array(), $start = 0, $start_days_ago = 33, $end_days_ago = 3, $increment = 0){
        // start querying for GSC records
        $start_date = (!empty($start_days_ago)) ? date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * intval($start_days_ago))) : date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 33));
        $end_date   = (!empty($end_days_ago)) ? date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * intval($end_days_ago))) : date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 3));
        $scan_range = array('start_date' => $start_date, 'end_date' => $end_date);
        $query_limit = 5000;

        // increment our progress
        $incremental_step = get_transient('wpil_gsc_query_row_increment');
        if(empty($incremental_step)){
            set_transient('wpil_gsc_query_row_increment', 1, DAY_IN_SECONDS);
        }else{
            set_transient('wpil_gsc_query_row_increment', ((int)$incremental_step += 1), DAY_IN_SECONDS);
        }
        
        $rows = Wpil_SearchConsole::query_console_data($start_date, $end_date, array('query', 'page'), $query_limit, $data['gsc_row']); // todo make the query vars some kind of legend so the saver has a more definite arg map.

        if(!empty($rows)){
            // save results to DB
            Wpil_SearchConsole::save_row_data($rows, $scan_range);
            // increment the row count
            $data['gsc_row']++;
            // reset the increment count
            set_transient('wpil_gsc_query_row_increment', 0, DAY_IN_SECONDS);
        }else{
            $data['state'] = 'gsc_process';
        }

        return $data;
    }

    /**
     * Processes the obtained GSC data to correlate it to posts
     **/
    public static function process_gsc_data($data = array(), $start = 0){

        // get all the unique urls from the GSC data
        $urls = Wpil_SearchConsole::get_unprocessed_unique_urls();

        // if there are no GSC keywords
        if(empty($urls)){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage('gsc_process');

            return $data;
        }

        foreach($urls as $url){
            // exit if we've hit the time limit
            if(microtime(true) - $start > 30){
                return $data;
            }

            $post = Wpil_Post::getPostByLink(self::normalize_gsc_page_url($url->page_url));

            // if we can't find a post for the given url
            if(empty($post)){
                // mark the data as processed and proceed to the next url
                Wpil_SearchConsole::mark_rows_processed_by_url($url->page_url);
                continue;
            }

            $keyword_data = Wpil_SearchConsole::get_rows_by_url($url->page_url);
            $save_data = array();
            foreach($keyword_data as $k_data){
                $save_data[] = array(
                    'post_id'       => $post->id,
                    'post_type'     => $post->type,
                    'keyword_type'  => 'gsc-keyword',
                    'keywords'      => $k_data->keywords,
                    'checked'       => 0,
                    'impressions'   => $k_data->impressions,
                    'clicks'        => $k_data->clicks,
                    'ctr'           => $k_data->ctr,
                    'position'      => $k_data->position,
                );

                // if we've found more than 50000 keywords
                if(count($save_data) > 50000){
                    // save them to the database and reset the save data array
                    self::save_target_keyword_data($save_data);
                    $save_data = array();
                }
            }

            if(!empty($save_data)){
                // save the GSC data to the keyword table
                self::save_target_keyword_data($save_data);
                // and update which keywords are checked
                self::update_checked_gsc_keywords($post);
                // remove any old GSC data
                self::remove_old_gsc_data($post);
            }

            Wpil_SearchConsole::mark_rows_processed_by_url($url->page_url);
        }

        return $data;
    }

    /**
     * Changes the domain of the GSC page URL so it matches the current site's.
     * If the domain is already present in the url, the original url is returned unchanged
     * 
     * @param string $url The URL that we want to normalize
     * @param string $url The resulte of trying to normalize the url
     **/
    public static function normalize_gsc_page_url($url){
        $home = get_home_url();
        if(strpos($url, $home) !== 0){
            $parts = wp_parse_url($url);
            $url = trailingslashit($home);

            if(isset($parts['path'])){
                $url .= ltrim($parts['path'], '/');
            }
        }
        return $url;
    }

    /**
     * Performs the GSC Keyword auto checking if the user has enabled autochecking.
     **/
    public static function autocheck_gsc_keywords($data = array(), $start = 0){
        global $wpdb;
        $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        // get the post ids that we're going to process
        $post_data = self::get_autocheck_gsc_posts();

        // get the number of GSC keywords that we'll be checking
        $autocheck_limit = (int)get_option('wpil_autotag_gsc_keyword_count', 0);

        // if there are no GSC keywords or the user has set the check count to 0
        if(empty($post_data) || empty($autocheck_limit)){
            // delete the post data transient
            delete_transient('wpil_autocheck_post_data');
            // and move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage('gsc_keyword_tag');

            return $data;
        }

        // get the user's sort basis
        $auto_sort_basis = (get_option('wpil_autotag_gsc_keyword_basis', 'impressions') === 'impressions') ? 'impressions': 'clicks';

        foreach($post_data as $key => $dat){
            // exit if we've hit the time limit
            if(microtime(true) - $start > 30){
                return $data;
            }

            $count = $autocheck_limit * 5;
            $active_gsc = $wpdb->get_var("SELECT COUNT(`keyword_index`) FROM {$target_keyword_table} WHERE `post_id` = $dat->post_id AND `post_type` = '{$dat->post_type}' AND `keyword_type` = 'gsc-keyword' AND (`checked` = '1' OR `auto_checked` = '1')");

            if($active_gsc < $autocheck_limit){
                $keyword_data = $wpdb->get_results("SELECT `keyword_index`, `keywords` FROM {$target_keyword_table} WHERE `post_id` = $dat->post_id AND `post_type` = '{$dat->post_type}' AND `keyword_type` = 'gsc-keyword' AND `checked` = 0 GROUP BY `keywords` ORDER BY `{$auto_sort_basis}` DESC LIMIT {$count}");
            }else{
                unset($post_data[$key]);
                continue;
            }

            // if there's no keyword data, remove this item from the post data and continue
            if(empty($keyword_data)){
                unset($post_data[$key]);
                continue;
            }

            // extract the top keywords so we're sure we don't get duplicates
            $keywords = array();
            foreach($keyword_data as $d){
                if(!isset($keywords[$d->keywords])){
                    $keywords[$d->keywords] = $d->keyword_index;
                }

                // exit if we've found our keywords
                if((count($keywords) + $active_gsc) >= $autocheck_limit){
                    break;
                }
            }

            if(!empty($keywords)){
                $ids = implode(', ', array_values($keywords));
                $wpdb->query("UPDATE {$target_keyword_table} SET `auto_checked` = 1 WHERE `keyword_index` IN ($ids)");
            }

            unset($post_data[$key]);

        }

        // if there are still posts to process
        if(!empty($post_data)){
            // update the post data transient
            set_transient('wpil_autocheck_post_data', $post_data, 5 * MINUTE_IN_SECONDS);
        }else{
            // delete the post data transient
            delete_transient('wpil_autocheck_post_data');
            // and move on to the next leg of the journey
            $data['state'] = self::determine_processing_stage('gsc_keyword_tag');
        }

        return $data;
    }

    /**
     * Gets all the post ids that we have gsc keywords for.
     * @return array
     **/
    public static function get_autocheck_gsc_posts(){
        global $wpdb;
        $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        $post_data = get_transient('wpil_autocheck_post_data');
        if(empty($post_data)){
            $post_data = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$target_keyword_table} WHERE `keyword_type` = 'gsc-keyword' GROUP BY `post_id`,`post_type`");
            if(!empty($post_data)){
                set_transient('wpil_autocheck_post_data', $post_data, 5 * MINUTE_IN_SECONDS);
            }else{
                set_transient('wpil_autocheck_post_data', 'no-ids', 5 * MINUTE_IN_SECONDS);
            }
        }elseif('no-ids' === $post_data){
            return array();
        }

        return $post_data;
    }

    /**
     * Processes the site's yoast data to insert it in the Target Keyword report
     **/
    public static function process_yoast_data($data = array(), $start = 0){

        // get the ids of the posts that we're going to process
        $keyword_data = self::getYoastPostData();

        // if there are no Yoast keywords, move to the next stage of processing
        if(empty($keyword_data['posts']) && empty($keyword_data['terms'])){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage($data['state']);

            delete_transient('wpil_target_keyword_yoast_ids');
            return $data;
        }

        $save_count = 0;
        $save_data = array();
        if(!empty($keyword_data['posts'])){
            foreach($keyword_data['posts'] as $index => $dat){
                // exit if we've hit the time limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['posts'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'post',
                    'keyword_type'  => 'yoast-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['posts'][$index]);
            }
        }elseif(!empty($keyword_data['terms'])){
            foreach($keyword_data['terms'] as $index => $dat){
                // exit if we've hit the time limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['terms'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'term',
                    'keyword_type'  => 'yoast-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['terms'][$index]);
            }
        }

        if(!empty($save_data)){
            self::save_target_keyword_data($save_data);
        }else{
            // move on to the next type of keywords to process
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_yoast_ids');
            return $data;
        }

        set_transient('wpil_target_keyword_yoast_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Gets all post and term data containing Yoast focus keywords
     **/
    public static function getYoastPostData(){
        global $wpdb;

        $keyword_data = get_transient('wpil_target_keyword_yoast_ids');
        if(empty($keyword_data)){
            $keyword_data = array('posts' => false, 'terms' => false);

            // get the post ids
            $post_data = $wpdb->get_results("SELECT `post_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `meta_key` = '_yoast_wpseo_focuskw'");

            if(!empty($keyword_data)){
                $kw_data = array();
                foreach($post_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['posts'] = $kw_data;
            }

            // get the term ids
            $taxonomy_data = get_option('wpseo_taxonomy_meta', array());
            if(!empty($taxonomy_data)){
                $keyword_data['terms'] = array();
                foreach($taxonomy_data as $term_data){
                    foreach($term_data as $cat_id => $dat){
                        if(isset($dat['wpseo_focuskw'])){
                            $kw_data = array();
                            $words = explode(',', $dat['wpseo_focuskw']);
                            foreach($words as $word){
                                $kw_data[] = (object) array('id' => $cat_id, 'keyword' => $word);
                            }
                            $keyword_data['terms'] = array_merge($keyword_data['terms'], $kw_data);
                        }
                        if(isset($dat['wpseo_focuskeywords']) && !empty($dat['wpseo_focuskeywords'])){
                            $focuskeywords = json_decode($dat['wpseo_focuskeywords']);
                            foreach($focuskeywords as $kword){
                                if(empty($kword) || !isset($kword->keyword) || empty($kword->keyword)){
                                    continue;
                                }
                                $kw_data = array();
                                $words = explode(',', $kword->keyword);
                                foreach($words as $word){
                                    $kw_data[] = (object) array('id' => $cat_id, 'keyword' => $word);
                                }

                                $keyword_data['terms'] = array_merge($keyword_data['terms'], $kw_data);
                            }
                        }
                        if(isset($dat['wpseo_keywordsynonyms']) && !empty($dat['wpseo_keywordsynonyms'])){
                            $synonymkeywords = json_decode($dat['wpseo_keywordsynonyms']);
                            foreach($synonymkeywords as $kword){
                                if(empty($kword)){
                                    continue;
                                }
                                $kw_data = array();
                                $words = explode(',', $kword);
                                foreach($words as $word){
                                    $kw_data[] = (object) array('id' => $cat_id, 'keyword' => $word);
                                }

                                $keyword_data['terms'] = array_merge($keyword_data['terms'], $kw_data);
                            }
                        }
                    }
                }
            }

            if(!empty($keyword_data['posts']) || !empty($keyword_data['terms'])){
                set_transient('wpil_target_keyword_yoast_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);
            }
        }else{
            $keyword_data = unserialize(gzinflate(base64_decode($keyword_data)));
        }

        return $keyword_data;
    }

    /**
     * Gets the Yoast keyword data for the given post.
     * At the moment we only pull in the focus keywords.
     **/
    public static function get_yoast_post_keywords_by_id($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $keyword_data = array();

        if(empty($post_id)){
            return $keyword_data;
        }

        if($post_type === 'post'){
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = '_yoast_wpseo_focuskw'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    if(empty($word)){
                        continue;
                    }
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }else{
            // get the term ids
            $taxonomy_data = get_option('wpseo_taxonomy_meta', array());
            if(!empty($taxonomy_data)){
                foreach($taxonomy_data as $term_data){
                    foreach($term_data as $cat_id => $dat){
                        if($cat_id == $post_id){
                            if(isset($dat['wpseo_focuskw'])){
                                $words = explode(',', $dat['wpseo_focuskw']);
                                foreach($words as $word){
                                    $kw = (object) array('keyword' => $word);
                                    $keyword_data[] = $kw;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $keyword_data;
    }

    /**
     * Processes the site's rank math data to insert it in the Target Keyword report
     **/
    public static function process_rank_math_data($data = array(), $start = 0){

        // get the ids of the posts that we're going to process
        $keyword_data = self::get_rank_math_post_data();

        // if there are no Rank Math keywords
        if(empty($keyword_data['posts']) && empty($keyword_data['terms'])){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_rank_math_ids');
            return $data;
        }

        $save_count = 0;
        $save_data = array();
        if(!empty($keyword_data['posts'])){
            foreach($keyword_data['posts'] as $index => $dat){
                // exit if we've hit the time limit or max batch size
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['posts'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'post',
                    'keyword_type'  => 'rank-math-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['posts'][$index]);
            }
        }elseif(!empty($keyword_data['terms'])){
            foreach($keyword_data['terms'] as $index => $dat){
                // exit if we've hit the time or processing limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['terms'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'term',
                    'keyword_type'  => 'rank-math-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['terms'][$index]);
            }
        }

        if(!empty($save_data)){
            self::save_target_keyword_data($save_data);
        }else{
            // move on to the next type of keywords to process
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_rank_math_ids');
            return $data;
        }

        set_transient('wpil_target_keyword_rank_math_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Gets all post and term data containing Rank Math focus keywords
     **/
    public static function get_rank_math_post_data(){
        global $wpdb;

        $keyword_data = get_transient('wpil_target_keyword_rank_math_ids');
        if(empty($keyword_data)){
            $keyword_data = array('posts' => false, 'terms' => false);

            // get the post ids
            $post_data = $wpdb->get_results("SELECT `post_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `meta_key` = 'rank_math_focus_keyword'");

            if(!empty($post_data)){
                $kw_data = array();
                foreach($post_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['posts'] = $kw_data;
            }

            // get the term ids
            $term_data = $wpdb->get_results("SELECT `term_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->termmeta} WHERE `meta_key` = 'rank_math_focus_keyword'");

            if(!empty($term_data)){
                $kw_data = array();
                foreach($term_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['terms'] = $kw_data;
            }

            if(!empty($keyword_data['posts']) || !empty($keyword_data['terms'])){
                set_transient('wpil_target_keyword_rank_math_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);
            }
        }else{
            $keyword_data = unserialize(gzinflate(base64_decode($keyword_data)));
        }

        return $keyword_data;
    }

    public static function get_rank_math_post_keywords_by_id($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $keyword_data = array();

        if(empty($post_id)){
            return $keyword_data;
        }

        if($post_type === 'post'){
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = 'rank_math_focus_keyword'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    if(empty($word)){
                        continue;
                    }
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }else{
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->termmeta} WHERE `term_id` = %d AND `meta_key` = 'rank_math_focus_keyword'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }

        return $keyword_data;
    }

    /**
     * Processes the site's AIOSEO data to insert it in the Target Keyword report
     **/
    public static function process_aioseo_data($data = array(), $start = 0){

        // get the ids of the posts that we're going to process
        $keyword_data = self::get_aioseo_post_data();

        // if there are no AIOSEO keywords
        if(empty($keyword_data['posts']) && empty($keyword_data['terms'])){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_aioseo_ids');
            return $data;
        }

        $save_count = 0;
        $save_data = array();
        if(!empty($keyword_data['posts'])){
            foreach($keyword_data['posts'] as $index => $dat){
                // exit if we've hit the time limit or max batch size
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['posts'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'post',
                    'keyword_type'  => 'aioseo-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['posts'][$index]);
            }
        }elseif(!empty($keyword_data['terms'])){
            foreach($keyword_data['terms'] as $index => $dat){
                // exit if we've hit the time or processing limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['terms'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'term',
                    'keyword_type'  => 'aioseo-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['terms'][$index]);
            }
        }

        if(!empty($save_data)){
            self::save_target_keyword_data($save_data);
        }else{
            // move on to the next type of keywords to process
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_aioseo_ids');
            return $data;
        }

        set_transient('wpil_target_keyword_aioseo_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Gets all post and term data containing AIOSEO keywords
     **/
    public static function get_aioseo_post_data(){
        global $wpdb;

        $keyword_data = get_transient('wpil_target_keyword_aioseo_ids');
        if(empty($keyword_data)){
            $keyword_data = array('posts' => false, 'terms' => false);

            // get the post ids
            $post_data = $wpdb->get_results("SELECT `post_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `meta_key` = '_aioseop_keywords'");

            if(!empty($post_data)){
                $kw_data = array();
                foreach($post_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['posts'] = $kw_data;
            }

            // get the term ids
            $term_data = $wpdb->get_results("SELECT `term_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->termmeta} WHERE `meta_key` = '_aioseop_keywords'");

            if(!empty($term_data)){
                $kw_data = array();
                foreach($term_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['terms'] = $kw_data;
            }

            if(!empty($keyword_data['posts']) || !empty($keyword_data['terms'])){
                set_transient('wpil_target_keyword_aioseo_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);
            }
        }else{
            $keyword_data = unserialize(gzinflate(base64_decode($keyword_data)));
        }

        return $keyword_data;
    }

    /**
     * 
     **/
    public static function get_aioseo_post_keywords_by_id($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $keyword_data = array();

        if(empty($post_id)){
            return $keyword_data;
        }

        if($post_type === 'post'){
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = '_aioseop_keywords'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    if(empty($word)){
                        continue;
                    }
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }else{
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->termmeta} WHERE `term_id` = %d AND `meta_key` = '_aioseop_keywords'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }

        return $keyword_data;
    }


    /**
     * Processes the site's SEOPress data to insert it in the Target Keyword report
     **/
    public static function process_seopress_data($data = array(), $start = 0){

        // get the ids of the posts that we're going to process
        $keyword_data = self::get_seopress_post_data();

        // if there are no SEOPress keywords
        if(empty($keyword_data['posts']) && empty($keyword_data['terms'])){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_seopress_ids');
            return $data;
        }

        $save_count = 0;
        $save_data = array();
        if(!empty($keyword_data['posts'])){
            foreach($keyword_data['posts'] as $index => $dat){
                // exit if we've hit the time limit or max batch size
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['posts'][$index]);
                    continue;
                }


                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'post',
                    'keyword_type'  => 'seopress-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['posts'][$index]);
            }
        }elseif(!empty($keyword_data['terms'])){
            foreach($keyword_data['terms'] as $index => $dat){
                // exit if we've hit the time or processing limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['terms'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'term',
                    'keyword_type'  => 'seopress-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['terms'][$index]);
            }
        }

        if(!empty($save_data)){
            self::save_target_keyword_data($save_data);
        }else{
            // move on to the next type of keywords to process
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_seopress_ids');
            return $data;
        }

        set_transient('wpil_target_keyword_seopress_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Gets all post and term data containing SEOPress keywords
     **/
    public static function get_seopress_post_data(){
        global $wpdb;

        $keyword_data = get_transient('wpil_target_keyword_seopress_ids');
        if(empty($keyword_data)){
            $keyword_data = array('posts' => false, 'terms' => false);

            // get the post ids
            $post_data = $wpdb->get_results("SELECT `post_id` AS 'id', `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `meta_key` = '_seopress_analysis_target_kw'");

            if(!empty($post_data)){
                $kw_data = array();
                foreach($post_data as $dat){
                    $words = explode(',', $dat->keyword);
                    foreach($words as $word){
                        $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $word);
                    }
                }
                $keyword_data['posts'] = $kw_data;
            }

            // SEOPress doesn't support target keywords for terms, so well just add a placeholder for compatibility
            $keyword_data['terms'] = array();

            if(!empty($keyword_data['posts']) || !empty($keyword_data['terms'])){
                set_transient('wpil_target_keyword_seopress_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);
            }
        }else{
            $keyword_data = unserialize(gzinflate(base64_decode($keyword_data)));
        }

        return $keyword_data;
    }

    /**
     * 
     **/
    public static function get_seopress_post_keywords_by_id($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $keyword_data = array();

        if(empty($post_id)){
            return $keyword_data;
        }

        // SEOPress only does posts
        if($post_type === 'post'){
            // get the target keyword
            $results = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` AS 'keyword' FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = '_seopress_analysis_target_kw'", $post_id));
            foreach($results as $result){
                $words = explode(',', $result->keyword);
                foreach($words as $word){
                    if(empty($word)){
                        continue;
                    }
                    $kw = (object) array('keyword' => $word);
                    $keyword_data[] = $kw;
                }
            }
        }

        return $keyword_data;
    }

    /**
     * Processes the site's post keyword data to insert it in the Target Keyword report
     **/
    public static function process_post_content_keywords_data($data = array(), $start = 0){

        // get the ids of the posts that we're going to process
        $keyword_data = self::get_post_content_keywords_data();

        // if there are no post keywords
        if(empty($keyword_data['posts']) && empty($keyword_data['terms'])){
            // move on to processing the next type of keywords
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_post_content_ids');
            return $data;
        }

        $save_count = 0;
        $save_data = array();
        if(!empty($keyword_data['posts'])){
            foreach($keyword_data['posts'] as $index => $dat){
                // exit if we've hit the time limit or max batch size
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['posts'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'post',
                    'keyword_type'  => 'post-content-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['posts'][$index]);
            }
        }elseif(!empty($keyword_data['terms'])){
            foreach($keyword_data['terms'] as $index => $dat){
                // exit if we've hit the time or processing limit
                if(microtime(true) - $start > 30 || $save_count > 800){
                    break;
                }

                // if there's no keyword text
                if(empty($dat->keyword)){
                    // remove the keyword form the list and continue to the next item
                    unset($keyword_data['terms'][$index]);
                    continue;
                }

                $save_data[] = array(
                    'post_id'       => $dat->id,
                    'post_type'     => 'term',
                    'keyword_type'  => 'post-content-keyword',
                    'keywords'      => $dat->keyword,
                    'checked'       => 1,
                    'impressions'   => 0,
                    'clicks'        => 0
                );
                $save_count += 1;

                unset($keyword_data['terms'][$index]);
            }
        }

        if(!empty($save_data)){
            self::save_target_keyword_data($save_data);
        }else{
            // move on to the next type of keywords to process
            $data['state'] = self::determine_processing_stage($data['state']);
            delete_transient('wpil_target_keyword_post_content_ids');
            return $data;
        }

        set_transient('wpil_target_keyword_post_content_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Gets keywords from post and term body content
     **/
    public static function get_post_content_keywords_data(){
        global $wpdb;

        $keyword_data = get_transient('wpil_target_keyword_post_content_ids');
        if(empty($keyword_data)){
            $keyword_data = array('posts' => false, 'terms' => false);

            $post_types = Wpil_Settings::getPostTypes();
            $query_types = "";
            if (!empty($post_types)) {
                $query_types = " AND post_type IN ('" . implode("', '", $post_types) . "') ";
            }

            $statuses_query = Wpil_Query::postStatuses();
            $kw_data = array();

            // get the post ids & titles
            $post_data = $wpdb->get_results("SELECT `ID` AS 'id', `post_title` AS 'keyword' FROM {$wpdb->posts} WHERE 1=1 $statuses_query $query_types");

            if(!empty($post_data)){
                $kw_data = array();
                foreach($post_data as $dat){
                    $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $dat->keyword);
                }
                $keyword_data['posts'] = $kw_data;
            }

            // get the term ids
            $term_data = $wpdb->get_results("SELECT `term_id` AS 'id', `name` AS 'keyword' FROM {$wpdb->terms}");

            if(!empty($term_data)){
                $kw_data = array();
                foreach($term_data as $dat){
                    $kw_data[] = (object) array('id' => $dat->id, 'keyword' => $dat->keyword);
                }
                $keyword_data['terms'] = $kw_data;
            }

            if(!empty($keyword_data['posts']) || !empty($keyword_data['terms'])){
                set_transient('wpil_target_keyword_post_content_ids', base64_encode(gzdeflate(serialize($keyword_data))), 5 * MINUTE_IN_SECONDS);
            }
        }else{
            $keyword_data = unserialize(gzinflate(base64_decode($keyword_data)));
        }

        return $keyword_data;
    }

    /**
     * Gets the post content keywords.
     * Currently pulls keywords from:
     *** The title
     *** The slug
     * 
     **/
    public static function get_post_content_keywords_by_id($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $keyword_data = array();

        if(empty($post_id)){
            return $keyword_data;
        }

        if($post_type === 'post'){
            $query = $wpdb->prepare("SELECT `post_title` AS 'title', `post_name` AS 'slug' FROM {$wpdb->posts} WHERE `ID` = %d", $post_id);
        }else{
            $query = $wpdb->prepare("SELECT `name` AS 'title', `slug` AS 'slug' FROM {$wpdb->terms} WHERE `term_id` = %d", $post_id);
        }

        // get the target keyword
        $results = $wpdb->get_results($query);

        foreach($results as $result){
            $has_title = (isset($result->title) && !empty($result->title));
            $has_slug = (isset($result->slug) && !empty($result->slug));

            if($has_title){
                $kw = (object) array('keyword' => $result->title);
                $keyword_data[] = $kw;
            }

            // if there's a slug
            if($has_slug){
                // process it a little to make into an actual keyword string
                
                // url decode it and trim
                $slug = trim(urldecode($result->slug));
                if(!empty($slug)){
                    // and replace any hyphens with spaces
                    $slug = trim(mb_ereg_replace(preg_quote('-'), ' ', $slug));
                    if(!empty($slug)){
                        // if we've made it this far, check to see if the 
                        $slug = Wpil_Word::strtolower($slug);

                        // if there is a title
                        if($has_title){
                            // check to see if the normalized title is the same as the normalized slug
                            if(trim(Wpil_Word::strtolower($slug)) !== trim(Wpil_Word::strtolower($result->title))){
                                // if it's not, add the slug words to the keywords
                                $kw = (object) array('keyword' => $slug);
                                $keyword_data[] = $kw;
                            }
                        }else{
                            // if there is no title, add the slug keywords
                            $kw = (object) array('keyword' => $slug);
                            $keyword_data[] = $kw;
                        }
                    }
                }

            }
        }

        return $keyword_data;
    }

    /**
     * Processes the custom keywords out of the custom table and into the target keyword table.
     * Not used since the target table is now handling the custom keywords. But leaving because it might be helpful when adding other keyword sources
     **//*
    public static function process_custom_keywords($data, $start){
        $custom_offset = (isset($data['custom_offset'])) ? $data['custom_offset']: 0;
        $limit = 500;
        while(true){
            if(microtime(true) - $start > 30){
                $data['custom_offset'] = $custom_offset;
                return $data;
            }

            $keyword_data = self::get_custom_keyword_data_by_offset($custom_offset, $limit);

            if(empty($keyword_data)){
                $data['state'] = 'complete';
                return $data;
            }

            $save_data = array();
            foreach($keyword_data as $data){
                $save_data[] = array(
                    'post_id'       => $data->post_id, 
                    'post_type'     => $data->post_type, 
                    'keyword_type'  => 'custom', 
                    'keywords'      => $data->keywords, 
                    'checked'       => $data->checked
                );
            }

            self::save_target_keyword_data($save_data);
            $custom_offset += $limit;
        }
    }
*/
    /**
     * Saves pre-formatted keyword data to the target data table.
     **/
    public static function save_target_keyword_data($rows){
        global $wpdb;
        $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

        // make sure the target keyword table exists
        self::prepareTable();

        $insert_query = "INSERT INTO {$target_keyword_table} (post_id, post_type, keyword_type, keywords, checked, impressions, clicks, ctr, position, save_date) VALUES ";

        $place_holders = array();
        $insert_rows = array();
        $inserted_list = array();
        $current_date = date('Y-m-d H:i:s', time()); // set the save date for right now
        foreach($rows as $row){
            // if the keyword has been saved already, skipp to the next item
            if(isset($inserted_list[$row['keywords']])){
                // Todo remove this section if no customers report duplicate keywords
//                continue;
            }else{
                // if the keyword hasn't been saved yet, note it in the keyword list
                $inserted_list[$row['keywords']] = true;
            }

            $place_holders[] = "('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%s')";
            $impressions = isset($row['impressions']) ? $row['impressions']: 0;
            $clicks = isset($row['clicks']) ? $row['clicks']: 0;
            $ctr = (isset($row['ctr']) || array_key_exists('ctr', $row) ) ? $row['ctr']: 0;
            $position = (isset($row['position']) || array_key_exists('position', $row)) ? $row['position']: 0;

            array_push(
                $insert_rows,
                $row['post_id'],
                $row['post_type'],
                $row['keyword_type'],
                $row['keywords'],
                $row['checked'],
                $impressions,
                $clicks,
                $ctr,
                $position,
                $current_date
            );
        }

        $insert_query .= implode(', ', $place_holders);
        $insert_query = $wpdb->prepare($insert_query, $insert_rows);
        $inserted = $wpdb->query($insert_query);

        return $inserted;
    }

    /**
     * Removes any old gsc keyword data for the current post.
     * This is meant to be run in connection with the data saver, so it just deletes all GSC data that's older than 1 day for the current post.
     * 
     * @param int $post_id the id of the post that we're removing the data from
     **/
    public static function remove_old_gsc_data($post){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_target_keyword_data';
        
        // exit if there's no post id
        if(empty($post)){
            return;
        }

        $save_date = date('Y-m-d H:i:s', (time() - DAY_IN_SECONDS));

        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$data_table} WHERE `post_id` = %d AND `post_type` = %s AND `keyword_type` = 'gsc-keyword' AND `checked` != 1 AND `save_date` < '{$save_date}'", $post->id, $post->type));

        return !empty($deleted);
    }

    /**
     * Updates any checked GSC keywords with data from new queries.
     * This is so the clicks, impressions, position and CTR is up to date.
     **/
    public static function update_checked_gsc_keywords($post){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_target_keyword_data';

        // get all the post's keywords
        $keywords = self::get_post_keywords_by_type($post->id, $post->type, 'gsc-keyword', false);

        // count how many times all the keywords show up in the db
        $index = array();
        foreach($keywords as $keyword){
            $index[$keyword->keywords][] = $keyword;
        }

        // filter out all the keywords that only show up once
        $index2 = array();
        foreach($index as $key => $dat){
            // if there's a second index, save the keywords
            if(isset($dat[1])){
                $index2[$key] = $dat;
            }
        }

        // unset the first index
        unset($index);

        $check_list = array();
        $delete_list = array();

        // go over all the keywords
        foreach($index2 as $dat){
            // sort them by insertion date
            usort($dat, function($a, $b){
                return $a->keyword_index < $b->keyword_index;
            });

            // get the newest
            $newest = $dat[0];

            // find out if any of the keywords are checked and add the extra keywords to the delete list
            $checked = false;
            foreach($dat as $key => $keyword){
                if(!empty($keyword->checked)){
                    $checked = true;
                }

                if($key > 0){
                    $delete_list[] = $keyword->keyword_index;
                }
            }

            // if one of the keywords is checked, mark the first item as one to update
            if($checked){
                $check_list[] = $newest->keyword_index;
            }
        }

        // make sure the latest keywords are checked
        if(!empty($check_list)){
            $check_list = implode(',', $check_list);
            $update_query = "UPDATE {$data_table} SET `checked` = 1 WHERE `keyword_index` IN ($check_list)";
            $wpdb->query($update_query);
        
            // make sure all the other keywords are unchecked
            if(!empty($delete_list)){
                $delete_list = implode(',', $delete_list);
                $update_query = "UPDATE {$data_table} SET `checked` = 0 WHERE `keyword_index` IN ($delete_list)";
                $wpdb->query($update_query);
            }
        }

        // and remove the old keywords
        // todo evaluate later if Google sends duplicate keywords. I'm seeing a lot of duplicates in the returned data, but I can't tell if this is legit data or google is just throwning duplicates in.
        /*if(!empty($delete_list)){
            $delete_list = '(' . implode(', ', $delete_list) . ')';
            $wpdb->query("DELETE FROM {$data_table} WHERE `keyword_index` IN $delete_list AND `keyword_type` = 'gsc-keyword'");
        }*/
    }


    /*
    Not used since the target table is now handling the custom keywords. But leaving because it might be helpful when adding other keyword sources
    public static function get_custom_keyword_data_by_offset($custom_offset = 0, $limit = 500){
        global $wpdb;
        $custom_keyword_table = $wpdb->prefix . 'wpil_custom_keyword_data';

        $data = $wpdb->query($wpdb->prepare("SELECT `post_id`, `post_type`, `keywords`, `checked` FROM {$custom_keyword_table} LIMIT %d OFFSET %d", $limit, $custom_offset));
    
        if(!empty($data)){
            return $data;
        }else{
            return array();
        }
    }*/

    /**
     * Checks to see if there's GSC keywords stored in the Target Keywords table
     *
     * @return bool
     **/
    public static function has_gsc_keywords_stored(){
        global $wpdb;

        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        $results = $wpdb->get_results("SELECT `keyword_index` FROM {$target_keywords} WHERE `keyword_type` = 'gsc-keyword' LIMIT 1");

        return !empty($results) ? true: false;
    }

    /**
     * Gets keywords from the target table by post ids.
     * Can also accept a single post id.
     * By default limits the GSC keywords to the top 20 sorted by impressesions + any that the user has checked that falls outside that list.
     * If not set to limit GSC, then all GSC keywords are returned.
     * 
     **/
    public static function get_keywords_by_post_ids($ids = array(), $type = 'post', $limit_gsc = true){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        if(empty($ids)){
            return array();
        }

        if(is_int($ids) || is_string($ids)){
            $ids = array($ids);
        }

        // get the keyword sources
        $active_sources = self::get_active_keyword_sources();
        $keyword_sources = array();
        $gsc_later = false;
        foreach($active_sources as $source){
            // if we're limiting the gsc keywords
            if($limit_gsc && $source === 'gsc'){
                // set up processing of the keywords later and skip to the next source
                $gsc_later = true;
                continue;
            }

            $keyword_sources[] = $source . '-keyword';
        }

        $keyword_sources = '(\'' . implode('\', \'', $keyword_sources) . '\')';

        $ids = array_map(function($id){ return (int)$id; }, $ids);
        $ids = '(' . implode(', ', $ids) . ')';

        $type = ('post' === $type) ? 'post': 'term';

        $keyword_data = $wpdb->get_results("SELECT * FROM {$target_keywords} WHERE `post_id` IN $ids AND `post_type` = '{$type}' AND `keyword_type` IN $keyword_sources");

        if($gsc_later){
            $gsc_data1 = $wpdb->get_results("SELECT * FROM {$target_keywords} WHERE `post_id` IN $ids AND `post_type` = '{$type}' AND `keyword_type` = 'gsc-keyword' ORDER BY `impressions` DESC LIMIT 100");

            // if there are gsc keywords
            if(!empty($gsc_data1)){
                // get the indexes of the selected keywords
                $indexes = array();
                foreach($gsc_data1 as $key => $dat){
                    $indexes[] = $dat->keyword_index;
                }
                $indexes = '(' . implode(', ', $indexes) . ')';
                $autochecked = (!empty(get_option('wpil_autotag_gsc_keywords', false))) ? "OR `auto_checked` = 1": '';
                $gsc_data2 = $wpdb->get_results("SELECT * FROM {$target_keywords} WHERE `post_id` IN $ids AND `post_type` = '{$type}' AND `keyword_index` NOT IN $indexes AND `keyword_type` = 'gsc-keyword' AND (`checked` = 1 {$autochecked})");

                // if there are checked keywords that didn't make the cut
                if(!empty($gsc_data2)){
                    // add them to the gsc data
                    $gsc_data1 = array_merge($gsc_data1, $gsc_data2);
                }

                $gsc_data1 = self::filter_duplicate_gsc_keywords($gsc_data1);

                $keyword_data = array_merge($keyword_data, $gsc_data1);
            }
        }


        return $keyword_data;
    }

    /**
     * Gets keywords from the target table by post ids.
     * Can also accept a single post id.
     * Returns all active keywords for the given post(s)
     * 
     **/
    public static function get_active_keywords_by_post_ids($ids = array(), $type = 'post'){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        if(empty($ids)){
            return array();
        }

        if(is_int($ids) || is_string($ids)){
            $ids = array($ids);
        }

        // get the keyword sources
        $active_sources = self::get_active_keyword_sources();
        $keyword_sources = array();
        foreach($active_sources as $source){
            $keyword_sources[] = $source . '-keyword';
        }

        $keyword_sources = '(\'' . implode('\', \'', $keyword_sources) . '\')';

        $ids = array_map(function($id){ return (int)$id; }, $ids);
        $ids = '(' . implode(', ', $ids) . ')';

        $type = ('post' === $type) ? 'post': 'term';

        $autochecked = (!empty(get_option('wpil_autotag_gsc_keywords', false))) ? "OR `auto_checked` = 1": '';
        $keyword_data = $wpdb->get_results("SELECT * FROM {$target_keywords} WHERE `post_id` IN $ids AND `post_type` = '{$type}' AND `keyword_type` IN $keyword_sources AND (`checked` = 1 {$autochecked})");

        return $keyword_data;
    }

    /**
     * Gets all the keywords of a given keyword type for the supplied post.
     *
     * @param int $post_id The id of the post we're getting keywords for.
     * @param string $type The type of keyword that we're pulling.
     *
     * @return array $keyword_data an array of the keywords that have been found.
     **/
    public static function get_post_keywords_by_type($post_id = 0, $post_type = 'post', $type = 'gsc-keyword', $limit_gsc = true){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        if(empty($post_id) || empty($post_type) || empty($type)){
            return array();
        }

        $post_type = ('post' === $post_type) ? 'post': 'term';

        // if this is a gsc keyword query and we're limiting the keywords
        if($type === 'gsc-keyword' && $limit_gsc){
            $keyword_data = $wpdb->get_results($wpdb->prepare("SELECT *, SUM(`impressions`) AS 'impressions', SUM(`clicks`) AS 'clicks' FROM {$target_keywords} WHERE `post_id` = %d AND `post_type` = %s AND `keyword_type` = %s GROUP BY `keywords` ORDER BY `impressions` DESC LIMIT 100", $post_id, $post_type, $type));

            // if there are gsc keywords
            if(!empty($keyword_data)){
                // get the indexes of the selected keywords
                $indexes = array();
                foreach($keyword_data as $key => $dat){
                    $indexes[] = $dat->keyword_index;
                }
                $indexes = '(' . implode(', ', $indexes) . ')';
                $autochecked = (!empty(get_option('wpil_autotag_gsc_keywords', false))) ? "OR `auto_checked` = 1": '';
                $gsc_data2 = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$target_keywords} WHERE `post_id` = %d AND `post_type` = %s AND `keyword_index` NOT IN $indexes AND `keyword_type` = 'gsc-keyword' AND (`checked` = 1 {$autochecked})", $post_id, $post_type));

                // if there are checked keywords that didn't make the cut
                if(!empty($gsc_data2)){
                    // add them to the gsc data
                    $keyword_data = array_merge($keyword_data, $gsc_data2);
                }
            }
        }else{
            $keyword_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$target_keywords} WHERE `post_id` = %d AND `post_type` = %s AND `keyword_type` = %s", $post_id, $post_type, $type));
        }

        return $keyword_data;

    }

    /**
     * Gets the unique gsc keywords from a selection of keywords
     **/
    public static function filter_duplicate_gsc_keywords($keywords = array()){
        if(empty($keywords)){
            return $keywords;
        }

        // if we're not showing keywords that are obviated by smaller ones
        $options = get_user_meta(get_current_user_id(), 'target_keyword_options', true);
        if(!empty($options['remove_obviated_keywords']) && $options['remove_obviated_keywords'] != 'off'){
            // get all the checked keywords for the current post
            $checked = self::get_active_keywords_by_post_ids($keywords[0]->post_id, $keywords[0]->post_type);

            foreach($keywords as $k => $keyword){
                foreach($checked as $c){
                    if((empty($keyword->checked) && empty($keyword->auto_checked)) && false !== strpos($keyword->keywords, $c->keywords)){
                        unset($keywords[$k]);
                    }
                }
            }
        }

        // create a list of the available keywords
        $keyword_list = array();

        foreach($keywords as $keyword){
            if(!isset($keyword_list[$keyword->keywords])){
                $keyword_list[$keyword->keywords] = $keyword;
            }elseif(!empty($keyword->checked) || !empty($keyword->auto_checked)){
                // be sure to add checked keywords to the list
                $keyword_list[$keyword->keywords] = $keyword;
            }
        }

        $keywords = array_slice(array_values($keyword_list), 0, 20);

        return $keywords;
    }

    /**
     * Gets the keyword data for a post based on it's keyword data
     * 
     * @param int $post_id The id of the post we're getting keywords for.
     * @param string $keyword The keyword string that we're using to query for the rest of the keyword data.
     * 
     * @return array $keyword_data an array of the keywords that have been found.
     **/
    public static function get_post_keyword_by_keyword($post_id = 0, $keyword = ''){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';
        
        if(empty($post_id) || empty($keyword)){
            return array();
        }

        $keyword_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$target_keywords} WHERE `post_id` = %d AND `keywords` = %s", $post_id, $keyword));

        return $keyword_data;

    }

    /**
     * Gets multiple keyword data for a post based on supplied keywords 
     *  
     * @param int $post_id The id of the post we're getting keywords for.
     * @param string $keyword The array of keywords that we're using to query for the rest of the keyword data.
     * 
     * @return array $keyword_data an array of the keywords that have been found.
     **/
    public static function get_post_keywords_by_keywords($post_id = 0, $keywords = array(), $keyword_type = false){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        if(empty($post_id) || empty($keywords)){
            return array();
        }

        $keyword_replace = array();
        $query_data = array($post_id);
        foreach($keywords as $keyword){
            $query_data[] = sanitize_text_field($keyword);
            $keyword_replace[] = '\'%s\'';
        }

        $keyword_replace = implode(', ', $keyword_replace);

        $type = '';
        if(!empty($keyword_type)){
            switch ($keyword_type) {
                case 'gsc':
                    $type = 'gsc-keyword';
                    break;
                case 'yoast':
                    $type = 'yoast-keyword';
                    break;
                case 'rank-math':
                    $type = 'rank-math-keyword';
                    break;
                case 'aioseo':
                    $type = 'aioseo-keyword';
                    break;
                case 'seopress':
                    $type = 'seopress-keyword';
                    break;
                case 'post-content':
                    $type = 'post-content-keyword';
                    break;
                case 'custom':
                default:
                    $type = 'custom-keyword';
                    break;
            }

            $type = "AND `keyword_type` = '{$type}'";
        }

        $keyword_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$target_keywords} WHERE `post_id` = %d $type AND `keywords` IN ({$keyword_replace})", $query_data));

        return $keyword_data;

    }

    /**
     * Gets all target keywords from the database.
     * Can be told to ignore posts by supplying post ids and types in separate arrays.
     * The ids and types are paired up, so if you pass [157, 3], ['post', 'term'] to the function,
     * it will ignore keywords for post 157, and term 3.
     * 
     * @param array $ignore_ids The ids of the posts we don't want to get keywords for.
     * @param array $ignore_item_types The types of items that we don't want to get keywords for.
     * 
     * @return array $keyword_data an array of the keywords that have been found.
     **/
    public static function get_all_active_keywords($ignore_ids = array(), $ignore_item_types = array()){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        $ignore = '';
        if(!empty($ignore_ids)){
            $ignore_ids = array_map(function($id){ return (int)$id; }, $ignore_ids);
            $post_ids = array();
            $term_ids = array();
            foreach($ignore_ids as $key => $id){
                if(isset($ignore_item_types[$key]) && 'post' === $ignore_item_types[$key]){
                    $post_ids[] = $id;
                }elseif(isset($ignore_item_types[$key]) && 'term' === $ignore_item_types[$key]){
                    $term_ids[] = $id;
                }
            }

            if(!empty($post_ids)){
                $ignore .= ' AND (`post_type` != \'post\' AND `post_id` NOT IN (' . implode(', ', $post_ids) . '))';
            }

            if(!empty($term_ids)){
                $ignore .= ' AND (`post_type` != \'term\' AND `post_id` NOT IN (' . implode(', ', $term_ids) . '))';
            }
        }

        $autochecked = (!empty(get_option('wpil_autotag_gsc_keywords', false))) ? "OR `auto_checked` = 1": '';
        $keyword_data = $wpdb->get_results("SELECT * FROM {$target_keywords} WHERE (`checked` = 1 $autochecked) {$ignore}");

        if(!empty($keyword_data)){
            return $keyword_data;
        }else{
            return array();
        }
    }

    /**
     * Gets an array of all active keyword texts.
     **/
    public static function get_active_keyword_list($post_id = 0, $post_type = 'post'){

        $keywords = self::get_active_keywords_by_post_ids($post_id, $post_type);

        if(empty($keywords)){
            return array();
        }

        $results = array();
        foreach($keywords as $keyword){
            if($keyword->checked || $keyword->auto_checked){
                $results[] = $keyword->keywords;
            }
        }

        return $results;
    }

    /**
     * Gets an array of all active keywords in a single long string.
     * Used for getting the inbound post suggestions.
     **/
    public static function get_active_keyword_string($post_id = 0, $post_type = 'post'){

        $keywords = self::get_active_keywords_by_post_ids($post_id, $post_type);

        if(empty($keywords)){
            return '';
        }

        $string = '';
        foreach($keywords as $keyword){
            if($keyword->checked || $keyword->auto_checked){
                $string .= ' ' . $keyword->keywords;
            }
        }

        return $string;
    }

    /**
     * Deletes a keyword by its id
     * 
     * @param int $keyword_id. The id of the target keyword to delete.
     * 
     * @return bool Return True on success, False on failure.
     **/
    public static function delete_keyword_by_id($keyword_id = 0){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';
        
        if(empty($keyword_id)){
            return false;
        }

        $deleted = $wpdb->delete($target_keywords, array('keyword_index' => (int)$keyword_id));

        return (bool) $deleted;
    }

    /**
     * Deletes a type of keyword from the given post
     * 
     * @param int $post_id. The id of the post that we're removing the keyword type from.
     * @param string $post_type The type of post object that we're removing the keyword type from (post|term).
     * @param string $keyword_type The type of keyword that we're removing from the post.
     * 
     * @return bool Return True on success, False on failure.
     **/
    public static function delete_keyword_by_type($post_id = 0, $post_type = 'post', $keyword_type = ''){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';
        
        if(empty($post_id) || empty($keyword_type)){
            return false;
        }

        $post_type = ($post_type === 'term') ? 'term': 'post';

        $deleted = $wpdb->delete($target_keywords, array('post_id' => (int)$post_id, 'post_type' => $post_type, 'keyword_type' => $keyword_type));

        return (bool) $deleted;
    }

    /**
     * Creates a list of the active keyword sources
     **/
    public static function get_active_keyword_sources(){
        $selected = Wpil_Settings::getSelectedKeywordSources();
        $available = self::get_available_keyword_sources();

        return array_intersect($available, $selected);
    }

    /**
     * Creates a list of the available keyword sources
     **/
    public static function get_available_keyword_sources(){
        $sources = array('custom'); // There will always be the custom keywords
        $sources[] = 'post-content'; // Post content keywords _should_ also always be available

        // add GSC is if its authenticated
        if(Wpil_Settings::HasGSCCredentials() || Wpil_TargetKeyword::has_gsc_keywords_stored()){
            $sources[] = 'gsc';
        }

        // add Yoast if its active
        if(defined('WPSEO_VERSION')){
            $sources[] = 'yoast';
        }

        // add Rank Math if its active
        if(defined('RANK_MATH_VERSION')){
            $sources[] = 'rank-math';
        }

        // add All In One SEO if its active
        if(defined('AIOSEO_VERSION')){
            $sources[] = 'aioseo';
        }

        // add SEOPress if its active
        if(defined('SEOPRESS_VERSION')){
            $sources[] = 'seopress';
        }

        return $sources;
    }

    /**
     * Creates a list of the supported keyword source names for display
     **/
    public static function get_keyword_name_list(){
        $names = array(
            'custom'        => __('Custom Keywords', 'wpil'),
            'post-content'  => __('Page Content Keywords'),
            'gsc'           => __('GSC Keywords', 'wpil'),
            'yoast'         => __('Yoast Keywords', 'wpil'),
            'rank-math'     => __('Rank Math Keywords', 'wpil'),
            'aioseo'        => __('AIOSEO Keywords', 'wpil'),
            'seopress'      => __('SEOPress Keywords', 'wpil'),
        );

        return $names;
    }

    /**
     * Updates the selected keywords with the user's selection from ajax
     **/
    public static function ajax_target_keyword_selected_update(){
        global $wpdb;
        $target_keywords = $wpdb->prefix . 'wpil_target_keyword_data';

        Wpil_Base::verify_nonce('update-selected-keywords-' . $_POST['post_id']);

        if(!isset($_POST['selected']) || empty($_POST['selected'])){
            wp_send_json(array('error' => array('title' => __('No keywords selected', 'wpil'), 'text' => __('The were no keywords selected for updating.', 'wpil'))));
        }

        $errors = array();
        foreach($_POST['selected'] as $id => $checked){
            if(empty($checked) || 'false' === $checked){
                $update_query = $wpdb->prepare("UPDATE {$target_keywords} SET `checked` = '0', `auto_checked` = '0' WHERE {$target_keywords}.`keyword_index` = %d", $id);
            }else{
                $update_query = $wpdb->prepare("UPDATE {$target_keywords} SET `checked` = '1', `auto_checked` = '0' WHERE {$target_keywords}.`keyword_index` = %d", $id);
            }

            $wpdb->query($update_query); //interestingly, $wpdb only runs one query at a time. So I can't load these into a single string and execute all at once.

            $errors[] = $wpdb->last_error;
        }

        if(empty(array_filter($errors))){
            wp_send_json(array('success' => array('title' => 'Keywords updated!', 'text' => 'The keywords have been succcessfully updated!')));
        }else{
            $errored    = count(array_filter($errors));
            $total      = count($errors);
            wp_send_json(array('error' => array('title' => 'Update Error', 'text' => $errored . ' Out of ' . $total . ' keywords were not updated.')));
        }
    }

    /**
     * Creates custom target keywords for the given posts on ajax call.
     **/
    public static function ajax_create_custom_target_keyword(){
        if(!isset($_POST['post_id']) || empty($_POST['post_id'])){
            wp_send_json(array('error' => array('title' => __('Post id missing', 'wpil'), 'text' => __('The id of the post was missing. Please try reloading the page and trying again.', 'wpil'))));
        }

        Wpil_Base::verify_nonce('create-target-keywords-' . $_POST['post_id']);

        if(!isset($_POST['keywords']) || empty($_POST['keywords'])){
            wp_send_json(array('error' => array('title' => __('No keyword', 'wpil'), 'text' => __('The were no keywords provided.', 'wpil'))));
        }

        $rows = array();
        $keywords = array();
        foreach($_POST['keywords'] as $k_dat){
            $keyword_data = explode(',', $k_dat);
            foreach($keyword_data as $keyword){
                $keyword = stripslashes(trim(sanitize_text_field($keyword)));
                $rows[] = array(
                    'post_id' => (int)$_POST['post_id'], 
                    'post_type' => ($_POST['post_type'] === 'post') ? 'post': 'term', 
                    'keyword_type' => 'custom-keyword', 
                    'keywords' => $keyword, 
                    'checked' => 1
                );
                $keywords[] = $keyword;
            }
        }

        $inserted = self::save_target_keyword_data($rows);

        if(!empty($inserted)){
            $keyword_data = self::get_post_keywords_by_keywords((int)$_POST['post_id'], $keywords, 'custom');

            $data = array();
            foreach($keyword_data as $keywrd){
                $dat = array();
                // create the new row we'll show the user
                $dat['reportRow'] = 
                '<li id="target-keyword-' . $keywrd->keyword_index . '">
                    <div style="display: inline-block;"><label><span>' . $keywrd->keywords . '</span></label></div>
                        <i class="wpil_target_keyword_delete dashicons dashicons-no-alt" data-keyword-id="' . $keywrd->keyword_index . '" data-keyword-type="custom-keyword" data-nonce="' . wp_create_nonce(get_current_user_id() . 'delete-target-keywords-' . $keywrd->keyword_index) . '"></i>
                </li>';

                $dat['suggestionRow'] = '
                <li id="keyword-custom-' . $keywrd->keyword_index . '" class="custom-keyword">
                    <label class="selectit">
                        <input type="checkbox" class="keyword-' . $keywrd->keyword_index . '" checked="checked" data-keyword-id="' . $keywrd->keyword_index . '" value="' . $keywrd->keyword_index . '">
                        ' . $keywrd->keywords . '
                        <i class="wpil_target_keyword_delete dashicons dashicons-no-alt" data-keyword-id="' . $keywrd->keyword_index . '" data-keyword-type="custom-keyword" data-keyword-type="custom-keyword" data-nonce="' . wp_create_nonce(get_current_user_id() . 'delete-target-keywords-' . $keywrd->keyword_index) . '"></i>
                    </label>
                </li>';

                $dat['keywordId'] = $keywrd->keyword_index;

                $dat['keyword'] = $keywrd->keywords;

                $data[] = $dat;
            }

            wp_send_json(array('success' => array('title' => __('Keyword created!', 'wpil'), 'text' => __('The keyword has been succcessfully created!', 'wpil'), 'data' => $data)));
        }else{
            wp_send_json(array('error' => array('title' => __('Error', 'wpil'), 'text' => __('The keyword could not be created.', 'wpil'))));
        }
    }

    /**
     * Deletes a target keyword by index on ajax call
     **/
    public static function ajax_delete_custom_target_keyword(){
        Wpil_Base::verify_nonce('delete-target-keywords-' . $_POST['keyword_id']);

        $deleted = self::delete_keyword_by_id($_POST['keyword_id']);

        if($deleted){
            wp_send_json(array('success' => array('title' => __('Keyword Deleted!', 'wpil'), 'text' => __('The keyword has been successfully deleted', 'wpil'))));
        }else{
            wp_send_json(array('error' => array('title' => __('Delete Error', 'wpil'), 'text' => __('The keyword couldn\'t be deleted, please reload the page and try again.', 'wpil'))));
        }
    }

    /**
     * Saves the state of the target keyword box visibility on the inbound suggestions page
     **/
    public static function ajax_save_inbound_target_keyword_visibility(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wpil-inbound-keyword-visibility-nonce') && isset($_POST['visible'])){
            update_user_meta(get_current_user_id(), 'wpil_inbound_target_keyword_visible', (int)$_POST['visible']);
        }
    }

    /**
     * Saves the state of the linking stats visibility on the inbound suggestions page
     **/
    public static function ajax_save_inbound_link_stats_visibility(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wpil-inbound-show-link-stats-nonce') && isset($_POST['visible'])){
            update_user_meta(get_current_user_id(), 'wpil_inbound_show_link_stats_visible', (int)$_POST['visible']);
        }
    }
}

new Wpil_TargetKeyword;