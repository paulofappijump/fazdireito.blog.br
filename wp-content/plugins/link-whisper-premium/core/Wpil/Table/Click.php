<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Click
 */
class Wpil_Table_Click extends WP_List_Table
{
    function get_columns()
    {
        $screen_options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_date = (!empty($screen_options['show_date']) && $screen_options['show_date'] == 'off') ? false : true;

        $options = array(
            'post_title' => __('Post', 'wpil'),
        );

        if($show_date){
            $options['date'] = __('Published', 'wpil');
        }

        $options['post_type'] = __('Post Type');
        $options['clicks'] = __('Link Clicks');

        return $options;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
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
        $data = Wpil_ClickTracker::get_data($per_page, $page, $search, $orderby, $order);
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
            $post = $item->post;
        }elseif(!empty($item)){
            $post = new Wpil_Model_Post($item->ID, $item->type);
        }

        switch($column_name) {
            case 'post_title':
                $actions = [];
                $title = '<a href="' . esc_url($post->getLinks()->edit) . '" class="row-title">' . esc_html($post->getTitle()) . '</a>';
                $actions['view'] = '<a target=_blank href="' . esc_url($post->getLinks()->view) . '">View</a>';
                $actions['edit'] = '<a target=_blank href="' . esc_url($post->getLinks()->edit) . '">Edit</a>';
                $actions['add_inbound'] = '<a target=_blank href="' . esc_url(admin_url("admin.php?post_id={$post->id}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']))) . '">Add Inbound Links</a>';
        
                return $title . $this->row_actions($actions);
            case 'date':
                return ($item->type === 'post') ? $item->post_date: __('Not Set', 'wpil');
            case 'clicks':
                $click_data = Wpil_ClickTracker::get_click_dropdown_data($post->id, $post->type);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_clicks.php';
                return ob_get_clean();
            default:
                return $item->$column_name;
        }
    }

    function get_sortable_columns()
    {
        return [
            'post_title'        => ['post_title', true],
            'date'              => ['date', true],
            'post_type'         => ['post_type', true],
            'clicks'            => ['clicks', true],
        ];
    }

    function extra_tablenav( $which ) {
        if ($which == "top") {
            $post_type = Wpil_Filter::linksPostType();
            $post_type = !empty($post_type) ? $post_type : 0;
            ?>
            <div class="alignright actions bulkactions" id="wpil_clicks_table_filter">
                <select name="click_post_type">
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
 
            if(in_array($column_name, array('gsc', 'yoast', 'rank-math', 'aioseo', 'seopress', 'custom'), true)){
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
}