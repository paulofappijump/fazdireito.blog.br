<?php

/**
 * Work with terms
 */
class Wpil_Term
{
    /**
     * Register services
     */
    public function register()
    {
        foreach (Wpil_Settings::getTermTypes() as $term) {
            add_action($term . '_add_form_fields', [$this, 'showTermSuggestions']);
            add_action($term . '_edit_form', [$this, 'showTermSuggestions']);
            add_action('edited_' . $term, [$this, 'addLinksToTerm']);
            add_action('edited_' . $term, ['Wpil_TargetKeyword', 'update_keywords_on_term_save']);
            add_action($term . '_add_form_fields', [$this, 'showTargetKeywords']);
            add_action($term . '_edit_form', [$this, 'showTargetKeywords']);
            // check the term link counts once were sure there's no more link processing to do
            add_action('saved_' . $term, [$this, 'updateTermStats'], 10, 3);
        }
    }

    /**
     * Show suggestions on term page
     */
    public static function showTermSuggestions()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Wpil_Settings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        $manually_trigger_suggestions = !empty(get_option('wpil_manually_trigger_suggestions', false));

        // exit if the term has been ignored
        $completely_ignored = Wpil_Settings::get_completely_ignored_pages();
        if(!empty($completely_ignored) && in_array('term_' . $term_id, $completely_ignored, true)){
            return;
        }

