<div class="wpil-collapsible-wrapper">
    <div class="wpil-collapsible wpil-collapsible-static wpil-clicks-count"><?= (!empty($click_data)) ? $click_data[0]->total_clicks: 0;?></div>
    <div class="wpil-content">
        <ul class="report_clicks">
            <?php foreach ($click_data as $data) : ?>
                <li>
                    <strong><?php _e('Total Link Clicks:', 'wpil'); ?> </strong><?php echo (int)$data->total_clicks;?>
                </li>
                <li>
                    <strong><?php _e('Clicks Over the Past 30 Days:', 'wpil'); ?> </strong><?php echo (int)$data->clicks_over_30_days;?>
                </li>
                <li>
                    <strong><?php _e('Most Clicked Link:', 'wpil'); ?> </strong><a href="<?=esc_url(admin_url("admin.php?post_id={$data->link_url}&post_type=url&page=link_whisper&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1')))?>" target="_blank"><strong><?php echo esc_html($data->link_anchor);?></strong></a> (<?php echo $data->most_clicked_count;?>)
                </li>
                <li>
                    <strong><a href="<?php echo esc_url(admin_url("admin.php?post_id={$post->id}&post_type={$post->type}&page=link_whisper&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1'))); ?>"><?php _e('View Detailed Click Report', 'wpil'); ?></a></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>