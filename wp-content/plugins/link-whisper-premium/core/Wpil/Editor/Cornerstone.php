<?php

/**
 * Cornerstone Editor by Themeco
 * https://theme.co/cornerstone
 *
 * Class Wpil_Editor_Cornerstone
 */
class Wpil_Editor_Cornerstone
{
    public static $link_processed;
    public static $keyword_links_count;
    public static $link_confirmed;
    public static $force_insert_link;

    /**
     * Obtains the post's text content data from the meta.
     **/
    public static function getContent($post_id = 0){
        $cornerstone = get_post_meta($post_id, '_cornerstone_data', true);
        $editor_not_overridden = empty(get_post_meta($post_id, '_cornerstone_override', true));
        $content = '';

        if(!empty($cornerstone) && $editor_not_overridden){
            $cornerstone = json_decode($cornerstone);
            foreach($cornerstone as $section){
                self::processContent($content, $section);
            }
        }

        return $content;
    }

    /**
     * Processes the Cornerstone editor content to provide us with content for making suggestions with.
     * 
     * @param $content The string of post content that we'll be progressively updating as we go.
     * @param $data The Cornerstone data that we'll be looking through to extract content from
     **/
    public static function processContent(&$content, $data)
    {

        foreach (['accordion_item_content', 'alert_content', 'content', 'modal_content', 'text_subheadline_content', 'quote_content', 'controls_std_content', 'testimonial_content', 'text_content', ] as $key) {
            if (!empty($data->$key) && !('headline' === $data->_type && $key === 'text_content')) {
                $content .= "\n" . $data->$key;
            }
        }

        if (!empty($data->_modules)) {
            foreach ($data->_modules as $module) {
                self::processContent($content, $module);
            }
        }
    }

    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $cornerstone = get_post_meta($post_id, '_cornerstone_data', true);

        // if there's cornerstone data and the editor is active for this post
        if (!empty($cornerstone) && empty(get_post_meta($post_id, '_cornerstone_override', true))) {
            $cornerstone = json_decode($cornerstone);
            foreach ($meta as $link) {

                self::$force_insert_link = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                $before = md5(json_encode($cornerstone));

                self::manageLink($cornerstone, [
                    'action' => 'add',
                    'sentence' => Wpil_Word::replaceUnicodeCharacters($link['sentence']),
                    'replacement' => Wpil_Post::getSentenceWithAnchor($link)
                ]);

                $after = md5(json_encode($cornerstone));

                // if the link hasn't been added to the cornerstone module
                if($before === $after && empty(self::$link_confirmed) && empty(self::$link_processed)){
                    // remove the link from the post content
                    $content = self::removeLinkFromPostContent($link, $content);
                }
            }

            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post_id, '_cornerstone_data', $cornerstone);
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
        $cornerstone = get_post_meta($post_id, '_cornerstone_data', true);

        if (!empty($cornerstone)) {
            $cornerstone = json_decode($cornerstone);
            self::manageLink($cornerstone, [
                'action' => 'remove',
                'url' => Wpil_Word::replaceUnicodeCharacters($url),
                'anchor' => Wpil_Word::replaceUnicodeCharacters($anchor)
            ]);
            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post_id, '_cornerstone_data', $cornerstone);
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
        $cornerstone = get_post_meta($post_id, '_cornerstone_data', true);

        if (!empty($cornerstone)) {
            $cornerstone = json_decode($cornerstone);
            $keyword->link = Wpil_Word::replaceUnicodeCharacters($keyword->link);
            $keyword->keyword = Wpil_Word::replaceUnicodeCharacters($keyword->keyword);
            self::$keyword_links_count = 0;
            self::manageLink($cornerstone, [
                'action' => 'remove_keyword',
                'keyword' => $keyword,
                'left_one' => $left_one
            ]);

            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post_id, '_cornerstone_data', $cornerstone);
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
        $cornerstone = get_post_meta($post->id, '_cornerstone_data', true);

        if (!empty($cornerstone)) {
            $cornerstone = json_decode($cornerstone);
            $url->old = Wpil_Word::replaceUnicodeCharacters($url->old);
            $url->new = Wpil_Word::replaceUnicodeCharacters($url->new);
            self::manageLink($cornerstone, [
                'action' => 'replace_urls',
                'url' => $url,
            ]);

            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post->id, '_cornerstone_data', $cornerstone);
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
        $cornerstone = get_post_meta($post->id, '_cornerstone_data', true);

        if (!empty($cornerstone)) {
            $cornerstone = json_decode($cornerstone);
            $url->old = Wpil_Word::replaceUnicodeCharacters($url->old);
            $url->new = Wpil_Word::replaceUnicodeCharacters($url->new);
            self::manageLink($cornerstone, [
                'action' => 'revert_urls',
                'url' => $url,
            ]);

            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post->id, '_cornerstone_data', $cornerstone);
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

        $cornerstone = get_post_meta($post->id, '_cornerstone_data', true);

        if (!empty($cornerstone)) {
            $cornerstone = json_decode($cornerstone);
            self::manageLink($cornerstone, [
                'action' => 'update_existing_link',
                'old_link' => Wpil_Word::replaceUnicodeCharacters($old_link),
                'new_link' => Wpil_Word::replaceUnicodeCharacters($new_link),
                'anchor' => $anchor,
            ]);

            $cornerstone = addslashes(json_encode($cornerstone));
            update_post_meta($post->id, '_cornerstone_data', $cornerstone);
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
        foreach (['accordion_item_content', 'alert_content', 'content', 'modal_content', 'text_subheadline_content', 'quote_content', 'controls_std_content', 'testimonial_content', 'text_content', ] as $key) {
            if (!empty($item->$key) && !('headline' === $item->_type && $key === 'text_content')) {
                self::manageBlock($item->$key, $params);
            }
        }

        if (!empty($item->_modules)) {
            foreach ($item->_modules as $module) {
                if (!self::$link_processed) {
                    self::checkItem($module, $params);
                }
            }
        }
    }

    /**
     * Checks the given item to see if its a heading and it can have links added to it.
     * @param object $item The cornerstone item that we're going to check
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
     * Remove links from the post content when they're not added to the Cornerstone content
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
        preg_match('`<a .+?' . preg_quote(Wpil_Word::replaceUnicodeCharacters($url), '`') . '.+?>' . preg_quote(Wpil_Word::replaceUnicodeCharacters($anchor), '`') . '</a>`i', $block_unicode,  $matches);

        if (!empty($matches[0])) {
            $block = $block_unicode;
            $before = md5($block);
            $block = preg_replace('|<a [^>]+' . preg_quote(Wpil_Word::replaceUnicodeCharacters($url), '`') . '[^>]+>' . preg_quote(Wpil_Word::replaceUnicodeCharacters($anchor), '`') . '</a>|i', $anchor,  $block);
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
        preg_match('`(href|url)=[\'\"]' . preg_quote($old_link, '`') . '\/*[\'\"]`i', $block, $matches);
        if (!empty($matches)) {
            Wpil_Link::updateLinkUrl($block, $old_link, $new_link, $anchor);
        }
    }
}