<?php

/**
 * Elementor editor
 *
 * Class Wpil_Editor_Elementor
 */
class Wpil_Editor_Elementor
{
    public static $link_processed;
    public static $keyword_links_count;
    public static $link_confirmed;
    public static $document;
    public static $current_id;
    public static $remove_unprocessable = true;
    public static $force_insert_link;

    /**
     * Gets the Elementor content for making suggestions
     *
     * @param int $post_id The id of the post that we're trying to get information for.
     * @param bool $editor_active Have we already checked to see if the Elementor editor is active for this post?
     */
    public static function getContent($post_id, $editor_active = false, $remove_unprocessable = true)
    {
        self::$remove_unprocessable = $remove_unprocessable;
        $elementor = get_post_meta($post_id, '_elementor_data', true);
        $content = '';
        // if there's elementor data and the editor is active for this post
        if (!empty($elementor) && ($editor_active || !empty(get_post_meta($post_id, '_elementor_edit_mode', true)) ) ) {
            $elementor = (is_string($elementor)) ? json_decode($elementor) : $elementor;
            if(is_array($elementor)){
                foreach($elementor as $data){
                    self::getProcessableData($data, $content, $post_id);
                }
            }
        }
        return $content;
    }

    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        if( !defined('ELEMENTOR_VERSION') || // if Elementor is not active
            !class_exists('\Elementor\Plugin') || // or the plugin main class isn't active
            !isset(\Elementor\Plugin::$instance) || empty(\Elementor\Plugin::$instance) || // or we have don't have an instance
            !isset(\Elementor\Plugin::$instance->db) || empty(\Elementor\Plugin::$instance->db) || // or there's no db method
            empty($post_id) || // or somehow there isn't a post id
            !\Elementor\Plugin::$instance->db->is_built_with_elementor($post_id)) // or the page isn't built with elementor
        {
            // exit
            return;
        }

        $elementor = get_post_meta($post_id, '_elementor_data', true);

