<form method="post" action="">
    <div id="wpil-inbound-suggestions-head-controls">
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
                    #wpil-inbound-suggestions-head-controls .same_category-aux{
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
                    #wpil-inbound-suggestions-head-controls .same_tag-aux{
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
            <br>
            <br>
            <!--<a class="wpil-export-suggestions" data-export-type="excel" data-suggestion-type="inbound" data-type="<?php echo esc_attr($post->type); ?>" data-id="<?php echo esc_attr($post->id); ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#">Export Suggestions to Excel</a><br>-->
            <a class="wpil-export-suggestions" data-export-type="csv" data-suggestion-type="inbound" data-type="<?php echo esc_attr($post->type); ?>" data-id="<?php echo esc_attr($post->id); ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#">Export Suggestions to CSV</a><br>
            <?php if(!empty($select_post_types)){ ?>
            <style>
                #wpil-inbound-suggestions-head-controls .select_post_types-aux{
                    display: inline-block;
                }
            </style>
            <?php } ?>
            <script>
                jQuery('.wpil-suggestion-multiselect').select2();
            </script>
            <?php if (!empty($phrases)){ ?>
                <br />
                <div style="display: inline-block;">
                    <label for="wpil-inbound-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;"><?php _e('Filter Displayed Posts by Published Date', 'wpil'); ?></label><br/>
                    <input id="wpil-inbound-daterange" type="text" name="daterange" class="wpil-date-range-filter" value="<?php echo '01/01/2000 - ' . date('m/d/Y', strtotime('today')); ?>">
                </div>
                <script>
                    var sentences = jQuery('.wpil-inbound-sentence');
                    jQuery('#wpil-inbound-daterange').on('apply.wpil-daterangepicker, hide.wpil-daterangepicker', function(ev, picker) {
                        jQuery(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                        var start = picker.startDate.unix();
                        var end = picker.endDate.unix();

                        sentences.each(function(index, element){
                            var elementTime = jQuery(element).data('wpil-post-published-date');
                            if(!start || (start < elementTime && elementTime < end)){
                                jQuery(element).css({'display': 'table-row'});
                            }else{
                                jQuery(element).css({'display': 'none'}).find('input.chk-keywords').prop('checked', false);
                            }
                        });

                        // handle the results of hiding any posts
                        handleHiddenPosts();
                    });

                    jQuery('#wpil-inbound-daterange').on('cancel.wpil-daterangepicker', function(ev, picker) {
                        jQuery(this).val('');
                        sentences.each(function(index, element){
                            jQuery(element).css({'display': 'table-row'});
                        });
                    });

                    jQuery('#wpil-inbound-daterange').daterangepicker({
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
                        if(jQuery('.inbound-checkbox:visible').length < 1){
                            // hide the table elements
                            jQuery('.wp-list-table thead, #inbound_suggestions_button, #inbound_suggestions_button_2').css({'display': 'none'});
                            // make sure the "Check All" box is unchecked
                            jQuery('.inbound-check-all-col input').prop('checked', false);
                            // show the "No matches" message
                            jQuery('.wpil-no-posts-in-range').css({'display': 'table-row'});
                        }else{
                            // show the table elements
                            jQuery('.wp-list-table thead').css({'display': 'table-header-group'});
                            jQuery('#inbound_suggestions_button, #inbound_suggestions_button_2').css({'display': 'inline-block'});
                            // hide the "No matches" message
                            jQuery('.wpil-no-posts-in-range').css({'display': 'none'});
                        }
                    }
                </script>

                <div style="display: flex; flex-direction: column; position: absolute; right: 12px; top: 40px;">
                    <label for="suggestion_filter_field" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;">Filter Suggestions by Keyword</label>
                    <textarea id="suggestion_filter_field"></textarea>
                </div>

            <?php } ?>
            <br>
        </div>
        <?php if (!empty($phrases)){ ?>
            <button id="inbound_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="inbound">Add links</button>
        <?php } ?>
        <?php $same_category = !empty(get_user_meta(get_current_user_id(), 'wpil_same_category_selected', true)); ?>
    </div>
        <?php require WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/table_inbound_suggestions.php'?>
        <?php if (!empty($phrases)){ ?>
        <button id="inbound_suggestions_button_2" class="sync_linking_keywords_list button-primary" data-id="<?=esc_attr($post->id)?>" data-type="<?=esc_attr($post->type)?>" data-page="inbound">Add links</button>
        <?php } ?>
</form>