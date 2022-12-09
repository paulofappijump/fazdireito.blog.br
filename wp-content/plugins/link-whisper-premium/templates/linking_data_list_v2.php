<?php if (get_option('wpil_disable_outbound_suggestions')) : ?>
    <div style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Outbound Link Suggestions Disabled', 'wpil') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=esc_url(admin_url("admin.php?{$post->type}_id={$post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($post->getLinks()->edit)))?>" class="button-primary">Add Inbound links</a>
        <br />
        <a href="<?=esc_url($post->getLinks()->export)?>" target="_blank"><?php _e('Export post data for support', 'wpil') ?></a>
    </div>
<?php else : ?>
<?php
    $has_suggestions = false;
    foreach($phrase_groups as $phrases){
        if(!empty($phrases)){
            $has_suggestions = true;
        }
    }

?>
<div class="wpil_notice" id="wpil_message" style="display: none">
    <p></p>
</div>
<div class="best_keywords outbound">
    <?=Wpil_Base::showVersion()?>
    <p>
        <div style="margin-bottom: 15px;">
            <input type="hidden" class="wpil-suggestion-input wpil-suggestions-can-be-regenerated" value="0" data-suggestion-input-initial-value="0">
            <?php if(!empty($categories)){ ?>
            <input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($same_category) ? 1: 0;?>" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category"><?php _e('Only Show Link Suggestions in the Same Category as This Post', 'wpil'); ?></label>
            <br>
            <div class="same_category-aux wpil-aux">
                <select multiple name="wpil_selected_category" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_categories);?>" style="width: 400px;">
                    <?php foreach ($categories as $cat){ ?>
                        <option value="<?php echo $cat->term_taxonomy_id; ?>" <?php echo (in_array($cat->term_taxonomy_id, $selected_categories, true) || empty($selected_categories))?'selected':''; ?>><?php esc_html_e($cat->name)?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
            <br class="same_category-aux wpil-aux">
                <?php if(!empty($same_category)){ ?>
                <style>
                    .best_keywords .same_category-aux{
                        display: inline-block;
                    }
                </style>
                <?php } ?>
            <?php } ?>
            <?php if(!empty($tags)){ ?>
            <input type="checkbox" name="same_tag" id="field_same_tag" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($same_tag) ? 1: 0;?>"  <?=!empty($same_tag) ? 'checked' : ''?>> <label for="field_same_tag"><?php _e('Only Show Link Suggestions with the Same Tag as This Post', 'wpil'); ?></label>
            <br>
            <div class="same_tag-aux wpil-aux">
                <select multiple name="wpil_selected_tag" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_tags);?>" style="width: 400px;">
                    <?php foreach ($tags as $tag){ ?>
                        <option value="<?php echo $tag->term_taxonomy_id; ?>" <?php echo (in_array($tag->term_taxonomy_id, $selected_tags, true))?'selected':''; ?>><?php esc_html_e($tag->name)?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
            <br class="same_tag-aux wpil-aux">
                <?php if(!empty($same_tag)){ ?>
                <style>
                    .best_keywords .same_tag-aux{
                        display: inline-block;
                    }
                </style>
                <?php } ?>
            <?php } ?>
            <input type="checkbox" name="select_post_types" id="field_select_post_types" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($select_post_types) ? 1: 0;?>" <?=!empty($select_post_types) ? 'checked' : ''?>> <label for="field_select_post_types"><?php _e('Select the Post Types to use in Suggestions', 'wpil'); ?></label>
            <br>
            <div class="select_post_types-aux wpil-aux">
                <select multiple name="selected_post_types" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_post_types);?>" style="width: 400px;">
                    <?php foreach ($post_types as $post_type => $lable){ ?>
                        <option value="<?php echo $post_type; ?>" <?php echo (in_array($post_type, $selected_post_types, true))?'selected':''; ?>><?php esc_html_e(ucfirst($lable))?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
            <br />
            <br />
            <button id="wpil-regenerate-suggestions" class="button disabled" disabled><?php _e('Regenerate Suggestions', 'wpil'); ?></button>
            <?php if(!empty($select_post_types)){ ?>
            <style>
                .best_keywords .select_post_types-aux{
                    display: inline-block;
                }
            </style>
            <?php } ?>
            <script>
                jQuery('.wpil-suggestion-multiselect').select2();
            </script>
        </div>
        <a href="<?=esc_url($post->getLinks()->export)?>" target="_blank">Export data for support</a><br>
        <a href="<?=esc_url($post->getLinks()->excel_export)?>" target="_blank">Export Post Data to Excel</a><br>
        <!--<a class="wpil-export-suggestions" data-export-type="excel" data-suggestion-type="outbound" data-type="<?php echo $post->type; ?>" data-id="<?php echo $post->id; ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#" target="_blank">Export Suggestion Data to Excel</a><br>-->
        <a class="wpil-export-suggestions" data-export-type="csv" data-suggestion-type="outbound" data-type="<?php echo $post->type; ?>" data-id="<?php echo $post->id; ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#" target="_blank">Export Suggestion Data to CSV</a>
    </p>
    <button class="sync_linking_keywords_list button-primary <?php echo (!$has_suggestions) ? 'disabled': '';?>" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="outbound" <?php echo (!$has_suggestions) ? 'disabled=disabled': '';?>>Insert links into <?=$post->type=='term' ? 'description' : 'post'?></button>
    <a href="<?=esc_url(admin_url("admin.php?{$post->type}_id={$post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($post->getLinks()->edit)))?>" target="_blank" class="wpil_inbound_links_button button-primary">Add Inbound links</a>
    <?php if (!empty($phrase_groups)){ ?>
        <br/>
        <div>
            <div style="display:inline-block;">
                <label for="wpil-outbound-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;"><?php _e('Filter Displayed Posts by Published Date', 'wpil'); ?></label>
                <br/>
                <input id="wpil-outbound-daterange" type="text" name="daterange" class="wpil-date-range-filter" value="<?php echo '01/01/2000 - ' . date('m/d/Y', strtotime('today')); ?>">
            </div>

            <div style="float:right;">
                <label for="suggestion_filter_field" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;"><?php _e('Filter Suggested Posts by Keyword', 'wpil'); ?></label>
                <br />
                <textarea id="suggestion_filter_field" style="width: 100%;"></textarea>
            </div>
        </div>
        <script>
            var rows = jQuery('tr[data-wpil-sentence-id]');
            jQuery('#wpil-outbound-daterange').on('apply.wpil-daterangepicker, hide.wpil-daterangepicker', function(ev, picker) {
                jQuery(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                var start = picker.startDate.unix();
                var end   = picker.endDate.unix();

                rows.each(function(index, element){
                    var suggestions = jQuery(element).find('.dated-outbound-suggestion');
                    var first = true;
                    suggestions.each(function(index, element2){
                        var elementTime = jQuery(element2).data('wpil-post-published-date');
                        var checkbox = jQuery(element2).find('input'); // wpil_dropdown checkbox for the current suggestion, not the suggestion's checkbox

                        if(!start || (start < elementTime && elementTime < end)){
                            jQuery(element2).removeClass('wpil-outbound-date-filtered');

                            // check the first visible suggested post 
                            if(first && checkbox.length > 0){
                                checkbox.trigger('click');
                                first = false;
                            }
                        }else{
                            jQuery(element2).addClass('wpil-outbound-date-filtered');

                            // if this is a suggestion in a collapsible box, uncheck it
                            if(checkbox.length > 0){
                                checkbox.prop('checked', false);
                            }
                        }
                    });

                    // if all of the suggestions have been hidden
                    if(suggestions.length === jQuery(element).find('.dated-outbound-suggestion.wpil-outbound-date-filtered').length){
                        // hide the suggestion row and uncheck it's checkboxes
                        jQuery(element).css({'display': 'none'});
                        jQuery(element).find('.chk-keywords').prop('checked', false);
                    }else{
                        // if not, make sure the suggestion row is showing
                        jQuery(element).css({'display': 'table-row'});
                    }
                });

                // handle the results of hiding any posts
                handleHiddenPosts();
            });

            jQuery('#wpil-outbound-daterange').on('cancel.wpil-daterangepicker', function(ev, picker) {
                jQuery(this).val('');
                jQuery('.wpil-outbound-date-filtered').removeClass('wpil-outbound-date-filtered');
            });

            jQuery('#wpil-outbound-daterange').daterangepicker({
                autoUpdateInput: false,
                linkedCalendars: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });

            /**
             * Handles the table display elements when the date range changes
             **/
            function handleHiddenPosts(){
                if(jQuery('.chk-keywords:visible').length < 1){
                    // hide the table elements
                    jQuery('.wp-list-table thead, .sync_linking_keywords_list, .wpil_inbound_links_button').css({'display': 'none'});
                    // make sure the "Check All" box is unchecked
                    jQuery('.inbound-check-all-col input, #select_all').prop('checked', false);
                    // show the "No matches" message
                    jQuery('.wpil-no-posts-in-range').css({'display': 'table-row'});
                }else{
                    // show the table elements
                    jQuery('.wp-list-table thead').css({'display': 'table-header-group'});
                    jQuery('.sync_linking_keywords_list, .wpil_inbound_links_button').css({'display': 'inline-block'});
                    // hide the "No matches" message
                    jQuery('.wpil-no-posts-in-range').css({'display': 'none'});
                }
            }
        </script>
    <?php } ?>
    <?php require WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/table_suggestions.php'; ?>
</div>
<br>
<button class="sync_linking_keywords_list button-primary <?php echo (!$has_suggestions) ? 'disabled': '';?>" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>"  data-page="outbound" <?php echo (!$has_suggestions) ? 'disabled=disabled': '';?>>Insert links into <?=$post->type=='term' ? 'description' : 'post'?></button>
<a href="<?=esc_url(admin_url("admin.php?{$post->type}_id={$post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'])))?>" target="_blank" class="wpil_inbound_links_button button-primary">Add Inbound links</a>
<br>
<br>
<?php endif; ?>