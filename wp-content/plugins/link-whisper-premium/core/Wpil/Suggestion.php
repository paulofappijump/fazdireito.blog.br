<?php

/**
 * Work with suggestions
 */
class Wpil_Suggestion
{
    public static $undeletable = false;
    public static $max_anchor_length = 10;

    /**
     * Gets the suggestions for the current post/cat on ajax call.
     * Processes the suggested posts in batches to avoid timeouts on large sites.
     **/
    public static function ajax_get_post_suggestions(){

        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();

        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'wpil'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Wpil_Base::verify_nonce('wpil_suggestion_nonce');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
        }else{
            $post = new Wpil_Model_Post($post_id);
        }

        $count = null;
        if(isset($_POST['count'])){
            $count = intval($_POST['count']);
        }

        $batch_size = Wpil_Settings::getProcessingBatchSize();

        if(empty($count) && !empty(get_option('wpil_make_suggestion_filtering_persistent', false))){
            Wpil_Settings::update_suggestion_filters();
        }

        if(isset($_POST['type']) && 'outbound_suggestions' === $_POST['type']){
            // get the total number of posts that we'll be going through
            if(!isset($_POST['post_count']) || empty($_POST['post_count'])){
                $post_count = self::getPostProcessCount($post);
            }else{
                $post_count = intval($_POST['post_count']);
            }

            $phrase_array = array();
            while(!Wpil_Base::overTimeLimit(15, 45) && (($count - 1) * $batch_size) < $post_count){

                // get the phrases for this batch of posts
                $phrases = self::getPostSuggestions($post, null, false, null, $count, $key);

                if(!empty($phrases)){
                    $phrase_array[] = $phrases;
                }

                $count++;
            }

            $status = 'no_suggestions';
            if(!empty($phrase_array)){
                $stored_phrases = get_transient('wpil_post_suggestions_' . $key);
                if(empty($stored_phrases)){
                    $stored_phrases = $phrase_array;
                }else{
                    // decompress the suggestions so we can add more to the list
                    $stored_phrases = self::decompress($stored_phrases);

                    foreach($phrase_array as $phrases){
                        // add the suggestions
                        $stored_phrases[] = $phrases;
                    }
                }

                // compress the suggestions to save space
                $stored_phrases = self::compress($stored_phrases);

                // store the current suggestions in a transient
                set_transient('wpil_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);
                // send back our status
                $status = 'has_suggestions';
            }

            $num = ($batch_size * $count < $post_count) ? $batch_size * $count : $post_count;
            $message = sprintf(__('Processing Link Suggestions: %d of %d processed', 'wpil'), $num, $post_count);

            wp_send_json(array('status' => $status, 'post_count' => $post_count, 'batch_size' => $batch_size, 'count' => $count, 'message' => $message));

        }elseif(isset($_POST['type']) && 'inbound_suggestions' === $_POST['type']){

            $phrases = [];
            $memory_break_point = Wpil_Report::get_mem_break_point();
            $ignore_posts = Wpil_Settings::getIgnorePosts();
            $batch_size = $batch_size * 10;
            $max_links_per_post = get_option('wpil_max_links_per_post', 0);

            // if the keywords list only contains newline semicolons
            if(isset($_POST['keywords']) && empty(trim(str_replace(';', '', $_POST['keywords'])))){
                // remove the "keywords" index
                unset($_POST['keywords']);
                unset($_REQUEST['keywords']);
            }

            $completed_processing_count = (isset($_POST['completed_processing_count']) && !empty($_POST['completed_processing_count'])) ? (int) $_POST['completed_processing_count'] : 0;

            $keywords = self::getKeywords($post);
 
            $suggested_post_ids = get_transient('wpil_inbound_suggested_post_ids_' . $key);
            // get all the suggested posts for linking TO this post
            if(empty($suggested_post_ids)){
                $search_keywords = (is_array($keywords)) ? implode(' ', $keywords) : $keywords;
                $ignored_category_posts = Wpil_Settings::getIgnoreCategoriesPosts();
                $linked_post_ids = Wpil_Post::getLinkedPostIDs($post);
                $excluded_posts = array_merge($linked_post_ids, $ignored_category_posts);
                $suggested_posts = self::getInboundSuggestedPosts($search_keywords, $excluded_posts);
                $suggested_post_ids = array();
                foreach($suggested_posts as $suggested_post){
                    $suggested_post_ids[] = $suggested_post->ID;
                }
                set_transient('wpil_inbound_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }else{
                // if there are stored ids, re-save the transient to refresh the count down
                set_transient('wpil_inbound_suggested_post_ids_' . $key, $suggested_post_ids, MINUTE_IN_SECONDS * 10);
            }

            $last_post = (isset($_POST['last_post'])) ? (int) $_POST['last_post'] : 0;

            if(isset(array_flip($suggested_post_ids)[$last_post])){
                $post_ids_to_process = array_slice($suggested_post_ids, (array_search($last_post, $suggested_post_ids) + 1), $batch_size);
            }else{
                $post_ids_to_process = array_slice($suggested_post_ids, 0, $batch_size);
            }

            $process_count = 0;
            $current_post = $last_post;
            foreach($post_ids_to_process as $post_id) {
                $temp_phrases = [];
                foreach ($keywords as $ind => $keyword) {
                    if (Wpil_Base::overTimeLimit(15, 45) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ){
                        break 2;
                    }

                    $links_post = new Wpil_Model_Post($post_id);
                    $current_post = $post_id;

                    // if the post isn't being ignored
                    if(!in_array( ($links_post->type . '_' . $post_id), $ignore_posts)){
                        // if the user has set a max link count for posts and this is the first pass for the post
                        if(!empty($max_links_per_post) && $ind < 1 && Wpil_link::at_max_outbound_links($links_post)){
                            // skip any posts that are at the limit
                            break;
                        }

                        //get suggestions for post
                        if (!empty($_REQUEST['keywords'])) {
                            $suggestions = self::getPostSuggestions($links_post, $post, false, $keyword, null, $key);
                        } else {
                            $suggestions = self::getPostSuggestions($links_post, $post, false, null, null, $key);
                        }

                        //skip if no suggestions
                        if (!empty($suggestions)) {
                            $temp_phrases = array_merge($temp_phrases, $suggestions);
                        }
                    }
                }

                // increase the count of processed posts
                $process_count++;

                if (count($temp_phrases)) {
                    Wpil_Phrase::TitleKeywordsCheck($temp_phrases, $keyword);
                    $phrases = array_merge($phrases, $temp_phrases);
                }
            }

            // get the suggestions transient
            $stored_phrases = get_transient('wpil_post_suggestions_' . $key);

            // if there are suggestions stored
            if(!empty($stored_phrases)){
                // decompress the suggestions so we can add more to the list
                $stored_phrases = self::decompress($stored_phrases);
            }else{
                $stored_phrases = array();
            }

            // if there are phrases to save
            if($phrases){
                if(empty($stored_phrases)){
                    $stored_phrases = $phrases;
                }else{
                    // add the suggestions
                    $stored_phrases = array_merge($stored_phrases, $phrases);
                }
            }

            // compress the suggestions to save space
            $stored_phrases = self::compress($stored_phrases);

            // save the suggestion data
            set_transient('wpil_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);

            $processing_status = array(
                    'status' => 'no_suggestions',
                    'keywords' => $keywords,
                    'last_post' => $current_post,
                    'post_count' => count($suggested_post_ids),
                    'id_count_to_process' => count($post_ids_to_process),
                    'completed' => empty(count($post_ids_to_process)), // has the processing run completed? If it has, then there won't be any posts to process
                    'completed_processing_count' => ($completed_processing_count += $process_count),
                    'batch_size' => $batch_size,
                    'posts_processed' => $process_count,
            );

            if(!empty($phrases)){
                $processing_status['status'] = 'has_suggestions';
            }

            wp_send_json($processing_status);

        }else{
            wp_send_json(array(
                'error' => array(
                    'title' => __('Unknown Error', 'wpil'),
                    'text'  => __('The data is incomplete for processing the request, please reload the page and try again.', 'wpil'),
                )
            ));
        }
    }

    /**
     * Gets the suggestions for any external sites that the user has linked to this one.
     *
     **/
    public static function ajax_get_external_site_suggestions(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $key = intval($_POST['key']);
        $user = wp_get_current_user();

        // exit the processing if there's no external linking to do
        $linking_enabled = get_option('wpil_link_external_sites', false);
        if(empty($linking_enabled)){
            wp_send_json(array('status' => 'no_suggestions', 'post_count' => 0, 'batch_size' => 300, 'count' => 0, 'message' => __('Processing Complete', 'wpil')));
        }

        if((empty($post_id) && empty($term_id)) || empty($key) || 999999 > $key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'wpil'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Wpil_Base::verify_nonce('wpil_suggestion_nonce');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
        }else{
            $post = new Wpil_Model_Post($post_id);
        }

        $count = 0;
        if(isset($_POST['count'])){
            $count = intval($_POST['count']);
        }

        $batch_size = Wpil_Settings::getProcessingBatchSize();

        if(!isset($_POST['post_count']) || empty($_POST['post_count'])){
            // get the total number of posts that we'll be going through
            $post_count = Wpil_SiteConnector::count_data_items();
        }else{
            $post_count = (int) $_POST['post_count'];
        }

        // exit the processing if there's no external posts to link to
        if(empty($post_count)){
            wp_send_json(array('status' => 'no_suggestions', 'post_count' => 0, 'batch_size' => $batch_size, 'count' => $count, 'message' => __('Processing Complete', 'wpil')));
        }

        $phrase_array = array();
        while(!Wpil_Base::overTimeLimit(15, 30) && (($count - 1) * $batch_size) < $post_count){

            // get the phrases for this batch of posts
            $phrases = self::getExternalSiteSuggestions($post, false, null, $count, $key);

            if(!empty($phrases)){
                $phrase_array[] = $phrases;
            }

            $count++;
        }

        $status = 'no_suggestions';
        if(!empty($phrase_array)){
            $stored_phrases = get_transient('wpil_post_suggestions_' . $key);
            if(empty($stored_phrases)){
                $stored_phrases = $phrase_array;
            }else{
                // decompress the suggestions so we can add more to the list
                $stored_phrases = self::decompress($stored_phrases);

                foreach($phrase_array as $phrases){
                    // add the suggestions
                    $stored_phrases[] = $phrases;
                }
            }

            // compress the suggestions to save space
            $stored_phrases = self::compress($stored_phrases);

            // store the current suggestions in a transient
            set_transient('wpil_post_suggestions_' . $key, $stored_phrases, MINUTE_IN_SECONDS * 15);
            // send back our status
            $status = 'has_suggestions';
        }

        $num = ($batch_size * $count < $post_count) ? $batch_size * $count : $post_count;
        $message = sprintf(__('Processing External Site Link Suggestions: %d of %d processed', 'wpil'), $num, $post_count);

        wp_send_json(array('status' => $status, 'post_count' => $post_count, 'batch_size' => $batch_size, 'count' => $count, 'message' => $message));

    }

    /**
     * Updates the link report displays with the suggestion results from ajax_get_post_suggestions.
     **/
    public static function ajax_update_suggestion_display(){
        $post_id = intval($_POST['post_id']);
        $term_id = intval($_POST['term_id']);
        $process_key = intval($_POST['key']);
        $user = wp_get_current_user();

        // if the processing specifics are missing, exit
        if((empty($post_id) && empty($term_id)) || empty($process_key) || 999999 > $process_key || empty($user->ID)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing while processing the site content, please refresh the page and try again.', 'wpil'),
                )
            ));
        }

        // if the nonce doesn't check out, exit
        Wpil_Base::verify_nonce('wpil_suggestion_nonce');

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        if(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
        }else{
            $post = new Wpil_Model_Post($post_id);
        }

        $same_category = Wpil_Settings::get_suggestion_filter('same_category');
        $max_suggestions_displayed = Wpil_Settings::get_max_suggestion_count();

        if('outbound_suggestions' === $_POST['type']){
            // get the suggestions from the database
            $phrases = get_transient('wpil_post_suggestions_' . $process_key);

            // if there are suggestions
            if(!empty($phrases)){
                // decompress the suggestions
                $phrases = self::decompress($phrases);
            }

            // merge them all into a suitable array
            $phrase_groups = self::merge_phrase_suggestion_arrays($phrases);

            foreach($phrase_groups as $phrases){
                foreach($phrases as $phrase){
                    usort($phrase->suggestions, function ($a, $b) {
                        if ($a->total_score == $b->total_score) {
                            return 0;
                        }
                        return ($a->total_score > $b->total_score) ? -1 : 1;
                    });
                }
            }

            $used_posts = array($post_id . ($post->type == 'term' ? 'cat' : ''));

            //remove same suggestions on top level
            foreach($phrase_groups as $phrases){
                foreach ($phrases as $key => $phrase) {
                    if(is_a($phrase->suggestions[0]->post, 'Wpil_Model_ExternalPost')){

                        $post_key = ($phrase->suggestions[0]->post->type=='term'?'ext_cat':'ext_post') . $phrase->suggestions[0]->post->id;
                    }else{
                        $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
                    }

                    if (!empty($target) || !in_array($post_key, $used_posts)) {
                        $used_posts[] = $post_key;
                    } else {
                        if (!empty(self::$undeletable)) {
                            $phrase->suggestions[0]->opacity = .5;
                        } else {
                            unset($phrase->suggestions[0]);
                        }

                    }

                    if (!count($phrase->suggestions)) {
                        unset($phrases[$key]);
                    } else {
                        if (!empty(self::$undeletable)) {
                            $i = 1;
                            foreach ($phrase->suggestions as $suggestion) {
                                $i++;
                                if ($i > 10) {
                                    $suggestion->opacity = .5;
                                }
                            }
                        } else {
                            $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                        }
                    }
                }
            }

            foreach($phrase_groups as $type => $phrases){
                if (!empty($phrase_groups[$type])) {
                    $phrase_groups[$type] = self::deleteWeakPhrases(array_filter($phrase_groups[$type]));
                    $phrase_groups[$type] = self::addAnchors($phrase_groups[$type], true);

                    // if the user is limiting the number of suggestions to display
                    if(!empty($max_suggestions_displayed) && !empty($phrase_groups[$type])){
                        // trim back the number of suggestions to fit
                        $phrase_groups[$type] = array_slice($phrase_groups[$type], 0, $max_suggestions_displayed);
                    }
                }
            }

            $selected_categories = self::get_selected_categories();
            $taxes = get_object_taxonomies(get_post($post_id));
            $query_taxes = array();
            foreach($taxes as $tax){
                if(get_taxonomy($tax)->hierarchical){
                    $query_taxes[] = $tax;
                }
            }
            $categories = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
            if (empty($categories) || is_a($categories, 'WP_Error')) {
                $categories = [];
            }

            if(empty($selected_categories) && !empty($categories)){
                $selected_categories = array_map(function($cat){ return $cat->term_taxonomy_id; }, $categories);
            }

            $same_tag = !empty(Wpil_Settings::get_suggestion_filter('same_tag'));
            $selected_tags = self::get_selected_tags();
            $taxes = get_object_taxonomies(get_post($post_id));
            $query_taxes = array();
            foreach($taxes as $tax){
                if(empty(get_taxonomy($tax)->hierarchical)){
                    $query_taxes[] = $tax;
                }
            }
            $tags = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
            if (empty($tags) || is_a($tags, 'WP_Error')) {
                $tags = [];
            }

            if(empty($selected_tags) && !empty($tags)){
                $selected_tags = array_map(function($tag){ return $tag->term_taxonomy_id; }, $tags);
            }

            $select_post_types = Wpil_Settings::get_suggestion_filter('select_post_types') ? 1 : 0;
            $selected_post_types = self::getSuggestionPostTypes();
            $post_types = Wpil_Settings::getPostTypeLabels(Wpil_Settings::getPostTypes());
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/linking_data_list_v2.php';
            // clear the suggestion cache now that we're done with it
            self::clearSuggestionProcessingCache($process_key, $post->id);
        }elseif('inbound_suggestions' === $_POST['type']){
            $phrases = get_transient('wpil_post_suggestions_' . $process_key);
            // decompress the suggestions
            $phrases = self::decompress($phrases);
            //add links to phrases
            Wpil_Phrase::InboundSort($phrases);
            $phrases = self::addAnchors($phrases);
            $groups = self::getInboundGroups($phrases);

            // if the user is limiting the number of suggestions to display
            if(!empty($max_suggestions_displayed) && !empty($groups)){
                // trim back the number of suggestions to fit
                $groups = array_slice($groups, 0, $max_suggestions_displayed, true); // preserve the keys because the array is keyed to the post id
            }

            $selected_categories = self::get_selected_categories();
            $taxes = get_object_taxonomies(get_post($post_id));
            $query_taxes = array();
            foreach($taxes as $tax){
                if(get_taxonomy($tax)->hierarchical){
                    $query_taxes[] = $tax;
                }
            }
            $categories = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
            if (empty($categories) || is_a($categories, 'WP_Error')) {
                $categories = [];
            }

            if(empty($selected_categories) && !empty($categories)){
                $selected_categories = array_map(function($cat){ return $cat->term_taxonomy_id; }, $categories);
            }

            $same_tag = !empty(Wpil_Settings::get_suggestion_filter('same_tag'));
            $selected_tags = self::get_selected_tags();
            $taxes = get_object_taxonomies(get_post($post_id));
            $query_taxes = array();
            foreach($taxes as $tax){
                if(empty(get_taxonomy($tax)->hierarchical)){
                    $query_taxes[] = $tax;
                }
            }
            $tags = wp_get_object_terms($post_id, $query_taxes, ['fields' => 'all_with_object_id']);
            if (empty($tags) || is_a($tags, 'WP_Error')) {
                $tags = [];
            }

            if(empty($selected_tags) && !empty($tags)){
                $selected_tags = array_map(function($tag){ return $tag->term_taxonomy_id; }, $tags);
            }

            $select_post_types = Wpil_Settings::get_suggestion_filter('select_post_types') ? 1 : 0;
            $selected_post_types = self::getSuggestionPostTypes();
            $post_types = Wpil_Settings::getPostTypeLabels(Wpil_Settings::getPostTypes());
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/inbound_suggestions_page_container.php';
            self::clearSuggestionProcessingCache($process_key, $post->id);
        }

