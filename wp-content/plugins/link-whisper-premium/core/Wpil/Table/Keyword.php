<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Domain
 */
class Wpil_Table_Keyword extends WP_List_Table
{
    function get_columns()
    {
        $cols = array(
            'keyword' => 'Keyword',
            'link' => 'Link',
        );

        $options = get_user_meta(get_current_user_id(), 'wpil_keyword_options', true);
        $select_links_active = Wpil_Keyword::keywordLinkSelectActive();

        if($select_links_active && (empty($options) || isset($options['hide_select_links_column']) && $options['hide_select_links_column'] === 'off')){
            $cols['select_links'] = 'Possible Links';
        }

        $cols['links'] = 'Links Added';
        $cols['actions'] = '';

        return $cols;
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'wpil_keyword_options', true);

        if(!empty($options) && isset($options['per_page'])){
            $per_page = intval($options['per_page']);
            if(empty($per_page)){
                $per_page = 20;
            }
        }else{
            $per_page = 20;
        }

        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Keyword::getData($per_page, $page, $search, $orderby, $order);
        $this->items = $data['keywords'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'keyword':
                $terms = Wpil_Term::getAllCategoryTerms();
                $term_selector = '';
                if(!empty($terms)){
                    // get any restricted terms
                    $restricted_cats = explode(',', $item->restricted_cats);
                    // build the tax cache
                    $tax_cache = array();
                    foreach($terms as $term){
                        if(!isset($tax_cache[$term->taxonomy])){
                            $tax_cache[$term->taxonomy] = get_taxonomy($term->taxonomy);
                        }
                    }

                    // build the term options
                    $cat_options = '';
                    $tag_options = '';
                    foreach($terms as $term){
                        if($tax_cache[$term->taxonomy]->hierarchical){
                            $cat_options .= '<li>
                                    <input type="hidden" name="wpil_keywords_restrict_term_' . $term->term_id . '" value="0" />
                                    <input type="checkbox" class="wpil-restrict-keywords-input" name="wpil_keywords_restrict_term_' . $term->term_id . '" ' . (in_array($term->term_id, $restricted_cats)?'checked':'') . ' data-term-id="' . $term->term_id . '">' . esc_html($term->name) . '</li>';
                        }else{
                            $tag_options .= '<li>
                                    <input type="hidden" name="wpil_keywords_restrict_term_' . $term->term_id . '" value="0" />
                                    <input type="checkbox" class="wpil-restrict-keywords-input" name="wpil_keywords_restrict_term_' . $term->term_id . '" ' . (in_array($term->term_id, $restricted_cats)?'checked':'') . ' data-term-id="' . $term->term_id . '">' . esc_html($term->name) . '</li>';
                            }
                    }

                    $term_selector ='
                    <div class="wpil_keywords_restrict_to_cats_container">
                        <input type="hidden" name="wpil_keywords_restrict_to_cats" value="0" />
                        <input type="checkbox" class="wpil_keywords_restrict_to_cats" name="wpil_keywords_restrict_to_cats" ' . (!empty($item->restrict_cats)?'checked':'') . ' value="1" />
                        <label for="wpil_keywords_restrict_to_cats">' . __('Restrict autolinks to specific categories or tags?', 'wpil') . '</label>
                        <span class="wpil-keywords-restrict-cats-show"></span>
                    </div>';

                    $term_selector .= '<ul class="wpil-keywords-restrict-cats" style="display:none;">';
                    $term_selector .= '<li>' . __('Available Categories:', 'wpil') . '</li>';
                    $term_selector .= $cat_options;
                    $term_selector .= '</ul>';
                    $term_selector .= '<br />';

                    $term_selector .= '<ul class="wpil-keywords-restrict-cats" style="display:none;">';
                    $term_selector .= '<li>' . __('Available Tags:', 'wpil') . '</li>';
                    $term_selector .= $tag_options;
                    $term_selector .= '</ul>';
                    $term_selector .= '<br />';
                }

                $date_restricted = (!empty($item->restrict_date) && !empty($item->restricted_date));

                return '<div>' . stripslashes($item->$column_name) . '<i class="dashicons dashicons-admin-generic"></i></div>
                        <div class="local_settings">
                            <div class="block" data-id="' . $item->id . '">
                                <input type="hidden" name="wpil_keywords_add_same_link" value="0" />
                                <input type="checkbox" name="wpil_keywords_add_same_link" ' . ($item->add_same_link==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_add_same_link">' . __('Add link if post already has this link?', 'wpil') . '</label>
                                <br>
                                <input type="hidden" name="wpil_keywords_link_once" value="0" />
                                <input type="checkbox" name="wpil_keywords_link_once" ' . ($item->link_once==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_link_once">' . __('Only link once per post', 'wpil') . '</label>
                                <br>
                                <input type="hidden" name="wpil_keywords_force_insert" value="0" />
                                <input type="checkbox" name="wpil_keywords_force_insert" ' . ($item->force_insert==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_force_insert">' . __('Override "One Link per Sentence" rule?', 'wpil') . '</label>
                                <div class="wpil_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div>' . __('By default, Link Whisper only inserts one link per sentence. If a sentence already has a link, Link Whisper won\'t add another one to it. This option allows you to override the rule so autolinks can be inserted in sentences that already have links.', 'wpil') . '</div>
                                </div>
                                <br>
                                <input type="hidden" name="wpil_keywords_select_links" value="0" />
                                <input type="checkbox" name="wpil_keywords_select_links" ' . ($item->select_links==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_select_links">Select links before inserting?</label>
                                <br>
                                <input type="hidden" name="wpil_keywords_set_priority" value="0" />
                                <input type="checkbox" name="wpil_keywords_set_priority" class="wpil_keywords_set_priority_checkbox" ' . ($item->set_priority==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_set_priority">' . __('Set priority for auto link insertion?', 'wpil') . '</label>
                                <div class="wpil_help" style="display: inline-block; float:none; height: 5px;">
                                    <i class="dashicons dashicons-editor-help" style="font-size: 20px; color: #444; margin: 2px 0 8px;"></i>
                                    <div>' . __('Setting a priority for the auto link will tell Link Whisper which link to insert if it comes across a sentence that has keywords that match multiple auto links. The auto link with the highest priority will be the one inserted in such a case.', 'wpil') . '</div>
                                </div>
                                <div class="wpil_keywords_priority_setting_container" style="' . ($item->set_priority==1?'display:block;':''). '">
                                    <input type="number" style="max-width: 60px;" name="wpil_keywords_priority_setting" min="0" value="'. ((isset($item->priority_setting) && !empty($item->priority_setting)) ? $item->priority_setting : 0) .'" step="1"/>
                                </div>
                                <br>
                                <input type="hidden" name="wpil_keywords_restrict_date" value="0" />
                                <input type="checkbox" id="wpil_keywords_restrict_date_' . $item->id . '" name="wpil_keywords_restrict_date" class="wpil_keywords_restrict_date_checkbox" ' . ($item->restrict_date==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_restrict_date_' . $item->id . '">' . __('Only add links to posts published after the given date', 'wpil') . '</label>
                                <div class="wpil_keywords_restricted_date-container" ' . (($date_restricted) ? 'style="display:block;"' : ''). '>
                                    <input type="date" name="wpil_keywords_restricted_date" ' . ((!empty($item->restricted_date)) ? 'value="' . str_replace(' 00:00:00', '', $item->restricted_date) . '"': '') . '/>
                                </div>
                                <br>
                                <input type="hidden" name="wpil_keywords_case_sensitive" value="0" />
                                <input type="checkbox" id="wpil_keywords_case_sensitive_' . $item->id . '" name="wpil_keywords_case_sensitive" class="wpil_keywords_case_sensitive_checkbox" ' . ($item->case_sensitive==1?'checked':'') . ' value="1" />
                                <label for="wpil_keywords_case_sensitive_' . $item->id . '">' . __('Make Keyword search case sensitive', 'wpil') . '</label>
                                <br>
                                ' . $term_selector . '
                                <a href="javascript:void(0)" class="button-primary wpil_keyword_local_settings_save" data-id="' . $item->id . '">Save</a>
                            </div>
                            <div class="progress_panel loader">
                                <div class="progress_count"></div>
                            </div>
                        </div>';
            case 'link':
                return esc_html($item->$column_name);
            case 'select_links':
                $possible_links = Wpil_Keyword::getPossibleLinksByKeyword($item->id);
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_select_keywords.php';
                return ob_get_clean();
            case 'links':
                $links = $item->$column_name;
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/blocks/collapsible_posts.php';
                return ob_get_clean();
            case 'actions':
                return '<a href="javascript:void(0)" class="delete" data-id="' . $item->id . '" title="Autolink ID: ' . $item->id . '">Delete</a>';
            default:
                return print_r($item, true);
        }
    }

    function get_sortable_columns()
    {
        return [
            'keyword' => ['keyword', false],
            'link' => ['link', false],
            'links' => ['links', false],
        ];
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
 
            if(in_array($column_name, array('select_links', 'links'), true)){
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