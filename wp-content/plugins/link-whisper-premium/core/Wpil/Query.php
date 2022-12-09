<?php

/**
 * Work with DB queries
 */
class Wpil_Query
{
    /**
     * Get post statuses query row
     *
     * @param string $table
     * @return string
     */
    public static function postStatuses($table = '')
    {
        $query = "";
        $statuses = Wpil_Settings::getPostStatuses();
        if (!empty($statuses)) {
            $query = " AND " . (!empty($table) ? $table."." : "") . "post_status IN ('" . implode("', '", $statuses) . "') ";
        }

        return $query;
    }

    /**
     * Get post types query row
     *
     * @param string $table
     * @return string
     */
    public static function postTypes($table = '')
    {
        $query = "";
        $post_types = Wpil_Settings::getPostTypes();
        if (!empty($post_types)) {
            $query = " AND " . ((!empty($table)) ? $table . ".post_type" : "`post_type`") . " IN ('" . implode("', '", $post_types) . "') ";
        }

        return $query;
    }

    /**
     * Get term taxonomy query row
     *
     * @param string $table
     * @return string
     */
    public static function taxonomyTypes($table = '')
    {
        $query = "";
        $taxonomies = Wpil_Settings::getTermTypes();
        if (!empty($taxonomies)) {
            $query = " AND taxonomy IN ('" . implode("', '", $taxonomies) . "')";
        }

        return $query;
    }

    /**
     * Get posts IDs for report query
     *
     * @param false $orphaned
     * @return string
     */
    public static function reportPostIds($orphaned = false)
    {
        global $wpdb;

        if ($orphaned) {
            $post_status = self::postStatuses('a');
            $post_types = self::postTypes('a');

            $ids1 = $wpdb->get_col("SELECT a.ID FROM {$wpdb->posts} a LEFT JOIN {$wpdb->postmeta} b ON a.ID = b.post_id WHERE 1=1 {$post_status} {$post_types} AND b.meta_key = 'wpil_sync_report3' AND b.meta_value = '1'");
            $ids2 = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = '0'");
            $ids = array_intersect($ids1, $ids2);

            // remove any links that are on the ignore orphan list
            $ignored = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'post');
            $ids = array_diff($ids, $ignored);

            // also remove any posts that are hidden by redirects
            $redirected = Wpil_Settings::getRedirectedPosts();
            $ids = array_diff($ids, $redirected);
        }

