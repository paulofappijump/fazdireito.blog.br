<?php

/**
 * Work with keywords
 */
class Wpil_Keyword
{
    public function register()
    {
        add_action('wp_ajax_wpil_keyword_delete', [$this, 'delete']);
        add_action('wp_ajax_wpil_keyword_add', [$this, 'add']);
        add_action('wp_ajax_wpil_keyword_reset', [$this, 'reset']);
        add_action('wp_ajax_wpil_insert_selected_keyword_links', [$this, 'insertSelectedLinks']);
        add_action('wp_ajax_wpil_bulk_keyword_add', [$this, 'bulk_create_autolinks']);
        add_action('wp_ajax_wpil_bulk_keyword_process', [$this, 'bulk_process_autolinks']);
        add_filter('screen_settings', array(__CLASS__, 'show_screen_options'), 11, 2);
        add_filter('set_screen_option_wpil_keyword_options', array(__CLASS__, 'saveOptions'), 12, 3);
        add_filter('wpil_process_keyword_list', array(__CLASS__, 'processKeywords'), 10, 1);
        add_filter('wpil_direct_add_keyword', array(__CLASS__, 'directStore'), 10, 1);
    }

    /**
     * Show settings page
     */
    public static function init()
    {
        if (!empty($_POST['save_settings'])) {
            self::saveSettings();
        }

        $user = wp_get_current_user();
        $reset = !empty(get_option('wpil_keywords_reset'));
        $table = new Wpil_Table_Keyword();
        $table->prepare_items();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/keywords.php';
    }

    public static function show_screen_options($settings, $screen_obj){

        $screen = get_current_screen();
        $options = get_user_meta(get_current_user_id(), 'wpil_keyword_options', true);

        // exit if we're not on the target keywords page
        if(!is_object($screen) || $screen->id != 'link-whisper_page_link_whisper_keywords'){
            return $settings;
        }

        // Check if the screen options have been saved. If so, use the saved value. Otherwise, use the default values.
        if ( $options ) {
            $per_page = !empty($options['per_page']) ? $options['per_page'] : 20 ;
            $hide_select_links = !empty($options['hide_select_links_column']) && $options['hide_select_links_column'] != 'off';
        } else {
            $per_page = 20;
            $hide_select_links = false;
        }

        //get apply button
        $button = get_submit_button( __( 'Apply', 'wp-screen-options-framework' ), 'primary large', 'screen-options-apply', false );

        //show HTML form
        ob_start();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/keyword_options.php';
        return ob_get_clean();
    }

    public static function saveOptions($status, $option, $value) {
        if(!wp_verify_nonce($_POST['screenoptionnonce'], 'screen-options-nonce')){
            return;
        }

        if ($option == 'wpil_keyword_options') {
            $value = [];
            if (isset( $_POST['wpil_keyword_options'] ) && is_array( $_POST['wpil_keyword_options'] )) {
                if (!isset($_POST['wpil_keyword_options']['hide_select_links_column'])) {
                    $_POST['wpil_keyword_options']['hide_select_links_column'] = 'off';
                }
                $value = $_POST['wpil_keyword_options'];
            }

            return $value;
        }

        return $status;
    }

    /**
     * Add new keyword
     */
    public static function add()
    {
        Wpil_Base::verify_nonce('wpil_keyword');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if (!empty($_POST['keyword_id'])) {
            if (isset($_POST['wpil_keywords_add_same_link']) && isset($_POST['wpil_keywords_link_once'])) {
                self::updateKeywordSettings();
            }

            $keyword = (is_array($_POST['keyword_id'])) ? array_map(function($id){ return (int)$id; }, $_POST['keyword_id']) : self::getKeywordByID((int)$_POST['keyword_id']);
        } else {
            $keyword = self::store();
        }

        if(!is_array($keyword)){
            self::checkPosts($keyword);
        }else{
            self::processKeywords($keyword);
        }
    }

    /**
     * Runs the autolink creation process for existing keywords based on keyword id.
     * Accepts an array of ids to process, and removes ids from the array as they are processed.
     * If there isn't enough time to complete the processing run, 
     * the ids to be processed are returned so they can be sent for another processing run.
     * 
     * @param array $ids An array of keyword ids to run the content insertion process for.
     * @return array|bool Returns unprocessed ids if there's more to process, an empty array when all ids are processed, and false if no ids are supplied.
     */
    public static function processKeywords($ids = array())
    {
        if(empty($ids)){
            return false;
        }

        $keyword_total = (empty($_POST['keyword_total'])) ? count($ids) + .1: (int)$_POST['keyword_total']; // the number of keywords to process
        $loop = (array_key_exists('loop', $_POST)) ? ((int)$_POST['loop'] + 1) : 0;
        $total = !empty($_POST['total']) ? (int)$_POST['total'] + 0.1 : 0.1; // The TOTAL number of posts to process for the keyword

        // if a single id was given, wrap it in an array
        if(is_int($ids)){
            $ids = array($ids);
        }

        // get the memory limit
        $memory_break_point = Wpil_Report::get_mem_break_point();

        // loop over the ids
        foreach($ids as $key => $id){
            // try getting the keyword from the DB
            $keyword = self::getKeywordByID((int)$id);

            // if we're close to the time limit or the memory limit, exit
            if(Wpil_Base::overTimeLimit(25) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ){
                break;
            }

            // skip to the next id if there's no keyword
            if(empty($keyword)){
                unset($ids[$key]);
                // also reset the loop
                $loop = 0;
                continue;
            }

            // run the autolink insertion process for a batch of posts using this keyword
            $results = self::checkPosts($keyword, true);

            // update the total count
            $total = $results['total'];

            // if all of the autolinks have been inserted
            if($results['finish']){
                // remove the current id from the list and proceed to the next one
                unset($ids[$key]);
                $loop = 0;
            }else{
                // if we have more posts to go over, break out of the loop
                break;
            }

        }

        $display_message = false;
        if(!empty($keyword) && !empty($ids)){
            // alternate the insert message between the percentage for the current keyword, and the total number of keywords
            if($loop % 3 === 0){
                $display_message = sprintf(__('%d of %d Autolink Rules Processed', 'wpil'), ((int)$keyword_total - count($ids)), (int)$keyword_total);
            }else{
                $display_message = sprintf(__('Creating "%s" autolinks: %d%% complete', 'wpil'), $keyword->keyword, $results['progress']);
            }
        }elseif(empty($ids)){ // if there's no keyword and no ids, we must be finishing up
            $display_message = __('Finishing up', 'wpil');
        }

        // return any remaining ids so they can be processed on another run
        wp_send_json([
            'nonce' => isset($_POST['nonce']) ? $_POST['nonce']: false,
            'displayMessage' => $display_message,
            'keyword_id' => $ids,
            'progress' => 100 - floor((count($ids) / $keyword_total) * 100),
            'total' => $total,
            'keyword_total' => $keyword_total,
            'loop' => $loop,
            'finish' => empty($ids)
        ]);
    }

    /**
     * Creates autolinks in bulk.
     * Doesn't handle keyword updating
     **/
    public static function bulk_create_autolinks(){
        Wpil_Base::verify_nonce('wpil_keyword');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // create the bulk keywords
        $keywords = self::bulk_store();

        if(!empty($keywords)){
            self::processKeywords($keywords);
        }else{
            wp_send_json(array('error' => array('title' => __('Autolink Data Not Saving', 'wpil'), 'text' => __('The autolink data could not be saved to the database. This could be caused for an error in the data\'s format, please reload the page and check to make sure the data is appropriately formatted.', 'wpil'))));
        }
    }

    /**
     * Bulk inserts a list of autolinks based on keyword id
     **/
    public static function bulk_process_autolinks(){
        Wpil_Base::verify_nonce('wpil_keyword');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(isset($_POST['keyword_ids']) && !empty($_POST['keyword_ids']) && is_array($_POST['keyword_ids'])){
            $_POST['keyword_ids'] = array_map(function($id){ return (int) $id;}, $_POST['keyword_ids']);
        }else{
            wp_send_json(array('error' => array('title' => __('Processing Error', 'wpil'), 'text' => __('No autolink ids detected in the queue. Please reload the page and see if the autolink rules have been created.', 'wpil'))));
        }

        self::processKeywords($_POST['keyword_ids']);
    }

