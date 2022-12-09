<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_URLChanger
 */
class Wpil_Table_URLChanger extends WP_List_Table
{
    function get_columns()
    {
        return [
            'old' => 'Old URL',
            'new' => 'New URL',
            'links' => 'Links Changed',
            'actions' => '',
        ];
    }

    function prepare_items()
    {
        $per_page = 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_URLChanger::getData($per_page, $page, $search, $orderby, $order);
        $this->items = $data['urls'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'links':
                $links = $item->links;
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_posts.php';
                return ob_get_clean();
            case 'actions':
                return '<a href="javascript:void(0)" class="delete" data-id="' . $item->id . '">Undo Changes</a>';
            default:
                return $item->$column_name;
        }
    }

    function get_sortable_columns()
    {
        return [
            'old' => ['old', false],
            'new' => ['new', false],
        ];
    }
}