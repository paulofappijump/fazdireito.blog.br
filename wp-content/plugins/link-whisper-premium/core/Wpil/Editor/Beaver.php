<?php

/**
 * Beaver editor
 *
 * Class Wpil_Editor_Beaver
 */
class Wpil_Editor_Beaver
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $beaver = get_post_meta($post_id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            $serialized_beaver = serialize($beaver);
            foreach ($meta as $link) {
                //change sentence
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                $sentence = trim($link['sentence']); // Don't need to slash
                $is_autolink    = isset($link['keyword_data']);
                $add_same_link  = isset($link['keyword_data']) ? !empty($link['keyword_data']->add_same_link): false;
                $link_once      = isset($link['keyword_data']) ? !empty($link['keyword_data']->link_once): false;
                $force_insert   = isset($meta['keyword_data']) ? !empty($meta['keyword_data']->force_insert): false;

                // check if this is an autolink
                if($is_autolink){
                    // if it is, check if adding the link is inline with the link settings
                    if( false !== strpos($serialized_beaver, $changed_sentence) &&
                      ( !$add_same_link ||  
                        $add_same_link && $link_once)
                    )
                    {
                        // if the autolink settings don't allow link insertion, skip to the next link
                        continue;
                    }
                }

                //update beaver post content
                foreach ($beaver as $key => $item) {
                    foreach (['text', 'html'] as $element) {
                        if (!empty($item->settings->$element) && !isset($item->settings->link)) { // if the element has content that we can process and isn't something that comes with a link
                            if (strpos($item->settings->$element, $sentence) !== false) {
                                $before = md5($beaver[$key]->settings->$element);
                                Wpil_Post::insertLink($beaver[$key]->settings->$element, $sentence, $changed_sentence, $force_insert);
                                $after = md5($beaver[$key]->settings->$element);

                                if($before !== $after && 
                                    (   !$is_autolink                   ||  // this isn't an autolink
                                        !$add_same_link                 ||  // this is an autolink, and it hasn't been specifically set to be inserted multiple times
                                        $add_same_link && $link_once        // this is an autolink, but it's only supposed to be inserted once
                                    ) 
                                ){
                                    // exit these 2 loops
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            update_post_meta($post_id, '_fl_builder_data', $beaver);
            update_post_meta($post_id, '_fl_builder_draft', $beaver);
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
        $beaver = get_post_meta($post_id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            foreach ($beaver as $key => $item) {
                foreach (['text', 'html'] as $element) {
                    if (!empty($item->settings->$element)) {
                        preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $item->settings->$element,  $matches);
                        if (!empty($matches[0])) {
                            $beaver[$key]->settings->$element = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $beaver[$key]->settings->$element);
                        }
                    }
                }
            }

            update_post_meta($post_id, '_fl_builder_data', $beaver);
            update_post_meta($post_id, '_fl_builder_draft', $beaver);
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
        $beaver = get_post_meta($post_id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            $links_count = 0;
            foreach ($beaver as $key => $item) {
                foreach (['text', 'html'] as $element) {
                    if (!empty($item->settings->$element)) {
                        $matches = Wpil_Keyword::findKeywordLinks($keyword, $item->settings->$element);
                        if (!empty($matches[0])) {
                            if (!$left_one || $links_count) {
                                Wpil_Keyword::removeAllLinks($keyword, $beaver[$key]->settings->$element);
                            }
                            if($left_one && $links_count == 0 and count($matches[0]) > 1) {
                                Wpil_Keyword::removeNonFirstLinks($keyword, $beaver[$key]->settings->$element);
                            }
                            $links_count += count($matches[0]);
                        }
                    }
                }
            }

            update_post_meta($post_id, '_fl_builder_data', $beaver);
            update_post_meta($post_id, '_fl_builder_draft', $beaver);
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
        $beaver = get_post_meta($post->id, '_fl_builder_data', true);
        if (!empty($beaver)) {
            foreach ($beaver as $key => $item) {
                foreach (['text', 'html'] as $element) {
                    if (!empty($item->settings->$element)) {
                        if (Wpil_URLChanger::hasUrl($item->settings->$element, $url)) {
                            Wpil_URLChanger::replaceLink($item->settings->$element, $url);
                        }
                    }
                }
            }

            update_post_meta($post->id, '_fl_builder_data', $beaver);
            update_post_meta($post->id, '_fl_builder_draft', $beaver);
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
        $beaver = get_post_meta($post->id, '_fl_builder_data', true);
        if (!empty($beaver)) {
            foreach ($beaver as $key => $item) {
                foreach (['text', 'html'] as $element) {
                    if (!empty($item->settings->$element)) {
                        preg_match('`data-wpil=\"url\" (?:data-wpil-url-old=[\'\"]([a-zA-Z0-9+=]*?)[\'\"] )*(href|url)=[\'\"]' . preg_quote($url->new, '`') . '\/*[\'\"]`i', $item->settings->$element, $matches);
                        if (!empty($matches)) {
                            Wpil_URLChanger::revertURL($item->settings->$element, $url);
                        }
                    }
                }
            }

            update_post_meta($post->id, '_fl_builder_data', $beaver);
            update_post_meta($post->id, '_fl_builder_draft', $beaver);
        }
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

        $beaver = get_post_meta($post->id, '_fl_builder_data', true);

        if (!empty($beaver)) {
            //update beaver post content
            foreach ($beaver as $key => $item) {
                foreach (['text', 'html'] as $element) {
                    if (!empty($item->settings->$element) && !isset($item->settings->link)) { // if the element has content that we can process and isn't something that comes with a link
                        $before = md5($beaver[$key]->settings->$element);
                        Wpil_Link::updateLinkUrl($beaver[$key]->settings->$element, $old_link, $new_link, $anchor);
                        $after = md5($beaver[$key]->settings->$element);

                        // if we've changed the url
                        if($before !== $after){
                            // exit these 2 loops
                            break 2;
                        }
                    }
                }
            }

            update_post_meta($post->id, '_fl_builder_data', $beaver);
            update_post_meta($post->id, '_fl_builder_draft', $beaver);
        }
    }
    
}