    /**
     * Reset links data
     */
    public static function reset()
    {
        global $wpdb;

        //verify input data
        Wpil_Base::verify_nonce('wpil_keyword');
        if (empty($_POST['count']) || (int)$_POST['count'] > 9999) {
            wp_send_json([
                'nonce' => $_POST['nonce'],
                'finish' => true
            ]);
        }

        $memory_break_point = Wpil_Report::get_mem_break_point();
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 1;

        if ($_POST['count'] == 1) {
            //make matched posts array on the first call
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wpil_keyword_links");
            $statuses_query = Wpil_Query::postStatuses();
            $posts = $wpdb->get_results("SELECT ID as id, 'post' as type FROM {$wpdb->posts} WHERE post_content LIKE '%wpil_keyword_link%' $statuses_query");
            $posts = self::getLinkedPostsFromAlternateLocations($posts);
            $taxonomies = Wpil_Settings::getTermTypes();
            $terms = array();
            if(!empty($taxonomies)){
                $taxonomies = implode("','", $taxonomies);
                $terms = $wpdb->get_results("SELECT term_id as id, 'term' as type FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('{$taxonomies}') AND description LIKE '%wpil_keyword_link%'");
            }
            $posts = array_merge($posts, $terms);
            $total = count($posts);
        } else {
            //get unprocessed posts
            $posts = get_option('wpil_keywords_reset', []);
            if ($total < count($posts)) {
                $total = count($posts);
            }
        }

        foreach ($posts as $key => $post) {
            $alt = (isset($post->alt)) ? true: false;
            $post = new Wpil_Model_Post($post->id, $post->type);
            if($alt){
                $content = $post->getContent();
            }else{
                $content = $post->getCleanContent();
            }
            preg_match_all('`<a [^><]*?(?:class=["\'][^"\']*?wpil_keyword_link[^"\']*?["\']|data-wpil-keyword-link="linked")[^><]*?href="([^"\'].*?)"[^><]*?>(.*?)<\/a>|<a [^><]*?href="([^"\']*?)"[^><]*?(?:class=["\'][^"\']*?wpil_keyword_link[^"\']*?["\']|data-wpil-keyword-link="linked")[^><]*?>(.*?)<\/a>`i', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {

                if(!empty($matches[1][$i]) && !empty($matches[2][$i])){
                    $link = $matches[1][$i];
                    $keyword = $matches[2][$i];
                }

                if(!empty($matches[3][$i]) && !empty($matches[4][$i])){
                    $link = $matches[3][$i];
                    $keyword = $matches[4][$i];
                }

                if (!empty($link) && !empty($keyword)) {
                    $keyword_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wpil_keywords WHERE keyword = '$keyword' AND link = '$link'");

                    if (empty($keyword_id)) {
                        //create new keyword
                        $wpdb->insert($wpdb->prefix . 'wpil_keywords', [
                            'keyword' => $keyword,
                            'link' => $link,
                            'add_same_link' => get_option('wpil_keywords_add_same_link'),
                            'link_once' => get_option('wpil_keywords_link_once'),
                            'select_links' => get_option('wpil_keywords_select_links'),
                        ]);
                        $keyword_id = $wpdb->insert_id;
                    }

                    $wpdb->insert($wpdb->prefix . 'wpil_keyword_links', [
                        'keyword_id' => $keyword_id,
                        'post_id' => $post->id,
                        'post_type' => $post->type,
                        'anchor' => $keyword,
                    ]);
                }
            }

            unset($posts[$key]);

            //break process if limits were reached
            if (Wpil_Base::overTimeLimit(7, 15) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)) {
                update_option('wpil_keywords_reset', $posts);
                break;
            }
        }

        if (empty($posts)) {
            update_option('wpil_keywords_reset', []);
        }

        wp_send_json([
            'nonce' => $_POST['nonce'],
            'ready' => $total - count($posts),
            'count' => ++$_POST['count'],
            'total' => $total,
            'finish' => empty($posts)
        ]);
    }

    /**
     * Inserts the links the user has selected from the autolink report page
     **/
    public static function insertSelectedLinks(){
        Wpil_Base::verify_nonce('wpil_keyword');

        $selected_ids = array_map(function($id){ return (int)$id; }, $_POST['link_ids']);

        if(empty($selected_ids)){
            wp_send_json(array('error' => array('title' => __('No Links Selected', 'wpil'), 'text' => __('Please select some links to insert', 'wpil'))));
        }

        $insert = array();
        $links = self::getPossibleLinksByID($selected_ids);
        $keyword_cache = array();

        foreach($links as $link){
            $post = (object) array('id' => $link->post_id, 'type' => $link->post_type);

            if(isset($link->keyword_id) && !isset($keyword_cache[$link->keyword_id])){
                $keyword_cache[$link->keyword_id] = self::getKeywordByID($link->keyword_id);
            }

            if(isset($keyword_cache[$link->keyword_id])){
                // add the link to the list of links to create
                $insert[$post->id . '_' . $post->type][] = maybe_unserialize($link->meta_data);

                // save the link ref to the db
                self::saveLinkToDB($keyword_cache[$link->keyword_id], $post, $link->case_keyword);

                // and remove the link from the potential list
                self::deletePossibleLinksById($link->id);
            }
        }

        //add links to all editors
        if (!empty($insert)) {
            // unhook the link adding to content from the post data insert action so duplicates aren't inserted
            Wpil_Base::remove_hooked_function('wp_insert_post_data', 'Wpil_Post', 'addLinksToContent', 9999);

            foreach($insert as $key => $meta){
                $post = explode('_', $key);

                if ($post[1] == 'term') {
                    // add any existing links to insert to the metadata
                    $existing_links = get_term_meta($post[0], 'wpil_links', true);
                    $meta = (!empty($existing_links) && is_array($existing_links)) ? array_merge($meta, $existing_links): $meta;
                    // update the stored meta
                    update_term_meta($post[0], 'wpil_links', $meta);
                    Wpil_Term::addLinksToTerm($post[0]);
                    // delete the term meta to avoid duplicate inserts
                    delete_term_meta($post[0], 'wpil_links', true);
                } else {
                    // add any existing links to insert to the metadata
                    $existing_links = get_post_meta($post[0], 'wpil_links', true);
                    $meta = (!empty($existing_links) && is_array($existing_links)) ? array_merge($meta, $existing_links): $meta;
                    // update the stored meta
                    update_post_meta($post[0], 'wpil_links', $meta);
                    Wpil_Post::addLinksToContent(null, ['ID' => $post[0]], array());
                    // delete the post meta to avoid duplicate inserts
                    delete_post_meta($post[0], 'wpil_links', true);
                }
            }
        }

        wp_send_json(array('success' => array('title' => __('Selected Links Created!', 'wpil'), 'text' => __('The selected auto links have been inserted!', 'wpil'))));
    }

    /**
     * Save keyword to DB
     *
     * @param $keyword
     * @param $link
     * @return object
     */
    public static function store()
    {
        global $wpdb;
        $keyword_data = trim(sanitize_text_field($_POST['keyword']));

        // if the keyword has been double quoted, don't split it on the commas
        if(0 === strpos($keyword_data, '\"') && strrpos($keyword_data, '\"') === (strlen($keyword_data) - 2)){
            $keyword_data = array(trim(substr($keyword_data, 2, strlen($keyword_data) - 4)));
        }else{
            $keyword_data = explode(',', $keyword_data);
        }

        $link = trim(esc_url_raw($_POST['link']));

        $priority = (isset($_POST['wpil_keywords_priority_setting']) && !empty($_POST['wpil_keywords_priority_setting'])) ? (int)$_POST['wpil_keywords_priority_setting']: 0;

        $restrict_date = (isset($_POST['wpil_keywords_restrict_date']) && !empty($_POST['wpil_keywords_restrict_date'])) ? 1: 0;
        $date = null;
        if(isset($_POST['wpil_keywords_restricted_date']) && !empty($_POST['wpil_keywords_restricted_date'])){
            $date = preg_replace("([^0-9-])", "", $_POST['wpil_keywords_restricted_date']);
            if($date !== $_POST['wpil_keywords_restricted_date']){
                $date = null;
            }
        }

        $restrict_cats = (isset($_POST['wpil_keywords_restrict_to_cats']) && !empty($_POST['wpil_keywords_restrict_to_cats'])) ? 1: 0;
        $term_ids = '';
        if(isset($_POST['restricted_cats']) && !empty($_POST['restricted_cats'])){
            $ids = array_map(function($num){ return (int)$num; }, $_POST['restricted_cats']);
            $term_ids = implode(',', $ids);
        }

        $force_insert = (isset($_POST['wpil_keywords_force_insert']) && !empty($_POST['wpil_keywords_force_insert'])) ? 1: 0;

        self::saveSettings();
        self::prepareTable();
        $insert_ids = array();
        foreach($keyword_data as $keyword){
            $keyword = trim($keyword);
            if(empty($keyword)){
                continue;
            }

            $wpdb->insert($wpdb->prefix . 'wpil_keywords', [
                'keyword' => $keyword,
                'link' => $link,
                'add_same_link' => get_option('wpil_keywords_add_same_link'),
                'link_once' => get_option('wpil_keywords_link_once'),
                'select_links' => get_option('wpil_keywords_select_links'),
                'set_priority' => get_option('wpil_keywords_set_priority'),
                'priority_setting' => $priority,
                'restrict_date' => $restrict_date,
                'restricted_date' => $date,
                'restrict_cats' => $restrict_cats,
                'restricted_cats' => $term_ids,
                'case_sensitive' => get_option('wpil_keywords_case_sensitive'),
                'force_insert' => $force_insert
            ]);

            $insert_ids[] = $wpdb->insert_id;
        }

        return (count($insert_ids) === 1) ? self::getKeywordByID($wpdb->insert_id): $insert_ids;
    }

    /**
     * Bulk save keywords to DB
     *
     * @param $keyword
     * @param $link
     * @return object
     */
    public static function bulk_store()
    {
        global $wpdb;

        // if there isn't any data or it's not an array, exit
        if(!isset($_POST['keyword_data']) || empty($_POST['keyword_data']) || !is_array($_POST['keyword_data'])){
            return false;
        }

        // setup the keyword id array
        $insert_ids = array();

        // go over the keywords 
        foreach($_POST['keyword_data'] as $dat){
            $keyword_data = trim(sanitize_text_field($dat['keyword']));

            // if the keyword has been double quoted, don't split it on the commas
            if(0 === strpos($keyword_data, '\"') && strrpos($keyword_data, '\"') === (strlen($keyword_data) - 2)){
                $keyword_data = array(trim(substr($keyword_data, 2, strlen($keyword_data) - 4)));
            }else{
                $keyword_data = explode(',', $keyword_data);
            }

            $link = trim(esc_url_raw($dat['link']));

            $priority = (isset($dat['wpil_keywords_priority_setting']) && !empty($dat['wpil_keywords_priority_setting'])) ? (int)$dat['wpil_keywords_priority_setting']: 0;

            $restrict_date = (isset($dat['wpil_keywords_restrict_date']) && !empty($dat['wpil_keywords_restrict_date'])) ? 1: 0;
            $date = null;
            if(isset($dat['wpil_keywords_restricted_date']) && !empty($dat['wpil_keywords_restricted_date'])){
                $date = preg_replace("([^0-9-])", "", $dat['wpil_keywords_restricted_date']);
                if($date !== $dat['wpil_keywords_restricted_date']){
                    $date = null;
                }
            }

            $restrict_cats = (isset($dat['wpil_keywords_restrict_to_cats']) && !empty($dat['wpil_keywords_restrict_to_cats'])) ? 1: 0;
            $term_ids = '';
            if(isset($dat['restricted_cats']) && !empty($dat['restricted_cats'])){
                $ids = array_map(function($num){ return (int)$num; }, $dat['restricted_cats']);
                $term_ids = implode(',', $ids);
            }

            $force_insert = (isset($dat['wpil_keywords_force_insert']) && !empty($dat['wpil_keywords_force_insert'])) ? 1: 0;

            $add_same_link = (isset($dat['wpil_keywords_add_same_link']) && !empty($dat['wpil_keywords_add_same_link'])) ? 1: 0;
            $link_once = (isset($dat['wpil_keywords_link_once']) && !empty($dat['wpil_keywords_link_once'])) ? 1: 0;
            $select_links = (isset($dat['wpil_keywords_select_links']) && !empty($dat['wpil_keywords_select_links'])) ? 1: 0;
            $set_priority = (isset($dat['wpil_keywords_set_priority']) && !empty($dat['wpil_keywords_set_priority'])) ? 1: 0;
            $case_sensitive = (isset($dat['wpil_keywords_case_sensitive']) && !empty($dat['wpil_keywords_case_sensitive'])) ? 1: 0;

            self::prepareTable();
            foreach($keyword_data as $keyword){
                $keyword = trim($keyword);
                if(empty($keyword)){
                    continue;
                }

                $wpdb->insert($wpdb->prefix . 'wpil_keywords', [
                    'keyword' => $keyword,
                    'link' => $link,
                    'add_same_link' => $add_same_link,
                    'link_once' => $link_once,
                    'select_links' => $select_links,
                    'set_priority' => $set_priority,
                    'priority_setting' => $priority,
                    'restrict_date' => $restrict_date,
                    'restricted_date' => $date,
                    'restrict_cats' => $restrict_cats,
                    'restricted_cats' => $term_ids,
                    'case_sensitive' => $case_sensitive,
                    'force_insert' => $force_insert
                ]);

                $insert_ids[] = $wpdb->insert_id;
            }
        }

        return $insert_ids;
    }

