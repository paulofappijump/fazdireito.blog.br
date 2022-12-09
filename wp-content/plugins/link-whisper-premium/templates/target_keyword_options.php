<input type="hidden" name="wp_screen_options[option]" value="target_keyword_options" />
<input type="hidden" name="wp_screen_options[value]" value="yes" />
<fieldset class="screen-options">
    <legend>Options</legend>
    <input type="checkbox" name="target_keyword_options[show_date]" id="show_date" <?=$show_date ? 'checked' : ''?>/>
    <label for="show_date">Show the post publish date </label>
    <input type="hidden" name="target_keyword_options[show_traffic]" value="off"/>
    <input type="checkbox" name="target_keyword_options[show_traffic]" id="show_traffic" <?=$show_traffic ? 'checked' : ''?>/>
    <label for="show_traffic">Show the organic traffic </label>
    <input type="hidden" name="target_keyword_options[remove_obviated_keywords]" value="off"/>
    <input type="checkbox" name="target_keyword_options[remove_obviated_keywords]" id="remove_obviated_keywords" <?=$remove_obviated_keywords ? 'checked' : ''?>/>
    <label for="remove_obviated_keywords">Hide large GSC keywords when there's a full text match with an active keyword of the same size or smaller </label>
</fieldset>
<fieldset class="screen-options">
    <legend>Pagination</legend>
    <label for="per_page">Posts per page</label>
    <input type="number" step="1" min="1" max="999" maxlength="3" name="target_keyword_options[per_page]" id="per_page" value="<?=esc_attr($per_page)?>" />
</fieldset>
<br>
<?=$button?>
<?php wp_nonce_field( 'screen-options-nonce', 'screenoptionnonce', false, false ); ?>