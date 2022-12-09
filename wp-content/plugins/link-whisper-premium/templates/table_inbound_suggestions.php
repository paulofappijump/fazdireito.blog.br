<div style="display:none"><div><textarea id="wpil-editor-target"></textarea></div></div>
<table class="wp-list-table widefat fixed striped posts tbl_keywords_x js-table wpil-inbound-links best_keywords inbound" id="tbl_keywords">
    <?php   $options = get_user_meta(get_current_user_id(), 'report_options', true); 
            $show_date = (!empty($options['show_date']) && $options['show_date'] == 'on') ? true : false;
            $taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'names', 'or');
            $taxonomies = (!empty($taxonomies)) ? array_keys($taxonomies): array();
            $show_traffic = (isset($options['show_traffic'])) ? ( ($options['show_traffic'] == 'off') ? false : true) : false;
            $gsc_active = Wpil_Settings::HasGSCCredentials();
            $memory_break_point = Wpil_Report::get_mem_break_point();
    ?>
    <?php if (!empty($groups)) : ?>
        <thead>
            <tr class="wpil-suggestion-table-heading">
                <th class="inbound-check-all-col"><input type="checkbox" id="select_all" class="suggestion-select-all"><b style="margin: 0 0 0 5px;">Check All</b></th>
                <th><b>Suggested Phrases</b></th>
                <th><b>Posts To Create Links In</b></th>
                <?php
                if($gsc_active && $show_traffic){
                    echo '<th class="gsc-data-col"><b>' . __('Organic Traffic', 'wpil') . '</b></th>';
                }
                ?>
                <?php
                if($show_date){
                    echo '<th class="date-published-col"><b>' . __('Date Published', 'wpil') . '</b></th>';
                } ?>
            </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($groups as $post_id => $group) : $phrase = $group[0]; ?>
            <?php 
                // exit the loop if we're about to hit a memory limit
                if('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point){ break; } 
            ?>
            <tr class="wpil-inbound-sentence" data-wpil-sentence-id="<?=esc_attr($post_id)?>" data-wpil-post-published-date="<?php echo strtotime(get_the_date('F j, Y', $post_id)); ?>">
                <td class="inbound-checkbox" data-colname="<?php _e('Check Link', 'wpil'); ?>">
                    <input type="checkbox" name="link_keywords[]" class="chk-keywords" wpil-link-new="">
                </td>
                <td class="sentences" data-colname="<?php _e('Phrase', 'wpil'); ?>">
                    <?php if (count($group) > 1) : ?>
                        <div class="wpil-collapsible-wrapper">
                            <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">
                                <div class="sentence top-level-sentence" data-id="<?=esc_attr($post_id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                                    <div class="wpil_edit_sentence_form">
                                        <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                        <span class="button-primary">Save</span>
                                        <span class="button-secondary">Cancel</span>
                                    </div>
                                    <span class="wpil_sentence_with_anchor" data-li-id="0"><span class="wpil_sentence" title="<?php esc_attr_e('Double clicking a word will select it.', 'wpil');?>"><?=$phrase->suggestions[0]->sentence_with_anchor?></span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="<?php _e('Click to undo changes', 'wpil'); ?>"></span></span>
                                    <span class="wpil_edit_sentence link-form-button">| <a href="javascript:void(0)">Edit Sentence</a></span>
                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                                    <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                                    <input type="hidden" name="custom_sentence" value="">
                                    <input type="hidden" name="original_sentence_with_anchor" value="<?php echo base64_encode($phrase->suggestions[0]->original_sentence_with_anchor)?>">
                                </div>
                            </div>
                            <div class="wpil-content" style="display: none;">
                                <ul>
                                    <?php foreach ($group as $key_phrase => $phrase) : ?>
                                        <li>
                                            <div class="wpil-inbound-sentence-data-container" data-container-id="<?=$key_phrase?>">
                                                <input type="radio" <?=!$key_phrase?'checked':''?> data-id="<?=$key_phrase?>">
                                                <div class="data">
                                                    <div class="wpil_edit_sentence_form">
                                                        <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                                        <span class="button-primary">Save</span>
                                                        <span class="button-secondary">Cancel</span>
                                                    </div>
                                                    <span class="wpil_sentence_with_anchor"  data-li-id="<?php echo $key_phrase; ?>"><span class="wpil_sentence" title="<?php esc_attr_e('Double clicking a word will select it.', 'wpil');?>"><?=$phrase->suggestions[0]->sentence_with_anchor?></span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="<?php _e('Click to undo changes', 'wpil'); ?>"></span></span>
                                                    <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                                                    <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                                                    <input type="hidden" name="custom_sentence" value="">
                                                    <input type="hidden" name="original_sentence_with_anchor" value="<?php echo base64_encode($phrase->suggestions[0]->original_sentence_with_anchor)?>">
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <?php if (Wpil_Settings::fullHTMLSuggestions()) : ?>
                            <?php foreach ($group as $key_phrase => $phrase) : ?>
                                <div class="raw_html" <?=$key_phrase > 0 ? 'style="display:none"' : '' ?> data-id="<?=$key_phrase?>"><?=htmlspecialchars($phrase->suggestions[0]->sentence_src_with_anchor)?></div>
                            <?php endforeach; ?>
                            <div class="raw_html custom-text" style="display:none" data-id="custom-text"></div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="sentence top-level-sentence" data-id="<?=esc_attr($post_id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                            <div class="wpil_edit_sentence_form">
                                <textarea class="wpil_content"><?=$phrase->suggestions[0]->sentence_src_with_anchor?></textarea>
                                <span class="button-primary">Save</span>
                                <span class="button-secondary">Cancel</span>
                            </div>
                            <span class="wpil_sentence_with_anchor"><span class="wpil_sentence" title="<?php esc_attr_e('Double clicking a word will select it.', 'wpil');?>"><?=$phrase->suggestions[0]->sentence_with_anchor?></span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="<?php _e('Click to undo changes', 'wpil'); ?>"></span></span>
                            <span class="wpil_edit_sentence link-form-button">| <a href="javascript:void(0)">Edit Sentence</a></span>
                            <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->anchor_score).')':''?>
                            <input type="hidden" name="sentence" value="<?=base64_encode($phrase->sentence_src)?>">
                            <input type="hidden" name="custom_sentence" value="">
                            <input type="hidden" name="original_sentence_with_anchor" value="<?php echo base64_encode($phrase->suggestions[0]->original_sentence_with_anchor)?>">

                            <?php if (Wpil_Settings::fullHTMLSuggestions()) : ?>
                                <div class="raw_html"><?=htmlspecialchars($phrase->suggestions[0]->sentence_src_with_anchor)?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td data-colname="<?php _e('Post', 'wpil'); ?>">
                    <div style="opacity:<?=$phrase->suggestions[0]->opacity?>" class="suggestion" data-id="<?=esc_attr($phrase->suggestions[0]->post->id)?>" data-type="<?=esc_attr($phrase->suggestions[0]->post->type)?>">
                        <?php
                            $terms = get_terms(array(
                                'taxonomy' => $taxonomies,
                                'hide_empty' => false,
                                'object_ids' => $phrase->suggestions[0]->post->id,
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
                        <?php echo '<b>' . __('Title: ', 'wpil') . '</b>' . esc_html($phrase->suggestions[0]->post->getTitle()) . '<br>'; ?>
                        <?php echo '<b>' . __('Type: ', 'wpil') . '</b>' . esc_html($phrase->suggestions[0]->post->getType()) . '<br>'; ?>
                        <?php echo (!empty($categories)) ? '<b>' . _n(__('Category: ', 'wpil'), __('Categories: ', 'wpil'), $cats_found) . '</b>' . $categories . '<br>': ''; ?>
                        <?php echo (!empty($tags)) ? '<b>' . _n(__('Tag: ', 'wpil'), __('Tags: ', 'wpil'), $tags_found) . '</b>' . $tags . '<br>': ''; ?>
                        <?=!empty(Wpil_Suggestion::$undeletable)?' ('.esc_attr($phrase->suggestions[0]->post_score).')':''?>
                        <?php echo '<b>' . __('Inbound Internal Links: ', 'wpil') . '</b>' . (int)$phrase->suggestions[0]->post->getInboundInternalLinks(true) . '<br>'; ?>
                        <?php echo '<b>' . __('Outbound Internal Links: ', 'wpil') . '</b>' . (int)$phrase->suggestions[0]->post->getOutboundInternalLinks(true) . '<br>'; ?>
                        <?php echo '<b>' . __('Outbound External Links: ', 'wpil') . '</b>' . (int)$phrase->suggestions[0]->post->getOutboundExternalLinks(true) . '<br>'; ?>
                            <?php echo '<b style="vertical-align: top;">' . __('Post View Link:', 'wpil') . '</b>'?>
                            <a class="post-slug inbound-slug" target="_blank" href="<?=esc_url($phrase->suggestions[0]->post->getLinks()->view)?>">
                                <?php echo esc_html($phrase->suggestions[0]->post->getLinks()->view)?>
                            </a>

                        <span class="wpil_add_link_to_ignore link-form-button"><a style="margin-left: 5px 0px;" href="javascript:void(0)">Ignore Link</a></span>
                    </div>
                </td>
                <?php if($gsc_active && $show_traffic){ ?>
                <td data-colname="<?php _e('Organic Traffic', 'wpil'); ?>">
                    <?php
                    $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($phrase->suggestions[0]->post->id, $phrase->suggestions[0]->post->type, 'gsc-keyword', false);
                    $clicks = 0;
                    $position = 0;
                    $impressions = 0;
                    $ctr = 0;
                    foreach($keywords as $keyword){
                        $clicks += $keyword->clicks;
                        $position += floatval($keyword->position);
                        $impressions += $keyword->impressions;

                    }

                    if($position > 0){
                        $position = round($position/count($keywords), 2);
                    }

                    if(!empty($clicks) && !empty($impressions)){
                        $ctr = round($clicks/$impressions, 2);
                    }

                    echo '<ul style="margin-top: 0px;">
                                <li>' . __('Clicks: ', 'wpil') . $clicks . '</li>
                                <li>' . __('Impressions: ', 'wpil') . $impressions . '</li>
                                <li>' . __('AVG Position: ', 'wpil') . $position . '</li>
                                <li>' . __('CTR: ', 'wpil') . $ctr . '</li>
                            </ul>';
                    ?>
                </td>
                <?php } ?>
                <?php if($show_date){ ?>
                <td data-colname="<?php _e('Date Published', 'wpil'); ?>">
                    <?=($phrase->suggestions[0]->post->type=='post'?get_the_date('', $phrase->suggestions[0]->post->id):'not set')?>
                </td>
                <?php } ?>
            </tr>
        <?php endforeach; ?>
            <tr class="wpil-no-posts-in-range" style="display:none">
                <td>No suggestions found</td>
            </tr>
        </tbody>
        <script>
            /** Sticky Header **/
            function createSticky(){
                // Makes the thead sticky to the top of the screen when scrolled down far enough
                if(jQuery('.wp-list-table:not(.sticky-ignore)').length){
                    var theadTop = jQuery('.wp-list-table:not(.sticky-ignore)').offset().top;
                    var adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
                    var scrollLine = (theadTop - adminBarHeight);
                    var sticky = false;

                    // duplicate the footer and insert in the table head
                    jQuery('.wp-list-table:not(.sticky-ignore) thead tr').clone().addClass('wpil-sticky-header').css({'display': 'none', 'top': adminBarHeight + 'px', 'margin': '0 33px 0 0'}).prepend(jQuery('#inbound-suggestions-dest-post-title').clone()).appendTo('.wp-list-table:not(.sticky-ignore) thead');

                    // resizes the header elements
                    function sizeHeaderElements(){
                        // adjust for any change in the admin bar
                        adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
                        jQuery('.wpil-sticky-header').css({'top': adminBarHeight + 'px'});

                        // adjust the size of the header columns
                        var elements = jQuery('.wpil-sticky-header').find('th');
                        jQuery('.wp-list-table:not(.sticky-ignore) thead tr').not('.wpil-sticky-header').find('th').each(function(index, element){
                            var width = getComputedStyle(element).width;

                            jQuery(elements[index]).css({'width': width});
                        });
                    }
                    sizeHeaderElements();

                    function resetScrollLinePositions(){
                        theadTop = jQuery('.wp-list-table:not(.sticky-ignore)').offset().top;
                        adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
                        scrollLine = (theadTop - adminBarHeight);
                    }

                    jQuery(window).on('scroll', function(e){
                        var scroll = parseInt(document.documentElement.scrollTop);

                        // if we've passed the scroll line and the head is not sticky
                        if(scroll > scrollLine && !sticky){
                            // sticky the header
                            jQuery('.wpil-sticky-header').css({'display': 'table-row'});
                            sticky = true;
                        }else if(scroll < scrollLine && sticky){
                            // if we're above the scroll line and the header is sticky, unsticky it
                            jQuery('.wpil-sticky-header').css({'display': 'none'});
                            sticky = false;
                        }
                    });

                    var wait;
                    jQuery(window).on('resize', function(){
                        clearTimeout(wait);
                        setTimeout(function(){ 
                            sizeHeaderElements(); 
                            resetScrollLinePositions();
                        }, 150);
                    });

                    setTimeout(function(){ 
                        resetScrollLinePositions();
                    }, 1500);
                }
            }
            createSticky();
            /** /Sticky Header **/
        </script>
    <?php else : ?>
        <tr>
            <td>No suggestions found</td>
        </tr>
    <?php endif; ?>
</table>
<script>
    var inbound_internal_link = '<?=esc_url_raw($post->getLinks()->view)?>';
</script>
