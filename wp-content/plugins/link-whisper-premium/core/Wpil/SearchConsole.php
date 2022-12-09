<?php

/**
 * Class for getting and formatting data from Google Search Console
 *
 * Class Wpil_SearchConsole
 */
class Wpil_SearchConsole
{

    private static $is_success = false;     // was the last call successful?
    private static $last_error = '';        // last error when calling a url
    private static $last_response = [];     // last response form caling a url
    private static $last_code = 0;          // the http response code from the last call
    private static $token = '';             // the GSC API access token
    public static $data = [];               // holder for all of the classes transactional data
    public static $profile;                 // the GSC property profile

    /**
     * Set url proxy server
     */
    private const REDIRECT_URI = WPIL_STORE_URL . '/wp-json/link-whisper/auth';

    function __construct(){
        self::set_data();
//        self::set_allowed_origin();
    }

    private static function set_allowed_origin ()
    {
        if ( !empty(self::REDIRECT_URI) ) {
            add_filter('allowed_http_origins', function ( $allowed_origins )
            {
                $parsed            = wp_parse_args(self::REDIRECT_URI);
                $allowed_origins[] = $parsed['scheme'] . '://' . $parsed['host'];

                return $allowed_origins;
            });
        }
    }

    /**
     * Creates the GSC data table if it doesn't already exist.
     * If it does exist, it clears the table data.
     **/
    public static function setup_search_console_table(){
        global $wpdb;
        $wpil_search_console_table = $wpdb->prefix . 'wpil_search_console_data';

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpil_search_console_table}'");
        if($table != $wpil_search_console_table){
            $wpil_search_console_table_query = "CREATE TABLE IF NOT EXISTS {$wpil_search_console_table} (
                                        gsc_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        page_url text,
                                        keywords text,
                                        clicks bigint(20) unsigned NOT NULL,
                                        impressions bigint(20) unsigned NOT NULL,
                                        ctr float,
                                        position float,
                                        scan_date_start datetime,
                                        scan_date_end datetime,
                                        processed tinyint(1) DEFAULT 0,
                                        PRIMARY KEY (gsc_index),
                                        INDEX (page_url(255)),
                                        INDEX (keywords(255))
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_search_console_table_query);

