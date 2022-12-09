<?php
$link_external = get_option('wpil_link_external_sites', false);
$taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'names', 'or');
$taxonomies = (!empty($taxonomies)) ? array_keys($taxonomies): array();
$has_suggestions = false;
foreach($phrase_groups as $phrase_group_type => $phrases){
    // omit the external section if external linking isn't enabled
    if(empty($link_external) && 'external_site' === $phrase_group_type){
        continue;
    }

    // output the spacer if this is the external suggestions
    if('external_site' === $phrase_group_type && !empty($phrases)){
        echo '<div style="border-top: solid 2px #ccd0d4; margin: 0 -13px;"></div>';
    }
?>
<div style="display:none"><div><textarea id="wpil-editor-target"></textarea></div></div>
<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table wpil-outbound-links" id="tbl_keywords">
    <?php if (!empty($phrases)) : ?>
        <?php $has_suggestions = true; ?>
        <thead>
        <tr class="wpil-suggestion-table-heading">
            <th>
                <div>
                    <b><?php if('internal_site' === $phrase_group_type){ _e('Add Internal Outbound Links', 'wpil'); }else{ _e('Add Outbound Links to External Sites', 'wpil'); } ?></b>
                    <br />
                    <div style="margin:5px 0 0 0;">
                        <input type="checkbox" id="select_all"><span style="margin:0 0 0 5px; font-weight:300"><?php _e('Check All', 'wpil'); ?></span>
                    </div>
                </div>
            </th>
            <th>
                <div>
                    <br />
                    <b><?php _e('Posts to link to', 'wpil'); ?></b>
                </div>
            </th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($phrases as $key_phrase => $phrase) : ?>
            <tr data-wpil-sentence-id="<?=esc_attr($key_phrase)?>">
                <td class="sentences">
                    <?php foreach ($phrase->suggestions as $suggestion) : ?>
                        <div class="sentence top-level-sentence" data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>">
                            <div class="wpil_edit_sentence_form">
                                <textarea class="wpil_content"><?=$suggestion->sentence_src_with_anchor?></textarea>
                                <span class="button-primary">Save</span>
                                <span class="button-secondary">Cancel</span>
                            </div>
                            <input type="checkbox" name="link_keywords[]" class="chk-keywords" wpil-link-new="">
                            <span class="wpil_sentence_with_anchor"><span class="wpil_sentence" title="<?php esc_attr_e('Double clicking a word will select it.', 'wpil');?>"><?=$suggestion->sentence_with_anchor?></span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="<?php _e('Click to undo changes', 'wpil'); ?>"></span></span>
                            <span class="wpil_edit_sentence link-form-button">| <a href="javascript:void(0)">Edit Sentence</a></span>
                            <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($suggestion->anchor_score).')':''?>
                            <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                            <input type="hidden" name="custom_sentence" value="">
                            <input type="hidden" name="original_sentence_with_anchor" value="<?php echo base64_encode($suggestion->original_sentence_with_anchor)?>">

                            <?php if (Wpil_Settings::fullHTMLSuggestions()) : ?>
                                <div class="raw_html"><?=htmlspecialchars($suggestion->sentence_src_with_anchor)?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                </td>
                <td>
                    <?php if (count($phrase->suggestions) > 1) : ?>
                        <?php 
                            $index = key($phrase->suggestions);
                            $a_post = $phrase->suggestions[$index]->post;

                            if(null === $index){
                                reset($phrase->suggestions);
                                $index = key($phrase->suggestions);
                            }

                            if(empty($a_post)){
                                continue;
                            }

                            $terms = get_terms(array(
                                'taxonomy' => $taxonomies,
                                'hide_empty' => false,
                                'object_ids' => $a_post->id,
                            ));

                            $categories = array();
                            $tags = array();
                            if(!is_wp_error($terms) && !empty($terms)){
                                foreach($terms as $term){
                                    if(get_taxonomy($term->taxonomy)->hierarchical){
                                        $categories[] = $term->name;
                                    }else{
                                        $tags[] = $term->name;
                                    }
                                }

                                $cats_found = count($categories);
                                $tags_found = count($tags);
                                $categories = implode(', ', $categories);
                                $tags = implode(', ', $tags);
                            }
                        ?>
                        <div class="wpil-collapsible-wrapper">
                            <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">
                                <div style="opacity:<?=$phrase->suggestions[$index]->opacity?>" data-id="<?=esc_attr($a_post->id)?>" data-type="<?=esc_attr($a_post->type)?>" data-post-origin="<?php echo (!isset($a_post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($a_post->site_url)) ? esc_url($a_post->site_url): ''; ?>">
                                    <div class="suggested-post-data-container"><strong><?php _e('Title:', 'wpil'); ?></strong> <?=esc_html($a_post->getTitle())?></div>
                                    <div class="suggested-post-data-container"><strong><?php _e('Type: ', 'wpil'); ?></strong> <?=esc_html($a_post->getType())?><br></div>
                                    <div class="suggested-post-data-container"><strong><?php _e('Published:', 'wpil'); ?></strong> <?=get_the_date('F j, Y', $a_post->id)?></div>
                                    <div class="suggested-post-data-container"><?php echo (!empty($categories)) ? '<b>' . _n(__('Category: ', 'wpil'), __('Categories: ', 'wpil'), $cats_found) . '</b>' . $categories . '<br>': ''; ?></div>
                                    <div class="suggested-post-data-container"><?php echo (!empty($tags)) ? '<b>' . _n(__('Tag: ', 'wpil'), __('Tags: ', 'wpil'), $tags_found) . '</b>' . $tags . '<br>': ''; ?></div>
<?php /*
                                    <div class="suggested-post-data-container"><strong><?php _e('Inbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getInboundInternalLinks(true) . '<br>'; ?>
                                    <div class="suggested-post-data-container"><strong><?php _e('Outbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getOutboundInternalLinks(true) . '<br>'; ?>
                                    <div class="suggested-post-data-container"><strong><?php _e('Outbound External Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getOutboundExternalLinks(true) . '<br>'; ?>
*/ ?>
                                    <strong><?php _e('URL:', 'wpil'); ?></strong> <a class="post-slug" target="_blank" href="<?=esc_url($a_post->getLinks()->view)?>"><?php echo esc_html($a_post->getSlug()); ?></a>
                                    <span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)">Custom Link</a></span>
                                    <span class="wpil_add_link_to_ignore link-form-button"> | <a href="javascript:void(0)">Ignore Link</a></span>
                                </div>
                            </div>
                            <div class="wpil-content" style="display: none;">
                                <ul>
                                    <?php $first = key($phrase->suggestions); ?>
                                    <?php foreach ($phrase->suggestions as $key_suggestion => $suggestion) : ?>
                                        <?php 
                                            $post_published_date = get_the_date('F j, Y', $suggestion->post->id); 
                                            $terms = get_terms(array(
                                                'taxonomy' => $taxonomies,
                                                'hide_empty' => false,
                                                'object_ids' => $suggestion->post->id,
                                            ));

                                            $categories = array();
                                            $tags = array();
                                            if(!is_wp_error($terms) && !empty($terms)){
                                                foreach($terms as $term){
                                                    if(get_taxonomy($term->taxonomy)->hierarchical){
                                                        $categories[] = $term->name;
                                                    }else{
                                                        $tags[] = $term->name;
                                                    }
                                                }

                                                $cats_found = count($categories);
                                                $tags_found = count($tags);
                                                $categories = implode(', ', $categories);
                                                $tags = implode(', ', $tags);
                                            }
                                        ?>
                                        <li class="dated-outbound-suggestion" data-wpil-post-published-date="<?php echo strtotime($post_published_date); ?>">
                                            <div>
                                                <input type="radio" <?=$key_suggestion==$first?'checked':''?> data-id="<?=esc_attr($suggestion->post->id)?>" data-type="<?=esc_attr($suggestion->post->type)?>" data-suggestion="<?=esc_attr($key_suggestion)?>" data-post-origin="<?php echo (!isset($suggestion->post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($suggestion->post->site_url)) ? esc_url($suggestion->post->site_url): ''; ?>">
                                                <span class="data">
                                                    <div class="suggested-post-data-container"><strong><?php _e('Title:', 'wpil'); ?></strong> <span class="suggested-post-title" style="opacity:<?=$suggestion->opacity?>"><?=esc_html($suggestion->post->getTitle())?></span></div>
                                                    <div class="suggested-post-data-container"><strong><?php _e('Type: ', 'wpil'); ?></strong> <?=esc_html($suggestion->post->getType())?><br></div>
                                                    <div class="suggested-post-data-container"><strong><?php _e('Published:', 'wpil'); ?></strong> <span class="suggested-post-published"><?=esc_attr($post_published_date)?></span></div>
                                                    <div class="suggested-post-data-container"><?php echo (!empty($categories)) ? '<b>' . _n(__('Category: ', 'wpil'), __('Categories: ', 'wpil'), $cats_found) . '</b>' . $categories . '<br>': ''; ?></div>
                                                    <div class="suggested-post-data-container"><?php echo (!empty($tags)) ? '<b>' . _n(__('Tag: ', 'wpil'), __('Tags: ', 'wpil'), $tags_found) . '</b>' . $tags . '<br>': ''; ?></div>
