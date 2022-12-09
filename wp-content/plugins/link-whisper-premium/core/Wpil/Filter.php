<?php

/**
 * Work with table filters
 */
class Wpil_Filter
{
    /**
     * Get links location filter
     *
     * @return bool|string
     */
    public static function linksLocation()
    {
        if (!empty($_GET['location'])) {
            return sanitize_text_field($_GET['location']);
        }

        return false;
    }

    /**
     * Get links category filter
     *
     * @return bool|int
     */
    public static function linksCategory()
    {
        if (!empty($_GET['category'])) {
            return (int)$_GET['category'];
        }

        return false;
    }

    /**
     * Get links post type filter
     *
     * @return bool|string
     */
    public static function linksPostType()
    {
        if (!empty($_GET['post_type'])) {
            return sanitize_text_field($_GET['post_type']);
        }elseif(!empty($_GET['keyword_post_type'])){
            return sanitize_text_field($_GET['keyword_post_type']);
        }

        if(isset($_GET['page']) && 'link_whisper_target_keywords' === $_GET['page']){
            $selected_filters = get_user_meta(get_current_user_id(), 'wpil_filter_settings', true);

            if( !empty($selected_filters) && 
                isset($selected_filters['target_keywords']) && 
                !empty($selected_filters['target_keywords']['keyword_post_type']))
            {
                return $selected_filters['target_keywords']['keyword_post_type'];
            }
        }

        return false;
    }

    /**
     * Gets the link count filter settings if set
     **/
    public static function filterLinkCount(){
        if(isset($_GET['filter_type']) && ($_GET['filter_type'] === '1' || $_GET['filter_type'] === '2')){
            $filters = array('filter_type' => (int)$_GET['filter_type']);

            $filters['link_type'] = (isset($_GET['link_type'])) ? sanitize_text_field($_GET['link_type']) : null;
            $filters['link_min_count'] = (isset($_GET['link_min_count'])) ? (int)$_GET['link_min_count'] : 0;
            $filters['link_max_count'] = (array_key_exists('link_max_count', $_GET)) ? (int)$_GET['link_max_count'] : null;

            return $filters;
        }

        return false;
    }

    /**
     * Get post IDs by links location filter
     *
     * @return array
     */
    public static function getLinksLocationIDs()
    {
        global $wpdb;
        $ids = [];
        $location = self::linksLocation();
        if ($location) {
            $result = $wpdb->get_results("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('wpil_links_inbound_internal_count_data', 'wpil_links_outbound_internal_count_data', 'wpil_links_outbound_external_count_data') AND  meta_value LIKE '%\"$location\"%'");
            foreach ($result as $r) {
                $ids[] = $r->post_id;
            }
        }

        return $ids;
    }

    /**
     * Get post IDs by links category filter
     *
     * @return array
     */
    public static function getLinksCatgeoryIDs()
    {
        $category = self::linksCategory();
        if ($category) {
            $category_id = (int)$_GET['category'];
            return Wpil_Post::getCategoryPosts($category_id);
        }

        return [];
    }

    /**
     * Filter query by error codes
     *
     * @return string
     */
    public static function errorCodes()
    {
        if(isset($_POST['action']) && $_POST['action'] === 'wpil_csv_export' && !empty($_POST['export_data'])){
            $codes = implode(',', array_map(function($code){ return (int)$code; }, explode(',', $_POST['export_data'])));
            return " AND code IN ({$codes}) ";
        }elseif (!empty($_GET['codes'])) {
            $codes = implode(',', array_map(function($code){ return (int)$code; }, explode(',', $_GET['codes'])));
            return " AND code IN ({$codes}) ";
        } else {
            return " AND code IN (6,7,28,404,451,500,503,925) ";
        }
    }
}