    /**
     * Directly save keyword to DB.
     * 
     * $keyword_data args are:
     * array(
            'keyword' => (string),
            'link' => (string),
            'add_same_link' => (bool int) (0|1),
            'link_once' => (bool int) (0|1),
            'select_links' => (bool int) (0|1),
            'set_priority' => (bool int) (0|1),
            'priority_setting' => (int),
            'restrict_date' => (bool int) (0|1),
            'restricted_date' => (null|timestring),
            'restrict_cats' => (bool int) (0|1),
            'restricted_cats' => (empty string | array of term ids), // it says 'restricted_cats', but it also works for tags
            'case_sensitive' => (bool int) (0|1),
            'force_insert' => (bool int) (0|1)
        )
     *
     * @param array $keyword_data
     * @return object|array
     */
    public static function directStore($keyword_data){
        global $wpdb;

        if( empty($keyword_data) ||
            !isset($keyword_data['keyword']) ||
            empty($keyword_data['keyword']) ||
            !isset($keyword_data['link']) ||
            empty($keyword_data['link']))
        {
            return array();
        }

        $defaults = array(
            'keyword' => '',
            'link' => '',
            'add_same_link' => get_option('wpil_keywords_add_same_link'),
            'link_once' => get_option('wpil_keywords_link_once'),
            'select_links' => get_option('wpil_keywords_select_links'),
            'set_priority' => get_option('wpil_keywords_set_priority'),
            'priority_setting' => 0,
            'restrict_date' => 0,
            'restricted_date' => null,
            'restrict_cats' => get_option('wpil_keywords_restrict_to_cats'),
            'restricted_cats' => '',
            'case_sensitive' => get_option('wpil_keywords_case_sensitive'),
            'force_insert' => 0
        );

        // merge the supplied data with the defaults
        $keyword_data = array_merge($defaults, $keyword_data);

        // get the keyword(s)
        $keyword_data['keyword'] = trim(sanitize_text_field($keyword_data['keyword']));
        if(0 === strpos($keyword_data['keyword'], '"') && strrpos($keyword_data['keyword'], '"') === (strlen($keyword_data['keyword']) - 1)){
            $keyword_data['keyword'] = array(trim(substr($keyword_data['keyword'], 1, strlen($keyword_data['keyword']) - 2)));
        }else{
            $keyword_data['keyword'] = explode(',', $keyword_data['keyword']);
        }

        // assemble the keyword data that we're going to save
        $data['keyword'] = $keyword_data['keyword'];
        $data['link'] = trim(esc_url_raw($keyword_data['link']));
        $data['add_same_link'] = (int) $keyword_data['add_same_link'];
        $data['link_once'] = (int) $keyword_data['link_once'];
        $data['select_links'] = (int) $keyword_data['select_links'];
        $data['set_priority'] = (int) $keyword_data['set_priority'];
        $data['priority_setting'] = (int) $keyword_data['priority_setting'];
        $data['restrict_date'] = (int) $keyword_data['restrict_date'];
        $data['restricted_date'] = null;
        $data['restrict_cats'] = (int) $keyword_data['restrict_cats'];
        $data['restricted_cats'] = '';
        $data['case_sensitive'] = (int) $keyword_data['case_sensitive'];
        $data['force_insert'] = (int) $keyword_data['force_insert'];

        // check if there's a date restriction active
        if(!empty($data['restrict_date']) && !empty($keyword_data['restricted_date'])){
            // if there is, process out the date setting
            $date = preg_replace("([^0-9-])", "", $keyword_data['restricted_date']);
            // if the supplied date is the same as the sanitized date
            if($keyword_data['restricted_date'] === $date){
                // set the date restriction to the sanitized date
                $data['restricted_date'] = $date;
            }
        }

        // check if the keyword is restricted to specific terms
        if(!empty($data['restrict_cats']) && !empty($keyword_data['restricted_cats'])){
            // if it is, get the sanitized, comma separated list of term ids
            $ids = array_map(function($num){ return (int)$num; }, $keyword_data['restricted_cats']);
            $data['restricted_cats'] = implode(',', $ids);
        }

        $insert_ids = array();
        foreach($data['keyword'] as $keyword){
            $keyword = trim($keyword);
            if(empty($keyword)){
                continue;
            }

            $wpdb->insert($wpdb->prefix . 'wpil_keywords', [
                'keyword' => $keyword,
                'link' => $data['link'],
                'add_same_link' => $data['add_same_link'],
                'link_once' => $data['link_once'],
                'select_links' => $data['select_links'],
                'set_priority' => $data['set_priority'],
                'priority_setting' => $data['priority_setting'],
                'restrict_date' => $data['restrict_date'],
                'restricted_date' => $data['restricted_date'],
                'restrict_cats' => $data['restrict_cats'],
                'restricted_cats' => $data['restricted_cats'],
                'case_sensitive' => $data['case_sensitive'],
                'force_insert' => $data['force_insert']
            ]);

            $insert_ids[] = $wpdb->insert_id;
        }

        return (count($insert_ids) === 1) ? self::getKeywordByID($wpdb->insert_id): $insert_ids;
    }

