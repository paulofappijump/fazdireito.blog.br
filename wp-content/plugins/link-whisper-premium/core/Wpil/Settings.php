<?php

/**
 * Work with settings
 */
class Wpil_Settings
{
    public static $ignore_phrases = null;
    public static $ignore_words = null;
    public static $stemmed_ignore_words = null;
    public static $keys = [
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
        'wpil_partial_title_split_char'
    ];

    /**
     * Show settings page
     */
    public static function init()
    {
        $types_active = Wpil_Settings::getPostTypes();
        $suggestion_types_active = self::getSuggestionPostTypes();
        $term_types_active = Wpil_Settings::getTermTypes();
        if(empty(get_option('wpil_2_show_all_post_types', false))){
            $types_available = get_post_types(['public' => true]);
        }else{
            $types_available = get_post_types();
        }

        $types_available = Wpil_Settings::getPostTypeLabels($types_available);

        $term_types_available = get_taxonomies();
        $statuses_available = [
            'publish',
            'private',
            'future',
            'pending',
            'draft'
        ];
        $statuses_active = Wpil_Settings::getPostStatuses();

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wpil_settings_v2.php';
    }

    /**
     * Get ignore phrases
     */
    public static function getIgnorePhrases()
    {
        if (is_null(self::$ignore_phrases)) {
            $phrases = [];
            foreach (self::getIgnoreWords() as $word) {
                if (strpos($word, ' ') !== false) {
                    $phrases[] = preg_replace('/\s+/', ' ',$word);
                }
            }

            self::$ignore_phrases = $phrases;
        }

        return self::$ignore_phrases;
    }

    /**
     * Gets the site's current language as defined in the WP settings
     **/
    public static function getSiteLanguage(){
        $locale = get_locale();

        switch ($locale) {
            case 'en':
            case 'en_AU':
            case 'en_GB':
            case 'en_CA':
            case 'en_NZ':
            case 'en_ZA':
                $language = 'english';
                break;
            case 'es_ES':
            case 'es_AR':
            case 'es_EC':
            case 'es_CO':
            case 'es_VE':
            case 'es_DO':
            case 'es_UY':
            case 'es_PE':
            case 'es_CL':
            case 'es_PR':
            case 'es_CR':
            case 'es_GT':
            case 'es_MX':
                $language = 'spanish';
                break;
            case 'fr_CA':
            case 'fr_FR':
            case 'fr_BE':
                $language = 'french';
                break;
            case 'de_CH_informal':
            case 'de_DE':
            case 'de_CH':
            case 'de_AT':
                $language = 'german';
                break;
            case 'ru_RU':
                $language = 'russian';
                break;
            case 'pt_BR':
            case 'pt_PT_ao90':
            case 'pt_PT':
            case 'pt_AO':
                $language = 'portuguese';
                break;
            case 'nl_NL':
            case 'nl_NL_formal':
            case 'nl_BE':
                $language = 'dutch';
                break;
            case 'da_DK':
                $language = 'danish';
                break;
            case 'it_IT':
                $language = 'italian';
                break;
            case 'pl_PL':
                $language = 'polish';
                break;
            case 'sk_SK':
                $language = 'slovak';
                break;
            case 'nb_NO':
                $language = 'norwegian';
                break;
            case 'sv_SE':
                $language = 'swedish';
                break;
            case 'ar':
            case 'ary':
                $language = 'arabic';
                break;
            case 'sr_RS':
                $language = 'serbian';
                break;
            case 'fi':
                $language = 'finnish';
                break;
            case 'he_IL':
                $language = 'hebrew';
                break;
            case 'hi_IN':
                $language = 'hindi';
                break;
            case 'hu_HU':
                $language = 'hungarian';
                break;
            case 'ro_RO':
                $language = 'romanian';
                break;
            default:
                $language = 'english';
                break;
        }

        return $language;
    }

    /**
     * Get ignore words
     */
    public static function getIgnoreWords()
    {
        if (is_null(self::$ignore_words)) {
            $words = get_option('wpil_2_ignore_words', null);
            // get the user's current language
            $selected_language = self::getSelectedLanguage();

            // if there are no stored words or the current language is different from the selected one
            if (is_null($words) || (WPIL_CURRENT_LANGUAGE !== $selected_language)) {
                $ignore_words_file = self::getIgnoreFile($selected_language);
                $words = file($ignore_words_file);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
            } else {
                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
            }

            self::$ignore_words = $words;
        }

        return self::$ignore_words;
    }

    /**
     * Get stemmed versions of the ignore words
     */
    public static function getStemmedIgnoreWords()
    {
        if (is_null(self::$stemmed_ignore_words)) {
            $words = self::getIgnoreWords();
            foreach($words as $key => $word) {
                $words[$key] = trim(Wpil_Stemmer::Stem($word));
            }

            // remove any duplicates
            $words = array_keys(array_flip($words));

            self::$stemmed_ignore_words = $words;
        }

        return self::$stemmed_ignore_words;
    }

    /**
     * Gets all current ignore word lists.
     * The word list for the language the user is currently using is loaded from the settings.
     * All other languages are loaded from the word files
     **/
    public static function getAllIgnoreWordLists(){
        $current_language       = self::getSelectedLanguage();
        $supported_languages    = self::getSupportedLanguages();
        $all_ignore_lists       = array();

        // go over all currently supported languages
        foreach($supported_languages as $language_id => $supported_language){

            // if the current language is the user's selected one
            if($language_id === $current_language){

                $words = get_option('wpil_2_ignore_words', null);
                if(is_null($words)){
                    $words = self::getIgnoreWords();
                }else{
                    $words = explode("\n", $words);
                    $words = array_unique($words);
                    sort($words);
                    foreach($words as $key => $word) {
                        $words[$key] = trim(Wpil_Word::strtolower($word));
                    }
                }

                $all_ignore_lists[$language_id] = $words;
            }else{
                $ignore_words_file = self::getIgnoreFile($language_id);
                $words = array();
                if(file_exists($ignore_words_file)){
                    $words = file($ignore_words_file);
                }else{
                    // if there is no word file, skip to the next one
                    continue;
                }
                
                if(empty($words)){
                    $words = array();
                }
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }
        }

        return $all_ignore_lists;
    }

    /**
     * Get ignore words file based on current language
     *
     * @param $language
     * @return string
     */
    public static function getIgnoreFile($language)
    {
        switch($language){
            case 'spanish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/ES_ignore_words.txt';
                break;
            case 'french':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FR_ignore_words.txt';
                break;
            case 'german':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DE_ignore_words.txt';
                break;
            case 'russian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RU_ignore_words.txt';
                break;
            case 'portuguese':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PT_ignore_words.txt';
                break;
            case 'dutch':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NL_ignore_words.txt';
                break;
            case 'danish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DA_ignore_words.txt';
                break;
            case 'italian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/IT_ignore_words.txt';
                break;
            case 'polish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PL_ignore_words.txt';
                break;            
            case 'slovak':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SK_ignore_words.txt';
                break;
            case 'norwegian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NO_ignore_words.txt';
                break;
            case 'swedish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SW_ignore_words.txt';
                break;            
            case 'arabic':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/AR_ignore_words.txt';
                break;
            case 'serbian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SR_ignore_words.txt';
                break;
            case 'finnish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FI_ignore_words.txt';
                break;
            case 'hebrew':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HE_ignore_words.txt';
                break;
            case 'hindi':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HI_ignore_words.txt';
                break;
            case 'hungarian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HU_ignore_words.txt';
                break;
            case 'romanian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RO_ignore_words.txt';
                break;
            default:
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/EN_ignore_words.txt';
                break;
        }

        return $file;
    }

