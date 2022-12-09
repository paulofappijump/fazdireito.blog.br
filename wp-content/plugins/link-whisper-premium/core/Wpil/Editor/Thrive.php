<?php

/**
 * Thrive editor
 *
 * Class Wpil_Editor_Thrive
 */
class Wpil_Editor_Thrive
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $thrive = get_post_meta($post_id, 'tve_updated_post', true);

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, 'tve_content_before_more', true);
            foreach ($meta as $link) {
                $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                if (strpos($thrive, $link['sentence']) === false) {
                    $link['sentence'] = addslashes($link['sentence']);
                }
                Wpil_Post::insertLink($thrive_before, $link['sentence'], $changed_sentence, $force_insert);
                Wpil_Post::insertLink($thrive, $link['sentence'], $changed_sentence, $force_insert);
            }

            update_post_meta($post_id, 'tve_updated_post', $thrive);
            update_post_meta($post_id, 'tve_content_before_more', $thrive_before);
        }

        $template = get_post_meta($post_id, 'tve_landing_page', true);
        // if the post has the Thrive Template active
        if($template){
            $thrive = get_post_meta($post_id, 'tve_updated_post_' . $template, true);

            if($thrive){
                $thrive_before = get_post_meta($post_id, 'tve_content_before_more_', true);
                foreach ($meta as $link) {
                    $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                    $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                    if (strpos($thrive, $link['sentence']) === false) {
                        $link['sentence'] = addslashes($link['sentence']);
                    }
                    Wpil_Post::insertLink($thrive_before, $link['sentence'], $changed_sentence, $force_insert);
                    Wpil_Post::insertLink($thrive, $link['sentence'], $changed_sentence, $force_insert);
                }

                update_post_meta($post_id, 'tve_updated_post_' . $template, $thrive);
                update_post_meta($post_id, 'tve_content_before_more_', $thrive_before);
            }
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
        $content_key = 'tve_updated_post';
        $before_more_key = 'tve_content_before_more';

        $thrive = get_post_meta($post_id, $content_key, true);

        // if we couldn't find the content, see if this is a thrive templated page
        if(empty($thrive)){
            // if it is
            if(get_post_meta($post_id, 'tve_landing_set', true) && $thrive_template = get_post_meta($post_id, 'tve_landing_page', true)){
                // get the template content
                $thrive = get_post_meta($post_id, 'tve_updated_post_' . $thrive_template, true);
                // and update the keys
                $content_key = 'tve_updated_post_' . $thrive_template;
                $before_more_key = 'tve_content_before_more_' . $thrive_template;
            }
        }

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, $before_more_key, true);

            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $thrive,  $matches);
            if (!empty($matches[0])) {
                $url = addslashes($url);
                $anchor = addslashes($anchor);
            }

            $post = new Wpil_Model_Post($post_id); // post is just for compatibility
            $thrive_before = Wpil_Link::deleteLink($post, $url, $anchor, $thrive_before, false);
            $thrive = Wpil_Link::deleteLink($post, $url, $anchor, $thrive, false);

            update_post_meta($post_id, $content_key, $thrive);
            update_post_meta($post_id, $before_more_key, $thrive_before);
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
        $content_key = 'tve_updated_post';
        $before_more_key = 'tve_content_before_more';

        $thrive = get_post_meta($post_id, $content_key, true);

        // if we couldn't find the content, see if this is a thrive templated page
        if(empty($thrive)){
            // if it is
            if(get_post_meta($post_id, 'tve_landing_set', true) && $thrive_template = get_post_meta($post_id, 'tve_landing_page', true)){
                // get the template content
                $thrive = get_post_meta($post_id, 'tve_updated_post_' . $thrive_template, true);
                // and update the keys
                $content_key = 'tve_updated_post_' . $thrive_template;
                $before_more_key = 'tve_content_before_more_' . $thrive_template;
            }
        }

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post_id, $before_more_key, true);
            $matches = Wpil_Keyword::findKeywordLinks($keyword, $thrive);
            if (!empty($matches[0])) {
                $keyword->link = addslashes($keyword->link);
                $keyword->keyword = addslashes($keyword->keyword);
            }

            if ($left_one) {
                Wpil_Keyword::removeNonFirstLinks($keyword, $thrive_before);
                Wpil_Keyword::removeNonFirstLinks($keyword, $thrive);
            } else {
                Wpil_Keyword::removeAllLinks($keyword, $thrive_before);
                Wpil_Keyword::removeAllLinks($keyword, $thrive);
            }

            update_post_meta($post_id, $content_key, $thrive);
            update_post_meta($post_id, $before_more_key, $thrive_before);
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
        $content_key = 'tve_updated_post';
        $before_more_key = 'tve_content_before_more';

        $thrive = get_post_meta($post->id, $content_key, true);

        // if we couldn't find the content, see if this is a thrive templated page
        if(empty($thrive)){
            // if it is
            if(get_post_meta($post->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($post->id, 'tve_landing_page', true)){
                // get the template content
                $thrive = get_post_meta($post->id, 'tve_updated_post_' . $thrive_template, true);
                // and update the keys
                $content_key = 'tve_updated_post_' . $thrive_template;
                $before_more_key = 'tve_content_before_more_' . $thrive_template;
            }
        }

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post->id, $before_more_key, true);
            Wpil_URLChanger::replaceLink($thrive, $url);
            Wpil_URLChanger::replaceLink($thrive_before, $url);

            update_post_meta($post->id, $content_key, $thrive);
            update_post_meta($post->id, $before_more_key, $thrive_before);
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
        $content_key = 'tve_updated_post';
        $before_more_key = 'tve_content_before_more';

        $thrive = get_post_meta($post->id, $content_key, true);

        // if we couldn't find the content, see if this is a thrive templated page
        if(empty($thrive)){
            // if it is
            if(get_post_meta($post->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($post->id, 'tve_landing_page', true)){
                // get the template content
                $thrive = get_post_meta($post->id, 'tve_updated_post_' . $thrive_template, true);
                // and update the keys
                $content_key = 'tve_updated_post_' . $thrive_template;
                $before_more_key = 'tve_content_before_more_' . $thrive_template;
            }
        }

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post->id, $before_more_key, true);
            Wpil_URLChanger::revertURL($thrive, $url);
            Wpil_URLChanger::revertURL($thrive_before, $url);

            update_post_meta($post->id, $content_key, $thrive);
            update_post_meta($post->id, $before_more_key, $thrive_before);
        }
    }

    /**
     * Updates the urls of existing links on a link-by-link basis.
     * For use with the Ajax URL updating functionality
     *
     * @param Wpil_Model_Post $post
     * @param $old_link
     * @param $new_link
     * @param $anchor
     */
    public static function updateExistingLink($post, $old_link, $new_link, $anchor)
    {
        // exit if this is a term or there's no post data
        if(empty($post) || $post->type !== 'post'){
            return;
        }

        $content_key = 'tve_updated_post';
        $before_more_key = 'tve_content_before_more';

        $thrive = get_post_meta($post->id, $content_key, true);

        // if we couldn't find the content, see if this is a thrive templated page
        if(empty($thrive)){
            // if it is
            if(get_post_meta($post->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($post->id, 'tve_landing_page', true)){
                // get the template content
                $thrive = get_post_meta($post->id, 'tve_updated_post_' . $thrive_template, true);
                // and update the keys
                $content_key = 'tve_updated_post_' . $thrive_template;
                $before_more_key = 'tve_content_before_more_' . $thrive_template;
            }
        }

        if (!empty($thrive)) {
            $thrive_before = get_post_meta($post->id, $before_more_key, true);
            Wpil_Link::updateLinkUrl($thrive, $old_link, $new_link, $anchor);
            Wpil_Link::updateLinkUrl($thrive_before, $old_link, $new_link, $anchor);

            update_post_meta($post->id, $content_key, $thrive);
            update_post_meta($post->id, $before_more_key, $thrive_before);
        }
    }
}
