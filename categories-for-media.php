<?php

/*
  Plugin Name: Categories for media
  Description: Categories for media library. Media items can then be sorted per category.
  Version: 0.2
  Author: agencia 786
  Author URI: 786.pe
 
 * License:       GNU General Public License, v2 (or newer)
 * License URI:  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */
 */

class media_category {

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'register_activation_hook'));
        add_action('init', array($this, 'create_media_categories'), 0);
        add_action('admin_menu', array($this, 'media_category_add_admin'), 0);
        add_filter('attachment_fields_to_edit', array(&$this, 'add_media_category_field'), 22, 2);
        add_filter('attachment_fields_to_save', array(&$this, 'save_media_category_field'), 23, 2);
        add_filter('manage_media_columns', array(&$this, 'add_media_column'));
        add_action('manage_media_custom_column', array(&$this, 'manage_media_column'), 10, 2);
        add_action('restrict_manage_posts', array(&$this, 'restrict_media_by_category'));
        add_filter('posts_where', array(&$this, 'convert_attachment_id_to_taxonomy_term_in_query'));
    }

    public function register_activation_hook() {
        $uri = WP_CONTENT_DIR . str_replace(WP_CONTENT_URL, '', get_bloginfo('template_directory')) . '/taxonomy-media_category.php';
        if (!file_exists($uri)) {
            $fp = fopen($uri, 'a+');
            fwrite($fp, '<?php /* Template for media_category */ ?>');
            fclose($fp);
        }
    }

    public function create_media_categories() {
        register_taxonomy(
                'media_category',
                array('media'),
                array(
                    'hierarchical' => true,
                    'label' => 'Media Categories',
                    'public' => true,
                    'show_ui' => true,
                    'query_var' => 'media_categories',
                    'rewrite' => array('slug' => 'media-categories')
                )
        );

        if (!term_exists('Uncategorized', 'media_category')) {
            wp_insert_term(
                    'Uncategorized',
                    'media_category',
                    array(
                        'description' => '',
                        'slug' => 'uncategorized',
                        'parent' => '0'
                    )
            );
        }
    }

    public function media_category_add_admin() {
        add_submenu_page('upload.php', 'Media Categories', __('Categories'), 10, 'edit-tags.php?taxonomy=media_category&post_type=media');
    }

    public function add_media_category_field($fields, $object) {
        if (!isset($fields['media_library_categories'])) {
            $selected_categories = (array) wp_get_object_terms($object->ID, 'media_category', array('fields' => 'ids'));
            $categories = get_terms('media_category', 'orderby=count&hide_empty=0');
            $html .= '<ul style="width:200px; height:120px; overflow-y:scroll; padding:2px; background-color:#fff; border:1px solid #DFDFDF;">';
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $html .= '<li><label class="selectit"><input ' . (in_array($category->term_id, $selected_categories) ? 'checked' : '') . ' name="attachments[' . $object->ID . '][media-categories][' . $category->term_id . ']" type="checkbox" value="' . $category->term_id . '" > ' . $category->name . '</label></li>';
                }
            }
            $html .= '</ul>';

            $label = __('Category');
            $fields['media_library_categories'] = array(
                'label' => $label,
                'input' => 'html',
                'html' => $html,
                'value' => (!empty($selected_categories)) ? $selected_categories->term_id : '',
                'helps' => ''
            );
        }
        return $fields;
    }

    public function save_media_category_field($post, $attachment) {
        $terms = array();
        if ($attachment && (count($attachment['media-categories']) > 0)) {
            foreach ($attachment['media-categories'] as $termID) {
                $term = get_term($termID, 'media_category');
                array_push($terms, $term->name);
            }
        }
        wp_set_object_terms($post['ID'], $terms, 'media_category', false);
        return $post;
    }

    function add_media_column($posts_columns) {
        $posts_columns['att_cats'] = _x('Categories', 'column name');
        return $posts_columns;
    }

    function manage_media_column($column_name, $id) {
        switch ($column_name) {
            case 'att_cats':
                $tagparent = "upload.php?";
                $categories = (array) wp_get_object_terms($id, 'media_category', array('fields' => 'all'));
                if (!empty($categories)) {
                    $arr_categories;
                    foreach($categories as $category){
                        $arr_categories[] = '<a href="?media_category=' . $category->term_id . '">' . $category->name . '</a>';
                    }
                    echo implode(', ', $arr_categories);
                } else {
                    _e('No Categories');
                }
                break;
            default:
                break;
        }
    }

    function restrict_media_by_category() {
        global $pagenow;
        global $typenow;
        global $wp_query;
        if ($pagenow == 'upload.php') {
            $taxonomy = 'media_category';
            $media_taxonomy = get_taxonomy($taxonomy);
            wp_dropdown_categories(array(
                'show_option_all' => __('View all categories'),
                'taxonomy' => $taxonomy,
                'name' => 'media_category',
                'orderby' => 'name',
                'selected' => $wp_query->query['term'],
                'hierarchical' => true,
                'depth' => 3,
                'show_count' => true,
                'hide_empty' => true
            ));
        }
    }

    function convert_attachment_id_to_taxonomy_term_in_query($where) {
        global $pagenow;
        global $wpdb;
        if ($pagenow == 'upload.php' && intval($_GET['media_category']) > 0) {
            $subquery = "SELECT r.object_id FROM $wpdb->term_relationships r INNER JOIN $wpdb->term_taxonomy tax on tax.term_taxonomy_id = r.term_taxonomy_id WHERE tax.term_id = " . $_GET['media_category'];
            $where .= " AND ID IN ($subquery)";
        }
        return $where;
    }
}

$media_category = new media_category();
?>