        ?>
        <div id="wpil_link-articles" class="postbox">
            <h2 class="hndle no-drag"><span><?php _e('Link Whisper Suggested Links', 'wpil'); ?></span></h2>
            <div class="inside">
                <?php include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_v2.php';?>
            </div>
        </div>
        <?php
    }

    /**
     * Show target keywords on term page
     */
    public static function showTargetKeywords()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Wpil_Settings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        $post = new Wpil_Model_Post($term_id, 'term');

        // exit if the term has been ignored
        $completely_ignored = Wpil_Settings::get_completely_ignored_pages();
        if(!empty($completely_ignored) && in_array($post->type . '_' . $post->id, $completely_ignored, true)){
            return;
        }

        $keywords = Wpil_TargetKeyword::get_keywords_by_post_ids($term_id, 'term');
        $keyword_sources = Wpil_TargetKeyword::get_active_keyword_sources();
        $is_metabox = true;
        ?>
        <div id="wpil_target-keywords" class="postbox ">
            <h2 class="hndle no-drag"><span><?php _e('Link Whisper Target Keywords', 'wpil'); ?></span></h2>
            <div class="inside"><?php
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/target_keyword_list.php';
            ?>
            </div>
        </div>
        <?php
    }
    /**
     * Add links to term description on term update
     *
     * @param $term_id
     */
    public static function addLinksToTerm($term_id)
    {
        global $wpdb;

        //get links
        $meta = get_term_meta($term_id,'wpil_links', true);

        if (!empty($meta)) {
            $description = term_description($term_id);

            //add links to the term description
            foreach ($meta as $link) {
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                $description = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $description, 1);
            }

            //update term description
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}term_taxonomy SET description = %s WHERE term_id = {$term_id} AND description != ''", $description));

            // add links to meta field content areas
            $description .= self::addLinkToMetaContent($term_id);
            // add links to the ACF fields
            $description .= self::addLinkToAdvancedCustomFields($term_id);

            //delete links from DB
            delete_term_meta($term_id, 'wpil_links'); // we can delete the meta at this pointe because there's no pagebuilders that support terms (that we support anywhay)

            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                //update the link table
                $term = new Wpil_Model_Post($term_id, 'term');
                Wpil_Report::update_post_in_link_table($term->setContent($description));
            }
        }

        if (get_option('wpil_post_procession', 0) < (time() - 300)) { // checking if links are being inserted by the autolinker so we don't wind up in a loop.
            $term = new Wpil_Model_Post($term_id, 'term');
            Wpil_Keyword::addKeywordsToPost($term);
            Wpil_URLChanger::replacePostURLs($term);
        }
    }

    /**
     * Updates the term's linking stats after the link adding is completed elsewhere
     **/
    public static function updateTermStats($term_id, $tt_id, $updated){
        $term = new Wpil_Model_Post($term_id, 'term');
        if(WPIL_STATUS_LINK_TABLE_EXISTS && Wpil_Report::stored_link_content_changed($term)){
            // get the fresh term content for the benefit of the descendent methods
            $term->getFreshContent();
            // update the links stored in the link table
            Wpil_Report::update_post_in_link_table($term);
            // update the meta data for the term
            Wpil_Report::statUpdate($term, true);
            // and update the link counts for the posts that this one links to
            Wpil_Report::updateReportInternallyLinkedPosts($term);
        }
    }

    /**
     * Adds links to the term's meta fields
     **/
    public static function addLinkToMetaContent($term_id){
        $meta = get_term_meta($term_id, 'wpil_links', true);

        if (!empty($meta)) {
            $fields = Wpil_Post::getMetaContentFieldList('term');
            if(!empty($fields)){
                foreach($fields as $field){
                    if($content = get_term_meta($term_id, $field, true)){
                        foreach($meta as $link){
                            if(strpos($content, $link['sentence']) !== false){
                                $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                                Wpil_Post::insertLink($content, $link['sentence'], $changed_sentence, $force_insert);
                            }
                        }
                        update_term_meta($term_id, $field, $content);
                    }
                }
            }

            /**
             * Add the links to any custom data fields the customer may have
             * @param int $term_id
             * @param string $post_type (post|term)
             * @param array $meta
             **/
            do_action('wpil_meta_content_data_add_link', $term_id, 'term', $meta);
        }
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList($term_id)
    {
        global $wpdb;

        $fields = [];

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $fields;
        }

        // get any ACF fields the user has ignored
        $ignored_fields = Wpil_Settings::getIgnoredACFFields();
        $fields_query = $wpdb->get_results("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->termmeta} WHERE term_id = $term_id AND meta_value LIKE 'field_%' AND SUBSTR(meta_key, 2) != ''");
        foreach ($fields_query as $field) {
            $name = trim($field->name);
            if(in_array($name, $ignored_fields, true)){
                continue;
            }

            if ($name) {
                $fields[] = $field->name;
            }
        }

        return $fields;
    }

    /**
     * Add link to the content in advanced custom fields
     *
     * @param $link
     * @param $post
     */
    public static function addLinkToAdvancedCustomFields($term_id)
    {
        // don't save the data if this is the result of using wp_update_post // there's no form submission, so $_POST will be empty
        if(empty($_POST)){
            return;
        }

        $meta = get_term_meta($term_id, 'wpil_links', true);

        if (!empty($meta)) {
            foreach ($meta as $link) {
                $fields = self::getAdvancedCustomFieldsList($term_id);
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        if ($content = get_term_meta($term_id, $field, true)) {
                            if (is_string($content) && strpos($content, $link['sentence']) !== false) {
                                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                                $content = preg_replace('/' . preg_quote($link['sentence'], '/') . '/i', $changed_sentence, $content, 1);
                                update_term_meta($term_id, $field, $content);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get category or tag by slug.
     * Works for all registered taxonomies
     *
     * @param $slug
     * @param $url (Optional) The URL that we're trying to pull info from
     * @return WP_Term
     */
    public static function getTermBySlug($slug, $url = '')
    {
        global $wp_rewrite;

        if(empty($slug) || is_int($slug) || is_array($slug)){
            return false;
        }

        $taxonomies = get_taxonomies();

        if(empty($taxonomies)){
            return false;
        }

        $taxonomies = array_values($taxonomies);

        $args = array(
            'get'                    => 'all',
            'slug'                   => $slug,
            'taxonomy'               => $taxonomies,
            'update_term_meta_cache' => false,
            'orderby'                => 'none',
            'suppress_filter'        => true,
        );

        $term = get_terms( $args );

        if(empty($term) || is_a($term, 'Wp_Error') || !is_array($term)){
            return false;
        }

        // if we've found more than one term and we have the source link
        if(count($term) > 1 && !empty($url)){
            // try to see if we can nail down which one the link belongs to
            foreach($term as $term_obj){
                $perma_struct = $wp_rewrite->get_extra_permastruct($term_obj->taxonomy);
                if(!empty($perma_struct)){
                    // build a testing version of the archive path
                    $sample_path = str_replace('%' . $term_obj->taxonomy . '%', $slug, $perma_struct);

                    // if the url is part of the supplied link
                    if(false !== strpos($url, $sample_path)){
                        // assume that this term is the one that we're looking for
                        $term = array($term_obj); // wrap in array for reset's benefit
                    }
                }
            }
        }


        return reset($term);
    }

    /**
     * Gets all category terms for all active post types
     * 
     * @return array 
     **/
    public static function getAllCategoryTerms(){
        $start = microtime(true);
        $post_types = Wpil_Settings::getPostTypes();
        if(empty($post_types)){
            return false;
        }

        $terms = get_transient('wpil_cached_category_terms');
        if(empty($terms)){

            $skip_terms = array(
                'product_type',
                'product_visibility',
                'product_shipping_class',
            );

            $terms = array();
            foreach($post_types as $type){
                $taxonomies = get_object_taxonomies($type);

                foreach($taxonomies as $taxonomy){
                    if(in_array($taxonomy, $skip_terms)){
                        continue;
                    }

                    $args = array(
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false
                    );
                    $queried_terms = get_terms($args);

                    if(!is_a($terms, 'WP_Error')){
                        foreach($queried_terms as $term){
                            $terms[] = $term;
                        }
                    }
                }
            }

            // sort the terms to find the most used ones
            usort($terms, function($a, $b){
                if($a->count < $b->count){
                    return 1;
                }else if($a->count < $b->count){
                    return -1;
                }else{
                    return 0;
                }
            });

            // _only_ use the top 450 terms to save loading resources for sites that have many, many terms.
            $terms = array_slice($terms, 0, 450);

            // compress the terms to save space
            $terms_to_save = Wpil_Suggestion::compress($terms);

            // cache the terms for 5 minutes
            set_transient('wpil_cached_category_terms', $terms_to_save, MINUTE_IN_SECONDS * 5);
        }else{
            // if there are terms, decompress them
            $terms = Wpil_Suggestion::decompress($terms);
        }

        return $terms;
    }
}