        // if there's elementor data and the editor is active for this post
        if (!empty($elementor) && !empty(get_post_meta($post_id, '_elementor_edit_mode', true))) {
            $elementor = json_decode($elementor);
            foreach ($meta as $link) {
                self::$force_insert_link = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                $before = md5(json_encode($elementor));

                self::manageLink($elementor, [
                    'action' => 'add',
                    'sentence' => Wpil_Word::replaceUnicodeCharacters($link['sentence']),
                    'replacement' => Wpil_Post::getSentenceWithAnchor($link)
                ]);

                $after = md5(json_encode($elementor));

                // if the link hasn't been added to the elementor module
                if($before === $after && empty(self::$link_confirmed) && empty(self::$link_processed)){
                    // remove the link from the post content
                    $content = self::removeLinkFromPostContent($link, $content);
                }
            }

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
        }
    }

    /**
     * Delete link
     *
     * @param $post_id
     * @param $url
     * @param $anchor
     */
    public static function deleteLink($post_id, $url, $anchor)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            self::manageLink($elementor, [
                'action' => 'remove',
                'url' => Wpil_Word::replaceUnicodeCharacters($url),
                'anchor' => Wpil_Word::replaceUnicodeCharacters($anchor)
            ]);
            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
        }
    }

    /**
     * Remove keyword links
     *
     * @param $keyword
     * @param $post_id
     * @param bool $left_one
     */
    public static function removeKeywordLinks($keyword, $post_id, $left_one = false)
    {
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $keyword->link = Wpil_Word::replaceUnicodeCharacters($keyword->link);
            $keyword->keyword = Wpil_Word::replaceUnicodeCharacters($keyword->keyword);
            self::$keyword_links_count = 0;
            self::manageLink($elementor, [
                'action' => 'remove_keyword',
                'keyword' => $keyword,
                'left_one' => $left_one
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post_id, '_elementor_data', $elementor);
        }
    }

    /**
     * Replace URLs
     *
     * @param $post
     * @param $url
     */
    public static function replaceURLs($post, $url)
    {
        $elementor = get_post_meta($post->id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $url->old = Wpil_Word::replaceUnicodeCharacters($url->old);
            $url->new = Wpil_Word::replaceUnicodeCharacters($url->new);
            self::manageLink($elementor, [
                'action' => 'replace_urls',
                'url' => $url,
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post->id, '_elementor_data', $elementor);
        }
    }

    /**
     * Revert URLs
     *
     * @param $post
     * @param $url
     */
    public static function revertURLs($post, $url)
    {
        $elementor = get_post_meta($post->id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            $url->old = Wpil_Word::replaceUnicodeCharacters($url->old);
            $url->new = Wpil_Word::replaceUnicodeCharacters($url->new);
            self::manageLink($elementor, [
                'action' => 'revert_urls',
                'url' => $url,
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post->id, '_elementor_data', $elementor);
        }
    }

    /**
     * Updates the urls of existing links on a link-by-link basis.
     * For use with the Ajax URL updating functionality
     *
     * @param Wpil_Model_Post $post
     * @param string $old_link
     * @param string $new_link
     * @param string $anchor
     */
    public static function updateExistingLink($post, $old_link, $new_link, $anchor)
    {
        // exit if this is a term or there's no post data
        if(empty($post) || $post->type !== 'post'){
            return;
        }

        $elementor = get_post_meta($post->id, '_elementor_data', true);

        if (!empty($elementor)) {
            $elementor = json_decode($elementor);
            self::manageLink($elementor, [
                'action' => 'update_existing_link',
                'old_link' => Wpil_Word::replaceUnicodeCharacters($old_link),
                'new_link' => Wpil_Word::replaceUnicodeCharacters($new_link),
                'anchor' => $anchor,
            ]);

            $elementor = addslashes(json_encode($elementor));
            update_post_meta($post->id, '_elementor_data', $elementor);
        }
    }

    /**
     * Find all text elements
     *
     * @param $data
     * @param $params
     */
    public static function manageLink(&$data, $params)
    {
        self::$link_processed = false;
        self::$link_confirmed = false;
        if (is_countable($data)) {
            foreach ($data as $item) {
                self::checkItem($item, $params);
            }
        }
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function checkItem(&$item, $params)
    {
        if (!empty($item->widgetType) && (!in_array($item->widgetType, ['heading', 'button', 'call-to-action']) || $item->widgetType === 'heading' && self::canAddLinksToHeading($item)) ) {
            if (!empty($item->settings->icon_list)) {
                foreach ($item->settings->icon_list as $key => $icon) {
                    self::manageBlock($item->settings->icon_list[$key]->text, $params);
                }
            }
            if (isset($item->settings) && isset($item->settings->tabs) && !empty($item->settings->tabs)) {
                foreach ($item->settings->tabs as $key => $tab) {
                    foreach(array('tab_content', 'faq_answer', 'accordion_content') as $tab_index){
                        if( isset($item->settings->tabs[$key]->$tab_index) && 
                            !empty($item->settings->tabs[$key]->$tab_index))
                        {
                            self::manageBlock($item->settings->tabs[$key]->$tab_index, $params);
                        }
                    }
                }
            }

            // look over any HBTheme repeating modules // todo abstract into a more refined form when more data is available. There will be other module packs that have items with sub content in the same way as this.
            foreach (['accordions', 'images'] as $key) {
                if (!empty($item->settings->$key)) {
                    foreach($item->settings->$key as $sub_item){
                        foreach(['desc', 'description', 'caption'] as $content_type){
                            self::manageBlock($sub_item->$content_type, $params);
                        }
                    }
                }
            }

            foreach (['editor', 'title', 'caption', 'text', 'description_text', 'testimonial_content', 'html', 'alert_title', 'alert_description', 'description', 'faq_answer', 'accordion_content', 'protected_content_text', 'blockquote_content'] as $key) {
                if (!empty($item->settings->$key)) {
                    self::manageBlock($item->settings->$key, $params);
                }
            }
        }

        if (!empty($item->elements)) {
            foreach ($item->elements as $element) {
                if (!self::$link_processed) {
                    self::checkItem($element, $params);
                }
            }
        }
    }

    /**
     * Checks the given item to see if its a heading and it can have links added to it.
     * @param object $item The Elementor item that we're going to check
     * @return bool
     **/
    public static function canAddLinksToHeading($item){
        if($item->widgetType !== 'heading'){
            return true; // possibly remove this. I'm returning true in case I accidentally use this somewhere that doesn't strictly check for headings, but this could allow false positives.
        }

        // if a custom heading element has been selected, and the element is a div, span, or p
        if(isset($item->settings) && isset($item->settings->header_size) && in_array($item->settings->header_size, array('div', 'span', 'p'))){
            // return that a link can be inserted here
            return true;
        }

        return false;
    }

    /**
     * Remove links from the post content when they're not added to the Elementor content
     **/
    public static function removeLinkFromPostContent($link, $content){
        $sentence_with_anchor = Wpil_Post::getSentenceWithAnchor($link);

        if(!empty($sentence_with_anchor) && false !== strpos($content, $sentence_with_anchor)){
            $content2 = preg_replace('`' . preg_quote($sentence_with_anchor, '`') . '`', $link['sentence'], $content, 1);
            if(!empty($content2)){
                $content = $content2;
            }
        }

        return $content;
    }

    /**
     * Route current action
     *
     * @param $block
     * @param $params
     */
    public static function manageBlock(&$block, $params)
    {
        if ($params['action'] == 'add') {
            self::addLinkToBlock($block, $params['sentence'], $params['replacement']);
        } elseif ($params['action'] == 'remove') {
            self::removeLinkFromBlock($block, $params['url'], $params['anchor']);
        } elseif ($params['action'] == 'remove_keyword') {
            self::removeKeywordFromBlock($block, $params['keyword'], $params['left_one']);
        } elseif ($params['action'] == 'replace_urls') {
            self::replaceURLInBlock($block, $params['url']);
        } elseif ($params['action'] == 'revert_urls') {
            self::revertURLInBlock($block, $params['url']);
        } elseif ($params['action'] == 'update_existing_link') {
            self::updateURLInBlock($block, $params['old_link'], $params['new_link'], $params['anchor']);
        }
    }

    /**
     * Insert link into block
     *
     * @param $block
     * @param $sentence
     * @param $replacement
     */
    public static function addLinkToBlock(&$block, $sentence, $replacement)
    {
        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);
        if (strpos($block_unicode, $sentence) !== false) {
            $block = $block_unicode;
            Wpil_Post::insertLink($block, $sentence, $replacement, self::$force_insert_link);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
            self::$link_processed = true;
        }elseif(false !== strpos($block_unicode, Wpil_Word::replaceUnicodeCharacters($replacement)) || false !== strpos($block_unicode, 'wpil_keyword_link') || false !== strpos($block_unicode, 'data-wpil-keyword-link')){
            self::$link_confirmed = true;
        }
    }

    /**
     * Remove link from block
     *
     * @param $block
     * @param $url
     * @param $anchor
     */
    public static function removeLinkFromBlock(&$block, $url, $anchor)
    { 
        // decode the url if it's base64 encoded
        if(base64_encode(base64_decode($url, true)) === $url){
            $url = base64_decode($url);
        }

        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);
        preg_match('`<a [^><]*?' . preg_quote(Wpil_Word::replaceUnicodeCharacters($url), '`') . '[^><]*?>(?:<(?!a)[a-zA-Z]+?[^><]*?>)*?' . preg_quote(Wpil_Word::replaceUnicodeCharacters($anchor), '`') . '(?:<(?!a)[a-zA-Z/]+?[^><]*?>)*?</a>`i', $block_unicode,  $matches);

        if (!empty($matches[0])) {
            $block = $block_unicode;
            $before = md5($block);
            $block = Wpil_Link::deleteLink(false, $url, $anchor, $block, false);
            $after = md5($block);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
            if($before !== $after){
                self::$link_processed = true;
            }
        }
    }

    /**
     * Remove keyword links
     *
     * @param $block
     * @param $keyword
     * @param $left_one
     */
    public static function removeKeywordFromBlock(&$block, $keyword, $left_one)
    {
        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);
        $matches = Wpil_Keyword::findKeywordLinks($keyword, $block_unicode);
        if (!empty($matches[0])) {
            $block = $block_unicode;
            if (!$left_one || self::$keyword_links_count) {
                Wpil_Keyword::removeAllLinks($keyword, $block);
            }
            if($left_one && self::$keyword_links_count == 0 and count($matches[0]) > 1) {
                Wpil_Keyword::removeNonFirstLinks($keyword, $block);
            }
            self::$keyword_links_count += count($matches[0]);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
        }
    }


    /**
     * Replace URL in block
     *
     * @param $block
     * @param $url
     */
    public static function replaceURLInBlock(&$block, $url)
    {
        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);

        if (Wpil_URLChanger::hasUrl($block_unicode, $url)) {
            $block = $block_unicode;
            Wpil_URLChanger::replaceLink($block, $url);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
        }
    }

    /**
     * Revert URL in block
     *
     * @param $block
     * @param $url
     */
    public static function revertURLInBlock(&$block, $url)
    {
        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);

        preg_match('`data\\\u2013wpil=\"url\" (href|url)=[\'\"]' . preg_quote($url->new, '`') . '\/*[\'\"]`i', $block_unicode, $matches);
        if (!empty($matches)) {
            $block = $block_unicode;
            $block = preg_replace('`data\\\u2013wpil=\"url\" (href|url)=([\'\"])' . $url->new . '\/*([\'\"])`i', '$1=$2' . $url->old . '$3', $block);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
        }
    }

    public static function updateURLInBlock(&$block, $old_link, $new_link, $anchor){
        $block_unicode = Wpil_Word::replaceUnicodeCharacters($block);

        preg_match('`(href|url)=[\'\"]' . preg_quote($old_link, '`') . '\/*[\'\"]`i', $block_unicode, $matches);
        if (!empty($matches)) {
            $block = $block_unicode;
            Wpil_Link::updateLinkUrl($block, $old_link, $new_link, $anchor);
            $block = Wpil_Word::replaceUnicodeCharacters($block, true);
        }
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function getProcessableData($item, &$content, $post_id)
    {
        if (!empty($item->widgetType) && (!in_array($item->widgetType, ['heading', 'button', 'call-to-action']) || $item->widgetType === 'heading' && self::canAddLinksToHeading($item) || !self::$remove_unprocessable) ) {
            if (!empty($item->settings->icon_list)) {
                foreach ($item->settings->icon_list as $key => $icon) {
                    $content .= "\n" . $item->settings->icon_list[$key]->text;
                }
            }
            if (isset($item->settings) && isset($item->settings->tabs) && !empty($item->settings->tabs)) {
                foreach ($item->settings->tabs as $key => $tab) {
                    foreach(array('tab_content', 'faq_answer', 'accordion_content') as $tab_index){
                        if( isset($item->settings->tabs[$key]->$tab_index) && 
                            !empty($item->settings->tabs[$key]->$tab_index))
                        {
                            $content .= "\n" . $item->settings->tabs[$key]->$tab_index;
                        }
                    }
                }
            }

            // look over any HBTheme repeating modules // todo abstract into a more refined form when more data is available. There will be other module packs that have items with sub content in the same way as this.
            foreach (['accordions', 'images'] as $key) {
                if (!empty($item->settings->$key)) {
                    foreach($item->settings->$key as $sub_item){
                        foreach(['desc', 'description', 'caption'] as $content_type){
                            $content .= "\n" . $sub_item->$content_type;
                        }
                    }
                }
            }

            if( in_array($item->widgetType, array('image'), true) || 
                (!self::$remove_unprocessable && ( (isset($item->settings) && isset($item->settings->link)) || in_array($item->widgetType, array('woocommerce-products'), true) ) )
            ){
                // if this is a WooCommerce item
                if(in_array($item->widgetType, array('woocommerce-products'), true) && class_exists('WooCommerce')){
                    // include the WooCommerce frontend files so we can render the item
                    $woo = WooCommerce::instance();
                    if(!empty($woo) && is_object($woo) && method_exists($woo, 'frontend_includes')){
                        $woo->frontend_includes();
                    }
                }

                $document = self::getDocument($post_id);
                try {
                    $content .= "\n" . $document->render_element( json_decode(json_encode($item), true) );
                } catch (Throwable $t) {
                } catch (Exception $e) {
                }

            }else{
                foreach (['editor', 'title', 'caption', 'text', 'description_text', 'testimonial_content', 'html', 'alert_title', 'alert_description', 'description', 'faq_answer', 'accordion_content', 'protected_content_text', 'blockquote_content'] as $key) {
                    if (!empty($item->settings->$key)) {
                        $content .= "\n" . $item->settings->$key;
                    }
                }
            }
        }

        if (!empty($item->elements)) {
            foreach ($item->elements as $element) {
                if (!self::$link_processed) {
                    self::getProcessableData($element, $content, $post_id);
                }
            }
        }
    }

    public static function getDocument($post_id){
        if(empty(self::$document) || ($post_id !== self::$current_id)){
            self::$document = \Elementor\Plugin::$instance->documents->get($post_id);
        }
        self::$current_id = $post_id;

        return self::$document;
    }
}