    /**
     * Create keywords DB table if not exists
     */
    public static function prepareTable()
    {
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_keywords'");
        if ($table != $wpdb->prefix . 'wpil_keywords') {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_keywords (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    keyword varchar(255) NOT NULL,
                                    link varchar(255) NOT NULL,
                                    add_same_link int(1) unsigned NOT NULL,
                                    link_once int(1) unsigned NOT NULL,
                                    select_links tinyint(1) DEFAULT 0,
                                    set_priority tinyint(1) DEFAULT 0,
                                    priority_setting int DEFAULT 0,
                                    restrict_date tinyint(1) DEFAULT 0,
                                    restricted_date DATETIME DEFAULT NULL,
                                    restrict_cats tinyint(1) DEFAULT 0,
                                    restricted_cats text,
                                    case_sensitive tinyint(1) DEFAULT 0,
                                    force_insert tinyint(1) DEFAULT 0,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_keyword_links'");
        if ($table != $wpdb->prefix . 'wpil_keyword_links') {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_keyword_links (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    keyword_id int(10) unsigned NOT NULL,
                                    post_id int(10) unsigned NOT NULL,
                                    post_type varchar(10) NOT NULL,
                                    anchor text,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }

        Wpil_Base::fixCollation($wpdb->prefix . 'wpil_keywords');
        Wpil_Base::fixCollation($wpdb->prefix . 'wpil_keyword_links');

        // set up the possible links table
        self::preparePossibleLinksTable();
    }

    /**
     * Creates the table for storing possible auto links so the user can select what links are to be inserted.
     **/
    public static function preparePossibleLinksTable(){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_keyword_select_links';
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$data_table}'");
        if ($table != $data_table) {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$data_table} (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    keyword_id int(10) unsigned NOT NULL,
                                    post_id int(10) unsigned NOT NULL,
                                    post_type varchar(10) NOT NULL,
                                    sentence_text text,
                                    case_keyword text,
                                    meta_data text,
                                    PRIMARY KEY  (id),
                                    INDEX (keyword_id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }
    }

    /**
     * Get data for keywords table
     *
     * @param $per_page
     * @param $page
     * @param $search
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public static function getData($per_page, $page, $search,  $orderby = '', $order = '')
    {
        self::prepareTable();
        global $wpdb;
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order && 'links' !== $orderby) {
            $sort = " ORDER BY $orderby $order ";
        }
        // todo! Actually make the inserted_keywords sort thingy work!
        $search = !empty($search) ? $wpdb->prepare(" AND (keyword LIKE %s OR link LIKE %s) ", Wpil_Toolbox::esc_like($search), Wpil_Toolbox::esc_like($search)) : '';
        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_keywords WHERE 1 {$search}");
        $keywords = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keywords WHERE 1 {$search} {$sort} {$limit}" );
        $keyword_ids = array();

        foreach($keywords as $kword){
            $keyword_ids[] = $kword->id;
        }

        $results = array();
        if(!empty($keyword_ids)){
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id IN (" . implode(', ', $keyword_ids) . ")");
            foreach($result as $r){
                $results[$r->keyword_id][] = $r;
            }
            $result = null;
        }

        //get posts with inserted links
        foreach ($keywords as $key => $keyword) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id = " . $keyword->id);
            $links = [];
            $link_count = 0;
            if(isset($results[$keyword->id])){
                foreach ($results[$keyword->id] as $r) {
                    $link_count++; // count the number of links this keyword has
                    $links[] = (object)[
                        'post' => new Wpil_Model_Post($r->post_id, $r->post_type),
                        'anchor' => $r->anchor,
                        'url' => $keyword->link,
                    ];
                }
            }
            $keywords[$key]->links = $links;
            $keywords[$key]->link_count = $link_count;
        }

        // if the user has opted to sort by link counts, sort the keywords
        if('links' === $orderby){
            if('asc' === $order){
                uasort($keywords, function($a, $b){
                    return $b->link_count - $a->link_count;
                });
            }else{
                uasort($keywords, function($a, $b){
                    return $a->link_count - $b->link_count;
                });
            }
        }

        return [
            'total' => $total,
            'keywords' => $keywords
        ];
    }

    /**
     * Removes the given autolinks from the given post and updates the post's content
     * @param $keyword The autolink that we're processing
     * @param $post The post that we're removing the link from
     **/
    public static function removeAndUpdate($keyword, $post){
        $content = $post->getCleanContent();
        $excerpt = $post->maybeGetExcerpt();

        self::removeAllLinks($keyword, $content);
        self::removeAllExcerptLinks($keyword, $post, $excerpt);
        self::removeAllMetaContentLinks($keyword, $post);
        self::updateContent($content, $keyword, $post, false, $excerpt);
    }

    /**
     * Delete keyword from DB
     */
    public static function delete()
    {
        if (!empty($_POST['id'])) {
            global $wpdb;
            $keyword = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpil_keywords WHERE id = " . $_POST['id']);

            foreach(self::getLinksByKeyword($keyword->id) as $link) {
                $keyword = self::getKeywordByID($keyword->id);
                $post = new Wpil_Model_Post($link->post_id, $link->post_type);

                self::removeAndUpdate($keyword, $post);
            }

            $wpdb->delete($wpdb->prefix . 'wpil_keywords', ['id' => $keyword->id]);
            $wpdb->delete($wpdb->prefix . 'wpil_keyword_links', ['keyword_id' => $keyword->id]);
            $wpdb->delete($wpdb->prefix . 'wpil_keyword_select_links', ['keyword_id' => $keyword->id]);
        }
    }

    /**
     * Deletes all stored possible links for the given keyword id
     **/
    public static function deletePossibleLinksForKeyword($keyword_id){
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wpil_keyword_select_links', ['keyword_id' => $keyword_id]);
    }

    /**
     * Deletes all stored possible links for the given post
     **/
    public static function deletePossibleLinksByPost($post){
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wpil_keyword_select_links', ['post_id' => $post->id, 'post_type' => $post->type]);
    }

    /**
     * Delete inserted link DB record
     *
     * @param $link_id
     */
    public static function deleteLink($link, $count = 999) {
        global $wpdb;
        $links = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wpil_keyword_links WHERE post_id = {$link->post_id} AND post_type = '{$link->post_type}' AND keyword_id = {$link->keyword_id}");

        foreach ($links as $key => $link) {
            if ($key >= $count) {
                $wpdb->delete($wpdb->prefix . 'wpil_keyword_links', ['id' => $link->id]);
            }
        }
    }

    /**
     * Get inserted links by keyword
     *
     * @param $keyword_id
     * @return array
     */
    public static function getLinksByKeyword($keyword_id)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id = " . $keyword_id);
    }

    /**
     * Get possible links by keyword id
     *
     * @param $keyword_id
     * @return array
     */
    public static function getPossibleLinksByKeyword($keyword_id)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keyword_select_links WHERE keyword_id = " . $keyword_id);
    }

    /**
     * Get inserted links by post
     *
     * @param $post
     * @return array
     */
    public static function getLinksByPost($post)
    {
        global $wpdb;
        return $wpdb->get_results("SELECT *, count(keyword_id) as `cnt` FROM {$wpdb->prefix}wpil_keyword_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' GROUP BY keyword_id");
    }

    /**
     * Create link from keyword in all posts and terms
     *
     * @param $keyword
     * @param bool $return Should this return or echo the results? Default is echo for built in ajax. Passing true will also allow the function to process until the PHP time limit is nearly up.
     */
    public static function checkPosts($keyword, $return = false)
    {
        global $wpdb;
        update_option('wpil_post_procession', time());
        Wpil_Base::update_option_cache('wpil_post_procession', time());
        $max_links_per_post = get_option('wpil_max_links_per_post', 0);

        $posts = get_transient('wpil_keyword_posts_' . $keyword->id);
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 0.1;
        if (empty($posts)) {
            $ignore_posts = Wpil_Settings::getIgnoreKeywordsPosts();
            $post_types = implode("','", Wpil_Settings::getPostTypes());
            //get matched posts and categories
            $link_post = Wpil_Post::getPostByLink($keyword->link);
            $where = " AND post_type IN ('{$post_types}')";
            if (!empty($link_post->type) && $link_post->type == 'post') {
                $where .= " AND ID != " . $link_post->id;
                $ignore_posts[] = $link_post->type . '_' . $link_post->id; // add the target post to the ignored post list so we can be more sure the target post's won't be linked
            }
            $when = '';
            if(!empty($keyword->restrict_date) && !empty($keyword->restricted_date)){
                $when = " AND `post_date_gmt` > '{$keyword->restricted_date}'";
            }

            $case_sensitive = (isset($keyword->case_sensitive) && !empty($keyword->case_sensitive)) ? "BINARY": "";

            $keyword_search = '';
            $encoded_keyword = json_encode($keyword->keyword);
            if(!empty($encoded_keyword) && trim($encoded_keyword, '"') !== $keyword->keyword){
                $encoded_keyword = trim($encoded_keyword, '"');
                $keyword_search = "(post_content LIKE {$case_sensitive} '%{$keyword->keyword}%' OR post_content LIKE {$case_sensitive} '%{$encoded_keyword}%')";
            }else{
                $keyword_search = "post_content LIKE {$case_sensitive} '%{$keyword->keyword}%'";
            }

            $posts = [];
            $statuses_query = Wpil_Query::postStatuses();
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE {$keyword_search} $statuses_query $where $when
                                                    UNION
                                                    SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN ('_themify_builder_settings_json', 'ct_builder_shortcodes', 'mfn-page-items-seo') AND m.meta_value LIKE {$case_sensitive} '%{$keyword->keyword}%' $statuses_query_p
                                                     $where");
            $results = self::getPostsFromAlternateLocations($results, $keyword);
            foreach ($results as $post) {
                $posts[] = new Wpil_Model_Post($post->ID);
            }

            if (!empty(Wpil_Settings::getTermTypes())) {
                $taxonomies = implode("','", Wpil_Settings::getTermTypes());
                $where = " AND taxonomy IN ('{$taxonomies}') ";
                if (!empty($link_post->type) && $link_post->type == 'term') {
                    $where .= " AND term_id != " . $link_post->id;
                }
                $results = $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} WHERE description LIKE {$case_sensitive} '%{$keyword->keyword}%' $where ");
                $results = self::getTermsFromAlternateLocations($results, $keyword);
                foreach ($results as $category) {
                    $posts[] = new Wpil_Model_Post($category->term_id, 'term');
                }
            }

            foreach ($posts as $key => $post) {
                if (in_array($post->type . '_' . $post->id, $ignore_posts)) {
                    unset($posts[$key]);
                }
            }

            $total = count($posts) + .1;
        }

        //proceed posts
        $memory_break_point = Wpil_Report::get_mem_break_point();
        foreach ($posts as $key => $post) {
            // skip to the next post if this one is at the limit
            if(!empty($max_links_per_post) && Wpil_link::at_max_outbound_links($post)){
                unset($posts[$key]);
                continue;
            }
            $phrases = Wpil_Suggestion::getPhrases($post->getContent(), $keyword->force_insert, array(), true, $keyword->keyword);
            self::makeLinks($phrases, $keyword, $post);
            unset($posts[$key]);

            if ( (Wpil_Base::overTimeLimit(10, 15) && empty($return)) || ($return && Wpil_Base::overTimeLimit(25)) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ) {
                set_transient('wpil_keyword_posts_' . $keyword->id, $posts, 60 * 5);
                break;
            }
        }

        if (empty($posts)) {
            delete_transient('wpil_keyword_posts_' . $keyword->id);
        }

        update_option('wpil_post_procession', 0);
        Wpil_Base::update_option_cache('wpil_post_procession', 0);

        $data = [
            'nonce' => isset($_POST['nonce']) ? $_POST['nonce']: false,
            'keyword_id' => $keyword->id,
            'progress' => 100 - floor((count($posts) / $total) * 100),
            'total' => $total,
            'finish' => empty($posts)
        ];

        if($return){
            return $data;
        }else{
            wp_send_json($data);
        }
    }

    /**
     * Check if keyword is part of word
     *
     * @param $sentence
     * @param $keyword
     * @param $pos
     * @return bool
     */
    public static function isPartOfWord($sentence, $keyword, $pos)
    {
        $endings = array_merge(Wpil_Word::$endings, ['', ' ', '>', '<', ' ', '-', urldecode('%C2%A0')]); // '%C2%A0' === nbsp
        if ($pos > 1) {
            $char_prev = Wpil_Word::onlyText(trim(mb_substr($sentence, $pos - 1, 1)));
        } else {
            $char_prev = '';
        }
        $char_next = Wpil_Word::onlyText(trim(mb_substr($sentence, $pos + mb_strlen($keyword), 1)));

        if (in_array($char_prev, $endings) && in_array($char_next, $endings) || 
            (WPIL_CURRENT_LANGUAGE === 'english' && self::isAsianText($char_prev, $char_next, $keyword))) 
        {
            return false;
        }

        return true;
    }

