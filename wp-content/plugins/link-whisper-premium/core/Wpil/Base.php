<?php

/**
 * Base controller
 */
class Wpil_Base
{
    public static $report_menu;
    public static $action_tracker = array();

    /**
     * Register services
     */
    public function register()
    {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'addScripts']);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'));
        add_action('plugin_action_links_' . WPIL_PLUGIN_NAME, [$this, 'showSettingsLink']);
        add_action('upgrader_process_complete', [$this, 'upgrade_complete'], 10, 2);
        add_action('wp_ajax_get_post_suggestions', ['Wpil_Suggestion','ajax_get_post_suggestions']);
        add_action('wp_ajax_wpil_get_external_site_suggestions', ['Wpil_Suggestion', 'ajax_get_external_site_suggestions']);
        add_action('wp_ajax_update_suggestion_display', ['Wpil_Suggestion','ajax_update_suggestion_display']);
        add_action('wp_ajax_wpil_csv_export', ['Wpil_Export','ajax_csv']);
        add_action('wp_ajax_wpil_export_suggestion_data', ['Wpil_Export','ajax_export_suggestion_data']);
        add_action('wp_ajax_wpil_clear_gsc_app_credentials', ['Wpil_SearchConsole','ajax_clear_custom_auth_config']);
        add_action('wp_ajax_wpil_gsc_deactivate_app', ['Wpil_SearchConsole','ajax_disconnect']);
        add_action('wp_ajax_wpil_save_animation_load_status', array('Wpil_Suggestion', 'ajax_save_animation_load_status'));
        add_filter('the_content', array(__CLASS__, 'add_link_attrs'));
        foreach(Wpil_Settings::getPostTypes() as $post_type){
            add_filter("get_user_option_meta-box-order_{$post_type}", [$this, 'group_metaboxes'], 1000, 1 );
            add_filter($post_type . '_row_actions', array(__CLASS__, 'modify_list_row_actions'), 10, 2);
            add_filter( "manage_{$post_type}_posts_columns", array(__CLASS__, 'add_columns'), 11 );
            add_action( "manage_{$post_type}_posts_custom_column", array(__CLASS__, 'columns_contents'), 11, 2);
        }

        foreach(Wpil_Settings::getTermTypes() as $term_type){
            add_filter($term_type . '_row_actions', array(__CLASS__, 'modify_list_row_actions'), 10, 2); // we can only add the row actions. There's no modifying of the columns...
        }
    }

    /**
     * Initial function
     */
    function init()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            return;
        }

        $post = self::getPost();

        if (!empty($_GET['csv_export'])) {
            Wpil_Export::csv();
        }

        if (!empty($_GET['type'])) { // if the current page has a "type" value
            $type = $_GET['type'];

            switch ($type) {
                case 'delete_link':
                    Wpil_Link::delete();
                    break;
                case 'inbound_suggestions_page_container':
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/inbound_suggestions_page_container.php';
                    exit;
                    break;
            }
        }

        if (!empty($_GET['area'])) {
            switch ($_GET['area']) {
                case 'wpil_export':
                    Wpil_Export::getInstance()->export($post);
                    break;
                case 'wpil_excel_export':
                    $post = self::getPost();
                    if (!empty($post)) {
                        Wpil_Excel::exportPost($post);
                    }
                    break;
            }
        }

        if (!empty($_POST['hidden_action'])) {
            switch ($_POST['hidden_action']) {
                case 'wpil_save_settings':
                    Wpil_Settings::save();
                    break;
                case 'activate_license':
                    Wpil_License::activate();
                    break;
            }
        }

        // if we're on a link whisper page
        if(isset($_GET['page']) && 'link_whisper' === $_GET['page']){
            // do a version check
            $version = get_option('wpil_version_check_update', WPIL_PLUGIN_OLD_VERSION_NUMBER);
            // if the plugin update check hasn't run yet
            if($version < WPIL_PLUGIN_VERSION_NUMBER){
                // create any tables that need creating
                self::createDatabaseTables();
                // and make sure the existing tables are up to date
                self::updateTables();
                // note the updated status
                update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
            }
        }


        //add screen options
        add_action("load-" . self::$report_menu, function () {
            add_screen_option( 'report_options', array(
                'option' => 'report_options',
            ) );
        });
    }

    /**
     * This function is used for adding menu and submenus
     *
     *
     * @return  void
     */
    public function addMenu()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            return;
        }

        if (!Wpil_License::isValid()) {
            add_menu_page(
                __('Link Whisper', 'wpil'),
                __('Link Whisper', 'wpil'),
                'manage_categories',
                'link_whisper_license',
                [Wpil_License::class, 'init'],
                plugin_dir_url(__DIR__).'../images/lw-icon-16x16.png'
            );

            return;
        }

        add_menu_page(
            __('Link Whisper', 'wpil'),
            __('Link Whisper', 'wpil'),
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init'],
            plugin_dir_url(__DIR__). '../images/lw-icon-16x16.png'
        );

        if(WPIL_STATUS_HAS_RUN_SCAN){
            $page_title = __('Internal Links Report', 'wpil');
            $menu_title = __('Reports', 'wpil');
        }else{
            $page_title = __('Internal Links Report', 'wpil');
            $menu_title = __('Complete Install', 'wpil');
        }

        self::$report_menu = add_submenu_page(
            'link_whisper',
            $page_title,
            $menu_title,
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init']
        );

        // add the advanced functionality if the first scan has been run
        if(!empty(WPIL_STATUS_HAS_RUN_SCAN)){
            add_submenu_page(
                'link_whisper',
                __('Add Inbound Internal Links', 'wpil'),
                __('Add Inbound Internal Links', 'wpil'),
                'edit_posts',
                'admin.php?page=link_whisper&type=links'
            );

            $autolinks = add_submenu_page(
                'link_whisper',
                __('Auto-Linking', 'wpil'),
                __('Auto-Linking', 'wpil'),
                'manage_categories',
                'link_whisper_keywords',
                [Wpil_Keyword::class, 'init']
            );

            //add autolink screen options
            add_action("load-" . $autolinks, function () {
                add_screen_option( 'wpil_keyword_options', array( // todo possibly update 'keywords' to 'autolink' to avoid confusion
                    'option' => 'wpil_keyword_options',
                ) );
            });

            $target_keywords = add_submenu_page(
                'link_whisper',
                __('Target Keywords', 'wpil'),
                __('Target Keywords', 'wpil'),
                'manage_categories',
                'link_whisper_target_keywords',
                [Wpil_TargetKeyword::class, 'init']
            );

            //add target keyword screen options
            add_action("load-" . $target_keywords, function () {
                add_screen_option( 'target_keyword_options', array(
                    'option' => 'target_keyword_options',
                ) );
            });

            add_submenu_page(
                'link_whisper',
                __('URL Changer', 'wpil'),
                __('URL Changer', 'wpil'),
                'manage_categories',
                'link_whisper_url_changer',
                [Wpil_URLChanger::class, 'init']
            );
        }
        add_submenu_page(
            'link_whisper',
            __('Settings', 'wpil'),
            __('Settings', 'wpil'),
            'manage_categories',
            'link_whisper_settings',
            [Wpil_Settings::class, 'init']
        );
    }

    /**
     * Get post or term by ID from GET or POST request
     *
     * @return Wpil_Model_Post|null
     */
    public static function getPost()
    {
        if (!empty($_REQUEST['term_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['term_id'], 'term');
        } elseif (!empty($_REQUEST['post_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['post_id']);
        } else {
            $post = null;
        }

        return $post;
    }

    /**
     * Show plugin version
     *
     * @return string
     */
    public static function showVersion()
    {
        $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');

        return "<p style='float: right'>version <b>".$plugin_data['Version']."</b></p>";
    }

    /**
     * Show extended error message
     *
     * @param $errno
     * @param $errstr
     * @param $error_file
     * @param $error_line
     */
    public static function handleError($errno, $errstr, $error_file, $error_line)
    {
        if (stristr($errstr, "WordPress could not establish a secure connection to WordPress.org")) {
            return;
        }

        $file = 'n/a';
        $func = 'n/a';
        $line = 'n/a';
        $debugTrace = debug_backtrace();
        if (isset($debugTrace[1])) {
            $file = isset($debugTrace[1]['file']) ? $debugTrace[1]['file'] : 'n/a';
            $line = isset($debugTrace[1]['line']) ? $debugTrace[1]['line'] : 'n/a';
        }
        if (isset($debugTrace[2])) {
            $func = $debugTrace[2]['function'] ? $debugTrace[2]['function'] : 'n/a';
        }

        $out = "call from <b>$file</b>, $func, $line";

        $trace = '';
        $bt = debug_backtrace();
        $sp = 0;
        foreach($bt as $k=>$v) {
            extract($v);

            $args = '';
            if (isset($v['args'])) {
                $args2 = array();
                foreach($v['args'] as $k => $v) {
                    if (!is_scalar($v)) {
                        $args2[$k] = "Array";
                    }
                    else {
                        $args2[$k] = $v;
                    }
                }
                $args = implode(", ", $args2);
            }

            $file = substr($file,1+strrpos($file,"/"));
            $trace .= str_repeat("&nbsp;",++$sp);
            $trace .= "file=<b>$file</b>, line=$line,
									function=$function(".
                var_export($args, true).")<br>";
        }

        $out .= $trace;

        echo "<b>Error:</b> [$errno] $errstr - $error_file:$error_line<br><br><hr><br><br>$out";
    }

    /**
     * Add meta box to the post edit page
     */
    public static function addMetaBoxes()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            return;
        }

        if (Wpil_License::isValid())
        {
            $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
            if ($post_id) {
                // exit if the post has been ignored
                $completely_ignored = Wpil_Settings::get_completely_ignored_pages();
                if(!empty($completely_ignored) && in_array('post_' . $post_id, $completely_ignored, true)){
                    return;
                }
            }

            add_meta_box('wpil_target-keywords', 'Link Whisper Target Keywords', [Wpil_Base::class, 'showTargetKeywordsBox'], Wpil_Settings::getPostTypes());
            add_meta_box('wpil_link-articles', 'Link Whisper Suggested Links', [Wpil_Base::class, 'showSuggestionsBox'], Wpil_Settings::getPostTypes());
        }
    }

    /**
     * Show meta box on the post edit page
     */
    public static function showSuggestionsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        $manually_trigger_suggestions = !empty(get_option('wpil_manually_trigger_suggestions', false));
        if ($post_id) {
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_v2.php';
        }
    }

    /**
     * Show the target keyword metabox on the post edit screen
     */
    public static function showTargetKeywordsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        if ($post_id) {
            $keyword_sources = Wpil_TargetKeyword::get_active_keyword_sources();
            $keywords = Wpil_TargetKeyword::get_keywords_by_post_ids($post_id);
            $post = new Wpil_Model_Post($post_id, 'post');
            $is_metabox = true;
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/target_keyword_list.php';
        }
    }

    /**
     * Makes sure the link suggestions and the target keyword metaboxes are in the same general grouping
     **/
    public static function group_metaboxes($option){
        // if there are no grouping settings, exit
        if(empty($option)){
            return $option;
        }

        $has_target_keyword = false;
        $suggestion_box = '';
        foreach($option as $position => $boxes){
            if(false !== strpos($boxes, 'wpil_target-keywords')){
                $has_target_keyword = true;
            }

            if(false !== strpos($boxes, 'wpil_link-articles')){
                $suggestion_box = $position;
            }
        }
        
        // if the target keyword box hasn't been set yet, but the suggestion box has
        if(empty($has_target_keyword) && !empty($suggestion_box)){
            // place the target keyword box above the suggestion box
            $option[$suggestion_box] = str_replace('wpil_link-articles', 'wpil_target-keywords,wpil_link-articles', $option[$suggestion_box]);
        }

        return $option;
    }

    /**
     * Add scripts to the admin panel
     *
     * @param $hook
     */
    public static function addScripts($hook)
    {
        if (strpos($_SERVER['REQUEST_URI'], '/post.php') !== false || strpos($_SERVER['REQUEST_URI'], '/term.php') !== false || (!empty($_GET['page']) && $_GET['page'] == 'link_whisper')) {
            if(function_exists('wp_enqueue_editor')){
                wp_enqueue_editor();
            }
        }

        wp_register_script('wpil_base64', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/base64.js', array(), false, true);
        wp_enqueue_script('wpil_base64');

        wp_register_script('wpil_sweetalert_script_min', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/sweetalert.min.js', array('jquery'), $ver=false, true);
        wp_enqueue_script('wpil_sweetalert_script_min');

        $js_path = 'js/wpil_admin.js';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
        $ver = filemtime($f_path);
        $current_screen = get_current_screen();

        wp_register_script('wpil_admin_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery', 'wpil_base64'), $ver, true);
        wp_enqueue_script('wpil_admin_script');

        // IF
        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && ($_GET['type'] == 'inbound_suggestions_page' ||  // on the Inbound Suggestions page
            $_GET['type'] == 'click_details_page') ||                                                                                           // or the Detailed Click Report page
            (!empty($current_screen) && ('post' === $current_screen->base || 'page' === $current_screen->base))                                 // or a post edit screen
        ){
            wp_register_style('wpil_daterange_picker_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/daterangepicker.css');
            wp_enqueue_style('wpil_daterange_picker_css');
            wp_register_style('wpil_select2_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/select2.min.css');
            wp_enqueue_style('wpil_select2_css');
            wp_register_script('wpil_moment', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/moment.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_moment');
            wp_register_script('wpil_daterange_picker', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/daterangepicker.js', array('jquery', 'wpil_moment'), $ver, true);
            wp_enqueue_script('wpil_daterange_picker');
            wp_register_script('wpil_select2', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/select2.full.min.js', array('jquery'), $ver, true); // Todo: remove the select2.min.js file when we pass 2.2.0
            wp_enqueue_script('wpil_select2');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'links') {
            wp_register_script('wpil_report', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_report.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_report');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'error') {
            wp_register_script('wpil_error', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_error.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_error');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'domains') {
            wp_register_script('wpil_domains', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_domains.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_domains');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && ( $_GET['type'] == 'click_details_page' || $_GET['type'] == 'clicks')) {
            wp_register_script('wpil_click', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_click.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_click');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper_keywords') {
            wp_register_script('wpil_keyword', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_keyword.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_keyword');
            wp_register_script('wpil_papa_parse', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/papaparse.min.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_papa_parse');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper_url_changer') {
            wp_register_script('wpil_keyword', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_url_changer.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_keyword');
        }

        if (isset($_GET['page']) && ($_GET['page'] == 'link_whisper_target_keywords' || $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] === 'inbound_suggestions_page') || ('post' === $current_screen->base || 'term' === $current_screen->base) ) {
            wp_register_script('wpil_target_keyword', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_target_keyword.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_target_keyword');
        }

        if(isset($_GET['page']) && ($_GET['page'] == 'link_whisper_settings')){
            $js_path = 'js/wpil_admin_settings.js';
            $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
            $ver = filemtime($f_path);
    
            wp_register_script('wpil_admin_settings_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery', 'wpil_select2'), $ver, true);
            wp_enqueue_script('wpil_admin_settings_script');

            wp_register_style('wpil_select2_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/select2.min.css');
            wp_enqueue_style('wpil_select2_css');
            wp_register_script('wpil_select2', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/select2.full.min.js', array('jquery'), $ver, true); // Todo: remove the select2.min.js file when we pass 2.2.0
            wp_enqueue_script('wpil_select2');
        }

        $style_path = 'css/wpil_admin.css';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$style_path;
        $ver = filemtime($f_path);

        wp_register_style('wpil_admin_style', WP_INTERNAL_LINKING_PLUGIN_URL.$style_path, $deps=[], $ver);
        wp_enqueue_style('wpil_admin_style');

        $disable_fonts = apply_filters('wpil_disable_fonts', false); // we've only got one font ATM
        if(empty($disable_fonts)){
            $style_path = 'css/wpil_fonts.css';
            $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$style_path;
            $ver = filemtime($f_path);

            wp_register_style('wpil_admin_fonts', WP_INTERNAL_LINKING_PLUGIN_URL.$style_path, $deps=[], $ver);
            wp_enqueue_style('wpil_admin_fonts');
        }

        $ajax_url = admin_url('admin-ajax.php');

        $script_params = [];
        $script_params['ajax_url'] = $ajax_url;
        $script_params['completed'] = __('completed', 'wpil');
        $script_params['site_linking_enabled'] = (!empty(get_option('wpil_link_external_sites', false))) ? 1: 0;

        $script_params["WPIL_OPTION_REPORT_LAST_UPDATED"] = get_option(WPIL_OPTION_REPORT_LAST_UPDATED);

        wp_localize_script('wpil_admin_script', 'wpil_ajax', $script_params);
    }

    /**
     * Enqueues the scripts to use on the frontend.
     **/
    public static function enqueue_frontend_scripts(){
        global $post;

        // TODO: Add an option to disable the frontend scripts.
        if(empty($post) || !Wpil_License::isValid()){
            return;
        }

        // get if the links are to be opened in new tabs
        $open_with_js       = (!empty(get_option('wpil_js_open_new_tabs', false))) ? 1: 0;
        $open_all_intrnl    = (!empty(get_option('wpil_open_all_internal_new_tab', false))) ? 1: 0;
        $open_all_extrnl    = (!empty(get_option('wpil_open_all_external_new_tab', false))) ? 1: 0;

        // and if the user has disabled click tracking
        $dont_track_clicks = (!empty(get_option('wpil_disable_click_tracking', false))) ? 1: 0;

        // if none of them are, exit
        if( ($open_with_js == 0 || $open_all_intrnl == 0 && $open_all_extrnl == 0) && $dont_track_clicks == 1){
            return;
        }

        // put together the ajax variables
        $ajax_url = get_site_url(null, 'wp-admin/admin-ajax.php', 'relative');
        $script_params = [];
        $script_params['ajaxUrl'] = $ajax_url;
        $script_params['postId'] = $post->ID;
        $script_params['postType'] = (is_a($post, 'WP_Term')) ? 'term': 'post'; // todo find out if the post can be a term, or if it's always a post. // I need to know for link tracking on term pages...
        $script_params['openInternalInNewTab'] = $open_all_intrnl;
        $script_params['openExternalInNewTab'] = $open_all_extrnl;
        $script_params['disableClicks'] = $dont_track_clicks;
        $script_params['openLinksWithJS'] = $open_with_js;
        $script_params['trackAllElementClicks'] = !empty(get_option('wpil_track_all_element_clicks', 0)) ? 1: 0;


        // output some actual localizations
        $script_params['clicksI18n'] = array(
            'imageNoText'   => __('Image in link: No Text', 'wpil'),
            'imageText'     => __('Image Title: ', 'wpil'),
            'noText'        => __('No Anchor Text Found', 'wpil'),
        );

        // enqueue the frontend scripts
        $filename = (true) ? 'frontend.min.js': 'frontend.js';

        $file_path = WP_INTERNAL_LINKING_PLUGIN_DIR . 'js/' . $filename;
        $url_path  = WP_INTERNAL_LINKING_PLUGIN_URL . 'js/' . $filename;
        wp_enqueue_script('wpil-frontend-script', $url_path, array(), filemtime($file_path), true);

        // output the ajax variables
        wp_localize_script('wpil-frontend-script', 'wpilFrontend', $script_params);
    }

    /**
     * Show settings link on the plugins page
     *
     * @param $links
     * @return array
     */
    public static function showSettingsLink($links)
    {
        if(class_exists('Wpil_License') && !Wpil_License::isValid()){
            $links[] = '<a href="admin.php?page=link_whisper_license">Activate License</a>';
        }else{
            $links[] = '<a href="admin.php?page=link_whisper_settings">Settings</a>';
        }

        return $links;
    }

    /**
     * Loads default LinkWhisper settings in to database on plugin activation.
     */
    public static function activate()
    {
        // only set default option values if the options are empty
        if('' === get_option(WPIL_OPTION_LICENSE_STATUS, '')){
            update_option(WPIL_OPTION_LICENSE_STATUS, '');
        }
        if('' === get_option(WPIL_OPTION_LICENSE_KEY, '')){
            update_option(WPIL_OPTION_LICENSE_KEY, '');
        }
        if('' === get_option(WPIL_OPTION_LICENSE_DATA, '')){
            update_option(WPIL_OPTION_LICENSE_DATA, '');
        }
        if('' === get_option(WPIL_OPTION_IGNORE_NUMBERS, '')){
            update_option(WPIL_OPTION_IGNORE_NUMBERS, '1');
        }
        if('' === get_option(WPIL_OPTION_POST_TYPES, '')){
            update_option(WPIL_OPTION_POST_TYPES, ['post', 'page']);
        }
        if('' === get_option(WPIL_OPTION_LINKS_OPEN_NEW_TAB, '')){
            update_option(WPIL_OPTION_LINKS_OPEN_NEW_TAB, '0');
        }
        if('' === get_option(WPIL_OPTION_DEBUG_MODE, '')){
            update_option(WPIL_OPTION_DEBUG_MODE, '0');
        }
        if('' === get_option(WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE, '')){
            update_option(WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE, '0');
        }
        if('' === get_option(WPIL_OPTION_IGNORE_WORDS, '')){
            // if there's no ignore words, configure the language settings
            update_option('wpil_selected_language', Wpil_Settings::getSiteLanguage());
            $ignore = "-\r\n" . implode("\r\n", Wpil_Settings::getIgnoreWords()) . "\r\n-";
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore);
        }
        if('' === get_option(WPIL_LINK_TABLE_IS_CREATED, '')){
            Wpil_Report::setupWpilLinkTable(true);
            // if the plugin is activating and the link table isn't set up, assume this is a fresh install
            update_option('wpil_fresh_install', true); // the link table was created with ver 0.8.3 and was the first major table event, so it should be a safe test for new installs
        }
        if('' === get_option('wpil_install_date', '')){
            // set the install date since it may come in handy
            update_option('wpil_install_date', current_time('mysql', true));
        }

        Wpil_Link::removeLinkClass();

        self::createDatabaseTables();
        self::updateTables();
        // note the updated status
        update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
    }

    /**
     * Runs any update routines after the plugin has been updated.
     */
    public static function upgrade_complete($upgrader_object, $options){
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            // Go through each plugin to see if Link Whisper was updated
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == WPIL_PLUGIN_NAME ) {
                    // create any tables that need creating
                    self::createDatabaseTables();
                    // and make sure the existing tables are up to date
                    self::updateTables();
                    // note the updated status
                    update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
                }
            }
        }
    }

    /**
     * Updates the existing LW data tables with changes as we add them.
     * Does a version check to see if any DB tables have been updated since the last time this was run.
     * 
     * @param bool $force_update Setting $force_update to true will ignore the version checks and run all update steps
     */
    public static function updateTables($force_update = false){
        global $wpdb;

        $autolink_tbl = $wpdb->prefix . 'wpil_keyword_links';
        $autolink_rule_tbl = $wpdb->prefix . 'wpil_keywords';
        $broken_link_tbl = $wpdb->prefix . 'wpil_broken_links';
        $report_links_tbl = $wpdb->prefix . 'wpil_report_links';
        $target_keyword_tbl = $wpdb->prefix . 'wpil_target_keyword_data';
        $url_changer_tbl = $wpdb->prefix . 'wpil_urls';
        $url_links_tbl = $wpdb->prefix . 'wpil_url_links';
        $click_tracking_tbl = $wpdb->prefix . 'wpil_click_data';

        $fresh_install = get_option('wpil_fresh_install', false);

        // if the DB is up to date, exit
        if(WPIL_STATUS_SITE_DB_VERSION === WPIL_STATUS_PLUGIN_DB_VERSION && !$force_update){
            return;
        }

        // if this is a fresh install of the plugin and not a forced update
        if($fresh_install && empty(WPIL_STATUS_SITE_DB_VERSION) && !$force_update){
            // set the DB version as the latest since all the created tables will be up to date
            update_option('wpil_site_db_version', WPIL_STATUS_PLUGIN_DB_VERSION);
            update_option('wpil_fresh_install', false);
            // and exit
            return;
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 0.9 || $force_update){
            // Added in v1.0.0
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a last_checked col
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'last_checked'");
                if(empty($col)){
                    // if it doesn't, add it and a check_count col to the table
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN check_count INT(2) DEFAULT 0 AFTER created, ADD COLUMN last_checked DATETIME NOT NULL DEFAULT NOW() AFTER created";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('wpil_site_db_version', '0.9');
        }

        // if the current DB version is less than 1.0, run the 1.0 update
        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.0 || $force_update){
            /** added in v1.0.1 **/
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a ignore_link col
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'ignore_link'");
                if(empty($col)){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN ignore_link tinyint(1) DEFAULT 0 AFTER `check_count`";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('wpil_site_db_version', '1.0');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.16 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'sentence'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN sentence varchar(1000) AFTER `ignore_link`";
                    $wpdb->query($update_table);
                }
            }

            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'location'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN location varchar(20) AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.16');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.17 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_tbl} LIKE 'anchor'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_tbl} ADD COLUMN anchor text AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.17');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.18 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restrict_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restrict_cats tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restricted_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restricted_cats text AFTER `restrict_cats`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.18');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.19 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restrict_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restrict_date tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restricted_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restricted_date DATETIME AFTER `restrict_date`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.19');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.20 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'select_links'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN select_links tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            // make sure the possible links table is created too
            Wpil_Keyword::preparePossibleLinksTable();

            update_option('wpil_site_db_version', '1.20');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.21 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'set_priority'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN set_priority tinyint(1) DEFAULT 0 AFTER `select_links`";
                    $wpdb->query($update_table);
                }
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'priority_setting'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN priority_setting int DEFAULT 0 AFTER `set_priority`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.21');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.22 || $force_update){
            $changed_urls_exist = $wpdb->query("SHOW TABLES LIKE '{$url_links_tbl}'");
            if(!empty($changed_urls_exist)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_links_tbl} LIKE 'relative_link'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_links_tbl} ADD COLUMN relative_link tinyint(1) DEFAULT 0 AFTER `anchor`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.22');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.23 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'broken_link_scanned'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN broken_link_scanned tinyint(1) DEFAULT 0 AFTER `location`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.23');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.24 || $force_update){
            $trgt_kword_tbl_exists = $wpdb->query("SHOW TABLES LIKE '$target_keyword_tbl'");
            if(!empty($trgt_kword_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM $target_keyword_tbl LIKE 'auto_checked'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE $target_keyword_tbl ADD COLUMN auto_checked tinyint(1) DEFAULT 0 AFTER `save_date`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.24');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.25 || $force_update){
            $clk_tbl_exists = $wpdb->query("SHOW TABLES LIKE '$click_tracking_tbl'");
            if(!empty($clk_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM $click_tracking_tbl LIKE 'link_location'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE $click_tracking_tbl ADD COLUMN link_location varchar(64) DEFAULT 'Body Content' AFTER `link_anchor`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.25');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.26 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'case_sensitive'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN case_sensitive tinyint(1) DEFAULT 0 AFTER `restricted_cats`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.26');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.27 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'force_insert'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN force_insert tinyint(1) DEFAULT 0 AFTER `case_sensitive`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.27');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.28 || $force_update){
            $url_changer_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$url_changer_tbl}'");
            if(!empty($url_changer_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_changer_tbl} LIKE 'wildcard_match'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_changer_tbl} ADD COLUMN wildcard_match tinyint(1) DEFAULT 0 AFTER `new`";
                    $wpdb->query($update_table);
                }
            }

            $url_links_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$url_links_tbl}'");
            if(!empty($url_links_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_links_tbl} LIKE 'original_url'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_links_tbl} ADD COLUMN original_url text NOT NULL AFTER `anchor`";
                    $wpdb->query($update_table);
                }
            }

            $broken_link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($broken_link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'anchor'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN anchor text NOT NULL AFTER `sentence`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.28');
        }

        // todo create a database index for click tracking's user_ip column if people find that it takes too long to load the user_ip view
