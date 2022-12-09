<?php

/**
 * Handles connecting to other sites that have LW installed and tranferring data.
 **/

class Wpil_SiteConnector{

    static $query_limit = 5000; // How many items should we ask for in a batch?

    public function register(){
        add_action('wp_ajax_wpil_link_selected_site', array(__CLASS__, 'ajax_link_selected_site'));
        add_action('wp_ajax_wpil_register_selected_site', array(__CLASS__, 'ajax_register_selected_site'));
        add_action('wp_ajax_wpil_external_site_suggestion_toggle', array(__CLASS__, 'ajax_external_site_suggestion_toggle'));
        add_action('wp_ajax_wpil_remove_registered_site', array(__CLASS__, 'ajax_remove_registered_site'));
        add_action('wp_ajax_wpil_remove_linked_site', array(__CLASS__, 'ajax_remove_linked_site'));
        add_action('wp_ajax_wpil_refresh_site_data', array(__CLASS__, 'ajax_download_all_posts'));
        add_action('wp_loaded', array(__CLASS__, 'process_tokens'));
        add_action('post_updated', array(__CLASS__, 'push_item_update_to_network'), 99, 3);
        add_action('edited_term_taxonomy', array(__CLASS__, 'push_item_update_to_network'), 99, 2);
        add_action('delete_post', array(__CLASS__, 'push_item_delete_to_network'), 99, 2);
        add_action('delete_term_taxonomy', array(__CLASS__, 'push_item_delete_to_network'), 99, 1);
    }