<?php /*
                                                    <div class="suggested-post-data-container"><strong><?php _e('Inbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$suggestion->post->getInboundInternalLinks(true) . '<br>'; ?>
                                                    <div class="suggested-post-data-container"><strong><?php _e('Outbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$suggestion->post->getOutboundInternalLinks(true) . '<br>'; ?>
                                                    <div class="suggested-post-data-container"><strong><?php _e('Outbound External Links: ', 'wpil'); ?></strong> <?=(int)$suggestion->post->getOutboundExternalLinks(true) . '<br>'; ?>
*/ ?>
                                                    <div class="suggested-post-data-container"><strong><?php _e('URL:', 'wpil'); ?></strong> <a class="post-slug" target="_blank" href="<?=esc_url($suggestion->post->getLinks()->view)?>"><?php echo esc_html($suggestion->post->getSlug()); ?></a></div>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php
                        if(empty(count($phrase->suggestions))){
                            continue;
                        }
                        $index = key($phrase->suggestions);

                        if(null === $index){
                            reset($phrase->suggestions);
                            $index = key($phrase->suggestions);
                        }

                        $a_post = $phrase->suggestions[$index]->post;

                        if(empty($a_post)){
                            continue;
                        }

                        $terms = get_terms(array(
                            'taxonomy' => $taxonomies,
                            'hide_empty' => false,
                            'object_ids' => $a_post->id,
                        ));

                        $categories = array();
                        $tags = array();
                        if(!is_wp_error($terms) && !empty($terms)){
                            foreach($terms as $term){
                                if(get_taxonomy($term->taxonomy)->hierarchical){
                                    $categories[] = $term->name;
                                }else{
                                    $tags[] = $term->name;
                                }
                            }

                            $cats_found = count($categories);
                            $tags_found = count($tags);
                            $categories = implode(',', $categories);
                            $tags = implode(',', $tags);
                        }
                        ?>
                        <div style="opacity:<?=$phrase->suggestions[$index]->opacity?>" class="suggestion dated-outbound-suggestion" data-id="<?=esc_attr($a_post->id)?>" data-type="<?=esc_attr($a_post->type)?>" data-wpil-post-published-date="<?php echo strtotime(get_the_date('F j, Y', $a_post->id)); ?>" data-post-origin="<?php echo (!isset($a_post->site_url)) ? 'internal': 'external'; ?>" data-site-url="<?php echo (isset($a_post->site_url)) ? esc_url($a_post->site_url): ''; ?>">
                            <div class="suggested-post-data-container"><strong><?php _e('Title:', 'wpil'); ?></strong> <span class="suggested-post-title"><?=esc_html($a_post->getTitle())?></span></div>
                            <div class="suggested-post-data-container"><strong><?php _e('Type: ', 'wpil'); ?></strong> <?=esc_html($a_post->getType())?><br></div>
                            <div class="suggested-post-data-container"><strong><?php _e('Published:', 'wpil'); ?></strong> <?=get_the_date('F j, Y', $a_post->id)?></div>
                            <div class="suggested-post-data-container"><?php echo (!empty($categories)) ? '<b>' . _n(__('Category: ', 'wpil'), __('Categories: ', 'wpil'), $cats_found) . '</b>' . $categories . '<br>': ''; ?></div>
                            <div class="suggested-post-data-container"><?php echo (!empty($tags)) ? '<b>' . _n(__('Tag: ', 'wpil'), __('Tags: ', 'wpil'), $tags_found) . '</b>' . $tags . '<br>': ''; ?></div>
