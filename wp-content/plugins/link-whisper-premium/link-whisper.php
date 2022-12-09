<?php
/**
 * Plugin Name: Link Whisper
 * Plugin URI: https://linkwhisper.com
 * Version: 2.1.4
 * Description: Quickly build smart internal links both to and from your content. Additionally, gain valuable insights with in-depth internal link reporting.
 * Author: Link Whisper
 * Author URI: https://linkwhisper.com
 * Text Domain: wpil
 */

function removeDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}

if (is_dir(ABSPATH . 'wp-content/plugins/link-whisper/')) {
    $plugins = get_option('active_plugins');
    foreach ($plugins as $key => $plugin) {
        if ($plugin == 'link-whisper/link-whisper.php') {
            unset($plugins[$key]);
        }
    }
    update_option('active_plugins', $plugins);
    removeDirectory(ABSPATH . 'wp-content/plugins/link-whisper/');
}

// remove the Free plugin's autoloader if it's present
$auto_loader_functions = spl_autoload_functions();
if(!empty($auto_loader_functions)){
    foreach($auto_loader_functions as $function){
        if($function === 'wpil_autoloader'){
            spl_autoload_unregister( 'wpil_autoloader' );
        }
    }
}

//autoloader
spl_autoload_register( 'wpil_autoloader_premium' );
function wpil_autoloader_premium( $class_name ) {
    if ( false !== strpos( $class_name, 'Wpil' ) ) {
        $classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
        $class_file = str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';
        require_once $classes_dir . $class_file;
    }
}

define( 'WPIL_PLUGIN_VERSION_NUMBER', '2.0.7'); // todo remember to update with each new release
define( 'WPIL_PLUGIN_OLD_VERSION_NUMBER', '2.0.6'); // and only update when the new release is ready so testing downloads don't miss updates
define( 'WPIL_STORE_URL', 'https://linkwhisper.com');
define( 'WPIL_PLUGIN_NAME', plugin_basename( __FILE__ ));
define( 'WP_INTERNAL_LINKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define( 'WP_INTERNAL_LINKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'WPIL_OPTION_IGNORE_WORDS', 'wpil_2_ignore_words');
define( 'WPIL_OPTION_IGNORE_NUMBERS', 'wpil_2_ignore_numbers');
define( 'WPIL_OPTION_DEBUG_MODE', 'wpil_2_debug_mode');
define( 'WPIL_OPTION_UPDATE_REPORTING_DATA_ON_SAVE', 'wpil_option_update_reporting_data_on_save');
define( 'WPIL_OPTION_REDUCE_CASCADE_UPDATING', 'wpil_option_reduce_cascade_updating');
define( 'WPIL_OPTION_DONT_COUNT_INBOUND_LINKS', 'wpil_option_dont_count_inbound_links');
define( 'WPIL_OPTION_LICENSE_KEY', 'wpil_2_license_key');
define( 'WPIL_OPTION_LICENSE_CHECK_TIME', 'wpil_2_license_check_time');
define( 'WPIL_OPTION_LICENSE_STATUS', 'wpil_2_license_status');
define( 'WPIL_OPTION_LICENSE_DATA', 'wpil_2_license_data');
define( 'WPIL_OPTION_LICENSE_LAST_ERROR', 'wpil_2_license_last_error');
define( 'WPIL_OPTION_POST_TYPES', 'wpil_2_post_types');
define( 'WPIL_OPTION_LINKS_OPEN_NEW_TAB', 'wpil_2_links_open_new_tab');
define( 'WPIL_OPTION_REPORT_LAST_UPDATED', 'wpil_2_report_last_updated');
define( 'WPIL_VERSION_DEV', '18-July-2019');
define( 'WPIL_VERSION_DEV_DISPLAY', true);
define( 'WPIL_LINKS_OUTBOUND_INTERNAL_COUNT', 'wpil_links_outbound_internal_count');
define( 'WPIL_LINKS_INBOUND_INTERNAL_COUNT', 'wpil_links_inbound_internal_count');
define( 'WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT', 'wpil_links_outbound_external_count');
define( 'WPIL_LINK_TABLE_IS_CREATED', 'wpil_link_table_is_created');
define( 'WPIL_STATUS_LINK_TABLE_EXISTS', get_option(WPIL_LINK_TABLE_IS_CREATED, false));
define( 'WPIL_STATUS_PLUGIN_DB_VERSION', '1.28');  // simple version counter that gets incremented when we change the existing DB tables. That way update_tables knows when and what to update.
define( 'WPIL_STATUS_SITE_DB_VERSION', get_option('wpil_site_db_version', '0'));  // existing DB version on this site
define( 'WPIL_STATUS_PROCESSING_START', microtime(true));
define( 'WPIL_STATUS_HAS_RUN_SCAN', get_option('wpil_has_run_initial_scan', false));
define( 'WPIL_DATA_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36');


