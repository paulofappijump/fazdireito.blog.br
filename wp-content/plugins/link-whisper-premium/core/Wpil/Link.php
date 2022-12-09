<?php

/**
 * Work with links
 */
class Wpil_Link
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_save_linking_references', [$this, 'addLinks']);
        add_action('wp_ajax_wpil_get_link_title', [$this, 'getLinkTitle']);
        add_action('wp_ajax_wpil_add_link_to_ignore', [$this, 'addLinkToIgnore']);
        add_action('wp_ajax_wpil_delete_selected_links', [$this, 'ajax_delete_selected_links']);
    }

    /**
     * Update post links
     */
    function addLinks()
    {
        $err_msg = false;

        //check if request has needed data
        if (empty($_POST['data'])) {
            $err_msg = "No links selected";
        } elseif (empty($_POST['id']) || empty($_POST['type']) || empty($_POST['page'])){
            $err_msg = "Broken links data";
        } else {
            $page = $_POST['page'];

            foreach ($_POST['data'] as $item) {
                $id = !empty($item['id']) ? (int)$item['id'] : (int)$_POST['id'];
                $type = !empty($item['type']) ? $item['type'] : $_POST['type'];

                $links = $item['links'];
                //trim sentences
                foreach ($links as $key => $link) {
                    if ($page == 'inbound') {
                        $link['id'] = (int)$_POST['id'];
                        $link['type'] = sanitize_text_field($_POST['type']);
                    }

                    $external = (isset($link['post_origin']) && $link['post_origin'] === 'external') ? true: false;

                    if (!empty($link['custom_link'])) {
                        $view_link = $link['custom_link'];
                    } elseif($external) {
                        $item = new Wpil_Model_ExternalPost(array('post_id' => (int)$link['id'], 'type' => $link['type'], 'site_url' => esc_url_raw($link['site_url'])));
                        
                        $view_link = $item->getLinks()->view;
                    } elseif ($link['type'] == 'term') {
                        $post = new Wpil_Model_Post((int)$link['id'], 'term');
                        $view_link = $post->getViewLink();
                    } else {
                        $post = new Wpil_Model_Post((int)$link['id']);
                        $view_link = $post->getViewLink();
                    }

                    $links[$key]['sentence'] = trim(base64_decode($link['sentence']));
                    $links[$key]['sentence_with_anchor'] = trim(str_replace('%view_link%', $view_link, $link['sentence_with_anchor']));

                    if (!empty($link['custom_sentence'])) {
                        $links[$key]['custom_sentence'] = trim(str_replace('|href="([^"]+)"|', $view_link, base64_decode($link['custom_sentence'])));

                        if (!empty($link['custom_link'])) {
                            $links[$key]['custom_sentence'] = preg_replace('|href="([^"]+)"|', 'href="'.$link['custom_link'].'"', $links[$key]['custom_sentence']);
                        }
                    }

                    update_post_meta($link['id'], 'wpil_sync_report3', 0);
                }

                if ($type == 'term') {
                    $existing_links = get_term_meta($id, 'wpil_links', true);
                    $links = (!empty($existing_links) && is_array($existing_links)) ? array_merge($links, $existing_links): $links;
                    update_term_meta($id, 'wpil_links', $links);
                } else {
                    $existing_links = get_post_meta($id, 'wpil_links', true);
                    $links = (!empty($existing_links) && is_array($existing_links)) ? array_merge($links, $existing_links): $links;
                    update_post_meta($id, 'wpil_links', $links);

                    if ($page == 'outbound') {
                        //create DB record with success flag
                        update_post_meta($id, 'wpil_is_outbound_links_added', '1');

                        //create DB record to refresh page after post update if Gutenberg is active
                        if (!empty($_POST['gutenberg']) && $_POST['gutenberg'] == 'true') {
                            update_post_meta($id, 'wpil_gutenberg_restart', '1');
                        }
                    }
                }
            }

            //add links to content
            if ($page == 'inbound') {
                foreach ($_POST['data'] as $item) {
                    if ($item['type'] == 'term') {
                        Wpil_Term::addLinksToTerm($item['id']);
                    } else {
                        ob_start();
                        Wpil_Post::addLinksToContent(null, ['ID' => $item['id']], array());
                        ob_end_clean();
                    }
                }

                if ($item['type'] == 'term') {
                    update_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', '1');
                } else {
                    update_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', '1');
                }
            }
        }

        //return response
        header("Content-type: application/json");
        echo json_encode(['err_msg' => $err_msg]);

        exit;
    }

    /**
     * Delete link from post
     * @param null $params
     */
    public static function delete($params = null, $no_die = false)
    {
        foreach (['post_id', 'post_type', 'url', 'anchor', 'url_anchor', 'link_id'] as $key) {
            $$key = self::getDeleteParam($params, $key);
        }
        $anchor = !empty($anchor) ? base64_decode($anchor) : null;

        if ($post_id && $post_type && $url) {
            $post = new Wpil_Model_Post($post_id, $post_type);
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();

            // create the search content so we can examine more than just the post content for the link
            $search_content = trim($content . ' ' . $excerpt);

            if(self::checkIfBase64ed($url)){
                $url = base64_decode($url);
            }

            // if the url isn't in the content, check if the url in the content is relative
            if(false === strpos($search_content, '"' . $url . '"')){
                $site_url = get_home_url();
                $relative = wp_make_link_relative($url);
                // if it is, make the url the relative version
                if(false !== strpos($search_content, '"' . $relative . '"')){
                    $url = $relative;
                }elseif(false !== strpos($url, $site_url)){ // if the wp_relative function didn't work, try removing the site URL from the link and see if that works
                    // create a new relative version of the link
                    $relative = ltrim(str_replace($site_url, '', $url), '/');
                    // if the link is more than just a directory separator and does appear in the content
                    if(strlen($relative) > 1 && false !== strpos($search_content, '"/' . $relative . '"')){
                        // go with this version
                        $url = ('/' . $relative);
                    }elseif(strlen($relative) > 1 && false !== strpos($search_content, '"' . $relative . '"')){ // if the link is more than just a directory separator, and appears in the content without a leading slash
                        // go with this version
                        $url = $relative;
                    }
                }
            }

            // check if the current URL is for an image
            $is_image = false;
            if((preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico/i', $url) || false !== strpos($url, '/nextgen-attach_to_post/preview/')) && empty($anchor)){ // we're checking for normal image extensions and if the URL points to a "NextGEN Gallery" image
                // if it is, check to see if there's an image tag with this URL in the post // Since the link is already for an image, we'll check if there's an image tag on the assumption that the user is deleting an image.
                if(preg_match('`<img [^><]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^><]*>|&lt;img [^&>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^&>]*&gt;`', $content)){
                    $is_image = true;
                }
            }

            if($is_image){
                $content = self::deleteImage($post, $url, $content);
                $meta_deleted = self::deleteLinkFromMetaFields($post, $url, '', true);
                $excerpt = self::deleteImage($post, $url, $excerpt);
            }else{
                $content = self::deleteLink($post, $url, $anchor, $content);
                $meta_deleted = self::deleteLinkFromMetaFields($post, $url, $anchor);
                $excerpt = self::deleteLink($post, $url, $anchor, $excerpt);
            }

            $updated = $post->updateContent($content, $excerpt);

            if($updated){
                $post->setContent($content);
                $post->clearPostCache();
            }

            //delete link record from wpil_broken_links table
            if (!empty($link_id)) {
                Wpil_Error::deleteLink($link_id);
            }

            if (WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::update_post_in_link_table($post);
            }

            Wpil_Report::statUpdate($post);

            //update second post if link was internal inbound
            $second_post = Wpil_Post::getPostByLink($url);
            if (!empty($second_post)) {
                Wpil_Report::statUpdate($second_post);
            }
        }elseif ($post_id && $post_type && $url_anchor) {
            $post = new Wpil_Model_Post($post_id, $post_type);
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();

            // create the search content so we can examine more than just the post content for the link
            $search_content = trim($content . ' ' . $excerpt);

            // create a list of removed urls
            $removed_urls = array();

            foreach($url_anchor as $data){
                $url = $data[0];
                $anchor = $data[1];

                if(Wpil_Base::overTimeLimit(0, 11)){
                    break;
                }

                if(self::checkIfBase64ed($url)){
                    $url = base64_decode($url);
                }

                if(self::checkIfBase64ed($anchor)){
                    $anchor = base64_decode($anchor);
                }

                // if the url isn't in the content, check if the url in the content is relative
                if(false === strpos($search_content, '"' . $url . '"')){
                    $site_url = get_home_url();
                    $relative = wp_make_link_relative($url);
                    // if it is, make the url the relative version
                    if(false !== strpos($search_content, '"' . $relative . '"')){
                        $url = $relative;
                    }elseif(false !== strpos($url, $site_url)){ // if the wp_relative function didn't work, try removing the site URL from the link and see if that works
                        // create a new relative version of the link
                        $relative = ltrim(str_replace($site_url, '', $url), '/');
                        // if the link is more than just a directory separator and does appear in the content
                        if(strlen($relative) > 1 && false !== strpos($search_content, '"/' . $relative . '"')){
                            // go with this version
                            $url = ('/' . $relative);
                        }elseif(strlen($relative) > 1 && false !== strpos($search_content, '"' . $relative . '"')){ // if the link is more than just a directory separator, and appears in the content without a leading slash
                            // go with this version
                            $url = $relative;
                        }
                    }
                }

                // check if the current URL is for an image
                $is_image = false;
                if((preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico/i', $url) || false !== strpos($url, '/nextgen-attach_to_post/preview/')) && empty($anchor)){ // we're checking for normal image extensions and if the URL points to a "NextGEN Gallery" image
                    // if it is, check to see if there's an image tag with this URL in the post // Since the link is already for an image, we'll check if there's an image tag on the assumption that the user is deleting an image.
                    if(preg_match('`<img [^><]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^><]*>|&lt;img [^&>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^&>]*&gt;`', $content)){
                        $is_image = true;
                    }
                }

                $before = md5($content);
                $before_excerpt = md5($excerpt);

                if($is_image){
                    $content = self::deleteImage($post, $url, $content);
                    $meta_deleted = self::deleteLinkFromMetaFields($post, $url, '', true);
                    $excerpt = self::deleteImage($post, $url, $excerpt);
                }else{
                    $content = self::deleteLink($post, $url, $anchor, $content);
                    $meta_deleted = self::deleteLinkFromMetaFields($post, $url, $anchor);
                    $excerpt = self::deleteLink($post, $url, $anchor, $excerpt);
                }

                // check if the url has been removed from any content
                if(md5($content) !== $before || $meta_deleted || $before_excerpt !== md5($excerpt)){
                    $removed_urls[] = array('url' => $data[0], 'anchor' => $data[1], 'post_id' => $post->id, 'post_type' => $post->type); // save the original versions of the URL+anchor pair and the post id so that we can remove them from the dropdown
                }
            }
            $updated = $post->updateContent($content, $excerpt);

            if($updated){
                $post->setContent($content);
                $post->clearPostCache();
            }

            //delete link record from wpil_broken_links table
            if (!empty($link_id)) {
                Wpil_Error::deleteLink($link_id);
            }

            if (WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::update_post_in_link_table($post);
            }

            Wpil_Report::statUpdate($post);

            //update second post(s) if link was internal inbound
            $second_posts = array();
            foreach($removed_urls as $removed_url){
                if(self::checkIfBase64ed($removed_url['url'])){
                    $url2 = base64_decode($removed_url['url']);
                }
                $second_post = Wpil_Post::getPostByLink($url2);
                if (!empty($second_post)) {
                    // make sure we only update a post's stats once
                    $pid = $second_post->type . '_' . $second_post->id;
                    if(!in_array($pid, $second_posts, true)){
                        $second_posts[] = $pid;
                        // update the second post's stats
                        Wpil_Report::statUpdate($second_post);
                        // and update it's status in the link table
                        Wpil_Report::update_post_in_link_table($second_post);
                    }
                }
            }

            return $removed_urls;
        }

        if (!$no_die) {
            die;
        }
    }

    /**
     * Deletes a link from the supplied post content.
     * @param $post The Wpil post object that we're deleting the link from
     * @param string $url The url of the link that we're removing
     * @param string|null $anchor The anchor text of the link we're removing
     * @param string $content The post content that we're removing the link from
     * @param string $run_editors Should we also tell the editors to remove the link? Defaults to true
     * @return string $content The content with the link removed.
     **/
    public static function deleteLink($post, $url, $anchor, $content, $run_editors = true){
        if (!empty($post) && $post->type == 'post' && $run_editors && is_string($content)) {
            Wpil_Post::editors('deleteLink', [$post->id, $url, $anchor]);
            Wpil_Editor_Kadence::deleteLink($content, $url, $anchor);
        }

        // if there's no content to process, exit here
        if(empty($content)){
            return $content;
        }

        $has_url = ($url !== '{{wpil-empty-url}}') ? true: false; // check and see if the current link has no attributes, including a url...
        $has_anchor = !empty($anchor);
        $old_content = md5($content);
        $remove_inner_html = Wpil_Settings::delete_link_inner_html();

        //delete link from post content
        if($has_anchor){
            if($has_url){
                $search = '`<a [^>]+(\'|\")' . preg_quote(trim($url, '"\\/'), '`') . '(?:/)*(\'|\"|\\\")[^>]*>' . preg_quote($anchor, '`') . '</a>`i';
            }else{
                // if the link has no url, make a really simple regex to search with
                $search = '`<a>' . preg_quote($anchor, '`') . '</a>`i';
            }

            $content = preg_replace($search, $anchor,  $content, 1);

            // if the link hasn't been removed
            if($old_content === md5($content)){
                // maybe the link has some HTML tags inside the anchor tag... so what we'll do is check to see if there's HTML inside the tag
                preg_match_all('`<a [^><]*?href=(?:\'|\")' . preg_quote(trim($url, '"\\/'), '`') . '(?:/)*(?:\'|\"|\\\")[^><]*?>((?:<(?!a)[a-zA-Z]+?[^><]*?>)*?' . preg_quote($anchor, '`') . '(?:<(?!a)[a-zA-Z/]+?[^><]*?>)*?)</a>`i', $content, $matches);
                // if there appears to be
                if(!empty($matches)){
                    // go over each result and check to see if we have a match
                    foreach($matches[0] as $key => $link){
                        $found_link_with_anchor = $matches[0][$key];
                        $found_anchor = $matches[1][$key];
                        // if the anchor isn't empty and if it is the same as the supplied anchor when we remove tags
                        if(!empty($found_anchor) && strip_tags($found_anchor) === $anchor){
                            // IT"S AN UNDENIABLE CERTAINTY THAT IT"S THE LINK TO REMOVE
                            // so replace the link with the anchor contents
                            $found_anchor = ($remove_inner_html) ? strip_tags($found_anchor): $found_anchor; // but first check if the user wants to remove the inner HTML
                            $content = preg_replace('`' . preg_quote($found_link_with_anchor, '`') . '`', $found_anchor,  $content, 1);
                            // if we met with success, exit the loop
                            if($old_content !== md5($content)){
                                break;
                            }
                        }
                    }
                }

                // if that still didn't work, try removing encoded anchors
                if($old_content === md5($content)){
                    $content = preg_replace('`&lt;a [^&]+(\'|\")' . preg_quote(trim($url, '"\\/'), '`') . '(?:/)*(\'|\"|\\\")[^>]*&gt;' . preg_quote($anchor, '`') . '&lt;/a&gt;`i', $anchor,  $content, 1);

                    // if THAT doesn't work
                    if($old_content === md5($content)){
                        // use a more aggresive regex to remove it
                        $content = preg_replace('`<a [^>]+(\'|\")' . preg_quote(trim($url, '"\\/'), '`') . '(?:/)*(\'|\"|\\\")[^>]*>(.*?)' . preg_quote($anchor, '`') . '(.*?)</a>`i', $anchor,  $content, 1);
                    }
                }
            }
        }

        // if there's no anchor or the link couldn't be deleted
        if (empty($has_anchor) || md5($content) === $old_content) {
            $content = preg_replace('`<a [^>]+(\'|\")' . preg_quote(trim($url, '"\\/'), '`') . '(?:/)*(\'|\"|\\\")[^>]*>([\s\S]*?)</a>`i', '$3',  $content, 1);
            // if the link hasn't been removed
            if($old_content === md5($content)){
                // try removing the encoded version of the anchor
                $content = preg_replace('`&lt;a [^&]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^>]*&gt;([\s\S]*?)&lt;/a&gt;`i', '$3',  $content, 1);
            }
        }

        return $content;
    }

    /**
     * Deletes a specific image from a post.
     * @param $post The Wpil post object that we're deleting the link from
     * @param string $url The url of the link that we're removing
     * @param string $content The post content that we're removing the link from
     * @return string $content The content with the link removed.
     **/
    public static function deleteImage($post, $url, $content){
        /*
        if ($post->type == 'post') { // todo: look into if needed
            Wpil_Post::editors('deleteLink', [$post->id, $url]);
            Wpil_Editor_Kadence::deleteLink($content, $url);
        }*/

        // if there's no content to process, exit here
        if(empty($content)){
            return $content;
        }

        $old_content = md5($content);

        // try removing the image
        $content = preg_replace('`<img [^><]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^><]*>`i', '',  $content);

        // if the image hasn't been removed
        if($old_content === md5($content)){
            // try removing the encoded version of the tag
            $content = preg_replace('`&lt;img [^&>]+(\'|\")' . preg_quote($url, '`') . '(\'|\")[^&>]*&gt;`i', '',  $content);
        }

        return $content;
    }

    public static function getDeleteParam($params, $key)
    {
        if (!empty($params[$key])) {
            return $params[$key];
        } elseif (!empty($_POST[$key])) {
            return $_POST[$key];
        } else {
            return null;
        }
    }

    /**
     * Deletes a given link from the post's metafields
     **/
    public static function deleteLinkFromMetaFields($post, $url, $anchor = '', $is_image = false){
        $fields = Wpil_Post::getMetaContentFieldList($post->type);

        // if this is a post, include any ACF fields that may exist
        if($post->type === 'post'){
            $fields = array_merge($fields, Wpil_Post::getAdvancedCustomFieldsList($post->id));
        }

        // set a flag for tracking if the link has been removed from the content
        $deleted = false;

        if(!empty($fields)){
            foreach($fields as $field){
                if($post->type === 'post'){
                    $content = get_post_meta($post->id, $field, true);
                }else{
                    $content = get_term_meta($post->id, $field, true);
                }

                // if the content is an array, skip to the next item
                if(is_array($content) || !is_string($content)){
                    continue; //TODO: make able ot process array field-values. Especially for ACF repeaters. I think those are stored as field references, so we'd haveto do an extra data query to pull the actual field data that we'd be deleting links from
                }

                $old_content = md5($content);

                if(!$is_image){
                    $content = self::deleteLink($post, $url, $anchor, $content);
                }else{
                    $content = self::deleteImage($post, $url, $content);
                }

                if(md5($content) !== $old_content){
                    $deleted = true;
                }

                if($post->type === 'post'){
                    update_post_meta($post->id, $field, $content);
                }else{
                    update_term_meta($post->id, $field, $content);
                }
            }
        }

        /**
         * Allows the user to delete links from a custom content location
         **/
        do_action('wpil_meta_content_data_delete_link', $post->id, $post->type, $url, $anchor, $is_image);

        return $deleted;
    }

    public static function ajax_delete_selected_links(){
        Wpil_Base::verify_nonce('delete-selected-links');

        if(!isset($_POST['links']) || empty($_POST['links'])){
            wp_send_json(array('error' => array(
                'title' => __('Data Error', 'wpil'),
                'text'  => __('No links were selected, please reload the page and try again.', 'wpil'),
            )));
        }

        // ignore the object cache if it's present
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // sort the links by post so that we get a higher efficiency out of removing them
        $links = array();
        foreach($_POST['links'] as $link){
            $pid = $link['post_id'] . '_' . $link['post_type'];
            if(!isset($links[$pid])){
                $links[$pid] = array('post_id' => $link['post_id'], 'post_type' => $link['post_type'], 'url_anchor' => array());
            }
            $links[$pid]['url_anchor'][] = array($link['url'], $link['anchor']);
        }

        // now remove as many links as we can in 10 seconds
        $removed = array();
        foreach($links as $dat){
            if(!Wpil_Base::overTimeLimit(0, 10)){
                $deleted = self::delete($dat);
                if(!empty($deleted)){
                    $removed[] = $deleted;
                }
            }
        }

        // if we've removed the links
        if(!empty($removed) && !empty($removed[0])){
            // send back the list of links that have been removed
            wp_send_json(array('progress' => $removed));
        }else{
            // If no links have been removed, send back the completion message
            wp_send_json(array('success' => array(
                'title' => __('Success', 'wpil'),
                'text'  => __('All of the links that could be removed have been removed.', 'wpil'),
            )));
        }
    }


    /**
     * Check if link is internal
     *
     * @param $url
     * @return bool
     */
    public static function isInternal($url)
    {
        if (strpos($url, '//') === false) {
            return true;
        }

        if (self::markedAsExternal($url)) {
            return false;
        }

        if(self::isAffiliateLink($url)){
            return false;
        }

        $localhost = parse_url(get_home_url(), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if (!empty($localhost) && !empty($host)) {
            $localhost = str_replace('www.', '', $localhost);
            $host = str_replace('www.', '', $host);
            if ($localhost == $host) {
                return true;
            }

            $internal_domains = Wpil_Settings::getInternalDomains();

            if(in_array($host, $internal_domains, true)){
                return true;
            }
        }

        // if the user is filtering staging, check if this is a filtered staging url
        $filtered_url = self::filter_live_to_staging_domain($url);
        $host = parse_url($filtered_url, PHP_URL_HOST);
        if($filtered_url !== $url && !empty($localhost) && !empty($host)){
            $localhost = str_replace('www.', '', $localhost);
            $host = str_replace('www.', '', $host);
            if ($localhost == $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the url is a known cloaked affiliate link.
     * 
     * @param string $url The url to be checked
     * @return bool Whether or not the url is to a cloaked affiliate link. 
     **/
    public static function isAffiliateLink($url){
        // if ThirstyAffiliates is active
        if(class_exists('ThirstyAffiliates')){
            $links = self::getThirstyAffiliateLinks();

            if(isset($links[$url])){
                return true;
            }
        }


        return false;
    }

    /**
     * Checks to see if the given url goes to a sponsored domain
     **/
    public static function isSponsoredLink($url){
        $domains = Wpil_Settings::getSponsoredDomains();

        // if there are no sponsored domains, return false now
        if(empty($domains)){
            return false;
        }

        // get the url's domain
        $url_domain = wp_parse_url($url, PHP_URL_HOST);

        if(empty($url_domain)){
            return false;
        }

        return (in_array($url_domain, $domains, true)) ? true: false;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return bool|int
     */
    public static function getResponseCode($url)
    {
        // if a url was provided and it's formatted correctly
        if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 1) == '/') ){

            // make sure the url is absolute so cURL doesn't have a problem with it
            $url = Wpil_Settings::makeLinkAbsolute($url);

            // make the call
            return self::getResponseCodeCurl($url);
        }

        return 925;
    }

    public static function getResponseCodeCurl($url) {
        $c = curl_init(html_entity_decode($url));
        $user_ip = get_transient('wpil_site_ip_address');
        
        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('wpil_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the list of headers to make the cURL request with
        $request_headers = array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: max-age=0, no-cache',
            'Keep-Alive: 300',
            'Pragma: ',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?0',
            'Host: ' . parse_url($url, PHP_URL_HOST),
            'Referer: ' . site_url(),
            'User-Agent: ' . WPIL_DATA_USER_AGENT,
        );

        if(!empty($user_ip)){
            $request_headers[] = 'X-Real-Ip: ' . $user_ip;
        }

        curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_FILETIME, true);
        curl_setopt($c, CURLOPT_NOBODY, true);
        curl_setopt($c, CURLOPT_HTTPGET, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_MAXREDIRS, 30);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($c, CURLOPT_TIMEOUT, 20);
        curl_setopt($c, CURLOPT_COOKIEFILE, null);

        $curl_version = curl_version();
        if (version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
            curl_setopt($c, CURLOPT_SSL_FALSESTART, true);
        }

        //Set the proxy configuration. The user can provide this in wp-config.php
        if(defined('WP_PROXY_HOST')){
            curl_setopt($c, CURLOPT_PROXY, WP_PROXY_HOST);
        }
        if(defined('WP_PROXY_PORT')){
            curl_setopt($c, CURLOPT_PROXYPORT, WP_PROXY_PORT);
        }
        if(defined('WP_PROXY_USERNAME')){
            $auth = WP_PROXY_USERNAME;
            if(defined('WP_PROXY_PASSWORD')){
                $auth .= ':' . WP_PROXY_PASSWORD;
            }
            curl_setopt($c, CURLOPT_PROXYUSERPWD, $auth);
        }

        //Make CURL return a valid result even if it gets a 404 or other error.
        curl_setopt($c, CURLOPT_FAILONERROR, false);

        $headers = curl_exec($c);
        if(defined('CURLINFO_RESPONSE_CODE')){
            $http_code = intval(curl_getinfo($c, CURLINFO_RESPONSE_CODE));
        }else{
            $info = curl_getinfo($c);
            if(isset($info['http_code']) && !empty($info['http_code'])){
                $http_code = intval($info['http_code']);
            }else{
                $http_code = 0;
            }
        }

        $curl_error_code = curl_errno($c);

        // if the curl request ultimately got a http code
        if(!empty($http_code)){
            // return the code
            return $http_code;
        }elseif(!empty($curl_error_code)){
            // if we got a curl error, return that
            return $curl_error_code;
        }

        return 925;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return array
     */
    public static function getResponseCodes($urls = array(), $head_call = false)
    {
        $site_protocol = (is_ssl()) ? 'https:': 'http:';
        $return_urls = array();
        $good_urls = array();
        foreach($urls as $url){
            // if a url was provided and it's formatted correctly, add it to the list to process
            if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 2) == '//') && parse_url($url, PHP_URL_HOST)){
                // the current URL is using a relative protocol
                if(strpos($url, '//') === 0){
                    // add the current site's protocol to it so cURL doesn't have a problem with it
                    $url = $site_protocol . $url;
                }
                $good_urls[] = $url;
            }elseif(!empty($url) && strpos($url, '/') === 0){
                // if the URL is relative, make it absolute for the so we can scan it
                $good_urls[] = Wpil_Settings::makeLinkAbsolute($url);
            }else{
                // if it wasn't, add it to the return list as a 925
                $return_urls[$url] = 925;
            }
        }

        // if there are good urls
        if(!empty($good_urls)){
            // get the curl response codes for each of them
            $codes = self::getResponseCodesCurl($good_urls, $head_call);
            // and merge the reponses into the return links
            $return_urls = array_merge($return_urls, $codes);
        }

        return $return_urls;
    }

    public static function getResponseCodesCurl($urls, $head_call = false) {
        $start = microtime(true);
        $redirect_codes = array(301, 302, 307);
        $user_ip = get_transient('wpil_site_ip_address');
        $return_urls = array();

        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('wpil_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the multihandle
        $mh = curl_multi_init();

        // if we're debugging curl
        if(WPIL_DEBUG_CURL){
            // setup the log files
            $verbose = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_log.log', 'a');     // logs the actions that curl goes through in contacting the server
            $connection = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_info.log', 'a'); // logs the result of contacting the server.
        }

        $handles = array();
        foreach($urls as $url){
            // create the curl handle and add it to the list keyed with the url its using
            $handles[$url] = curl_init(html_entity_decode($url));

            // create the list of headers to make the cURL request with
            $request_headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: max-age=0, no-cache',
                'Pragma: ',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?0',
                'Host: ' . parse_url($url, PHP_URL_HOST),
                'Referer: ' . site_url(),
                'User-Agent: ' . WPIL_DATA_USER_AGENT,
            );

            if(!empty($user_ip)){
                $request_headers[] = 'X-Real-Ip: ' . $user_ip;
            }

            if($head_call){
                $request_headers[] = 'Connection: close';
            }else{
                $request_headers[] = 'Connection: keep-alive';
                $request_headers[] = 'Keep-Alive: 300';
            }

            curl_setopt($handles[$url], CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($handles[$url], CURLOPT_HEADER, true);
            curl_setopt($handles[$url], CURLOPT_FILETIME, true);
            curl_setopt($handles[$url], CURLOPT_NOBODY, true);
            curl_setopt($handles[$url], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handles[$url], CURLOPT_MAXREDIRS, 10);
            curl_setopt($handles[$url], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handles[$url], CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handles[$url], CURLOPT_TIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_COOKIEFILE, null);
            curl_setopt($handles[$url], CURLOPT_FORBID_REUSE, true);
            curl_setopt($handles[$url], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($handles[$url], CURLOPT_COOKIESESSION, true);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYPEER, false);

            $curl_version = curl_version();
            if (version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
                curl_setopt($handles[$url], CURLOPT_SSL_FALSESTART, true);
            }

            if(false === $head_call){
                curl_setopt($handles[$url], CURLOPT_HTTPGET, true);
            }

            //Set the proxy configuration. The user can provide this in wp-config.php
            if(defined('WP_PROXY_HOST')){
                curl_setopt($handles[$url], CURLOPT_PROXY, WP_PROXY_HOST);
            }
            if(defined('WP_PROXY_PORT')){
                curl_setopt($handles[$url], CURLOPT_PROXYPORT, WP_PROXY_PORT);
            }
            if(defined('WP_PROXY_USERNAME')){
                $auth = WP_PROXY_USERNAME;
                if(defined('WP_PROXY_PASSWORD')){
                    $auth .= ':' . WP_PROXY_PASSWORD;
                }
                curl_setopt($handles[$url], CURLOPT_PROXYUSERPWD, $auth);
            }

            //Make CURL return a valid result even if it gets a 404 or other error.
            curl_setopt($handles[$url], CURLOPT_FAILONERROR, false);

            // if we're debugging curl
            if(WPIL_DEBUG_CURL){
                // set curl to verbose logging and set where to write it to
                curl_setopt($handles[$url], CURLOPT_VERBOSE, true);
                curl_setopt($handles[$url], CURLOPT_STDERR, $verbose);
            }

            // and add it to the multihandle
            curl_multi_add_handle($mh, $handles[$url]);
        }

        // if there are handles, execute the multihandle
        if(!empty($handles)){
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
        }

        // get any error codes from the operations
        $curl_codes = array();
        foreach($handles as $handle){
            $info = curl_multi_info_read($mh);
            $handle_int = intval($info['handle']);
            if(isset($info['result'])){
                $curl_codes[$handle_int] = $info['result'];
            }else{
                $curl_codes[$handle_int] = 0;
            }
        }

        // when the multihandle is finished, go over the handles and process the responses
        foreach($handles as $handle_url => $handle){
            $handle_int = intval($handle);
            $http_code = intval(curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
            $curl_error_code = (isset($curl_codes[$handle_int])) ? $curl_codes[$handle_int]: 0;

            // if we're debugging curl
            if(WPIL_DEBUG_CURL){
                // save the results of the connection
                fwrite($connection, print_r(curl_getinfo($handle),true));
            }

            // if the curl request ultimately got a http code
            if(!empty($http_code)){
                // if the code is for a redirect and we have some time to chase it
                if(in_array($http_code, $redirect_codes) && (microtime(true) - $start) < 15){
                    // get the url from the curl data
                    $new_url = trim(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL));
                    if(!empty($new_url)){
                        // call _that_ url to see what happens and add the response to the link list
                        $return_urls[$handle_url] = self::getResponseCodeCurl($new_url);
                    }
                }else{
                    // if the code wasn't a redirect or we don't have the time to check, add the code to the list
                    $return_urls[$handle_url] =  $http_code;
                }
            }elseif(!empty($curl_error_code)){
                // curl error list: https://curl.haxx.se/libcurl/c/libcurl-errors.html
                // useful for diagnosing errors < 100
                $return_urls[$handle_url] = $curl_error_code;
            }

            // if a status hasn't been added to the link yet
            if(!isset($return_urls[$handle_url])){
                // mark it as 925
                $return_urls[$handle_url] = 925;
            }

            // close the current handle
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);
        }

        // close the multi handle
        curl_multi_close($mh);

        return $return_urls;
    }

    /**
     * Get link title by URL
     */
    public static function getLinkTitle()
    {
        $link = !empty($_POST['link']) ? $_POST['link'] : '';
        $title = '';
        $id = '';
        $type = '';
        $date = __('Not Available', 'wpil');

        if ($link) {
            if (self::isInternal($link)) {
                $post_id = url_to_postid($link);
                if ($post_id) {
                    $post = get_post($post_id);
                    $title = $post->post_title;
                    $link = '/' . $post->post_name;
                    $id = $post_id;
                    $type = 'post';
                    $date = get_the_date('F j, Y', $post_id);
                } else {
                    $slugs = array_filter(explode('/', $link));
                    $term = Wpil_Term::getTermBySlug(end($slugs));
                    if (!empty($term)) {
                        $title = $term->name;
                        $link = get_term_link($term->term_id);
                        $id = $term->term_id;
                        $type = 'term';
                    }
                }
            }

            //get title if link is not post or term
            if (!$title) {
                $str = file_get_contents($link);
                if(strlen($str)>0){
                    $str = trim(preg_replace('/\s+/', ' ', $str)); // supports line breaks inside <title>
                    preg_match("/\<title\>(.*)\<\/title\>/i",$str,$title); // ignore case
                    $title = $title[1];
                }
            }

            echo json_encode([
                'title' => $title,
                'link' => $link,
                'id' => $id,
                'type' => $type,
                'date' => $date
            ]);
        }

        die;
    }

    /**
     * Remove class "wpil_internal_link" from links
     */
    public static function removeLinkClass()
    {
        global $wpdb;

        $wpdb->get_results("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, 'wpil_internal_link', '') WHERE post_content LIKE '%wpil_internal_link%'");
    }

    /**
     * Add link to ignore list
     */
    public static function addLinkToIgnore()
    {
        $error = false;
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $type = !empty($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
        $site_url = (isset($_POST['site_url']) && !empty($_POST['site_url'])) ? esc_url_raw($_POST['site_url']): null;
        $origin = (isset($_POST['post_origin'])) ? $_POST['post_origin']: null;

        if ($id && $type) {
            // if the object is known to be external
            if($origin === 'external'){
                // create an external post object
                $post = new Wpil_Model_ExternalPost(array('post_id' => $id, 'type' => $type, 'site_url' => $site_url));
            }else{
                // otherwise, assume it's an internal post object
                $post = new Wpil_Model_Post($id, $type);
            }

            $link = $post->getLinks()->view;

            if (!empty($link)) {
                $links = get_option('wpil_ignore_links');
                if (!empty($links)) {
                    $links_array = explode("\n", $links);
                    if (!in_array($link, $links_array)) {
                        $links .= "\n" . $link;
                    }
                } else {
                    $links = $link;
                }
                // clear any ignore link cache that exists
                delete_transient('wpil_ignore_links');
                // save the ignore link
                update_option('wpil_ignore_links', $links);
            } else {
                $error = 'Empty post link';
            }
        } else {
            $error = 'Wrong data';
        }

        echo json_encode(['error' => $error]);
        die;
    }

    /**
     * Clean link from trash symbols
     *
     * @param $link
     * @return string
     */
    public static function clean($link)
    {
        $link = str_replace(['http://', 'https://', '//www.'], '//', strtolower(trim($link)));
        if (substr($link, -1) == '/') {
            $link = substr($link, 0, -1);
        }

        return $link;
    }

    /**
     * Processes and formats urls for comparative purposes inside of Link Whisper.
     * That way, we have a nice standard for comparing if links are basicalls the same, even if there's a few differences in text.
     * Not intended for use when inserting links, its just for when checking to see if two links are the same
     **/
    public static function normalize_url($url){
        // first clean the url
        $url = self::clean($url);
        // decode the double encoded & signs
        $url = str_replace(array('&#038;', '&&amp;'), '&', $url);
        // decode the normally encoded & signs
        $url = str_replace(array('#038;', '&amp;'), '&', $url);

        // and return the url
        return $url;
    }

    /**
     * Updates an existing link in a post with a new link
     * 
     * @param $post_id
     * @param $post_type
     * @param $old_link
     * @param $new_link
     * @param $anchor (Only used for anchors with no attrs)
     **/
    public static function updateExistingLink($post_id = 0, $post_type = '', $old_link = '', $new_link = '', $anchor = ''){
        if(empty($post_id) || empty($post_type) || empty($old_link) || empty($new_link)){
            return false;
        }

        // get the post we want to update
        $post = new Wpil_Model_Post($post_id, $post_type);

        // if there is a post, update it with the new link
        if(!empty($post)){
            $content = $post->getCleanContent();
            self::updateLinkUrl($content, $old_link, $new_link, $anchor);
            $updated = $post->updateContent($content);

            Wpil_Base::clear_tracked_action('link_url_update');
            Wpil_Post::editors('updateExistingLink', array($post, $old_link, $new_link, $anchor));
            $updated = (!$updated) ? Wpil_Base::action_happened('link_url_update'): $updated;

            return $updated;
        }

        return false;
    }

    /**
     * Updates the URLs of links inside post content!
     **/
    public static function updateLinkUrl(&$content = '', $old_link = '', $new_link = '', $anchor = ''){
        $old = md5($content);
        if($old_link !== '{{wpil-empty-url}}'){
            if(false !== strpos($content, $old_link)){
                $content = str_replace($old_link, $new_link, $content);
            }elseif(false !== strpos($content, str_replace(array('&', '[', ']', '{', '}'), array('&amp;', '%5B', '%5D', '%7B', '%7D'), $old_link))){ // if the link has been encoded
                $old_link = str_replace(array('&', '[', ']', '{', '}'), array('&amp;', '%5B', '%5D', '%7B', '%7D'), $old_link); // encode the version of the old link that we're working with
                $new_link = str_replace(array('&', '[', ']', '{', '}'), array('&amp;', '%5B', '%5D', '%7B', '%7D'), $new_link); // and encode the new link to maintain formatting
                $content = str_replace($old_link, $new_link, $content);
            }elseif(false !== strpos($content, urldecode($old_link))){ // if the url has been encoded
                $content = str_replace(urldecode($old_link), $new_link, $content); // remove the encoded old url and replace it with the new encoded url
            }
        }elseif($old_link === '{{wpil-empty-url}}' && !empty($anchor)){
            $new_tag = '<a href="' . $new_link . '">' . $anchor . '</a>';
            $content = preg_replace('`<a>' . preg_quote($anchor, '`') . '</a>`i', $new_tag,  $content, 1);
        }

        // log if the url update was successful
        $updated = md5($content) !== $old;
        Wpil_Base::track_action('link_url_update', $updated);
    }

    /**
     * Check if link was marked as external
     *
     * @param $link
     * @return bool
     */
    public static function markedAsExternal($link)
    {
        $external_links = Wpil_Settings::getMarkedAsExternalLinks();

        if (in_array($link, $external_links)) {
            return true;
        }

        foreach ($external_links as $external_link) {
            if (substr($external_link, -1) == '*' && strpos($link, substr($external_link, 0, -1)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the given post is at the outbound link limit
     *
     * @param $post
     * @return bool Returns true if the post is at the limit and false if it is not.
     */
    public static function at_max_outbound_links($post)
    {
        $max_outbound_links = get_option('wpil_max_links_per_post', 0);

        if(empty($max_outbound_links) || empty($post)){
            return false;
        }

        $post_link = $post->getLinks()->view;
        $ignore_image_urls = !empty(get_option('wpil_ignore_image_urls', false));
        $ignored_links = Wpil_Settings::getIgnoreLinks();
        $content = $post->getContent();

        //get all links from content
        preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $content, $matches);
        // if there are encoded links
        if(false !== strpos($content, '&lt;a') && false !== strpos($content, '&lt;/a&gt;')){
            // try getting encoded links too
            preg_match_all('`&lt;a[^&]*?href=(\"|\')([^\"\']*?)(\"|\')[^&]*?&gt;([\s\w\W]*?)&lt;\/a&gt;`i', $content, $matches2);
            if(!empty($matches2) && !empty($matches2[0])){
                foreach($matches2 as $key => $values){
                    $matches[$key] = array_merge($matches[$key], $values);
                }

                $m_count = count($matches2[0]);
                for($i = 0; $i < $m_count; $i++){
                    $matches[5][] = '';
                    $matches[6][] = '';
                }
            }
        }

        // make a counter for the links
        $outbound_count = 0;

        //make array with results
        foreach ($matches[0] as $key => $value) {
            $url = '';
            if (!empty($matches[2][$key]) && !empty($matches[4][$key]) && !Wpil_Report::isJumpLink($matches[2][$key], $post_link)) {
                $url = trim($matches[2][$key]);
            }elseif(!empty($matches[5][$key]) && !Wpil_Report::isJumpLink($matches[5][$key], $post_link) ||  // if this is an embed link
                    !empty($matches[6][$key]) && !Wpil_Report::isJumpLink($matches[6][$key], $post_link))    // if this is a link that is inserted in the content as a straight url // Mostly this means its an embed but as case history grows I'll come up with a better notice for the user
            {
                if(!empty($matches[5][$key])){
                    $url = trim($matches[5][$key]);
                }else{
                    $url = trim($matches[6][$key]);
                }
            }

            // skip if the url is empty
            if(empty($url)){
                continue;
            }

            // ignore any links that are being used as buttons
            if(false !== strpos($url, 'javascript:void(0)')){
                continue;
            }

            // if we're making a point to ignore image urls
            if($ignore_image_urls){
                // if the link is an image url, skip to the next match
                if(preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico/i', $url)){
                    continue;
                }
            }

            // if we're ignoring links
            if(!empty($ignored_links)){
                // check to see if this link is on the ignore list
                if(!empty(array_intersect($ignored_links, array($url)))){
                    // if it is, skip to the next
                    continue;
                }else{
                    // if the link wasn't detected with the simple check, see if there's a partial match possible. Mostly this is to allow domain-based ignoring
                    foreach($ignored_links as $link){
                        if(false !== strpos($url, $link)){
                            continue 2;
                        }
                    }
                }
            }

            // filter the URLs with an internal check so users can choose to ignore outbound external or outbound internal if they wish.
            /**
             * @param bool $count_link Should the link be counted in the total? Default is true.
             * @param bool $internal If the current link is internal or not.
             * @param string $url The URL we're currently looking at
             **/
            if(!apply_filters('wpil_max_outbound_links_filter_internal', true, self::isInternal($url), $url)){
                continue;
            }

            $outbound_count++;
        }

        return ($outbound_count >= $max_outbound_links) ? true: false;
    }

    /**
     * Checks to see if the current post is at the limit for Inbound Internal links
     * @param $post
     * @return bool
     **/
    public static function at_max_inbound_links($post){
        $max_inbound_links = get_option('wpil_max_inbound_links_per_post', 0);

        if(empty($max_inbound_links) || empty($post)){
            return false;
        }

        // get the inbound link counts from the stored data
        $inbound_count = $post->getInboundInternalLinks(true);

        return ($inbound_count >= $max_inbound_links) ? true: false;
    }

    /**
     * Checks to see if the supplied text contains a link.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasLink($text = '', $replace_text = ''){

        // if there's no link anywhere to be seen, return false
        if(empty(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>/i', $text))){
            return false;
        }

        // if there is a link in the replace text, return true
        if(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>/i', $replace_text)){
            return true;
        }

        // if there is a link, see if it ends before the replace text
        $replace_start = mb_strpos($text, $replace_text);
        if(preg_match('/<\/a>/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<a [^><]*?(href|src)[^><]*?>/i', mb_substr($text, 0, $replace_start)) || preg_match('/<\/a>/i', mb_substr($text, $replace_start)) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a link
            return true;
        }

        return false;
    }


    /**
     * Checks to see if the supplied text contains a heading tag.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasHeading($text = '', $replace_text = '', $sentence = ''){
        // if there's no heading anywhere to be seen, return false
        if(empty(preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>/i', $text))){
            return false;
        }

        // if there is a heading, see if it ends before the replace text
        $replace_start = mb_strpos($text, $sentence);
        if(preg_match('/<\/h[1-6]>/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<h[1-6][^><]*?>/i', mb_substr($text, 0, $replace_start)) || (preg_match('/<\/h[1-6]>/i', mb_substr($text, $replace_start)) && !preg_match('/<h[1-6][^><]*?>/i', mb_substr($text, $replace_start)) ) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a heading
            return true;
        }

        // if there is a heading in the replace text, return true
        if(substr_count($replace_text, $sentence) > 1 && preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>/i', $replace_text)){
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the current slice of text contains any tags that we don't want to insert a link into
     * 
     * @param string $text
     * @return bool
     **/
    public static function checkForForbiddenTags($text, $replace_text, $sentence, $ignore_links = false){
        if(self::hasLink($text, $replace_text) && !$ignore_links){
            return true;
        }elseif(self::hasHeading($text, $replace_text, $sentence)){
            return true;
        }

        return false;
    }

    public static function remove_all_links_from_text($text = ''){
        if(empty($text)){
            return $text;
        }

        $text = preg_replace('/<a[^>]+>(.*?)<\/a>/', '$1', $text);

        return $text;
    }

    /**
     * Gets all ThirstyAffiliate links in an array keyed with the urls.
     * Caches the results to save processing time later
     **/
    public static function getThirstyAffiliateLinks(){
        global $wpdb;
        $links = get_transient('wpil_thirsty_affiliate_links');

        if(empty($links)){
            // query for the link posts
            $results = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_type` = 'thirstylink'");

            // store a flag if there are no link posts
            if(empty($results)){
                set_transient('wpil_thirsty_affiliate_links', 'no-links', 5 * MINUTE_IN_SECONDS);
                return array();
            }

            // get the urls to the link posts
            $links = array();
            foreach($results as $id){
                $links[] = get_permalink($id);
            }

            // flip the array for easy searching
            $links = array_flip($links);

            // store the results
            set_transient('wpil_thirsty_affiliate_links', $links, 5 * MINUTE_IN_SECONDS);

        }elseif($links === 'no-links'){
            return array();
        }

        return $links;
    }

    /**
     * Checks to see if the supplied text is base64ed.
     * @param string $text The text to check if base64 encoded.
     * @return bool True if the text is base64 encoded, false if the string is empty or not encoded
     **/
    public static function checkIfBase64ed($text = ''){
        if(empty($text)){
            return false;
        }
        $possible = preg_match('`^([A-Za-z0-9+/]{4})*([A-Za-z0-9+/]{3}=|[A-Za-z0-9+/]{2}==)?$`', $text);

        if($possible === 0){
            return false;
        }

        if(!empty(mb_detect_encoding(base64_decode($text)))){
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the given URL is from a "sponsored" domain
     * @param string $url
     * @return bool
     **/
    public static function checkIfSponsoredLink($url = ''){
        $domains = Wpil_Settings::getSponsoredDomains();

        $domain = wp_parse_url($url, PHP_URL_HOST);

        if(empty($domain) && empty($domains)){
            return false;
        }

        return (in_array($domain, $domains, true)) ? true: false;
    }

    /**
     * Filters the supplied link to change the domain from staging to live.
     * Only changes the site's domain & scheme, otherwise leaves the rest of the URL as is
     * 
     * @param string $url The url to filter
     * @return string $url The filtered URL if it's supposed to be filtered.
     **/
    public static function filter_staging_to_live_domain($url = ''){
        // if there's no url, the user isn't filtering staging urls out or relative link mode is active
        if(empty($url) || !get_option('wpil_filter_staging_url', false) || !empty(get_option('wpil_insert_links_as_relative', false)))
        {
            // return the url
            return $url;
        }

        // get the live site's url
        $live_site_url = get_option('wpil_live_site_url', false);
        $staging_site_url = get_option('wpil_staging_site_url', false);
        $home_url = get_home_url();

        // if there's no live site url entered, we're actually on the live site, or this isn't a staging site url
        if( empty($live_site_url) ||
            empty($staging_site_url) ||
            $live_site_url === $staging_site_url || // if the urls are the same
            false !== strpos($home_url, $live_site_url) || // if the current site is the live site
            false !== strpos($live_site_url, $home_url) || // if the current site is the live site from a different direction
            false === strpos($url, $staging_site_url) || // if the url isn't pointed at the staging site
            false === strpos($url, $home_url) || // if the url isn't pointed to the current site
            Wpil_URLChanger::isRelativeLink($url) // or if the link is relative
        ){
            // return the url without changing it
            return $url;
        }

        // now that we've made it past the checks, lets change the staging domain for the live one
        // first, lets try a simple URL replace and see if it's valid
        $test_url = str_replace($staging_site_url, $live_site_url, $url);

        // if there's a url and it's not changed by sending it through esc_url_raw
        if(!empty($test_url) && $test_url === esc_url_raw($test_url)){
            // it's good
            return $test_url;
        }

        // break it into pieces
        $live_site_url = wp_parse_url(sanitize_text_field($live_site_url));

        // break the staging site url into pieces
        $staging_site_url = wp_parse_url(sanitize_text_field($staging_site_url));

        // exit if either url has no host
        if( !isset($live_site_url['host']) || empty($live_site_url['host']) ||
            !isset($staging_site_url['host']) || empty($staging_site_url['host'])
        ){
            return $url;
        }

        $url = str_replace($staging_site_url['host'], $live_site_url['host'], $url);

        // if the scheme was included in both urls, and they are different
        if( isset($live_site_url['scheme']) && !empty($live_site_url['scheme']) &&
            isset($staging_site_url['scheme']) && !empty($staging_site_url['scheme']) &&
            ($live_site_url['host'] !== $staging_site_url['scheme'])
        ){
            // replace the scheme
            $pos = strpos($url, $staging_site_url['scheme']);
            if($pos !== false){
                $url = substr_replace($url, $live_site_url['scheme'], $pos, strlen($staging_site_url['scheme']));
            }
        }

        return $url;
    }

    /**
     * Filters the supplied link to change the domain from live to staging.
     * Only changes the site's domain & scheme, otherwise leaves the rest of the URL as is
     * 
     * @param string $url The url to filter
     * @return string $url The filtered URL if it's supposed to be filtered.
     **/
    public static function filter_live_to_staging_domain($url = ''){
        // if there's no url, the user isn't filtering staging urls out or relative link mode is active
        if(empty($url) || !get_option('wpil_filter_staging_url', false) || !empty(get_option('wpil_insert_links_as_relative', false)))
        {
            // return the url
            return $url;
        }

        // get the live site's url
        $live_site_url = get_option('wpil_live_site_url', false);
        $staging_site_url = get_option('wpil_staging_site_url', false);
        $home_url = get_home_url();

        // if there's no live site url entered, we're actually on the live site, or this isn't a staging site url
        if( empty($live_site_url) ||
            empty($staging_site_url) ||
            $live_site_url === $staging_site_url || // if the urls are the same
            false !== strpos($home_url, $live_site_url) || // if the current site is the live site
            false !== strpos($live_site_url, $home_url) || // if the current site is the live site from a different direction
            false === strpos($url, $live_site_url) || // if the url isn't pointed at the live site
            false !== strpos($url, $home_url) || // if the url is pointed to the current site
            Wpil_URLChanger::isRelativeLink($url) // or if the link is relative
        ){
            // return the url without changing it
            return $url;
        }

        // now that we've made it past the checks, lets change the live domain for the staging one
        // first, lets try a simple URL replace and see if it's valid
        $test_url = str_replace($live_site_url, $staging_site_url, $url);

        // if there's a url and it's not changed by sending it through esc_url_raw
        if(!empty($test_url) && $test_url === esc_url_raw($test_url)){
            // it's good
            return $test_url;
        }

        // break it into pieces
        $live_site_url = wp_parse_url(sanitize_text_field($live_site_url));

        // break the staging site url into pieces
        $staging_site_url = wp_parse_url(sanitize_text_field($staging_site_url));

        // exit if either url has no host
        if( !isset($live_site_url['host']) || empty($live_site_url['host']) ||
            !isset($staging_site_url['host']) || empty($staging_site_url['host'])
        ){
            return $url;
        }

        $url = str_replace($live_site_url['host'], $staging_site_url['host'], $url);

        // if the scheme was included in both urls, and they are different
        if( isset($live_site_url['scheme']) && !empty($live_site_url['scheme']) &&
            isset($staging_site_url['scheme']) && !empty($staging_site_url['scheme']) &&
            ($live_site_url['host'] !== $staging_site_url['scheme'])
        ){
            // replace the scheme
            $pos = strpos($url, $live_site_url['scheme']);
            if($pos !== false){
                $url = substr_replace($url, $staging_site_url['scheme'], $pos, strlen($live_site_url['scheme']));
            }
        }

        return $url;
    }
}
