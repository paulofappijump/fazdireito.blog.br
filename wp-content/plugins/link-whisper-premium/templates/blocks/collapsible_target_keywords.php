<div class="wpil-collapsible-wrapper <?php echo ($col_number !== false) ? 'wpil-keyword-col-' . $col_number: '';?>">
    <div class="wpil-collapsible wpil-collapsible-static wpil-links-count"><?=count($keywords)?></div>
    <div class="wpil-content">
        <ul class="report_links">
            <?php foreach ($keywords as $keyword) : ?>
                <li id="target-keyword-<?php echo $keyword->keyword_index; ?>">
                    <?php
                    if('custom-keyword' === $keyword->keyword_type){
                        echo '<div style="display: inline-block;"><label><span>'. esc_html($keyword->keywords) . '</span></label></div>';
                        echo '<i class="wpil_target_keyword_delete dashicons dashicons-no-alt" data-keyword-id="' . $keyword->keyword_index . '" data-keyword-type="custom-keyword" data-nonce="' . wp_create_nonce(get_current_user_id() . 'delete-target-keywords-' . $keyword->keyword_index) . '"></i>';
                    }else{
                        ?><div style="display: inline-block;"><input id="keyword-<?php echo $keyword->keyword_index; ?>" style="vertical-align: sub;" type="checkbox" name="keyword_active" data-keyword-id="<?php echo $keyword->keyword_index; ?>" <?php echo (!empty($keyword->checked) || !empty($keyword->auto_checked)) ? 'checked="checked"': '';?>><label for="keyword-<?php echo $keyword->keyword_index; ?>"><span><?php echo esc_html($keyword->keywords); ?></span></label></div><?php
                    }
                    
                    if('gsc-keyword' === $keyword->keyword_type){
                        echo 
                        '<div>
                            <div style="margin: 3px 0;"><b>' . __('Impressions', 'wpil') . ':</b> ' . $keyword->impressions . '</div>
                            <div style="margin: 3px 0;"><b>' . __('Clicks', 'wpil') . ':</b> ' . $keyword->clicks . '</div>
                            <div style="margin: 3px 0;"><b>' . __('Position', 'wpil') . ':</b> ' . $keyword->position . '</div>
                            <div style="margin: 3px 0;"><b>' . __('CTR', 'wpil') . ':</b> ' . $keyword->ctr . '</div>
                        </div>';
                    } ?>
                    <br>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if(!empty($keywords) && 'custom-keyword' !== $keyword_type){ ?>
    <div class="update-post-keywords">
        <a href="#" class="button-primary wpil-update-selected-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'update-selected-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>"><?php _e('Update Active', 'wpil'); ?></a>
    </div>
    <?php } ?>
    <?php if('custom-keyword' === $keyword_type){ ?>
    <div class="create-post-keywords">
        <a href="#" style="vertical-align: top;" class="button-primary wpil-create-target-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'create-target-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>" data-post-type="<?php echo $post->type; ?>"><?php _e('Create', 'wpil'); ?></a>
        <div class="wpil-create-target-keywords-row-container"  style="display: inline-block; width: calc(100% - 200px);">
            <input type="text" style="width: 100%" class="create-custom-target-keyword-input" placeholder="<?php _e('New Custom Keyword', 'wpil'); ?>">
        </div>
        <a href="#" class="button-primary wpil-add-target-keyword-row" style="margin-left:0px; vertical-align: top;"><?php _e('Add Row', 'wpil'); ?></a>
    </div>
    <?php } ?>
</div>