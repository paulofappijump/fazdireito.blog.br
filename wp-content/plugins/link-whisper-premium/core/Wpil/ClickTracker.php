<?php

/**
 * Handles all of the click tracking related functionality
 */
class Wpil_ClickTracker
{

    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_link_clicked', array(__CLASS__, 'ajax_link_clicked'));
        add_action('wp_ajax_nopriv_wpil_link_clicked', array(__CLASS__, 'ajax_link_clicked'));
        add_action('wp_ajax_wpil_clear_click_data', array(__CLASS__, 'ajax_clear_click_data')); // clear is for erasing all click data
        add_action('wp_ajax_wpil_delete_click_data', array(__CLASS__, 'ajax_delete_click_data')); // delete is for specific pieces of click data
        add_action('wp_ajax_wpil_delete_user_data', array(__CLASS__, 'ajax_delete_user_data'));
        self::init_cron();
    }

    /**
     * Creates the click tracking table if it doesn't already exist
     **/
    public static function prepare_table(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table != $clicks_table){
            $clicks_table_query = "CREATE TABLE IF NOT EXISTS {$clicks_table} (
                                        click_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        post_id bigint(20) unsigned,
                                        post_type varchar(10),
                                        click_date datetime,
                                        user_ip varchar(191),
                                        user_id bigint(20) unsigned,
                                        link_url text,
                                        link_anchor text,
                                        link_location varchar(64),
                                        PRIMARY KEY (click_id),
                                        INDEX (post_id),
                                        INDEX (link_url(191)),
                                        INDEX (user_ip(48))
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($clicks_table_query);
        }
    }

    /**
     * Clears the data in the click table if it exists
     **/
    public static function clear_click_tracking_table(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table === $clicks_table){
            $updated = $wpdb->query("TRUNCATE TABLE {$clicks_table}");
        }else{
            // if the table doesn't exist, create it
            self::prepare_table();
            $updated = true;
        }

        if(!empty($updated)){
            wp_send_json(array(
                'success' => array(
                    'title' => __('Click Data Cleared', 'wpil'),
                    'text'  => __('The click data has been successfully cleared!', 'wpil'),
            )));
        }else{
            wp_send_json(array(
                'error' => array(
                    'title' => __('Database Error', 'wpil'),
                    'text'  => sprintf(__('There was an error in creating the links database table. The error message was: %s', 'wpil'), $wpdb->last_error),
            )));
        }
    }

    /**
     * Inits cron
     **/
    public static function init_cron(){
        if(!empty(get_option('wpil_delete_old_click_data', '0'))){
            add_action('admin_init', array(__CLASS__, 'schedule_click_data_delete'));
            add_action('wpil_scheduled_click_data_delete', array(__CLASS__, 'do_scheduled_click_data_delete'));
        }

        register_deactivation_hook(__FILE__, array(__CLASS__, 'clear_cron_schedules'));
    }

    /**
     * Schedules the click data deletion hook
     **/
    public static function schedule_click_data_delete(){
        if(!wp_get_schedule('wpil_scheduled_click_data_delete')){
            wp_schedule_event(time(), 'daily', 'wpil_scheduled_click_data_delete');
        }
    }

    /**
     * Deletes click data that's older than the user's selection in the settings via cron
     **/
    public static function do_scheduled_click_data_delete(){
        global $wpdb;
        $click_table = $wpdb->prefix . 'wpil_click_data';
        $delete_age = get_option('wpil_delete_old_click_data', '0');

        // if the user has disabled click data deleting
        if(empty($delete_age)){
            // unschedule the the cron task for future runs
            $timestamp = wp_next_scheduled('wpil_delete_old_click_data');
            if(!empty($timestamp)){
                wp_unschedule_event($timestamp, 'wpil_delete_old_click_data');
            }
            // and exit
            return;
        }

        $delete_time = (time() - ($delete_age * DAY_IN_SECONDS) );
        $date = date('Y-m-d H:i:s', $delete_time);

        if(empty($date)){
            return;
        }

        $wpdb->query("DELETE FROM {$click_table} WHERE `click_date` < '{$date}'");
    }

    /**
     * Stores data related to the user's recent link click
     **/
    public static function ajax_link_clicked(){
        // exit if any critical data is missing
        if( !isset($_POST['post_id']) || 
            !isset($_POST['post_type']) || 
            !isset($_POST['link_url']) || 
            !isset($_POST['link_anchor']))
        {
            die();
        }

        global $wpdb;

        // assemble the click data
        $post_id = intval($_POST['post_id']);
        $post_type = ($_POST['post_type'] === 'term') ? 'term': 'post';
        $url = esc_url_raw(urldecode($_POST['link_url']));
        $anchor = sanitize_text_field(urldecode($_POST['link_anchor']));
        $location = isset($_POST['link_location']) ? sanitize_text_field($_POST['link_location']): 'Body Content';


        $user_ip = null;
        $user_id = 0;

        // if the user hasn't disabled visitor data collection
        if(empty(get_option('wpil_disable_click_tracking_info_gathering', false))){
            // get some user data
            $user_ip = self::get_current_client_ip();
            $user_id = get_current_user_id();
        }

        // if the user is an admin, exit
        if(!empty($user_id) && current_user_can('edit_posts')){
            die();
        }

        // get the ignored click data
        $ignored_links = Wpil_Settings::getIgnoredClickLinks();
        if(!empty($ignored_links)){
            foreach($ignored_links as $link){
                // if the link's anchor or url has been ignored, exit
                if($link === $anchor || $link === $url){
                    die();
                }
            }
        }

        // get when the click was made
        $click_time = current_time('mysql', true);

        // create in the insert data
        $insert_data = array(
            'post_id' => $post_id, 
            'post_type' => $post_type, 
            'click_date' => $click_time, 
            'user_ip' => $user_ip,
            'user_id' => $user_id,
            'link_url' => $url,
            'link_anchor' => $anchor,
            'link_location' => $location
        );

        // create the format array
        $format_array = array(
            '%d', // post_id
            '%s', // post_type
            '%s', // click_date
            '%s', // user_ip
            '%d', // user_id
            '%s', // url
            '%s', // anchor
            '%s', // link_location
        );

        // save the click data to the database
        $wpdb->insert($wpdb->prefix . 'wpil_click_data', $insert_data, $format_array);

        // and exit
        die();
    }

    /**
     * Clears the stored click data on ajax call
     **/
    public static function ajax_clear_click_data(){

        Wpil_Base::verify_nonce('wpil_clear_clicks_data');

        if(isset($_POST['clear_data'])){
            self::clear_click_tracking_table();
        }
        
        die();
    }

    /**
     * Deletes a specific piece of click data on ajax call
     **/
    public static function ajax_delete_click_data(){
        Wpil_Base::verify_nonce('delete_click_data');

        if( !array_key_exists('click_id', $_POST) || 
            !array_key_exists('post_id', $_POST) ||
            !isset($_POST['post_type']) ||
            !array_key_exists('anchor', $_POST) ||
            !isset($_POST['url']))
        {
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing from the request, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        global $wpdb;
        $click_table = $wpdb->prefix . 'wpil_click_data';
        $click_id = (int)$_POST['click_id'];
        $post_id = (int)$_POST['post_id'];
        $post_type = ($_POST['post_type'] === 'term' ? 'term' : 'post');
        $anchor = sanitize_text_field(stripslashes($_POST['anchor']));
        $url = esc_url_raw(base64_decode($_POST['url']));
        $query = '';

        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_click_traffic = (isset($options['show_click_traffic'])) ? true : false;

        // if we're working with individual clicks
        if($show_click_traffic){
            // delete the single instance
            $query = $wpdb->prepare("DELETE FROM {$click_table} WHERE `click_id` = %d", $click_id);
        }else{
            // if we're working with aggregate data, delete the data from the post
            $query = $wpdb->prepare("DELETE FROM {$click_table} WHERE `post_id` = %d AND `post_type` = %s AND `link_url` = %s AND `link_anchor` = %s", $post_id, $post_type, $url, $anchor);
        }

        $deleted = $wpdb->query($query);

        if(!empty($deleted) && empty($wpdb->last_error)){
            $response = array('success' => array(
                'title' => __('Success', 'wpil'),
                'text'  => __('The click data has been successfully deleted!', 'wpil'),
            ));
        }elseif(!empty($wpdb->last_error)){
            $response = array('error' => array(
                'title' => __('Data Error', 'wpil'),
                'text'  => sprintf(__('There was an error when trying to delete the click data. The error was: %s', 'wpil'), $wpdb->last_error),
            ));
        }else{
            $response = array('error' => array(
                'title' => __('Data Error', 'wpil'),
                'text'  => __('Unfortunately, the click data couldn\'t be deleted. Please reload the page and try again.', 'wpil'),
            ));
        }

        wp_send_json($response);
    }

    /**
     * Deletes all instances of the User IP from the click tracking table on ajax call
     **/
    public static function ajax_delete_user_data(){
        Wpil_Base::verify_nonce('delete_click_ip_data');

        if( !array_key_exists('user_ip', $_POST) || empty($_POST['user_ip']))
        {
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing from the request, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        $user_ip = filter_var($_POST['user_ip'], FILTER_VALIDATE_IP);

        // if the user ip isn't valid, tell the user about it
        if(empty($user_ip)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing from the request, please reload the page and try again.', 'wpil'),
                )
            ));
        }

        global $wpdb;
        $click_table = $wpdb->prefix . 'wpil_click_data';

        // unset any user ids accociated with the user's ip
        $wpdb->query($wpdb->prepare("UPDATE {$click_table} SET `user_id` = 0 WHERE `user_ip` = %s", $user_ip));

        // unset all ips that match the user's ip
        $erased = $wpdb->query($wpdb->prepare("UPDATE {$click_table} SET `user_ip` = null WHERE `user_ip` = %s", $user_ip));

        if(!empty($erased) && empty($wpdb->last_error)){
            $response = array('success' => array(
                'title' => __('Success', 'wpil'),
                'text'  => __('The IP address data has been successfully removed!', 'wpil'),
            ));
        }elseif(!empty($wpdb->last_error)){
            $response = array('error' => array(
                'title' => __('Data Error', 'wpil'),
                'text'  => sprintf(__('There was an error when trying to remove the IP address data. The error was: %s', 'wpil'), $wpdb->last_error),
            ));
        }else{
            $response = array('error' => array(
                'title' => __('Data Error', 'wpil'),
                'text'  => __('Unfortunately, the IP address data couldn\'t be removed. Please reload the page and try again.', 'wpil'),
            ));
        }

        wp_send_json($response);
    }

    /**
     * Gets the user's ip address.
     * @return string $ipaddress The user's ip address.
     **/
    public static function get_current_client_ip() {
        $ipaddress = (isset($_SERVER['REMOTE_ADDR']))?sanitize_text_field( $_SERVER['REMOTE_ADDR'] ):'';

        if(isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        }
        elseif(isset($_SERVER['HTTP_X_FORWARDED']) && $_SERVER['HTTP_X_FORWARDED'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED'] );
        }
        elseif(isset($_SERVER['HTTP_FORWARDED_FOR']) && $_SERVER['HTTP_FORWARDED_FOR'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_FORWARDED_FOR'] );
        }
        elseif(isset($_SERVER['HTTP_FORWARDED']) && $_SERVER['HTTP_FORWARDED'] != '127.0.0.1') {
            $ipaddress = sanitize_text_field( $_SERVER['HTTP_FORWARDED'] );
        }

        $ips = explode(',', $ipaddress);
        if(isset($ips[1])) {
            $ipaddress = $ips[0]; //Fix for flywheel
        }

        return $ipaddress;
    }

    public static function get_data($limit=20, $start = 0, $search='', $orderby = '', $order = 'desc'){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $hide_ignored = Wpil_Settings::hideIgnoredPosts();
        $hide_noindex = (isset($options['hide_noindex'])) ? ( ($options['hide_noindex'] == 'off') ? false : true) : false;
        $limit = (int)$limit;
        $start = (int)$start;
        $search = sanitize_text_field($search);
        $orderby = sanitize_text_field($orderby);
        $order = (strtolower($order) === 'desc') ? 'DESC': 'ASC';
        $process_terms = !empty(Wpil_Settings::getTermTypes());


        // set the limit and offset
        $limit = " LIMIT " . (($start - 1) * $limit) . ',' . $limit;

        $post_types = "'" . implode("','", Wpil_Settings::getPostTypes()) . "'";

        //create search query requests
        $term_search = '';
        $title_search = '';
        $term_title_search = '';
        if (!empty($search)) {
            $is_internal = Wpil_Link::isInternal($search);
            $search_post = Wpil_Post::getPostByLink($search);
            if ($is_internal && $search_post && ($search_post->type != 'term' || ($show_categories && $process_terms))) {
                if ($search_post->type == 'term') {
                    $term_search = " AND t.term_id = {$search_post->id} ";
                    $search = " AND 2 > 3 ";
                } else {
                    $term_search = " AND 2 > 3 ";
                    $search = " AND p.ID = {$search_post->id} ";
                }
            } else {
                $term_title_search = ", IF(t.name LIKE '%$search%', 1, 0) as title_search ";
                $title_search = ", IF(p.post_title LIKE '%$search%', 1, 0) as title_search ";
                $term_search = " AND (t.name LIKE '%$search%' OR tt.description LIKE '%$search%') ";
                $search = " AND (p.post_title LIKE '%$search%' OR p.post_content LIKE '%$search%') ";
            }
        }

        //filters
        $post_ids = Wpil_Filter::getLinksLocationIDs();
        if (Wpil_Filter::linksCategory()) {
            $process_terms = false;
            if (!empty($post_ids)) {
                $post_ids = array_intersect($post_ids, Wpil_Filter::getLinksCatgeoryIDs());
            } else {
                $post_ids = Wpil_Filter::getLinksCatgeoryIDs();
            }
        }

        if (!empty($post_ids)) {
            $search .= " AND p.ID IN (" . implode(', ', $post_ids) . ") ";
        }

        if ($post_type = Wpil_Filter::linksPostType()) {
            $term_search .= " AND tt.taxonomy = '$post_type' ";
            $search .= " AND p.post_type = '$post_type' ";
        }

        //sorting
        if (empty($orderby) && !empty($title_search)) {
            $orderby = 'title_search';
            $order = 'DESC';
        } elseif (empty($orderby) || $orderby == 'date') {
            $orderby = 'post_date';
        }

        //get data
        $statuses_query = Wpil_Query::postStatuses('p');
        $report_post_ids = Wpil_Query::reportPostIds(false);
        $report_term_ids = Wpil_Query::reportTermIds(false, $hide_noindex);

        $post_filter_query = "";
        $link_filters = Wpil_Filter::filterLinkCount();
        if($link_filters){
            switch($link_filters['link_type']){
                case 'inbound-internal':
                    $key = 'wpil_links_inbound_internal_count';
                    break;
                case 'outbound-internal':
                    $key = 'wpil_links_outbound_internal_count';
                    break;
                case 'outbound-external':
                default:
                    $key = 'wpil_links_outbound_external_count';
                    break;
            }

            $filter_query = " meta_key = '{$key}' AND meta_value >= {$link_filters['link_min_count']}";
            $filter_query .= ($link_filters['link_max_count'] !== null) ? " AND meta_value <= {$link_filters['link_max_count']}": '';
            
            $post_filter_query = " AND p.ID IN (select `post_id` as 'ID' from {$wpdb->postmeta} WHERE meta_key = '{$key}' AND meta_value >= {$link_filters['link_min_count']}";
            $post_filter_query .= ($link_filters['link_max_count'] !== null) ? " AND meta_value <= {$link_filters['link_max_count']}": '';
            $post_filter_query .= ")";

            if(!empty($report_post_ids)){
                $report_post_ids = str_replace('AND p.ID', 'post_id', $report_post_ids);
                $report_post_ids = $wpdb->get_col("SELECT `post_id` FROM $wpdb->postmeta WHERE $report_post_ids AND $filter_query");
                $report_post_ids = !empty($report_post_ids) ? " AND p.ID IN (" . implode(',', $report_post_ids) . ")" : "AND p.ID = null";
            }

            if(!empty($report_term_ids)){
                $report_term_ids = "term_id IN ($report_term_ids)";
                $report_term_ids = $wpdb->get_col("SELECT `term_id` FROM $wpdb->termmeta WHERE $report_term_ids AND $filter_query");
                $report_term_ids = implode(',', $report_term_ids);
            }
        }

        // hide ignored
        $ignored_posts = Wpil_Query::get_all_report_ignored_post_ids('', array('hide_noindex' => $hide_noindex));
        $ignored_terms = '';
        if($hide_ignored && $show_categories){
            $ignored_terms = Wpil_Query::ignoredTermIds();
        }


        //create query for other orders
        $query = "SELECT a.ID, a.post_title, a.post_type, a.post_date, a.type, COUNT(`click_id`) as clicks FROM (SELECT p.ID, p.post_title, p.post_type, p.post_date as `post_date`, 'post' as `type` $title_search  
                    FROM {$wpdb->prefix}posts p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE 1 = 1 $report_post_ids $statuses_query $ignored_posts AND p.post_type IN ($post_types) $search AND pm.meta_key = 'wpil_sync_report3' AND pm.meta_value = '1'  {$post_filter_query}";

        if ($show_categories && $process_terms && !empty($report_term_ids)) {
            $taxonomies = Wpil_Settings::getTermTypes();
            $query .= " UNION
                        SELECT t.term_id as `ID`, t.name as `post_title`, tt.taxonomy as `post_type`, NOW() as `post_date`, 'term' as `type` $term_title_search  
                        FROM {$wpdb->prefix}termmeta m INNER JOIN {$wpdb->prefix}terms t ON m.term_id = t.term_id INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                        WHERE t.term_id in ($report_term_ids) $ignored_terms AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search";
        }

        $query .= ") a LEFT JOIN {$clicks_table} c ON c.post_id = a.ID AND c.post_type = a.type GROUP BY ID ORDER BY {$orderby} {$order} {$limit}";


        $result = $wpdb->get_results($query);

        //calculate total count
        $total_items = self::get_total_items($query);

        //prepare report data
        foreach ($result as $key => &$post_data) {
            if ($post_data->type == 'term') {
                $p = new Wpil_Model_Post($post_data->ID, 'term');
                $inbound = admin_url("admin.php?term_id={$post_data->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            } else {
                $p = new Wpil_Model_Post($post_data->ID);
                $inbound = admin_url("admin.php?post_id={$post_data->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            }

            $post_data->post = $p;
            $post_data->links_inbound_page_url = $inbound;
        }

        return array( 'data' => $result , 'total_items' => $total_items);

    }

    /**
     * Get total items depend on filters
     *
     * @param $query
     * @return string|null
     */
    public static function get_total_items($query)
    {
        global $wpdb;

        $query = str_replace('UNION', 'UNION ALL', $query);
        $limit = strpos($query, ' LIMIT');
        $query = "SELECT count(*) FROM (" . substr($query, 0, $limit) . ") as t1";
        return $wpdb->get_var($query);
    }

    public static function get_detailed_click_table_data($id, $type = 'post', $page = 1, $orderby = '', $order = 'desc', $range = array('start' => false, 'end' => false)){

        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $limit = (isset($options['per_page'])) ? $options['per_page'] : 20;
        $show_click_traffic = (isset($options['show_click_traffic'])) ? true : false;
        $search = (isset($_POST['keywords']) && !empty($_POST['keywords'])) ? sanitize_text_field($_POST['keywords']): '';
        $orderby = (in_array($orderby, array('post_id', 'link_url', 'link_anchor', 'click_date', 'user_ip', 'total_clicks'), true)) ? $orderby: '';
        $order = ($order === 'desc') ? 'desc': 'asc';
        $start = (isset($range['start']) && !empty($range['start'])) ? date('Y-m-d H:i:s', intval($range['start'])): date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        $end = (isset($range['end']) && !empty($range['end'])) ? date('Y-m-d H:i:s', intval($range['end'])): date('Y-m-d H:i:s', time());

        // exit if there's no id
        if(empty($id)){
            return array('data' => array(), 'total_items' => 0);
        }

        if(!empty($orderby)){
            $orderby = "ORDER BY `{$orderby}` {$order}";
        }

        // set the limit and offset
        $limit = "LIMIT " . (((int)$page - 1) * $limit) . ',' . $limit;

        $count_clicks = (!$show_click_traffic) ? ", COUNT(`link_url`) AS 'total_clicks'": '';
        $group_clicks = (!$show_click_traffic) ? "GROUP BY `link_url`, `link_anchor`": '';
        $group_posts  = (!$show_click_traffic) ? "GROUP BY `post_id`": '';

        if($type === 'url'){
            $id = esc_url_raw($id);
            if(!empty($search)){
                $query = "SELECT post_id, a.post_type AS post_type, click_date, user_ip, user_id, link_url, link_anchor, link_location {$count_clicks} FROM {$clicks_table} a LEFT JOIN {$wpdb->posts} b ON a.post_id = b.ID WHERE a.link_url = '{$id}' AND a.click_date > '{$start}' AND '{$end}' > a.click_date AND (a.link_anchor LIKE '%{$search}%' OR b.post_title LIKE '%{$search}%') {$group_posts} {$orderby} {$limit}";
            }else{
                $query = "SELECT * {$count_clicks} FROM {$clicks_table} WHERE `link_url` = '{$id}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$group_posts} {$orderby} {$limit}";
            }
        }elseif($type === 'user_ip'){
            $id = filter_var($id, FILTER_VALIDATE_IP);

            if(!empty($search)){
                $search = "AND (`link_url` LIKE '%{$search}%' OR `link_anchor` LIKE '%{$search}%')";
            }

            $query = "SELECT `post_id`, `post_type`, `link_url`, `link_anchor`, `click_date`, `user_ip`, link_location {$count_clicks} FROM {$clicks_table} WHERE `user_ip` = '{$id}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$search} {$group_clicks} {$orderby} {$limit}";
        }else{
            $id = intval($id);
            $type = ($type === 'post') ? 'post': 'term';
            if(!empty($search)){
                $search = "AND (`link_url` LIKE '%{$search}%' OR `link_anchor` LIKE '%{$search}%')";
            }

            $query = "SELECT `post_id`, `post_type`, `link_url`, `link_anchor`, link_location {$count_clicks} FROM {$clicks_table} WHERE `post_id` = {$id} AND `post_type` = '{$type}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$search} {$group_clicks} {$orderby} {$limit}";

            if($show_click_traffic){
                $query = "SELECT * FROM {$clicks_table} WHERE `post_id` = {$id} AND `post_type` = '{$type}' AND `click_date` > '{$start}' AND '{$end}' > `click_date` {$search} {$orderby} {$limit}";
            }
        }

        $result = $wpdb->get_results($query);
        $total_items = !empty($result) ? self::get_total_detailed_click_items($query) : 0;

        return array('data' => $result, 'total_items' => $total_items);
    }

    /**
     * Get total items depend on filters
     *
     * @param $query
     * @return string|null
     */
    public static function get_total_detailed_click_items($query)
    {
        global $wpdb;

        $limit = strpos($query, 'LIMIT');
        $query = "SELECT count(*) FROM (" . substr($query, 0, $limit) . ") as t1";
        return $wpdb->get_var($query);
    }

    /**
     * Gets data for the click report dropdowns.
     * @param int $post_id
     * @param string (post|term) $post_type The LW post type, so is it a 'post' or a 'term? Should really be called something like 'data_type'.
     **/
    public static function get_click_dropdown_data($post_id, $post_type){
        global $wpdb;

        $post_id = (int) $post_id;
        $post_type = ($post_type === 'post') ? 'post': 'term';
        if(function_exists('wp_date')){
            $range = wp_date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        }else{
            $range = date_i18n('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        }

        $query = "SELECT    b.`link_url`,
                            b.`link_anchor`, 
                            COUNT(b.link_url) AS 'most_clicked_count', 
                            (select count(a.`click_id`) from {$wpdb->prefix}wpil_click_data a where `post_id` = {$post_id} and `post_type` = '{$post_type}' AND `click_date` > '{$range}') AS 'clicks_over_30_days', 
                            (select count(a.`click_id`) from {$wpdb->prefix}wpil_click_data a where `post_id` = {$post_id} and `post_type` = '{$post_type}') AS 'total_clicks'
        FROM {$wpdb->prefix}wpil_click_data b WHERE `post_id` = {$post_id} AND `post_type` = '{$post_type}' GROUP BY `link_url` ORDER BY `most_clicked_count` DESC LIMIT 1";

        $click_data = $wpdb->get_results($query);

        return $click_data;
    }

    /**
     * Gets the detailed click data for the given post and date range.
     * This is use
     **/
    public static function get_detailed_click_data($post_id, $post_type, $range = array('start' => false, 'end' => false)){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';
        $start = (isset($range['start']) && !empty($range['start'])) ? date('Y-m-d H:i:s', intval($range['start'])): date('Y-m-d H:i:s', (time() - (30 * DAY_IN_SECONDS)));
        $end = (isset($range['end']) && !empty($range['end'])) ? date('Y-m-d H:i:s', intval($range['end'])): date('Y-m-d H:i:s', time());
        $search = (isset($_POST['keywords']) && !empty($_POST['keywords'])) ? sanitize_text_field($_POST['keywords']): '';

        if($post_type === 'url'){
            $post_id = esc_url_raw($post_id);
            if(!empty($search)){
                $query = "SELECT post_id, a.post_type AS post_type, click_date, user_ip, user_id, link_url, link_anchor FROM {$clicks_table} a LEFT JOIN {$wpdb->posts} b ON a.post_id = b.ID WHERE a.link_url = '{$post_id}' AND a.click_date > '{$start}' AND '{$end}' > a.click_date AND (a.link_anchor LIKE '%{$search}%' OR b.post_title LIKE '%{$search}%')";
            }else{
                $query = "SELECT * FROM {$clicks_table} WHERE `link_url` = '{$post_id}' AND `click_date` > '{$start}' AND `click_date` < '{$end}'";
            }
        }elseif($post_type === 'user_ip'){
            $post_id = filter_var($post_id, FILTER_VALIDATE_IP);
            if(!empty($search)){
                $query = "SELECT post_id, a.post_type AS post_type, click_date, user_ip, user_id, link_url, link_anchor FROM {$clicks_table} a LEFT JOIN {$wpdb->posts} b ON a.post_id = b.ID WHERE a.user_ip = '{$post_id}' AND a.click_date > '{$start}' AND '{$end}' > a.click_date AND (a.link_anchor LIKE '%{$search}%' OR b.post_title LIKE '%{$search}%')";
            }else{
                $query = "SELECT * FROM {$clicks_table} WHERE `user_ip` = '{$post_id}' AND `click_date` > '{$start}' AND `click_date` < '{$end}'";
            }
        }else{
            $post_id = (int) $post_id;
            $post_type = ($post_type === 'post') ? 'post': 'term';
            if(!empty($search)){
                $search = "AND (`link_url` LIKE '%{$search}%' OR `link_anchor` LIKE '%{$search}%')";
            }
            $query = "SELECT * FROM {$clicks_table} WHERE `post_id` = {$post_id} AND `post_type` = '{$post_type}' AND `click_date` > '{$start}' AND `click_date` < '{$end}' {$search}";
        }

        $click_data = $wpdb->get_results($query);

        return $click_data;
    }

    /**
     * Checks the click table to see if there's any stored visitor IP addresses or user ids.
     * 
     * @return bool
     **/
    public static function check_for_stored_visitor_data(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        // first check if the click table is created
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table != $clicks_table){
            // if it doesn't, return false
            return false;
        }

        // next check for IP data
        $results = $wpdb->get_results("SELECT * FROM {$clicks_table} WHERE `user_ip` IS NOT NULL LIMIT 1");

        // if there is IP data, return true
        if(!empty($results)){
            return true;
        }

        // If there isn't IP data, check for user id data
        $results = $wpdb->get_results("SELECT * FROM {$clicks_table} WHERE `user_id` != 0 LIMIT 1");

        if(!empty($results)){
            // return true if we've saved id data
            return true;
        }

        // if we've made it past both checks, then there's no user data in the click report table
        return false;
    }

    /**
     * Deletes all stored user data from the click table
     **/
    public static function delete_stored_visitor_data(){
        global $wpdb;

        $clicks_table = $wpdb->prefix . 'wpil_click_data';

        // first check if the click table is created
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$clicks_table}'");
        if($table != $clicks_table){
            // if it doesn't, return false
            return false;
        }

        // unset the user ips
        $erased_1 = $wpdb->query("UPDATE {$clicks_table} SET `user_ip` = null WHERE `user_ip` IS NOT NULL");

        // and unset the user ids
        $erased_2 = $wpdb->query("UPDATE {$clicks_table} SET `user_id` = 0 WHERE `user_id` != 0");

        return !empty($erased_1) && !empty($erased_2);
    }
}