    public static function create_data_table(){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpil_data_table}'");
        if ($table != $wpil_data_table) {
            $wpil_data_table_query = "CREATE TABLE IF NOT EXISTS {$wpil_data_table} (
                                        item_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        post_id bigint(20) unsigned NOT NULL,
                                        post_type text,
                                        type varchar(10),
                                        site_url text,
                                        post_url text,
                                        post_title text,
                                        stemmed_title text,
                                        post_modified_gmt DATETIME,
                                        last_scan DATETIME NOT NULL DEFAULT NOW(),
                                        PRIMARY KEY  (item_id),
                                        INDEX (post_id),
                                        INDEX (site_url(255))
                                    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_data_table_query);
        }
    }

    public static function clear_data_table(){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpil_data_table}'");
        if ($table == $wpil_data_table) {
            $wpdb->query("TRUNCATE TABLE {$wpil_data_table}");
        }
    }

    /**
     * Erases the data for a given external site
     **/
    public static function clear_external_site_data($url = ''){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_site_linking_data';
        if(empty($url)){
            return false;
        }

        $url = trailingslashit(esc_url_raw($url));

        // make sure the site exists
        if(!self::check_if_site_added($url)){
            return false;
        }

        // get all the post indexes for the site data to remove
        $indexes = $wpdb->get_col($wpdb->prepare("SELECT `item_id` FROM {$data_table} WHERE `site_url` = %s", $url));

        // if we have post indexes
        if(!empty($indexes)){
            // drop the database indexes for the other cols to speed up deletion
            $wpdb->query("ALTER TABLE {$data_table} DROP INDEX `post_id`");
            $wpdb->query("ALTER TABLE {$data_table} DROP INDEX `site_url`");

            // loop over the ids and remove them from the db
            while(true){
                $ids = array_splice($indexes, 0, 10000);

                // exit if we're out of ids
                if(empty($ids)){
                    break;
                }

                // create the ids list
                $ids = implode(', ', $ids);

                // delete the ids in the list
                $wpdb->query("DELETE FROM {$data_table} WHERE `item_id` IN ({$ids})");
            }

            // now that we're done removing the posts, re-add the database indexes
            $wpdb->query("ALTER TABLE {$data_table} ADD INDEX( `post_id`)");
            $wpdb->query("ALTER TABLE {$data_table} ADD INDEX( `site_url`(255))");
        }
            
        return true;
    }

    /**
     * Handles the incoming netword calls.
     * Processes the token handshake and executes actions if the access token is good.
     * 
     **/
    public static function process_tokens(){
        if( isset($_POST['initok']) && 
            !empty($_POST['initok']) && 
            isset($_POST['time']) && 
            !empty($_POST['time'])
        ){
            // if we're not accepting site linking, exit
            if(empty(get_option('wpil_link_external_sites', false))){
                die;
            }

            // ignore the object cache if it's present
            Wpil_Base::ignore_external_object_cache();

            // Remove any hooks that may interfere with AJAX requests
            Wpil_Base::remove_problem_hooks();

            // check to see if the site is valid
            $site_url = self::process_initial_request_string($_POST['initok'], $_POST['time']);

            // if it is
            if(!empty($site_url)){
                // tee up round two of the authentication handshake
                $second_query = self::create_secondary_request_string($site_url);

                // if this is just a ping, tell the other site we know it's a ping
                $ping = false;
                if(isset($_POST['ping']) && !empty($_POST['ping'])){
                    $ping = true;
                }

                if(!empty($second_query)){
                    self::update_secondary_request_hash_data($second_query['sectok'], $site_url, $second_query['time']);
                    wp_send_json($second_query);
                }
            }
        }

        // note: I don't believe this is currently used because of the connection method used.
        if( isset($_POST['sectok']) && 
            !empty($_POST['sectok']) && 
            isset($_POST['time']) && 
            !empty($_POST['time']))
        {
            // if we're not accepting site linking, exit
            if(get_option('wpil_link_external_sites', false)){
                die;
            }

            // ignore the object cache if it's present
            Wpil_Base::ignore_external_object_cache();

            // Remove any hooks that may interfere with AJAX requests
            Wpil_Base::remove_problem_hooks();

            $second_site_url = self::process_secondary_request_string($_POST['sectok'], $_POST['time']);

            if(!empty($second_site_url)){
                $token = self::create_access_token($_POST['sectok'], $second_site_url, $_POST['time']);

                // if the token could be created and this isn't a ping, call the site for the final round
                if(!empty($token) && !isset($_POST['ping'])){
                    self::call_site($second_site_url, $second_query);
                }
            }
        }

        // if we've been given the final token
        if( isset($_POST['fintok']) && 
            !empty($_POST['fintok']) && 
            isset($_POST['time']) && 
            !empty($_POST['time']))
        {
            // if we're not accepting site linking, exit
            if(empty(get_option('wpil_link_external_sites', false))){
                die;
            }

            // ignore the object cache if it's present
            Wpil_Base::ignore_external_object_cache();

            // Remove any hooks that may interfere with AJAX requests
            Wpil_Base::remove_problem_hooks();

            // get the query data
            $query_data = array_diff_key($_POST, array('fintok' => 1, 'target_url' => 1, 'time' => 1, 'page' => 1, 'limit' => 1));

            // get if this is a ping
            $ping = false;
            if(isset($_POST['ping']) && !empty($_POST['ping'])){
                $ping = true;
                unset($query_data['ping']);
            }

            if(empty($query_data)){
                $query_data = array();
            }

            // check it to see if it's valid
            $token_valid = self::verify_access_token($_POST['fintok'], $_POST['target_url'], $_POST['time'], $_POST['page'], $query_data);

            // if it is, respond to the other site
            if(!empty($token_valid)){
                
                // if it's an authentication ping
                if($ping){
                    // log the site as linked if it isn't already
                    self::update_linked_sites($_POST['target_url']);
                    // and send back that we've accepted the site
                    wp_send_json(array('status' => 200));
                }elseif(isset($_POST['update']) && !empty($_POST['update']) && isset($_POST['data']) && !empty($_POST['data'])){
                    // if we're being notified of a content update, update the stored content

                    // decode the data
                    $data = unserialize(base64_decode($_POST['data']));
                    // update the item
                    $updated = self::update_data_item($data, $_POST['target_url']);

                    // if it was successful, tell the caller about it
                    if(!empty($updated)){
                        wp_send_json(array('status' => 200));
                    }

                }elseif(isset($_POST['delete']) && !empty($_POST['delete']) && isset($_POST['data']) && !empty($_POST['data'])){
                    // if we're being notified of an item deletion, delete the stored content
                    
                    // decode the data
                    $data = unserialize(base64_decode($_POST['data']));
                    // delete the item
                    $deleted = self::delete_data_item($data, $_POST['target_url']);

                    // if it was successful, tell the caller about it
                    if(!empty($deleted)){
                        wp_send_json(array('status' => 200));
                    }

                }elseif(isset($_POST['import']) && !empty($_POST['import'])){
                    // begin the data export
                    $page = 0;
                    if(isset($_POST['page'])){
                        $page = (int)$_POST['page'];
                    }

                    $limit = self::$query_limit;
                    if(isset($_POST['limit']) && $_POST['limit'] < 10000){
                        $limit = (int)$_POST['limit'];
                    }

                    $results = self::export_data($page, $limit);

                    $hmac = self::create_hmac($results, $limit, $page, $_POST['target_url']);
                    
                    wp_send_json(array('data' => $results, 'limit' => $limit, 'hmac' => $hmac));
                }else{
                    
                }
            }
        }
    }

    /**
     * Gets a data item from the database.
     **/
    public static function get_data_item($id = 0, $type = 'post', $site_url = ''){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_site_linking_data';

        if(empty($id) || empty($type) || empty($site_url)){
            return false;
        }

        $query = $wpdb->prepare("SELECT * FROM {$data_table} WHERE `post_id` = %d AND `type` = %s AND `site_url` = %s", $id, $type, $site_url);
        $item = $wpdb->get_results($query);

        return $item;

    }

    /**
     * Updates an item's data in the database.
     * Creates the item if it doesn't exist in the table.
     * 
     * @param object $item_data Item data from the site that pushed the update.
     * @param string $site_url The url of the site that pushed the update.
     **/
    public static function update_data_item($item_data = array(), $site_url = ''){
        if(empty($item_data) || empty($site_url)){
            return false;
        }

        $site_url = trailingslashit(esc_url_raw($site_url));

        // find out if we have the item on hand
        $stored_item = self::get_data_item($item_data->post_id, $item_data->type, $site_url);

        if(empty($stored_item)){
            $results = self::save_data(array($item_data), $site_url); // wrapp the object in an array because the saver expects to deal with an array of items
        }else{
            $results = self::update_data(array($item_data), $site_url); // wrapp the object in an array because the saver expects to deal with an array of items
        }

        return $results;
    }

    /**
     * Deletes an item's data from the database.
     * 
     * @param object $item_data The id and data type of the item that was deleted.
     * @param string $site_url The url that the item was deleted from.
     **/
    public static function delete_data_item($item_data = array(), $site_url = ''){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';

        if(empty($item_data) || empty($site_url)){
            return false;
        }

        $site_url = trailingslashit(esc_url_raw($site_url));

        // find out if we have the item
        $stored_items = self::get_data_item($item_data->post_id, $item_data->type, $site_url);

        // if we do, delete it
        $deleted = false;
        if(!empty($stored_items)){
            foreach($stored_items as $stored_item){
                $deleted = $wpdb->delete($wpil_data_table, array('item_id' => $stored_item->item_id));
            }
        }

        return $deleted;
    }

    /**
     * Counts how many external site data items we have in the database
     **/
    public static function count_data_items(){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';

        // check if the user has disabled suggestions for an external site
        $no_suggestions = get_option('wpil_disable_external_site_suggestions', array());
        $ignore_sites = '';
        if(!empty($no_suggestions)){
            $urls = array_keys($no_suggestions);
            $ignore = implode('\', \'', $urls);
            $ignore_sites = "WHERE `site_url` NOT IN ('$ignore')";
        }

        $item_count = $wpdb->get_var("SELECT COUNT(`post_id`) FROM {$wpil_data_table} {$ignore_sites}");

        if(!empty($item_count)){
            return $item_count;
        }else{
            return 0;
        }
    }

    /**
     * Checks to see if there's data stored for the given url.
     **/
    public static function check_for_stored_data($site_url = ''){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';

        if(empty($site_url)){
            return false;
        }

        $site_url = trailingslashit(esc_url_raw($site_url));

        $item = $wpdb->get_var($wpdb->prepare("SELECT COUNT(`post_id`) FROM {$wpil_data_table} WHERE `site_url` = %s LIMIT 1", $site_url));

        return (!empty($item)) ? true: false;
    }

    /**
     * Authenticates a connection to a single site
     * 
     * @param string $url The url of the external site that we want to auth
     * @return bool True if the site has been authenticated, False if it hasn't
     **/
    public static function authenticate($url = ''){
        if(empty($url)){
            return false;
        }
        
        $url = trailingslashit(wp_http_validate_url($url));

        $query_data = self::create_initial_request_string($url);

        if(empty($query_data)){
            return false;
        }

        // call the other site to see if it recognizes us
        $response = self::call_site($url, $query_data);

        // if the site has responded to out call, check to make sure it has the second token
        if(!empty($response)){
            
            if(isset($response->sectok) && !empty($response->sectok)){
                // check the token
                $away_site_url = self::process_secondary_request_string($response->sectok, $response->time);

                // if the token is valid
                if(!empty($away_site_url)){
                    $time = time();
                    $sectok = $response->sectok;

                    // make the request token
                    $final_token = self::create_access_token($sectok, $url, $time);
                    $response = self::call_site($url, $final_token, true);

                    if(!empty($response)){
                        // if the site responded that the token is good
                        if(isset($response->status) && $response->status === 200){
                            // update the hash data to indicate we have an open door
                            self::update_secondary_request_hash_data($sectok, $url, $time, true);
                            // return true to say this was successful
                            return true;
                        }
                    }else{
                        return false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Obtains post data from the given external site
     **/
    public static function call_for_data($url = '', $page = 0){

        $hash_data = self::get_secondary_request_hash_data($url);

        if(empty($hash_data)){
            return false;
        }

        // make the request token
        $query_data['import'] = '1';
        $final_token = self::create_access_token($hash_data['hash'], $url, $hash_data['time'], (int)$page, $query_data);
        $response = self::call_site($url, $final_token);

        if(!empty($response)){
            return $response;
        }else{
            return false;
        }

    }

    /**
     * Pings the site at the given url to see if it recognizes this site.
     **/
    public static function ping_site($target_url = ''){
        if(empty($target_url)){
            return false;
        }

        $query_data = self::create_initial_request_string($target_url);

        if(empty($query_data)){
            return false;
        }

        // call the other site to see if it recognizes us
        $response = self::call_site($target_url, $query_data, true);

        // if the site has responded to out call, check to make sure it has the second token
        if(!empty($response)){
            if(isset($response->sectok) && !empty($response->sectok)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * Updates all the sites in the network. 
     * Runs through the authentication process for all the passed sites.
     * Specific sites to update can be listed by passing them in the $urls var.
     **/
    public static function update_network_sites($query_args = array(), $urls = array()){
        // if site linking isn't enabled or the user has disabled network updating
        if(empty(get_option('wpil_link_external_sites', false)) || !empty(get_option('wpil_disable_external_site_updating', false))){
            // exit
            return false;
        }

        if(empty($urls)){
            $urls = self::get_linked_sites();
        }

        $linked_urls = array();
        foreach($urls as $url){
            $linked_urls[] = trailingslashit(wp_http_validate_url($url));
        }

        if(empty($linked_urls)){
            return false;
        }

        $query_data = array();
        foreach($linked_urls as $url){
            $query_data[$url] = self::create_initial_request_string($url);
        }

        if(empty($query_data)){
            return false;
        }

        // call the other sites with the initial round of auths
        $responses = self::call_sites($linked_urls, $query_data);

        // if the site has responded to out call, check to make sure it has the second token
        if(!empty($responses)){

            $verified_urls = array();
            $access_tokens = array();
            foreach($responses as $response){
                if(isset($response->sectok) && !empty($response->sectok)){
                    // check the token
                    $away_site_url = self::process_secondary_request_string($response->sectok, $response->time);

                    // if the token is valid
                    if(!empty($away_site_url)){
                        $time = time();
                        // save the url for calling
                        $verified_urls[] = $away_site_url;

                        // make the request token
                        $access_tokens[$away_site_url] = self::create_access_token($response->sectok, $away_site_url, $time, 0, $query_args);
                    }
                }
            }

            if(!empty($verified_urls)){
                $responses = self::call_sites($verified_urls, $access_tokens, true);

                foreach($responses as $response){
                    if(isset($response->status) && $response->status === 200){
                        // if any site returns 200, return true.
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Call a single site.
     * Functions as a wrapper for call_sites that works with single target URLs and returns single responses
     * 
     **/
    public static function call_site($target_url = '', $query_args = array(), $ping = false){
        if(empty($target_url)){
            return false;
        }

        if(!empty($ping)){
            $query_args['ping'] = '1';
        }

        $url = array($target_url);
        $target_args = array($target_url => $query_args);

        $response = self::call_sites($url, $target_args);

        if(!empty($response)){
            $response = $response[$target_url];
        }

        return $response;
    }

    public static function call_sites($urls, $query_args = array()) {
        $start = microtime(true);
        $user_ip = get_transient('wpil_site_ip_address');

        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('wpil_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the multihandle
        $mh = curl_multi_init();

        // if we're debugging curl
        if(WPIL_DEBUG_CURL){
            // setup the log files
            $verbose = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_log.log', 'a');     // logs the actions that curl goes through in contacting the server
            $connection = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_info.log', 'a'); // logs the result of contacting the server.
        }

        $handles = array();
        foreach($urls as $url){
            // make sure we're calling wp-load
            $call_url = $url;
            if(false === strpos($url, 'wp-load.php')){
                $call_url = trailingslashit($url) . 'wp-load.php';
            }

            // filter the call url if the user needs it
            $call_url = apply_filters('wpil_filter_external_site_call_url', $call_url);

            // create the curl handle and add it to the list keyed with the url its using
            $handles[$url] = curl_init(html_entity_decode($call_url));

            // create the list of headers to make the cURL request with
            $request_headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: max-age=0, no-cache',
                'Pragma: ',
                'Connection: keep-alive',
                'Keep-Alive: 300',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?0',
                'Host: ' . parse_url($url, PHP_URL_HOST),
                'Referer: ' . apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url())),
                'User-Agent: ' . WPIL_DATA_USER_AGENT,
            );

            if(!empty($user_ip)){
                $request_headers[] = 'X-Real-Ip: ' . $user_ip;
            }

            curl_setopt($handles[$url], CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($handles[$url], CURLOPT_HEADER, false);
            curl_setopt($handles[$url], CURLOPT_FILETIME, true);
            curl_setopt($handles[$url], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handles[$url], CURLOPT_MAXREDIRS, 1);
            curl_setopt($handles[$url], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handles[$url], CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handles[$url], CURLOPT_TIMEOUT, 60);
            curl_setopt($handles[$url], CURLOPT_COOKIEFILE, null);
            curl_setopt($handles[$url], CURLOPT_FORBID_REUSE, true);
            curl_setopt($handles[$url], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($handles[$url], CURLOPT_COOKIESESSION, true);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYPEER, false);

            if(!empty($query_args)){
                curl_setopt($handles[$url], CURLOPT_POSTFIELDS, $query_args[$url]);
            }

            $curl_version = curl_version();
            if (version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
                curl_setopt($handles[$url], CURLOPT_SSL_FALSESTART, true);
            }

            // if we're debugging curl
            if(WPIL_DEBUG_CURL){
                // set curl to verbose logging and set where to write it to
                curl_setopt($handles[$url], CURLOPT_VERBOSE, true);
                curl_setopt($handles[$url], CURLOPT_STDERR, $verbose);
            }

            // and add it to the multihandle
            curl_multi_add_handle($mh, $handles[$url]);
        }

        // if there are handles, execute the multihandle
        if(!empty($handles)){
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
        }

        // get any error codes from the operations
        $curl_codes = array();
        foreach($handles as $handle){
            $info = curl_multi_info_read($mh);
            $handle_int = intval($info['handle']);
            if(isset($info['result'])){
                $curl_codes[$handle_int] = $info['result'];
            }else{
                $curl_codes[$handle_int] = 0;
            }
        }

        // when the multihandle is finished, go over the handles and process the responses
        $responses = array();
        foreach($handles as $handle_url => $handle){
            $response = curl_multi_getcontent($handle);
            if(self::is_json($response)){
                $response = json_decode($response);
            }elseif(!empty($response)){ // if the response isn't JSON, but it's not empty either, then it's probably gzipped
                $unzip = @gzdecode($response); // mute any errors so the JS isn't interupted if this doesn't work
                
                if(self::is_json($unzip)){
                    $response = json_decode($unzip);
                }else{
                    $response = false;
                }
            }else{
                $response = false;
            }
            $responses[$handle_url] = $response;
        }

        // close the multi handle
        curl_multi_close($mh);

        return $responses;
    }

    /**
     * Saves the data from the away site into the database
     * @param string $data
     **/
    public static function save_data($data = array(), $site_url = ''){
        global $wpdb;
        $wpil_data_table = $wpdb->prefix . 'wpil_site_linking_data';

        if(empty($data) || empty($site_url)){
            return false;
        }

        $count = 0;
        $insert_query = "INSERT INTO {$wpil_data_table} (post_id, post_type, type, site_url, post_url, post_title, stemmed_title, post_modified_gmt) VALUES ";
        $insert_data = array();
        $place_holders = array();
        $limit = 1000;
        $insert_count = 0;
        $site_url = trailingslashit(esc_url_raw($site_url));
        $timezone = new DateTimeZone('UTC');

        foreach($data as $dat){

            $type = ($dat->type === 'post') ? 'post': 'term';
            $timestamp = strtotime($dat->post_modified_gmt . ' GMT');

            if(empty($timestamp)){
                continue;
            }

            if(function_exists('wp_date')){
                $gmt_time = wp_date('Y-m-d H:i:s', $timestamp, $timezone);
            }else{
                $gmt_time = date_i18n('Y-m-d H:i:s', $timestamp);
            }
            $title = sanitize_text_field($dat->post_title);
            $stemmed_title = Wpil_Word::getStemmedSentence($title);

            array_push(
                $insert_data,
                (int) $dat->post_id,
                sanitize_text_field($dat->post_type),
                $type,
                $site_url,
                esc_url_raw($dat->post_url),
                $title,
                $stemmed_title,
                $gmt_time
            );
            $place_holders [] = "('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')";

            // if we've hit the limit, save the assembled data
            if($count > $limit){
                $insert_query .= implode(', ', $place_holders);
                $insert_query = $wpdb->prepare($insert_query, $insert_data);
                $insert_count += $wpdb->query($insert_query);

                // reset the insert data vars for the next run around
                $insert_query = "INSERT INTO {$wpil_data_table} (post_id, post_type, type, site_url, post_url, post_title, stemmed_title, post_modified_gmt) VALUES ";
                $insert_data = array();
                $place_holders = array();
                $count = 0;
            }
            $count++;
        }

        if(!empty($insert_data)){
            $insert_query .= implode(', ', $place_holders);
            $insert_query = $wpdb->prepare($insert_query, $insert_data);
            $insert_count += $wpdb->query($insert_query);
        }

        if(!empty($insert_count)){
            return $insert_count;
        }else{
            return false;
        }
    }

    /**
     * Updates existing data items from extenal sites
     **/
    public static function update_data($data_items, $site_url){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_site_linking_data';
        
        if(empty($data_items)){
            return false;
        }

        $timezone = new DateTimeZone('UTC');
        $site_url = trailingslashit(esc_url_raw($site_url));
        $updated = 0;

        foreach($data_items as $item){

            $timestamp = strtotime($item->post_modified_gmt . ' GMT');

            if(empty($timestamp)){
                continue;
            }

            if(function_exists('wp_date')){
                $gmt_time = wp_date('Y-m-d H:i:s', $timestamp, $timezone);
            }else{
                $gmt_time = date_i18n('Y-m-d H:i:s', $timestamp);
            }
            $type = ($item->type === 'post') ? 'post': 'term';
            $title = sanitize_text_field($item->post_title);
            $stemmed_title = Wpil_Word::getStemmedSentence($title);

            $update = array(
                'post_title' => $title,
                'stemmed_title' => $stemmed_title,
                'post_url' => esc_url_raw($item->post_url),
                'post_modified_gmt' => $gmt_time
            );

            $where = array('site_url' => $site_url, 'post_id' => (int)$item->post_id, 'type' => $type);

            $update_status = $wpdb->update($data_table, $update, $where);

            if(!empty($update_status)){
                $updated += $update_status;
            }
        }

        return $updated;
    }


    /**
     * Obtains data for the incoming request.
     **/
    public static function export_data($page = 0, $limit = 5000){
        global $wpdb;
        $process_terms = !empty(Wpil_Settings::getTermTypes());

        //calculate offset
        $offset = $page * $limit;

        $post_types = "'" . implode("','", Wpil_Settings::getPostTypes()) . "'";

        //get data
        $statuses_query = " AND post_status = 'publish' ";  //Wpil_Query::postStatuses('p');
//        $report_post_ids = Wpil_Query::reportPostIds();
//        $report_term_ids = Wpil_Query::reportTermIds();

        $query = "SELECT p.ID as `post_id`, p.post_title, p.post_type, p.post_date as `post_date`, 'post' as `type`, `post_modified_gmt` 
        FROM {$wpdb->posts} p
            WHERE 1 = 1 $statuses_query AND p.post_type IN ($post_types) ";

        if ($process_terms && !empty($report_term_ids)) {
        $taxonomies = Wpil_Settings::getTermTypes();
        $query .= " UNION
                    SELECT tt.term_id as `post_id`, t.name as `post_title`, tt.taxonomy as `post_type`, NOW() as `post_date`, 'term' as `type`  
                    FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id 
                    WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') ";
        }

        $query .= " ORDER BY post_id 
        LIMIT $offset, $limit";

        $results = $wpdb->get_results($query);

        //calculate total count
        $total_items = Wpil_Report::getTotalItems($query);

        // add the urls to the items
        foreach($results as &$item){
            if($item->type === 'post'){
                $item->post_url = get_the_permalink((int)$item->post_id);
            }else{
                $item->post_url = get_term_link((int)$item->post_id);
            }
        }

        return array('found_items' => $results, 'total' => $total_items);
    }

    /**
     * Coordinates the creating and updating of data items on the network sites
     * 
     * @param int $id The id of the post or term that's just been updated
     * @param mixed $data1 If a post has been updated, $data1 will be the new post object. If a term has been updated, $data1 will be the taxonomy slug
     * @param object|null $data2 If a post has been updated, $data2 will be the post object before the update. If a term has been updated, $data2 will be NULL
     **/
    public static function push_item_update_to_network($id, $data1, $data2 = null){
        // format the item for use in the network sites
        $formatted = self::format_post_object($id, $data1);

        if(empty($formatted)){
            return false;
        }

        // update the sites in the network with the item
        $response = self::update_item_content_on_network($formatted); // we're not listening to the response because the sites don't responde when content is updated.

    }

    /**
     * Coordinates the deleting of data items from the network sites
     **/
    public static function push_item_delete_to_network($id, $post_obj = null){

        if(empty($id)){
            return false;
        }

        $type = 'post';
        if(empty($post_obj)){
            $type = 'term';
        }

        // tell the sites in the network that this item has been deleted
        $response = self::delete_item_content_on_network($id, $type);
    }

    public static function create_initial_request_string($target_url = ''){
        $secret_string = self::get_access_code_1();

        if(empty($secret_string) || empty($target_url)){
            return false;
        }

        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $target_url = trailingslashit($target_url);
        $time = time();

        $hash = hash('sha512', ($secret_string . $target_url . $current_url . $time));

        return array('initok' => $hash, 'time' => $time);
    }

    /**
     * Processes the token from the request string to see if it came from a valid linked site.
     **/
    public static function process_initial_request_string($string = '', $time = 0){
        $linked_sites = self::get_registered_sites();
        $secret_string = self::get_access_code_1();

        if(empty($string) || empty($linked_sites) || empty($secret_string) || empty($time) || ( (time() - $time) > 60) ){
            return false;
        }

        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $calling_site = false;

        // find the site that initiated the call
        foreach($linked_sites as $site){
            $hash = hash('sha512', ($secret_string . $current_url . trailingslashit($site) . $time));
            if($hash === $string){
                $calling_site = $site;
                break;
            }
        }

        // return false if we couldn't find it
        if(empty($calling_site)){
            return false;
        }

        // check the found site's license
        $license_active = Wpil_License::check_site_license($calling_site);

        // if the license is active, return the url
        if($license_active){
            return $calling_site;
        }else{
            return false;
        }
    }

    /**
     * 
     **/
    public static function create_secondary_request_string($target_url = ''){
        $secret_string = self::get_access_code_1();

        if(empty($secret_string) || empty($target_url)){
            return false;
        }

        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $target_url = trailingslashit($target_url);
        $time = time();

        $hash = hash('sha512', ($current_url. $secret_string . $target_url . $time . $time));

        // save the hash for later
        self::update_secondary_request_hash_data($hash, $target_url, $time);

        return array('sectok' => $hash, 'time' => $time);
    }

    /**
     * Processes the secondary confirmation hash and checks to make sure the responding site is licensed.
     * 
     * @param string $hash The secondary hash to process.
     * @param int $time Unix timestamp of the given hash.
     **/
    public static function process_secondary_request_string($string = '', $time = 0){
        $linked_sites = self::get_registered_sites();
        $secret_string = self::get_access_code_1();
        $secondary_secret_string = self::get_access_code_2();

        if( empty($string) || 
            empty($linked_sites) || 
            empty($secret_string) || 
            empty($secondary_secret_string) || 
            empty($time) || 
            ( (time() - $time) > 60) )
        {
            return false;
        }

        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $calling_site = false;

        // find the site that initiated the call
        foreach($linked_sites as $site){
            $hash = hash('sha512', (trailingslashit($site) . $secret_string . $current_url . $time . $time));
            if($hash === $string){
                $calling_site = $site;
                break;
            }
        }

        // return false if we couldn't find it
        if(empty($calling_site)){
            return false;
        }

        // check the found site's license
        $license_active = Wpil_License::check_site_license($calling_site);

        // if the license is active, return the url
        if($license_active){
            return $calling_site;
        }else{
            return false;
        }
    }

    /**
     * Get the stored hash for the current url if it exists.
     * 
     * @param string $target_url The site to lookup the hash for. 
     * @return string|bool Returns the hash if stored, false if it's not.
     **/
    public static function get_secondary_request_hash_data($target_url = ''){
        if(empty($target_url)){
            return false;
        }

        $stored_hashes = get_option('wpil_stored_request_hashes', array());
        $time = time();
        $target_url = trailingslashit(esc_url_raw($target_url));

        // go over all the hashes
        foreach($stored_hashes as $hash_data){
            if($hash_data['target_url'] === $target_url && ($hash_data['expiration'] > $time)){
                // return the stored hash
                return $hash_data;
            }
        }

        return false;
    }

    /**
     * Saves the created secondary hash to the options.
     * Also removes any expired hashes from the cache.
     * 
     * @param string $hash The newly created secondary hash.
     * @param int $time Unix timestamp of the current hash time.
     **/
    public static function update_secondary_request_hash_data($hash, $target_url, $time, $authenticated = false){
        $stored_hashes = get_option('wpil_stored_request_hashes', array());

        $current_time = time();
        $target_url = trailingslashit(esc_url_raw($target_url));

        // remove hashes for the current item or any expired hashes
        $updated_hashes = $stored_hashes;
        foreach($stored_hashes as $key => $hash_data){
            if($hash_data['hash'] === $hash || ($hash_data['expiration'] < $current_time) || $hash_data['target_url'] === $target_url){
                unset($updated_hashes[$key]);
            }
        }

        $updated_hashes[] = array(  'hash' => $hash, 
                                    'expiration' => ($current_time + (9 * MINUTE_IN_SECONDS) ), // set the expiration for 9 mins so we're sure creds are fresh when we call.
                                    'target_url' =>  $target_url, 
                                    'time' => $time, 
                                    'authenticated' => $authenticated
                                );

        update_option('wpil_stored_request_hashes', $updated_hashes);
    }

    /**
     * Creates the final access token used for authenticating the data import.
     * 
     * @param string $secondary_hash The hash string from the responding site.
     * @param string $target_url The url of the site we're sending the url to.
     * @param int $time Unix time stamp of the current request
     **/
    public static function create_access_token($secondary_hash = '', $target_url = '', $time = 0, $page = 0, $query_data = array()){
        $secret_string = self::get_access_code_1();
        $secondary_secret_string = self::get_access_code_2();
        $time = (int) $time;

        if( empty($secret_string) ||
            empty($secondary_hash) || 
            empty($target_url) || 
            empty($secondary_secret_string) || 
            empty($time) || 
            ( (time() - $time) > (10 * MINUTE_IN_SECONDS) )
        ){
            return false;
        }

        $page = (int) $page;
        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $hash = hash('sha512', ($secondary_hash . $time . $secret_string . $secondary_secret_string . $target_url . $page . serialize($query_data)));

        // store the secondary hash so we can make additional tokens if needed
        self::update_secondary_request_hash_data($secondary_hash, $target_url, $time);

        $call_data = array('fintok' => $hash, 'target_url' => $current_url, 'time' => $time, 'page' => $page, 'limit' => self::$query_limit);

        if(!empty($query_data)){
            $call_data = array_merge($call_data, $query_data);
        }

        return $call_data;
    }

    /**
     * Verifies the access token.
     **/
    public static function verify_access_token($token = '', $source_url = '', $time = 0, $page = 0, $query_data = array()){
        $secret_string = self::get_access_code_1();
        $secondary_secret_string = self::get_access_code_2();
        $hash_data = self::get_secondary_request_hash_data($source_url);
        $time = (int) $time;

        if( empty($token) ||
            empty($secret_string) ||
            empty($source_url) || 
            empty($hash_data) || 
            empty($secondary_secret_string) || 
            empty($time) || 
            ( (time() - $time) > (10 * MINUTE_IN_SECONDS) ) 
        ){
            return false;
        }

        $page = (int) $page;
        $current_url = apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url()));
        $hash = hash('sha512', ($hash_data['hash'] . $time . $secret_string . $secondary_secret_string . $current_url . $page . serialize($query_data)));

        if($hash === $token){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Updates the list of linked sites with the supplied site url.
     * Checks to make sure duplicate urls aren't added to the list.
     * 
     * @param string $site_url The url of the site that's been validated and we're saving to the list.
     **/
    public static function update_registered_sites($site_url = ''){
        if(!empty($site_url)){
            $site_url = trailingslashit(esc_url_raw($site_url));
            $sites = self::get_registered_sites();
            $site_exists = false;
            foreach($sites as $site){
                if($site === $site_url){
                    $site_exists = true;
                    break;
                }
            }

            if(!$site_exists){
                $sites[] = $site_url;
                update_option('wpil_registered_sites', $sites);
                return true;
            }else{
                return false;
            }
        }

        return null;
    }

    /**
     * Updates the list of linked sites with the supplied site url.
     * Checks to make sure duplicate urls aren't added to the list.
     * 
     * @param string $site_url The url of the site that's been validated and we're saving to the list.
     **/
    public static function update_linked_sites($site_url = ''){
        if(!empty($site_url)){
            $site_url = trailingslashit(esc_url_raw($site_url));
            $sites = self::get_linked_sites();
            $site_exists = false;
            foreach($sites as $site){
                if($site === $site_url){
                    $site_exists = true;
                    break;
                }
            }

            if(!$site_exists){
                $sites[] = $site_url;
                update_option('wpil_linked_sites', $sites);
            }
        }
    }

    /**
     * Gets an array of all registered sites.
     * A site can be registered in the site linking settings without being successfully linked.
     * 
     * @return array $linked_sites
     **/
    public static function get_registered_sites(){
        $registered_sites = get_option('wpil_registered_sites', array());

        return $registered_sites;
    }

    /**
     * Gets an array of all linked sites.
     * 
     * @return array $linked_sites
     **/
    public static function get_linked_sites(){
        $linked_sites = get_option('wpil_linked_sites', array());

        return $linked_sites;
    }

    /**
     * Removes a site from the linked site list and deletes it's stored data
     * 
     * @param string $site_url The home url of the site that we're removing from the site.
     * @return bool $removed Returns True if the site has been removed, and False if it hasn't been.
     **/
    public static function remove_linked_site($site_url = ''){
        $removed = false;
        if(!empty($site_url)){
            $sites = self::get_linked_sites();
            $found_site = false;
            foreach($sites as $key => $site){
                if($site === $site_url){
                    $found_site = true;
                    unset($sites[$key]);
                }
            }

            // if the site has been found
            if($found_site){
                // clear the site data from the db
                $cleared = self::clear_external_site_data($site_url);
            }

            // update the list
            $removed = update_option('wpil_linked_sites', $sites);

            // and unregister the site
            self::remove_registered_site($site_url);
        }

        return $removed;
    }


    /**
     * Removes a site from the registered site list.
     * 
     * @param string $site_url The home url of the site that we're removing from the registered list.
     * @return bool $removed Returns True if the site has been removed, and False if it hasn't been.
     **/
    public static function remove_registered_site($site_url = ''){
        $unregistered = false;
        if(!empty($site_url)){
            $sites = self::get_registered_sites();
            $found_site = false;
            foreach($sites as $key => $site){
                if($site === $site_url){
                    $found_site = true;
                    unset($sites[$key]);
                }
            }

            if($found_site){
                // update the list
                $unregistered = update_option('wpil_registered_sites', $sites);
            }
        }

        return $unregistered;
    }

    public static function check_if_site_added($site_url = ''){
        if(!empty($site_url)){
            $sites = self::get_linked_sites();
            foreach($sites as $key => $site){
                if($site === $site_url){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets an array of linked site domains
     * @return array $domains An array of linked site domains
     **/
    public static function get_linked_site_domains(){
        $domains = array();
        $sites = self::get_linked_sites();
        foreach($sites as $key => $site){
            $domain = wp_parse_url($site, PHP_URL_HOST);
            if(!empty($domain)){
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Formats a post or term object into the data object used by the site interlinking data table
     **/
    public static function format_post_object($id, $data = null){
        if(empty($id) || empty($data)){
            return false;
        }

        $object = array();

        if(is_a($data, 'WP_Post')){
            // if this isn't a post type or status to process
            if(!in_array($data->post_type, Wpil_Settings::getPostTypes()) || !in_array($data->post_status, Wpil_Settings::getPostStatuses())){
                // return false so we don't push it to the network
                return false;
            }

            $object = array(
                'post_id' => $data->ID,
                'post_type' => $data->post_type,
                'type' => 'post',
                'post_url' => get_the_permalink($data->ID),
                'post_title' => $data->post_title,
                'post_modified_gmt' => $data->post_modified_gmt,
            );
        }else{
            $term = get_term($id, $data);
            if(!empty($term) && !is_a($data, 'WP_Error')){
                // if this isn't a taxonomy to process
                if(!in_array($term->taxonomy, Wpil_Settings::getTermTypes())){
                    // return false so we don't push it to the network
                    return false;
                }

                $object = array(
                    'post_id' => $term->term_id,
                    'post_type' => $term->taxonomy,
                    'type' => 'term',
                    'post_url' => get_term_link($term->term_id),
                    'post_title' => $term->name,
                    'post_modified_gmt' => current_time('mysql', true), 
                );
            }
        }

        $object = (object) $object;

        return $object;

    }

    /**
     * Updates all netword sites with content changes from this one.
     **/
    public static function update_item_content_on_network($item_data = array()){
        if(empty($item_data)){
            return false;
        }

        $query_data = array('update' => '1', 'data' => base64_encode(serialize($item_data))); // in future cases remember that the receiving site gets all types as strings

        $results = self::update_network_sites($query_data);

        return $results;
    }

    /**
     * Deletes an item from the network
     **/
    public static function delete_item_content_on_network($id, $type = 'post'){
        if(empty($id) || empty($type)){
            return false;
        }

        $item_data = array(
            'post_id' => $id,
            'type' => $type
        );

        $query_data = array('delete' => '1', 'data' => base64_encode(serialize((object)$item_data)));

        $results = self::update_network_sites($query_data);

        return $results;
    }

    public static function ajax_register_selected_site(){
        Wpil_Base::verify_nonce('register-site-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('No Url', 'wpil'), 'text' => __('The site url field is empty, please add the url of the site you want to link to.', 'wpil'))));
        }else{
            $url = trailingslashit(wp_http_validate_url($_POST['url']));
            if(empty($url)){
                wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The given url was not in the necessary format. Please enter the url as it appears in your browser\'s address bar, including the protocol (https or http).', 'wpil'))));
            }

            $url = trailingslashit($url);
        }

        // if we have a valid url, check it's license
        $license_active = Wpil_License::check_site_license($url);

        // if it's not valid, tell the user
        if(empty($license_active)){
            wp_send_json(array('error' => array('title' => __('Site License Not Verified', 'wpil'), 'text' => __('The license for the given site could not be verified. Please check to make sure the site is using the same license key as this site.', 'wpil'))));
        }

        // if the license is valid, save the site to the registry
        $saved = self::update_registered_sites($url);

        if($saved){
            $site_link_button = '
            <label>
                <a href="#" class="wpil-link-site-button button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'link-site-nonce') . '">' . __('Attempt Site Linking', 'wpil') . '</a>
                <a href="#" class="wpil-unregister-site-button button-primary button-purple site-linking-button" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unregister-site-nonce') . '">' . __('Unregister Site', 'wpil') . '</a>
            </label>';
            wp_send_json(array('info' => array('title' => __('Site Registered', 'wpil'), 'text' => __("The external site has been registered on this site, please go to the external site and register this site there. \n\n Attempting to link the sites before they are registered with each other will fail.", 'wpil'), 'link_button' => $site_link_button)));
        }elseif($saved === false){
            wp_send_json(array('info' => array('title' => __('Site Already Registered', 'wpil'), 'text' => __('The given site has already been registered on this site.', 'wpil'))));
        }else{
            wp_send_json(array('error' => array('title' => __('Site Not Registered', 'wpil'), 'text' => __('Unfortunately, the site could not be registered.', 'wpil'))));
        }
    }

    /**
     * Performs the site link validating and saving on an ajax call.
     **/
    public static function ajax_link_selected_site(){
        Wpil_Base::verify_nonce('link-site-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('No Url', 'wpil'), 'text' => __('The site url field is empty, please add the url of the site you want to link to.', 'wpil'))));
        }else{
            $url = trailingslashit(wp_http_validate_url($_POST['url']));
            if(empty($url)){
                wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The given url was not in the necessary format. Please enter the url as it appears in your browser\'s address bar, including the protocol (https or http).', 'wpil'))));
            }

            $url = trailingslashit($url);
        }

        // set up the data table if it's not created
        self::create_data_table();

        // if we have a valid url, check it's license
        $license_active = Wpil_License::check_site_license($url);

        // if the license is valid
        if(!empty($license_active)){
            // ping the other site to see if this one has alrady been added
            $ping_result = self::ping_site($url);

            // if the ping attempt was successful
            if($ping_result){
                // create the action buttons
                $import_button = '<a href="#" class="wpil-refresh-post-data button-primary" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'download-site-data-nonce') . '">' . __('Import Post Data', 'wpil') . '</a>';
                $suggestions_button = '<a href="#" class="wpil-external-site-suggestions-toggle button-primary site-linking-button" data-suggestions-enabled="1" data-site-url="' . esc_url($url) . '" data-enable-text="' . __('Enable Suggestions', 'wpil') . '" data-disable-text="' . __('Disable Suggestions', 'wpil') . '" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'toggle-external-site-suggestions-nonce') . '">' . __('Disable Suggestions', 'wpil') . '</a>';
                $unlink_button = '<a href="#" class="wpil-unlink-site-button button-primary button-purple" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'unlink-site-nonce') . '">' . __('Remove Site', 'wpil') . '</a>';
                // save the url to the list of sites
                self::update_linked_sites($url);
                // and tell the user the site is linked
                wp_send_json(array('success' => array('title' => __('Site Linked!', 'wpil'), 'text' => __('The site has been linked to this one and data can be shared between them!', 'wpil'), 'unlink_button' => $unlink_button, 'import_button' => $import_button, 'suggestions_button' => $suggestions_button)));
            }else{
                // if the ping wasn't successful, tell the user about it
                wp_send_json(array('info' => array('title' => __('Site Not Linked', 'wpil'), 'text' => __('The site could not be linked to this one. Please check the "Site Interlinking Access Code" on the external site to make sure it exactly matches the code on this site.', 'wpil'))));
            }
        }else{
            wp_send_json(array('error' => array('title' => __('Site License Not Verified', 'wpil'), 'text' => __('The license for the given site could not be verified. Please check to make sure the site is using the same license key as this site.', 'wpil'))));
        }
    }

    /**
     * Toggles if suggestions will be made to external sites on ajax call
     **/
    public static function ajax_external_site_suggestion_toggle(){
        Wpil_Base::verify_nonce('toggle-external-site-suggestions-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url']) || empty($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('Url Missing', 'wpil'), 'text' => __('The site url was missing from the unregister attempt. Please reload the page and try again.', 'wpil'))));
        }

        $url = wp_http_validate_url($_POST['url']);

        if(empty($url)){
            wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The site url was misformatted. Please reload the page and try again.', 'wpil'))));
        }

        $url = trailingslashit($url);

        $current_status = isset($_POST['suggestions_enabled']) ? (int)$_POST['suggestions_enabled']: 0;

        $no_suggestions_sites = get_option('wpil_disable_external_site_suggestions', array());

        // if suggestions are currently enabled, toggle them off
        if($current_status){
            $no_suggestions_sites[$url] = 1;
        }else{
            if(isset($no_suggestions_sites[$url])){
                unset($no_suggestions_sites[$url]);
            }
        }

        $updated = update_option('wpil_disable_external_site_suggestions', $no_suggestions_sites);

        if($updated){
            if($current_status){
                wp_send_json(array('success' => array('title' => __('Site Suggestions Disabled!', 'wpil'), 'text' => __('Link Whisper will not show suggestions to this external site.'))));
            }else{
                wp_send_json(array('success' => array('title' => __('Site Suggestions Enabled!', 'wpil'), 'text' => __('Link Whisper will now show suggestions to the external site.'))));
            }
        }else{
            wp_send_json(array('info' => array('title' => __('Site Setting Not Saved', 'wpil'), 'text' => __('There was an error that prevented Link Whisper from saving the option. Please reload the page and try again.'))));
        }
    }

    /**
     * Removes a registered site by ajax call
     * 
     **/
    public static function ajax_remove_registered_site(){
        Wpil_Base::verify_nonce('unregister-site-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url']) || empty($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('Url Missing', 'wpil'), 'text' => __('The site url was missing from the unregister attempt. Please reload the page and try again.', 'wpil'))));
        }

        $url = wp_http_validate_url($_POST['url']);

        if(empty($url)){
            wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The site url was misformatted. Please reload the page and try again.', 'wpil'))));
        }

        $url = trailingslashit($url);

        $removed = self::remove_registered_site($url);

        if($removed){
            wp_send_json(array('success' => array('title' => __('Site Unregistered!', 'wpil'), 'text' => __('The external site has been removed from this site\'s list of registered sites.'))));
        }else{
            wp_send_json(array('info' => array('title' => __('Site Could Not Be Unregistered', 'wpil'), 'text' => __('The external site could not be removed from the site list. Please reload the page and try again.'))));
        }
    }

    /**
     * Removes a linked site by ajax call
     * 
     **/
    public static function ajax_remove_linked_site(){
        Wpil_Base::verify_nonce('unlink-site-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url']) || empty($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('Url Missing', 'wpil'), 'text' => __('The site url was missing from the unlink attempt. Please reload the page and try again.', 'wpil'))));
        }

        $url = wp_http_validate_url($_POST['url']);

        if(empty($url)){
            wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The site url was misformatted. Please reload the page and try again.', 'wpil'))));
        }

        $url = trailingslashit($url);

        $removed = self::remove_linked_site($url);

        if($removed){
            wp_send_json(array('success' => array('title' => __('Site Unlinked!', 'wpil'), 'text' => __('The external site has been removed from this site\'s list of sites. Just a reminder, the external site may need to have this site removed from its list as well.'))));
        }else{
            wp_send_json(array('info' => array('title' => __('Site Could Not Be Unlinked', 'wpil'), 'text' => __('The external site could not be removed from the site list. Please reload the page and try again.'))));
        }
    }

    /**
     * Downloads all the post data from a single site by a stepped process.
     **/
    public static function ajax_download_all_posts(){
        Wpil_Base::verify_nonce('download-site-data-nonce');

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!isset($_POST['url']) || empty($_POST['url'])){
            wp_send_json(array('error' => array('title' => __('Url Missing', 'wpil'), 'text' => __('The site url was missing from the unlink attempt. Please reload the page and try again.', 'wpil'))));
        }

        $url = wp_http_validate_url($_POST['url']);

        if(empty($url)){
            wp_send_json(array('error' => array('title' => __('Url Format Error', 'wpil'), 'text' => __('The site url was misformatted. Please reload the page and try again.', 'wpil'))));
        }

        $url = trailingslashit($url);

        // get the secondary hash data to see if we're authenticated
        $hash_data = self::get_secondary_request_hash_data($url);

        // if we're not authenticated, run the auth process
        if(empty($hash_data) || empty($hash_data['authenticated'])){
            $auth = self::authenticate($url);

            // if the site couldn't be authenticated, tell the user about it
            if(empty($auth)){
                wp_send_json(array('error' => array('title' => 'Could Not Authenticate', 'text' => 'Link Whisper was not able to complete the authentication process. This can be caused by not having this site registered on the external one, or if the site identification codes don\'t match between the sites.')));
            }
        }

        // if the site has been authed and this is the first go round, clear the existing site data
        if(isset($_POST['reset']) && !empty($_POST['reset'])){
            $cleared = self::clear_external_site_data($url);
        
            if(empty($cleared)){
                wp_send_json(array('error' => array('title' => 'Could Not Clear Existing Data', 'text' => 'Link Whisper could not erase the site\'s existing post data from the database. Please reload the page and try again.')));
            }
        }

        $page = (int) $_POST['page'];

        $response = self::call_for_data($url, $page);

        $status = array(
            'url'   => $url,
            'page'  => $page,
            'saved' => (isset($_POST['saved']) && !empty($_POST['saved'])) ? (int) $_POST['saved']: 0,
            'total' => (isset($_POST['total']) && !empty($_POST['total'])) ? (int) $_POST['total']: 0,
        );

        if(!empty($response) && isset($response->data) && isset($response->hmac)){
            // check the hash
            if(self::check_hmac($response->data, $page, $url, $response->hmac)){
                // if there are items
                if(!empty($response->data->found_items) && $response->data->total > 0){
                    // save them
                    $saved = self::save_data($response->data->found_items, $url);

                    // update the status vars
                    $status['total'] = $response->data->total;
                    $status['saved'] += $saved;
                    $status['page']  += 1;
                    $status['message'] = sprintf(__('Posts Imported: %d of %d', 'wpil'), $status['saved'], $response->data->total);

                }elseif(empty($response->data->found_items) && $response->data->total > 0){
                    // if we've reached the end of the items, tell the user we're done!
                    wp_send_json(array('success' => array('title' => __('Download complete!', 'wpil'), 'text' => __('All of the post data has been downloaded and saved.', 'wpil'))));
                }
            }else{
                wp_send_json(array('error' => array('title' => __('Data Error', 'wpil'), 'text' => __('There was a problem with the data from the other site. The import could not be run', 'wpil'))));
            }
        }else{
            wp_send_json(array('error' => array('title' => __('Site did not respond', 'wpil'), 'text' => __('Link Whisper was not able to connect to the other site.', 'wpil'))));
        }

        // send the status back around for another processing run
        wp_send_json($status);

    }

    public static function generate_random_id_string(){
        $string = 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium totam rem aperiam eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt neque porro quisquam est qui dolorem ipsum quia dolor sit amet consectetur adipiscingvelit sed quia non numquam do eius modi tempora incididunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam quis nostrumd exercitationem ullam corporis suscipit laboriosam nisi ut aliquid ex ea commodi consequatur';
        $string .= '12478157109868713518973513596817351231354610512753798358757893475757895675972857750912390810238192301938901830918039810923810938102936987598783754875857637453587985775';
        $string = str_shuffle($string);
        $string .= time();
        $string .= md5(str_shuffle($string));
        $string = str_shuffle($string);
        $string = str_replace(' ', '', $string);

        return $string;
    }

    /**
     * Creates an hmac hash for verifying data in transit
     **/
    public static function create_hmac($results, $limit, $page, $target_url){
        $secret_string = self::get_access_code_1();
        $secondary_secret_string = self::get_access_code_2();
        $target_url = trailingslashit(esc_url_raw($target_url));
        $secondary_hash_data = self::get_secondary_request_hash_data($target_url);

        if(empty($secondary_hash_data)){
            return false;
        }

        $data = array(
            $results,
            (int)$limit,
            (int)$page,
            $target_url,
            $secondary_hash_data['hash']
        );

        $hash = hash_hmac('sha512', http_build_query($data), $secret_string . $secondary_secret_string);

        return $hash;
    }

    public static function check_hmac($results, $page, $other_site, $data_hash){
        $secret_string = self::get_access_code_1();
        $secondary_secret_string = self::get_access_code_2();
        $other_site = trailingslashit(esc_url_raw($other_site));
        $secondary_hash_data = self::get_secondary_request_hash_data($other_site);

        if(empty($secondary_hash_data)){
            return false;
        }

        $data = array(
            $results,
            self::$query_limit,
            (int)$page,
            apply_filters('wpil_filter_connected_site_current_url', trailingslashit(site_url())),
            $secondary_hash_data['hash']
        );

        $hash = hash_hmac('sha512', http_build_query($data), $secret_string . $secondary_secret_string);

        return hash_equals($hash, $data_hash);

    }

    public static function is_json($str) {
        $json = json_decode($str);
        return $json && $str != $json;
    }

    /**
     * Gets the first access code from the front half of the inputted access code.
     * To simplify the interface, we store both access codes in the same input and split it here to get the correct peice for the job. 
     **/
    public static function get_access_code_1(){
        $access_code = get_option('wpil_link_external_sites_access_code', false);

        if(empty($access_code)){
            return false;
        }

        $access_code = trim($access_code);

        $length = floor((mb_strlen($access_code) / 2));
        $code1 = mb_substr($access_code, 0, $length);

        return $code1;
    }

    /**
     * Gets the second access code from the back half of the inputted access code.
     * To simplify the interface, we store both access codes in the same input and split it here to get the correct peice for the job. 
     **/
    public static function get_access_code_2(){
        $access_code = get_option('wpil_link_external_sites_access_code', false);

        if(empty($access_code)){
            return false;
        }

        $access_code = trim($access_code);

        $length = floor((mb_strlen($access_code) / 2));
        $code2 = mb_substr($access_code, $length);

        return $code2;
    }
}
?>