<?php

/**
 * Work with URL Changer
 */
class Wpil_URLChanger
{
    /**
     * Register hooks
     */
    public function register()
    {
        add_action('wp_ajax_wpil_url_changer_delete', [$this, 'delete']);
        add_action('wp_ajax_wpil_url_changer_reset', [$this, 'reset']);
    }

    /**
     * Show table page
     */
    public static function init()
    {
        if (!empty($_POST['old']) && !empty($_POST['new'])) {
            if(false !== strpos($_POST['old'], ',')){
                $olds = explode(',', $_POST['old']);
                foreach($olds as $old){
                    // exit if we're running close to the limit
                    if(Wpil_Base::overTimeLimit(15)){
                        break;
                    }
                    $url = self::store($old);
                    self::replaceURL($url);
                }
            }else{
                $url = self::store();
                self::replaceURL($url);
            }
        }

        $user = wp_get_current_user();
        $reset = !empty(get_option('wpil_url_changer_reset'));
        $table = new Wpil_Table_URLChanger();
        $table->prepare_items();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/url_changer.php';
    }

    public static function reset()
    {
        global $wpdb;

        //verify input data
        Wpil_Base::verify_nonce('wpil_url_changer');
        if (empty($_POST['count']) || (int)$_POST['count'] > 9999) {
            wp_send_json([
                'nonce' => $_POST['nonce'],
                'finish' => true
            ]);
        }

        $start = microtime(true);
        $memory_break_point = Wpil_Report::get_mem_break_point();
        $total = !empty($_POST['total']) ? (int)$_POST['total'] : 1;

        if ($_POST['count'] == 1) {
            //make matched posts array on the first call
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wpil_url_links");
            $statuses_query = Wpil_Query::postStatuses();
            $posts = $wpdb->get_results("SELECT ID as id, 'post' as type FROM {$wpdb->posts} WHERE post_content LIKE '%data-wpil=\"url\"%' $statuses_query");
            $posts = self::getLinkedPostsFromAlternateLocations($posts);
            $taxonomies = Wpil_Settings::getTermTypes();
            $terms = array();
            if(!empty($taxonomies)){
                $taxonomies = implode("','", $taxonomies);
                $terms = $wpdb->get_results("SELECT term_id as id, 'term' as type FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('{$taxonomies}') AND description LIKE '%data-wpil=\"url\"%'");
            }
            $posts = array_merge($posts, $terms);
            $total = count($posts);
        } else {
            //get unprocessed posts
            $posts = get_option('wpil_url_changer_reset', []);
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
            // todo: continue making the URLChanging able to handle wildcard matching. in this section, work on the base64ing of the old_url parameter in the URL
            preg_match_all('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]([a-zA-Z0-9+=]*?)[\'\"] )*(href|url)=[\'\"]([^\'\"]*?)[\'\"][^>]*?(?:[>\]]|&gt;)(.*?)(?:</a|&lt;/a&gt;)|(?:<a|&lt;a)[^>&]*?(href|url)=[\'\"]([^\'\"]*?)[\'\"][^>&]*?data-wpil=\"url\"[^>]*?(?:[>\]]|&gt;)(.*?)(?:[\[]|</a>|&lt;/a&gt;)`i', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {
                $m1 = (!empty($matches[3][$i]) && !empty($matches[4][$i]));
                $m2 = (!empty($matches[6][$i]) && !empty($matches[7][$i]));
                if ($m1 || $m2) {
                    $link = ($m1) ? $matches[3][$i]: $matches[6][$i];
                    $link2 = substr($link, -1) == '/' ? substr($link, 0, -1) : $link . '/';
                    $anchor = ($m1) ? $matches[4][$i]: $matches[7][$i];
                    $original_url = (!empty($matches[1][$i])) ? sanitize_text_field(base64_decode($matches[1][$i])): '';

                    $url_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wpil_urls WHERE new = '$link' OR new = '$link2'");
                    if (!empty($url_id)) {
                        $wpdb->insert($wpdb->prefix . 'wpil_url_links', [
                            'url_id' => $url_id,
                            'post_id' => $post->id,
                            'post_type' => $post->type,
                            'anchor' => $anchor,
                            'original_url' => $original_url,
                            'relative_link' => self::isRelativeLink($link)
                        ]);
                    }
                }
            }

            unset($posts[$key]);

            //break process if limits were reached
            if (microtime(true) - $start > 10 || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)) {
                update_option('wpil_url_changer_reset', $posts);
                break;
            }
        }

        if (empty($posts)) {
            update_option('wpil_url_changer_reset', []);
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
     * Create tables if they not exists
     */
    public static function prepareTable()
    {
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_urls'");
        if ($table != $wpdb->prefix . 'wpil_urls') {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_urls (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    old varchar(255) NOT NULL,
                                    new varchar(255) NOT NULL,
                                    wildcard_match tinyint(1) DEFAULT 0,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_url_links'");
        if ($table != $wpdb->prefix . 'wpil_url_links') {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_url_links (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    url_id int(10) unsigned NOT NULL,
                                    post_id int(10) unsigned NOT NULL,
                                    post_type varchar(10) NOT NULL,
                                    anchor varchar(255) NOT NULL,
                                    original_url text NOT NULL,
                                    relative_link tinyint(1) DEFAULT 0,
                                    PRIMARY KEY  (id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }

        Wpil_Base::fixCollation($wpdb->prefix . 'wpil_urls');
        Wpil_Base::fixCollation($wpdb->prefix . 'wpil_url_links');
    }

    /**
     * Get data for table
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
        global $wpdb;
        self::prepareTable();
        $limit = " LIMIT " . (($page - 1) * $per_page) . ',' . $per_page;

        $sort = " ORDER BY id DESC ";
        if ($orderby && $order) {
            $sort = " ORDER BY $orderby $order ";
        }

        $search = !empty($search) ? $wpdb->prepare(" AND (old LIKE %s OR new LIKE %s) ", Wpil_Toolbox::esc_like($search), Wpil_Toolbox::esc_like($search)) : '';
        $total = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_urls");
        $urls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_urls WHERE 1 $search $sort $limit");

        //get posts with inserted links
        foreach ($urls as $key => $url) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_url_links WHERE url_id = " . $url->id);
            $links = [];
            foreach ($result as $r) {
                $links[] = (object)[
                    'post' => new Wpil_Model_Post($r->post_id, $r->post_type),
                    'anchor' => $r->anchor,
                    'url' => $url->new,
                ];
            }
            $urls[$key]->links = $links;
        }

        return [
            'total' => $total,
            'urls' => $urls
        ];
    }

    /**
     * Save URL to DB
     *
     * @return object
     */
    public static function store($old = null)
    {
        global $wpdb;
        self::prepareTable();
        $old_url = !empty($old) ? $old: $_POST['old'];
        $wildcard_match = (substr($old_url, -1, 1) === '*') ? 1: 0;

        $wpdb->insert($wpdb->prefix . 'wpil_urls', [
            'old' => rtrim($old_url, '*'),
            'new' => $_POST['new'],
            'wildcard_match' => $wildcard_match,
        ]);

        return self::getURL($wpdb->insert_id);
    }

    /**
     * Get URL by ID
     *
     * @param $id
     * @return object
     */
    public static function getURL($id)
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpil_urls WHERE id = " . $id);
    }

    /**
     * Delete URL
     */
    public static function delete()
    {
        if (!empty($_POST['id'])) {
            global $wpdb;
            $url = self::getURL((int)$_POST['id']);
            $links = $wpdb->get_results("SELECT post_id, post_type FROM {$wpdb->prefix}wpil_url_links WHERE url_id = {$url->id} GROUP BY post_id, post_type");
            foreach ($links as $link) {
                $post = new Wpil_Model_Post($link->post_id, $link->post_type);
                $content = $post->getCleanContent();
                if ($post->type == 'post') {
                    Wpil_Post::editors('revertURLs', [$post, $url]);
                    Wpil_Editor_Kadence::revertURLs($content, $url);
                }
                self::revertURL($content, $url);
                $post->updateContent($content);
            }
            $wpdb->delete($wpdb->prefix . 'wpil_urls', ['id' => $url->id]);
            $wpdb->delete($wpdb->prefix . 'wpil_url_links', ['url_id' => $url->id]);
        }
    }

    /**
     * Revert link URL
     *
     * @param $content
     * @param $url
     * @param $anchor
     */
    public static function revertURL(&$content, $url)
    {
        if($url->wildcard_match){
            if(preg_match_all('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]([a-zA-Z0-9+=]*?)[\'\"] )*(href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', $content, $matches)){
                foreach($matches[0] as $key => $match){
                    if(!empty($matches[1][$key])){
                        $encoded = $matches[1][$key];
                        $decoded = sanitize_text_field(base64_decode($encoded));
                        // if there is indeed an old url in the attribute, and the url contains the current old url, replace it with the new one
                        if(!empty($decoded) && false !== strpos($decoded, $url->old)){
                            if(false !== strpos($content, $url->new)){
                                $content = preg_replace('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]' . $encoded . '[\'\"] )*(href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $decoded . '$3', $content, 1);
                                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $decoded . '$4$5', $content, 1);
                                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $decoded . '$4$5', $content, 1);
                            }
                
                            if(false !== strpos($content, htmlentities($url->new))){
                                $new_encoded = htmlentities($url->new);
                                $old_encoded = htmlentities($decoded);
                                $content = preg_replace('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]' . $encoded . '[\'\"] )*(href|url)=([\'\"])' . $new_encoded . '\/*([\'\"])`i', '$1=$2' . $old_encoded . '$3', $content, 1);
                                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content, 1);
                                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content, 1);
                            }
                
                            $url2 = (object)array('old' => $decoded, 'new' => $url->new);
                            self::prepareLinks($url2);

                            if(false !== strpos($content, $url2->new)){
                                $content = preg_replace('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]' . $encoded . '[\'\"] )*(href|url)=([\'\"])' . $url2->new . '\/*([\'\"])`i', '$1=$2' . $url2->old . '$3', $content, 1);
                                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $url2->new . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url2->old . '$4$5', $content, 1);
                                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $url2->new . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url2->old . '$4$5', $content, 1);
                            }
                
                            if(false !== strpos($content, htmlentities($url2->new))){
                                $new_encoded = htmlentities($url2->new);
                                $old_encoded = htmlentities($url2->old);
                                $content = preg_replace('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]' . $encoded . '[\'\"] )*(href|url)=([\'\"])' . $new_encoded . '\/*([\'\"])`i', '$1=$2' . $old_encoded . '$3', $content, 1);
                                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content, 1);
                                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content, 1);
                            }
                        }
                    }
                }
            }
        }else{
            if(false !== strpos($content, $url->new)){
                $content = preg_replace('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"](?:[a-zA-Z0-9+=]*?)[\'\"] )*(href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $content);
                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url->old . '$4$5', $content);
                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url->old . '$4$5', $content);
            }

            if(false !== strpos($content, htmlentities($url->new))){
                $new_encoded = htmlentities($url->new);
                $old_encoded = htmlentities($url->old);
                $content = preg_replace('`data-wpil=\"url\" (href|url)=([\'\"])' . $new_encoded . '\/*([\'\"])`i', '$1=$2' . $old_encoded . '$3', $content);
                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content);
                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content);
            }

            self::prepareLinks($url);

            if(false !== strpos($content, $url->new)){
                $content = preg_replace('`data-wpil=\"url\" (href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $content);
                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url->old . '$4$5', $content);
                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $url->new . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $url->old . '$4$5', $content);
            }

            if(false !== strpos($content, htmlentities($url->new))){
                $new_encoded = htmlentities($url->new);
                $old_encoded = htmlentities($url->old);
                $content = preg_replace('`data-wpil=\"url\" (href|url)=([\'\"])' . $new_encoded . '\/*([\'\"])`i', '$1=$2' . $old_encoded . '$3', $content);
                $content = preg_replace('`(<a[^>]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^>]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content);
                $content = preg_replace('`(&lt;a[^&]*?)(href|url)=([\'\"])' . $new_encoded . '([\'\"])([^&]*?)data-wpil=\"url\"`i', '$1$2=$3' . $old_encoded . '$4$5', $content);
            }
        }
    }

    /**
     * Replace URL for all posts
     *
     * @param $url
     */
    public static function replaceURL($url)
    {
        global $wpdb;

        $posts_relative = '';
        $meta_relative = '';
        if(self::isRelativeLink($url->old)){
            $unprepared_url = unserialize(serialize($url));
            $posts_relative =  "OR post_content LIKE '%href=\\\"{$wpdb->esc_like($url->old)}\\\"%' OR post_content LIKE '%href=\\\"{$wpdb->esc_like(htmlentities($url->old))}\\\"%'";
            $meta_relative = "OR m.meta_value LIKE '%href=\\\"{$wpdb->esc_like($url->old)}\\\"%' OR m.meta_value LIKE '%href=\\\"{$wpdb->esc_like(htmlentities($url->old))}\\\"%'";
        }

        $ignore_posts = Wpil_Settings::getIgnoreKeywordsPosts();
        update_option('wpil_post_procession', time());
        Wpil_Base::update_option_cache('wpil_post_procession', time());
        //get matched posts and categories
        $posts = [];
        self::prepareLinks($url);
        $statuses_query = Wpil_Query::postStatuses();
        $results = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE '%{$wpdb->esc_like($url->old)}%' OR post_content LIKE '%{$wpdb->esc_like(htmlentities($url->old))}%' $posts_relative) $statuses_query 
                                                UNION
                                                SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN ('_themify_builder_settings_json', 'ct_builder_shortcodes', 'mfn-page-items-seo') AND (m.meta_value LIKE '%{$wpdb->esc_like($url->old)}%' OR m.meta_value LIKE '%{$wpdb->esc_like(htmlentities($url->old))}%' $meta_relative) $statuses_query");
        $results = self::getPostsFromAlternateLocations($results, $url, $meta_relative);
        foreach ($results as $post) {
            $posts[] = new Wpil_Model_Post($post->ID);
        }

        $taxonomy_query = "";
        $taxonomies = implode("','", Wpil_Settings::getTermTypes());
        if (!empty($taxonomies)) {
            $taxonomy_query = " taxonomy IN ('{$taxonomies}') AND ";
        }

        $results = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE $taxonomy_query (`description` LIKE '%{$wpdb->esc_like($url->old)}%' OR `description` LIKE '%{$wpdb->esc_like(htmlentities($url->old))}%')");
        foreach ($results as $category) {
            $posts[] = new Wpil_Model_Post($category->term_id, 'term');
        }

        //proceed posts
        foreach ($posts as $post) {
            if (!in_array($post->type . '_' . $post->id, $ignore_posts)) {
                if(!empty($posts_relative)){
                    self::checkLink($unprepared_url, $post);
                }

                self::checkLink($url, $post);

                // if the post is published
                // trigger the post update so the save_post hook if fired and so everything hooked to it can handle the update routine
                $status = $post->getStatus();
                if(!empty($status) && 'publish' === $status){
                    wp_cache_delete($post->id, 'posts');
                    $post_arr = array('ID' => $post->id);

                    // if we're not updating the post's modified date
                    if(!Wpil_Settings::updatePostModifiedDate()){
                        $wp_post = get_post($post->id);
                        // set the current modified date for it
                        $post_arr['post_modified']      = $wp_post->post_modified;
                        $post_arr['post_modified_gmt']  = $wp_post->post_modified_gmt;
                    }

                    wp_update_post($post_arr);
                }
            }
        }
        update_option('wpil_post_procession', 0);
        Wpil_Base::update_option_cache('wpil_post_procession', 0);
    }

    /**
     * Get all URLs
     *
     * @return array
     */
    public static function getURLs()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_urls ORDER BY id ASC");
    }

    /**
     * Replace URLs for certain post
     *
     * @param $post
     */
    public static function replacePostURLs($post)
    {
        if (in_array($post->type . '_' . $post->id, Wpil_Settings::getIgnoreKeywordsPosts())) {
            return;
        }

        self::prepareTable();
        $content = $post->getCleanContent();
        foreach (self::getURLs() as $url) {
            if(self::isRelativeLink($url->old) && strpos($content, $url->old)){
                self::checkLink($url, $post);
            }elseif(self::isRelativeLink($url->old) && strpos($content, htmlentities($url->old))){
                // if the URL encoded version of the link is in the content, encode the URL and replace it
                $encoded_url = $url;
                $encoded_url->old = htmlentities($url->old);
                $encoded_url->new = htmlentities($url->new);
                self::checkLink($encoded_url, $post);
            }

            self::prepareLinks($url);
            if (strpos($content, $url->old)) {
                self::checkLink($url, $post);
            }elseif(strpos($content, htmlentities($url->old))){
                $encoded_url = $url;
                $encoded_url->old = htmlentities($url->old);
                $encoded_url->new = htmlentities($url->new);
                self::checkLink($encoded_url, $post);
            }
        }

        // if the post is published and is one of the ones that we process
        // trigger the post update so the save_post hook is fired and everything hooked to it can handle the update routine
        $status = $post->getStatus();
        if(!empty($status) && 'publish' === $status && in_array($post->getRealType(), Wpil_Settings::getAllTypes(), true) && !Wpil_Settings::disable_followup_post_updating()){
            update_option('wpil_post_procession', time());
            Wpil_Base::update_option_cache('wpil_post_procession', time());
            wp_cache_delete($post->id, 'posts');
            $post_arr = array('ID' => $post->id);

            // if we're not updating the post's modified date
            if(!Wpil_Settings::updatePostModifiedDate()){
                $wp_post = get_post($post->id);
                // set the current modified date for it
                $post_arr['post_modified']      = $wp_post->post_modified;
                $post_arr['post_modified_gmt']  = $wp_post->post_modified_gmt;
            }

            wp_update_post($post_arr);
            update_option('wpil_post_procession', 0);
            Wpil_Base::update_option_cache('wpil_post_procession', 0);
        }

        self::checkLinksCount($post);
    }

    /**
     * Check if content has certain URL
     *
     * @param $content
     * @param $url
     * @param $post
     */
    public static function checkLink($url, $post)
    {
        $content = $post->getCleanContent();

        if (self::hasUrl($content, $url)) {
            self::replaceLink($content, $url, true, $post);

            if ($post->type == 'post') {
                Wpil_Post::editors('replaceURLs', [$post, $url]);
                Wpil_Editor_Kadence::replaceURLs($content, $url);
            }

            $post->updateContent($content);
        } elseif (self::hasUrl(Wpil_Editor_Themify::getContent($post->id), $url)) {
            Wpil_Editor_Themify::replaceURLs($post, $url);
        } elseif (self::hasUrl(Wpil_Editor_Oxygen::getContent($post->id), $url)) {
            Wpil_Editor_Oxygen::replaceURLs($post, $url);
        } elseif (self::hasUrl(Wpil_Editor_Muffin::getContent($post->id), $url)) {
            Wpil_Editor_Muffin::replaceURLs($post, $url);
        } elseif (defined('GDLR_CORE_LOCAL') && self::hasUrl(Wpil_Editor_Goodlayers::getContent($post->id), $url)) {
            Wpil_Editor_Goodlayers::replaceURLs($post, $url);
        } elseif(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes()) && 'post' === $post->type && 'wprm_recipe' === get_post_type($post->id)){
            Wpil_Editor_WPRecipe::replaceURLs($post, $url);
        }
    }

    /**
     * Check if content has URL
     *
     * @param $content
     * @param $url
     * @return bool
     */
    public static function hasUrl($content, $url)
    {
        // if the user is doing wildcard matching
        if($url->wildcard_match){
            preg_match('`(href|url)=[\'\"]((?:' . preg_quote($url->old, '`') . '|' . preg_quote(htmlentities($url->old), '`') . ')[^\'\"]*?)[\'\"].*?([>\]]|&gt;)(.*?)([<\[]|&lt;)`i', $content, $matches);
        }else{
            preg_match('`(href|url)=[\'\"](' . preg_quote($url->old, '`') . '|' . preg_quote(htmlentities($url->old), '`') . ')\/*[\'\"].*?([>\]]|&gt;)(.*?)([<\[]|&lt;)`i', $content, $matches);
        }

        return !empty($matches);
    }

    /**
     * Replace certain URL
     *
     * @param $content
     * @param $url
     * @param bool $db_insert
     * @param null|object $post
     */
    public static function replaceLink(&$content, $url, $db_insert = false, $post = null)
    {
        // search with both the url as inserted in the DB
        $urls[] = $url;
        // and the htmlentity encoded version
        $urls[] = self::encode_url($url);

        foreach($urls as $url){
            // if the user is doing wildcard matching
            if($url->wildcard_match){
                // search for the urls with a broad matching basis
                preg_match_all('`(href|url)=[\'\"](' . preg_quote($url->old, '`') . '[^\'\"]*?)[\'\"].*?(?:[>\]]|&gt;)(.*?)(?:[<\[]|&lt;)`i', $content, $matches);
            }else{
                preg_match_all('`(href|url)=[\'\"](' . preg_quote($url->old, '`') . '\/*)[\'\"].*?(?:[>\]]|&gt;)(.*?)(?:[<\[]|&lt;)`i', $content, $matches);
            }

            foreach ($matches[3] as $key => $anchor) {
                // TODO: MAKE SURE ALL EDITORS USE THIS METHOD
                $text = 'data-wpil="url" ';

                $content_before = md5($content);
                $old_url = ($url->wildcard_match) ? $matches[2][$key]: $url->old;
                $text .= ($url->wildcard_match) ? 'data-wpil-url-old="' . base64_encode($old_url) . '" ': ''; // if we're doing wildcard matching, include an encoded version of the old url in an attribute
                $link = str_replace(['href=', 'url=', $old_url, '///"', '//"'], [$text . 'href=', $text . 'url=', $url->new, '/"', '/"'], $matches[0][$key]); // tag the link as changed, replace the link, and normalize the trailing slashes
                $content = str_replace($matches[0][$key], $link, $content);
                $content_after = md5($content);

                if ($db_insert && $content_before !== $content_after) { // if we're logging the change and the URL has been changed
                    global $wpdb;
                    $si = $wpdb->insert($wpdb->prefix . 'wpil_url_links', [
                        'url_id' => $url->id,
                        'post_id' => $post->id,
                        'post_type' => $post->type,
                        'anchor' => $anchor,
                        'original_url' => $old_url,
                        'relative_link' => self::isRelativeLink($url->old) // todo add setting
                    ]);
                }
            }
        }
    }

    /**
     * Remove ghost DB link records
     *
     * @param $post
     */
    public static function checkLinksCount($post)
    {
        global $wpdb;

        $links = $wpdb->get_results("SELECT url_id, anchor, count(*) as cnt FROM {$wpdb->prefix}wpil_url_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' GROUP BY anchor");
        foreach ($links as $link) {
            $url = self::getURL($link->url_id);
            $unprepared_url = unserialize(serialize($url));
            self::prepareLinks($url);
            if(self::isRelativeLink($unprepared_url->old)){
                $regex = '`(href|url)=[\'\"](' . $url->new . '|' . $unprepared_url->new . '|' . htmlentities($url->new) . ')\/*[\'\"].*?[>\]]' . $link->anchor . '[<\[]`i';
            }else{
                $regex = '`(href|url)=[\'\"](' . $url->new . '|' . htmlentities($url->new) . ')\/*[\'\"].*?[>\]]' . $link->anchor . '[<\[]`i';
            }

            preg_match_all($regex, $post->getCleanContent(), $matches);
            if (count($matches[0]) < $link->cnt) {
                $link_ids = [];
                $result = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wpil_url_links WHERE post_id = {$post->id} AND post_type = '{$post->type}' AND url_id = {$url->id} ORDER BY id");
                foreach ($result as $r) {
                    $link_ids[] = $r->id;
                }
                $link_ids = array_slice($link_ids, count($matches[0]));
                if(!empty($link_ids)){
                    $wpdb->query("DELETE FROM {$wpdb->prefix}wpil_url_links WHERE id IN (" . implode(', ', $link_ids) . ")");
                }
            }
        }
    }

    /**
     * Checks if the link is relative
     * 
     * @param string $link
     **/
    public static function isRelativeLink($link = ''){
        if(empty($link) || empty(trim($link))){
            return false;
        }

        if(strpos($link, 'http') === false && substr($link, 0, 1) === '/'){
            return true;
        }

        // parse the URL to see if it only contains a path
        $parsed = wp_parse_url($link);
        if( !isset($parsed['host']) && 
            !isset($parsed['scheme']) && 
            isset($parsed['path']) && !empty($parsed['path'])
        ){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Transform link to the common view
     *
     * @param $link
     * @return string
     */
    public static function prepareLink(&$link)
    {
        if (strpos($link, 'http') !== 0) {
            $link = site_url($link);
        }
        if (substr($link, -1) == '/') {
            $link = substr($link, 0, -1);
        }

        return $link;
    }

    /**
     * Prepare both links and check if they are not the same
     *
     * @param $url
     */
    public static function prepareLinks(&$url) {
        $insert_relative = get_option('wpil_insert_links_as_relative', false);
        $old = $url->old;
        $new = $url->new;

        self::prepareLink($old);

        // if the link isn't relative or the user hasn't chosen to only insert relative links
        if(!self::isRelativeLink($new) || empty($insert_relative)){
            // prepare the new link
            self::prepareLink($new);
        }else{
            // make sure there's only one slash
            $new = ltrim($new, '/');
            $new = '/' . $new;
        }

        if ($old !== $new) {
            $url->old = $old;
            $url->new = $new;
        }
    }

    public static function getLinkedPostsFromAlternateLocations($posts){
        global $wpdb;

        $found_posts = false;
        $active_post_types = Wpil_Settings::getPostTypes();

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', $active_post_types)){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' AS type, 1 AS alt FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND meta_value LIKE '%data-wpil=\"url\"%'");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS id, 'post' AS type, 1 AS alt FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'gdlr-core-page-builder' {$post_types_p} {$statuses_query_p} AND m.meta_value LIKE '%data-wpil=\"url\"%'");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if WooCommerce is active
        if(defined('WC_PLUGIN_FILE') && in_array('product', $active_post_types)){
            $results = $wpdb->get_results("SELECT DISTINCT ID AS id, 'post' AS type, 1 AS alt FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND p.post_excerpt LIKE '%data-wpil=\"url\"%'");

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

    public static function getPostsFromAlternateLocations($posts, $url, $meta_relative){
        global $wpdb;

        $found_posts = false;
        $active_post_types = Wpil_Settings::getPostTypes();

        // if WP Recipes is active and the user wants to add links to the recipe notes
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', $active_post_types)){
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->prefix}postmeta m WHERE `meta_key` = 'wprm_notes' AND (meta_value LIKE '%{$wpdb->esc_like($url->old)}%' OR meta_value LIKE '%{$wpdb->esc_like(htmlentities($url->old))}%' $meta_relative)");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_results("SELECT DISTINCT m.post_id AS ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key = 'gdlr-core-page-builder' {$post_types_p} {$statuses_query_p} AND (meta_value LIKE '%{$wpdb->esc_like($url->old)}%' OR meta_value LIKE '%{$wpdb->esc_like(htmlentities($url->old))}%' {$meta_relative})");

            if(!empty($results)){
                $found_posts = true;
                $posts = array_merge($posts, $results);
            }
        }

        // if WooCommerce is active
        if(defined('WC_PLUGIN_FILE') && in_array('product', $active_post_types)){
            $posts_relative = '';
            if(self::isRelativeLink($url->old)){
                $posts_relative =  "OR p.post_excerpt LIKE '%href=\\\"{$wpdb->esc_like($url->old)}\\\"%'";
            }

            $results = $wpdb->get_results("SELECT DISTINCT ID FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND (p.post_excerpt LIKE '%{$wpdb->esc_like($url->old)}%' OR p.post_excerpt LIKE '%{$wpdb->esc_like($url->old)}%' {$posts_relative})");

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

        return $posts;
    }

    /**
     * Helper function to create html encoded versions of urls.
     * Encodes both the old url and the new url parameters
     * 
     * @param $object $url The url object to encode
     **/
    public static function encode_url($url){
        $new_url = json_decode(json_encode($url));
        $new_url->new = str_replace('&amp;amp;', '&amp;', htmlentities($url->new));
        $new_url->old = str_replace('&amp;amp;', '&amp;', htmlentities($url->old));
        return $new_url;
    }
}