<?php /*
                            <div class="suggested-post-data-container"><strong><?php _e('Inbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getInboundInternalLinks(true) . '<br>'; ?>
                            <div class="suggested-post-data-container"><strong><?php _e('Outbound Internal Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getOutboundInternalLinks(true) . '<br>'; ?>
                            <div class="suggested-post-data-container"><strong><?php _e('Outbound External Links: ', 'wpil'); ?></strong> <?=(int)$a_post->getOutboundExternalLinks(true) . '<br>'; ?>
*/ ?>
                            <strong><?php _e('URL:', 'wpil'); ?></strong> <a class="post-slug" target="_blank" href="<?=esc_url($a_post->getLinks()->view)?>"><?php echo urldecode($a_post->getSlug()); ?></a>
                            <span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)">Custom Link</a></span>
                            <span class="wpil_add_link_to_ignore link-form-button"> | <a href="javascript:void(0)">Ignore Link</a></span>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr class="wpil-no-posts-in-range" style="display:none">
                <td>No suggestions found</td>
            </tr>
        </tbody>
    <?php else : ?>
        <?php
            if('external_site' === $phrase_group_type){
                echo '<div style="border-top: solid 2px #ccd0d4; margin: 0 -13px;"></div>';
            }
        ?>
        <thead>
            <tr class="wpil-suggestion-table-heading">
                <th>
                    <div>
                        <b><?php if('internal_site' === $phrase_group_type){ _e('Add Internal Outbound Links', 'wpil'); }else{ _e('Add Outbound Links to External Sites', 'wpil'); } ?></b>
                        <br />
                    </div>
                </th>
                <th>
                    <div>
                        <br />
                        <b><?php _e('Posts to link to', 'wpil'); ?></b>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php _e('No suggestions found', 'wpil'); ?></td>
            </tr>
        </tbody>
    <?php endif; ?>
</table>
<?php
}

// if there weren't any internal or external suggestions, 
if(!$has_suggestions){ ?>
<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table wpil-outbound-links" id="tbl_keywords">
    <tbody>
        <tr>
            <td><?php _e('No suggestions found', 'wpil'); ?></td>
        </tr>
    </tbody>
</table>
<?php } ?>