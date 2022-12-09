<div id="wpil-keyword-select-metabox" class="categorydiv wpil_styles">
    <ul id="keyword-tabs" class="category-tabs">
        <li class="tabs keyword-tab"><a href="#keywords-all" data-keyword-tab="keywords-all">All Keywords</a></li>
        <?php if(in_array('gsc', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-gsc" data-keyword-tab="keywords-gsc">Google Search Console Keywords</a></li>
        <?php } ?>
        <?php if(in_array('yoast', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-yoast" data-keyword-tab="keywords-yoast">Yoast Keywords</a></li>
        <?php } ?>
        <?php if(in_array('rank-math', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-rank-math" data-keyword-tab="keywords-rank-math">Rank Math Keywords</a></li>
        <?php } ?>
        <?php if(in_array('aioseo', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-aioseo" data-keyword-tab="keywords-aioseo">All in one SEO Keywords</a></li>
        <?php } ?>
        <?php if(in_array('seopress', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-seopress" data-keyword-tab="keywords-seopress">SEOPress Keywords</a></li>
        <?php } ?>
        <?php if(in_array('post-content', $keyword_sources)){ ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-post-content" data-keyword-tab="keywords-post-content">Page Content Keywords</a></li>
        <?php } ?>
        <li class="hide-if-no-js keyword-tab"><a href="#keywords-custom" data-keyword-tab="keywords-custom">Custom Keywords</a></li>
        <?php if(!empty($is_metabox)){ ?>
        <li style="display: inline-block; height: 1px; float: right; margin: 0; padding: 0; position: relative; top: -8px; right: 0px;" class="target-keyword-help">
            <div class="wpil_help" style="display: inline-block; float:none;">
                <i class="dashicons dashicons-editor-help"></i>
                <div><?php _e('Target Keywords are used when making Inbound Link suggestions. The keywords help make better matches to this post.', 'wpil'); ?></div>
            </div>
        </li>
        <?php } ?>
    </ul>

    <div id="keywords-all" class="tabs-panel">
        <input type="hidden" value="0">
        <ul id="keywordchecklist" data-wp-lists="list:category" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){ 
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-all-<?php echo $id; ?>" class="all-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" data-keyword-id="<?php echo $id; ?>" <?php echo ($keyword->checked || $keyword->auto_checked) ? 'checked="checked"' : ''; ?> value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php }?>
        </ul>
    </div>

    <?php if(in_array('gsc', $keyword_sources)){ // Show the GSC keywords ?>
    <div id="keywords-gsc" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-gsc" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('gsc-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-gsc-<?php echo $id; ?>" class="gsc-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked || $keyword->auto_checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <?php if(in_array('yoast', $keyword_sources)){ // Show the Yoast keywords  ?>
    <div id="keywords-yoast" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-yoast" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('yoast-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-yoast-<?php echo $id; ?>" class="yoast-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <?php if(in_array('rank-math', $keyword_sources)){ // Show the Rank Math keywords  ?>
    <div id="keywords-rank-math" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-rank-math" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('rank-math-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-rank-math-<?php echo $id; ?>" class="rank-math-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <?php if(in_array('aioseo', $keyword_sources)){ // Show the AIOSEO keywords  ?>
    <div id="keywords-aioseo" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-aioseo" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('aioseo-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-aioseo-<?php echo $id; ?>" class="aioseo-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <?php if(in_array('seopress', $keyword_sources)){ // Show the SEOPress keywords  ?>
    <div id="keywords-seopress" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-seopress" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('seopress-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-seopress-<?php echo $id; ?>" class="seopress-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <?php if(in_array('post-content', $keyword_sources)){ // Show the Post Content keywords  ?>
    <div id="keywords-post-content" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-post-content" class="categorychecklist form-no-clear">
            <?php foreach($keywords as $keyword){
                if('post-content-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-post-content-<?php echo $id; ?>" class="post-content-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                </label>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>
    <div id="keywords-custom" class="tabs-panel" style="display: none;">
        <ul id="keywordchecklist-custom" class="categorychecklist form-no-clear">
        <?php foreach($keywords as $keyword){
                if('custom-keyword' !== $keyword->keyword_type){
                    continue;
                }
                $id = $keyword->keyword_index;
            ?>
            <li id="keyword-custom-<?php echo $id; ?>" class="custom-keyword">
                <label class="selectit">
                    <input type="checkbox" class="keyword-<?php echo $id; ?>" <?php echo ($keyword->checked) ? 'checked="checked"' : ''; ?> data-keyword-id="<?php echo $id; ?>" value="<?php echo $id; ?>">
                    <?php echo esc_html($keyword->keywords);?>
                    <i class="wpil_target_keyword_delete dashicons dashicons-no-alt" data-keyword-id="<?php echo $id; ?>" data-keyword-type="custom-keyword" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'delete-target-keywords-' . $id); ?>"></i>
                </label>
            </li>
            <?php } ?>
        </ul>
        <div class="create-post-keywords" style=" padding-bottom: 10px;">
            <a href="#" style="vertical-align: top;" class="button-primary wpil-create-target-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'create-target-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>" data-post-type="<?php echo esc_attr($post->type); ?>"><?php _e('Create New Keyword', 'wpil'); ?></a>
            <div class="wpil-create-target-keywords-row-container" style="width: calc(100% - 300px); display: inline-block;">
                <input style="width: 100%;vertical-align: baseline;" type="text" class="create-custom-target-keyword-input" placeholder="<?php _e('New Custom Keyword', 'wpil'); ?>">
            </div>
            <a href="#" style="vertical-align: top;" class="button-primary wpil-add-target-keyword-row" style="margin-left:10px;"><?php _e('Add Row', 'wpil'); ?></a>
        </div>
    </div>
    <?php $hide = (empty($keywords)) ? ' display:none; ': '';?>
    <button class="button-primary wpil-update-selected-keywords" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'update-selected-keywords-' . $post->id); ?>" data-post-id="<?php echo $post->id; ?>" style="margin: 15px 0 0 0;<?php echo $hide; ?>"><?php _e('Update Existing Keywords', 'wpil'); ?></button>
</div>