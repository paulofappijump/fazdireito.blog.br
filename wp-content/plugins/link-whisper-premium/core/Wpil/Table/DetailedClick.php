<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_DetailedClick
 * This is the table that's displayed in the Detailed Clicks page, not the Clicks Report.
 */
class Wpil_Table_DetailedClick extends WP_List_Table
{
    function get_columns()
    {
        $screen_options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_date = true; //(!empty($screen_options['show_date']) && $screen_options['show_date'] == 'off') ? false : true; // todo make into real setting if needed
        $show_click_traffic = (!empty($screen_options['show_click_traffic']) && $screen_options['show_click_traffic'] !== 'off') ? true : false;
        $show_click_location = !empty(get_option('wpil_track_all_element_clicks', 0));
        $options = array();

        if(isset($_GET['post_type']) && $_GET['post_type'] === 'url'){
            $options['post_id'] = __('Post', 'wpil');
        }else{
            $options['link_url'] = __('Link URL', 'wpil');
        }

        $options['link_anchor'] = __('Link Anchor', 'wpil');

        if($show_click_location){
            $options['show_click_location'] = __('Link Location', 'wpil');
        }

        if($show_date && $show_click_traffic){
            $options['click_date'] = __('Click Date', 'wpil');
        }

        if($show_click_traffic && empty(get_option('wpil_disable_click_tracking_info_gathering', false))){
            $options['user_ip'] = __('User IP', 'wpil');
        }

        if(!$show_click_traffic){
            $options['total_clicks'] = __('Total Clicks', 'wpil');
        }

        $options['delete_click_data'] = '';

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

        $type = (isset($_GET['post_type']) && ($_GET['post_type'] === 'post' || $_GET['post_type'] === 'term' || $_GET['post_type'] === 'url' || $_GET['post_type'] === 'user_ip')) ? $_GET['post_type']: false;
        $id = false;

        // sanitize the post id
        if(isset($_GET['post_id'])){
            switch ($type) {
                case 'url':
                    $id = esc_url_raw($_GET['post_id']);
                    break;
                case 'post':
                case 'term':
                    $id = intval($_GET['post_id']);
                    break;
                case 'user_ip':
                    $id = filter_var($_GET['post_id'], FILTER_VALIDATE_IP);
                    break;
            }
        }

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $start_date = strtotime('30 days ago');
        if(isset($_GET['start_date']) && !empty($_GET['start_date'])){
            $start_string = preg_replace('/([^0-9-TZ:\/])/', '', $_GET['start_date']);

            if(!empty(DateTime::createFromFormat('Y-m-d', $start_string))){
                $date = new DateTime($start_string);
                $start_date = $date->getTimestamp();
            }
        }

        $end_date = strtotime('now');
        if(isset($_GET['end_date']) && !empty($_GET['end_date'])){
            $end_string = preg_replace('/([^0-9-TZ:\/])/', '', $_GET['end_date']);

            if(!empty(DateTime::createFromFormat('Y-m-d', $end_string))){
                $date = new DateTime($end_string);
                $end_date = $date->getTimestamp() + (DAY_IN_SECONDS - 5);
            }
        }

        $data = Wpil_ClickTracker::get_detailed_click_table_data($id, $type, $page, $orderby, $order, array('start' => $start_date, 'end' => $end_date));
        $this->items = $data['data'];

        $this->set_pagination_args(array(
            'total_items' => $data['total_items'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total_items'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'link_url':
                return '<a href="' . esc_url(admin_url("admin.php?post_id=" . urlencode($item->link_url)) . "&post_type=url&page=link_whisper&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1')) . '">' . esc_html($item->link_url) . '</a>';
            case 'post_id':
                $post = new Wpil_Model_Post($item->post_id, $item->post_type);
                return '<a href="' . esc_url(admin_url("admin.php?post_id={$post->id}&post_type={$post->type}&page=link_whisper&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1'))) . '">' . esc_html($post->getTitle()) . '</a>';
            case 'delete_click_data':
                return '<i data-click_id="'. ((isset($item->click_id)) ? $item->click_id: 0) .'" data-post_id="'.$item->post_id.'" data-post_type="'.$item->post_type.'" data-anchor="'. esc_attr(((isset($item->link_anchor)) ? $item->link_anchor: '')) .'" data-url="'.base64_encode($item->link_url).'" data-nonce="'. wp_create_nonce(wp_get_current_user()->ID . 'delete_click_data') .'" class="wpil_delete_click_data dashicons dashicons-no-alt"></i>';
            case 'user_ip':
                return '<a href="' . esc_url(admin_url("admin.php?post_id=" . urlencode($item->user_ip) . "&post_type=user_ip&page=link_whisper&type=click_details_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI'] . '&direct_return=1'))) . '">' . esc_html($item->user_ip) . '</a>';
            case 'show_click_location':
                return $item->link_location;
            default:
                return $item->$column_name;
        }
    }

    function get_sortable_columns()
    {

        if(isset($_GET['post_type']) && $_GET['post_type'] === 'url'){
            $options['post_id'] = ['post_id', true];
        }else{
            $options['link_url'] = ['link_url', true];
        }

        $options['link_anchor'] = ['link_anchor', true];
        $options['click_date']  = ['click_date', true];
        if(empty(get_option('wpil_disable_click_tracking_info_gathering', false))){
            $options['user_ip']     = ['user_ip', true];
        }
        $options['total_clicks'] = ['total_clicks', true];

        return $options;
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

    /**
     * Remove the search box from the click table.
     **/
    public function search_box( $text, $input_id ) {
        if(!isset($_GET['post_type']) || $_GET['post_type'] !== 'user_ip'){
            return;
        }

        $ip_address =  (isset($_GET['post_id'])) ? filter_var($_GET['post_id'], FILTER_VALIDATE_IP): false;
        if(empty($ip_address)){
            return;
        }

        ?>
        <div class="erase-user-ip-data-wrapper">
            <div class="erase-user-ip-data-container">
                <button type="button" class="button-primary erase-user-ip-data" data-user-ip="<?php echo esc_attr($ip_address); ?>" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'delete_click_ip_data'); ?>"><?php _e('Erase IP Data', 'wpil'); ?></button>
                <input type="hidden" id="erase-user-ip-data-confirm-text-1" value="<?php esc_attr_e(sprintf(__('Please confirm that you want to remove the references to the IP address %s', 'wpil'), $ip_address)); ?>">
                <input type="hidden" id="erase-user-ip-data-confirm-text-2" value="<?php esc_attr_e(__('This will not delete the clicks, but it will make the clicks anonymous by removing personally identifiable information from the clicks.', 'wpil')); ?>">
                <input type="hidden" id="erase-user-ip-data-confirm-text-3" value="<?php echo (empty(get_option('wpil_disable_click_tracking_info_gathering', false))) ? esc_attr(__('This will not prevent tracking of this IP address in the future.', 'wpil')) : ''; ?>">
                <input type="hidden" id="erase-user-ip-data-confirm-text-4" value="<?php echo (empty(get_option('wpil_disable_click_tracking_info_gathering', false))) ? esc_attr(__('To disable IP address tracking, please go to the Link Whisper settings and activate the "Don\'t Collect User-Identifying Information with Click Tracking" option.', 'wpil')) : ''; ?>">
                <div class="wpil_help" style="float:right;">
                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                    <div style="margin: 30px 0 0 -190px;">
                        <?php _e("Clicking this button will tell Link Whisper to erase the IP address data associated with these clicks", 'wpil'); ?>
                        <br>
                        <br>
                        <?php _e("This will not delete the clicks, but will make the clicks anonymous.", 'wpil'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return;
    }
}