    /**
     * Get selected post types
     *
     * @return mixed|void
     */
    public static function getPostTypes()
    {
        return get_option('wpil_2_post_types', ['post', 'page']);
    }


    /**
     * Get the post types that users have limited the suggestions to
     *
     * @return mixed|void
     */
    public static function getSuggestionPostTypes()
    {
        return get_option('wpil_suggestion_limited_post_types', self::getPostTypes());
    }

    /**
     * Get merged array of post types and term types
     *
     * @return array
     */
    public static function getAllTypes()
    {
        return array_merge(self::getPostTypes(), self::getTermTypes());
    }

    /**
     * Get selected post statuses
     *
     * @return array
     */
    public static function getPostStatuses()
    {
        return get_option('wpil_2_post_statuses', ['publish']);
    }

    public static function getInternalDomains(){
        $domains = get_transient('wpil_domains_marked_as_internal');
        if(empty($domains)){
            $domains = array();
            $domain_data = get_option('wpil_domains_marked_as_internal');
            $domain_data = explode("\n", $domain_data);
            foreach ($domain_data as $domain) {
                $pieces = wp_parse_url(trim($domain));
                if(!empty($pieces) && isset($pieces['host'])){
                    $domains[] = str_replace('www.', '', $pieces['host']);
                }
            }

            set_transient('wpil_domains_marked_as_internal', $domains, 15 * MINUTE_IN_SECONDS);
        }

        return $domains;
    }

    /**
     * Gets any custom content fields that the user has defined on his site and wants to process for content.
     * @return array $fields Returns an array if there's fields, and an empty arry if there's no fields.
     **/
    public static function getCustomFieldsToProcess(){
        $fields = get_transient('wpil_custom_fields_to_process');
        if(empty($fields)){
            $fields = get_option('wpil_custom_fields_to_process', array());

            if(empty($fields)){
                $fields = 'no-fields';
            }else{
                $fields = explode("\n", $fields);
                if(!empty($fields)){
                    $fields = array_map('trim', $fields);
                }else{
                    $fields = 'no-fields';
                }
            }

            set_transient('wpil_custom_fields_to_process', $fields, 15 * MINUTE_IN_SECONDS);
        }

        if($fields === 'no-fields'){
            return array();
        }

        return $fields;
    }

    /**
     * Gets the currently supported languages
     * 
     * @return array
     **/
    public static function getSupportedLanguages(){
        $languages = array(
            'english'       => 'English',
            'spanish'       => 'Español',
            'french'        => 'Français',
            'german'        => 'Deutsch',
            'russian'       => 'Русский',
            'portuguese'    => 'Português',
            'dutch'         => 'Dutch',
            'danish'        => 'Dansk',
            'italian'       => 'Italiano',
            'polish'        => 'Polskie',
            'norwegian'     => 'Norsk bokmål',
            'swedish'       => 'Svenska',
            'slovak'        => 'Slovenčina',
            'arabic'        => 'عربي',
            'serbian'       => 'Српски / srpski',
            'finnish'       => 'Suomi',
            'hebrew'        => 'עִבְרִית',
            'hindi'         => 'हिन्दी',
            'hungarian'     => 'Magyar',
            'romanian'      => 'Română',
        );
        
        return $languages;
    }

    /**
     * Gets the currently selected language
     * 
     * @return array
     **/
    public static function getSelectedLanguage(){
        return get_option('wpil_selected_language', 'english');
    }

