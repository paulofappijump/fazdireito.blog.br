<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Domain
 */
class Wpil_Table_Domain extends WP_List_Table
{
    function get_columns()
    {
        return [
            'host' => 'Domain',
            'posts' => 'Posts',
            'links' => 'Links',
        ];
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $search_type = !empty($_GET['domain_search_type']) ? $_GET['domain_search_type'] : 'domain';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Dashboard::getDomainsData($per_page, $page, $search, $search_type);
        $this->items = $data['domains'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'host':
                return '<a href="'.$item['protocol'] . $item[$column_name].'" target="_blank">'. $item['protocol'] . $item[$column_name].'</a>';
            case 'posts':
                $posts = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($posts as $post) {
                    $list .= '<li>'
                                . esc_html($post->getTitle()) . '<br>
                                <a href="' . admin_url('post.php?post=' . (int)$post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                <a href="' . esc_url($post->getLinks()->view) . '" target="_blank">[view]</a><br><br>
                              </li>';
                }
                $list .= '</ul>';

                return '<div class="wpil-collapsible-wrapper">
  			                <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">'.count($posts).'</div>
  				            <div class="wpil-content">'.$list.'</div>
  				        </div>';
            case 'links':
                $links = $item[$column_name];

                $list = '<ul class="report_links">';
                foreach ($links as $link) {
                    $list .= '<li>
                                <i data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr(base64_encode($link->anchor)) . '" data-url="'.base64_encode($link->url).'" class="wpil_link_delete dashicons dashicons-no-alt"></i>
                                <div>
                                    <a href="' . esc_url($link->url) . '" target="_blank">' . esc_html($link->url) . '</a>
                                    <br>
                                    <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank"><b>[' . esc_html($link->anchor) . ']</b></a>
                                    <br>
                                    <a href="#" class="wpil_edit_link" target="_blank">[' . __('Edit URL', 'wpil') . ']</a>
                                    <div class="wpil-domains-report-url-edit-wrapper">
                                        <input class="wpil-domains-report-url-edit" type="text" value="' . esc_attr($link->url) . '">
                                        <button class="wpil-domains-report-url-edit-confirm wpil-domains-edit-link-btn" data-link_id="' . $link->link_id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr($link->anchor) . '" data-url="'.esc_url($link->url).'" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->link_id) . '">
                                            <i class="dashicons dashicons-yes"></i>
                                        </button>
                                        <button class="wpil-domains-report-url-edit-cancel wpil-domains-edit-link-btn">
                                            <i class="dashicons dashicons-no"></i>
                                        </button>
                                    </div>
                                </div>
                            </li>';
                }
                $list .= '</ul>';

                return '<div class="wpil-collapsible-wrapper">
  			                <div class="wpil-collapsible wpil-collapsible-static wpil-links-count">'.count($links).'</div>
  				            <div class="wpil-content">'.$list.'</div>
  				        </div>';
            default:
                return print_r($item, true);
        }
    }

    function extra_tablenav( $which ) {
        if ($which == "bottom") {
            ?>
            <div class="alignright actions bulkactions detailed_export">
                <a href="javascript:void(0)" class="button-primary csv_button" data-type="domains" id="wpil_cvs_export_button" data-file-name="<?php esc_attr_e('detailed-domain-export.csv', 'wpil'); ?>">Detailed Export to CSV</a>
            </div>
            <?php
        }
    }

    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if(!empty($_REQUEST['orderby'])){
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if(!empty($_REQUEST['order'])){
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if(!empty($_REQUEST['post_mime_type'])){
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($_REQUEST['post_mime_type']) . '" />';
        }
        if(!empty($_REQUEST['detached'])){
            echo '<input type="hidden" name="detached" value="' . esc_attr($_REQUEST['detached']) . '" />';
        }

        $search_type = isset($_REQUEST['domain_search_type']) && !empty($_REQUEST['domain_search_type']) ? $_REQUEST['domain_search_type']: 'domain';
        ?>
<p class="search-box">
    <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
    <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
        <?php submit_button($text, '', '', false, array('id' => 'search-submit')); ?>
    <br />
    <span style="display: inline-block; float: left;">
        <label class="" for="wpil-domain-search-host">Domain</label>
    	<input type="radio" id="wpil-domain-search-host" name="domain_search_type" value="domain" <?php checked($search_type, 'domain');?>>
        <label class="" for="wpil-domain-search-path">Links</label>
    	<input type="radio" id="wpil-domain-search-path" name="domain_search_type" value="links" <?php checked($search_type, 'links');?>>
    </span>
</p>
        <?php
    }
}
