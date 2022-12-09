<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_TargetKeyword
 */
class Wpil_Table_TargetKeyword extends WP_List_Table
{
    function get_columns()
    {
        $screen_options = get_user_meta(get_current_user_id(), 'target_keyword_options', true);
        $show_date = (!empty($screen_options['show_date']) && $screen_options['show_date'] == 'off') ? false : true;
        $show_traffic = (!empty($screen_options['show_traffic']) && $screen_options['show_traffic'] == 'off') ? false : true;

        $options = array(
            'post_title' => __('Post', 'wpil'),
        );

        if($show_date){
            $options['date'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('Published', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The Published date is the date that the post was published on.', 'wpil') . '</div>
                </div>
            </div>';
        }

        $options['word_cloud'] = 
        '<div class="wpil-target-keyword-header-tooltip">' . __('Active Keywords', 'wpil') . 
            '<div class="wpil_help">
                <i class="dashicons dashicons-editor-help"></i>
                <div class="wpil-help-text" style="display: none;">' . __('The Active Keywords are the keywords that Link Whisper will use to improve it\'s link suggestions.', 'wpil') . '</div>
            </div>
        </div>';

        if($show_traffic){
            $options['organic_traffic'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('Organic Traffic', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The number of clicks this page has received from Google organic search in the last 30 days. Google search console does not always provide all the data, so your actual organic traffic may vary from this number.', 'wpil') . '</div>
                </div>
            </div>';
        }

        $selected_sources = Wpil_TargetKeyword::get_active_keyword_sources();
        if(in_array('gsc', $selected_sources, true)){
            $options['gsc'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('GSC Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The GSC Keywords are the keywords that we\'ve received from Google and can use when making link suggestions. The keywords are pulled from a date range of 30 days.', 'wpil') . '</div>
                </div>
            </div>';
        }

        if(in_array('yoast', $selected_sources, true)){
            $options['yoast'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('Yoast Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The Yoast Keywords are the keywords that Link Whisper has extracted from the Yoast SEO data for the post and can use when making link suggestions.', 'wpil') . '</div>
                </div>
            </div>';
        }

        if(in_array('rank-math', $selected_sources, true)){
            $options['rank-math'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('Rank Math Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The Rank Math Keywords are the keywords that Link Whisper has extracted from the Rank Math data for the post and can use when making link suggestions.', 'wpil') . '</div>
                </div>
            </div>';
        }

        if(in_array('aioseo', $selected_sources, true)){
            $options['aioseo'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('All in One SEO Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The All in One SEO Keywords are the keywords that Link Whisper has extracted from the All in One SEO data for the post and can use when making link suggestions.', 'wpil') . '</div>
                </div>
            </div>';
        }

        if(in_array('seopress', $selected_sources, true)){
            $options['seopress'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('SEOPress Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The SEOPress Keywords are the keywords that Link Whisper has extracted from the SEOPress data for the post and can use when making link suggestions.', 'wpil') . '</div>
                </div>
            </div>';
        }

        if(in_array('post-content', $selected_sources, true)){
            $options['post-content'] = 
            '<div class="wpil-target-keyword-header-tooltip">' . __('Page Content Keywords', 'wpil') . 
                '<div class="wpil_help">
                    <i class="dashicons dashicons-editor-help"></i>
                    <div class="wpil-help-text" style="display: none;">' . __('The Page Content Keywords are the keywords that Link Whisper has extracted from the page\'s content. Currently, only the page\'s title and slug are used as keywords. If the title and slug are virturally the same, the slug will be omitted.', 'wpil') . '</div>
                </div>
            </div>';
        }

        $options['custom'] = 
        '<div class="wpil-target-keyword-header-tooltip">' . __('Custom Keywords', 'wpil') . 
            '<div class="wpil_help">
                <i class="dashicons dashicons-editor-help"></i>
                <div class="wpil-help-text" style="display: none;">' . __('The Custom Keywords are the keywords that you create for use in making link suggestions', 'wpil') . '</div>
            </div>
        </div>';

        return $options;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'target_keyword_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : false;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        if(empty($per_page)){
            $options2 = get_user_meta(get_current_user_id(), 'report_options', true);
            $per_page = !empty($options2['per_page']) ? $options2['per_page'] : 20;
        }

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_TargetKeyword::getData($per_page, $page, $search, $orderby, $order);
        $this->items = $data['data'];

        $this->set_pagination_args(array(
            'total_items' => $data['total_items'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total_items'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        if(is_array($item) && isset($item['post'])){
            $post = $item['post'];
        }elseif(!empty($item)){
            $post = new Wpil_Model_Post($item->ID, $item->post_type);
        }

        switch($column_name) {
            case 'post_title':
                $actions = [];

                $title = '<a href="' . esc_url($post->getLinks()->edit) . '" class="row-title">' . esc_attr($post->getTitle()) . '</a>';
                $actions['view'] = '<a target=_blank href="' . esc_url($post->getLinks()->view) . '">View</a>';
                $actions['edit'] = '<a target=_blank href="' . esc_url($post->getLinks()->edit) . '">Edit</a>';
                $actions['add_inbound'] = '<a target=_blank href="' . esc_url(admin_url("admin.php?post_id={$post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']))) . '">Add Inbound Links</a>';
        
                return $title . $this->row_actions($actions);
            case 'word_cloud':
                $keywords = Wpil_TargetKeyword::get_keywords_by_post_ids($post->id, $post->type);
                $keyword_string = '';
                $has_active_keywords = false;
                foreach($keywords as $keyword){
                    $hidden = 'style="display:none;"';
                    if(!empty($keyword->checked) || !empty($keyword->auto_checked)){
                        $has_active_keywords = true;
                        $hidden = '';
                    }

                    $keyword_string .= '<li id="active-keyword-' . $keyword->keyword_index . '" class="wpil-target-keyword-active-kywrd" ' . $hidden . '>' . esc_html($keyword->keywords) . '</li>';
                }

                $hidden_notice = '';
                if($has_active_keywords){
                    $hidden_notice = ' style="display:none;" ';
                }

                $no_active_keys = '<li class="no-active-keywords-notice"' . $hidden_notice . '>' . __('No Active Keywords', 'wpil') . '</li>';

                return '<ul>' . $no_active_keys . $keyword_string . '</ul>';
            case 'organic_traffic':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'gsc-keyword', false);
                $clicks = 0;
                $position = 0;
                foreach($keywords as $keyword){
                    $clicks += $keyword->clicks;
                    $position += floatval($keyword->position);
                }

                if($position > 0){
                    $position = round($position/count($keywords), 2);
                }

                return '<ul>
                            <li>' . __('Clicks: ', 'wpil') . $clicks . '</li>
                            <li>' . __('AVG Position: ', 'wpil') . $position . '</li>
                        </ul>';
            case 'gsc':
                $keywords = Wpil_TargetKeyword::filter_duplicate_gsc_keywords(Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type));
                $keyword_type = 'gsc-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'yoast':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'yoast-keyword');
                $keyword_type = 'yoast-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'rank-math':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'rank-math-keyword');
                $keyword_type = 'rank-math-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'aioseo':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'aioseo-keyword');
                $keyword_type = 'aioseo-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'seopress':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'seopress-keyword');
                $keyword_type = 'seopress-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'post-content':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'post-content-keyword');
                $keyword_type = 'post-content-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'custom':
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($post->id, $post->type, 'custom-keyword');
                $keyword_type = 'custom-keyword';
                $col_number = $this->get_keyword_column_number($column_name);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_target_keywords.php';
                return ob_get_clean();
            case 'date':
                if($post->type === 'post'){
                    return get_the_date('', $post->id);
                }else{
                    return __('Not Set', 'wpil');
                }
            default:
                return $item->$column_name;
        }
    }

    function get_sortable_columns()
    {
        return [
            'post_title'        => ['post_title', false],
            'gsc'               => ['gsc', false],
            'yoast'             => ['yoast', true],
            'rank-math'         => ['rank-math', true],
            'aioseo'            => ['aioseo', true],
            'seopress'          => ['seopress', true],
            'organic_traffic'   => ['organic_traffic', true],
            'custom'            => ['custom', false],
            'post-content'      => ['post-content', true],
            'date'              => ['date', false]
        ];
    }

    function extra_tablenav( $which ) {
        if ($which == "top") {
            $post_type = Wpil_Filter::linksPostType();
            $post_type = !empty($post_type) ? $post_type : 0;
            ?>
            <div class="alignright actions bulkactions" id="wpil_links_table_filter">
                <select name="keyword_post_type">
                    <option value="0"><?php _e('All Post Types', 'wpil'); ?></option>
                    <?php foreach (Wpil_Settings::getAllTypes() as $type) : ?>
                        <option value="<?=$type?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                    <?php endforeach; ?>
                </select>
                <span class="button-primary">Filter</span>
                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
            </div>
            <?php
        }
    }

    /**
     * Generates the columns for a single row of the table.
     *
     * @since 3.1.0
     *
     * @param object $item The current item.
     */
    protected function single_row_columns( $item ) {
        list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

        foreach ( $columns as $column_name => $column_display_name ) {
            $classes = "$column_name column-$column_name";
            if ( $primary === $column_name ) {
                $classes .= ' has-row-actions column-primary';
            }

            if ( in_array( $column_name, $hidden, true ) ) {
                $classes .= ' hidden';
            }
 
            if(in_array($column_name, array('gsc', 'yoast', 'rank-math', 'aioseo', 'seopress', 'post-content', 'custom'), true)){
                $classes .= ' wpil-dropdown-column';
            }

            // Comments column uses HTML in the display name with screen reader text.
            // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
            $data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';
 
            $attributes = "class='$classes' $data";
 
            if ( 'cb' === $column_name ) {
                echo '<th scope="row" class="check-column">';
                echo $this->column_cb( $item );
                echo '</th>';
            } elseif ( method_exists( $this, '_column_' . $column_name ) ) {
                echo call_user_func(
                    array( $this, '_column_' . $column_name ),
                    $item,
                    $classes,
                    $data,
                    $primary
                );
            } elseif ( method_exists( $this, 'column_' . $column_name ) ) {
                echo "<td $attributes>";
                echo call_user_func( array( $this, 'column_' . $column_name ), $item );
                echo $this->handle_row_actions( $item, $column_name, $primary );
                echo '</td>';
            } else {
                echo "<td $attributes>";
                echo $this->column_default( $item, $column_name );
                echo $this->handle_row_actions( $item, $column_name, $primary );
                echo '</td>';
            }
        }
    }

    /**
     * Calculates which Target Keyword column is the current one
     * @param string $col The name of the current column
     * @return int The column's number, counting from the left
     **/
    function get_keyword_column_number($col = ''){
        $columns = array(
            'gsc'           => true,
            'yoast'         => true,
            'rank-math'     => true,
            'aioseo'        => true,
            'seopress'      => true,
            'post-content'  => true,
            'custom'        => true
        );

        $active = array_flip(array_keys(array_intersect_key($columns, $this->_column_headers[0])));

        return isset($active[$col]) ? $active[$col] : false;
    }

}