        exit;
    }

    /** 
     * Saves the user's "Load without animation" setting so it's persistent between loads
     **/
    public static function ajax_save_animation_load_status(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'wpil-load-with-animation-nonce') && (isset($_POST['status']) || array_key_exists('status', $_POST))){
            update_user_meta(get_current_user_id(), 'wpil_disable_load_with_animation', (int)$_POST['status']);
        }
    }

    /**
     * Merges multiple arrays of phrase data into a single array suitable for displaying.
     **/
    public static function merge_phrase_suggestion_arrays($phrase_array = array(), $inbound_suggestions = false){

        if(empty($phrase_array)){
            return array();
        }

        $merged_phrases = array('internal_site' => array(), 'external_site' => array());
        if(true === $inbound_suggestions){ // a simpler process is used for the inbound suggestions // Note: not currently used but might be used for inbound external matches
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(!empty($unserialized_batch)){
                    $merged_phrases = array_merge($merged_phrases, $unserialized_batch);
                }
            }
        }else{
            foreach($phrase_array as $phrase_batch){
                $unserialized_batch = maybe_unserialize($phrase_batch);
                if(is_array($unserialized_batch) && !empty($unserialized_batch)){
                    foreach($unserialized_batch as $phrase_key => $phrase_obj){
                        // go over each suggestion in the phrase obj
                        foreach($phrase_obj->suggestions as $post_id => $suggestion){
                            if(is_a($suggestion->post, 'Wpil_Model_ExternalPost')){
                                if(!isset($merged_phrases['external_site'][$phrase_key])){
                                    $base_phrase = $phrase_obj;
                                    unset($base_phrase->suggestions);
                                    $merged_phrases['external_site'][$phrase_key] = $base_phrase;
                                }
                                $merged_phrases['external_site'][$phrase_key]->suggestions[] = $suggestion;
                            }else{
                                if(!isset($merged_phrases['internal_site'][$phrase_key])){
                                    $base_phrase = $phrase_obj;
                                    unset($base_phrase->suggestions);
                                    $merged_phrases['internal_site'][$phrase_key] = $base_phrase;
                                }
                                $merged_phrases['internal_site'][$phrase_key]->suggestions[] = $suggestion;
                            }
                        }
                    }
                }
            }
        }

        return $merged_phrases;
    }

    public static function getPostProcessCount($post){
        global $wpdb;
        //add all posts to array
        $post_count = 0;
        $exclude = self::getTitleQueryExclude($post);
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $exclude_categories = Wpil_Settings::getIgnoreCategoriesPosts();
        if (!empty($exclude_categories)) {
            $exclude_categories = " AND ID NOT IN (" . implode(',', $exclude_categories) . ") ";
        } else {
            $exclude_categories = '';
        }

        // get the age query if the user is limiting the range for linking
        $age_string = Wpil_Query::getPostDateQueryLimit();

        $statuses_query = Wpil_Query::postStatuses();
        $results = $wpdb->get_results("SELECT COUNT('ID') AS `COUNT` FROM {$wpdb->prefix}posts WHERE 1=1 $exclude $exclude_categories AND post_type IN ('{$post_types}') $statuses_query {$age_string}");
        $post_count = $results[0]->COUNT;

        $taxonomies = Wpil_Settings::getTermTypes();
        if (!empty($taxonomies) && empty(self::get_selected_categories()) && empty(self::get_selected_tags())) {
            //add all categories to array
            $exclude = "";
            if ($post->type == 'term') {
                $exclude = " AND t.term_id != {$post->id} ";
            }

            $results = $wpdb->get_results("SELECT COUNT(t.term_id)  AS `COUNT` FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");
            $post_count += $results[0]->COUNT;
        }

        return $post_count;
    }

    public static function getExternalSitePostCount(){
        global $wpdb;
        $data_table = $wpdb->prefix . 'wpil_site_linking_data';
        $post_count = 0;


        $linked_sites = Wpil_SiteConnector::get_linked_sites();
        if(!empty($linked_sites)){
            $results = $wpdb->get_var("SELECT COUNT(item_id) FROM {$data_table}");
            $post_count += $results;
        }

        return $results;
    }

    /**
     * Get link suggestions for the post
     *
     * @param $post_id
     * @param $ui
     * @param null $target_post_id
     * @return array|mixed
     */
    public static function getPostSuggestions($post, $target = null, $all = false, $keyword = null, $count = null, $process_key = 0)
    {
        global $wpdb;
        $ignored_words = Wpil_Settings::getIgnoreWords();
        $stemmed_ignore_words = Wpil_Settings::getStemmedIgnoreWords();
        $is_outbound = (empty($target)) ? true: false;

        if ($target) {
            $internal_links = Wpil_Post::getLinkedPostIDs($target, false);
        }else{

            $internal_links = get_transient('wpil_outbound_post_links' . $process_key);
            if(empty($internal_links)){
                // if we're preventing twoway linking
                if(get_option('wpil_prevent_two_way_linking', false)){
                    // get the inbound && the outbound internal links
                    $internal_links = Wpil_Post::getLinkedPostIDs($post, false);
                }else{
                    $internal_links = Wpil_Report::getOutboundLinks($post);
                    $internal_links = $internal_links['internal'];
                }
                set_transient('wpil_outbound_post_links' . $process_key, self::compress($internal_links), MINUTE_IN_SECONDS * 15);

            }else{
                $internal_links = self::decompress($internal_links);
            }

        }

        $used_posts = [];
        foreach ($internal_links as $link) {
            if (!empty($link->post)) {
                $used_posts[] = ($link->post->type == 'term' ? 'cat' : '') . $link->post->id;
            }
        }

        //get all possible words from post titles
        $words_to_posts = self::getTitleWords($post, $target, $keyword, $count, $process_key);

        // if this is an inbound suggestion call
        if(!empty($target)){
            // get all selected target keywords
            $target_keywords = self::getPostKeywords($target, $process_key);
        }else{
            $post_keywords = self::getPostKeywords($post, $process_key);
            $target_keywords = self::getOutboundPostKeywords($words_to_posts, $post_keywords);

            // create a list of more specific keywords to use for overriding the outbound keyword matching limitations
            $more_specific_keywords = self::getMoreSpecificKeywords($target_keywords, $post_keywords);

//            $unique_keywords = self::getPostUniqueKeywords($target_keywords, $process_key);
        }

        //get all posts with same category
        $result = self::getSameCategories($post, $process_key, $is_outbound);
        $category_posts = [];
        foreach ($result as $cat) {
            $category_posts[] = $cat->object_id;
        }

        $word_segments = array();
        if(!empty($target) && method_exists('Wpil_Stemmer', 'get_segments') && empty($_REQUEST['keywords'])){
            // todo consider caching this too since it'll have to be called multiple times
            $word_segments = array();
            if(!empty($target_keywords)){
                foreach($target_keywords as $dat){
                    $dat_words = explode(' ', $dat->stemmed);
                    $word_segments = array_merge($word_segments, $dat_words);
                }
            }

            $word_segments = array_merge($word_segments, array_keys($words_to_posts));
            $word_segments = Wpil_Stemmer::get_segments($word_segments);
        }

        // if this is an inbound link scan
        if(!empty($target)){
            $phrases = self::getPhrases($post->getContent(), false, $word_segments);
        }else{
            // if this is an outbound link scan, get the phrases formatted for outbound use
            $phrases = self::getOutboundPhrases($post, $process_key);
        }

        // get if the user wants to only match on target keywords and isn't searching
        $only_match_target_keywords = (!empty(get_option('wpil_only_match_target_keywords', false)) && empty($_REQUEST['keywords']));

        //divide text to phrases
        foreach ($phrases as $key_phrase => $phrase) {
            if(empty($target)){
                // if this is an outbound link search, remove all phrases that contain the target keywords.
                $has_keyword = self::checkSentenceForKeywords($phrase->text, $post_keywords, array()/*, $unique_keywords*/, $more_specific_keywords);
                if($has_keyword){
                    unset($phrases[$key_phrase]);
                    continue;
                }
            }

            //get array of unique sentence words cleared from ignore phrases
            if (!empty($_REQUEST['keywords'])) {
                $sentence = trim(preg_replace('/\s+/', ' ', $phrase->text));
                $words_uniq = array_map(function($word){ return Wpil_Stemmer::Stem($word); }, array_unique(Wpil_Word::getWords($sentence)));
            } else {
                // if this is an inbound scan
                if(!empty($target)){
                    $text = Wpil_Word::strtolower(Wpil_Word::removeEndings($phrase->text, ['.','!','?','\'',':','"']));
                    $text = array_unique(Wpil_Word::cleanFromIgnorePhrases($text));
                    $words_uniq = array_map(function($word){ return Wpil_Stemmer::Stem($word); }, $text);
                }else{
                    // if this is an outbound scan
                    $words_uniq = $phrase->words_uniq;
                }
            }

            $suggestions = [];
            foreach ($words_uniq as $word) {
                // if we're only matching with target keywords, exit the loop
                if($only_match_target_keywords){
                    break;
                }

                if (empty($_REQUEST['keywords']) && in_array($word, $stemmed_ignore_words)) {
                    continue;
                }

                //skip word if no one post title has this word
                if (empty($words_to_posts[$word])) {
                    continue;
                }

                //create array with all possible posts for current word
                foreach ($words_to_posts[$word] as $p) {
                    if (is_null($target)) {
                        $key = $p->type == 'term' ? 'cat' . $p->id : $p->id;
                    } else {
                        $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                    }

                    if (in_array($key, $used_posts)) {
                        continue;
                    }

                    //create new suggestion
                    if (empty($suggestions[$key])) {
                        //check if post have same category with main post
                        $same_category = false;
                        if ($p->type == 'post' && in_array($p->id, $category_posts)) {
                            $same_category = true;
                        }

                        if (!is_null($target)) {
                            $suggestion_post = $post;
                        } else {
                            $suggestion_post = $p;
                        }

                        // unset the suggestions post content if it's set
                        if(isset($suggestion_post->content)){
                            $suggestion_post->content = null;
                        }

                        $suggestions[$key] = [
                            'post' => $suggestion_post,
                            'post_score' => $same_category ? .5 : 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 1;
                    }
                }
            }

            // if there are target keywords
            if(!empty($target_keywords)){

                $stemmed_phrase = Wpil_Word::getStemmedSentence($phrase->text);

                foreach($target_keywords as $target_keyword){
                    // skip the keyword if it's only 2 chars long
                    if(3 > strlen($target_keyword->keywords)){
                        continue;
                    }

                    // if the keyword is in the phrase
                    if( false !== strpos(Wpil_Word::strtolower($phrase->text), Wpil_Word::strtolower($target_keyword->keywords)) || // the keyword exists in an unstemmed version
                        false !== strpos($stemmed_phrase, $target_keyword->stemmed) && !in_array($target_keyword->stemmed, $ignored_words, true)) // if the keyword can be found after stemming it, and the stemmed version isn't an ignored word
                    {

                        // do an additional check to make sure the stemmed keyword isn't a partial match of a different word. //EX: TK of "shoe" would be found in "shoestring" and would trip the above check
                        $pos = mb_strpos($stemmed_phrase, $target_keyword->stemmed);
                        if(Wpil_Keyword::isPartOfWord($stemmed_phrase, $target_keyword->stemmed, $pos)){ // TODO: Create a general isPartOfWord function in a toolbox class so we don't have to go reaching across classes for methods
                            continue;
                        }

                        // if we're doing outbound suggestion matching
                        if(empty($target)){
                            $key = $target_keyword->post_type == 'term' ? 'cat' . $target_keyword->post_id : $target_keyword->post_id;
                            $link_post = new Wpil_Model_Post($target_keyword->post_id, $target_keyword->post_type);
                        }else{
                            $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                            $link_post = $post;
                        }
                        if (in_array($key, $used_posts)) {
                            break;
                        }

                        //create new suggestion
                        if (!isset($suggestions[$key])) {

                            //check if post have same category with main post
                            $same_category = false;
                            if ($link_post->type == 'post' && in_array($link_post->id, $category_posts)) {
                                $same_category = true;
                            }

                            // unset the suggestions post content if it's set
                            if(isset($link_post->content)){
                                $link_post->content = null;
                            }

                            $suggestions[$key] = [
                                'post' => $link_post,
                                'post_score' => $same_category ? .5 : 0,
                                'words' => [],
                                'matched_target_keywords' => array()
                            ];
                        }

                        // add the target keyword to the suggestion's data
                        $suggestions[$key]['matched_target_keywords'][] = $target_keyword;

                        foreach(explode(' ', $target_keyword->stemmed) as $word){
                            //add new word to suggestion if it hasn't already been listed and the user isn't searching for keywords
                            if (!in_array($word, $suggestions[$key]['words']) && empty($_REQUEST['keywords'])) {
                                if(!self::isAsianText()){
                                    $suggestions[$key]['words'][] = $word;
                                }else{
                                    $suggestions[$key]['words'][] = mb_str_split($word);
                                }

                                $suggestions[$key]['post_score'] += 30; // add more points since this is for a target keyword
                                $suggestions[$key]['passed_target_keywords'] = true;
                            }elseif(!isset($suggestions[$key]['passed_target_keywords'])){
                                $suggestions[$key]['post_score'] += 20; // add more points since this is for a target keyword
                                $suggestions[$key]['passed_target_keywords'] = true;
                            }
                        }
                    }
                }
            }

            /** Performs a word-by-word keyword match. So if a "Keyword" contains text like "best business site", it will check for matches to "best", "business", and "site". Rather than seeing if the text contains "best business site" specifically. **//*
            // create the target keyword suggestions
            foreach ($uniq_word_list as $word) {
                if(!isset($target_keywords[$word])){
                    continue;
                }

                foreach($target_keywords[$word] as $key_id => $kwrd){
                    $key = $post->type == 'term' ? 'cat' . $post->id : $post->id;
                    if (in_array($key, $used_posts)) {
                        continue;
                    }

                    //create new suggestion
                    if (!isset($suggestions[$key])) {

                        //check if post have same category with main post
                        $same_category = false;
                        if ($post->type == 'post' && in_array($post->id, $category_posts)) {
                            $same_category = true;
                        }

                        // unset the suggestions post content if it's set
                        if(isset($post->content)){
                            $post->content = null;
                        }

                        $suggestions[$key] = [
                            'post' => $post,
                            'post_score' => $same_category ? .5 : 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        $suggestions[$key]['words'][] = $word;
                        $suggestions[$key]['post_score'] += 3; // add more points since this is for a target keyword
                        $suggestions[$key]['passed_target_keywords'] = true;
                    }elseif(!isset($suggestions[$key]['passed_target_keywords'])){
                        $suggestions[$key]['post_score'] += 2; // add more points since this is for a target keyword
                        $suggestions[$key]['passed_target_keywords'] = true;
                    }

                    // award more points if the suggestion has an exact match with the keywords
                    if($kwrd->word_count > 1 && false !== strpos(Wpil_Word::getStemmedSentence($phrase->text), $kwrd->stemmed)){
                        $suggestions[$key]['post_score'] += 1000;
                    }
                }
            }*/

            //check if suggestion has at least 2 words & is less than 10 words long, and then calculate count of close words
            foreach ($suggestions as $key => $suggestion) {
                if ((!empty($_REQUEST['keywords']) && count($suggestion['words']) != count(array_unique(explode(' ', $keyword))))
                    || (empty($_REQUEST['keywords']) && count($suggestion['words']) < 2 && !isset($suggestion['passed_target_keywords']))
                ) {
                    unset ($suggestions[$key]);
                    continue;
                }

                // get the suggestion's current length
                $suggestion['length'] = self::getSuggestionAnchorLength($phrase, $suggestion['words']);

                // if the suggested anchor is longer than 10 words
                if(self::$max_anchor_length < $suggestion['length']){
                    // see if we can trim up the suggestion to get under the limit
                    $trimmed_suggestion = self::adjustTooLongSuggestion($phrase, $suggestion);
                    // if we can
                    if( self::$max_anchor_length <= $trimmed_suggestion['length'] && 
                        count($suggestion['words']) >= 2)
                    {
                        // update the suggestion
                        $suggestions[$key] = $trimmed_suggestion;
                    }else{
                        // if we can't, remove the suggestion
                        unset($suggestions[$key]);
                    }
                }

                sort($suggestion['words']);

                $close_words = self::getMaxCloseWords($suggestion['words'], $suggestion['post']->getTitle());

                if ($close_words > 1) {
                    $suggestion['post_score'] += $close_words;
                }

                //calculate anchor score
                $close_words = self::getMaxCloseWords($suggestion['words'], $phrase->text);
                $suggestion['anchor_score'] = count($suggestion['words']);
                if ($close_words > 1) {
                    $suggestion['anchor_score'] += $close_words * 2;
                }
                $suggestion['total_score'] = $suggestion['anchor_score'] + $suggestion['post_score'];

                $phrase->suggestions[$key] = new Wpil_Model_Suggestion($suggestion);
            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key_phrase]);
                continue;
            }

            usort($phrase->suggestions, function ($a, $b) {
                if ($a->total_score == $b->total_score) {
                    return 0;
                }
                return ($a->total_score > $b->total_score) ? -1 : 1;
            });
        }

        // if we're processing outbound suggestions
        if(empty($target)){
            // remove all top-level suggestion post duplicates and leave the best suggestion as top
            $phrases = self::remove_top_level_suggested_post_repeats($phrases);
        }

        //remove same suggestions on top level
        foreach ($phrases as $key => $phrase) {
            if(empty($phrase->suggestions)){
                unset($phrases[$key]);
                continue;
            }
            $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
            if (!empty($target) || !in_array($post_key, $used_posts)) {
                $used_posts[] = $post_key;
            } else {
                if (!empty(self::$undeletable)) {
                    $phrase->suggestions[0]->opacity = .5;
                } else {
                    unset($phrase->suggestions[0]);
                }

            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key]);
            } else {
                if (!empty(self::$undeletable)) {
                    $i = 1;
                    foreach ($phrase->suggestions as $suggestion) {
                        $i++;
                        if ($i > 10) {
                            $suggestion->opacity = .5;
                        }
                    }
                } else {
                    if (!$all) {
                        $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                    }else{
                        $phrase->suggestions = array_values($phrase->suggestions);
                    }
                }
            }
        }

        $phrases = self::deleteWeakPhrases($phrases);

        return $phrases;
    }

    public static function getExternalSiteSuggestions($post, $all = false, $keyword = null, $count = null, $process_key = 0){
        $ignored_words = Wpil_Settings::getIgnoreWords();

        $link_index = get_transient('wpil_external_post_link_index_' . $process_key);

        if(empty($link_index)){
            $external_links = Wpil_Report::getOutboundLinks($post);
            $link_index = array();
            if(isset($external_links['external'])){
                foreach($external_links['external'] as $link){
                    $link_index[$link->url] = true;
                }
            }
            unset($external_links);
            set_transient('wpil_external_post_link_index_' . $process_key, self::compress($link_index), MINUTE_IN_SECONDS * 15);
        }else{
            $link_index = self::decompress($link_index);
        }

        //get all possible words from external post titles
        $words_to_posts = self::getExternalTitleWords(false, false, $count, $link_index);

        $used_posts = array();

        $phrases = self::getOutboundPhrases($post, $process_key);

        //divide text to phrases
        foreach ($phrases as $key_phrase => $phrase) {

            $suggestions = [];
            foreach ($phrase->words_uniq as $word) {
                if (empty($_REQUEST['keywords']) && in_array($word, $ignored_words)) {
                    continue;
                }

                //skip word if no one post title has this word
                if (empty($words_to_posts[$word])) {
                    continue;
                }

                //create array with all possible posts for current word
                foreach ($words_to_posts[$word] as $p) {
                    $key = $p->type == 'term' ? 'ext_cat' . $p->id : 'ext_post' . $p->id;

                    //create new suggestion
                    if (empty($suggestions[$key])) {
                        $suggestion_post = $p;
                
                        $suggestions[$key] = [
                            'post' => $suggestion_post,
                            'post_score' => 0,
                            'words' => []
                        ];
                    }

                    //add new word to suggestion
                    if (!in_array($word, $suggestions[$key]['words'])) {
                        if(!self::isAsianText()){
                            $suggestions[$key]['words'][] = $word;
                        }else{
                            $suggestions[$key]['words'] = mb_str_split($word);
                        }

                        $suggestions[$key]['post_score'] += 1;
                    }
                }
            }

            //check if suggestion has at least 2 words & is less than 10 words long, and then calculate count of close words
            foreach ($suggestions as $key => $suggestion) {
                if ((!empty($_REQUEST['keywords']) && count($suggestion['words']) != count(array_unique(explode(' ', $keyword))))
                    || (empty($_REQUEST['keywords']) && count($suggestion['words']) < 2)
                ) {
                    unset ($suggestions[$key]);
                    continue;
                }

                // get the suggestion's current length
                $suggestion['length'] = self::getSuggestionAnchorLength($phrase, $suggestion['words']);

                // if the suggested anchor is longer than 10 words
                if(self::$max_anchor_length < $suggestion['length']){
                    // see if we can trim up the suggestion to get under the limit
                    $trimmed_suggestion = self::adjustTooLongSuggestion($phrase, $suggestion);
                    // if we can
                    if( self::$max_anchor_length <= $trimmed_suggestion['length'] && 
                        count($suggestion['words']) >= 2)
                    {
                        // update the suggestion
                        $suggestions[$key] = $trimmed_suggestion;
                    }else{
                        // if we can't, remove the suggestion
                        unset($suggestions[$key]);
                    }
                }

                sort($suggestion['words']);

                $close_words = self::getMaxCloseWords($suggestion['words'], $suggestion['post']->getTitle());

                if ($close_words > 1) {
                    $suggestion['post_score'] += $close_words;
                }

                //calculate anchor score
                $close_words = self::getMaxCloseWords($suggestion['words'], $phrase->text);
                $suggestion['anchor_score'] = count($suggestion['words']);
                if ($close_words > 1) {
                    $suggestion['anchor_score'] += $close_words * 2;
                }
                $suggestion['total_score'] = $suggestion['anchor_score'] + $suggestion['post_score'];

                $phrase->suggestions[$key] = new Wpil_Model_Suggestion($suggestion);
            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key_phrase]);
                continue;
            }

            usort($phrase->suggestions, function ($a, $b) {
                if ($a->total_score == $b->total_score) {
                    return 0;
                }
                return ($a->total_score > $b->total_score) ? -1 : 1;
            });
        }

        //remove same suggestions on top level
        foreach ($phrases as $key => $phrase) {
            $post_key = ($phrase->suggestions[0]->post->type=='term'?'ext_cat':'ext_post') . $phrase->suggestions[0]->post->id;
            if (!empty($target) || !in_array($post_key, $used_posts)) {
                $used_posts[] = $post_key;
            } else {
                if (!empty(self::$undeletable)) {
                    $phrase->suggestions[0]->opacity = .5;
                } else {
                    unset($phrase->suggestions[0]);
                }

            }

            if (!count($phrase->suggestions)) {
                unset($phrases[$key]);
            } else {
                if (!empty(self::$undeletable)) {
                    $i = 1;
                    foreach ($phrase->suggestions as $suggestion) {
                        $i++;
                        if ($i > 10) {
                            $suggestion->opacity = .5;
                        }
                    }
                } else {
                    if (!$all) {
                        $phrase->suggestions = array_slice($phrase->suggestions, 0, 10);
                    }else{
                        $phrase->suggestions = array_values($phrase->suggestions);
                    }
                }
            }
        }

        $phrases = self::deleteWeakPhrases($phrases);

        return $phrases;
    }

    /**
     * Divide text to sentences
     *
     * @param $content
     * @return array
     */
    public static function getPhrases($content, $with_links = false, $word_segments = array(), $single_words = false, $ignore_text = array())
    {
        // get the section skip type and counts
        $section_skip_type = Wpil_Settings::getSkipSectionType();

        // replace unicode chars with their decoded forms
        $replace_unicode = array('\u003c', '\u003', '\u0022');
        $replacements = array('<', '>', '"');

        $content = str_ireplace($replace_unicode, $replacements, $content);

        // remove the heading tags from the text
        $content = mb_ereg_replace('<h1(?:[^>]*)>(.*?)<\/h1>|<h2(?:[^>]*)>(.*?)<\/h2>|<h3(?:[^>]*)>(.*?)<\/h3>|<h4(?:[^>]*)>(.*?)<\/h4>|<h5(?:[^>]*)>(.*?)<\/h5>|<h6(?:[^>]*)>(.*?)<\/h6>', '', $content);

        // remove the head tag if it's present. It should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<head')){
            $content = mb_ereg_replace('<head(?:[^>]*)>(.*?)<\/head>', '', $content);
        }

        // remove any title tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<title')){
            $content = mb_ereg_replace('<title(?:[^>]*)>(.*?)<\/title>', '', $content);
        }

        // remove any meta tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<meta')){
            $content = mb_ereg_replace('<meta(?:[^>]*)>(.*?)<\/meta>', '', $content);
        }

        // remove any link tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<link')){
            $content = mb_ereg_replace('<link(?:[^>]*)>(.*?)<\/link>', '', $content);
        }

        // remove any script tags that might be present. We really don't want to suggest links for schema sections
        if(false !== strpos($content, '<script')){
            $content = mb_ereg_replace('<script(?:[^>]*)>(.*?)<\/script>', '', $content);
        }

        // if there happen to be any css tags, remove them too
        if(false !== strpos($content, '<style')){
            $content = mb_ereg_replace('<style(?:[^>]*)>(.*?)<\/style>', '', $content);
        }

        // remove any shortcodes that the user has defined
        $content = self::removeShortcodes($content);

        // remove page builder modules that will be turned into things like headings, buttons, and links
        $content = self::removePageBuilderModules($content);

        // remove elements that have certain classes
        $content = self::removeClassedElements($content);

        // remove any tags that the user doesn't want to create links in
        $content = self::removeIgnoredContentTags($content);

        // encode the contents of attributes so we don't have mistakes when breaking the content into sentences
        $content = preg_replace_callback('|(?:[a-zA-Z-]*="([^"]*?)")[^<>]*?|i', function($i){ return str_replace($i[1], 'wpil-attr-replace_' . base64_encode($i[1]), $i[0]); }, $content);

        // encode any supplied ignore text so we don't split sentences that are supposed to contain punctuation. (EX: Autolink keywords that contain Dr. or Mr.)
        if(!empty($ignore_text)){
            $ignore_text = (is_string($ignore_text)) ? array($ignore_text): $ignore_text;
            $ignore_text = implode("(?![[:alpha:]<>-_1-9])|(?<![[:alpha:]<>-_1-9])", array_map(function($text){ return preg_quote($text, '/');}, $ignore_text));
            $ignore_text = "(?<![[:alpha:]<>-_1-9])" . $ignore_text . "(?![[:alpha:]<>-_1-9])";
            $content = preg_replace_callback('/' . $ignore_text . '/i' , function($i){ return str_replace($i[0], 'wpil-ignore-replace_' . base64_encode($i[0]), $i[0]); }, $content);
        }

        // if the user want's to skip paragraphs
        if('paragraphs' === $section_skip_type){
            // remove the number he's selected
            $content = self::removeParagraphs($content);
        }

        //divide text to sentences
        $replace = [
            ['.<', '. ', '. ', '.&nbsp;', '.\\', '!<', '! ', '! ', '!\\', '?<', '? ', '? ', '?\\', '<div', '<br', '<li', '<p', '<h1', '<h2', '<h3', '<h4', '<h5', '<h6', '。'],
            [".\n<", ". \n", ".\n", ".\n&nbsp;", ".\n\\", "!\n<", "! \n", "!\n", "!\n\\", "?\n<", "? \n", "?\n", "?\n\\", "\n<div", "\n<br", "\n<li", "\n<p", "\n<h1", "\n<h2", "\n<h3", "\n<h4", "\n<h5", "\n<h6", "\n。"]
        ];
        $content = str_ireplace($replace[0], $replace[1], $content);
        $content = preg_replace('|\.([A-Z]{1})|', ".\n$1", $content);
        $content = preg_replace('|\[[^\]]+\]|i', "\n", $content);

        $list = explode("\n", $content);


        foreach($list as $key => $item){
            // decode all the attributes now that the content has been broken into sentences
            if(false !== strpos($item, 'wpil-attr-replace_')){
                $list[$key] = preg_replace_callback('|(?:[a-zA-Z-]*="(wpil-attr-replace_([^"]*?))")[^<>]*?|i', function($i){
                    return str_replace($i[1], base64_decode($i[2]), $i[0]);
                }, $item);
            }
        }

        $list = self::mergeSplitSentenceTags($list);
        self::removeEmptySentences($list, $with_links);
        self::trimTags($list, $with_links);

        // if the user want's to skip sentences
        if('sentences' === $section_skip_type){
            // remove the number he's selected
            $list = array_slice($list, Wpil_Settings::getSkipSentences());
        }

        $phrases = [];
        foreach ($list as $item) {
            $item = trim($item);

            if(!empty($word_segments)){
                // check if the phrase contains 2 title words
                $wcount = 0;
                foreach($word_segments as $seg){
                    if(false !== stripos($item, $seg)){
                        $wcount++;
                        if($wcount > 1){
                            break;
                        }
                    }
                }
                if($wcount < 2){
                    continue;
                }
            }

            if (in_array(substr($item, -1), ['.', ',', '!', '?', '。'])) {
                $item = substr($item, 0, -1);
            }

            // save the src before we decode the ignored txt
            $src_raw = $item;
            // decode the ignored txt
            $item = self::decodeIgnoredText($item);

            $sentence = [
                'src_raw' => $src_raw,
                'src' => $item,
                'text' => strip_tags(htmlspecialchars_decode($item))
            ];

            $sentence['text'] = trim($sentence['text']);

            //add sentence to array if it has at least 2 words
            if (!empty($sentence['text']) && ($single_words || count(explode(' ', $sentence['text'])) > 1)) {
                $phrases = array_merge($phrases, self::getPhrasesFromSentence($sentence, true));
            }
        }


        return $phrases;
    }

    /**
     * Removes page builder created modules from the text content.
     * Since these heading elements are rendered by the builder, the normal HTML heading/link remover doesn't catch these.
     * Checks the text for the presence of the modules so we're not regexing the text unnecessarily
     * 
     * @param string $content The post content.
     * @return string $content The processed post content
     **/
    public static function removePageBuilderModules($content = ''){


        $fusion_regex = '';
        // remove fusion builder (Avada) titles if present
        if(false !== strpos($content, 'fusion_title')){
            $fusion_regex .= '\[fusion_title(?:[^\]]*)\](.*?)\[\/fusion_title\]';
        }
        if(false !== strpos($content, 'fusion_imageframe')){
            $fusion_regex .= '|\[fusion_imageframe(?:[^\]]*)\](.*?)\[\/fusion_imageframe\]';
        }
        if(false !== strpos($content, 'fusion_button')){
            $fusion_regex .= '|\[fusion_button(?:[^\]]*)\](.*?)\[\/fusion_button\]';
        }
        if(false !== strpos($content, 'fusion_gallery')){
            $fusion_regex .= '|\[fusion_gallery(?:[^\]]*)\](.*?)\[\/fusion_gallery\]';
        }
        if(false !== strpos($content, 'fusion_code')){
            $fusion_regex .= '|\[fusion_code(?:[^\]]*)\](.*?)\[\/fusion_code\]';
        }
        if(false !== strpos($content, 'fusion_modal')){
            $fusion_regex .= '|\[fusion_modal(?:[^\]]*)\](.*?)\[\/fusion_modal\]';
        }
        if(false !== strpos($content, 'fusion_menu')){
            $fusion_regex .= '|\[fusion_menu(?:[^\]]*)\](.*?)\[\/fusion_menu\]';
        }
        if(false !== strpos($content, 'fusion_modal_text_link')){
            $fusion_regex .= '|\[fusion_modal_text_link(?:[^\]]*)\](.*?)\[\/fusion_modal_text_link\]';
        }
        if(false !== strpos($content, 'fusion_vimeo')){
            $fusion_regex .= '|\[fusion_vimeo(?:[^\]]*)\](.*?)\[\/fusion_vimeo\]';
        }
        // if there is Fusion (Avada) content
        if(!empty($fusion_regex)){
            // remove any leading "|" since it would be bad to have it...
            $fusion_regex = ltrim($fusion_regex, '|');
            // remove the items we don't want to add links to
            $content = mb_ereg_replace($fusion_regex, '', $content);
        }

        $cornerstone_regex = '';
        // if a Cornerstone heading is present in the text
        if(false !== strpos($content, 'cs_element_headline')){
            $cornerstone_regex .= '\[cs_element_headline(?:[^\]]*)\]\[cs_content_seo\](.*?)\[\/cs_content_seo\]';
        }
        if(false !== strpos($content, 'x_custom_headline')){
            $cornerstone_regex .= '|\[x_custom_headline(?:[^\]]*)\](.*?)\[\/x_custom_headline\]';
        }
        if(false !== strpos($content, 'x_image')){
            $cornerstone_regex .= '|\[x_image(?:[^\]]*)\](.*?)\[\/x_image\]';
        }
        if(false !== strpos($content, 'x_button')){
            $cornerstone_regex .= '|\[x_button(?:[^\]]*)\](.*?)\[\/x_button\]';
        }
        if(false !== strpos($content, 'cs_element_card')){
            $cornerstone_regex .= '|\[cs_element_card(?:[^\]]*)\]\[cs_content_seo\](.*?)\[\/cs_content_seo\]';
        }

        // if there is Cornerstone content
        if(!empty($cornerstone_regex)){
            // remove any leading "|" since it would be bad to have it...
            $cornerstone_regex = ltrim($cornerstone_regex, '|');
            // remove the items we don't want to add links to
            $content = mb_ereg_replace($cornerstone_regex, '', $content); // Remove Cornerstone/X|Pro theme headings and items with links
        }

        return $content;
    }

    /**
     * Removes elements from the content that have certain classes
     **/
    public static function removeClassedElements($content = ''){
        if(empty($content)){
            return $content;
        }

        // remove the twitter-tweet element as a standard remove if it's in the content
        if(!empty($content) && false !== strpos($content, 'blockquote') && false !== strpos($content, 'twitter-tweet')){
            $content = mb_ereg_replace('<blockquote[\s][^>]*?(class=["\'][^"\']*?(twitter-tweet)[^"\']*?["\'])[^>]*?>.*?<(\/blockquote|\\\/blockquote)', '', $content);
        }

        // get the classes to ignore
        $remove_classes = Wpil_Settings::get_ignored_element_classes();

        // exit if the user isn't ignoring any classed elements
        if(empty($remove_classes)){
            return $content;
        }

        // create a list of the different styles of regex
        $regex_strings = array(
            'right_wildcard' => '',
            'left_wildcard' => '',
            'all_wildcard' => '',
            'no_wildcard' => ''
        );

        // sort the classes based on wildcard matching status and "OR" separate them in the search strings
        foreach($remove_classes as $class){
            // if the class isn't in the content, skip to the next class
            if(false === strpos($content, trim(trim($class, '*')))){
                continue;
            }

            $left   = ('*' === substr($class, 0, 1)) ? true: false;
            $right  = ('*' === substr($class, -1)) ? true: false;

            // TODO: double check these wildcard matches to make sure that we aren't just checking the first item for wildcard matching
            if($left & $right){
                $regex_strings['all_wildcard'] .= '|' . preg_quote(trim(trim($class, '*')));
            }elseif($left){
                $regex_strings['left_wildcard'] .= '|' . preg_quote(trim(trim($class, '*')));
            }elseif($right){
                $regex_strings['right_wildcard'] .= '|' . preg_quote(trim(trim($class, '*')));
            }else{
                $regex_strings['no_wildcard'] .= '|' . preg_quote(trim(trim($class, '*')));
            }
        }

        // go over the search strings
        foreach($regex_strings as $index => $string){
            if(empty($string)){
                continue;
            }

            // remove any leading/trailling "OR"s
            $regex_class = trim($string, '|');

            // create the class search string for the appropriate wildcard settings
            if($index === 'all_wildcard'){
                $regex_class = '["\'][^"\'<>]*?(' . $regex_class . ')[^"\'<>]*?["\']';
            }elseif($index === 'left_wildcard'){
                $regex_class = '["\'][^"\'<>]*?(' . $regex_class . ')(?:["\']|\s[^"\'<>]*?["\'])';
            }elseif($index === 'right_wildcard'){
                $regex_class = '(?:["\']|["\'][^"\'<>]*?\s)(' . $regex_class . ')[^"\'<>]*?["\']';
            }else{
                $regex_class = '(?:["\']|["\'][^"\'<>]*?\s)(' . $regex_class . ')(?:["\']|\s[^"\'<>]*?["\'])';
            }

            // create a regext that searches for opening/closing elements with the classes, and any images with the classes
            $regex = '<([a-zA-Z]*?[^\s<>]*?)[\s][^<>]*?(class=' . $regex_class . ')[^<>]*?>[\s\S]*?<\/\1>|<img[\s][^<>]*?(class=' . $regex_class . ')[^<>]*?>';
            // NOTE: This regex will remove the opening tag that contains the class and any closing tag that matches the opening tag's name.
            // So it's possible to remove everything between the first opening tag in a bunch of nested divs and the closing tag of the innermost element.
            // Leaving behind a number of unaffiliated closing divs.
            // Since we don't directly use most of the content we process, this _shouldn't_ be an issue for suggestions.
            // But it does mean that the reach of this is somewhat limited.
            // And if we do make the mistake of using phrase data for saving purposes, it could cause some issues.

            // remove the classed elements from the content
            $content = mb_ereg_replace($regex, '', $content);
        }

        return $content;
    }

    /**
     * Removes user-ignored shortcodes from supplied content so we don't process their content
     **/
    public static function removeShortcodes($content = ''){
        if(empty($content)){
            return $content;
        }

        // get the shortcodes to ignore
        $shortcode_names = Wpil_Settings::get_ignored_shortcode_names();

        // exit if the user isn't ignoring any shortcodes
        if(empty($shortcode_names)){
            return $content;
        }

        // go over the shortcode names
        foreach($shortcode_names as $index => $name){
            // if there's no name, or it's not in the content
            if(empty($name) || false === strpos($content, '[' . $name)){ // we're checking for the opening tag
                // skip to the next shortcode
                continue;
            }
            
            // remove any opening/closing shortcode pairs, and the content then contain
            $regex = '\[' . preg_quote($name) . '(?:[ ][^\[\]]*\]|\])[\s\S]*?\[\/' . preg_quote($name) . '\]';
            $content = mb_ereg_replace($regex, '', $content);

            // if there are still shortcodes in the content
            if(false !== strpos($content, '[' . $name)){ // again checking for the opening tag
                // try removing singular shortcode tags
                $regex = '\[' . preg_quote($name) . '(?:[ ][^\[\]]*\]|\])';
                $content = mb_ereg_replace($regex, '', $content);
            }
        }

        return $content;
    }

    /**
     * Removes user-ignored HTML tags so we don't stick links in them
     **/
    public static function removeIgnoredContentTags($content = ''){
        if(empty($content)){
            return $content;
        }

        // get the shortcodes to ignore
        $ignored_tags = Wpil_Settings::getIgnoreLinkingTags();

        // exit if the user isn't ignoring any shortcodes
        if(empty($ignored_tags)){
            return $content;
        }

        // set up the tag removing regex
        $tag_regex = '';

        // go over the tags
        foreach($ignored_tags as $index => $tag){
            // if there's no tag, or it's not in the content
            if(empty($tag) || false === strpos($content, '</' . $tag . '>')){ // we're checking for the closing tag since that won't have classes or other random attributes
                // skip to the next tag
                continue;
            }
            
            // if we've got a tag, add it to the regex
            $tag_regex .= '<' . $tag . '(?:[^>]*)>(.*?)<\/' . $tag . '>|';
        }

        // trim off any pipes
        $tag_regex = (!empty($tag_regex)) ? trim($tag_regex, '|'): '';

        // if we've got a regex to work with
        if(!empty($tag_regex)){
            // use it on the content
            $content = mb_ereg_replace($tag_regex, '', $content);
        }

        // and return the content
        return $content;
    }

    /**
     * Removes the user's selected number of paragraphs from the start of the post content
     **/
    public static function removeParagraphs($content){
        $skip_count = Wpil_Settings::getSkipSentences();

        if(empty($skip_count)){
            return $content;
        }

        // create an offset index for the tags we're searching for
        $char_count = array(
            'p' => 4,
            'div' => 6,
            'newline' => 2,
            'blockquote' => 12
        );

        $i = 0;
        $pos = 0;
        while($i < $skip_count){
            // search for the possible paragraph endings
            $pos_search = array(
                'p' => mb_strpos($content, '</p>', $pos),
                'div' => mb_strpos($content, '</div>', $pos),
                'newline' => mb_strpos($content, '\n', $pos), // newlines mainly apply to module-based builder content since we separate the modules with "\n"
                'blockquote' => mb_strpos($content, '</blockquote>', $pos)
            );

            // sort the results and remove the empties
            asort($pos_search);
            $pos_search = array_filter($pos_search);

            // exit if nothing is found
            if(empty($pos_search)){
                break;
            }

            // get the closest paragraph ending and it's type
            $temp_pos = reset($pos_search);
            $temp_ind = key($pos_search);

            // if the ending was a div
            if($temp_ind === 'div'){
                // see if there's an opening tag before the last pos
                $div_pos = mb_strpos($content, '<div', $pos);

                // if there is
                if(false !== $div_pos){
                    // check if there's any text between the tags
                    $div_content = mb_substr($content, $div_pos, ($temp_pos - $div_pos)); // full-length string ending - div start == div_content. If we don't remove the div start the string is too long
                    $div_content = trim(strip_tags(mb_ereg_replace('<a[^>]*>.*?</a>|<h[1-6][^>]*>.*?</h[1-6]>', '', $div_content))); // remove links, headings, strip tags that might have text attrs, and trim

                    // if there isn't any content, but there is a runner-up tag
                    if(empty($div_content) && count($pos_search) > 1){
                        // go with it's position since the div wasn't actually a paragraph
                        $slice = array_slice($pos_search, 1, 1);
                        $temp_pos = reset($slice);
                        $temp_ind = key($slice);
                    }
                }else{
                    // if there isn't an opening div tag, we're holding the tail of a container.
                    // So go with the runner-up tag
                    if(count($pos_search) > 1){
                        $slice = array_slice($pos_search, 1, 1);
                        $temp_pos = reset($slice);
                        $temp_ind = key($slice);
                    }
                }
            }

            $pos = ($temp_pos + $char_count[$temp_ind]);

            $i++;
        }

        // make sure that content is longer than the skip pos
        if(mb_strlen($content) > $pos){
            $content = mb_substr($content, $pos);
        }

        return $content;
    }

    /**
     * Get phrases from sentence
     */
    public static function getPhrasesFromSentence($sentence, $one_word = false)
    {
        $phrases = [];
        $replace = [', ', ': ', '; ', ' – ', ' (', ') ', ' {', '} '];
        $src = $sentence['src_raw'];

        //change divided symbols inside tags to special codes
        preg_match_all('|<[^>]+>|', $src, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tag) {
                $tag_replaced = $tag;
                foreach ($replace as $key => $value) {
                    if (strpos($tag, $value) !== false) {
                        $tag_replaced = str_replace($value, "[rp$key]", $tag_replaced);
                    }
                }

                if ($tag_replaced != $tag) {
                    $src = str_replace($tag, $tag_replaced, $src);
                }
            }
        }

        //divide sentence to phrases
        $src = str_ireplace($replace, "\n", $src);

        //change special codes to divided symbols inside tags
        foreach ($replace as $key => $value) {
            $src = str_replace("[rp$key]", $value, $src);
        }

        $list = explode("\n", $src);

        foreach ($list as $item) {
            $item = self::decodeIgnoredText($item);
            $phrase = new Wpil_Model_Phrase([
                'text' => trim(strip_tags(htmlspecialchars_decode($item))),
                'src' => $item,
                'sentence_text' => $sentence['text'],
                'sentence_src' => $sentence['src'],
            ]);

            if (!empty($phrase->text) && ($one_word || count(explode(' ', $phrase->text)) > 1)) {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    /**
     * Decodes any ignored text.
     * @param string The text that is to be decoded
     **/
    public static function decodeIgnoredText($text = ''){
        if(false !== strpos($text, 'wpil-ignore-replace_')){
            $text = preg_replace_callback('/(?:wpil-ignore-replace_([A-z0-9=\/+]*))/', function($i){
                return str_replace($i[0], base64_decode($i[1]), $i[0]);
            }, $text);
        }

        return $text;
    }

    /**
     * Collect uniques words from all post titles
     *
     * @param $post_id
     * @param null $target
     * @return array
     */
    public static function getTitleWords($post, $target = null, $keyword = null, $count = null, $process_key = 0)
    {
        global $wpdb;

        $start = microtime(true);
        $ignore_words = Wpil_Settings::getIgnoreWords();
        $ignore_posts = Wpil_Settings::getIgnorePosts();
        $ignore_categories_posts = Wpil_Settings::getIgnoreCategoriesPosts();
        $ignore_numbers = get_option(WPIL_OPTION_IGNORE_NUMBERS, 1);
        $only_show_cornerstone = (get_option('wpil_link_to_yoast_cornerstone', false) && empty($target));
        $outbound_selected_posts = Wpil_Settings::getOutboundSuggestionPostIds();
        $inbound_link_limit = (int)get_option('wpil_max_inbound_links_per_post', 0);

        $posts = [];
        if (!is_null($target)) {
            $posts[] = $target;
        } else {
            $limit  = Wpil_Settings::getProcessingBatchSize();
            $post_ids = get_transient('wpil_title_word_ids_' . $process_key);
            if(empty($post_ids) && !is_array($post_ids)){
                //add all posts to array
                $exclude = self::getTitleQueryExclude($post);
                $post_types = implode("','", self::getSuggestionPostTypes());
                $offset = intval($count) * $limit;

                // get all posts in the same language if translation active
                $include = "";
                if (Wpil_Settings::translation_enabled()) {
                    $ids = Wpil_Post::getSameLanguagePosts($post->id);

                    if (!empty($ids)) {
                        $ids = array_slice($ids, $offset, $limit);
                        $include = " AND ID IN (" . implode(', ', $ids) . ") ";
                    } else {
                        $include = " AND ID IS NULL ";
                    }
                }

                // get the age query if the user is limiting the range for linking
                $age_string = Wpil_Query::getPostDateQueryLimit();

                $statuses_query = Wpil_Query::postStatuses();
                $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts WHERE 1=1 $exclude AND post_type IN ('{$post_types}') $statuses_query {$age_string} " . $include);

                if(!empty($post_ids) && !empty($inbound_link_limit)){
                    $post_ids = implode(',', $post_ids);
                    $post_ids = $wpdb->get_col("SELECT `post_id` as 'ID' FROM {$wpdb->postmeta} WHERE `post_id` IN ({$post_ids}) AND `meta_key` = 'wpil_links_inbound_internal_count' AND `meta_value` < {$inbound_link_limit}");
                }

                if(empty($post_ids)){
                    $post_ids = array();
                }

                set_transient('wpil_title_word_ids_' . $process_key, $post_ids, MINUTE_IN_SECONDS * 15);
            }

            // if we're only supposed to show links to the Yoast cornerstone content
            if($only_show_cornerstone && !empty($result)){
                // get the ids from the initial query
                $ids = array();
                foreach($result as $item){
                    $ids[] = $item->ID;
                }

                // query the meta to see what posts have been set as cornerstone content
                $result = $wpdb->get_results("SELECT `post_id` AS ID FROM {$wpdb->prefix}postmeta WHERE `post_id` IN (" . implode(', ', $ids) . ") AND `meta_key` = '_yoast_wpseo_is_cornerstone'");
            }

            // if we're limiting outbound suggestions to specfic posts
            if(empty($target) && !empty($outbound_selected_posts) && !empty($result)){
                // get all of the ids that the user wants to make suggestions to
                $ids = array();
                foreach($outbound_selected_posts as $selected_post){
                    if(false !== strpos($selected_post, 'post_')){
                        $ids[substr($selected_post, 5)] = true;
                    }
                }

                // filter out all the items that aren't in the outbound suggestion limits
                $result_items = array();
                foreach($result as $item){
                    if(isset($ids[$item->ID])){
                        $result_items[] = $item;
                    }
                }

                // update the results with the filtered ids
                $result = $result_items;
            }


            $posts = [];
            $process_ids = array_slice($post_ids, 0, $limit);

            if(!empty($process_ids)){
                $process_ids = implode("', '", $process_ids);
                $result = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE ID IN ('{$process_ids}')");

                foreach ($result as $item) {
                    if (!in_array('post_' . $item->ID, $ignore_posts) && !in_array($item->ID, $ignore_categories_posts)) {
                        $post_obj = new Wpil_Model_Post($item->ID);
                        $post_obj->title = $item->post_title;
                        $posts[] = $post_obj;
                    }
                }

                // remove this batch of post ids from the list and save the list
                $save_ids = array_slice($post_ids, $limit);
                set_transient('wpil_title_word_ids_' . $process_key, $save_ids, MINUTE_IN_SECONDS * 15);
            }

            // if terms are to be scanned, but the user is restricting suggestions by term, don't search for terms to link to. Only search for terms if:
            if (    !empty(Wpil_Settings::getTermTypes()) && // terms have been selected
                    empty(Wpil_Settings::get_suggestion_filter('same_category')) && // we're not restricting by category
                    empty(Wpil_Settings::get_suggestion_filter('same_tag')) && empty($only_show_cornerstone)) // we're not restricting by tag and not setting LW to only process cornerstone content
            {
                if (is_null($count) || $count == 0) {
                    //add all categories to array
                    $exclude = "";
                    if ($post->type == 'term') {
                        $exclude = " AND t.term_id != {$post->id} ";
                    }

                    $taxonomies = Wpil_Settings::getTermTypes();
                    $result = $wpdb->get_results("SELECT t.term_id FROM {$wpdb->prefix}term_taxonomy tt LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $exclude");

                    // if the user only wants to make outbound suggestions to specific categories
                    if(empty($target) && !empty($outbound_selected_posts) && !empty($result)){
                        // get all of the ids that the user wants to make suggestions to
                        $ids = array();
                        foreach($outbound_selected_posts as $selected_term){
                            if(false !== strpos($selected_term, 'term_')){
                                $ids[substr($selected_term, 5)] = true;
                            }
                        }

                        foreach($result as $key => $item){
                            if(!isset($ids[$item->term_id])){
                                unset($result[$key]);
                            }
                        }
                    }

                    foreach ($result as $term) {
                        if (!in_array('term_' . $term->term_id, $ignore_posts)) {
                            $posts[] = new Wpil_Model_Post($term->term_id, 'term');
                        }
                    }
                }
            }
        }

        // get if the user is only matching with part of the post titles
        $partial_match = Wpil_Settings::matchPartialTitles();

        $words = [];
        foreach ($posts as $key => $p) {
            //get unique words from post title
            if (!empty($keyword)) { 
                $title_words = array_unique(Wpil_Word::getWords($keyword));
            } else {
                $title = $p->getTitle();
                if($partial_match){
                    $title = self::getPartialTitleWords($title);
                }
                // get any target keywords the post has and add them to the title so we can make outbound matches based on them
                $title .= Wpil_TargetKeyword::get_active_keyword_string($p->id, $p->type);

                $title_words = array_unique(Wpil_Word::getWords($title));
            }

            foreach ($title_words as $word) {
                $word = Wpil_Stemmer::Stem(Wpil_Word::strtolower($word));

                //check if word is not a number and is not in the ignore words list
                if (!empty($_REQUEST['keywords']) ||
                    (strlen($word) > 2 && !in_array($word, $ignore_words) && (!$ignore_numbers || !is_numeric(str_replace(['.', ',', '$'], '', $word))))
                ) {
                    $words[$word][] = $p;
                }
            }

            // todo: Remove if we pass ver 1.8.6 and there haven't been reports of tons of irrelevant suggestions or timeouts.
//            if ($key % 100 == 0 && microtime(true) - $start > 10) {
//                break;
//            }
        }

        return $words;
    }

    /**
     * Gets the section of title words that the user selected from the settings.
     * @param string $title The unchanged post title straigt from the db.
     * @return string $title The post title after we've applied the user's rules to it and removed any words he doesn't want to match with.
     **/
    public static function getPartialTitleWords($title){
        $partial_match_basis = get_option('wpil_get_partial_titles', false);

        // if the user hasn't set a basis, return the title unchanged
        if(empty($partial_match_basis)){
            return $title;
        }

        // if the user wants to only match with a limited number of words from the front or back of the title
        if($partial_match_basis === '1' || $partial_match_basis === '2'){
            // get the number of words he's selected
            $word_count = get_option('wpil_partial_title_word_count', 0);

            if(!empty($word_count)){
                $title_words = mb_split('\s', $title);

                // if we're supposed to remove words from the front of the title
                if($partial_match_basis === '1'){
                    $title_words = array_splice($title_words, 0, $word_count);
                }else{
                    $title_words = array_splice($title_words, count($title_words) - $word_count);
                }
            }

            $title = implode(' ', $title_words);

        }elseif($partial_match_basis === '3' || $partial_match_basis === '4'){ // if the user wants the title words before or after a split char
            $split_char = get_option('wpil_partial_title_split_char', '');

            // if the user has specified a split char and it's present in the title
            if(!empty($split_char) && false !== mb_strpos($title, $split_char)){
                $title_words = mb_split(preg_quote($split_char), $title);

                // if we're returning words beofre the split
                if($partial_match_basis === '3'){
                    $title = $title_words[0];
                }else{
                    $title = end($title_words);
                }

            }
        }

        return trim($title);
    }

    public static function getExternalTitleWords($post, $keyword = null, $count = null, $external_links = array()){
        global $wpdb;

        $start = microtime(true);
        $ignore_words = Wpil_Settings::getIgnoreWords();
        $ignore_numbers = get_option(WPIL_OPTION_IGNORE_NUMBERS, 1);
        $ignore_posts = Wpil_Settings::getIgnoreExternalPosts();
        $external_site_data = $wpdb->prefix  . 'wpil_site_linking_data';

        $posts = [];

        //add all posts to array
        $limit  = Wpil_Settings::getProcessingBatchSize();
        $offset = intval($count) * $limit;

        // check if the user has disabled suggestions for an external site
        $no_suggestions = get_option('wpil_disable_external_site_suggestions', array());
        $ignore_sites = '';
        if(!empty($no_suggestions)){
            $urls = array_keys($no_suggestions);
            $ignore = implode('\', \'', $urls);
            $ignore_sites = "WHERE `site_url` NOT IN ('$ignore')";
        }

        $result = $wpdb->get_results("SELECT * FROM {$external_site_data} {$ignore_sites} LIMIT {$limit} OFFSET {$offset}");

        $posts = [];
        foreach ($result as $item) {
            // if the object's url isn't being ignored
            if (!in_array('post_' . $item->post_id, $ignore_posts, true) && $item->type === 'post' ||
                !in_array('term_' . $item->post_id, $ignore_posts, true) && $item->type === 'term')
            {
                $posts[] = new Wpil_Model_ExternalPost($item); // pass the whole object from the db
            }
        }

        $words = [];
        foreach ($posts as $key => $p) {
            //get unique words from post title
            if (!empty($keyword)) {
                $title_words = array_unique(Wpil_Word::getWords($keyword));
            } else {
                $title_words = array_unique(explode(' ', $p->stemmedTitle));
            }

            foreach ($title_words as $word) {
                //check if word is not a number and is not in the ignore words list
                if (!empty($_REQUEST['keywords']) ||
                    (strlen($word) > 2 && !in_array($word, $ignore_words) && (!$ignore_numbers || !is_numeric(str_replace(['.', ',', '$'], '', $word))))
                ) {
                    $words[$word][] = $p;
                }
            }

            if ($key % 100 == 0 && microtime(true) - $start > 10) {
                break;
            }
        }

        return $words;
    }

    /**
     * Gets all the keywords the post is trying to rank for.
     * First checks to see if the keywords have been cached before running through the keyword loading process.
     * 
     * @param object $post The Wpil post object that we're getting the keywords for.
     * @param int $key The key assigned to this suggestion processing program.
     * @return array $all_keywords All the keywords the current post has.
     **/
    public static function getPostKeywords($post, $process_key = 0){
        $all_keywords = get_transient('wpil_post_suggestions_keywords_' . $process_key);
        if(empty($all_keywords)){
            // get the target keywords for the current post
            $target_keywords = self::getInboundTargetKeywords($post);

            // if there's no target keywords
            if(empty($target_keywords)){
                // get the post's possible keywords from the content and url
                $content_keywords = self::getContentKeywords($post);
            }else{
                $content_keywords = array();
            }

            // merge the keywords together
            $all_keywords = array_merge($target_keywords, $content_keywords);

            set_transient('wpil_post_suggestions_keywords_' . $process_key, $all_keywords, MINUTE_IN_SECONDS * 10);
        }

        return $all_keywords;
    }

    /**
     * Gets all keywords belonging to posts that the current one could link to.
     * Removes any keywords that match the current post's keywords
     *
     * @param array $words_to_posts The list of posts that share common words with the current post
     * @param array $words_to_posts The list of posts that share common words with the current post
     * @return array $all_keywords All the keywords the current post has.
     **/
    public static function getOutboundPostKeywords($words_to_posts = array(), $post_keywords = array()){
        if(empty($words_to_posts)){
            return array();
        }

        // obtain the post ids from the post word data
        $id_data = array();
        foreach($words_to_posts as $word_data){
            foreach($word_data as $post){
                $id_data[$post->type][$post->id] = true;
            }
        }

        // create a list of all the stemmed post keywords
        $post_keyword_list = array();
        foreach($post_keywords as $keyword){
            $post_keyword_list[$keyword->stemmed] = true;
        }

        $all_keywords = array();
        foreach($id_data as $type => $ids){
            $keywords = Wpil_TargetKeyword::get_active_keywords_by_post_ids(array_keys($ids), $type);

            if(!empty($keywords)){
                foreach($keywords as $keyword){
                    $kword = new Wpil_Model_Keyword($keyword);
                    if(!isset($post_keyword_list[$kword->stemmed])){
                        $all_keywords[] = $kword;
                    }
                }
            }
        }

        return $all_keywords;
    }

    /**
     * Creates a list of keywords for potential suggestion posts that are more specific than the keywords for the current post.
     * So for example, if the current post has a keyword of "House", but another post has "Dog House", this will add "Dog House" as a more specific keyword and allow matches to be made to that post.
     * Without this, all sentences that contain the word "House" will be removed from the suggestions, and "Dog House", "House Repair", and "House Fire" posts won't get suggestions.
     * 
     * All specific keywords are stored in subarrays that are keyed with the less specific keyword.
     * That way we don't have to loop through all of the keywords to see if one of there's a more specific keyword.
     * 
     * @param array $target_keywords The full list of keywords that belong to posts that could be linked
     * @param array $post_keywords The current post's keywords
     * @return array $specific_keywords The list of more specific keywords
     **/
    public static function getMoreSpecificKeywords($target_keywords, $post_keywords){
        $specific_keywords = array();

        foreach($target_keywords as $target_keyword){
            foreach($post_keywords as $post_keyword){
                if( isset($post_keyword->stemmed) && !empty($post_keyword->stemmed) &&
                    $target_keyword->stemmed !== $post_keyword->stemmed &&
                    false !== mb_strpos($target_keyword->stemmed, $post_keyword->stemmed))
                {
                    if(!isset($specific_keywords[$post_keyword->stemmed])){
                        $specific_keywords[$post_keyword->stemmed] = array($target_keyword);
                    }else{
                        $specific_keywords[$post_keyword->stemmed][] = $target_keyword;
                    }
                }
            }
        }

        return $specific_keywords;
    }


    /**
     * Gets the target keyword data from the database for the current post, formatted for use in the inbound suggestions.
     **/
    public static function getInboundTargetKeywords($post){
        $keywords = Wpil_TargetKeyword::get_active_keywords_by_post_ids($post->id, $post->type);
        if(empty($keywords)){
            return array();
        }

        $keyword_list = array();
        foreach($keywords as $keyword){
            $keyword_list[] = new Wpil_Model_Keyword($keyword);
        }

        return $keyword_list;
    }

    /**
     * Extracts the post's keywords from the post's content and url.
     * 
     **/
    public static function getContentKeywords($post_obj){
        if(empty($post_obj)){
            return array();
        }

        $post_keywords = array();

        // first get the keyword data from the url/slug
        if($post_obj->type === 'post'){
            $post = get_post($post_obj->id);
            $url_words = $post->post_name;
        }else{
            $post = get_term($post_obj->id);
            $url_words = $post->slug;
        }

        $keywords = implode(' ', explode('-', $url_words));

        $data = array(
            'post_id' => $post_obj->id, 
            'post_type' => $post_obj->type, 
            'keyword_type' => 'post-keyword', 
            'keywords' => $keywords,
            'checked' => 1 // the keyword is effectively checked since it's in the url
        );

        // create the url keyword object and add it to the list of keywords
        $post_keywords[] = new Wpil_Model_Keyword($data);

        // now pull the keywords from the h1 tags if present
        if($post_obj->type === 'post'){
            $content = $post->post_title;
        }else{
            $content = $post->name;
        }

        $data = array(
            'post_id' => $post_obj->id, 
            'post_type' => $post_obj->type, 
            'keyword_type' => 'post-keyword', 
            'keywords' => strip_tags($content),
            'checked' => 1 // the keyword is effectively checked since it's in the header
        );

        // create the h1 keyword object and add it to the list of keywords
        $post_keywords[] = new Wpil_Model_Keyword($data);

/*        
        preg_match('/<h1[^>]*.?>([[:alpha:]\s]*.?)(<\/h1>)/', $content, $matches);
error_log(print_r($post, true));
        if(!empty($matches) && isset($matches[1])){
            $data = array(
                'post_id' => $post_obj->id, 
                'post_type' => $post_obj->type, 
                'keyword_type' => 'post-keyword', 
                'keywords' => strip_tags($matches[1]),
                'checked' => 1 // the keyword is effectively checked since it's in the header
            );

            // create the h1 keyword object and add it to the list of keywords
            $post_keywords[] = new Wpil_Model_Keyword($data);
        }*/

        return $post_keywords;
    }

    /**
     * Creates a list of all the unique keyword words for use in comparisons.
     * So far only used in loose keyword matching. (Currently disabled)
     **/
    public static function getPostUniqueKeywords($target_keywords = array(), $process_key = 0){
        if(empty($target_keywords) || empty($process_key)){
            return array();
        }

        $keywords = get_transient('wpil_post_suggestions_unique_keywords_' . $process_key);
        if(empty($keywords)){
            $keywords = array();
            // add all the keywords to the array
            foreach($target_keywords as $keyword){
                $words = explode(' ', $keyword->stemmed);
                $keywords = array_merge($keywords, $words);
            }

            // at the end, do a flip flip to get the unique words
            $keywords = array_flip(array_flip($keywords));

            // save the results to the cache
            set_transient('wpil_post_suggestions_unique_keywords_' . $process_key, $keywords, MINUTE_IN_SECONDS * 10);
        }

        return $keywords;
    }

    /**
     * Gets the target keyword data from the database for all the posts, formatted for use in the inbound suggestions.
     * Not currently used.
     **/
    public static function getOutboundTargetKeywords($ignore_ids = array(), $ignore_item_types = array()){
        if(isset($_REQUEST['type']) && 'inbound_suggestions' === $_REQUEST['type']){
            $keywords = Wpil_TargetKeyword::get_all_active_keywords($ignore_ids, $ignore_item_types);
            if(empty($keywords)){
                return array();
            }

            $keyword_index = array();
            foreach($keywords as $keyword){
                $words = explode(' ', $keyword->keywords);
                foreach($words as $word){
                    $keyword_index[Wpil_Stemmer::Stem(Wpil_Word::strtolower($word))][$keyword->keyword_index] = $keyword;
                }
            }

            return $keyword_index;
        }else{
            return array();
        }
    }

    /**
     * Get max amount of words in group between sentence
     *
     * @param $words
     * @param $title
     * @return int
     */
    public static function getMaxCloseWords($words_used_in_suggestion, $phrase_text)
    {
        // get the individual words in the source phrase, cleaned of puncuation and spaces
        $phrase_text = Wpil_Word::getWords($phrase_text);

        // stem each word in the phrase text
        foreach ($phrase_text as $key => $value) {
            $phrase_text[$key] = Wpil_Stemmer::Stem(Wpil_Word::strtolower($value));
        }

        // loop over the phrase words, and find the largest grouping of the suggestion's words that occur in sequence in the phrase
        $max = 0;
        $temp_max = 0;
        foreach($phrase_text as $key => $phrase_word){
            if(in_array($phrase_word, $words_used_in_suggestion)){
                $temp_max++;
                if($temp_max > $max){
                    $max = $temp_max;
                }
            }else{
                if($temp_max > $max){
                    $max = $temp_max;
                }
                $temp_max = 0;
            }
        }

        return $max;
    }

    /**
     * Measures how long an anchor text suggestion would be based on the words from the match
     **/
    public static function getSuggestionAnchorLength($phrase = '', $words = array()){
        // stem the sentence words
        $stemmed_phrase_words = array_map(array('Wpil_Stemmer', 'Stem'), Wpil_Word::getWords($phrase->text));

        //get min and max words position in the phrase
        $min = count($stemmed_phrase_words);
        $max = 0;
        foreach ($words as $word) {
            if (in_array($word, $stemmed_phrase_words)) {
                $pos = array_search($word, $stemmed_phrase_words);
                $min = $pos < $min ? $pos : $min;
                $max = $pos > $max ? $pos : $max;
            }
        }

        // check to see if we can get a link in this suggestion
        $has_words = array_slice($stemmed_phrase_words, $min, $max - $min + 1);

        // if we are
        if(!empty($has_words)){
            // return the length of the suggested anchor
            return ($max + 1) - $min;
        }

        // If that method didn't work, try the old one
        $intersect = array_keys(array_intersect($stemmed_phrase_words, $words));
        $start = current($intersect);
        $end = end($intersect);

        return (($end - $start) + 1);
    }

    /**
     * Adjusts long suggestions so they're shorter by removing words that aren't required for making suggestions.
     * Since LW uses ALL possible common words in making suggestions, it's possible that the matches will contain so many words that it trips the max anchor length check.
     * So an extremly valid suggestion will be removed because it's over qualified.
     * This function's job is to remove extra words that are less important to the overall suggestion to hopefully get under the limit.
     * 
     * @param object $phrase
     * @param array $suggestion The suggestion data that we're going to adjust. This is before the data is put into a suggestion object, so we're going to be dealing with an array
     **/
    public static function adjustTooLongSuggestion($phrase = array(), $suggestion = array()){
        if(empty($suggestion)){
            return $suggestion;
        }

        // stem the sentence words
        $stemmed_phrase_words = array_map(array('Wpil_Stemmer', 'Stem'), Wpil_Word::getWords($phrase->text));

        // get the suggestion text start and end points
        $min = count($stemmed_phrase_words);
        $max = 0;
        foreach ($suggestion['words'] as $word) {
            if (in_array($word, $stemmed_phrase_words)) {
                $pos = array_search($word, $stemmed_phrase_words);
                $min = $pos < $min ? $pos : $min;
                $max = $pos > $max ? $pos : $max;
            }
        }

        // try obtaining the anchor text
        $anchor_words = array_slice($stemmed_phrase_words, $min, $max - $min + 1);

        // if we aren't able to
        if(empty($anchor_words)){
            // try the old method of getting anchor text
            $intersect = array_keys(array_intersect($stemmed_phrase_words, $suggestion['words']));
            $min = current($intersect);
            $max = end($intersect);
            $anchor_words = array_slice($stemmed_phrase_words, $min, $max - $min + 1);
        }

        // create a list of the matching words
        $temp_sentence = implode(' ', $anchor_words);
        // and create the inital map of the words
        $word_positions = array_map(function($word){ 
            return array(
                'word' => $word,            // the stemmed anchor word
                'value' => 0,               // the words matching value
                'significance' => array(),  // what gives the word meaning. (keyword|title match)
                'keyword_class' => array());// tag so we can tell if words are part of a keyword
            }, $anchor_words);

        // first, find any target keyword matches
        if(!empty($suggestion['matched_target_keywords'])){
            foreach($suggestion['matched_target_keywords'] as $kword_ind => $keyword){
                $pos = mb_strpos($temp_sentence, $keyword->stemmed);
                if(false !== $pos){
                    // get the string before and after the keyword
                    $before = mb_substr($temp_sentence, 0, $pos);
                    $after  = mb_substr($temp_sentence, ($pos + mb_strlen($keyword->stemmed)));

                    // now explode all of the strings to get the positions
                    $before = explode(' ', $before);
                    $keyword_bits = explode(' ', $keyword->stemmed);
                    $after  = explode(' ', $after);

                    //
                    $offset = (count($before) > 0) ? count($before) - 1: 0;

                    // and map the pieces so we can tell where the keyword is
                    foreach($keyword_bits as $ind => $bit){
                        $ind2 = ($ind + $offset);
                        $word_positions[$ind2]['value'] += 20;
                        $word_positions[$ind2]['significance'][] = 'keyword';
                        $word_positions[$ind2]['keyword_class'][] = 'keyword-' . $kword_ind;
                    }
                }
            }
        }

        // next get the matched title words
        $stemmed_title_words = Wpil_Word::getStemmedWords($suggestion['post']->getTitle());
        $title_count = count($stemmed_title_words);
        $position_count = count($word_positions);

        foreach($stemmed_title_words as $title_ind => $title_word){
            foreach($word_positions as $ind => $dat){
                if($dat['word'] === $title_word){
                    $word_positions[$ind]['value'] += 1;
                    $word_positions[$ind]['significance'][] = 'title-word';

                    // if this is the first word in the anchor & the first word in the post title
                    if($title_ind === 0 && $ind === 0){
                        // note it since it's more likely to be important
                        $word_positions[$ind]['significance'][] = 'first-title-word';
                        $word_positions[$ind]['significance'][] = 'title-position-match';
                        $word_positions[$ind]['value'] += 1;
                    }elseif($title_ind === ($title_count - 1) && $ind === ($position_count - 1)){
                        // if this is the last word anchor & the last word in the post title
                        // note it since it's more likely to be important
                        $word_positions[$ind]['significance'][] = 'last-title-word';
                        $word_positions[$ind]['significance'][] = 'title-position-match';
                        $word_positions[$ind]['value'] += 1;
                    }
                }
            }
        }

        // now that we've mapped the word positions, it's time to begin working on what words to remove.
        // first check the easy ones, are there any words on the edges of the sentence that aren't keywords
        $end = end($word_positions);
        $start = reset($word_positions);

        // check the end first
        if( in_array('title-word', $end['significance'], true) && 
            !in_array('last-title-word', $end['significance'], true) && 
            !in_array('keyword', $end['significance'], true)
        ){
            // remove the last word from the possible anchor
            $word_positions = array_slice($word_positions, 0, count($word_positions) - 1);

            // remove any insignificant words that result
            $end = end($word_positions);
            if(empty($end['significance'])){
                $word_ind = (count($word_positions) - 1);
                while(empty($word_positions[$word_ind]) && $word_ind > 0){
                    $word_positions = array_slice($word_positions, 0, $word_ind);
                    $word_ind--;
                }
            }

            // if the proposed anchor is shorter than the limit
            if(count($word_positions) <= self::$max_anchor_length){
                // update the suggestion words
                $new_words = array_filter(array_map(function($data){ return (!empty($data['significance'])) ? $data['word']: false; }, $word_positions));
                $suggestion['words'] = $new_words;
                // say how long the suggestion is
                $suggestion['length'] = count($word_positions);
                // and return the suggestion
                return $suggestion;
            }
        }

        if( in_array('title-word', $start['significance'], true) && 
            !in_array('first-title-word', $start['significance'], true) && 
            !in_array('keyword', $start['significance'], true)
        ){
            // remove the first word from the possible anchor
            $word_positions = array_slice($word_positions, 1);

            // remove any insignificant words that result
            $start = reset($word_positions);
            if(empty($start['significance'])){
                $word_count = count($word_positions);
                $loop = 0;
                while(empty($word_positions[0]) && $loop < $word_count){
                    $word_positions = array_slice($word_positions, 1);
                    $loop++;
                }
            }

            // if the proposed anchor is shorter than the limit
            if(count($word_positions) <= self::$max_anchor_length){
                // update the suggestion words
                $new_words = array_filter(array_map(function($data){ return (!empty($data['significance'])) ? $data['word']: false; }, $word_positions));
                $suggestion['words'] = $new_words;
                // say how long the suggestion is
                $suggestion['length'] = count($word_positions);
                // and return the suggestion
                return $suggestion;
            }
        }

        // if we've made it this far, we weren't able to remove enough words to fit within the limit
        // Now we have to try and judge which words are most likely to not be missed

        // we'll go around 5 times at the most
        for($run = 0; $run < 5; $run++){
            // get the first and last words in the suggested anchor
            $first = $word_positions[0];
            $last = end($word_positions);

            // check if they're both keyword-words
            if( in_array('keyword', $first['significance'], true) &&
                in_array('keyword', $last['significance'], true))
            {
                // if they are, figure out which is the less valuable keyword and remove it
                $first_score = 0;
                $last_score = 0;

                foreach($word_positions as $dat){
                    $first_class = array_intersect($dat['keyword_class'], $first['keyword_class']);
                    $last_class = array_intersect($dat['keyword_class'], $last['keyword_class']);

                    if(!empty($first_class)){
                        $first_score += $dat['value'];
                    }

                    if(!empty($last_class)){
                        $last_score += $dat['value'];
                    }
                }

                // if the first word's score is lower than the last
                if($first_score < $last_score){
                    // remove words from the start
                    $temp = self::removeStartingWords($first, $word_positions);
                }else{
                    // if the last score is smaller or the same as the first, remove the words from the end
                    $temp = self::removeEndingWords($last, $word_positions);
                }
            }elseif($last['value'] < $first['value']){
                $temp = self::removeEndingWords($last, $word_positions);
            }elseif($last['value'] > $first['value']){
                $temp = self::removeStartingWords($first, $word_positions);
            }else{
                // if both words have the same value, remove the word(s) from the end of the anchor
                $temp = self::removeEndingWords($last, $word_positions);
            }

            // exit the loop if all we've managed to do is delete the text
            if(empty($temp)){
                break;
            }

            // update the word positions with the temp data
            $word_positions = $temp;

            // if the proposed anchor is shorter than the limit
            if(count($word_positions) <= self::$max_anchor_length){
                // update the suggestion words
                $new_words = array_filter(array_map(function($data){ return (!empty($data['significance'])) ? $data['word']: false; }, $word_positions));
                $suggestion['words'] = $new_words;
                // say how long the suggestion is
                $suggestion['length'] = count($word_positions);
                // and break out of the loop
                break;
            }
        }

        // now that we're done chewing the anchor text, return the suggestion
        return $suggestion;
    }

    /**
     * Removes words from the start of the suggested anchor
     **/
    private static function removeStartingWords($first, $word_positions){
        $anchr_len = count($word_positions);

        // if the first word is part of a keyword
        if( in_array('keyword', $first['significance'], true) && 
            isset($word_positions[1]) && 
            in_array('keyword', $word_positions[1]['significance'], true)
        ){
            // remove all of the words in this keyword and any insignificant words following it
            $ind = 0;
            $kword_class = $word_positions[$ind]['keyword_class'];
            while($ind < $anchr_len && (in_array('keyword', $word_positions[0]['significance'], true) || empty($word_positions[0]['significance']))){
                $same_class = array_intersect($word_positions[0]['keyword_class'], $kword_class);

                // if the word is part of the keyword(s) we started with or is just a filler word
                if(!empty($same_class) || empty($word_positions[0]['significance'])){
                    // remove it
                    $word_positions = array_slice($word_positions, 1);
                    // and increment the index for another pass
                    $ind++;
                }else{
                    // if we can't remove any more words, exit
                    break;
                }
            }
        }else{
            // if the word isn't part of a keyword, remove it and any insignificant words following it
            $ind = 0;
            $word_positions = array_slice($word_positions, 1);
            while($ind < ($anchr_len - 1) && empty($word_positions[0]['significance'])){
                // if the word is just a filler word
                if(empty($word_positions[0]['significance'])){
                    // remove it
                    $word_positions = array_slice($word_positions, 1);
                    // and increment the index for another pass
                    $ind++;
                }else{
                    break;
                }
            }
        }

        return $word_positions;
    }

    /**
     * Removes words from the end of the suggested anchor
     **/
    private static function removeEndingWords($last, $word_positions){
        $ind = count($word_positions) - 1;

        // if the last word is part of a keyword
        if( in_array('keyword', $last['significance'], true) && 
            isset($word_positions[$ind]) && 
            in_array('keyword', $word_positions[$ind]['significance'], true)
        ){
            // remove all of the words in this keyword and any insignificant words preceeding it
            $kword_class = $word_positions[$ind]['keyword_class'];
            while($ind >= 0 && (in_array('keyword', $word_positions[$ind]['significance'], true) || empty($word_positions[$ind]['significance']))){
                $same_class = array_intersect($word_positions[$ind]['keyword_class'], $kword_class);

                // if the word is part of the keyword(s) we started with or is just a filler word
                if(!empty($same_class) || (isset($word_positions[$ind]) && empty($word_positions[$ind]['significance']))){
                    // remove it
                    $word_positions = array_slice($word_positions, 0, $ind);
                    // and decrement the index for another pass
                    $ind--;
                }else{
                    // if we can't remove any more words, exit
                    break;
                }
            }
        }else{
            // if the word isn't part of a keyword, remove it and any insignificant words preceeding it
            $word_positions = array_slice($word_positions, 0, $ind);
            $ind--;
            while($ind >= 0 && empty($word_positions[$ind]['significance'])){
                // if the word is just a filler word
                if(isset($word_positions[$ind]) && empty($word_positions[$ind]['significance'])){
                    // remove it
                    $word_positions = array_slice($word_positions, 0, $ind);
                    // and decrement the index for another pass
                    $ind--;
                }else{
                    break;
                }
            }
        }

        return $word_positions;
    }

    /**
     * Add anchors to sentences
     *
     * @param $sentences
     * @return mixed
     */
    public static function addAnchors($phrases, $outbound = false)
    {
        if(empty($phrases)){
            return array();
        }

        $post = Wpil_Base::getPost();
        $used_anchors = ($_POST['type'] === 'outbound_suggestions') ? Wpil_Post::getAnchors($post) : array();
        $nbsp = urldecode('%C2%A0');
        $post_target_keywords = Wpil_Word::strtolower(implode(' ', Wpil_TargetKeyword::get_active_keyword_list($post->id, $post->type)));

        $ignored_words = Wpil_Settings::getIgnoreWords();
        foreach ($phrases as $key_phrase => $phrase) {
            //prepare rooted words array from phrase
            $words = trim(preg_replace('/\s+|'.$nbsp.'/', ' ', $phrase->text));
            $words = $words_real = (!self::isAsianText()) ? array_map(function($a){ return trim($a, '():\''); /* remove the letters that going to be marked as "non-word chars" later */}, explode(' ', $words)) : mb_str_split(trim($words));
            foreach ($words as $key => $value) {
                $value = Wpil_Word::removeEndings($value, ['[', ']', '(', ')', '{', '}', '.', ',', '!', '?', '\'', ':', '"']);
                if (!empty($_REQUEST['keywords']) || !in_array($value, $ignored_words) || false !== strpos($post_target_keywords, Wpil_Word::strtolower($value))) {
                    $words[$key] = Wpil_Stemmer::Stem(Wpil_Word::strtolower(strip_tags($value)));
                } else {
                    unset($words[$key]);
                }
            }

            foreach ($phrase->suggestions as $suggestion_key => $suggestion) {
                //get min and max words position in the phrase
                $min = count($words_real);
                $max = 0;
                foreach ($suggestion->words as $word) {
                    if (in_array($word, $words)) {
                        $pos = array_search($word, $words);
                        $min = $pos < $min ? $pos : $min;
                        $max = $pos > $max ? $pos : $max;
                    }
                }

                // check to see if we can get a link in this suggestion
                $has_words = array_slice($words_real, $min, $max - $min + 1);
                if(empty($has_words)){
                    // if it can't, remove it from the list
                    unset($phrase->suggestions[$suggestion_key]);
                    // and proceed
                    continue;
                }

                //get anchors and sentence with anchor
                $anchor = '';
                $sentence_with_anchor = '<span class="wpil_word">' . implode('</span> <span class="wpil_word">', explode(' ', str_replace($nbsp, ' ', strip_tags($phrase->sentence_src, '<b><i><u><strong>')))) . '</span>';
                $sentence_with_anchor = str_replace(['<span class="wpil_word">(', ')</span>', ':</span>', '<span class="wpil_word">\'', '\'</span>'], ['<span class="wpil_word no-space-right wpil-non-word">(</span><span class="wpil_word">', '</span><span class="wpil_word no-space-left wpil-non-word">)</span>', '</span><span class="wpil_word no-space-left wpil-non-word">:</span>', '<span class="wpil_word no-space-right wpil-non-word">\'</span><span class="wpil_word">', '</span><span class="wpil_word no-space-left wpil-non-word">\'</span>'], $sentence_with_anchor);
                $sentence_with_anchor = str_replace(',</span>', '</span><span class="wpil_word no-space-left wpil-non-word">,</span>', $sentence_with_anchor);
                $sentence_with_anchor = self::formatTags($sentence_with_anchor);
                if ($max >= $min) {
                    if ($max == $min) {
                        $anchor = '<span class="wpil_word">' . $words_real[$min] . '</span>';
                        $to = '<a href="%view_link%">' . $anchor . '</a>';
                        $sentence_with_anchor = preg_replace('/'.preg_quote($anchor, '/').'/', $to, $sentence_with_anchor, 1);
                    } else {
                        $anchor = '<span class="wpil_word">' . implode('</span> <span class="wpil_word">', array_slice($words_real, $min, $max - $min + 1)) . '</span>';
                        $from = [
                            '<span class="wpil_word">' . $words_real[$min] . '</span>',
                            '<span class="wpil_word">' . $words_real[$max] . '</span>'
                        ];
                        $to = [
                            '<a href="%view_link%"><span class="wpil_word">' . $words_real[$min] . '</span>',
                            '<span class="wpil_word">' . $words_real[$max] . '</span></a>'
                        ];

                        $sentence_with_anchor = preg_replace('/'.preg_quote($from[0], '/').'/', $to[0], $sentence_with_anchor, 1);
                        $begin = strpos($sentence_with_anchor, '<a ');
                        if ($begin !== false) {
                            $first = substr($sentence_with_anchor, 0, $begin);
                            $second = substr($sentence_with_anchor, $begin);
                            $second = preg_replace('/'.preg_quote($from[1], '/').'/', $to[1], $second, 1);
                            $sentence_with_anchor = $first . $second;
                        }
                    }
                }

                self::setSentenceSrcWithAnchor($suggestion, $phrase->sentence_src, $words_real[$min], $words_real[$max]);

                //add results to suggestion
                $suggestion->anchor = $anchor;

                if (in_array(strip_tags($anchor), $used_anchors)) {
                    unset($phrases[$key_phrase]);
                }

                $suggestion->sentence_with_anchor = self::setSuggestionTags($sentence_with_anchor);
                $suggestion->original_sentence_with_anchor = $suggestion->sentence_with_anchor;
            }

            // if there are no suggestions, remove the phrase
            if(empty($phrase->suggestions)){
                unset($phrases[$key_phrase]);
            }
        }

        // if these are outbound suggestions
        if($outbound){
            // merge different suggestions from the same source sentence into the same sentence
            $phrases = self::mergeSourceTextPhrases($phrases);
        }

        return $phrases;
    }

    public static function formatTags($sentence_with_anchor){
        $tags = array(
            '<span class="wpil_word"><b>',
            '<span class="wpil_word"><i>',
            '<span class="wpil_word"><u>',
            '<span class="wpil_word"><strong>',
            '<span class="wpil_word"><em>',
            '<span class="wpil_word"></b>',
            '<span class="wpil_word"></i>',
            '<span class="wpil_word"></u>',
            '<span class="wpil_word"></strong>',
            '<span class="wpil_word"></em>',
            '<b></span>',
            '<i></span>',
            '<u></span>',
            '<strong></span>',
            '<em></span>',
            '</b></span>',
            '</i></span>',
            '</u></span>',
            '</strong></span>',
            '</em></span>'
        );

        // the replace tokens of the tags
        $replace_tags = array(
            '<span class="wpil_word wpil_suggestion_tag open-tag wpil-bold-open wpil-bold">PGI+</span><span class="wpil_word">', // these are the base64ed versions of the tags so we can process them later
            '<span class="wpil_word wpil_suggestion_tag open-tag wpil-ital-open wpil-ital">PGk+</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag open-tag wpil-under-open wpil-under">PHU+</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag open-tag wpil-strong-open wpil-strong">PHN0cm9uZz4=</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag open-tag wpil-em-open wpil-em">PGVtPg==</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag close-tag wpil-bold-close wpil-bold">PC9iPg==</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag close-tag wpil-ital-close wpil-ital">PC9pPg==</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag close-tag wpil-under-close wpil-under">PC91Pg==</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag close-tag wpil-strong-close wpil-strong">PC9zdHJvbmc+</span><span class="wpil_word">',
            '<span class="wpil_word wpil_suggestion_tag close-tag wpil-em-close wpil-em">PC9lbT4=</span><span class="wpil_word">',
            '</span><span class="wpil_word wpil_suggestion_tag open-tag wpil-bold-open wpil-bold">PGI+</span>', 
            '</span><span class="wpil_word wpil_suggestion_tag open-tag wpil-ital-open wpil-ital">PGk+</span>',
            '</span><span class="wpil_word wpil_suggestion_tag open-tag wpil-under-open wpil-under">PHU+</span>',
            '</span><span class="wpil_word wpil_suggestion_tag open-tag wpil-strong-open wpil-strong">PHN0cm9uZz4=</span>',
            '</span><span class="wpil_word wpil_suggestion_tag open-tag wpil-em-open wpil-em">PGVtPg==</span>',
            '</span><span class="wpil_word wpil_suggestion_tag close-tag wpil-bold-close wpil-bold">PC9iPg==</span>',
            '</span><span class="wpil_word wpil_suggestion_tag close-tag wpil-ital-close wpil-ital">PC9pPg==</span>',
            '</span><span class="wpil_word wpil_suggestion_tag close-tag wpil-under-close wpil-under">PC91Pg==</span>',
            '</span><span class="wpil_word wpil_suggestion_tag close-tag wpil-strong-close wpil-strong">PC9zdHJvbmc+</span>',
            '</span><span class="wpil_word wpil_suggestion_tag close-tag wpil-em-close wpil-em">PC9lbT4=</span>',
        );

        return str_replace($tags, $replace_tags, $sentence_with_anchor);
    }

    /**
     * Add anchor to the sentence source
     *
     * @param $suggestion
     * @param $sentence
     * @param $word_start
     * @param $word_end
     */
    public static function setSentenceSrcWithAnchor(&$suggestion, $sentence, $word_start, $word_end)
    {
        $sentence .= ' ';
        $begin = strpos($sentence, $word_start . ' ');
        if($begin === false){
            $begin = strpos($sentence, $word_start);
        }
        while($begin && substr($sentence, $begin - 1, 1) !== ' ') {
            $begin--;
        }

        $end = strpos($sentence, $word_end . ' ', $begin);
        if(false === $end){
            $end = strpos($sentence, $word_end, $begin);
        }
        $end += strlen($word_end);
        while($end < strlen($sentence) && substr($sentence, $end, 1) !== ' ') {
            $end++;
        }

        $anchor = substr($sentence, $begin, $end - $begin);
        $replace = '<a href="%view_link%">' . $anchor . '</a>';
        $suggestion->sentence_src_with_anchor = preg_replace('/'.preg_quote($anchor, '/').'/', $replace, trim($sentence), 1);

    }

    public static function setSuggestionTags($sentence_with_anchor){
        // if there isn't a tag inside the suggested text, return it
        if(false === strpos($sentence_with_anchor, 'wpil_suggestion_tag')){
            return $sentence_with_anchor;
        }

        // see if the tag is inside the link
        $link_start = mb_strpos($sentence_with_anchor, '<a href="%view_link%"');
        $link_end = mb_strpos($sentence_with_anchor, '</a>', $link_start);
        $link_length = ($link_end + 4 - $link_start);
        $link = mb_substr($sentence_with_anchor, $link_start, $link_length);

        // if it's not or the open and close tags are in the link, return the link
        if(false === strpos($link, 'wpil_suggestion_tag') || (false !== strpos($link, 'open-tag') && false !== strpos($link, 'close-tag'))){ // todo make this tag specific. As it is now, we _could_ get the opening of one tag and the closing tag of another one since we're only looking for open and close tags. But considering that we've not had much trouble at all from the prior system, this isn't a priority.
            return $sentence_with_anchor;
        }

        // if we have the opening tag inside the link, move it right until it's outside the link
        if(false !== strpos($link, 'open-tag')){
            // get the tag start
            $open_tag = mb_strpos($sentence_with_anchor, '<span class="wpil_word wpil_suggestion_tag open-tag');
            // extract the tag
            $tag = mb_substr($sentence_with_anchor, $open_tag, (mb_strpos($sentence_with_anchor, '</span>', $open_tag) + 7) - $open_tag);
            // replace the tag
            $sentence_with_anchor = mb_ereg_replace(preg_quote($tag), '', $sentence_with_anchor);
            // get the points before and after the link's closing tag
            $link_end = mb_strpos($sentence_with_anchor, '</a>', $link_start);
            $before = mb_substr($sentence_with_anchor, 0, ($link_end + 4));
            $after = mb_substr($sentence_with_anchor, ($link_end + 4));
            // and insert the closing tag just after the link
            $sentence_with_anchor = ($before . $tag . $after);
        }

        // if we have the closing tag inside the link, move it left until it's outside the link
        if(false !== strpos($link, 'close-tag')){
            // get the tag start
            $close_tag = mb_strpos($sentence_with_anchor, '<span class="wpil_word wpil_suggestion_tag close-tag');
            // extract the tag
            $tag = mb_substr($sentence_with_anchor, $close_tag, (mb_strpos($sentence_with_anchor, '</span>', $close_tag) + 7) - $close_tag);
            // replace the tag
            $sentence_with_anchor = mb_ereg_replace(preg_quote($tag), '', $sentence_with_anchor);
            // get the points before and after the link opening tag
            $before = mb_substr($sentence_with_anchor, 0, $link_start);
            $after = mb_substr($sentence_with_anchor, $link_start);
            // and insert the cloasing tag just before the link
            $sentence_with_anchor = ($before . $tag . $after);
        }

        return $sentence_with_anchor;
    }

    /**
     * Merges phrases with the same source text into the same suggestion pool.
     * That way, users get a dropdown of different suggestions instead of a number of loose suggestions.
     * 
     * @param array $phrases 
     * @return array $merged_phrases
     **/
    public static function mergeSourceTextPhrases($phrases){
        $merged_phrases = array();
        $phrase_key_index = array();
        foreach($phrases as $phrase_key => $data){
            $phrase_key_index[$data->sentence_text] = $phrase_key;
        }

        foreach($phrases as $phrase_key => $data){
            $key = $phrase_key_index[$data->sentence_text];
            if(isset($merged_phrases[$key])){
                $merged_phrases[$key]->suggestions = array_merge($merged_phrases[$key]->suggestions, $data->suggestions);
            }else{
                $merged_phrases[$key] = $data;
            }
        }

        return $merged_phrases;
    }

    /**
     * Get Inbound internal links page search keywords
     *
     * @param $post
     * @return array
     */
    public static function getKeywords($post)
    {
        $keywords = array();
        if(!empty($_POST['keywords'])){
            $keywords = explode(";", sanitize_text_field($_POST['keywords']));
        }elseif (!empty($_GET['keywords'])){
            $keywords = explode(";", sanitize_text_field($_GET['keywords']));
        }

        $keywords = array_filter($keywords);

        if(empty($keywords)){
            $words = self::getPartialTitleWords($post->getTitle()) . ' ' . Wpil_TargetKeyword::get_active_keyword_string($post->id, $post->type);
            $words = array_flip(array_flip(Wpil_Word::cleanIgnoreWords(explode(' ', Wpil_Word::strtolower($words)))));
            $keywords = array(implode(' ', $words));
        }

        return $keywords;
    }

    /**
     * Search posts with common words in the content and return an array of all found post ids
     *
     * @param $keyword
     * @param $excluded_posts
     * @return array
     */
    public static function getInboundSuggestedPosts($keyword, $excluded_posts)
    {
        global $wpdb;

        $post_types = implode("','", self::getSuggestionPostTypes());

        $selected_terms = '';
        $term_taxonomy_ids = array();
        if (!empty(Wpil_Settings::get_suggestion_filter('same_category'))) {
            $post = Wpil_Base::getPost();
            if ($post->type === 'post') {
                if (!empty(Wpil_Settings::get_suggestion_filter('selected_category'))) {
                    $term_taxonomy_ids = array_merge($term_taxonomy_ids, self::get_selected_categories());
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(get_taxonomy($tax)->hierarchical){
                            $query_taxes[] = $tax;
                        }
                    }
                    $categories = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'tt_ids']);
                    if(!empty($categories) && !is_a($categories, 'WP_Error')){
                        $term_taxonomy_ids = array_merge($term_taxonomy_ids, $categories);
                    }
                }
            }
        }

        if (!empty(Wpil_Settings::get_suggestion_filter('same_tag'))) {
            $post = Wpil_Base::getPost();
            if ($post->type === 'post') {
                if (!empty(Wpil_Settings::get_suggestion_filter('selected_tag'))) {
                    $term_taxonomy_ids = array_merge($term_taxonomy_ids, self::get_selected_tags());
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(empty(get_taxonomy($tax)->hierarchical)){
                            $query_taxes[] = $tax;
                        }
                    }
                    $tags = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'tt_ids']);
                    if(!empty($tags) && !is_a($tags, 'WP_Error')){
                        $term_taxonomy_ids = array_merge($term_taxonomy_ids, $tags);
                    }
                }
            }
        }

        if(!empty($term_taxonomy_ids)){
            $terms = implode(',', $term_taxonomy_ids);
            $selected_terms = " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($terms)) ";
        }

        //get all posts contains words from post title
        $post_content = self::getInboundPostContent($keyword);

        $include_ids = array();
        $custom_fields = self::getInboundCustomFields($keyword, $term_taxonomy_ids);
        if (!empty($custom_fields)) {
            $posts = $custom_fields;
            if(!empty($excluded_posts)){
                foreach ($posts as $key => $included_post) {
                    if (in_array($included_post, $excluded_posts)) {
                        unset($posts[$key]);
                    }
                }
            }

            if (!empty($posts)) {
                $include_ids = $posts;
            }
        }

        //WPML
        $post = Wpil_Base::getPost();
        $same_language_posts = array();
        $multi_lang = false;
        if ($post->type == 'post') {
            if (Wpil_Settings::translation_enabled()) {
                $multi_lang = true;
                $same_language_posts = Wpil_Post::getSameLanguagePosts($post->id);
            }
        }

        $statuses_query = Wpil_Query::postStatuses();

        // create the array of posts
        $posts = array();

        // create the string of excluded posts
        $excluded_posts = implode(',', $excluded_posts);

        // if the user is age limiting the posts, get the query limit string
        $age_query = Wpil_Query::getPostDateQueryLimit();

        // if there are ids to process
        if(!empty($same_language_posts) && $multi_lang){
            // chunk the ids to query so we don't ask for too many
            $id_batches = array_chunk($same_language_posts, 2000);
            foreach($id_batches as $batch){
                $include = " AND ID IN (" . implode(', ', $batch) . ") ";
                $batch_ids = $wpdb->get_results("SELECT `ID` FROM {$wpdb->posts} WHERE post_type IN ('{$post_types}') $statuses_query AND ID NOT IN ({$excluded_posts}) {$age_query} {$selected_terms} {$post_content} $include ORDER BY ID DESC");

                if(!empty($batch_ids)){
                    $posts = array_merge($posts, $batch_ids);
                }
            }
        }elseif(empty($multi_lang)){
            $posts = $wpdb->get_results("SELECT `ID` FROM {$wpdb->posts} WHERE post_type IN ('{$post_types}') $statuses_query AND ID NOT IN ({$excluded_posts}) {$age_query} {$selected_terms} {$post_content} ORDER BY ID DESC");
        }

        if(!empty($include_ids)){
            foreach($include_ids as $included){
                $posts[] = (object)array('ID' => $included);
            }
        }

        // get any posts from alternate storage locations
        $posts = self::getPostsFromAlternateLocations($posts, $keyword, $excluded_posts);

        // if there are posts found, remove any duplicate ids and posts hidden by redirects
        if(!empty($posts)){
            $redirected = Wpil_Settings::getRedirectedPosts(true);
            $post_ids = array();
            foreach($posts as $post){
                if(!isset($redirected[$post->ID])){
                    $post_ids[$post->ID] = $post;
                }
            }

            $posts = array_values($post_ids);
        }

        return $posts;
    }

    public static function getInboundPostContent($keyword)
    {
        global $wpdb;

        //get unique words from post title
        $words = (!self::isAsianText()) ? Wpil_Word::getWords($keyword) : mb_str_split(trim($keyword));
        $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
        $words = array_values(array_filter($words));

        if (empty($words)) {
            return '';
        }

        $post_content = "";
        foreach($words as $ind => $word){
            $escaped = "%" . $wpdb->esc_like($word) . "%";
            if($ind < 1){
                $post_content .= $wpdb->prepare("AND (post_content LIKE %s", $escaped);
            }else{
                $post_content .= $wpdb->prepare(" OR post_content LIKE %s", $escaped);
            }
        }

        // if we have a post content query string
        if(!empty($post_content)){
            // add the closing bracket for the end of the AND
            $post_content .= ')';
        }

        return $post_content;
    }

    /**
     * Gets the posts that store content in locations other than the post_content.
     * Most page builders update post_content as a fallback measure, so we can typically get the content that way.
     * But some items are unique and don't update the post_content.
     **/
    public static function getPostsFromAlternateLocations($posts, $keyword, $exclude_ids){
        global $wpdb;

        $active_post_types = self::getSuggestionPostTypes();

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', $active_post_types)){

            //get unique words from post title
            $words = Wpil_Word::getWords($keyword);
            $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
            $words = array_filter($words);

            if(!empty($words)){
                $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->postmeta} m WHERE `meta_key` = 'wprm_notes' AND m.post_id NOT IN ($exclude_ids) AND (meta_value LIKE '%" . implode("%' OR meta_value LIKE '%", $words) . "%')");

                if(!empty($results)){
                    $posts = array_merge($posts, $results);
                }
            }
        }

        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            //get unique words from post title
            $words = Wpil_Word::getWords($keyword);
            $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
            $words = array_filter($words);

            // get the age query if the user is limiting the range for linking
            $age_string = Wpil_Query::getPostDateQueryLimit('p');

            if(!empty($words)){
                $post_types_p = Wpil_Query::postTypes('p');
                $statuses_query_p = Wpil_Query::postStatuses('p');
                $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'gdlr-core-page-builder' {$age_string} {$post_types_p} {$statuses_query_p} AND (meta_value LIKE '%" . implode("%' OR meta_value LIKE '%", $words) . "%')");

                if(!empty($results)){
                    $posts = array_merge($posts, $results);
                }
            }
        }

        // if Oxygen is active
        if(defined('CT_PLUGIN_MAIN_FILE')){
            //get unique words from post title
            $words = Wpil_Word::getWords($keyword);
            $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
            $words = array_filter($words);

            // get the age query if the user is limiting the range for linking
            $age_string = Wpil_Query::getPostDateQueryLimit('p');

            if(!empty($words)){
                $post_types_p = Wpil_Query::postTypes('p');
                $statuses_query_p = Wpil_Query::postStatuses('p');
                $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'ct_builder_shortcodes' {$age_string} {$post_types_p} {$statuses_query_p} AND (meta_value LIKE '%" . implode("%' OR meta_value LIKE '%", $words) . "%')");

                if(!empty($results)){
                    $posts = array_merge($posts, $results);
                }
            }
        }

        // if WooCommerce is active
        if(defined('WC_PLUGIN_FILE') && in_array('product', $active_post_types)){
            //get unique words from post title
            $words = Wpil_Word::getWords($keyword);
            $words = Wpil_Word::cleanIgnoreWords(array_unique($words));
            $words = array_filter($words);

            // get the age query if the user is limiting the range for linking
            $age_string = Wpil_Query::getPostDateQueryLimit('p');

            if(!empty($words)){
                $results = $wpdb->get_results("SELECT DISTINCT ID FROM {$wpdb->posts} p WHERE p.post_type = 'product' {$age_string} AND (p.post_excerpt LIKE '%" . implode("%' OR p.post_excerpt LIKE '%", $words) . "%')");

                if(!empty($results)){
                    $posts = array_merge($posts, $results);
                }
            }
        }

        return $posts;
    }

    public static function getKeywordsUrl()
    {
        $url = '';
        if (!empty($_POST['keywords'])) {
            $url = '&keywords=' . str_replace("\n", ";", $_POST['keywords']);
        }

        return $url;
    }

    /**
     * Removes all repeat toplevel suggestions leaving only the best one to be presented to the user.
     * Loops around multiple times to make sure that we're only showing the top one per post on the top level.
     **/
    public static function remove_top_level_suggested_post_repeats($phrases){
        $count = 0;
        do{
            // sort the top-level suggestions so we can tell which suggestion has the highest score between the different phrases so we can remove the lower ranking ones
            $top_level_posts = array();
            foreach($phrases as $key => $phrase){
                if(empty($phrase->suggestions)){
                    unset($phrases[$key]);
                    continue;
                }

                $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;
                $tk_match = (isset($phrase->suggestions[0]->passed_target_keywords) && !empty($phrase->suggestions[0]->passed_target_keywords)) ? true: false;
                $top_level_posts[$post_key][] = (object)array('key' => $key, 'total_score' => $phrase->suggestions[0]->total_score, 'target_match' => $tk_match);
            }

            // sort the top-level posts so we can find the best suggestion for each phrase and build the list of suggestions to remove
            $remove_suggestions = array();
            foreach($top_level_posts as $post_key => $dat){
                // skip suggestions that are the only ones for their posts
                if(count($dat) < 2){
                    continue;
                }

                usort($top_level_posts[$post_key], function ($a, $b) {
                    if ($a->total_score == $b->total_score) {
                        return 0;
                    }
                    return ($a->total_score > $b->total_score) ? -1 : 1;
                });

                // remove the top suggestion from the list of suggestions to remove and make a list of the phrase keys
                $remove_suggestions[$post_key] = array_map(function($var){ return $var->key; }, array_slice($top_level_posts[$post_key], 1));
            }

            // go over the phrases and remove any suggested links that are not the top-level ones
            foreach($phrases as $key => $phrase){
                $post_key = ($phrase->suggestions[0]->post->type=='term'?'cat':'') . $phrase->suggestions[0]->post->id;

                // skip any that aren't on the list
                if(!isset($remove_suggestions[$post_key])){
                    continue;
                }

                // if the phrase is listed in the remove list
                if(in_array($key, $remove_suggestions[$post_key], true)){

                    // remove the suggestion
                    $suggestions = array_slice($phrase->suggestions, 1);
                    // if this is the only suggestion
                    if(empty($suggestions)){
                        // remove the phrase from the list to consider
                        unset($phrases[$key]);
                    }else{
                        // if it wasn't the only suggestion for the phrase, update the list of suggestions
                        $phrases[$key]->suggestions = $suggestions;
                    }
                }
            }

            // exit if we've gotten into endless looping
            if($count > 100){ // todo: create some kind of "Link Whisper System Instability Report" that will tell users if the plugin is getting wasty or caught in loops or anything like that.
                break;
            }
            $count++;
        }while(!empty($remove_suggestions));

        return $phrases;
    }

    /**
     * Delete phrases with sugeestion point < 3
     *
     * @param $phrases
     * @return array
     */
    public static function deleteWeakPhrases($phrases)
    {
        if (count($phrases) <= 10) {
            return $phrases;
        }

        $three_and_more = 0;
        foreach ($phrases as $key => $phrase) {
            if(!isset($phrase->suggestions[0])){
                unset($phrases[$key]);
                continue;
            }
            if ($phrase->suggestions[0]->post_score >=3) {
                $three_and_more++;
            }
        }

        if ($three_and_more < 10) {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    if ($three_and_more < 10) {
                        $three_and_more++;
                    } else {
                        unset($phrases[$key]);
                    }
                }
            }
        } else {
            foreach ($phrases as $key => $phrase) {
                if ($phrase->suggestions[0]->post_score < 3) {
                    unset($phrases[$key]);
                }
            }
        }

        return $phrases;
    }

    /**
     * Get post IDs from inbound custom fields
     *
     * @param $keyword
     * @return array
     */
    public static function getInboundCustomFields($keyword, $term_taxonomy_ids = array())
    {
        global $wpdb;
        $posts = [];

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $posts;
        }

        // get the age query if the user is limiting the range for linking
        $age_string = Wpil_Query::getPostDateQueryLimit('p');

        $post_content = str_replace('post_content', 'm.meta_value', self::getInboundPostContent($keyword));
        $statuses_query = Wpil_Query::postStatuses('p');
        $post_types = implode("','", self::getSuggestionPostTypes());
        $fields = Wpil_Post::getAllCustomFields();
        $selected_terms = (!empty($term_taxonomy_ids) && is_array($term_taxonomy_ids)) ? " AND p.ID in (select object_id from {$wpdb->term_relationships} where term_taxonomy_id in (" . implode(',', $term_taxonomy_ids) . ")) ": '';
        if(count($fields) < 100){
            $fields = !empty($fields) ? " AND m.meta_key in ('" . implode("', '", $fields) . "') " : '';
            $posts_query = $wpdb->get_results("SELECT m.post_id FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID WHERE p.post_type IN ('{$post_types}') {$age_string} {$selected_terms} $fields $post_content $statuses_query");
            foreach ($posts_query as $post) {
                $posts[] = $post->post_id;
            }
        }else{
            // chunk the fields to query so we don't ask for too many at once
            $field_batches = array_chunk($fields, 100);
            foreach($field_batches as $batch){
                $fields = !empty($batch) ? " AND m.meta_key in ('" . implode("', '", $batch) . "') " : '';
                $posts_query = $wpdb->get_results("SELECT m.post_id FROM {$wpdb->prefix}postmeta m INNER JOIN {$wpdb->prefix}posts p ON m.post_id = p.ID WHERE p.post_type IN ('{$post_types}') {$age_string} {$selected_terms}  $fields $post_content $statuses_query");
                foreach ($posts_query as $post) {
                    $posts[] = $post->post_id;
                }
            }
        }

        return $posts;
    }

    /**
     * Group inbound suggestions by post ID
     *
     * @param $phrases
     * @return array
     */
    public static function getInboundGroups($phrases)
    {
        $groups = [];
        foreach ($phrases as $phrase) {
            $post_id = $phrase->suggestions[0]->post->id;
            if (empty($groups[$post_id])) {
                $groups[$post_id] = [$phrase];
            } else {
                $groups[$post_id][] = $phrase;
            }
        }

        return $groups;
    }

    /**
     * Merges sentences that have been split on styling tags back into a single sentence
     **/
    public static function mergeSplitSentenceTags($list = array()){
        if(empty($list)){
            return $list;
        }

        $tags = array(
            'b' => array('opening' => array('<b>'), 'closing' => array('</b>', '<\/b>')),
            'strong' => array('opening' => array('<strong>'), 'closing' => array('</strong>', '<\/strong>'))
        );

        $updated_list = array();
        $skip_until = false;
        foreach($list as $key => $item){
            if($skip_until !== false && $skip_until >= $key){
                continue;
            }

            $sentence = '';
            // look over the tags
            foreach($tags as $tag => $dat){
                // if the string has an opening tab, but not a closing one
                if(self::hasStringFromArray($item, $dat['opening']) && !self::hasStringFromArray($item, $dat['closing'])){
                    // find a string that contains the closing tag
                    foreach(array_slice($list, $key, null, true) as $sub_key => $sub_item){
                        $sentence .= $sub_item;
                        $skip_until = $sub_key;
                        if(self::hasStringFromArray($sub_item, $dat['closing'])){
                            break 2;
                        }
                    }
                }
            }

            // add the sentence to the list
            $updated_list[$key] = !empty($sentence) ? $sentence: $item;
        }

        return $updated_list;
    }

    /**
     * Searches a string for the presence of any strings from an array.
     * 
     * @return bool Returns true if the string contains a string from the search array. Returns false if the string does not contain a search string or is empty.
     **/
    public static function hasStringFromArray($string = '', $search = array()){
        if(empty($string) || empty($search)){
            return false;
        }

        foreach($search as $s){
            // if the string contains the searched string, return true
            if(false !== strpos($string, $s)){
                return true;
            }
        }

        return false;
    }

    /**
     * Remove empty sentences from the list
     *
     * @param $sentences
     */
    public static function removeEmptySentences(&$sentences, $with_links = false)
    {
        $prev_key = null;
        foreach ($sentences as $key => $sentence)
        {
            //Remove text from alt and title attributes
            $pos = 0;
            if ($prev_key && ($pos = strpos($prev_sentence, 'alt="') !== false || $pos = strpos($prev_sentence, 'title="') !== false)) {
                if (isset($sentences[$prev_key]) && strpos($sentences[$prev_key], '"', $pos) == false) {
                    $pos = strpos($sentence, '"');
                    if ($pos !== false) {
                        $sentences[$key] = substr($sentence, $pos + 1);
                    } else {
                        unset ($sentences[$key]);
                    }
                }
            }
            $prev_sentence = $sentence;

            $endings = ['</h1>', '</h2>', '</h3>'];

            if (!$with_links) {
                $endings[] = '</a>';
            }

            if (in_array(trim($sentence), $endings) && $prev_key) {
                unset($sentences[$prev_key]);
            }
            if (empty(trim(strip_tags($sentence)))) {
                unset($sentences[$key]);
            }

            if (substr($sentence, 0, 5) == '<!-- ' && substr($sentence, 0, -4) == ' -->') {
                unset($sentences[$key]);
            }

            if('&nbsp;' === $sentence){
                unset($sentences[$key]);
            }

            $prev_key = $key;
        }
    }

    /**
     * Remove tags from the beginning and the ending of the sentence
     *
     * @param $sentences
     */
    public static function trimTags(&$sentences, $with_links = false)
    {
        foreach ($sentences as $key => $sentence)
        {
            if (strpos($sentence, '<h') !== false || strpos($sentence, '</h') !== false) {
                unset($sentences[$key]);
                continue;
            }

            if (!$with_links && (
                strpos($sentence, '<a ') !== false || strpos($sentence, '</a>') !== false ||
                strpos($sentence, '<ta ') !== false || strpos($sentence, '</ta>') !== false)
            ){
                unset($sentences[$key]);
                continue;
            }

            if (substr_count($sentence, '<a ') >  substr_count($sentence, '</a>')
            ) {
                // check and see if we've split the anchor by mistake
                if( isset($sentences[$key + 1]) && // if there's a sentence after this one
                    substr_count($sentence . $sentences[$key + 1], '<a ') === substr_count($sentence . $sentences[$key + 1], '</a>') // and adding that sentence to this one equalizes the tag count
                ){
                    // update the next sentence with this full one so we can process it in it's entirety
                    $sentences[$key + 1] = $sentence . $sentences[$key + 1];
                }

                // then unset the current sentence and skip to the next one
                unset($sentences[$key]);
                continue;
            }

            if (substr_count($sentence, '<ta ') >  substr_count($sentence, '</ta>')
            ) {
                // check and see if we've split the anchor by mistake
                if( isset($sentences[$key + 1]) && // if there's a sentence after this one
                    substr_count($sentence . $sentences[$key + 1], '<ta ') === substr_count($sentence . $sentences[$key + 1], '</ta>') // and adding that sentence to this one equalizes the tag count
                ){
                    // update the next sentence with this full one so we can process it in it's entirety
                    $sentences[$key + 1] = $sentence . $sentences[$key + 1];
                }

                // then unset the current sentence and skip to the next one
                unset($sentences[$key]);
                continue;
            }

            $sentence = trim($sentences[$key]);
            while (substr($sentence, 0, 1) == '<' || substr($sentence, 0, 1) == '[') {
                $end_char = substr($sentence, 0, 1) == '<' ? '>' : ']';
                $end = strpos($sentence, $end_char);
                $tag = substr($sentence, 0, $end + 1);
                if (in_array($tag, ['<b>', '<i>', '<u>', '<strong>'])) {
                    break;
                }
                if (substr($tag, 0, 3) == '<a ' || substr($tag, 0, 4) == '<ta ') {
                    break;
                }
                $sentence = trim(substr($sentence, $end + 1));
            }

            while (substr($sentence, -1) == '>' || substr($sentence, -1) == ']') {
                $start_char = substr($sentence, -1) == '>' ? '<' : '[';
                $start = strrpos($sentence, $start_char);
                $tag = substr($sentence, $start);
                if (in_array($tag, ['</b>', '</i>', '</u>', '</strong>', '</a>', '</ta>'])) {
                    break;
                }
                $sentence = trim(substr($sentence, 0, $start));
            }

            $sentences[$key] = $sentence;
        }
    }

    /**
     * Generate subquery to search posts or products only with same categories
     *
     * @param $post
     * @return string
     */
    public static function getTitleQueryExclude($post)
    {
        global $wpdb;

        $exclude = "";
        $linked_posts = array();
        if(get_option('wpil_prevent_two_way_linking', false)){
            $linked_posts = Wpil_Post::getLinkedPostIDs($post, false);
        }
        if ($post->type == 'post') {
            $redirected = Wpil_Settings::getRedirectedPosts();  // ignore any posts that are hidden by redirects
            $redirected[] = $post->id;                          // ignore the current post
            foreach($linked_posts as $link){
                if(!empty($link->post) && $link->post->type === 'post'){
                    $redirected[] = $link->post->id;
                }
            }
            $redirected = implode(', ', $redirected);
            $exclude .= " AND ID NOT IN ({$redirected}) ";
        }

        if (!empty(Wpil_Settings::get_suggestion_filter('same_category'))) {
            if ($post->type === 'post') {
                if (!empty(Wpil_Settings::get_suggestion_filter('selected_category'))) {
                    $categories = self::get_selected_categories();
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(get_taxonomy($tax)->hierarchical){
                            $query_taxes[] = $tax;
                        }
                    }
                    $categories = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'tt_ids']);
                }
                foreach($linked_posts as $link){
                    if(!empty($link->post) && $link->post->type === 'term'){
                        $categories[] = $link->post->id;
                    }
                }
                $categories = count($categories) ? implode(',', $categories) : "''";
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($categories))";
            }
        }

        if (!empty(Wpil_Settings::get_suggestion_filter('same_tag'))) {
            if ($post->type === 'post') {
                if (!empty(Wpil_Settings::get_suggestion_filter('selected_tag'))) {
                    $tags = self::get_selected_tags();
                } else {
                    $taxes = get_object_taxonomies(get_post($post->id));
                    $query_taxes = array();
                    foreach($taxes as $tax){
                        if(empty(get_taxonomy($tax)->hierarchical)){
                            $query_taxes[] = $tax;
                        }
                    }
                    $tags = wp_get_object_terms($post->id, $query_taxes, ['fields' => 'tt_ids']);
                }
                foreach($linked_posts as $link){
                    if(!empty($link->post) && $link->post->type === 'term'){
                        $tags[] = $link->post->id;
                    }
                }
                $tags = count($tags) ? implode(',', $tags) : "''";
                $exclude .= " AND ID in (select object_id from {$wpdb->prefix}term_relationships where term_taxonomy_id in ($tags))";
            }
        }

        return $exclude;
    }

    /**
     * Gets the post types for use in making suggestions.
     * Looks to see if the user has selected any post types from the suggestion panel.
     * If he has, then it returns the post types the user has selected. Otherwise, it returns the post types from the LW Settings
     **/
    public static function getSuggestionPostTypes(){

        // get the post types from the settings
        $post_types = Wpil_Settings::getPostTypes();

        // if the user has selected post types from the suggestion panel
        if( Wpil_Settings::get_suggestion_filter('select_post_types'))
        {
            // obtain the selected post types
            $user_selected = Wpil_Settings::get_suggestion_filter('selected_post_types');
            if(!empty($user_selected)){
                // check to make sure the supplied post types are ones that are in the settings
                $potential_types = array_intersect($post_types, $user_selected);

                // if there are post types, set the current post types for the selected ones
                if(!empty($potential_types)){
                    $post_types = $potential_types;
                }
            }
        }

        return $post_types;
    }

    /**
     * Compresses and base64's the given data so it can be saved in the db.
     * 
     * @param string $data The data to be compressed
     * @return null|string Returns a string of compressed and base64 encoded data 
     **/
    public static function compress($data = false){
        // first serialize the data
        $data = serialize($data);

        // if zlib is available
        if(extension_loaded('zlib')){
            // use it to compress the data
            $data = gzdeflate($data);
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to compress the data
            $data = bzcompress($data);
        }

        // now base64 and return the (hopefully) compressed data
        return base64_encode($data);
    }

    /**
     * Decompresses stored data that was compressed with compress.
     * 
     * @param string $data The data to be decompressed
     * @return mixed $data 
     **/
    public static function decompress($data){
        if(empty($data) || !is_string($data)){
            return $data;
        }

        // first un-64 the data
        $data = base64_decode($data);

        // then determine what our flavor of encoding is and decode the data
        // if zlib is available
        if(extension_loaded('zlib')){
            // use it to decompress the data
            $data = gzinflate($data);
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to decompress the data
            $data = bzdecompress($data);
        }

        // and return our unserialized and hopefully de-compressed data
        return unserialize($data);
    }

    /**
     * Gets the phrases from the current post for use in outbound linking suggestions.
     * Caches the phrase data so subsequent requests are faster
     * 
     * @param $post The post object we're getting the phrases from.
     * @param int $process_key The ajax processing key for the current process.
     * @return array $phrases The phrases from the given post
     **/
    public static function getOutboundPhrases($post, $process_key){
        // try getting cached phrase data
        $phrases = get_transient('wpil_processed_phrases_' . $process_key);

        // if there aren't any phrases, process them now
        if(empty($phrases)){
            $phrases = self::getPhrases($post->getContent());

            //divide text to phrases
            foreach ($phrases as $key_phrase => &$phrase) {
                // replace any punctuation in the text and lower the string
                $text = Wpil_Word::strtolower(Wpil_Word::removeEndings($phrase->text, ['.','!','?','\'',':','"']));

                //get array of unique sentence words cleared from ignore phrases
                if (!empty($_REQUEST['keywords'])) {
                    $sentence = trim(preg_replace('/\s+/', ' ', $text));
                    $words_uniq = Wpil_Word::getWords($sentence);
                } else {
                    $words_uniq = Wpil_Word::cleanFromIgnorePhrases($text);
                }

                // remove words less than 3 letters long and stem the words
                foreach($words_uniq as $key => $word){
                    if(strlen($word) < 3){
                        unset($words_uniq[$key]);
                        continue;
                    }

                    $words_uniq[$key] = Wpil_Stemmer::Stem($word);
                }

                $phrase->words_uniq = $words_uniq;
            }

            $save_phrases = self::compress($phrases);
            set_transient('wpil_processed_phrases_' . $process_key, $save_phrases, MINUTE_IN_SECONDS * 15);
            reset($phrases);
            unset($save_phrases);
        }else{
            $phrases = self::decompress($phrases);
        }

        return $phrases;
    }

    /**
     * Gets the categories that are assigned to the current post.
     * If we're doing an outbound scan, it caches the cat ids so they can be pulled up without a query
     **/
    public static function getSameCategories($post, $process_key = 0, $is_outbound = false){
        global $wpdb;

        if($is_outbound){
            $cats = get_transient('wpil_post_same_categories_' . $process_key);
            if(empty($cats) && !is_array($cats)){
                $cats = $wpdb->get_results("SELECT object_id FROM {$wpdb->prefix}term_relationships where term_taxonomy_id in (SELECT r.term_taxonomy_id FROM {$wpdb->prefix}term_relationships r inner join {$wpdb->prefix}term_taxonomy t on t.term_taxonomy_id = r.term_taxonomy_id where r.object_id = {$post->id} and t.taxonomy = 'category')");
            
                if(empty($cats)){
                    $cats = array();
                }

                set_transient('wpil_post_same_categories_' . $process_key, $cats, MINUTE_IN_SECONDS * 15);
            }
            
        }else{
            $cats = $wpdb->get_results("SELECT object_id FROM {$wpdb->prefix}term_relationships where term_taxonomy_id in (SELECT r.term_taxonomy_id FROM {$wpdb->prefix}term_relationships r inner join {$wpdb->prefix}term_taxonomy t on t.term_taxonomy_id = r.term_taxonomy_id where r.object_id = {$post->id} and t.taxonomy = 'category')");
        }

        return $cats;
    }

    /**
     * Checks to see if the current sentence contains any of the post's keywords.
     * Performs a strict check and a loose keyword check.
     * The strict check examines the sentence to see if there's an exact match of the keywords in the sentence.
     * The loose checks sees if the sentence contains any matching words from the keyword in any order.
     * 
     * @param string $sentence The sentence to examine
     * @param array $target_keywords The processed keywords to check for
     * 
     * @return object|bool Returns the keyword if the sentence contains any of the keywords, False if the sentence doesn't contain any keywords.
     **/
    public static function checkSentenceForKeywords($sentence = '', $target_keywords = array(), $unique_keywords = array(), $more_specific_keywords = array()){
        $stemmed_sentence = Wpil_Word::getStemmedSentence($sentence);
        $loose_match_count = false; // get_option('wpil_loose_keyword_match_count', 0);

        // if we're supposed to check for loose matching of the keywords
        if(!empty($loose_match_count) && !empty($unique_keywords)){
            // find out how many times the keywords show up in the sentence
            $words = array_flip(explode(' ', $stemmed_sentence));
            $unique_keywords = array_flip($unique_keywords);

            $matches = array_intersect_key($unique_keywords, $words);

            // if the count is more than what the user has set
            if(count($matches) > $loose_match_count){
                // report that the sentence contains the keywords
                return true;
            }
        }

        foreach($target_keywords as $keyword){
            // skip the keyword if it's only 2 chars long
            if(3 > strlen($keyword->keywords)){
                continue;
            }

            // if the keyword is in the phrase
            if(false !== strpos($stemmed_sentence, $keyword->stemmed)){
                // check if it participates in a more specific keyword
                if(isset($more_specific_keywords[$keyword->stemmed])){
                    foreach($more_specific_keywords[$keyword->stemmed] as $dat){
                        // if it does, skip to the next keyword
                        if(false !== mb_strpos($stemmed_sentence, $dat->stemmed)){
                            continue 2;
                        }
                    }
                }
                return true;
            }

            // if the keyword is a post content keyword, 
            // check if the there's at least 2 consecutively matching words between the sentence and the keyword
            if($keyword->keyword_type === 'post-keyword'){
                $k_words = explode(' ', $keyword->keywords);
                $s_words = explode(' ', $stemmed_sentence);

                foreach($k_words as $key => $word){
                    // see if the current word is in the stemmed sentence
                    $pos = array_search($word, $s_words, true);

                    // if it is, see if the next word is the same for both strings
                    if( false !== $pos && 
                        isset($s_words[$pos + 1]) &&
                        isset($k_words[$key + 1]) &&
                        ($s_words[$pos + 1] === $k_words[$key + 1])
                    ){
                        // if it is, return true
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clears the cached data when the suggestion processing is complete
     * 
     * @param int $processing_key The id of the suggestion processing run.
     **/
    public static function clearSuggestionProcessingCache($processing_key = 0, $post_id = 0){
        // clear the suggestions
        delete_transient('wpil_post_suggestions_' . $processing_key);
        // clear the inbound suggestion ids
        delete_transient('wpil_inbound_suggested_post_ids_' . $processing_key);
        // clear the keyword cache
        delete_transient('wpil_post_suggestions_keywords_' . $processing_key);
        // clear the unique keyword cache
        delete_transient('wpil_post_suggestions_unique_keywords_' . $processing_key);
        // clear any cached inbound links cache
        delete_transient('wpil_stored_post_internal_inbound_links_' . $post_id);
        // clear the processed phrase cache
        delete_transient('wpil_processed_phrases_' . $processing_key);
        // clear the post link cache
        delete_transient('wpil_external_post_link_index_' . $processing_key);
        // clear the outbound post link cache
        delete_transient('wpil_outbound_post_links' . $processing_key);
        // clear the post category cache
        delete_transient('wpil_post_same_categories_' . $processing_key);
    }

    /**
     * Checks to see if we're dealing with Asian caligraphics
     * Todo: Not currently active
     **/
    public static function isAsianText(){
        return false;
    }

    /**
     * Gets the currently selected categories for suggestion matching.
     * Pulls data from the $_POST variable or the stored filtering settings
     * @return array
     **/
    public static function get_selected_categories(){
        return Wpil_Settings::get_suggestion_filter('selected_category');
    }

    /**
     * Gets the currently selected tags for suggestion matching.
     * Pulls data from the $_POST variable or the stored filtering settings
     * @return array
     **/
    public static function get_selected_tags(){
        return Wpil_Settings::get_suggestion_filter('selected_tag');
    }
}