            if (strpos($wpdb->last_error, 'Index column size too large') !== false) {
                $wpil_search_console_table_query = str_replace(array('page_url(255)', 'keywords(255)'), array('page_url(191)', 'keywords(191)'), $wpil_search_console_table_query);
                dbDelta($wpil_search_console_table_query);
            }
        }

        $wpdb->query("TRUNCATE TABLE {$wpil_search_console_table}");
    }

    /**
     * Obtains all of the unique page urls from the search console table that haven't been processed yet.
     * 
     * @return array $urls Array of unique urls
     **/
    public static function get_unprocessed_unique_urls(){
        global $wpdb;
        $search_console_table = $wpdb->prefix . 'wpil_search_console_data';

        $urls = $wpdb->get_results("SELECT DISTINCT(`page_url`) FROM {$search_console_table} WHERE `processed` = 0");

        if(!empty($urls)){
            return $urls;
        }else{
            return array();
        }
    }

    /**
     * Obtains all rows that contain the given url
     **/
    public static function get_rows_by_url($url = ''){
        global $wpdb;
        $search_console_table = $wpdb->prefix . 'wpil_search_console_data';

        if(empty($url)){
            return false;
        }

        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$search_console_table} WHERE `page_url` = %s", $url));

        if(!empty($data)){
            return $data;
        }else{
            return array();
        }
    }

    /**
     * Mark all the rows that have the given url as processed.
     * 
     * @param string $url 
     * 
     * @return bool True on success, False on error
     **/
    public static function mark_rows_processed_by_url($url = ''){
        global $wpdb;
        $search_console_table = $wpdb->prefix . 'wpil_search_console_data';

        if(empty($url)){
            return false;
        }

        $updated = $wpdb->update($search_console_table, array('processed' => 1), array('page_url' => esc_url_raw($url)), array('%d'));

        if(false !== $updated){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Get the Search Console auth url.
     *
     * @return string
     */
    public static function get_auth_url(){
        return Wpil_Settings::getGSCAuthUrl();
    }

    /**
     * Get all profiles for the authorized account.
     *
     * @return array
     */
    public static function get_profiles(){
        $profiles = array();
        $response = self::get_request('https://www.googleapis.com/webmasters/v3/sites');

        if(!self::$is_success || empty($response)){
            return $profiles;
        }

        foreach($response['siteEntry'] as $site){
            $profiles[$site['siteUrl']] = $site['siteUrl'];
        }

        return $profiles;
    }

    /**
     * Get the search console profile for this site.
     * Todo find out if there's any special accounting to do for multisite installs
     *
     * @return array
     */
    public static function get_site_profile(){
        $profiles = self::get_profiles();

        if(empty($profiles)){
            return false;
        }

        $profile_data = (!empty(self::$data) && isset(self::$data['profiles'])) ? self::$data: array('profiles' => array());

        $site_domain = wp_parse_url(get_home_url());
        $domain = str_replace('www.', '', $site_domain['host']);
        $return_profile = false;

        foreach($profiles as $profile){
            $property_domain =  wp_parse_url($profile);

            $scheme_match = false;
            $domain_match = false;
            if(isset($property_domain['scheme']) && isset($property_domain['host'])){
                $scheme_match = ($property_domain['scheme'] === $site_domain['scheme']) ? true: false;
                $domain_match = ($property_domain['host'] === $site_domain['host']) ? true: false;
            }

            if(str_replace('sc-domain:', '', $profile) === $domain || ($scheme_match && $domain_match)){
                $return_profile = $profile;
                break;
            }

            // if there's a stored profile and it matches the current profile
            if(isset($profile_data['profiles']) && !empty($profile_data['profiles']) && in_array($profile, $profile_data['profiles'], true)){
                $return_profile = $profile;
                break;
            }
        }

        update_option('wpil_gsc_profile_not_easily_found', empty($return_profile));

        return $return_profile;
    }

    /**
     * Attempt to exchange a code for an valid authentication token.
     * Helper wrapped around the OAuth 2.0 implementation.
     *
     * @param string $code Authorize code from accounts.google.com.
     *
     * @return array access token
     */
    public static function call_for_access_token($code){
        $config = self::get_config();

        $url = 'https://oauth2.googleapis.com/token';
        $args = array(
            'code'          => $code,
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri'  => $config['redirect_uri'],
            'grant_type'    => 'authorization_code',
            'state'         => $config['state']
        );

        return self::post($url, $args, 15);
    }

    /**
     * Attempt to refresh the access code.
     * Helper wrapped around the OAuth 2.0 implementation.
     *
     * @param string|array $token The token (access token or a refresh token) that should be revoked.
     *
     * @return array access token
     */
    public static function refresh_token($token){
        $config = self::get_config();

        return self::post(
            'https://www.googleapis.com/oauth2/v4/token',
            array(
                'refresh_token' => $token['refresh_token'],
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'grant_type'    => 'refresh_token',
            ),
            15
       );
    }

    /**
     * Revoke an OAuth2 access token or refresh token. This method will revoke the current access
     * token, if a token isn't provided.
     *
     * @param string|array $token The token (access token or a refresh token) that should be revoked.
     *
     * @return boolean Returns True if the revocation was successful, otherwise False.
     */
    public static function revoke_token($token){
        if(is_array($token)){
            $token = isset($token['refresh_token']) ? $token['refresh_token'] : $token['access_token'];
        }

        $url = esc_url_raw(add_query_arg(array('token' => $token), 'https://oauth2.googleapis.com/revoke'));

        self::post($url);

        return self::$is_success;
    }

    /**
     * Make an HTTP GET request - for retrieving data.
     *
     * @param string $url     URL to do request.
     * @param array  $args    Assoc array of arguments (usually your data).
     * @param int    $timeout Timeout limit for request in seconds.
     *
     * @return array|false     Assoc array of API response, decoded from JSON.
     */
    public static function get_request($url, $args = [], $timeout = 10){
        return self::make_request('GET', $url, $args, $timeout);
    }

    /**
     * Make an HTTP POST request - for creating and updating items.
     *
     * @param string $url     URL to do request.
     * @param array  $args    Assoc array of arguments (usually your data).
     * @param int    $timeout Timeout limit for request in seconds.
     *
     * @return array|false     Assoc array of API response, decoded from JSON.
     */
    public static function post($url, $args = [], $timeout = 20){
        return self::make_request('POST', $url, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     *
     * @param string $http_verb The HTTP verb to use: get, post, put, patch, delete.
     * @param string $url       URL to do request.
     * @param array  $args       Assoc array of parameters to be passed.
     * @param int    $timeout    Timeout limit for request in seconds.
     *
     * @return array|false Assoc array of decoded result.
     */
    private static function make_request($http_verb, $url, $args = [], $timeout = 20){
        $params = array(
            'timeout' => $timeout,
            'method'  => $http_verb,
        );

        $params['headers'] = array('Authorization' => 'Bearer ' . self::$token);

        if('DELETE' === $http_verb || 'PUT' === $http_verb){
            $params['headers']['Content-Length'] = '0';
        } elseif('POST' === $http_verb && !empty($args) && is_array($args)){
            $params['body']                    = wp_json_encode($args);
            $params['headers']['Content-Type'] = 'application/json';
        }

        self::reset();
        $response           = wp_remote_request($url, $params);
        $formatted_response = self::format_response($response);
        self::determine_success($response, $formatted_response);

        return $formatted_response;
    }

    /**
     * Decode the response and format any error messages for debugging
     *
     * @param array $response The response from the curl request.
     *
     * @return array|false The JSON decoded into an array
     */
    private static function format_response($response){
        self::$last_response = $response;

        if(is_wp_error($response)){
            return false;
        }

        if(!empty($response['body'])){
            return json_decode($response['body'], true);
        }

        return false;
    }

    /**
     * Check if the response was successful or a failure. If it failed, store the error.
     *
     * @param array       $response           The response from the curl request.
     * @param array|false $formatted_response The response body payload from the curl request.
     */
    private static function determine_success($response, $formatted_response){
        if(is_wp_error($response)){
            self::$last_error = 'WP_Error: ' . $response->get_error_message();
            return;
        }

        self::$last_code = wp_remote_retrieve_response_code($response);
        if(in_array(self::$last_code, array(200, 204), true)){
            self::$is_success = true;
            return;
        }

        if(isset($formatted_response['error_description'])){
            if('Bad Request' === $formatted_response['error_description'] && 'invalid_grant' === $formatted_response['error']){
                self::$last_error = __('Google responded that the code was invalid. Please try re-authorizing the app and re-entering the code supplied by Google.', 'wpil');
            }elseif('Bad Request' === $formatted_response['error_description']){
                self::$last_error = sprintf(__('Something went wrong with the request. The error code was: %d', 'wpil'), self::$last_code);
            }else{
                self::$last_error = $formatted_response['error_description'];
            }

            return self::$last_error;
        }

        self::$last_error = 'Unknown error.';
    }

    /**
     * Get Search Console API config.
     *
     * @return array
     */
    private static function get_config(){
        return Wpil_Settings::getGSCConfiguration();
    }

    /**
     * Save custom Search Console API config.
     *
     * @return bool True on update, False on failure
     */
    public static function save_custom_auth_config($config = array()){
        $saved = update_option('wpil_gsc_custom_config', $config);
        if($saved){
            update_option('wpil_gsc_auth_method', 'custom_auth');
        }
        return $saved;
    }

    /**
     * Clear the stored Search Console API config by ajax call
     **/
    public static function ajax_clear_custom_auth_config(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'clear-gsc-creds')){
            self::clear_custom_auth_config();
        }
    }

    /**
     * Clear custom Search Console API config.
     *
     * @return bool
     */
    public static function clear_custom_auth_config(){
        $deleted = delete_option('wpil_gsc_custom_config');
        if($deleted){
            update_option('wpil_gsc_auth_method', 'standard');
        }
        return $deleted;
    }

    /**
     * Reset request.
     */
    private static function reset(){
        self::$last_code     = 0;
        self::$last_error    = '';
        self::$is_success    = false;
        self::$last_response = [
            'body'    => null,
            'headers' => null,
       ];
    }

    /**
     * Query search console data from google.
     *
     * @param string  $start_date Start date.
     * @param string  $end_date   End date.
     * @param string  $dimension  Dimension of data.
     * @param integer $limit      Number of rows.
     * @param integer $start_row  Row to start from.
     *
     * @return array
     */
    public static function query_console_data($start_date, $end_date, $dimension, $limit = 5000, $start_row = 0){
        if(is_string($dimension) && !empty($dimension)){
            $dimension = array($dimension);
        }elseif(!is_array($dimension) || empty($dimension)){
            return false;
        }

        $response = self::post(
            'https://www.googleapis.com/webmasters/v3/sites/' . urlencode(self::$profile) . '/searchAnalytics/query',
            array(
                'startDate'     => $start_date,
                'endDate'       => $end_date,
                'rowLimit'      => $limit,
                'dimensions'    => $dimension,
                'startRow'      => ($start_row * $limit),
                'searchType'    => 'web'
            )
       );

        $rows = false;
        if(self::$is_success){
            if(isset($response['rows'])){
                // round long numbers to 2 places
                foreach($response['rows'] as &$row){
                    $row['ctr']      = round($row['ctr'] * 100, 2);
                    $row['position'] = round($row['position'], 2);
                }
                $rows = $response['rows'];
            }
        }

        return $rows ? $rows : [];
    }

    /**
     * Fetch access token
     *
     * @param string $code oAuth token.
     *
     * @return array
     */
    public static function get_access_token($code){
        $response = self::call_for_access_token($code);

        if(!self::$is_success){
            return array(
                'access_valid'  => false,
                'message'       => self::$last_error,
            );
        }

        $data = array(
            'authorized'    => true,
            'expire'        => time() + $response['expires_in']
        );

        if(isset($response['access_token']) && !empty($response['access_token'])){
            $data['access_token'] = $response['access_token'];
        }
        
        if(isset($response['refresh_token']) && !empty($response['refresh_token'])){
            $data['refresh_token'] = $response['refresh_token'];
        }

        self::search_console_data($data);

        self::set_data();

        return array(
            'access_valid'  => true,
            'message'       => __('The connection has been verified! Please go to the Target Keyword report and refresh the keywords.', 'wpil')
        );
    }

    /**
     * Refreshes the authentication token if the app has been previously authenticated.
     */
    public static function refresh_auth_token(){
        // Bail if the user is not authenticated at all yet.
        if(!self::is_authenticated() || !self::is_token_expired()){
            return;
        }

        $response = self::refresh_token(self::$data);

        if(!self::$is_success){
            self::disconnect();
            return;
        }

        $data = array(  
            'expire'=> time() + $response['expires_in'],
            'access_token' => $response['access_token']);

        self::search_console_data($data);

        self::set_data();
    }

    /**
     * Disconnects from the Google app on ajax call.
     **/
    public static function ajax_disconnect(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'disconnect-gsc')){
            self::disconnect();
        }
    }

    /**
     * Disconnects from the Google app that's currently authenticated.
     * Also clears any custom config details.
     */
    public static function disconnect(){
        self::revoke_token(self::$data);
        self::search_console_data(false);
        self::search_console_data(array('authorized' => false, 'profiles'   => array()));
        self::clear_custom_auth_config();
        update_option('wpil_gsc_app_authorized', false);
        update_option('wpil_gsc_remote_credentials', array());

        self::set_data();
    }

    /**
     * Check if the current user is authenticated.
     *
     * @return boolean True if the user is authenticated, false otherwise.
     */
    public static function is_authenticated(){
        return self::$data['authorized'] && self::$data['access_token'] && self::$data['refresh_token'];
    }

    /**
     * Check if token is expired.
     *
     * @return boolean
     */
    public static function is_token_expired(){
        return self::$data['expire'] && time() > (self::$data['expire'] - 120);
    }

    /**
     * Set data.
     */
    public static function set_data(){
        self::$data    = self::search_console_data();

        if(isset(self::$data['access_token'])){
            self::$token = self::$data['access_token'];
        }

        self::$profile = self::get_site_profile();

        if(!self::$profile && !empty(self::$data['profiles'])){
            self::$profile = key(self::$data['profiles']);
        }
    }

    /**
     * Get midnight time for the date variables.
     *
     * @param  int $time Timestamp of date.
     * @return int
     */
    public static function get_midnight($time){
        if(is_numeric($time)){
            $time = date_i18n('Y-m-d H:i:s', $time);
        }
        $date = new \DateTime($time);
        $date->setTime(0, 0, 0);

        return $date->getTimestamp();
    }

    /**
     * Get or update Search Console data.
     *
     * @param  bool|array $data Data to save.
     * @return bool|array
     */
    public static function search_console_data($data = null){
        $key          = 'wpil_search_console_data';
        $encrypt_keys = [
            'access_token',
            'refresh_token',
            'profiles',
       ];

        // Clear data.
        if(false === $data){
            delete_option($key);
            return false;
        }

        $saved = get_option($key, []);
        foreach($encrypt_keys as $enc_key){
            if(isset($saved[$enc_key])){
                $saved[$enc_key] = Wpil_Toolbox::deep_decrypt($saved[$enc_key]);
            }
        }

        // Getter.
        if(is_null($data)){
            return wp_parse_args(
                $saved,
                array('authorized' => false, 'profiles' => array())
           );
        }

        // Setter.
        foreach($encrypt_keys as $enc_key){
            if(isset($saved[$enc_key])){
                $saved[$enc_key] = Wpil_Toolbox::deep_encrypt($saved[$enc_key]);
            }
            if(isset($data[$enc_key])){
                $data[$enc_key] = Wpil_Toolbox::deep_encrypt($data[$enc_key]);
            }
        }

        $data = wp_parse_args($data, $saved);
        update_option($key, $data);

        return $data;
    }

    /**
     * Get encryption key.
     *
     * @return string Key.
     */
    public static function get_key(){
        return Wpil_Toolbox::get_key();
    }

    /**
     * Get salt.
     *
     * @return string Salt.
     */
    public static function get_salt(){
        return Wpil_Toolbox::get_salt();
    }

    /**
     * Saves GSC row data to the DB.
     * Doesn't do any housekeeping to make sure that duplicate information isn't being added since
     * it's goal is to insert as much data as fast as possible.
     * 
     * @param array $rows       The row data that was returned from Google.
     * @param array $scan_range The range of dates that the data was scanned across.
     **/
    public static function save_row_data($rows = array(), $scan_range = array()){
        global $wpdb;
        $wpil_search_console_table = $wpdb->prefix . 'wpil_search_console_data';

        if(empty($rows)){
            return false;
        }

        // set up default dates.
        // GSC data is only available when it's around 3 days old.
        // So for a 30 day range, we want to offset the range by 3 days.
        $start_date = date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 33));
        $end_date   = date_i18n('Y-m-d', time() - (DAY_IN_SECONDS * 3));

        if(isset($scan_range['start_date']) && !empty($scan_range['start_date'])){
            $start_date = $scan_range['start_date'];
        }

        if(isset($scan_range['end_date']) && !empty($scan_range['end_date'])){
            $end_date = $scan_range['end_date'];
        }

        $insert_query = "INSERT INTO {$wpil_search_console_table} (page_url, keywords, clicks, impressions, ctr, position, scan_date_start, scan_date_end) VALUES ";
        $initial_query = "INSERT INTO {$wpil_search_console_table} (page_url, keywords, clicks, impressions, ctr, position, scan_date_start, scan_date_end) VALUES ";
        $insert_data = array();
        $place_holders = array();
        $insert_limit = 800;
        $count = 0;
        $total_rows = count($rows);
        $errors = '';
        $insert_count = 0;
        $inserted_list = array();
        foreach($rows as $key => $row){
            if( !isset($row['keys']) ||
                !isset($row['keys'][0]) ||
                !isset($row['keys'][1]))
            {
                continue;
            }

            // note the current keyword data so we can aviod duplicates
            $item_id = $row['keys'][1] . $row['keys'][0];
            if(isset($inserted_list[$item_id])){
                // if the keyword has been saved already, skipp to the next item
                continue;
            }else{
                // if the keyword hasn't been saved yet, note it in the keyword list
                $inserted_list[$item_id] = true;
            }

            array_push(
                $insert_data,
                esc_url_raw($row['keys'][1]),
                $row['keys'][0],
                $row['clicks'],
                $row['impressions'],
                $row['ctr'],
                $row['position'],
                $start_date,
                $end_date
           );
            $place_holders [] = "('%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s')";

            if(($insert_limit === $count) || ($key + 1) >= $total_rows){

                $insert_query .= implode(', ', $place_holders);
                $insert_query = $wpdb->prepare($insert_query, $insert_data);

                $inserted = $wpdb->query($insert_query);

                // reset the query data
                $insert_query = $initial_query;
                $place_holders = array();
                $insert_data = array();
                $count = 0;


                if(!empty($wpdb->last_error)){
                    $errors .= $wpdb->last_error . '<br />';
                }elseif(!empty($inserted)){
                    $insert_count += $inserted;
                }
            }

            $count++;
        }

        return array('inserted' => $insert_count, 'errors' => $errors);
    }

    /**
     * Obtains GSC data from the DB
     * @param string $query_type The type of data query were planning to do. We can query by url, date_range, offset
     * @param array $args The args for the query type.
     * If "url" is the type, the args should be an array of url(s) to query for.
     * If "date_range" is the type, the args should be an array with "start_time" & "end_time" timestring values
     * If "offset" is the type, supply a numeric offset in an array keyed to "offset"
     * "Limit" can be supplied for all arg types
     **/
    public static function get_gsc_data($query_type = array(), $args = array()){
        global $wpdb;

        if(empty($query_type) || empty($args)){
            return false;
        }

        $results = array();
        if('url' === $query_type){

        }elseif('date_range' === $query_type){

        }elseif('offset' === $query_type){

        }

    }
}

new Wpil_SearchConsole;
?>