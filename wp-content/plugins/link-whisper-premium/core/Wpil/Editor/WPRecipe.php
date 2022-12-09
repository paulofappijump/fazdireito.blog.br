<?php

/**
 * Recipe editor
 *
 * Class Wpil_Editor_WPRecipe
 */
class Wpil_Editor_WPRecipe
{
    /**
     * Add links
     *
     * @param $meta
     * @param $post_id
     */
    public static function addLinks($meta, $post_id, &$content)
    {
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            foreach ($meta as $link) {
                $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                $changed_sentence = Wpil_Post::getSentenceWithAnchor($link);
                if (strpos($recipe, $link['sentence']) === false) {
                    $link['sentence'] = addslashes($link['sentence']);
                }
                Wpil_Post::insertLink($recipe, $link['sentence'], $changed_sentence, $force_insert);
            }

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            preg_match('|<a .+'.$url.'.+>'.$anchor.'</a>|i', $recipe,  $matches);
            if (!empty($matches[0])) {
                $url = addslashes($url);
                $anchor = addslashes($anchor);
            }

            $recipe = preg_replace('|<a [^>]+'.$url.'[^>]+>'.$anchor.'</a>|i', $anchor,  $recipe);

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post_id, 'wprm_notes', true);

        if (!empty($recipe)) {
            $matches = Wpil_Keyword::findKeywordLinks($keyword, $recipe);
            if (!empty($matches[0])) {
                $keyword->link = addslashes($keyword->link);
                $keyword->keyword = addslashes($keyword->keyword);
            }

            if ($left_one) {
                Wpil_Keyword::removeNonFirstLinks($keyword, $recipe);
            } else {
                Wpil_Keyword::removeAllLinks($keyword, $recipe);
            }

            update_post_meta($post_id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post->id, 'wprm_notes', true);

        if (!empty($recipe)) {
            Wpil_URLChanger::replaceLink($recipe, $url, true, $post);

            update_post_meta($post->id, 'wprm_notes', $recipe);
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
        $recipe = get_post_meta($post->id, 'wprm_notes', true);

        if (!empty($recipe)) {
            Wpil_URLChanger::revertURL($recipe, $url);

            update_post_meta($post->id, 'wprm_notes', $recipe);
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

        $recipe = get_post_meta($post->id, 'wprm_notes', true);

        if (!empty($recipe)) {
            Wpil_Link::updateLinkUrl($recipe, $old_link, $new_link, $anchor);

            update_post_meta($post->id, 'wprm_notes', $recipe);
        }
    }
}