        return !empty($ids) ? " AND p.ID IN (" . implode(',', $ids) . ")" : "";
    }

    /**
     * Get terms IDs for report query
     *
     * @param false $orphaned
     * @return string
     */
    public static function reportTermIds($orphaned = false, $hide_noindex = false)
    {
        global $wpdb;

        $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1'");
        if ($orphaned) {
            $ids = array_intersect($ids, $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = '0'"));
        
            // remove any links that are on the ignore orphan list
            $ignored = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'term');
            $ids = array_diff($ids, $ignored);
        }

        if($orphaned || $hide_noindex){
            //** Remove any noIndex post ids **//
            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                foreach($ids as $key => $id){
                    $term = get_term($id);
                    if(is_a($term, 'WP_Error') || empty(\RankMath\Helper::is_term_indexable($term))){
                        unset($ids[$key]);
                    }
                }
            }

            // if Yoast is active rmeove any ids that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $yoast_taxonomy_data = get_site_option('wpseo_taxonomy_meta');
                if(!empty($yoast_taxonomy_data)){
                    foreach($ids as $key => $id){
                        // if the category has been set to noIndex
                        if( isset($yoast_taxonomy_data[$id]) &&
                            isset($yoast_taxonomy_data[$id]['wpseo_noindex']) && 
                            'noindex' === $yoast_taxonomy_data[$id]['wpseo_noindex'])
                        {
                            // remove the id from the list
                            unset($ids[$key]);
                        }
                    }
                }
            }
        }

        if(!empty($ids)){
            $taxonomies = self::taxonomyTypes();
            $ids2 = implode(',', $ids);
            $ids = array_intersect($ids, $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$ids2}) {$taxonomies}"));
        }

        return implode(',', $ids);
    }

    public static function ignoredPostIds(){
        $post_ids = Wpil_Settings::getAllIgnoredPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'post_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND p.ID NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredTermIds(){
        $post_ids = Wpil_Settings::getAllIgnoredPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'term_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND t.term_id NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredExternalPostIds(){
        $post_ids = Wpil_Settings::getIgnoreExternalPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'post_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND p.ID NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredExternalTermIds(){
        $post_ids = Wpil_Settings::getIgnoreExternalPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'term_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND t.term_id NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function getReportLinksIgnoreQueryStrings(){
        // if the user is hiding the ignored posts
        $ignored = "";
        if(Wpil_Settings::hideIgnoredPosts()){
            // don't count the links from ignored posts
            $posts = Wpil_Settings::getAllIgnoredPosts();
            if(!empty($posts)){
                $post_data = array();
                $term_data = array();
                foreach($posts as $post){
                    $post = explode('_', $post);
                    if($post[0] === 'post'){
                        $post_data[] = $post[1];
                    }else{
                        $term_data[] = $post[1];
                    }
                }

                if(!empty($post_data)){
                    $ignored .= " AND (`post_type` = 'post' AND `post_id` NOT IN (" . implode(', ', $post_data) . "))";
                }

                if(!empty($term_data)){
                    $sign = (!empty($post_data)) ? "OR": "AND";
                    $ignored .= " {$sign} (`post_type` = 'term' AND `post_id` NOT IN (" . implode(', ', $term_data) . "))";
                }

            }
        }

        return $ignored;
    }

    /**
     * Gets the query string for obtaining posts newer than the user's date restrictions
     **/
    public static function getPostDateQueryLimit($table = ''){
        // if the user is age limiting the posts
        $age_limit = get_option('wpil_max_linking_age', 0);
        $age_query = '';
        if(!empty($age_limit)){
            if(function_exists('wp_date')){
                $time_string = wp_date('Y-m-d H:i:s', (time() - ($age_limit * YEAR_IN_SECONDS)));
            }else{
                $time_string = date_i18n('Y-m-d H:i:s', (time() - ($age_limit * YEAR_IN_SECONDS)));
            }
            $col = (!empty($table)) ? ($table . '.post_date_gmt'): '`post_date_gmt`';
            $age_query .= "AND {$col} > '{$time_string}'";
        }

        return $age_query;
    }

    /**
     * Gets a query string for ignoring post ids from all sources. 
     * @param string $table The table variable that we're building the query for
     * @param array $args An array of indexes that we're going to use to get ids to ignore
     * @todo Migrate the term_id getting functionality so it works like the whole system that uses this. (Includes some filter and query changes in the reports)
     **/
    public static function get_all_report_ignored_post_ids($table = '', $args = array()){
        global $wpdb;

        $ids = array();

        if(isset($args['orphaned']) && !empty($args['orphaned'])){
            // remove any links that are on the ignore orphan list
            $ignored = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'post');
            $ids = array_merge($ids, $ignored);

            // also remove any posts that are hidden by redirects
            $redirected = Wpil_Settings::getRedirectedPosts();
            $ids = array_merge($ids, $redirected);
        }

        if( (isset($args['orphaned']) && !empty($args['orphaned'])) || (isset($args['hide_noindex']) && !empty($args['hide_noindex'])) ){
            //** Remove any noIndex post ids **//
            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                $id_string = " `post_id` NOT IN ('" . implode("', '", $ids) . "')";
                $rank_math_meta = $wpdb->get_results("SELECT `post_id`, `meta_value` FROM {$wpdb->postmeta} WHERE {$id_string} AND `meta_key` = 'rank_math_robots'");

                foreach($rank_math_meta as $data){
                    if(false !== strpos($data->meta_value, 'noindex')){ // we can check the unserialized data because Rank Math uses a simple flag like structure to the saved data.
                        // NOTE: if the friends want to include the global no index rules, there's a "is_post_indexable" function that should do it. I went this route because it should be faster on large sites.
                        $ids[] = $data->post_id;
                    }
                }
            }

            // if Yoast is active, remove any posts that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $id_string = " `post_id` NOT IN ('" . implode("', '", $ids) . "')";
                $no_index_ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE $id_string AND meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'");
                $ids = array_merge($ids, $no_index_ids);
            }
        }

        $hide_ignored = Wpil_Settings::hideIgnoredPosts();

        if($hide_ignored){
            $setting_ignored_ids = Wpil_Settings::getAllIgnoredPosts();

            if(!empty($setting_ignored_ids)){
                foreach($setting_ignored_ids as $post_id){
                    if(false !== strpos($post_id, 'post_')){
                        $ids[] = substr($post_id, 5);
                    }
                }
            }
        }

        if(!empty($ids)){
            $ids = array_flip(array_flip($ids)); 
        }

        // if the flag has been set to return the ids outside of a query string
        if(isset($args['return_ids'])){
            // return the ids
            return $ids;
        }

        if(!empty($ids)){
            return " AND " . (!empty($table) ? $table."." : "") . "ID NOT IN ('" . implode(",", $ids) . "') ";
        }
        
        return '';
        // get any 
    }
}
