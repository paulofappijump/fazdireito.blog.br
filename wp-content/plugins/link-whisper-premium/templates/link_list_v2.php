<?php
$type = (!empty($term_id)?'term':'post');
$wpil_post = new Wpil_Model_Post($post_id, $type);
$max_links_per_post = get_option('wpil_max_links_per_post', 0);

if(get_option('wpil_disable_outbound_suggestions')){ ?>
    <div class="wpil_styles" style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Outbound Link Suggestions Disabled', 'wpil') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=esc_url(admin_url("admin.php?{$wpil_post->type}_id={$wpil_post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($wpil_post->getLinks()->edit)))?>" class="button-primary">Add Inbound links</a>
        <br />
        <a href="<?=esc_url($wpil_post->getLinks()->export)?>" target="_blank"><?php _e('Export post data for support', 'wpil') ?></a>
    </div>
    <?php
    return;
}elseif(!empty($max_links_per_post)){
    // check if the current post is at the link limit
    if(Wpil_link::at_max_outbound_links($wpil_post)){?>
    <div class="wpil_styles" style="min-height: 200px">
        <p style="display: inline-block;"><?php _e('Post has reached the max link limit. To generate suggestions for this post, please increase the Max Outbound Links Per Post setting from the Link Whisper Settings.', 'wpil') ?></p>
        <a style="float: right; margin: 15px 0px;" href="<?=esc_url(admin_url("admin.php?{$wpil_post->type}_id={$wpil_post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($wpil_post->getLinks()->edit)))?>" class="button-primary">Add Inbound links</a>
        <br />
        <a href="<?=esc_url($wpil_post->getLinks()->export)?>" target="_blank"><?php _e('Export post data for support', 'wpil') ?></a>
    </div>
    <?php
        return;
    }
}

?>
<?php if($manually_trigger_suggestions){ ?>
    <div class="wpil_styles wpil-get-manual-suggestions-container" style="min-height: 200px">
        <a href="#" id="wpil-get-manual-suggestions" style="margin: 15px 0px;" class="button-primary"><?php _e('Get Suggestions', 'wpil'); ?></a>
        <a style="float: right; margin: 15px 0px;" href="<?=esc_url(admin_url("admin.php?{$wpil_post->type}_id={$wpil_post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($wpil_post->getLinks()->edit)))?>" class="button-primary">Add Inbound links</a>
        <br />
        <a href="<?=esc_url($wpil_post->getLinks()->export)?>" target="_blank"><?php _e('Export post data for support', 'wpil') ?></a>
    </div>
<?php } ?>
<div data-wpil-ajax-container="" data-wpil-ajax-container-url="<?=esc_url(admin_url('admin.php?post_id=' . $post_id . '&page=link_whisper&type=outbound_suggestions_ajax'.(!empty($term_id)?'&term_id='.$term_id:'').(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID .'wpil_suggestion_nonce') : '')) . Wpil_Settings::get_suggestion_filter_string())?>" class="wpil_keywords_list wpil_styles" data-wpil-manual-suggestions="<?php echo ($manually_trigger_suggestions) ? 1: 0;?>" <?php echo ($manually_trigger_suggestions) ? 'style="display:none"': ''; ?>>
    <div class="progress_panel loader">
        <div class="progress_count" style="width: 100%"><?php _e('Processing Link Suggestions', 'wpil');?></div>
    </div>
</div>