    /**
     * Checks to see if the current text is Asian language text.
     **/
    public static function isAsianText($char_prev, $char_next, $keyword){
        $string = $char_prev . $keyword . $char_next;

        // if it's Japanese
        if($count = preg_match_all('/[\x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}。、]/u', $string)){
            $char_count = mb_strlen($string);

            // if all the chars are Japanese
            if($count === $char_count){
                return true;
            }
        }

        return false;
    }

    /**
     * Check if keyword is inside link
     *
     * @param $sentence
     * @param $keyword
     * @return bool
     */
    public static function insideLink($sentence, $keyword)
    {
        preg_match_all('`<a[^>]+>.*?</a>`i', $sentence, $matches);
        if(!empty($matches[0])){
            foreach($matches[0] as $match){
                // if the keyword occurs in an existing link, return true
                if(false !== mb_stripos($match, $keyword)){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks to see if the sentence occurs inside a header tag
     **/
    public static function insideHeading($sentence, $keyword, $post){
        preg_match_all('`<h[1-6][^><]*?>(.*?)<\/h[1-6]>`i', $post->getContent(), $matches);

        if (!empty($matches)){
            foreach($matches[0] as $match){
                if(false !== strpos($match, $sentence)){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks to see if the sentence already has a link.
     * If it does, checks to see if the autolink is using the force insertion override
     * 
     * @return bool Returns true if the link can be inserted and false if it can't
     **/
    public static function forceLinkCheck($sentence, $keyword){
        $has_link = (false !== strpos($sentence, '<a')) ? true : false;
        if(!$has_link || $has_link && !empty($keyword->force_insert)){
            return true;
        }

        return false;
    }

    /**
     * Get all keywords
     *
     * @return array
     */
    public static function getKeywords()
    {
        global $wpdb;
        $keywords = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keywords ORDER BY id");

        $sorted = array();
        foreach($keywords as $keyword){
            $sorted[$keyword->priority_setting][] = $keyword;
        }

        $sorted2 = array();
        foreach($sorted as $key => $sort){
            shuffle($sort);
            $sorted2[$key] = $sort;
        }

        // sort the keyowrds by priority
        krsort($sorted2, SORT_NUMERIC);

        $results = array();
        foreach($sorted2 as $sort){
            foreach($sort as $kword){
                $results[] = $kword;
            }
        }

        return $results;
    }

    /**
     * Get keyword by ID
     *
     * @param $id
     * @return object|null
     */
    public static function getKeywordByID($id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpil_keywords WHERE id = " . $id);
    }

    /**
     * Get possible links by id.
     * Can accept a single id or array of ids
     *
     * @param int|array $id
     * @return object|null
     */
    public static function getPossibleLinksByID($id)
    {
        global $wpdb;

        if(is_array($id)){
            $id = implode(',', $id);
            return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_keyword_select_links WHERE `id` IN (" . $id . ")");
        }else{
            return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpil_keyword_select_links WHERE `id` = " . $id);
        }
    }

    /**
     * Deletes possible links by id.
     * Can accept a single id or array of ids
     *
     * @param int|array $id
     * @return null
     */
    public static function deletePossibleLinksById($id)
    {
        global $wpdb;

        if(is_array($id)){
            $id = implode(',', $id);
            return $wpdb->query("DELETE FROM {$wpdb->prefix}wpil_keyword_select_links WHERE id IN (" . $id . ")");
        }else{
            return $wpdb->query("DELETE FROM {$wpdb->prefix}wpil_keyword_select_links WHERE id = " . $id);
        }
    }

    /**
     * Make links from all keywords for certain post
     *
     * @param $post
     */
    public static function addKeywordsToPost($post)
    {
        if (!in_array($post->getRealType(), Wpil_Settings::getAllTypes()) || !$post->statusApproved()) {
            return;
        }

        if (in_array($post->type . '_' . $post->id, Wpil_Settings::getIgnoreKeywordsPosts())) {
            return;
        }

        // exit if we've just inserted selected links so we don't insert duplicates
        if(!empty($_POST) && isset($_POST['action']) && 'wpil_insert_selected_keyword_links' === $_POST['action']){
            return;
        }

        $max_links_per_post = get_option('wpil_max_links_per_post', 0);

        self::prepareTable();
        update_option('wpil_post_procession', time());
        Wpil_Base::update_option_cache('wpil_post_procession', time());
        $keywords = self::getKeywords();
        $url_index = array();
        foreach ($keywords as $key => $keyword) {
            $keyword->keyword = stripslashes($keyword->keyword);
            $link_post = Wpil_Post::getPostByLink($keyword->link);
            if (!empty($link_post->type) && $link_post->type == $post->type && $link_post->id == $post->id) {
                unset($keywords[$key]);
                continue;
            }
            if (stripos($post->getContent(), $keyword->keyword) === false) {
                unset($keywords[$key]);
                continue;
            }
            // if a link with the current link's url is slated to be installed and the current link doesn't have rules to insert more than once
            if(isset($url_index[$keyword->link]) && !empty($keyword->link_once) && empty($keyword->add_same_link)){
                // remove it from the list
                unset($keywords[$key]);
                continue;
            }
            $url_index[$keyword->link] = true;
        }

        // remove any existing possible links
        self::deletePossibleLinksByPost($post);

        if (!empty($keywords)) {
            // compile the keyword texts so we can ignore them when splitting the phrases
            $ignore_texts = array_map(function($keyword){ return $keyword->keyword; }, $keywords);
            // create a list for the insertable links
            $possible_links = array();

            $phrases = Wpil_Suggestion::getPhrases($post->getFreshContent(), true, array(), true, $ignore_texts);
            foreach ($keywords as $keyword) {
                // if there is a limit to the number of links and this isn't a manually selected autolink
                if(!empty($max_links_per_post) && Wpil_link::at_max_outbound_links($post)){
                    continue;
                }

                $possible_links = array_merge($possible_links, self::makeLinks($phrases, $keyword, $post, true));
            }

            // if we have links
            if(!empty($possible_links)){
                // insert them with the appropriate inserter
                if ($post->type == 'term') {
                    // add any existing links to insert to the metadata
                    $existing_links = get_term_meta($post->id, 'wpil_links', true);
                    $possible_links = (!empty($existing_links) && is_array($existing_links)) ? array_merge($possible_links, $existing_links): $possible_links;

                    update_term_meta($post->id, 'wpil_links', $possible_links);

                    Wpil_Term::addLinksToTerm($post->id);
                } else {
                    // add any existing links to insert to the metadata
                    $existing_links = get_post_meta($post->id, 'wpil_links', true);
                    $possible_links = (!empty($existing_links) && is_array($existing_links)) ? array_merge($possible_links, $existing_links): $possible_links;

                    update_post_meta($post->id, 'wpil_links', $possible_links);

                    // add the links to the content
                    Wpil_Post::addLinksToContent(null, ['ID' => $post->id], array());
                }
            }
        }

        self::deleteGhostLinks($post);
        update_option('wpil_post_procession', 0);
        Wpil_Base::update_option_cache('wpil_post_procession', 0);
    }

    /**
     * Replace keyword with link
     *
     * @param $phrases
     * @param $keyword
     * @param $post
     * @param $return_meta Should we return the links to insert or not? Default is No so the links are inserted directly
     */
    public static function makeLinks($phrases, $keyword, $post, $return_meta = false)
    {
        if (self::canAddLink($post, $keyword)) {
            $meta = [];
            $keyword->keyword = stripslashes($keyword->keyword);
            foreach ($phrases as $phrase) {
                $begin = 0;
                while (mb_stripos($phrase->text, $keyword->keyword, $begin) !== false) {
                    $begin = mb_stripos($phrase->text, $keyword->keyword, $begin);
                    if (!self::isPartOfWord($phrase->text, $keyword->keyword, $begin) && 
                        !self::insideLink($phrase->src, $keyword->keyword) && 
                        !self::insideHeading($phrase->src, $keyword->keyword, $post) && 
                        self::forceLinkCheck($phrase->src, $keyword)) 
                    {
                        // create the keyword search regex. By default, it's case-sensitive
                        $keyword_search = '/(?<![a-zA-Z])'.preg_quote($keyword->keyword, '/').'(?![a-zA-Z])/';
                        // if the keyword isn't explicitly case-sensitive
                        if(!isset($keyword->case_sensitive) || empty($keyword->case_sensitive)){
                            // make it insensitive
                            $keyword_search .= 'i';
                        }

                        preg_match($keyword_search, $phrase->src, $case_match);

                        if(empty($case_match[0])){
                            break;
                        }

                        $case_keyword = $case_match[0];
                        $custom_sentence = preg_replace('/(?<![a-zA-Z])'.preg_quote($case_keyword, '/').'(?![a-zA-Z])/', self::getFullLink($keyword, $case_keyword, $post), $phrase->src, 1);
                        if ($custom_sentence == $phrase->src) {
                            break;
                        }

                        // if the user wants to select links before inserting
                        if($keyword->select_links){
                            // save the link data to the possible links table
                            self::savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence);
                        }else{
                            $before_custom_sentence = mb_substr($phrase->sentence_src, 0, mb_strpos($phrase->sentence_src, $phrase->src));
                            $after_custom_sentence = mb_substr($phrase->sentence_src, mb_strpos($phrase->sentence_src, $phrase->src) + mb_strlen($phrase->src));
                        
                            $custom_sentence = $before_custom_sentence . $custom_sentence . $after_custom_sentence;

                            $meta[] = [
                                'id' => $post->id,
                                'type' => $post->type,
                                'sentence' => $phrase->sentence_src,
                                'sentence_with_anchor' => '',
                                'added_by_keyword' => 1,
                                'custom_sentence' => $custom_sentence,
                                'keyword_data' => $keyword
                            ];

                            self::saveLinkToDB($keyword, $post, $case_keyword);

                        }

                        //Break loop if post should contain only one link for this keyword
                        if (!empty($keyword->link_once)) {
                            break 2;
                        }
                    }

                    $begin++;
                }
            }

            // if we're supposed to return the links instead of inserting them now
            if($return_meta){
                // return them if we have them, and an empty array if there aren't any
                return (!empty($meta)) ? $meta: array();
            }

            //add links to all editors
            if (!empty($meta)) {
                if ($post->type == 'term') {
                    // add any existing links to insert to the metadata
                    $existing_links = get_term_meta($post->id, 'wpil_links', true);
                    $meta = (!empty($existing_links) && is_array($existing_links)) ? array_merge($meta, $existing_links): $meta;

                    update_term_meta($post->id, 'wpil_links', $meta);
                    Wpil_Term::addLinksToTerm($post->id);
                } else {
                    // add any existing links to insert to the metadata
                    $existing_links = get_post_meta($post->id, 'wpil_links', true);
                    $meta = (!empty($existing_links) && is_array($existing_links)) ? array_merge($meta, $existing_links): $meta;

                    update_post_meta($post->id, 'wpil_links', $meta);
                    Wpil_Post::addLinksToContent(null, ['ID' => $post->id], array());
                }
            }
        }

        // if we're down here and we're supposed to return links
        if($return_meta){
            // return an empty array since the autolink couldn't be inserted
            return array();
        }
    }

    /**
     * Get full link for replace
     *
     * @param $keyword
     * @param $link
     * @param object|bool $post A Wpil_Post_Model object for the current item getting an autolink
     * @return string
     */
    public static function getFullLink($keyword, $caseKeyword = '', $post = false)
    {

        $is_external = !Wpil_Link::isInternal($keyword->link);
        $open_new_tab = (int)get_option('wpil_2_links_open_new_tab', 0);
        $open_external_new_tab = false;
        if($is_external){
            $open_external_new_tab = get_option('wpil_external_links_open_new_tab', null);
        }

        //add target blank if needed
        $blank = '';
        $rel = '';
        if (($open_new_tab == 1 && empty($is_external)) || 
            ($is_external && $open_external_new_tab) ||
            ($open_new_tab == 1 && $open_external_new_tab === null)
        ) {
            $noreferrer = !empty(get_option('wpil_add_noreferrer', false)) ? ' noreferrer': '';
            $blank = 'target="_blank" ';
            $rel = 'rel="noopener' . $noreferrer;
        }

        // if the user has set external links to be nofollow, this is an external link, and this isn't an interlinked site
        if(
            !empty(get_option('wpil_add_nofollow', false)) && 
            $is_external && 
            !empty(wp_parse_url($keyword->link, PHP_URL_HOST)) &&
            !in_array(wp_parse_url($keyword->link, PHP_URL_HOST), Wpil_SiteConnector::get_linked_site_domains(), true))
        {
            if(empty($rel)){
                $rel = 'rel="nofollow';
            }else{
                $rel .= ' nofollow';
            }
        }

        // if the user has set some domains to be listed as sponsored
        if(
            $is_external && 
            !empty(wp_parse_url($keyword->link, PHP_URL_HOST)) &&
            Wpil_Link::checkIfSponsoredLink($keyword->link))
        {
            if(empty($rel)){
                $rel = 'rel="sponsored';
            }else{
                $rel .= ' sponsored';
            }
        }

        if(!empty($rel)){
            $rel .= '"';
        }

        /**
         * allow the users to add classes to the link
         * @param string The class list
         * @param bool $external Is the link going to an external site?
         * @param string The location of the filter
         **/
        $classes = apply_filters('wpil_link_classes', '', $is_external, 'keyword');

        // if the user returned an array, stringify it
        if(is_array($classes)){
            $classes = implode(' ', $classes);
        }

        $classes = (!empty($classes)) ? sanitize_text_field($classes): '';

        $wp_object = null;
        if($post->type === 'post'){
            $wp_object = get_post($post->id);
        }elseif($post->type === 'term'){
            $wp_object = get_term($post->id);
        }

        $title = esc_attr(apply_filters('wpil_filter_autolink_title', str_replace(array('[', ']'), array('&#91;', '&#93;'), $caseKeyword), $wp_object, $keyword));

        return '<a class="' . trim('wpil_keyword_link ' . $classes) . '" href="' . $keyword->link . '" ' . $blank . ' ' . $rel . ' title="' . $title . '" data-wpil-keyword-link="linked">' . $caseKeyword . '</a>';
    }

    /**
     * Check if link can be added to certain post
     *
     * @param $post
     * @param $keyword
     * @return bool
     */
    public static function canAddLink($post, $keyword)
    {
        global $wpdb;
        if (empty($keyword->add_same_link)) {
            $links = [];
            $outbound = Wpil_Report::getOutboundLinks($post);
            foreach (array_merge($outbound['internal'], $outbound['external']) as $l) {
                $links[] = Wpil_Link::normalize_url($l->url);
            }

            if (in_array(Wpil_Link::normalize_url($keyword->link), $links)) {
                return false;
            }
        }

        if (!empty($keyword->link_once)) {
            preg_match('|<a .*href=[\'"]' . $keyword->link . '[\'"].*?>.*?</a>|i', $post->getContent(), $matches);
            if (!empty($matches[0])) {
                return false;
            }
        }

        $link_post = Wpil_Post::getPostByLink($keyword->link);
        if (!empty($link_post->type) && $link_post->getType() == 'Category') {
            $category_post = $wpdb->get_var("SELECT count(*) FROM {$wpdb->postmeta} WHERE post_id = {$post->id} AND meta_key = '_elementor_conditions' AND meta_value LIKE '%include/archive/category/{$link_post->id}%'");

            if (!empty((int)$category_post)) {
                return false;
            }
        }

        if($post->type === 'post' && isset($keyword->restricted_cats) && !empty($keyword->restricted_cats)){
            $in_cats = $wpdb->get_col("SELECT `object_id` FROM {$wpdb->term_relationships} WHERE `object_id` = {$post->id} && `term_taxonomy_id` IN ({$keyword->restricted_cats})");

            if(empty($in_cats)){
                return false;
            }
        }

        // if we're preventing twoway linking
        if(!empty($link_post) && get_option('wpil_prevent_two_way_linking', false)){
            $links = Wpil_Post::getLinkedPostIDs($post, false);
            foreach($links as $link){
                // if the post has been linked to by the destination post
                if(!empty($link->post) && (int)$link->post->id === (int)$link_post->id && $link->post->type === $link_post->type){
                    // exit
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Save inserted link to the DB table
     *
     * @param $keyword
     * @param $post
     */
    public static function saveLinkToDB($keyword, $post, $anchor = '')
    {
        global $wpdb;

        if(empty($anchor)){
            $anchor = $keyword->keyword;
        }

        $wpdb->insert($wpdb->prefix . 'wpil_keyword_links', [
            'keyword_id' => $keyword->id,
            'post_id' => $post->id,
            'post_type' => $post->type,
            'anchor' => $anchor,
        ]);
    }

    /**
     * Save inserted link to the DB table
     *
     * @param object $post
     * @param object $phrase
     * @param object $keyword
     * @param string $case_keyword
     * @param string $custom_sentence
     */
    public static function savePossibleLinkToDB($post, $phrase, $keyword, $case_keyword, $custom_sentence)
    {
        global $wpdb;

        //replace changed phrase inside the sentence
        $custom_sentence = str_replace($phrase->src, $custom_sentence, $phrase->sentence_src);

        $meta_data = array(
            'id' => $post->id,
            'type' => $post->type,
            'sentence' => $phrase->sentence_src,
            'sentence_with_anchor' => '',
            'added_by_keyword' => 1,
            'custom_sentence' => $custom_sentence,
            'keyword_data' => $keyword
        );

        $wpdb->insert($wpdb->prefix . 'wpil_keyword_select_links', [
            'keyword_id' => $keyword->id,
            'post_id' => $post->id,
            'post_type' => $post->type,
            'sentence_text' => $phrase->sentence_src,
            'case_keyword' => $case_keyword,
            'meta_data' => serialize($meta_data)
        ]);
    }

    /**
     * Save keywords settings
     */
    public static function saveSettings()
    {
        update_option('wpil_keywords_add_same_link', (int)$_POST['wpil_keywords_add_same_link']);
        update_option('wpil_keywords_link_once', (int)$_POST['wpil_keywords_link_once']);
        update_option('wpil_keywords_select_links', (int) $_POST['wpil_keywords_select_links']);
        update_option('wpil_keywords_set_priority', (int) $_POST['wpil_keywords_set_priority']);
        update_option('wpil_keywords_restrict_to_cats', (int)$_POST['wpil_keywords_restrict_to_cats']);
        update_option('wpil_keywords_case_sensitive', (int) $_POST['wpil_keywords_case_sensitive']);
    }

    /**
     * Find deleted links in the post content and remove them from DB
     *
     * @param $post
     */
    public static function deleteGhostLinks($post)
    {
        foreach (self::getLinksByPost($post) as $link) {
            $keyword = self::getKeywordByID($link->keyword_id);
            if (!empty($keyword)) {
                $c = $post->getFreshContent();

                preg_match_all('`<a (?:[^><]*?(?:class=["\'][^"\']*?wpil_keyword_link[^"\']*?["\']|data-wpil-keyword-link="linked")[^><]*?href="' . preg_quote($keyword->link, '`') . '"|[^><]*?href="' . preg_quote($keyword->link, '`') . '".*?(?:data-wpil-keyword-link="linked"))[^><]*?>' . preg_quote($keyword->keyword, '`') . '</a>`i', $c, $matches);
                if (empty($matches[0]) || count($matches[0]) != (int)$link->cnt) {
                    self::deleteLink($link, count($matches[0]));
                }
            }
        }
    }

    /**
     * Update keyword settings
     */
    public static function updateKeywordSettings()
    {
        $keyword = self::getKeywordByID($_POST['keyword_id']);

        if (!empty($keyword)) {
            global $wpdb;

            $priority_setting = 0;
            if(isset($_POST['wpil_keywords_priority_setting'])){
                $priority_setting = (int)$_POST['wpil_keywords_priority_setting'];
            }

            $date = null;
            if(isset($_POST['wpil_keywords_restricted_date']) && !empty($_POST['wpil_keywords_restricted_date'])){
                $date = preg_replace("([^0-9-])", "", $_POST['wpil_keywords_restricted_date']);
                if($date !== $_POST['wpil_keywords_restricted_date']){
                    $date = null;
                }
            }

            $term_ids = '';
            if(isset($_POST['restricted_cats']) && !empty($_POST['restricted_cats'])){
                $ids = array_map(function($num){ return (int)$num; }, $_POST['restricted_cats']);
                $term_ids = implode(',', $ids);
            }

            $restrict_to_date = (int)$_POST['wpil_keywords_restrict_date'];
            $restrict_to_cats = (int)$_POST['wpil_keywords_restrict_to_cats'];

            $wpdb->update($wpdb->prefix . 'wpil_keywords', [
                'add_same_link' => (int)$_POST['wpil_keywords_add_same_link'],
                'link_once' => (int)$_POST['wpil_keywords_link_once'],
                'select_links' => (int)$_POST['wpil_keywords_select_links'],
                'set_priority' => (int)$_POST['wpil_keywords_set_priority'],
                'priority_setting' => $priority_setting,
                'restrict_date' => $restrict_to_date,
                'restricted_date' => $date,
                'restrict_cats' => $restrict_to_cats,
                'restricted_cats' => $term_ids,
                'case_sensitive' => (int)$_POST['wpil_keywords_case_sensitive'],
                'force_insert' => (int)$_POST['wpil_keywords_force_insert']
            ], ['id' => $keyword->id]);

            if ($keyword->link_once == 0 && $_POST['wpil_keywords_link_once'] == 1) {
                self::leftOneLink($keyword);
            }

            if ($keyword->add_same_link == 1 && $_POST['wpil_keywords_add_same_link'] == 0) {
                self::removeSameLink($keyword);
            }

            // if date restricting has been turned on and a date is given or the given date is older than the saved date
            if( ($keyword->restrict_date == 0 && $restrict_to_date == 1 &&
                !empty($date)) ||
                (!empty($restrict_to_date) && !empty($date) && strtotime($date) > strtotime($keyword->restricted_date)) || true
            ){
                // update the keyword with the date
                $keyword->restricted_date = $date;
                // remove any autolinks on posts older than the set time
                self::removeTooOldLinks($keyword->id);
            }

            if(!empty($term_ids)){
                $keyword->restricted_cats = $term_ids;
                self::removeCategoryRestrictedLinks($keyword);
            }

            if($keyword->force_insert == 1 && (int)$_POST['wpil_keywords_force_insert'] === 0){
                self::removeForceInsertedLinks($keyword);
            }

            // clear any stored selectable links since we'll be adding new ones after this
            self::deletePossibleLinksForKeyword($keyword->id);
        }
    }

    /**
     * Remove all keyword links except one
     *
     * @param $keyword
     */
    public static function leftOneLink($keyword)
    {
        global $wpdb;
        $links = $wpdb->get_results("SELECT *, count(keyword_id) as cnt FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id = {$keyword->id} GROUP BY post_id, post_type HAVING count(keyword_id) > 1");
        foreach ($links as $link) {
            $keyword = self::getKeywordByID($keyword->id);
            $post = new Wpil_Model_Post($link->post_id, $link->post_type);
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();
            self::removeNonFirstLinks($keyword, $content);

            // remove non-first links from the excerpts if present
            if(!empty($excerpt)){
                if(count(self::findKeywordLinks($keyword, $content)) > 0){
                    self::removeAllExcerptLinks($keyword, $post, $excerpt);
                }else{
                    self::removeNonFirstLinks($keyword, $excerpt);
                }
            }

            self::updateContent($content, $keyword, $post, true, $excerpt);
            self::deleteGhostLinks($post);
        }
    }

    /**
     * Remove keyword links if post already has this link
     *
     * @param $keyword
     */
    public static function removeSameLink($keyword)
    {
        global $wpdb;
        $links = $wpdb->get_results("SELECT post_id, post_type FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id = {$keyword->id} GROUP BY post_id, post_type");
        foreach ($links as $link) {
            $post = new Wpil_Model_Post($link->post_id, $link->post_type);
            $keyword = self::getKeywordByID($keyword->id);
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();

            $matches_keyword = self::findKeywordLinks($keyword, $content . "\n" . $excerpt);
            preg_match_all('|<a\s[^>]*href=["\']' . $keyword->link . '[\'"][^>]*>|', $content . "\n" . $excerpt, $matches_all);

            if (count($matches_all[0]) > count($matches_keyword[0])) {
                self::removeAllLinks($keyword, $content);
                self::removeAllExcerptLinks($keyword, $post, $excerpt);
                self::updateContent($content, $keyword, $post, false, $excerpt);
                self::deleteGhostLinks($post);
            }
        }
    }

    /**
     * Removes the keyword links from all posts that we're published before the link's time.
     * 
     * @param int $keyword_id
     **/
    public static function removeTooOldLinks($keyword_id){
        global $wpdb;

        if(empty($keyword_id)){
            return;
        }

        $keyword = self::getKeywordByID($keyword_id);

        // exit if there's no date
        if(empty($keyword->restricted_date)){
            return;
        }

        // get all the posts with the keywords
        $links = self::getLinksByKeyword($keyword->id);

        // exit if there's no links
        if(empty($links)){
            return;
        }

        // extract the post ids from the keywords
        $ids = array();
        foreach($links as $link){
            $ids[$link->post_id] = true;
        }

        $ids = implode(', ', array_keys($ids));

        // get all the posts that have been published before the given date
        $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE `ID` IN ({$ids}) AND `post_date_gmt` < '{$keyword->restricted_date}'");

        // exit if there's no posts published before the date
        if(empty($posts)){
            return;
        }

        // remove the links from the post contents
        foreach($posts as $post){
            $post = new Wpil_Model_Post($post->ID, 'post');
            self::removeAndUpdate($keyword, $post);
        }
    }

    /**
     * Remove all keyword links except one from curtain post
     *
     * @param $keyword
     * @param $content
     */
    public static function removeNonFirstLinks($keyword, &$content)
    {
        $links = self::findKeywordLinks($keyword, $content);

        if(is_array($links[0])){
            $links = $links[0];
        }

        if (count($links) > 1) {
            $begin = stripos($content, $links[0]) + strlen($links[0]);
            $first = substr($content, 0, $begin);
            $second = substr($content, $begin);
            self::removeAllLinks($keyword, $second);
            $content = $first . $second;
        }
    }

    /**
     * Remove all keyword links
     *
     * @param $keyword
     * @param $content
     */
    public static function removeAllLinks($keyword, &$content)
    {
        $links = self::findKeywordLinks($keyword, $content, true);
        if(!empty($links)){
            foreach($links as $link){
                foreach($links as $link){
                    $content = preg_replace('`' . preg_quote($link['link'], '`') . '`', $link['anchor'],  $content);
                }
            }
        }
    }

    /**
     * Removes the given autolink from the current post
     **/
    public static function removeAllMetaContentLinks($keyword, $post){
        $fields = Wpil_Post::getMetaContentFieldList($post->type);

        $acf_fields = ($post->type === 'post') ? Wpil_Post::getAdvancedCustomFieldsList($post->id): Wpil_Term::getAdvancedCustomFieldsList($post->id);

        if(!empty($acf_fields)){
            $fields = array_merge($fields, $acf_fields);
        }

        if (!empty($fields)) {
            foreach ($fields as $field) {
                $content = ($post->type === 'post') ? get_post_meta($post->id, $field, true): get_term_meta($post->id, $field, true);

                self::removeAllLinks($keyword, $content);

                if($post->type === 'post'){
                    update_post_meta($post->id, $field, $content);
                }else{
                    update_term_meta($post->id, $field, $content);
                }
            }
        }

        /**
         * Allows the user to remove the keywords from their own custom data fields
         **/
        do_action('wpil_meta_content_data_remove_autolinks', $post->id, $post->type, $keyword);
    }

    /**
     * Removes the given autolink from the current post's excerpt
     **/
    public static function removeAllExcerptLinks($keyword, $post, &$excerpt = ''){
        if($post->type === 'term' || empty($excerpt)){
            return;
        }

        self::removeAllLinks($keyword, $excerpt);
    }

    /**
     * Removes links from all items that aren't in the categories listed by the user.
     * 
     * @param $keyword
     **/
    public static function removeCategoryRestrictedLinks($keyword){
        global $wpdb;
        $links = self::getLinksByKeyword($keyword->id);

        if(empty($links) || !isset($keyword->restricted_cats) || empty($keyword->restricted_cats)){
            return false;
        }

        // get all of the linked post ids
        $ids = array();
        foreach($links as $link){
            // skip the current item if it's a term
            if('term' === $link->post_type){
                continue;
            }
            $ids[$link->post_id] = true;
        }

        $ids = array_keys($ids);
        $search_ids = implode(',', $ids);

        // get all the linked post ids that do have the desired terms
        $post_ids_with_terms = $wpdb->get_results("SELECT `object_id` FROM {$wpdb->term_relationships} WHERE `object_id` IN ({$search_ids}) && `term_taxonomy_id` IN ({$keyword->restricted_cats})");

        // process the results
        $found_ids = array();
        foreach($post_ids_with_terms as $object_id){
            $found_ids[$object_id->object_id] = true;
        }

        $found_ids = array_keys($found_ids);

        // diff the ids that have the terms against the autolinks on record to find the ones we need to clean
        $cleanup_ids = array_diff($ids, $found_ids);

        // remove the current keyword from the items
        foreach($cleanup_ids as $id){
            $post = new Wpil_Model_Post($id);
            self::removeAndUpdate($keyword, $post);
        }
    }

    /**
     * Removes force-inserted autolinks from sentences that already contain a link.
     * @param $keyword
     **/
    public static function removeForceInsertedLinks($keyword, $items = array()){
        global $wpdb;

        // get a list of all the posts that the keyword shows up in
        if(empty($items)){
            $items = $wpdb->get_results("SELECT post_id, post_type FROM {$wpdb->prefix}wpil_keyword_links WHERE keyword_id = {$keyword->id} GROUP BY post_id, post_type");
        }

        foreach ($items as $item) {
            $post = new Wpil_Model_Post($item->post_id, $item->post_type);
            $keyword = self::getKeywordByID($keyword->id);
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();

            self::removeAllLinks($keyword, $content);
            self::removeAllExcerptLinks($keyword, $post, $excerpt);
            self::updateContent($content, $keyword, $post, false, $excerpt);

            $phrases = Wpil_Suggestion::getPhrases($post->getFreshContent(), true, array(), true, array($keyword->keyword));

            // if there is a limit to the number of links and this isn't a manually selected autolink
            if(empty($max_links_per_post) || !Wpil_link::at_max_outbound_links($post)){
                self::makeLinks($phrases, $keyword, $post);
            }


            self::deleteGhostLinks($post);
        }

        update_option('wpil_post_procession', 0);
        Wpil_Base::update_option_cache('wpil_post_procession', 0);
    }

    /**
     * Find keyword links in the content
     *
     * @param $keyword
     * @param $content
     * @param bool $return_text Should the anchor texts be returned for case sensitive matching?
     * @return array
     */
    public static function findKeywordLinks($keyword, $content, $return_text = false)
    {
        preg_match_all('`(?:<a\s[^><]*?(?:class=["\'][^"\']*?wpil_keyword_link[^"\']*?["\']|data-wpil-keyword-link="linked")[^><]*?(href|url)=[\'\"]' . preg_quote($keyword->link, '`') . '*[\'\"][^><]*?>|<a\s[^><]*?(href|url)=[\'\"]' . preg_quote($keyword->link, '`') . '*[\'\"][^><]*?(?:class=["\'][^"\']*?wpil_keyword_link[^"\']*?["\']|data-wpil-keyword-link="linked")[^><]*?>)(?!<a)(' . preg_quote($keyword->keyword, '`') . ')<\/a>`i', $content, $matches);

        if($return_text){
            $return_matches = array();
            foreach($matches[0] as $key => $match){
                if(!$return_text){
                    $return_matches[] = $match;
                }else{
                    $return_matches[] = array('link' => $match, 'anchor' => $matches[3][$key]);
                }
            }

            return $return_matches;
        }else{
            return $matches;
        }
    }

    /**
     * Update post content in all editors
     */
    public static function updateContent($content, $keyword, $post, $left_one = false, $excerpt = '')
    {
        if ($post->type == 'post') {
            Wpil_Post::editors('removeKeywordLinks', [$keyword, $post->id, $left_one]);
            Wpil_Editor_Kadence::removeKeywordLinks($content, $keyword, $left_one);
            // todo, update this so it removes the autolinks and re-inserts only the ones that are supposed to be in the content. As it is now, all the links are removed from the editor content when someone changes the date range for autolinks and the categories that are allowed.
        }

        // update the meta field content


        $post->updateContent($content, $excerpt);

    }

    /**
     * Does a check to see if the user has set any autolinks for manual select
     **/
    public static function keywordLinkSelectActive(){
        global $wpdb;
        $keyword_table = $wpdb->prefix . 'wpil_keywords';

        $set = false;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_keywords'");
        if($table === $wpdb->prefix . 'wpil_keywords'){
            $set = $wpdb->get_results("SELECT `id` FROM {$keyword_table} WHERE `select_links` = 1 LIMIT 1");
        }

        return (!empty($set)) ? true: false;
    }

    public static function getLinkedPostsFromAlternateLocations($posts){
        global $wpdb;

        $found_posts = false;
        $active_post_types = Wpil_Settings::getPostTypes();

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', $active_post_types)){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' as type, 1 AS alt FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND (meta_value LIKE '%wpil_keyword_link%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' as type, 1 AS alt FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'gdlr-core-page-builder' {$post_types_p} {$statuses_query_p} AND (meta_value LIKE '%wpil_keyword_link%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Elementor is active
        if(defined('ELEMENTOR_VERSION')){ // todo think about moving to the editor file
            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' as type, 1 AS alt FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' {$post_types_p} {$statuses_query_p} AND (meta_value LIKE '%wpil_keyword_link%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if WooCommerce is active
        if(defined('WC_PLUGIN_FILE') && in_array('product', $active_post_types)){
            $results = $wpdb->get_results("SELECT DISTINCT ID AS id, 'post' as type, 1 AS alt FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND p.post_excerpt LIKE '%wpil_keyword_link%'");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        if($found_posts){
            // if there are posts found, remove any duplicate ids
            $post_ids = array();
            foreach($posts as $post){
                $post_ids[$post->id] = $post;
            }

            $posts = array_values($post_ids);
        }


        return $posts;
    }

    public static function getPostsFromAlternateLocations($posts, $keyword){
        global $wpdb;

        $case_sensitive = (isset($keyword->case_sensitive) && !empty($keyword->case_sensitive)) ? "BINARY": "";
        $found_posts = false;
        $fields = Wpil_Post::getMetaContentFieldList('post');
        $active_post_types = Wpil_Settings::getPostTypes();
        $link_post = Wpil_Post::getPostByLink($keyword->link);

        // if there are custom metafields to examine
        if(!empty($fields)){
            
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){
                $ignore_target = " AND m.post_id != " . $link_post->id;
            }

            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $locations = '\'' . implode('\', \'', $fields) . '\'';
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN ({$locations}) {$post_types_p} {$statuses_query_p} {$ignore_target} AND (meta_value LIKE {$case_sensitive} '%$keyword->keyword%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        if(class_exists('ACF') && !get_option('wpil_disable_acf', false)){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){
                $ignore_target = " AND m.post_id != " . $link_post->id;
            }

            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN (SELECT DISTINCT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->postmeta} WHERE meta_value LIKE 'field_%' AND SUBSTR(meta_key, 2) != '') {$ignore_target} {$post_types_p} {$statuses_query_p} AND meta_value LIKE {$case_sensitive} '%$keyword->keyword%'");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', $active_post_types)){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){ // We are searching for posts to link in, but the keyword's target might not be a post and we don't want to ignore a valid match
                $ignore_target = " AND m.post_id != " . $link_post->id;
            }

            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->postmeta} m WHERE `meta_key` = 'wprm_notes' {$ignore_target} AND (meta_value LIKE {$case_sensitive} '%$keyword->keyword%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){
                $ignore_target = " AND m.post_id != " . $link_post->id;
            }

            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'gdlr-core-page-builder' {$ignore_target} {$post_types_p} {$statuses_query_p} AND (meta_value LIKE {$case_sensitive} '%$keyword->keyword%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Elementor is active
        if(defined('ELEMENTOR_VERSION')){ // todo think about moving to the editor file
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){
                $ignore_target = " AND m.post_id != " . $link_post->id;
            }

            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' {$ignore_target} {$post_types_p} {$statuses_query_p} AND (meta_value LIKE {$case_sensitive} '%$keyword->keyword%')");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if WooCommerce is active
        if(defined('WC_PLUGIN_FILE') && in_array('product', $active_post_types)){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'post'){
                $ignore_target = " AND p.ID != " . $link_post->id;
            }

            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT ID FROM {$wpdb->posts} p WHERE p.post_type = 'product' {$ignore_target} {$statuses_query_p} AND p.post_excerpt LIKE {$case_sensitive} '%$keyword->keyword%'");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        if($found_posts){
            // if there are posts found, remove any duplicate ids
            $post_ids = array();
            foreach($posts as $post){
                $post_ids[$post->ID] = $post;
            }

            $posts = array_values($post_ids);
        }

        /**
         * Allows the user to filter the post ids that are examined for autolinking
         * @param array $posts The list of post ids that are going to be examind
         **/
        $posts = apply_filters('wpil_filter_autolink_ids_alternate_locations', $posts, 'post', $keyword);

        return $posts;
    }

    public static function getTermsFromAlternateLocations($posts, $keyword){
        global $wpdb;

        $found_terms = false;
        $fields = Wpil_Post::getMetaContentFieldList('term');
        $case_sensitive = (isset($keyword->case_sensitive) && !empty($keyword->case_sensitive)) ? "BINARY": "";
        $link_post = Wpil_Post::getPostByLink($keyword->link);

        // if there are metafields to examine
        if(!empty($fields)){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'term'){
                $ignore_target = " AND m.term_id != " . $link_post->id;
            }

            $locations = '\'' . implode('\', \'', $fields) . '\'';
            $results = $wpdb->get_results("SELECT DISTINCT m.term_id AS 'term_id' FROM {$wpdb->termmeta} m WHERE `meta_key` IN ({$locations}) {$ignore_target} AND (meta_value LIKE {$case_sensitive} '%$keyword->keyword%')");

            if(!empty($results)){
                $found_terms = true;
                $posts = array_merge($posts, $results);
            }
        }

        if(class_exists('ACF') && !get_option('wpil_disable_acf', false)){
            $ignore_target = "";
            if(!empty($link_post) && $link_post->type === 'term'){
                $ignore_target = " AND term_id != " . $link_post->id;
            }

            $results = $wpdb->get_results("SELECT `term_id` FROM {$wpdb->termmeta} WHERE meta_key IN (SELECT DISTINCT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->termmeta} WHERE meta_value LIKE 'field_%' AND SUBSTR(meta_key, 2) != '') {$ignore_target} AND meta_value LIKE {$case_sensitive} '%$keyword->keyword%'");
            if(!empty($results)){
                $found_terms = true;
                $posts = array_merge($posts, $results);
            }
        }

        if($found_terms){
            // if there are posts found, remove any duplicate ids
            $post_ids = array();
            foreach($posts as $post){
                $post_ids[$post->term_id] = $post;
            }

            $posts = array_values($post_ids);
        }

        /**
         * Allows the user to filter the term ids that are examined for autolinking
         * @param array $posts The list of term ids that are going to be examind
         **/
        $posts = apply_filters('wpil_filter_autolink_ids_alternate_locations', $posts, 'term', $keyword);

        return $posts;
    }

}