    /**
     * Gets the language for the current processing run.
     * Does a check to see if there's a translation plugin active.
     * If there is, it tries to set the current language to the current post's language.
     * If that's not possible, or there isn't a translation plugin, it defaults to the set language
     **/
    public static function getCurrentLanguage(){

        // if Polylang is active
        if(defined('POLYLANG_VERSION')){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_post_suggestions' || $_POST['action'] === 'update_suggestion_display') &&
                isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];

                // get the language ids
                $language_ids = $wpdb->get_col("SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy WHERE `taxonomy` = 'language'");

                // if there are no ids, return the selected language from the settings
                if(empty($language_ids)){
                    return self::getSelectedLanguage();
                }

                $language_ids = implode(', ', $language_ids);

                // check the term_relationships to see if any are applied to the current post
                $tax_id = $wpdb->get_var("SELECT `term_taxonomy_id` FROM $wpdb->term_relationships WHERE `object_id` = {$post_id} AND `term_taxonomy_id` IN ({$language_ids})");

                // if there are no ids, return the selected language from the settings
                if(empty($tax_id)){
                    return self::getSelectedLanguage();
                }

                // query the wp_terms to get the language code for the applied language
                $code = $wpdb->get_var("SELECT `slug` FROM $wpdb->terms WHERE `term_id` = {$tax_id}");

                // if we've gotten the language code, see if we support the language
                if($code){
                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'nb' => 'norwegian',
                        'sv' => 'swedish',
                        'sd' => 'arabic',
                        'snd' => 'arabic',
                        'sr' => 'serbian',
                        'fi' => 'finnish',
                        'he' => 'hebrew',
                        'hi' => 'hindi',
                        'hu' => 'hungarian',
                        'ro' => 'romanian'
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        // if WPML is active
        if(self::wpml_enabled()){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_post_suggestions' || $_POST['action'] === 'update_suggestion_display') &&
            isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];
                $post_type = get_post_type($post_id);
                $post_type = 'post_' . $post_type;
                $code = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` = '{$post_type}'");

                if(!empty($code)){

                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt-br' => 'portuguese',
                        'pt-pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'no' => 'norwegian',
                        'sv' => 'swedish',
                        'ar' => 'arabic',
                        'sr' => 'serbian',
                        'fi' => 'finnish',
                        'he' => 'hebrew',
                        'hi' => 'hindi',
                        'hu' => 'hungarian',
                        'ro' => 'romanian'
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        return self::getSelectedLanguage();
    }

    public static function getProcessingBatchSize(){
        $batch_size = (int) get_option('wpil_option_suggestion_batch_size', 300);
        if($batch_size < 10){
            $batch_size = 10;
        }
        return $batch_size;
    }

    /**
     * This function is used handle settting page submission
     *
     * @return  void
     */
    public static function save()
    {
        if (isset($_POST['wpil_save_settings_nonce'])
            && wp_verify_nonce($_POST['wpil_save_settings_nonce'], 'wpil_save_settings')
            && isset($_POST['hidden_action'])
            && $_POST['hidden_action'] == 'wpil_save_settings'
        ) {
            //prepare ignore words to save
            $ignore_words = sanitize_textarea_field(stripslashes(trim(base64_decode($_POST['ignore_words']))));
            $ignore_words = mb_split("\n|\r", $ignore_words);
            $ignore_words = array_unique($ignore_words);
            $ignore_words = array_filter(array_map('trim', $ignore_words));
            sort($ignore_words);
            $ignore_words = implode(PHP_EOL, $ignore_words);

            //update ignore words
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore_words);

            // set a flag so we know if the user recently activated GSC
            $activated_gsc = false;

            // if the customer has manually selected the active GSC profile
            if( isset($_POST['wpil_manually_select_gsc_profile']) && // only shows once GSC is activated
                !empty($_POST['wpil_manually_select_gsc_profile']))
            {
                // get the GSC setting data
                $setting_data = Wpil_SearchConsole::search_console_data();

                if(isset($setting_data['profiles'])){
                    $setting_data['profiles'] = array(sanitize_text_field($_POST['wpil_manually_select_gsc_profile']));
                    Wpil_SearchConsole::search_console_data($setting_data);
                }
            }

            // save the API tokens if an access key is supplied
            $setting_update_msg = '';
            if( isset($_POST['wpil_gsc_access_code']) && !empty(trim($_POST['wpil_gsc_access_code']))){
                $response = Wpil_SearchConsole::get_access_token(trim($_POST['wpil_gsc_access_code']));
                $setting_update_msg = (!empty($response['access_valid'])) ? '&access_valid=1': '&access_valid=0';
                set_transient('wpil_gsc_access_status_message', $response['message'], 60);

                if(!empty($response['access_valid'])){
                    update_option('wpil_gsc_app_authorized', true);
                    $activated_gsc = true;
                }
            }

            if( isset($_POST['wpil_gsc_custom_app_name']) &&
                isset($_POST['wpil_gsc_custom_client_id']) &&
                isset($_POST['wpil_gsc_custom_client_secret']) &&
                !empty($_POST['wpil_gsc_custom_app_name']) &&
                !empty($_POST['wpil_gsc_custom_client_id']) &&
                !empty($_POST['wpil_gsc_custom_client_secret']))
            {
                $config = array('application_name'  => sanitize_text_field($_POST['wpil_gsc_custom_app_name']), 
                                'client_id'         => sanitize_text_field($_POST['wpil_gsc_custom_client_id']), 
                                'client_secret'     => sanitize_text_field($_POST['wpil_gsc_custom_client_secret']));

                $response = Wpil_SearchConsole::save_custom_auth_config($config);
                $setting_update_msg  = (!empty($response)) ? '&access_valid=1': '&access_valid=0';
                $save_message   = (!empty($response)) ? 'Your Google app credentials have been saved! Please scroll down and authorize the connection to your app.': 'There was an error in saving the app credentials.';
                set_transient('wpil_gsc_access_status_message', $save_message, 60);

                if(!empty($response['access_valid'])){
                    update_option('wpil_gsc_app_authorized', true);
                    $activated_gsc = true;
                }
            }

            if (empty($_POST[WPIL_OPTION_POST_TYPES]))
            {
                $_POST[WPIL_OPTION_POST_TYPES] = [];
            }

            if (empty($_POST['wpil_2_term_types'])) {
                $_POST['wpil_2_term_types'] = [];
            }

            if(empty($_POST['wpil_ignore_tags_from_linking'])){
                $_POST['wpil_ignore_tags_from_linking'] = [];
            }

            // if the settings aren't set for showing all post types, remove all but the public ones
            if( empty($_POST['wpil_2_show_all_post_types']) &&
                isset($_POST['wpil_2_post_types']) &&
                !empty($_POST['wpil_2_post_types']))
            {
                $types_available = get_post_types(['public' => true]);
                foreach($_POST['wpil_2_post_types'] as $key => $type){
                    if(!isset($types_available[$type])){
                        unset($_POST['wpil_2_post_types'][$key]);
                    }
                }
            }

            if (empty($_POST['wpil_selected_target_keyword_sources'])) {
                $_POST['wpil_selected_target_keyword_sources'] = [];
            }

            // update the list of known keyword sources
            update_option('wpil_available_target_keyword_sources', Wpil_TargetKeyword::get_available_keyword_sources()); // should mention at_save, but the name would be getting too long

            //save other settings
            $opt_keys = self::$keys;
            foreach($opt_keys as $opt_key) {
                if (array_key_exists($opt_key, $_POST)) {
                    update_option($opt_key, $_POST[$opt_key]);
                }
            }

            // make sure GSC is a selected keyword source if the user just activated GSC
            if($activated_gsc){
                $selected_sources = get_option('wpil_selected_target_keyword_sources', array('custom'));
                if(!in_array('gsc', $selected_sources)){
                    $selected_sources = array_merge($selected_sources, array('gsc'));
                    update_option('wpil_selected_target_keyword_sources', $selected_sources);
                }
            }

            // make sure the external data table is created when external linking is activated
            if(array_key_exists('wpil_link_external_sites', $_POST) && !empty($_POST['wpil_link_external_sites'])){
                Wpil_SiteConnector::create_data_table();
            }

            // if the user has checked the option to cancel the active broken link scans
            if(isset($_POST['wpil_clear_error_checker_process']) && !empty($_POST['wpil_clear_error_checker_process'])){
                // run the finishing routine for the link checker
                update_option('wpil_error_reset_run', 0);
                Wpil_Error::mergeIgnoreLinks();
                Wpil_Error::deleteValidLinks();
                update_option('wpil_error_check_links_cron', 1);
                // tell the user that we've cancelled the process
                $setting_update_msg .= '&broken_link_scan_cancelled=1';
                set_transient('wpil_clear_error_checker_message', __('Broken Link scan cancelled!', 'wpil'), 60);
            }

            // if the user has checked the option to create the database tables
            if(isset($_POST['wpil_force_create_database_tables']) && !empty($_POST['wpil_force_create_database_tables'])){
                // run the table create routine
                Wpil_Base::createDatabaseTables();
                // tell the user that we've re-run the process
                $setting_update_msg .= '&database_creation_activated=1';
                set_transient('wpil_database_creation_message', __('Database creation routine complete!', 'wpil'), 60);
            }

            // if the user has checked the option to update the database tables
            if(isset($_POST['wpil_force_database_update']) && !empty($_POST['wpil_force_database_update'])){
                // run the table update routine
                Wpil_Base::updateTables(true);
                // tell the user that we've re-run the process
                $setting_update_msg .= '&database_update_activated=1';
                set_transient('wpil_database_update_message', __('Database update routine complete!', 'wpil'), 60);
            }

            // if the user has chosen to delete all stored user data
            if(isset($_POST['wpil_delete_stored_visitor_data']) && !empty($_POST['wpil_delete_stored_visitor_data'])){
                // delete the data
                Wpil_ClickTracker::delete_stored_visitor_data();
                // check for any stored data that wasn't deleted
                $erased = !Wpil_ClickTracker::check_for_stored_visitor_data();
                // and tell the user about the status
                $setting_update_msg .= '&user_data_deleted=';
                $setting_update_msg .= ($erased) ? '1': '0';
                if($erased){
                    set_transient('wpil_user_data_delete_message', __('Stored user data deleted!', 'wpil'), 60);
                }else{
                    set_transient('wpil_user_data_delete_message', __('All of the stored user data couldn\'t be deleted. Please try again.', 'wpil'), 60);
                }
            }

            // clear the item caches if they're set
            delete_transient('wpil_ignore_links');
            delete_transient('wpil_ignore_external_links');
            delete_transient('wpil_ignore_keywords_posts');
            delete_transient('wpil_ignore_categories');
            delete_transient('wpil_domains_marked_as_internal');
            delete_transient('wpil_links_to_ignore');
            delete_transient('wpil_ignore_elements_by_class');
            delete_transient('wpil_ignore_shortcodes_by_name');
            delete_transient('wpil_ignore_pages_completely');
            delete_transient('wpil_suggest_to_outbound_posts');
            delete_transient('wpil_ignore_acf_fields');
            delete_transient('wpil_ignore_click_links');
            delete_transient('wpil_sponsored_domains');
            delete_transient('wpil_nofollow_domains');
            delete_transient('wpil_custom_fields_to_process');

            wp_redirect(admin_url('admin.php?page=link_whisper_settings&success' . $setting_update_msg));
            exit;
        }
    }

    public static function getSkipSectionType()
    {
        return get_option('wpil_skip_section_type', 'sentences');
    }

    public static function getSkipSentences()
    {
        return get_option('wpil_skip_sentences', 3);
    }

    /**
     * Gets the max number of suggestions that will be shown at once in the suggestion panel.
     * @return int
     **/
    public static function get_max_suggestion_count(){
        return (int) get_option('wpil_max_suggestion_count', 0);
    }

    /**
     * Checks to see if the site has a translation plugin active
     * 
     * @return bool
     **/
    public static function translation_enabled(){
        if(defined('POLYLANG_VERSION')){
            return true;
        }elseif(self::wpml_enabled()){
            return true;
        }

        return false;
    }

    /**
     * Check if WPML installed and has at least 2 languages
     *
     * @return bool
     */
    public static function wpml_enabled()
    {
        global $wpdb;

        // if WPML is activated
        if(function_exists('icl_object_id') || class_exists('SitePress')){
            $languages_count = 1;
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if ($table == $wpdb->prefix . 'icl_languages') {
                $languages_count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}icl_languages WHERE active = 1");
            } else {
                $languages_count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'language'");
            }

            if (!empty($languages_count) && $languages_count > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get checked term types
     *
     * @return array
     */
    public static function getTermTypes()
    {
        return get_option('wpil_2_term_types', []);
    }

    /**
     * Get ignore posts (posts & terms)
     * Pulls posts from cache if available to save processing time.
     *
     * @return array
     */
    public static function getIgnorePosts()
    {
        $posts = get_transient('wpil_ignore_links');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_links');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $link = trim($link);
                if(empty($link)){
                    continue;
                }

                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            set_transient('wpil_ignore_links', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Get ignore posts from the externally linked sites
     * Pulls posts from cache if available to save processing time.
     *
     * @return array
     */
    public static function getIgnoreExternalPosts()
    {
        global $wpdb;

        $posts = get_transient('wpil_ignore_external_links');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_links');
            $links = explode("\n", $links);
            $linked_domains = array_filter(array_map(function($site_url){ return wp_parse_url(trim($site_url), PHP_URL_HOST);}, Wpil_SiteConnector::get_linked_sites()));
            $query_links = array();
            foreach ($links as $link) {
                // if the ignored link is one that goes to an external site, add it the list to query for
                if(in_array(wp_parse_url(trim($link), PHP_URL_HOST), $linked_domains, true)){
                    $query_links[] = trim($link);
                }
            }

            if(!empty($query_links)){
                $query_links = implode('\', \'', $query_links);
                $external_posts = $wpdb->get_results("SELECT `post_id`, `type` FROM {$wpdb->prefix}wpil_site_linking_data WHERE `post_url` IN ('{$query_links}')");
                if(!empty($external_posts)){
                    foreach($external_posts as $post){
                        $posts[] = $post->type . '_' . $post->post_id;
                    }
                }
            }

            if(empty($posts)){
                $posts = 'no-posts';
            }

            set_transient('wpil_ignore_external_links', $posts, 15 * MINUTE_IN_SECONDS);
        }

        // if there are no posts
        if($posts === 'no-posts'){
            // return an empty array
            $posts = array();
        }

        return $posts;
    }

    /**
     * Get ignore posts
     *
     * @return array
     */
    public static function getIgnoreKeywordsPosts()
    {
        $posts = get_transient('wpil_ignore_keywords_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_keywords_posts');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $link = trim($link);
                if(empty($link)){
                    continue;
                }

                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            $completely_ignored = self::get_completely_ignored_pages();
            if(!empty($completely_ignored)){
                $posts = array_merge($posts, $completely_ignored);
                $posts = array_values(array_flip(array_flip($posts)));
            }

            set_transient('wpil_ignore_keywords_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Get ignored orphaned posts
     * Used in the link report page
     *
     * @return array
     */
    public static function getIgnoreOrphanedPosts()
    {
        $posts = [];
        $links = get_option('wpil_ignore_orphaned_posts');
        $links = explode("\n", $links);
        foreach ($links as $link) {
            $link = trim($link);
            if(empty($link)){
                continue;
            }

            $post = Wpil_Post::getPostByLink($link);
            if (!empty($post)) {
                $posts[] = $post->type . '_' . $post->id;
            }
        }

        $completely_ignored = self::get_completely_ignored_pages();
        if(!empty($completely_ignored)){
            $posts = array_merge($posts, $completely_ignored);
            $posts = array_values(array_flip(array_flip($posts)));
        }

        return $posts;
    }

    /**
     * Get categories list to be ignored
     *
     * @return array
     */
    public static function getIgnoreCategoriesPosts()
    {
        $posts = get_transient('wpil_ignore_categories');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_categories', '');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $category = Wpil_Post::getPostByLink(trim($link));
                if (!empty($category)) {
                    $posts = array_merge($posts, Wpil_Post::getCategoryPosts($category->id));
                }
            }
            $posts = array_values(array_flip(array_flip($posts)));

            set_transient('wpil_ignore_categories', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Gets the ids of all the posts and categories that have been ignored from the suggestion process.
     * So it counts BOTH the posts that have been ignored directly, and the ones that have been ignored by category.
     * Also loops in the pages that have been completely ignored.
     **/
    public static function getAllIgnoredPosts(){
        $posts = array();

        $ignored_posts = self::getIgnorePosts();
        if(!empty($ignored_posts)){
            $posts = array_merge($posts, $ignored_posts);
        }

        $ignored_posts = self::getIgnoreCategoriesPosts();
        if(!empty($ignored_posts)){
            foreach($ignored_posts as $id){
                $posts[] = 'post_' . $id;
            }
        }

        $completely_ignored = self::get_completely_ignored_pages();
        if(!empty($completely_ignored)){
            $posts = array_merge($posts, $completely_ignored);
        }

        if(!empty($posts)){
            $posts = array_values(array_flip(array_flip($posts)));
        }

        return $posts;
    }

    /**
     * Get if the ignored posts aren't supposed to be shown or referenced on the Report pages
     * @return bool
     **/
    public static function hideIgnoredPosts(){
        // check if the hide setting has been set from the Settings page
        if(!empty(get_option('wpil_dont_show_ignored_posts', false))){
            return true;
        }

        // get if the specific user want's to hide the posts
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $hide_ignored = (isset($options['hide_ignore'])) ? ( ($options['hide_ignore'] == 'off') ? false : true) : false;

        return $hide_ignored;
    }

    /**
     * Gets the 
     **/

    /**
     * Gets an array of post ids to affirmatively make outbound links to.
     *
     * @return array
     */
    public static function getOutboundSuggestionPostIds()
    {
        $posts = get_transient('wpil_suggest_to_outbound_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_suggest_to_outbound_posts', '');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            if(empty($posts)){
                $posts = 'no-posts';
            }

            set_transient('wpil_suggest_to_outbound_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        // if there are no posts
        if($posts === 'no-posts'){
            // return an empty array
            $posts = array();
        }

        return $posts;
    }

    /**
     * Gets an array of type specific ids from the url input settings.
     */
    public static function getItemTypeIds($ids = array(), $type = 'post'){
        if($type === 'post'){
            $ids = array_map(function($id){ if(false !== strpos($id, 'post_')){ return substr($id, 5); }else{ return false;} }, $ids);
            $ids = array_filter($ids);
        }else{
            $ids = array_map(function($id){ if(false !== strpos($id, 'term_')){ return substr($id, 5); }else{ return false;} }, $ids);
            $ids = array_filter($ids);
        }

        return $ids;
    }

    //Check if need to show ALL links
    public static function showAllLinks()
    {
        return !empty(get_option('wpil_show_all_links'));
    }

    /**
     * Gets if the user wants to count links from related post plugins in the Links Report.
     * Returns false if the user has opted to show all links because that includes related post links already.
     **/
    public static function get_related_post_links()
    {
        return (!empty(get_option('wpil_count_related_post_links', false)) && !self::showAllLinks());
    }

    /**
     * Gets if the user wants to ignore links from latest post blocks/widgets in the Links Report.
     **/
    public static function ignore_latest_post_links()
    {
        return !empty(get_option('wpil_ignore_latest_posts', false));
    }

    /**
     * Gets if the user wants to show comment links in the Links Report.
     * Returns false if the user has opted to show all links because that includes comments already.
     **/
    public static function getCommentLinks()
    {
        return (!empty(get_option('wpil_show_comment_links')) && !self::showAllLinks());
    }

    /**
     * Gets the current content formatting level when pulling links from content
     **/
    public static function getContentFormattingLevel()
    {
        // if the user has programattically disabled formatting, return zero
        if(apply_filters('wpil_disable_content_link_formatting', false)){
            return 0;
        }

        return (int) get_option('wpil_content_formatting_level', 2);
    }

    /**
     * 
     */
    public static function getPossibleIgnoreLinkingTags(){
        return array('p', 'span', 'li', 'div', 'ul', 'ol', 'blockquote', 'td', 'th');
    }

    /**
     * 
     */
    public static function getIgnoreLinkingTags(){
        $tags = get_option('wpil_ignore_tags_from_linking', array());
        $tag_list = self::getPossibleIgnoreLinkingTags();
        $return_tags = array();

        if(!empty($tags) && is_array($tags)){
            foreach($tags as $tag){
                // if the tag is in the list of preapproved tags
                if(in_array($tag, $tag_list, true)){
                    // add it to the return list
                    $return_tags[] = $tag;
                }
            }
        }

        return $return_tags;
    }

    /**
     * Gets if the user wants to update the Post Modified date when links are inserted.
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function updatePostModifiedDate()
    {
        return (!empty(get_option('wpil_update_post_edit_date', false)));
    }

    /**
     * Gets if the user wants to force all LW created links to be in HTTPS.
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function forceHTTPS()
    {
        return (!empty(get_option('wpil_force_https_links', false)));
    }

    /**
     * Gets if the user wants to make suggestion matches based on some of the words in the post title.
     **/
    public static function matchPartialTitles()
    {
        return (!empty(get_option('wpil_get_partial_titles', false)));
    }

    /**
     * Checks to see if the user has saved auth credentials on the site and has gotten authed in the past
     * @return bool
     **/
    public static function HasGSCCredentials(){
        $credentials = get_option('wpil_search_console_data');
        return (!empty($credentials) && isset($credentials['authorized']) && $credentials['authorized'] != false && isset($credentials['access_token']) && !empty($credentials['access_token']));
    }

    /**
     * Gets the configuration data for the GSC integration.
     * Was formerly in the GSC class, but instantiating the class would trigger a call to Google.
     * If the site wasn't connected, this would be unnecessary and would result in a 401 error.
     * @return array
     **/
    public static function getGSCConfiguration(){
        // get the auth method
        $method = get_option('wpil_gsc_auth_method', 'standard');

        switch($method){
            case 'standard':
                $credentials = self::get_credentials();

                $state = base64_encode(get_rest_url(null, '/' . Wpil_Rest::NAMESPACE . '/' . Wpil_Rest::ROUTE));

                $config = [
                    'application_name' => 'Link Whisper',
                    'redirect_uri'     => WPIL_STORE_URL . '/wp-json/link-whisper/auth',
                    'scopes'           => [ 'https://www.googleapis.com/auth/webmasters.readonly' ],
                    'access_type'      => 'offline',
                    'state'            => $state
                ];

                $config = array_merge($config, $credentials);

            break;
            case 'custom_auth':
                $config = get_option('wpil_gsc_custom_config', array());
                if(!empty($config)){
                    $config['redirect_uri'] = 'urn:ietf:wg:oauth:2.0:oob';
                    $config['scopes']       = array('https://www.googleapis.com/auth/webmasters.readonly');
                }
            break;
            case 'legacy_api':
                // todo fill out
            break;
        }

        // todo handle empty config further down the line
        return $config;
    }

    public static function get_credentials ()
    {
        $credentials = get_option('wpil_gsc_remote_credentials', array());
        if(empty($credentials)){
            return self::get_remote_gsc_credentials();
        }else{
            $credentials = Wpil_Toolbox::deep_decrypt($credentials);
            
            // if the credentials don't have a valid client_id (probs because the salt/key has changed)
            if(!isset($credentials['client_id']) && !empty(Wpil_Toolbox::get_key()) && !empty(Wpil_Toolbox::get_salt())){
                // try getting some new ones and return the results of the attempt
                return self::get_remote_gsc_credentials();
            }

            return $credentials;
        }
        return [];
    }

    /**
     * Gets the GSC credentials from the proxy server and stores them in an option if they're available.
     **/
    private static function get_remote_gsc_credentials(){
        $response = wp_remote_get(WPIL_STORE_URL . '/wp-json/link-whisper/credentials', [
            'body' => [
                'name' => WPIL_PLUGIN_NAME
            ]
        ]);

        if ( !is_wp_error($response) && !empty($response = json_decode($response['body'], true)) ) {
            if ( isset($response['credentials']) ) {
                update_option('wpil_gsc_remote_credentials', Wpil_Toolbox::deep_encrypt($response['credentials']));
                return $response['credentials'];
            }
        }

        // if there's no creds, return an array
        return array();
    }

    /**
     * Gets the authentication URL for the GSC connection.
     * Was formerly in the GSC class, but instantiating the class would trigger a call to Google.
     * If the site wasn't connected, this would be unnecessary and would result in a 401 error.
     * @return string
     **/
    public static function getGSCAuthUrl(){
        $config = self::getGSCConfiguration();

        $url = add_query_arg([
                                 'response_type' => 'code',
                                 'client_id'     => $config['client_id'],
                                 'redirect_uri'  => $config['redirect_uri'],
                                 'scope'         => implode(' ', $config['scopes']),
                                 'state'         => $config['state'],
                                 'access_type'   => $config['access_type']
                             ], 'https://accounts.google.com/o/oauth2/v2/auth');

        return esc_url_raw($url);
    }

    /**
     * Gets the target keyword sources the user has selected from the settings.
     * Automatically includes new keyword sources if the user hasn't saved them
     **/
    public static function getSelectedKeywordSources()
    {
        $kw_sources_known_at_save = get_option('wpil_available_target_keyword_sources', array());
        $kw_sources = Wpil_TargetKeyword::get_available_keyword_sources();
        $diffed_kw_sources = array_diff($kw_sources, $kw_sources_known_at_save);
        $selected_sources = get_option('wpil_selected_target_keyword_sources', $kw_sources);
        return array_merge($selected_sources, $diffed_kw_sources, array('custom'));
    }

    /**
     * Gets if links should have any HTML tags in their anchor texts removed when they are deleted.
     **/
    public static function delete_link_inner_html(){
        return !empty(get_option('wpil_delete_link_inner_html', false));
    }

    /**
     * Check if need to show full HTML in suggestions
     *
     * @return bool
     */
    public static function fullHTMLSuggestions()
    {
        return !empty(get_option('wpil_full_html_suggestions'));
    }

    /**
     * Checks to see if the user has disabled post updating on follow-up actions.
     * Things like the URL Changer's update_post call after the changing code
     **/
    public static function disable_followup_post_updating(){
        return apply_filters('wpil_disable_url_changer_update', false);
    }

    /**
     * Gets any active suggestion filter based on requested index
     * @param string $index The $_REQUEST or stored data index to search for
     * @return bool|array
     */
    public static function get_suggestion_filter($index = ''){
        if(empty($index)){
            return false;
        }

        $filters_persistent = !empty(get_option('wpil_make_suggestion_filtering_persistent', false));
        $filtering_settings = ($filters_persistent) ? get_user_meta(get_current_user_id(), 'wpil_persistent_filter_settings', true) : false;

        $status = false;
        switch ($index) {
            // bool filters
            case 'same_category':
            case 'same_tag':
            case 'select_post_types':
                if($filters_persistent){
                    $status = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? true: false;
                }else{
                    $status = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? true: false;
                }
            break;
            // number array filters
            case 'selected_category':
            case 'selected_tag':
                if($filters_persistent){
                    $data = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? $filtering_settings[$index]: array();
                }else{
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                }

                $status = (!empty($data) && is_array($data)) ? array_filter(array_map(function($dat){ return (int)$dat; }, $data)): array();
            break;
            // selected post type filter
            case 'selected_post_types':
                if($filters_persistent){
                    $data = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? $filtering_settings[$index]: array();
                }else{
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                }

                // make sure the post types that are being requested are ones that the user selected in the settings
                $status = (!empty($data) && is_array($data)) ? array_intersect(Wpil_Settings::getPostTypes(), $data): array();
            break;
            default:
                $status = false;
                break;
        }

        return $status;
    }

    /**
     * Updates the suggestion filter settings based on $_REQUEST data
     **/
    public static function update_suggestion_filters(){
        // if we're not making the filters persistent
        if(empty(get_option('wpil_make_suggestion_filtering_persistent', false))){
            // exit
            return;
        }

        // set the default state of the filters. (off)
        $setting_data = array(
            'same_category' => false,
            'same_tag' => false,
            'select_post_types' => false,
            'selected_category' => array(),
            'selected_tag' => array(),
            'selected_post_types' => array()
        );

        // go over the $_REQUEST variable to see if any of the filters are turned on
        foreach($setting_data as $index => $default){
            switch ($index) {
                // bool filters
                case 'same_category':
                case 'same_tag':
                case 'select_post_types':
                    $status = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? true: false;
                break;
                // number array filters
                case 'selected_category':
                case 'selected_tag':
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                    $status = (!empty($data) && is_array($data)) ? array_filter(array_map(function($dat){ return (int)$dat; }, $data)): array();
                break;
                // selected post type filter
                case 'selected_post_types':
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                    // make sure the post types that are being requested are ones that the user selected in the settings
                    $status = (!empty($data) && is_array($data)) ? array_intersect(Wpil_Settings::getPostTypes(), $data): array();
                break;
                default:
                    $status = false;
                    break;
            }

            // if there is a filter active
            if(!empty($status)){
                // save the data
                $setting_data[$index] = $status;
            }
        }

        // update the stored settings with the results of our efforts
        update_user_meta(get_current_user_id(), 'wpil_persistent_filter_settings', $setting_data); // the settings are user-specific
    }

    /**
     * Gets the selected suggestion filtering options in a URL encoded string for when the suggestions are initially loaded
     * Checks for the global post type suggestion setting
     **/
    public static function get_suggestion_filter_string(){
        $indexes = array(
            'same_category',
            'same_tag',
            'select_post_types',
            'selected_category',
            'selected_tag',
            'selected_post_types'
        );

        $string_data = array();
        $suggestion_post_type_filtering = (!empty(get_option('wpil_limit_suggestions_to_post_types', false))) ? self::getSuggestionPostTypes() : false;

        foreach($indexes as $index){
            $filter_setting = self::get_suggestion_filter($index);
            if(!empty($filter_setting)){
                $string_data[$index] = is_array($filter_setting) ? implode(',', $filter_setting): $filter_setting;
            }
        }

        // if the user has selected a limited set of post types to point suggestions to
        if(!empty($suggestion_post_type_filtering) && is_array($suggestion_post_type_filtering)){
            $string_data['select_post_types'] = 1; // check the "filter post types" box
            $string_data['selected_post_types'] = implode(',', $suggestion_post_type_filtering); // and set the post types
        }

        return !empty($string_data) ? '&' . http_build_query($string_data): '';
    }

    /**
     * Get links that was marked as external
     *
     * @return array
     */
    public static function getIgnoreNofollowDomains()
    {
        $domains = get_option('wpil_nofollow_ignore_domains', '');

        if (!empty($domains)) {
            $domains = explode("\n", $domains);
            foreach ($domains as $key => $domain) {
                $domain = wp_parse_url(trim($domain), PHP_URL_HOST);

                if(empty($domain)){
                    continue;
                }

                $domains[$key] = $domain;
            }

            return $domains;
        }

        return [];
    }

    /**
     * Get a list of domains that have been marked as "sponsored"
     *
     * @return array
     */
    public static function getSponsoredDomains()
    {
        $domains = get_option('wpil_sponsored_domains', '');

        if (!empty($domains)) {
            $domains = explode("\n", $domains);
            foreach ($domains as $key => $domain) {
                // if the domain doesn't have the protocol included
                if(false === strpos($domain, 'http')){
                    // add a protocol so that wp_parse_url can process it correctly
                    $domain = 'http://' . ltrim($domain, '/:');
                }
                $domains[$key] = wp_parse_url(trim($domain), PHP_URL_HOST);
            }

            return $domains;
        }

        return [];
    }

    /**
     * Get a list of domains that have been marked as "nofollow"
     *
     * @return array
     */
    public static function getNofollowDomains()
    {
        $domains = get_option('wpil_nofollow_domains', '');

        if (!empty($domains)) {
            $domains = explode("\n", $domains);
            foreach ($domains as $key => $domain) {
                // if the domain doesn't have the protocol included
                if(false === strpos($domain, 'http')){
                    // add a protocol so that wp_parse_url can process it correctly
                    $domain = 'http://' . ltrim($domain, '/:');
                }
                $domains[$key] = wp_parse_url(trim($domain), PHP_URL_HOST);
            }

            return $domains;
        }

        return [];
    }

    /**
     * Get links that the user wants to ignore
     *
     * @return array
     */
    public static function getIgnoreLinks()
    {
        $links = get_transient('wpil_links_to_ignore');
        if(empty($links)){

            $links = get_option('wpil_links_to_ignore', array());
            if (!empty($links)) {
                $links = explode("\n", $links);
                foreach ($links as $key => $link) {
                    if(empty(trim($link)) || empty(esc_url_raw($link)) && !Wpil_URLChanger::isRelativeLink($link)){
                        unset($links[$key]);
                    }else{
                        $links[$key] = trim($link);
                    }
                }

            }
            if(empty($links)){
                $links = 'no-links-ignored';
            }

            set_transient('wpil_links_to_ignore', $links, 60 * MINUTE_IN_SECONDS);
        }

        if($links === 'no-links-ignored'){
            return array();
        }

        return $links;
    }

    /**
     * Gets an array of any classes that the user wants to be ignored from both the Link Report and the Suggestions
     **/
    public static function get_ignored_element_classes(){
        $classes = get_transient('wpil_ignore_elements_by_class');
        if(empty($classes)){

            $classes = get_option('wpil_ignore_elements_by_class', array());
            if(!empty($classes)){
                $classes = explode("\n", $classes);
                foreach($classes as $key => $class){
                    $class = trim($class);
                    if(empty($class)){
                        unset($classes[$key]);
                    }else{
                        $classes[$key] = $class;
                    }
                }
            }
            if(empty($classes)){
                $classes = 'no-elements-ignored';
            }

            set_transient('wpil_ignore_elements_by_class', $classes, 60 * MINUTE_IN_SECONDS);
        }

        if($classes === 'no-elements-ignored'){
            return array();
        }

        return $classes;
    }

    /**
     * Gets an array of shortcode names that the user wants to ignore
     **/
    public static function get_ignored_shortcode_names(){
        $shortcodes = get_transient('wpil_ignore_shortcodes_by_name');
        if(empty($shortcodes)){

            $shortcodes = get_option('wpil_ignore_shortcodes_by_name', array());
            if(!empty($shortcodes)){
                $shortcodes = explode("\n", $shortcodes);
                foreach($shortcodes as $key => $shortcode){
                    $shortcode = trim(preg_replace('`[^\w-]`', '', $shortcode)); // remove all non-word chars minus hyphens from the shortcode name
                    if(empty($shortcode)){
                        unset($shortcodes[$key]);
                    }else{
                        $shortcodes[$key] = $shortcode;
                    }
                }
            }
            if(empty($shortcodes)){
                $shortcodes = 'no-shortcodes-ignored';
            }

            set_transient('wpil_ignore_shortcodes_by_name', $shortcodes, 60 * MINUTE_IN_SECONDS);
        }

        if($shortcodes === 'no-shortcodes-ignored'){
            return array();
        }

        return $shortcodes;
    }

    /**
     * Gets an array of post & term ids that the user wants to ignore.
     **/
    public static function get_completely_ignored_pages(){
        $pages = get_transient('wpil_ignore_pages_completely');
        if(empty($pages)){
            $pages = array();

            $page_links = get_option('wpil_ignore_pages_completely', array());
            if(!empty($page_links)){
                $page_links = explode("\n", $page_links);
                foreach ($page_links as $link) {
                    $post = Wpil_Post::getPostByLink(trim($link));
                    if (!empty($post)) {
                        $pages[] = $post->type . '_' . $post->id;
                    }
                }
            }
            if(empty($pages)){
                $pages = 'no-pages-ignored';
            }

            set_transient('wpil_ignore_pages_completely', $pages, 60 * MINUTE_IN_SECONDS);
        }

        if($pages === 'no-pages-ignored'){
            return array();
        }

        return $pages;
    }

    /**
     * Get links that was marked as external
     *
     * @return array
     */
    public static function getMarkedAsExternalLinks()
    {
        $links = get_option('wpil_marked_as_external', '');

        if (!empty($links)) {
            $links = explode("\n", $links);
            foreach ($links as $key => $link) {
                $links[$key] = trim($link);
            }

            return $links;
        }

        return [];
    }

    /**
     * Gets an array of ACF fields that the user wants to ignore from processing
     **/
    public static function getIgnoredACFFields(){
        $field_data = get_transient('wpil_ignore_acf_fields');
        if(empty($field_data)){
            $field_data = get_option('wpil_ignore_acf_fields', array());

            if(is_string($field_data)){
                $field_data = array_map('trim', explode("\n", $field_data));
            }

            set_transient('wpil_ignore_acf_fields', $field_data, 60 * MINUTE_IN_SECONDS);
        }

        return $field_data;
    }

    /**
     * Gets an array of URLs and anchors that the user doesn't want tracked by the click tracking
     * @return array
     **/
    public static function getIgnoredClickLinks(){
        $click_data = get_transient('wpil_ignore_click_links');
        if(empty($click_data)){
            $click_data = get_option('wpil_ignore_click_links', array());

            if(is_string($click_data)){
                $click_data = array_map('trim', explode("\n", $click_data));
            }elseif(empty($click_data)){
                $click_data = 'no-links-ignored';
            }

            set_transient('wpil_ignore_click_links', $click_data, 60 * MINUTE_IN_SECONDS);
        }

        if($click_data === 'no-links-ignored'){
            return array();
        }

        return $click_data;
    }

    /**
     * Gets a list of posts that have had redirects applied to their urls.
     * Obtains the redirect list from plugins that offer redirects.
     * Results are cached for 5 minutes
     * 
     * @param bool $flip Should we return a flipped array of post ids so they can be searched easily?
     * @return array $post_ids And array of posts that have had redirections applied to them
     **/
    public static function getRedirectedPosts($flip = false){
        global $wpdb;

        $post_ids = get_transient('wpil_redirected_post_ids');

        if(!empty($post_ids) && $post_ids !== 'no-ids'){
            // refresh the transient
            set_transient('wpil_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);
            // and return the ids
            return ($flip) ? array_flip($post_ids) : $post_ids;
        }elseif($post_ids === 'no-ids'){
            // if a prevsious run hadn't found any ids, return an empty array
            return array();
        }

        // set up the id array
        $post_ids = array();

        // if RankMath is active and the redirections table exists
        if(defined('RANK_MATH_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}rank_math_redirections'"))){
            $dest_url_cache = array();

            $permalink_format = get_option('permalink_structure', '');
            $post_name_position = false;

            if(false !== strpos($permalink_format, '%postname%')){
                $pieces = explode('/', $permalink_format);
                $piece_count = count($pieces);
                $post_name_position = array_search('%postname%', $pieces);
            }

            // get the active redirect rules from Rank Math
            $active_redirections = $wpdb->get_results("SELECT `id`, `url_to` FROM {$wpdb->prefix}rank_math_redirections WHERE `status` = 'active'");

            // if there are redirections
            if(!empty($active_redirections)){
                $redirection_ids = array();
                foreach($active_redirections as $dat){
                    if(!isset($dest_url_cache[$dat->url_to])){
                        $id = url_to_postid($dat->url_to);
                        $dest_url_cache[$dat->url_to] = $id;
                    }

                    $redirection_ids[] = $dat->id;
                }

                // if there are posts with updated urls, get the ids so we can ignore them
                $ignore_posts = '';
                if(!empty($dest_url_cache) && !empty(array_filter(array_values($dest_url_cache)))){
                    $ignore_posts = "AND `object_id` NOT IN (" . implode(', ',array_filter(array_values($dest_url_cache))) . ")";
                }

                $redirection_ids = implode(', ', $redirection_ids);
                $redirection_data = $wpdb->get_results("SELECT `from_url`, `object_id` FROM {$wpdb->prefix}rank_math_redirections_cache WHERE `redirection_id` IN ({$redirection_ids}) {$ignore_posts}"); // we're getting the redriects from the cache to save processing time. Rules based searching could take a long time

                // go over the data from the Rank Math cache
                $post_names = array();
                foreach($redirection_data as $dat){
                    // if a redirect was specified for a post, grab the id directly
                    if(isset($dat->object_id) && !empty($dat->object_id)){
                        $post_ids[] = $dat->object_id;
                    }else{
                        // if a url was redirected based on a rule, try to get the post name from the data so we can search the post table for it
                        $url_pieces = explode('/', $dat->from_url);
                        $url_pieces_count = count($url_pieces);

                        if($post_name_position && $url_pieces_count === $piece_count){  // if the url uses the permalink settings and therefor has the same number of pieces as the permalink string (EX: it's a post)
                            $post_names[] = $url_pieces[$post_name_position];
                        }elseif($url_pieces_count === 1){                               // if the url is just the slug
                            $post_names[] = $dat->from_url;
                        }elseif($url_pieces_count === 2 || $url_pieces_count === 3){    // if the url is just the slug, but there's a slash or two
                            $post_names[] = $url_pieces[1];
                        }
                    }
                }

                // if we've found the post names
                if(!empty($post_names)){
                    // query the post table with them to get the post ids
                    $post_names = implode('\', \'', $post_names);
                    $ids = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` IN ('{$post_names}')");

                    // if there's ids
                    if(!empty($ids)){
                        // add them to the list of post ids that are redirected away from
                        $post_ids = array_merge($post_ids, $ids);
                    }
                }
            }
        }

        // if there aren't any ids
        if(empty($post_ids)){
            // make a note that there aren't any and return an empty
            set_transient('wpil_redirected_post_ids', 'no-ids', 5 * MINUTE_IN_SECONDS);
        }else{
            // save the fruits of our labours in the cache
            set_transient('wpil_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);
        }

        return ($flip && !empty($post_ids)) ? array_flip($post_ids) : $post_ids;
    }

    /**
     * Obtains an array of URLs that have been redirected away from and their destination URLs.
     * The output is an array of new URLs keyed to the old URLs that are being redirected away from.
     * All URLs are trailing slashed for consistency.
     * When comparing URLs in content to the URLs, be sure to slash them.
     *
     * Currently supports Rank Math and Redirection (John Godley)
     * At the moment, we're only focusing on the absolute versions of the URLs.
     * Nobody has asked for relative, and there's only been a couple users that have ever mentioned using relative links.
     * Added to this is the fact that the inbound linking functionality only counts absolute URLs makes adding relative moot.
     **/
    public static function getRedirectionUrls(){
        global $wpdb;

        $urls = get_transient('wpil_redirected_post_urls');

        if(!empty($urls) && $urls !== 'no-redirects'){
            // refresh the transient
            set_transient('wpil_redirected_post_urls', $urls, 5 * MINUTE_IN_SECONDS);
            // and return the URLs
            return $urls;
        }elseif($urls === 'no-redirects'){
            return array();
        }

        // set up the url array
        $urls = array();

        if(defined('RANK_MATH_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}rank_math_redirections'"))){
            // get the active redirect rules from Rank Math
            $active_redirections = $wpdb->get_results("SELECT `id`, `url_to` FROM {$wpdb->prefix}rank_math_redirections WHERE `status` = 'active'");

            // if there are redirections
            if(!empty($active_redirections)){

                $redirection_ids = array();
                foreach($active_redirections as $dat){
                    $redirection_ids[$dat->id] = trailingslashit($dat->url_to);
                }

                $id_string = implode(', ', array_keys($redirection_ids));
                $redirection_data = $wpdb->get_results("SELECT `from_url`, `object_id`, `redirection_id` FROM {$wpdb->prefix}rank_math_redirections_cache WHERE `redirection_id` IN ({$id_string})"); // we're getting the redriects from the cache to save processing time. Rules based searching could take a long time

                // go over the data from the Rank Math cache
                foreach($redirection_data as $dat){
                    $url = trailingslashit(self::makeLinkAbsolute($dat->from_url));
                    $redirected_url = trailingslashit(self::makeLinkAbsolute($redirection_ids[$dat->redirection_id]));
                    $urls[$url] = $redirected_url;
                }
            }
        }

        if(defined('WPSEO_VERSION')){
            $active_redirections   = $wpdb->get_results("SELECT option_name, option_value FROM  {$wpdb->options} WHERE option_name = 'wpseo-premium-redirects-export-plain'");
            foreach ( $active_redirections as $redirection ) {
                $dat = maybe_unserialize($redirection->option_value);
                if(!empty($dat)){
                    foreach($dat as $key => $d){
                        $url = trailingslashit(self::makeLinkAbsolute($key));
                        $redirected_url = trailingslashit(self::makeLinkAbsolute($d['url']));
                        $urls[$url] = $redirected_url;
                    }
                }
            }
        }

        /**
         * Search for the redirects from the dedicated redirect pl;ugin last to override the SEO plugins' redirects
         **/
        if(defined('REDIRECTION_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}redirection_items'"))){
            // get the redirect plugin data
            $active_redirections = $wpdb->get_results("SELECT `url`, `action_data` FROM {$wpdb->prefix}redirection_items WHERE `match_type` ='url' AND `match_url` != 'regex'");

            // add the redirections to the url list
            foreach($active_redirections as $dat){
                if(is_string($dat->action_data)){
                    $url = trailingslashit(self::makeLinkAbsolute($dat->url));
                    $action_data = trailingslashit(self::makeLinkAbsolute($dat->action_data));
                    $urls[$url] = $action_data;
                }
            }
        }

        // if we've found some redirected urls
        if(!empty($urls)){
            // save the fruits of our labours in the cache
            set_transient('wpil_redirected_post_urls', $urls, 5 * MINUTE_IN_SECONDS);
        }else{
            // otherwise, set a flag so we know there's no urls to keep an eye out for
            set_transient('wpil_redirected_post_urls', 'no-redirects', 5 * MINUTE_IN_SECONDS);
        }

        if('no-redirects' === $urls){
            return array();
        }

        return $urls;
    }

    /**
     * Makes the supplied link an absolute one.
     * If the link is already absolute, the link is returned unchanged
     * 
     * @param string $url The relative link to make absolute
     * @return string $url The absolute version of the link
     **/
    public static function makeLinkAbsolute($url){
        $site_url = trailingslashit(get_home_url());
        $site_domain = wp_parse_url($site_url, PHP_URL_HOST);
        $site_scheme = wp_parse_url($site_url, PHP_URL_SCHEME);
        $url_domain = wp_parse_url($url, PHP_URL_HOST);

        // if the link isn't pointing to the current domain, 
        if( strpos($url, $site_domain) === false && 
            empty($url_domain) &&                       // but also isn't pointing to an external one
            strpos($url, 'www.') !== 0)                 // and doesn't start with "www.". (Even though browsers DO consider this to be a relative URL. The user didn't mean for it to be)
        {
            $url = ltrim($url, '/');
            $url_pieces = array_reverse(explode('/', rtrim(trim($site_url), '/')));

            foreach($url_pieces as $piece){
                if(empty($piece) || false === strpos(trim($url), $piece)){
                    $url = $piece . '/' . $url;
                }
            }
        }elseif(strpos($url, 'http') === false){
            $url = rtrim($site_scheme, ':') . '://' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Gets the labels for the given post types.
     * Currently, only gets the labels for the public post types because the non-public ones are usually utility post types and the labels are often generic.
     * So if we used their given labels, it may confuse the user.
     *
     * @param string|array $post_types The list of post types that we're getting the labels for. Can also accept a single post type string
     * @return array $labled_types An array of post type labels keyed to their respective post types. Or an empty array if we can't find the post types...
     **/
    public static function getPostTypeLabels($post_types = array()){
        $labled_types = array();

        if(empty($post_types) || (!is_array($post_types) && !is_string($post_types))){
            return $labled_types;
        }

        if(is_string($post_types)){
            $post_types = array($post_types);
        }

        foreach($post_types as $type){
            $type_object = get_post_type_object($type);
            if(!empty($type_object)){
                if(!empty($type_object->public)){
                    $labled_types[$type_object->name] = $type_object->label;
                }else{
                    $labled_types[$type_object->name] = $type_object->name;
                }
            }
        }

        return $labled_types;
    }

    /**
     * Gets an array of WP constants that are active on the site and could have some impact on Link Whisper's functioning.
     **/
    public static function get_wp_constants($constant = ''){
        $constants = array();

        if(defined('WP_MEMORY_LIMIT')){
            $constants['WP_MEMORY_LIMIT'] = WP_MEMORY_LIMIT;
        }

        if(defined('WP_MAX_MEMORY_LIMIT')){
            $constants['WP_MAX_MEMORY_LIMIT'] = WP_MAX_MEMORY_LIMIT;
        }
        
        if(defined('DISABLE_WP_CRON')){
            $constants['DISABLE_WP_CRON'] = DISABLE_WP_CRON;
        }

        if(!empty($constant) && !empty($constants) && isset($constants[$constant])){
            return $constants[$constant];
        }elseif(!empty($constant)){
            return null;
        }

        return $constants;
    }
}
