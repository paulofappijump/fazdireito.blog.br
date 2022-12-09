<?php

/**
 * Model for posts and terms
 *
 * Class Wpil_Model_Post
 */
class Wpil_Model_Post
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $content;
    public $links;
    public $slug = null;
    public $clicks = null;
    public $position = null;
    public $organic_traffic = null;
    public $editor = null;

    public function __construct($id, $type = 'post')
    {
        $this->id = (int)$id;
        $this->type = ($type === 'term') ? 'term': 'post';
    }

    function getTitle()
    {
        if (empty($this->title)) {
            // if the user wants to use the post's SEO title
            if(get_option('wpil_use_seo_titles', false)){
                $this->title = $this->getSEOTitle();
            }else{
                // otherwise, get the standard title
                if ($this->type == 'term') {
                    $term = get_term($this->id);
                    if (!empty($term) && !isset($term->errors)) {
                        $this->title = $term->name;
                    }
                    unset($term);
                } elseif ($this->type == 'post') {
                    $this->title = get_the_title($this->id);
                }
            }
        }

        return $this->title;
    }

    function getSEOTitle(){
        $title = '';

        // if yoast is active
        if(defined('WPSEO_VERSION')){
            // get the data object
            if($this->type === 'post'){
                $obj = get_post($this->id);
            }else{
                $obj = get_term($this->id);
            }

            if(empty($obj) || is_a($obj, 'WP_Error')){
                return $title;
            }

            if($this->type === 'post'){
                $obj = get_post($this->id);
            }else{
                $obj = get_term($this->id);
            }

            if($this->type === 'post'){
                // try gettting it's SEO title replace string directly
                $replace_string = WPSEO_Meta::get_value( 'title', $this->id );
            }else{
                // try gettting it's SEO title replace string directly
                $replace_string = WPSEO_Taxonomy_Meta::get_term_meta($this->id, $obj->taxonomy, 'title');
            }

            // if that didn't work, get the default string
            if(empty($replace_string)){
                if($this->type === 'post'){
                    // try gettting it's SEO title replace string directly
                    $replace_string = WPSEO_Options::get( 'title-' . $obj->post_type, '' );
                }else{
                    // try gettting it's SEO title replace string directly
                    $replace_string = WPSEO_Options::get( 'title-tax-' . $obj->taxonomy, '' );
                }
            }

            // setup the var replacer
            $replacer = new WPSEO_Replace_Vars;

            // get the title by replacing the values
            $title = $replacer->replace($replace_string, $obj);

        }elseif(defined('AIOSEO_VERSION')){ // AIOSEO is active
            if($this->type === 'post'){
                $Title_Getter = new AIOSEO\Plugin\Common\Meta\Title;
                $title = $Title_Getter->getTitle(get_post($this->id));

            }else{
                // term titles are only available in the Premium version of AIOSEO, and we're only supporting Free at the moment
            }
        }elseif(defined('RANK_MATH_VERSION')){
            if($this->type === 'post'){
                $title = RankMath\Post::get_meta('title', $this->id);
                if('' === $title){
                    $post = get_post($this->id);
                    if(!empty($post)){
                        $title = RankMath\Paper\Paper::get_from_options("pt_{$post->post_type}_title", $post, '%title% %sep% %sitename%');
                    }
                }
            }else{
                $term = get_term($this->id);
                $title = RankMath\Term::get_meta('title', $term, $term->taxonomy); // todo add a check to make sure the class exists
                if('' === $title){
                    $title = RankMath\Paper\Paper::get_from_options("tax_{$term->taxonomy}_title", $term);
                }
            }
        }

        // if we couldn't get the SEO title, get the WP title
        if(empty($title)){
            if ($this->type == 'term') {
                $term = get_term($this->id);
                if (!empty($term) && !isset($term->errors)) {
                    $title = $term->name;
                }
                unset($term);
            } elseif ($this->type == 'post') {
                $title = get_the_title($this->id);
            }
        }

        return $title;
    }

    function getLinks()
    {
        if (empty($this->links)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                if (!empty($term) && !isset($term->errors)) {
                    $this->links = (object)[
                        'view' => $this->getViewLink(),
                        'edit' => esc_url(admin_url('term.php?taxonomy=' . $term->taxonomy . '&post_type=post&tag_ID=' . $this->id)),
                        'export' => esc_url(admin_url("post.php?area=wpil_export&term_id=" . $this->id)),
                        'excel_export' => esc_url(admin_url("post.php?area=wpil_excel_export&term_id=" . $this->id)),
                        'refresh' => esc_url(admin_url("admin.php?page=link_whisper&type=post_links_count_update&term_id=" . $this->id))
                    ];
                }
                unset($term);
            } elseif ($this->type == 'post') {
                $this->links = (object)[
                    'view' => $this->getViewLink(),
                    'edit' => get_edit_post_link($this->id),
                    'export' => esc_url(admin_url("post.php?area=wpil_export&post_id=" . $this->id)),
                    'excel_export' => esc_url(admin_url("post.php?area=wpil_excel_export&post_id=" . $this->id)),
                    'refresh' => esc_url(admin_url("admin.php?page=link_whisper&type=post_links_count_update&post_id=" . $this->id)),
                ];
            }
        }

        if (empty($this->links)) {
            $this->links = (object)[
                'view' => '',
                'edit' => '',
                'export' => '',
                'excel_export' => '',
                'refresh' => '',
            ];
        }

        return $this->links;
    }

    /**
     * Gets the view link for the current post
     **/
    function getViewLink(){
        $forceHTTPS = Wpil_Settings::forceHTTPS();

        if ($this->type == 'term') {
            $term = get_term($this->id);
            if (!empty($term) && !isset($term->errors)) {
                $link = get_term_link($term);

                if(defined('BWLM_file')){
                    $woo_link_manage = new BeRocketLinkManager;
                    $link = $woo_link_manage->rewrite_terms($link, $term, $term->taxonomy);
                }

                // if the user wants to force the links to be https, update any http links
                if($forceHTTPS){
                    $link = str_replace('http:', 'https:', $link);
                }

                return $link;
            }
            unset($term);
        } elseif ($this->type == 'post') {
            // if the Yoast Primary Category class is available, register  their permalink filters
            if(class_exists('Yoast\WP\SEO\Integrations\Primary_Category')){
                $yoast_pc = new Yoast\WP\SEO\Integrations\Primary_Category;
                $yoast_pc->register_hooks();
            }

            // if the post isn't published yet
            if(in_array($this->getStatus(), array('draft', 'pending', 'future'))){
                // get the sample permalink
                if(function_exists('get_sample_permalink')){
                    $url_data = get_sample_permalink($this->id);
                }else{
                    $url_data = $this->get_sample_permalink($this->id);
                }

                if(false === strpos($url_data[0], '%postname%') && false === strpos($url_data[0], '%pagename%')){
                    $view_link = $url_data[0];
                }else{
                    $view_link = str_replace(array('%pagename%', '%postname%'), $url_data[1], $url_data[0]);    
                }
            }else{
                $view_link = get_the_permalink($this->id);

                if(defined('BWLM_file')){
                    $woo_link_manage = new BeRocketLinkManager;
                    $view_link = $woo_link_manage->rewrite_products($view_link, get_post($this->id));
                }
            }

            // if the user wants to force the links to be https, update any http links
            if($forceHTTPS){
                $view_link = str_replace('http:', 'https:', $view_link);
            }

            return $view_link;
        }

        return '';
    }

    /**
     * Checks to see if the post already has content stored
     *
     * @return bool True if content is stored, False if it isn't
     */
    function hasStoredContent()
    {
        return !empty($this->content);
    }

    /**
     * Update post content
     *
     * @param $content
     * @return $this
     */
    function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get post content depends on post type
     * I don't believe the content pulled here is used for adding links.
     * We should only be pulling it so we can check the content for links, but I want to confirm this.
     *
     * @return string
     */
    function getContent($remove_unprocessable = true)
    {
        if (empty($this->content)) {
            if ($this->type == 'term') {
                $content = term_description($this->id);
                $content .= $this->getAdvancedCustomFields();
                $content .= $this->getMetaContent();
                $this->editor = 'wordpress';
            } else {
                $content = '';
                // if the Thrive plugin is active
                if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
                    $thrive_active = get_post_meta($this->id, 'tcb_editor_enabled', true);
                    if(!empty($thrive_active)){
                        $thrive_content = get_post_meta($this->id, 'tve_updated_post', true);
                        if($thrive_content){
                            $content = $thrive_content;
                        }
                    }

                    if(get_post_meta($this->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($this->id, 'tve_landing_page', true)){
                        $content = get_post_meta($this->id, 'tve_updated_post_' . $thrive_template, true);
                    }

                    $this->editor = !empty($content) ? 'thrive' : null;
                }

                // if there's no content and the muffin builder is active
                if(empty($content) && defined('MFN_THEME_VERSION')){
                    // try getting the Muffin content
                    $content = Wpil_Editor_Muffin::getContent($this->id);
                    $this->editor = !empty($content) ? 'muffin' : null;
                }

                // if there's no content and the goodlayer builder is active
                if(empty($content) && defined('GDLR_CORE_LOCAL')){
                    // try getting the Goodlayer content
                    $content = Wpil_Editor_Goodlayers::getContent($this->id);
                    $this->editor = !empty($content) ? 'goodlayers' : null;
                }

                // if the Enfold Advanced editor is active
                if(defined('AV_FRAMEWORK_VERSION') && 'active' === get_post_meta($this->id, '_aviaLayoutBuilder_active', true)){
                    // get the editor content from the meta
                    $content = get_post_meta($this->id, '_aviaLayoutBuilderCleanData', true);
                    $this->editor = !empty($content) ? 'enfold': null;
                }

                // if we have no content and Cornerstone is active
                if(empty($content) && defined('CS_ASSET_REV')){
                    // try getting the Cornerstone content
                    $content = Wpil_Editor_Cornerstone::getContent($this->id);
                    $this->editor = !empty($content) ? 'cornerstone': null;
                }

                // if we have no content
                if(empty($content) && 
                    defined('ELEMENTOR_VERSION') && // Elementor is active
                    class_exists('\Elementor\Plugin') &&
                    isset(\Elementor\Plugin::$instance) && !empty(\Elementor\Plugin::$instance) && // and we have an instance
                    isset(\Elementor\Plugin::$instance->db) && !empty(\Elementor\Plugin::$instance->db) && // and the instance has a db method?
                    isset($this->id) && 
                    !empty($this->id)){
                    // check if the post was made with Elementor
                    if (\Elementor\Plugin::$instance->db->is_built_with_elementor($this->id)){
                        // if it was, use the power of Elementor to get the content
                        $content = Wpil_Editor_Elementor::getContent($this->id, true, $remove_unprocessable);
                        $this->editor = !empty($content) ? 'elementor': null;
                    }
                }

                // if WP Recipe is active and we're REALLY sure that this is a recipe
                if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes()) && 'wprm_recipe' === get_post_type($this->id)){
                    // get the recipe content
                    $content = get_post_meta($this->id, 'wprm_notes', true);
                    $this->editor = !empty($content) ? 'wp-recipe': null;
                }

                // Beaver Builder is active and this is a BB post
                if( defined('FL_BUILDER_VERSION') && 
                    class_exists('FLBuilder') && 
                    class_exists('FLBuilderModel') && 
                    is_array(FLBuilderModel::get_admin_settings_option('_fl_builder_post_types')) && 
                    in_array($this->getRealType(), FLBuilderModel::get_admin_settings_option('_fl_builder_post_types'), true) &&
                    FLBuilderModel::is_builder_enabled($this->id)
                ){
                    // try getting it's BB content
                    $beaver = get_post_meta($this->id, '_fl_builder_data', true);
                    if(!empty($beaver) && is_array($beaver)){
                        // go over all the beaver content and create a long string of it
                        foreach ($beaver as $key => $item) {
                            foreach (['text', 'html'] as $element) {
                                if (!empty($item->settings->$element) && !isset($item->settings->link)) { // if the element has content that we can process and isn't something that comes with a link
                                    $content .= ("\n" . $item->settings->$element);
                                }
                            }
                        }
                        $content = trim($content);
                        unset($beaver);
                        $this->editor = !empty($content) ? 'beaver': null;
                    }
                }

                if(empty($content)){
                    $item = get_post($this->id);
                    $content = $item->post_content;
                    $content .= $this->maybeGetExcerpt();
                    $content .= $this->getAdvancedCustomFields();
                    $content .= $this->getMetaContent();
                    $this->editor = !empty($content) ? 'wordpress': null;
                    $content .= $this->getThemifyContent();
                    $oxy_content = Wpil_Editor_Oxygen::getContent($this->id, $remove_unprocessable);
                    if(!empty($oxy_content)){
                        $content .= $oxy_content;
                        $this->editor = 'oxygen';
                    }
                }
            }

            if($remove_unprocessable){
                // remove any blocks that can't be processed
                $content = $this->removeUnprocessableBlocks($content);
            }

            $this->content = $content;
        }

        return $this->content;
    }

    /**
     * Gets content from meta fields created by plugins and themes. (Other than ACF)
     **/
    function getMetaContent(){
        $content = '';
        $fields = Wpil_Post::getMetaContentFieldList($this->type);

        foreach($fields as $field){
            if($this->type === 'post'){
                $data = get_post_meta($this->id, $field, true);
            }else{
                $data = get_term_meta($this->id, $field, true);
            }

            if(!is_string($data) || empty($data)){
                continue;
            }

            $content .= "\n" . $data;
        }

        /**
         * Filter the content so users can get their own field data from custom sources.
         * Or so they can modify the field content data.
         * @param string $content
         * @param int $id
         * @param string $type
         **/
        $content = apply_filters('wpil_meta_content_data_get', $content, $this->id, $this->type);

        return $content;
    }

    /**
     * Removes Gutenberg blocks that we can't add links to without breaking
     **/
    function removeUnprocessableBlocks($content){

        $constants = apply_filters('wpil_filter_unprocessable_block_constants', array(
            'WPRM_POST_TYPE',
            'WPSEO_VERSION',
            'RANK_MATH_VERSION',
            'WPZOOM_RCB_PLUGIN_FILE'
        ));

        $classes = apply_filters('wpil_filter_unprocessable_block_classes', array(
            'LazyBlocks'
        ));


        // if WordPress Recipe Maker is active and there's a recipe block in the content
        if(in_array('WPRM_POST_TYPE', $constants) && false !== strpos($content, '<!--WPRM Recipe')){
            //Remove WPRM plugin content
            $content = preg_replace('#(?<=<!--WPRM Recipe)(.*?)(?=<!--End WPRM Recipe-->)#ms', '', $content);
        }

        // if Yoast is active and there's a Yoast block in the content
        if(in_array('WPSEO_VERSION', $constants) && false !== strpos($content, '<!-- wp:yoast/')){
            // remove the Yoast blocks since adding links to them breaks them...
            $content = preg_replace('#(?<=<!-- wp:yoast/)(.*?)(?=<!-- /wp:yoast/)#ms', '', $content);
        }

        // if Rank Math is active and there's a Rank Math block in the content
        if(in_array('RANK_MATH_VERSION', $constants) && false !== strpos($content, '<!-- wp:rank-math/')){
            // remove the Rank Math blocks since adding links to them breaks them...
            $content = preg_replace('#(?<=<!-- wp:rank-math/)(.*?)(?=<!-- /wp:rank-math/)#ms', '', $content);
        }

        // if WP Zoom Recipes is active and there's a recipe block in the content
        if(in_array('WPZOOM_RCB_PLUGIN_FILE', $constants) && false !== strpos($content, '<!-- wp:wpzoom-recipe-card/')){
            // remove Zoom Recipe blocks since adding links to them breaks them...
            $content = preg_replace('#(?<=<!-- wp:wpzoom-recipe-card/block-recipe-card )(.*?)(?=/-->)#ms', '', $content);
        }

        // if LazyBlocks is active and there's a LazyBlocks block in the content
        if(in_array('LazyBlocks', $classes) && class_exists('LazyBlocks') && false !== strpos($content, '<!-- wp:lazyblock/')){
            // remove the LazyBlocks blocks since adding links to them breaks them...
            $content = preg_replace('#(?<=<!-- wp:lazyblock/)(.*?)(?=/-->)#ms', '', $content);
        }

        // if there are simple JSON data blocks in the content
        if(false !== strpos($content, '<!-- wp:') && (false !== strpos($content, '{"') || false !== strpos($content, '{\"'))){
            // remove the JSON part so we don't add links to it...
            $content = preg_replace('#(<!-- wp:[a-zA-Z\/_\-1-9]*? )({(?:.*?)})( \/-->)#', '$1$3', $content); // currently removing just the JSON in case the tag is useful

            // todo: either remove this or make it more intelligent so we can remove blocks that really can't be handled
            // if there are still JSON data blocks in the content
            /*if(false !== strpos($content, '<!-- wp:') && (false !== strpos($content, '{"') || false !== strpos($content, '{\"'))){
                // try removing opening/closing gutenberg blocks
                $content = preg_replace('#(<!-- wp:([a-zA-Z\/_\-1-9]*?) )({(.*?)})( -->)[\s\S]*?(<!-- \/wp:\2 -->)#', '$1$5$6', $content); // currently removing just the JSON in case the tag is useful
            }*/
        }

        return $content;
    }

    /**
     * Gets the post content without updating the post's content var.
     * This is mostly so we can deal with WP Recipe posts
     **/
    function getContentWithoutSetting($remove_unprocessable = true){
        // store the existing content
        $existing = $this->content;
        // unset the current content
        $this->content = '';
        // get the new content
        $content = $this->getContent($remove_unprocessable);
        // reset the post's existing content
        $this->content = $existing;
        // and return the found content
        return $content;
    }

    /**
     * Gets the post comment content as a single long string
     **/
    function getCommentContent(){
        global $wpdb;

        $id = (int) $this->id;
        $content = '';
        $data = $wpdb->get_results("SELECT `comment_content` FROM $wpdb->comments WHERE `comment_post_ID` = {$id}");

        if(!empty($data)){
            foreach($data as $dat){
                $content .= "\n" . $dat->comment_content;
            }
            $content .= ' ';
        }

        return $content;
    }

    /**
     * Gets post content by direct database query instead of relying on WP functionality.
     * The post content is intended for cases where formatting isn't important since it doesn't make use of any WP filters.
     * At the moment, I'm only planning to use it for simple content checks.
     * To keep the check fast, I'm not going to check for ACF content.
     *
     * This doesn't set the object's content.
     *
     * @param bool $remove_unprocessable
     * @return string $content The post object's content
     **/
    function getContentDirectly($remove_unprocessable = true){
        global $wpdb;

        $content = '';

        if ($this->type == 'term') {
            $desc = $wpdb->get_results($wpdb->prepare("SELECT `description` FROM {$wpdb->term_taxonomy} WHERE `term_id` = %d", $this->id));
            if(!empty($desc)){
                $content = $desc[0]->description;
                $this->editor = 'wordpress';
            }
        } else {
            // if the Thrive plugin is active
            if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
                $thrive_active = $this->directlyGetPostMeta('tcb_editor_enabled');
                if(!empty($thrive_active)){
                    $thrive_content = $this->directlyGetPostMeta('tve_updated_post');
                    if($thrive_content){
                        $content = $thrive_content;
                    }
                }

                if($this->directlyGetPostMeta('tve_landing_set') && $thrive_template = $this->directlyGetPostMeta('tve_landing_page')){
                    $content = $this->directlyGetPostMeta('tve_updated_post_' . $thrive_template);
                }

                $this->editor = !empty($content) ? 'thrive': null;
            }

            // if there's no content and the muffin builder is active
            if(empty($content) && defined('MFN_THEME_VERSION')){
                // try getting the Muffin content
                $content = Wpil_Editor_Muffin::getContent($this->id); // using standard content method. If Muffin users complain, upgrade

                $this->editor = !empty($content) ? 'muffin': null;
            }

            // if there's no content and the goodlayer builder is active
            if(empty($content) && defined('GDLR_CORE_LOCAL')){
                // try getting the Goodlayer content
                $content = Wpil_Editor_Goodlayers::getContent($this->id); // using standard content method. If Goodlayer users complain, upgrade

                $this->editor = !empty($content) ? 'goodlayers': null;
            }

            // if the Enfold Advanced editor is active
            if(defined('AV_FRAMEWORK_VERSION') && 'active' === $this->directlyGetPostMeta('_aviaLayoutBuilder_active')){
                // get the editor content from the meta
                $content = $this->directlyGetPostMeta('_aviaLayoutBuilderCleanData');

                $this->editor = !empty($content) ? 'enfold': null;
            }

            // if we have no content and Cornerstone is active
            if(empty($content) && defined('CS_ASSET_REV')){
                // try getting the Cornerstone content
                $content = Wpil_Editor_Cornerstone::getContent($this->id); // using standard content method. If Cornerstone users complain, upgrade

                $this->editor = !empty($content) ? 'cornerstone': null;
            }

            // TODO: Get the Elementor content!

            // if WP Recipe is active and we're REALLY sure that this is a recipe
            if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes()) && 'post' === $this->type && 'wprm_recipe' === get_post_type($this->id)){
                // get the recipe content
                $content = $this->directlyGetPostMeta('wprm_notes');

                $this->editor = !empty($content) ? 'wp-recipe': null;
            }

            if(empty($content)){
                $data = $wpdb->get_results($wpdb->prepare("SELECT `post_content`, `post_excerpt`, `post_type` FROM {$wpdb->posts} WHERE `ID` = %d", $this->id));
                if(!empty($data)){
                    $content = $data[0]->post_content;
                    // if WooCommerce is active, include the post excerpt too
                    if(defined('WC_PLUGIN_FILE') && 'product' === $data[0]->post_type && in_array('product', Wpil_Settings::getPostTypes())){
                        $content .= $data[0]->post_excerpt;
                    }
                }

                $this->editor = !empty($content) ? 'wordpress': null;

                if(class_exists('Themify_Builder')){
                    $content .= $this->getThemifyContent(); // using standard content method. If Themify users complain, upgrade
                }

                if(defined('CT_PLUGIN_MAIN_FILE')){
                    // try getting the content
                    try {
                        $oxy_content = Wpil_Editor_Oxygen::getContent($this->id); // using standard content method. If Oxygen users complain, upgrade

                        $this->editor = !empty($oxy_content) ? 'oxygen': null;
                        $content .= $oxy_content;
                    } catch (Throwable $t) {
                    } catch (Exception $e) {
                    }
                }
            }
        }

        if($remove_unprocessable){
            // remove any blocks that can't be processed
            $content = $this->removeUnprocessableBlocks($content);
        }

        // filter the content so users can add their own content
        $content = apply_filters('wpil_meta_content_data_get_directly', $content, $this->id, $this->type);

        return $content;
    }

    /**
     * Directly queries the database for post meta belonging to this post
     * @param string $key The meta key for the data we want to get
     * @return string|mixed Returns an empty string if there's no data. If there is data, an unserialized version of it is returned.
     **/
    function directlyGetPostMeta($key = ''){
        global $wpdb;

        if(empty($key)){
            return '';
        }

        $data = $wpdb->get_results($wpdb->prepare("SELECT `meta_value` FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = %s", $this->id, $key));

        if(empty($data) || !isset($data[0]) || !property_exists($data[0], 'meta_value')){
            return '';
        }

        $data = $data[0]->meta_value;

        return maybe_unserialize($data);
    }

    /**
     * Clears the post/term cache for the current item
     *
     * @return bool
     */
    function clearPostCache()
    {
        if($this->type === 'post'){
            $clear = wp_cache_delete($this->id, 'posts');
        }else{
            $clear = wp_cache_delete($this->id, 'terms');
        }

        return $clear;
    }

    /**
     * Get updated post content
     *
     * @return string
     */
    function getFreshContent()
    {
        if($this->type === 'post'){
            wp_cache_delete($this->id, 'posts');
        }else{
            wp_cache_delete($this->id, 'terms');
        }
        $this->content = null;
        return $this->getContent();
    }

    /**
     * Get not modified post content
     *
     * @return string
     */
    function getCleanContent()
    {
        if ($this->type == 'term') {
            wp_cache_delete($this->id, 'terms');
            $term = get_term($this->id);
            $content = $term->description;
        } else {
            wp_cache_delete($this->id, 'posts');
            $p = get_post($this->id);
            $content = $p->post_content;
        }

        return $content;
    }

    /**
     * Get post slug depends on post type
     *
     * @return string|null
     */
    function getSlug($leading_slash = true)
    {
        if (empty($this->slug)) {
            if ($this->type == 'term') {
                $term = get_term($this->id);
                $this->slug = $term->slug;
            } else {
                // Todo make a slug getter that uses the post url so it works with draft posts
                $post = get_post($this->id);
                $this->slug = $post->post_name;
            }
        }

        $slug = ($leading_slash) ? '/' . $this->slug: $this->slug;;

        return $slug;
    }

    /**
     * Gets the post excerpt if this is a post type that we process excerpt content for.
     * Currently, only WooCommerce is supported
     **/
    function maybeGetExcerpt(){
        $excerpt = '';

        // terms don't have execerpts
        if($this->type === 'term'){
            // so just return the empty string
            return $excerpt;
        }

        // if WooCommerce is active and we're really sure this is a product
        if(defined('WC_PLUGIN_FILE') && in_array('product', Wpil_Settings::getPostTypes()) && 'product' === get_post_type($this->id)){
            $post = get_post($this->id);
            $excerpt = $post->post_excerpt;
        }

        return $excerpt;
    }

    /**
     * Get post content from advanced custom fields
     *
     * @return string
     */
    function getAdvancedCustomFields()
    {
        $content = '';

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $content;
        }

        if($this->type === 'post'){
            foreach (Wpil_Post::getAdvancedCustomFieldsList($this->id) as $field) {
                if ($c = get_post_meta($this->id, $field, true)) {
                    if(is_array($c)){
                        continue;
                    }

                    $content .= "\n" . $c;
                }
            }
        }else{
            foreach (Wpil_Term::getAdvancedCustomFieldsList($this->id) as $field) {
                if ($c = get_term_meta($this->id, $field, true)) {
                    if(is_array($c)){
                        continue;
                    }

                    $content .= "\n" . $c;
                }
            }
        }

        return $content;
    }

    /**
     * Get post type.
     * Consider reworking so it says what the term type actually is
     */
    function getType()
    {
        $type = 'Post';
        if ($this->type == 'term') {
            $type = 'Category';
            $term = get_term($this->id);
            if (!is_a($term, 'WP_Error') && $term->taxonomy == 'post_tag') {
                $type = 'Tag';
            }
        } elseif ($this->type == 'post') {
            $item = get_post($this->id);
            $type = ucfirst($item->post_type);
        }

        return $type;
    }

    /**
     * Get real post type
     *
     * @return string
     */
    function getRealType()
    {
        $type = '';
        if ($this->type == 'term') {
            $term = get_term($this->id);
            $type = !empty($term->taxonomy) ? $term->taxonomy : '';
        } elseif ($this->type == 'post') {
            $item = get_post($this->id);
            $type = !empty($item->post_type) ? $item->post_type : '';
        }

        return $type;
    }

    /**
     * Get post status
     *
     * @return string
     */
    function getStatus()
    {
        if (empty($this->status)) {
            $this->status = 'publish';
            if ($this->type == 'post') {
                $item = get_post($this->id);
                if(!empty($item)){
                    $this->status = $item->post_status;
                }
            }
        }

        return $this->status;
    }

    /**
     * Updates post content and optionally the post excerpt
     *
     * @param $content
     * @param $excerpt
     */
    function updateContent($content, $excerpt = '')
    {
        global $wpdb;

        if ($this->type == 'term') {
            $updated = $wpdb->update($wpdb->term_taxonomy, ['description' => $content], ['term_id' => $this->id]);
        } else {
            $update = (!empty($excerpt)) ? ['post_content' => $content, 'post_excerpt' => $excerpt]: ['post_content' => $content];
            $updated = $wpdb->update($wpdb->posts, $update, ['ID' => $this->id]);
        }

        return $updated;
    }

    /**
     * Get Inbound Internal Links list
     *
     * @return array
     */
    function getInboundInternalLinks($count = false)
    {
        return $this->getLinksData('wpil_links_inbound_internal_count', $count);
    }

    /**
     * Get Outbound Internal Links list
     *
     * @return array
     */
    function getOutboundInternalLinks($count = false)
    {
        return $this->getLinksData('wpil_links_outbound_internal_count', $count);
    }

    /**
     * Get Outbound External Links list
     *
     * @return array
     */
    function getOutboundExternalLinks($count = false)
    {
        return $this->getLinksData('wpil_links_outbound_external_count', $count);
    }

    /**
     * Get Post Links list
     *
     * @return array|int
     */
    function getLinksData($key, $count)
    {
        if (!$count) {
            $key .= '_data';
        }

        if ($this->type == 'term') {
            $links = get_term_meta($this->id, $key, $single = true);
        } else {
            $links = get_post_meta($this->id, $key, $single = true);
        }

        if (empty($links)) {
            $links = $count ? 0 : [];
        }

        return $links;
    }

    /**
     * Get Themify Builder content
     *
     * @return string
     */
    function getThemifyContent()
    {
        $content = '';
        $item = get_post($this->id);

        if (strpos($item->post_content, 'wp:themify-builder') !== false) {
            $content = Wpil_Editor_Themify::getContent($this->id);
        }

        return $content;
    }

    /**
     * Check if post status is checked in the settings page
     *
     * @return bool
     */
    function statusApproved()
    {
        if (in_array($this->getStatus(), Wpil_Settings::getPostStatuses())) {
            return true;
        }

        return false;
    }

    /**
     * Borrowed from WP without changing except for restricting when the 'get_sample_permalink' filter is called.
     * From V 5.5.1
     **/
    function get_sample_permalink( $id, $title = null, $name = null ) {
        $post = get_post( $id );
        if ( ! $post ) {
            return array( '', '' );
        }
     
        $ptype = get_post_type_object( $post->post_type );
     
        $original_status = $post->post_status;
        $original_date   = $post->post_date;
        $original_name   = $post->post_name;
     
        // Hack: get_permalink() would return ugly permalink for drafts, so we will fake that our post is published.
        if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
            $post->post_status = 'publish';
            $post->post_name   = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
        }
     
        // If the user wants to set a new name -- override the current one.
        // Note: if empty name is supplied -- use the title instead, see #6072.
        if ( ! is_null( $name ) ) {
            $post->post_name = sanitize_title( $name ? $name : $title, $post->ID );
        }
     
        $post->post_name = wp_unique_post_slug( $post->post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );
     
        $post->filter = 'sample';
     
        $permalink = get_permalink( $post, true );
     
        // Replace custom post_type token with generic pagename token for ease of use.
        $permalink = str_replace( "%$post->post_type%", '%pagename%', $permalink );
     
        // Handle page hierarchy.
        if ( $ptype->hierarchical ) {
            $uri = get_page_uri( $post );
            if ( $uri ) {
                $uri = untrailingslashit( $uri );
                $uri = strrev( stristr( strrev( $uri ), '/' ) );
                $uri = untrailingslashit( $uri );
            }
     
            /** This filter is documented in wp-admin/edit-tag-form.php */
            $uri = apply_filters( 'editable_slug', $uri, $post );
            if ( ! empty( $uri ) ) {
                $uri .= '/';
            }
            $permalink = str_replace( '%pagename%', "{$uri}%pagename%", $permalink );
        }
     
        /** This filter is documented in wp-admin/edit-tag-form.php */
        $permalink         = array( $permalink, apply_filters( 'editable_slug', $post->post_name, $post ) );
        $post->post_status = $original_status;
        $post->post_date   = $original_date;
        $post->post_name   = $original_name;
        unset( $post->filter );
     
        /**
         * Filters the sample permalink.
         *
         * @since 4.4.0
         *
         * @param array   $permalink {
         *     Array containing the sample permalink with placeholder for the post name, and the post name.
         *
         *     @type string $0 The permalink with placeholder for the post name.
         *     @type string $1 The post name.
         * }
         * @param int     $post_id   Post ID.
         * @param string  $title     Post title.
         * @param string  $name      Post name (slug).
         * @param WP_Post $post      Post object.
         */
        if(!defined('EDIT_FLOW_VERSION')){ // don't apply filters for the Edit Flow plugin since it makes a call to 'get_sample_permalink', which doesn't exist...
            return apply_filters( 'get_sample_permalink', $permalink, $post->ID, $title, $name, $post );
        }

    }

    /**
     * Gets the organic traffic for the current post.
     **/
    function get_organic_traffic(){
        if(is_null($this->organic_traffic)){
            $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($this->id, $this->type, 'gsc-keyword', false);

            if(empty($keywords) || !is_array($keywords)){
                $this->organic_traffic = (object) array(
                    'clicks' => 0, 
                    'impressions' => 0,
                    'ctr' => 0,
                    'position' => 0
                );
                return $this->organic_traffic;
            }

            $position = 0;
            $clickes = 0;
            $impressions = 0;
            $ctr = 0;
            foreach($keywords as $keyword){
                if(!empty($keyword->clicks)){
                    $clickes += $keyword->clicks;
                }

                if(!empty($keyword->impressions)){
                    $impressions += $keyword->impressions;
                }

                $position += floatval($keyword->position);
            }

            if($position > 0){
                $position = round($position/count($keywords), 2);
            }

            if(!empty($clickes) && !empty($impressions)){
                $ctr = round($clickes/$impressions, 2);
            }

            $this->organic_traffic = (object) array(
                'clicks' => $clickes, 
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => $position
            );
        }

        return $this->organic_traffic;
    }

    /**
     * Gets the editor that the post uses to make the content.
     * (As near as we can tell when we set the content)
     * @return string|bool Returns the editor's name or false if we haven't pulled content yet
     **/
    function getContentEditor(){
        if(!empty($this->editor)){
            return $this->editor;
        }

        return false;
    }

    /**
     * Gets the current post's terms and returns an array of them.
     * Returns an empty array if there's no terms or if this is a term itself
     **/
    function getPostTerms($args = array()){
        if($this->type === 'term'){
            return array();
        }

        $taxes = get_object_taxonomies(get_post($this->id));
        $terms = wp_get_object_terms($this->id, $taxes, ['fields' => 'all_with_object_id']);
        if (empty($terms) || is_a($terms, 'WP_Error')) {
            $terms = [];
        }

        if(array_key_exists('hierarchical', $args)){
            $hier = (bool) $args['hierarchical'];
            $filtered_terms = array();
            foreach($terms as $term){
                $tax = get_taxonomy($term->taxonomy);
                if($tax->hierarchical === $hier){
                    $filtered_terms[] = $term;
                }
            }

            return $filtered_terms;
        }

        return $terms;
    }
}