define( 'WPIL_DEBUG_CURL', false); // should be activated only for short term use, can generate huge log files

Wpil_Init::register_services();

register_activation_hook(__FILE__, [Wpil_Base::class, 'activate'] );
register_uninstall_hook(__FILE__, array(Wpil_Base::class, 'delete_link_whisper_data'));

if (is_admin())
{
    if(!class_exists( 'EDD_SL_Plugin_Updater'))
    {
        // load our custom updater if it doesn't already exist
        include (dirname(__FILE__).'/vendor/EDD_SL_Plugin_Updater.php');
    }

    if(!function_exists('get_plugin_data'))
    {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    if (Wpil_License::isValid()) {

        $license_key = trim(get_option( WPIL_OPTION_LICENSE_KEY));
        $edd_item_id = Wpil_License::getItemId($license_key);
        $license = Wpil_License::getKey($license_key);

        $plugin_data = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];

        // setup the updater
        $edd_updater = new EDD_SL_Plugin_Updater( WPIL_STORE_URL, __FILE__, array(
            'version' => $plugin_version,		// current version number
            'license' => $license,	// license key (used get_option above to retrieve from DB)
            'item_id' => $edd_item_id,	// id of this plugin
            'author' => 'Spencer Haws',	// author of this plugin
            'url' => home_url(),
            'beta' => false, // set to true if you wish customers to receive update notifications of beta releases
        ));

    }

}


add_action('plugins_loaded', 'wpil_init');

if (!function_exists('wpil_init'))
{
    function wpil_init()
    {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'wpil');
        unload_textdomain('wpil');
        load_textdomain('wpil', WP_INTERNAL_LINKING_PLUGIN_DIR . 'languages/' . "wpil-" . $locale . '.mo');
        load_plugin_textdomain('wpil', false, WP_INTERNAL_LINKING_PLUGIN_DIR . 'languages');
    }
}

add_filter('plugin_row_meta', 'wpil_filter_plugin_row_meta', 4, 10);
if(!function_exists('wpil_filter_plugin_row_meta')){
    function wpil_filter_plugin_row_meta($plugin_meta, $plugin_file, $plugin_data, $status){
        $plugin_slug = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : sanitize_title( $plugin_data['Name'] );
        if($plugin_slug === 'link-whisper'){
            $plugin_meta[] = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal">%s</a>',
                esc_url( get_admin_url() . 'plugin-install.php?tab=plugin-information&plugin=link-whisper&section=changelog&TB_iframe=true' ),
                __('Change Log', 'wpil')
            );
        }

        return $plugin_meta;
    }
}

/**
 * A text logging function for use when error_log isn't a possibility.
 * I find myself copy-pasting file writers often enough that it makes sense to add a logger here for debugging
 * Can accept a string or array/object for writing
 * 
 * @param mixed $content The content to write to the file.
 **/
if(!function_exists('WPIL_TEXT_LOGGER')){
    function WPIL_TEXT_LOGGER($content){
        $file = fopen(trailingslashit(WP_INTERNAL_LINKING_PLUGIN_DIR) . 'wpil_text_log.txt', 'a');
        fwrite($file, print_r($content, true));
        fclose($file);
    }
}

if(false){
    // track errors based on shutdown in case something's not telling us there's an error
    function wpil_shutdown_tracking() {
        $error = error_get_last();
        error_log(print_r(array('shutdown error tracking', 'error' => $error, 'time' => microtime(true) - WPIL_STATUS_PROCESSING_START, 'last_error' => debug_backtrace()), true));
    }

    register_shutdown_function('wpil_shutdown_tracking');
}