/*
        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.23 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'broken_link_scanned'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN broken_link_scanned tinyint(1) DEFAULT 0 AFTER `location`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.23');
        }*/
    }


    /**
     * Modifies the post's row actions to add an "Add Inbound Links" button to the row actions.
     * Only adds the link to post types that we create links for.
     * 
     * @param $actions
     * @param $object
     * @return $actions
     **/
    public static function modify_list_row_actions( $actions, $object ) {
        $type = is_a($object, 'WP_Post') ? $object->post_type: $object->taxonomy;

        if(!in_array($type, Wpil_Settings::getAllTypes())){
            return $actions;
        }

        $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? '&paged=' . (int)$_GET['paged']: '';

        if(is_a($object, 'WP_Post')){
            $actions['wpil-add-inbound-links'] = '<a target=_blank href="' . admin_url("admin.php?post_id={$object->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode(admin_url("edit.php?post_type={$type}{$page}&direct_return=1"))) . '">Add Inbound Links</a>';
        }else{
            global $wp_taxonomies;
            $post_type = $wp_taxonomies[$type]->object_type[0];
            $actions['wpil-add-inbound-links'] = '<a target=_blank href="' . admin_url("admin.php?term_id={$object->term_id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode(admin_url("edit-tags.php?taxonomy={$type}{$page}&post_type={$post_type}&direct_return=1"))) . '">Add Inbound Links</a>';
        }

        return $actions;
    }

	/**
	 * Add new columns for SEO title, description and focus keywords.
	 *
	 * @param array $columns Array of column names.
	 *
	 * @return array
	 */
	public static function add_columns($columns){
		global $post_type;

        if(!in_array($post_type, Wpil_Settings::getPostTypes())){
            return $columns;
        }
        
		$columns['wpil-link-stats'] = esc_html__('Link Stats', 'wpil');

		return $columns;
	}

    /**
	 * Add content for custom column.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public static function columns_contents($column_name, $post_id){
        if('wpil-link-stats' === $column_name){
            $post_status = get_post_status($post_id);
            // exit if the current post is in a status we don't process
            if(!in_array($post_status, Wpil_Settings::getPostStatuses())){
                $status_obj = get_post_status_object($post_status);
                $status = (!empty($status_obj)) ? $status_obj->label: ucfirst($post_status);
                ?>
                <span class="wpil-link-stats-column-display wpil-link-stats-content">
                    <strong><?php _e('Links: ', 'wpil'); ?></strong>
                    <span><span><?php echo sprintf(__('%s post processing %s.', 'wpil'), $status, '<a href="' . admin_url("admin.php?page=link_whisper_settings") . '">' . __('not set', 'wpil') . '</a>'); ?></span></span>
                </span>
                <?php
                return;
            }

            $post = new Wpil_Model_Post($post_id);
            $post_scanned = !empty(get_post_meta($post_id, 'wpil_sync_report3', true));
            $inbound_internal = (int)get_post_meta($post_id, 'wpil_links_inbound_internal_count', true);
            $outbound_internal = (int)get_post_meta($post_id, 'wpil_links_outbound_internal_count', true);
            $outbound_external = (int)get_post_meta($post_id, 'wpil_links_outbound_external_count', true);
            $broken_links = Wpil_Error::getBrokenLinkCountByPostId($post_id);

            $post_type = get_post_type($post_id);
            $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? '&paged=' . (int)$_GET['paged']: '';
            ?>
            <span class="wpil-link-stats-column-display wpil-link-stats-content">
                <?php if($post_scanned){ ?>
                <strong><?php _e('Links: ', 'wpil'); ?></strong>
                <span title="<?php _e('Inbound Internal Links', 'wpil'); ?>"><a target=_blank href="<?php echo admin_url("admin.php?post_id={$post_id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode(admin_url("admin.php/edit.php?post_type={$post_type}{$page}"))); ?>"><span class="dashicons dashicons-arrow-down-alt"></span><span><?php echo $inbound_internal; ?></span></a></span>
                <span class="divider"></span>
                <span title="<?php _e('Outbound Internal Links', 'wpil'); ?>"><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><span class="dashicons dashicons-external  <?php echo (!empty($outbound_internal)) ? 'wpil-has-outbound': ''; ?>"></span> <span><?php echo $outbound_internal; ?></span></a></span>
                <span class="divider"></span>
                <span title="<?php _e('Outbound External Links', 'wpil'); ?>"><span class="dashicons dashicons-admin-site-alt3 <?php echo (!empty($outbound_external)) ? 'wpil-has-outbound': ''; ?>"></span> <span><?php echo $outbound_external; ?></span></span>
                <span class="divider"></span>
                <?php if(!empty($broken_links)){ ?>
                <span title="<?php _e('Broken Links', 'wpil'); ?>"><a target=_blank href="<?php echo admin_url("admin.php?page=link_whisper&type=error&post_id={$post_id}"); ?>"><span class="dashicons dashicons-editor-unlink broken-links"></span> <span><?php echo $broken_links; ?></span></a></span>
                <?php }else{ ?>
                <span title="<?php _e('Broken Links', 'wpil'); ?>"><span class="dashicons dashicons-editor-unlink"></span> <span>0</span></span>
                <?php } ?>
                <?php }else{ ?>
                    <?php $scan_link = (empty(get_option('wpil_has_run_initial_scan', false))) ? admin_url("admin.php?page=link_whisper"): $post->getLinks()->refresh; ?>
                    <strong><?php _e('Links: Not Scanned', 'wpil'); ?></strong>
                    <span title="<?php _e('Scan Links', 'wpil'); ?>"><a target=_blank href="<?php echo esc_url($scan_link); ?>"><span><?php _e('Scan Links', 'wpil'); ?></span> <span class="dashicons dashicons-update-alt wpil-refresh-links"></span></a></span>
                <?php } ?>
            </span>
        <?php
        }
	}

    /**
     * Filters the post content to make links open in new tabs if they don't already.
     * Differentiates between internal and external links.
     * @param string $content 
     * @return string $content 
     **/
    public static function open_links_in_new_tabs($content = ''){

        $open_all_intrnl = !empty(get_option('wpil_open_all_internal_new_tab', false));
        $open_all_extrnl = !empty(get_option('wpil_open_all_external_new_tab', false));

        if($open_all_intrnl || $open_all_extrnl){
            preg_match_all( '/<(a\s[^>]*?href=[\'"]([^\'"]*?)[\'"][^>]*?)>/', $content, $matches );

            foreach($matches[0] as $key => $link){
                // if the link already opens in a new tab, skip to the next link
                if(false !== strpos($link, 'target="_blank"')){
                    continue;
                }

                $internal = Wpil_Link::isInternal($matches[2][$key]);

                if($internal && $open_all_intrnl){
                    $new_link = str_replace($matches[1][$key], $matches[1][$key] . ' target="_blank"', $link);
                    $content = mb_ereg_replace(preg_quote($link), $new_link, $content);
                }elseif(!$internal && $open_all_extrnl){
                    $new_link = str_replace($matches[1][$key], $matches[1][$key] . ' target="_blank"', $link);
                    $content = mb_ereg_replace(preg_quote($link), $new_link, $content);
                }
            }
        }

        return $content;
    }

    /**
     * Filters the post content to make links open in new tabs if they don't already.
     * Differentiates between internal and external links.
     * @param string $content 
     * @return string $content 
     **/
    public static function add_link_attrs($content = ''){
        global $post;

        $open_all_intrnl    = !empty(get_option('wpil_open_all_internal_new_tab', false));
        $open_all_extrnl    = !empty(get_option('wpil_open_all_external_new_tab', false));
        $same_all_intrnl    = !empty(get_option('wpil_open_all_internal_same_tab', false));
        $same_all_extrnl    = !empty(get_option('wpil_open_all_external_same_tab', false));
        $no_follow          = !empty(get_option('wpil_add_nofollow', false));

        $ignore_nofollow_domains = ($no_follow) ? Wpil_Settings::getIgnoreNofollowDomains() : array();
        $nofollow_domains = array_diff(Wpil_Settings::getNofollowDomains(), $ignore_nofollow_domains); // skip the ignored nofollow domains
        $sponsored = Wpil_Settings::getSponsoredDomains();

        // don't apply link attributes to links with these classes
        $ignore_classes = array(
            'page-numbers',
            'navigation',
            'nav-link'
        );

        // allow users to filter the classes to ignore
        $ignore_classes = apply_filters('wpil_filter_link_attr_classes', $ignore_classes);

        // flip the classes for fast searching
        $ignore_classes = array_flip($ignore_classes);

        if( $open_all_intrnl || 
            $open_all_extrnl || 
            $no_follow || 
            $same_all_intrnl || 
            $same_all_extrnl || 
            !empty($sponsored) ||
            !empty($nofollow_domains))
        {
            $post_url = (!empty($post) && isset($post->ID)) ? get_the_permalink($post->ID): false;
            preg_match_all('/<(a\s[^>]*?href=[\'"]([^\'"]*?)[\'"][^>]*?)>/', $content, $matches);

            $external_site_links = array_map(function($url){ return wp_parse_url($url, PHP_URL_HOST); }, Wpil_SiteConnector::get_registered_sites());

            foreach($matches[0] as $key => $link){

                // if there are classes, check them to see if we should ignored the links
                $skip = false;
                if(false !== strpos($link, 'class=')){
                    preg_match('/class="([^"]*?)"/', $link, $classes);
                    if(!empty($classes)){
                        $classes = explode(' ', $classes[1]);
                        foreach($classes as $class){
                            if(isset($ignore_classes[$class])){
                                $skip = true;
                                break;
                            }
                        }
                    }
                }

                // if we found a class to skip
                if($skip){
                    // skip
                    continue;
                }

                $url = $matches[2][$key];

                // if this is a jump link
                if(Wpil_Report::isJumpLink($url, $post_url)){
                    // skip
                    continue;
                }

                $url_host = wp_parse_url($url, PHP_URL_HOST);
                $link_attrs = $matches[1][$key];
                $internal = Wpil_Link::isInternal($url);

                if( ( ($internal && $open_all_intrnl) || (!$internal && $open_all_extrnl) ) &&
                    false === strpos($link, 'target="_blank"'))
                {
                    $link_attrs .= ' target="_blank"';
                }

                if( $no_follow && !$internal &&                             // if we're supposed to add nofollow to external links and this is an external link
                    false === strpos($link_attrs, 'nofollow') &&            // and the link doesn't already have a nofollow attr
                    !in_array($url_host, $external_site_links, true) &&     // and if the link isn't pointing to a registered external site
                    !in_array($url_host, $ignore_nofollow_domains, true)    // and if the link isn't pointing to a domain that the user is ignoring
                ){
                    preg_match('/(rel="([^"]+)")/', $link_attrs, $rel);

                    // if there is a rel attr
                    if(!empty($rel)){
                        // insert the nofollow attr in the rel 
                        $updated = str_replace($rel[2], $rel[2] . ' nofollow', $rel[0]);
                        $link_attrs = str_replace($rel[0], $updated, $link_attrs);
                    }else{
                        $link_attrs .= ' rel="nofollow"';
                    }
                }

                if( !empty($nofollow_domains) &&                            // if we have domains to mark as nofollow
                    false === strpos($link_attrs, 'nofollow') &&            // and the link doesn't already have a nofollow attr
                    !in_array($url_host, $external_site_links, true) &&     // and if the link isn't pointing to a registered external site
                    in_array($url_host, $nofollow_domains, true)            // and if the link is pointing to a domain that the user is ignoring
                ){
                    preg_match('/(rel="([^"]+)")/', $link_attrs, $rel);

                    // if there is a rel attr
                    if(!empty($rel)){
                        // insert the nofollow attr in the rel 
                        $updated = str_replace($rel[2], $rel[2] . ' nofollow', $rel[0]);
                        $link_attrs = str_replace($rel[0], $updated, $link_attrs);
                    }else{
                        $link_attrs .= ' rel="nofollow"';
                    }
                }

                // if the user wants to set all internal or external links to open in the same tab
                if( ( ($internal && $same_all_intrnl) || (!$internal && $same_all_extrnl) ) && false !== strpos($link_attrs, 'target="_blank"')){
                    // remove _blank from the attr list
                    $link_attrs = str_replace('target="_blank"', '', $link_attrs);
                }

                if( !empty($sponsored) && !$internal &&                     // if we're supposed to add "sponsored" to external links and this is an external link
                    false === strpos($link_attrs, 'sponsored') &&           // and this link doesn't have a "sponsored" attr
                    in_array($url_host, $sponsored, true)                   // and the link's host is one of the sponsored ones
                ){
                    preg_match('/(rel="([^"]+)")/', $link_attrs, $rel);

                    // if there is a rel attr
                    if(!empty($rel)){
                        // insert the sponsored attr in the rel
                        $updated = str_replace($rel[2], $rel[2] . ' sponsored', $rel[0]);
                        $link_attrs = str_replace($rel[0], $updated, $link_attrs);
                    }else{
                        $link_attrs .= ' rel="sponsored"';
                    }
                }

                if($matches[1][$key] !== $link_attrs){
                    $new_link = str_replace($matches[1][$key], $link_attrs, $link);
                    $content = mb_ereg_replace(preg_quote($link), $new_link, $content);
                }
            }
        }

        return $content;
    }
    public static function fixCollation($table)
    {
        global $wpdb;
        $table_status = $wpdb->get_results("SHOW TABLE STATUS where name like '$table'");
        if (empty($table_status[0]->Collation) || $table_status[0]->Collation != 'utf8mb4_unicode_ci') {
            $wpdb->query("alter table $table convert to character set utf8mb4 collate utf8mb4_unicode_ci");
        }
    }

    public static function verify_nonce($key)
    {
        $user = wp_get_current_user();
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $user->ID . $key)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                )
            ));
        }
    }

    /**
     * Removes a hooked function from the wp hook or filter.
     * We have to flip through the hooked functions because a lot of the methods use instantiated objects
     *
     * @param string $tag The hook/filter name that the function is hooked to
     * @param string $object The object who's method we're removing from the hook/filter
     * @param string $function The object method that we're removing from the hook/filter
     * @param int $priority The priority of the function that we're removing
     **/
    public static function remove_hooked_function($tag, $object, $function, $priority){
        global $wp_filter;
        $priority = intval($priority);

        // if the hook that we're looking for does exist and at the priority we're looking for
        if( isset($wp_filter[$tag]) &&
            isset($wp_filter[$tag]->callbacks) &&
            !empty($wp_filter[$tag]->callbacks) &&
            isset($wp_filter[$tag]->callbacks[$priority]) &&
            !empty($wp_filter[$tag]->callbacks[$priority]))
        {
            // look over all the callbacks in the priority we're looking in
            foreach($wp_filter[$tag]->callbacks[$priority] as $key => $data)
            {
                // if the current item is the callback we're looking for
                if(isset($data['function']) && is_a($data['function'][0], $object) && $data['function'][1] === $function){
                    // remove the callback
                    unset($wp_filter[$tag]->callbacks[$priority][$key]);

                    // if there aren't any more callbacks, remove the priority setting too
                    if(empty($wp_filter[$tag]->callbacks[$priority])){
                        unset($wp_filter[$tag]->callbacks[$priority]);
                    }
                }
            }
        }
    }

    /**
     * Updates the WP option cache independently of the update_options functionality.
     * I've found that for some users the cache won't update and that keeps some option based processing from working.
     * The code is mostly pulled from the update_option function
     *
     * @param string $option The name of the option that we're saving.
     * @param mixed $value The option value that we're saving.
     **/
    public static function update_option_cache($option = '', $value = ''){
        $option = trim( $option );
        if ( empty( $option ) ) {
            return false;
        }

        $serialized_value = maybe_serialize( $value );
        $alloptions = wp_load_alloptions( true );
        if ( isset( $alloptions[ $option ] ) ) {
            $alloptions[ $option ] = $serialized_value;
            wp_cache_set( 'alloptions', $alloptions, 'options' );
        } else {
            wp_cache_set( $option, $serialized_value, 'options' );
        }
    }

    /**
     * Deletes all Link Whisper related data on plugin deletion
     **/
    public static function delete_link_whisper_data(){
        global $wpdb;

        // if we're not really sure that the user wants to delete all data, exit
        if('1' !== get_option('wpil_delete_all_data', false)){
            return;
        }

        // create a list of all possible tables
        $tables = self::getDatabaseTableList();

        // go over the list of tables and delete all tables that exist
        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $wpdb->query("DROP TABLE {$table}");
            }
        }

        // get the settings
        $settings = array(
            'wpil_2_ignore_numbers',
            'wpil_2_post_types',
            'wpil_suggestion_limited_post_types',
            'wpil_2_term_types',
            'wpil_2_post_statuses',
            'wpil_2_links_open_new_tab',
            'wpil_limit_suggestions_to_post_types',
            'wpil_2_ll_use_h123',
            'wpil_2_ll_pairs_mode',
            'wpil_2_ll_pairs_rank_pc',
            'wpil_2_debug_mode',
            'wpil_option_update_reporting_data_on_save',
            'wpil_skip_section_type',
            'wpil_skip_sentences',
            'wpil_selected_language',
            'wpil_ignore_links',
            'wpil_ignore_categories',
            'wpil_dont_show_ignored_posts',
            'wpil_show_all_links',
            'wpil_count_related_post_links',
            'wpil_manually_trigger_suggestions',
            'wpil_disable_outbound_suggestions',
            'wpil_make_suggestion_filtering_persistent',
            'wpil_full_html_suggestions',
            'wpil_ignore_keywords_posts',
            'wpil_ignore_orphaned_posts',
            'wpil_nofollow_ignore_domains',
            'wpil_links_to_ignore',
            'wpil_ignore_elements_by_class',
            'wpil_ignore_shortcodes_by_name',
            'wpil_ignore_tags_from_linking',
            'wpil_ignore_pages_completely',
            'wpil_marked_as_external',
            'wpil_disable_acf',
            'wpil_use_seo_titles',
            'wpil_link_external_sites',
            'wpil_link_external_sites_access_code',
            'wpil_disable_external_site_updating',
            'wpil_2_show_all_post_types',
            'wpil_disable_search_update',
            'wpil_domains_marked_as_internal',
            'wpil_custom_fields_to_process',
            'wpil_link_to_yoast_cornerstone',
            'wpil_suggest_to_outbound_posts',
            'wpil_sponsored_domains',
            'wpil_nofollow_domains',
            'wpil_only_match_target_keywords',
            'wpil_add_noreferrer',
            'wpil_add_nofollow',
            'wpil_filter_staging_url',
            'wpil_live_site_url',
            'wpil_staging_site_url',
            'wpil_delete_all_data',
            'wpil_external_links_open_new_tab',
            'wpil_insert_links_as_relative',
            'wpil_prevent_two_way_linking',
            'wpil_ignore_image_urls',
            'wpil_include_image_src',
            'wpil_delete_link_inner_html',
            'wpil_include_post_meta_in_support_export',
            'wpil_ignore_acf_fields',
            'wpil_ignore_click_links',
            'wpil_open_all_internal_new_tab',
            'wpil_open_all_external_new_tab',
            'wpil_open_all_internal_same_tab',
            'wpil_open_all_external_same_tab',
            'wpil_js_open_new_tabs',
            'wpil_add_destination_title',
            'wpil_disable_broken_link_cron_check',
            'wpil_disable_click_tracking',
            'wpil_delete_old_click_data',
            'wpil_max_links_per_post',
            'wpil_max_inbound_links_per_post',
            'wpil_max_linking_age',
            'wpil_max_suggestion_count',
            'wpil_disable_click_tracking_info_gathering',
            'wpil_autotag_gsc_keywords',
            'wpil_autotag_gsc_keyword_count',
            'wpil_autotag_gsc_keyword_basis',
            'wpil_show_comment_links',
            'wpil_ignore_latest_posts',
            'wpil_content_formatting_level',
            'wpil_update_post_edit_date',
            'wpil_force_https_links',
            'wpil_track_all_element_clicks',
            'wpil_selected_target_keyword_sources',
            'wpil_get_partial_titles',
            'wpil_partial_title_word_count',
            'wpil_partial_title_split_char',
            // and the other options
            'wpil_2_license_status',
            'wpil_2_license_key',
            'wpil_2_license_data',
            'wpil_2_ignore_words',
            'wpil_has_run_initial_scan',
            'wpil_site_db_version',
            'wpil_link_table_is_created',
            'wpil_fresh_install',
            'wpil_install_date',
            'wpil_2_license_check_time',
            'wpil_2_license_last_error',
            'wpil_post_procession',
            'wpil_error_reset_run',
            'wpil_error_check_links_cron',
            'wpil_keywords_add_same_link',
            'wpil_keywords_link_once',
            'wpil_keywords_select_links',
            'wpil_keywords_restrict_date',
            'wpil_keywords_case_sensitive',
            'wpil_keywords_set_priority',
            'wpil_keywords_restrict_to_cats',
            'wpil_search_console_data',
            'wpil_gsc_app_authorized',
            'wpil_2_report_last_updated',
            'wpil_cached_valid_sites',
            'wpil_registered_sites',
            'wpil_linked_sites',
            'wpil_url_changer_reset',
            'wpil_keywords_reset',
            'wpil_keyword_reset_last_run_time',
        );

        // delete each one from the option table
        foreach($settings as $setting){
            delete_option($setting);
        }

        // delete all of the link metafields
        Wpil_Report::clearMeta();
    }

    /**
     * Checks to see if we're over the time limit.
     * 
     * @param int $time_pad The amount of time in advance of the PHP time limit that is considered over the time limit
     * @param int $max_time The absolute time limit that we'll wait for the current process to complete
     * @return bool
     **/
    public static function overTimeLimit($time_pad = 0, $max_time = null){
        $limit = ini_get( 'max_execution_time' );

        // if there is no limit or the limit is larger than 90 seconds
        if(empty($limit) || $limit === '-1' || $limit > 90){
            // create a self imposed limit so the user know LW is still working on looped actions
            $limit = 90;
        }

        // filter the limit so users with special constraints can make adjustments
        $limit = apply_filters('wpil_filter_processing_time_limit', $limit);

        // if the exit time pad is less than the limit
        if($limit < $time_pad){
            // default to a 5 second pad
            $time_pad = 5;
        }

        // get the current time
        $current_time = microtime(true);

        // if we've been running for longer than the PHP time limit minus the time pad, OR
        // a max time has been set and we've passed it
        if( ($current_time - WPIL_STATUS_PROCESSING_START) > ($limit - $time_pad) || 
            $max_time !== null && ($current_time - WPIL_STATUS_PROCESSING_START) > $max_time)
        {
            // signal that we're over the time limit
            return true;
        }else{
            return false;
        }
    }

    /**
     * Creates the database tables so we're sure that they're all set.
     * I'll still use the old method of creation for a while as a fallback.
     * But this will make LW more plug-n-play
     **/
    public static function createDatabaseTables(){
        Wpil_ClickTracker::prepare_table();
        Wpil_Error::prepareTable(false);
        Wpil_Error::prepareIgnoreTable();
        Wpil_Keyword::prepareTable(); // also prepares the possible links table
        Wpil_TargetKeyword::prepareTable();
        Wpil_URLChanger::prepareTable();

        // search console table not included because it's explicitly activated by the user
        // linked site data table also not included because it's explicitly activated by the user
    }

    /**
     * Returns an array of all the tables created by Link Whisper.
     * @param bool $should_prefix Should the returned tables have the site's database prefix attached?
     * @return array
     **/
    public static function getDatabaseTableList($should_prefix = true){
        global $wpdb;

        if($should_prefix){
            $prefix = $wpdb->prefix;
        }else{
            $prefix = '';
        }

        return array(
            "{$prefix}wpil_broken_links",
            "{$prefix}wpil_ignore_links",
            "{$prefix}wpil_click_data",
            "{$prefix}wpil_keywords",
            "{$prefix}wpil_keyword_links",
            "{$prefix}wpil_keyword_select_links",
            "{$prefix}wpil_report_links",
            "{$prefix}wpil_search_console_data",
            "{$prefix}wpil_site_linking_data",
            "{$prefix}wpil_target_keyword_data",
            "{$prefix}wpil_urls",
            "{$prefix}wpil_url_links",
        );
    }

    /**
     * Helper function to set WP to not use external object caches when doing AJAX
     **/
    public static function ignore_external_object_cache(){
        if( defined('DOING_AJAX') && DOING_AJAX &&
            function_exists('wp_using_ext_object_cache') &&
            file_exists( WP_CONTENT_DIR . '/object-cache.php') &&
            wp_using_ext_object_cache())
        {
            wp_using_ext_object_cache(false);
        }
    }

    /**
     *  Helper function to remove any problem hooks interfering with our AJAX requests
     * 
     * @param bool $ignore_ajax True allows the removing of hooks when ajax is not running
     **/
    public static function remove_problem_hooks($ignore_ajax = false){
        $admin_ajax = is_admin() && defined('DOING_AJAX') && DOING_AJAX;

        if( ($admin_ajax || $ignore_ajax) && defined('TOC_VERSION')){
            remove_all_actions('wp_enqueue_scripts');
        }
    }

    /**
     * Tracks actions that have taken place so we can tell if something in a distantly connected part of Link Whisper happened
     * 
     * @param string $action The name we've given to the action that's happened
     * @param mixed $value The value of the action that we're watching
     * @param bool $overwrite_true Should we overwrite TRUE results with whatever we currently have? By default, we don't so we can track if a result happened somewhere
     **/
    public static function track_action($action = '', $value = null, $overwrite_true = false){
        if(empty($action) || !is_string($action)){
            return;
        }

        if(isset(self::$action_tracker[$action]) && !empty(self::$action_tracker[$action]) && $overwrite_true){
            self::$action_tracker[$action] = $value;
        }elseif(!array_key_exists($action, self::$action_tracker)){
            self::$action_tracker[$action] = $value;
        }
    }

    public static function action_happened($action = '', $return_result = true){
        if(empty($action) || !is_string($action)){
            return false;
        }

        $logged = array_key_exists($action, self::$action_tracker);

        if(!$logged){
            return false;
        }

        return ($return_result) ? self::$action_tracker[$action]: $logged;
    }

    public static function clear_tracked_action($action = ''){
        if(empty($action) || !is_string($action)){
            return;
        }

        if(array_key_exists($action, self::$action_tracker)){
            unset(self::$action_tracker[$action]);
        }
    }
}