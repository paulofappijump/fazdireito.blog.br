<div class="wpil-collapsible-wrapper">
    <div class="wpil-collapsible wpil-collapsible-static wpil-links-count"><?=count($possible_links)?></div>
    <div class="wpil-content">
        <ul class="report_links keyword-<?php echo $item->id; ?>">
            <?php foreach ($possible_links as $possible_link) : ?>
                <?php 
                $post = new Wpil_Model_Post($possible_link->post_id, $possible_link->post_type);
                $display_sentence = preg_replace('/' . preg_quote($possible_link->case_keyword, '/') . '/', '<b>' . $possible_link->case_keyword . '</b>', $possible_link->sentence_text, 1);
                ?>
                <li id="select-keyword-<?php echo $possible_link->id; ?>">
                    <div style="display: inline-block;"><?php echo '<b>' . __('Post', 'wpil') . '</b>: '; ?><a href="<?php echo esc_url($post->getViewLink()); ?>" target="_blank"><?php echo esc_html($post->getTitle()); ?></a></div>
                    <br />
                    <span><?php echo '<b>' . __('Sentence', 'wpil') . '</b>: '; ?></span>
                    <br />
                    <label><div style="display: inline-block;"><input id="select-keyword-<?php echo $possible_link->id; ?>" type="checkbox" name="wpil_keyword_select_link" data-select-keyword-id="<?php echo $possible_link->id; ?>"></div><span><?php echo $display_sentence; ?></span></label>
                    <br />
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if(!empty($possible_links)){ ?>
    <div class="insert-selected-autolinks">
        <div style="display:inline-block">
            <input id="select-all-possible-<?php echo $item->id; ?>" type="checkbox" class="wpil-select-all-possible-keywords" data-keyword-id="<?php echo $item->id; ?>"><label for="select-all-possible-<?php echo $item->id; ?>"><b><?php _e('Select All', 'wpil'); ?></b></label>
        </div>
        <a href="#" class="button-primary wpil-insert-selected-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'insert-selected-autolinks-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>"  data-selected-link-id="<?php echo $post->id; ?>"><?php _e('Create Links', 'wpil'); ?></a>
    </div>
    <?php } ?>
</div>
<div class="progress_panel loader" style="display: none; margin: 0;">
    <div class="progress_count" style="width: 100%"></div>
</div>