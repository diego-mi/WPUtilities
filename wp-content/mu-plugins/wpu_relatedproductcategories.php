<?php

/*
Plugin Name: WPU Related Product Categories
Description: Add related product categories to Woocommerce
Version: 0.1
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

/* Based on https://gist.github.com/corsonr/9fae7b7a1f45e92a34a3ae6796616ac1 */
class wpuRelatedProductsCategories {

    private $related_list = array();

    public function __construct() {

        // Init
        add_action('init', array(&$this, 'init'));

    }

    public function init() {

        // Display Fields
        add_action('woocommerce_product_options_related', array(&$this, 'display_fields'));

        // Save Fields
        add_action('woocommerce_process_product_meta', array(&$this, 'save_fields'));

        // Build list
        $this->related_list = apply_filters('wpurelatedproductscategories_cats', array());
    }

    /* ----------------------------------------------------------
      Front
    ---------------------------------------------------------- */

    public function get_related_products($id, $post_id = false) {
        global $post;
        if (!$post_id) {
            if (!is_object($post) || !isset($post->ID)) {
                return false;
            }
            $post_id = $post->ID;
        }

        if (!isset($this->related_list[$id])) {
            return false;
        }

        $response = array(
            'infos' => $this->related_list[$id],
            'products' => array()
        );

        $products = get_post_meta($post_id, '_' . $id . '_product_ids', true);

        if (is_array($products)) {
            $response['products'] = $products;
        }

        return $response;

    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    /* Display fields */

    public function display_fields() {
        global $woocommerce, $post;

        echo '<div class="options_group">';
        foreach ($this->related_list as $id => $field) {
            echo '<p class="form-field">';
            if (isset($field['label']) && !empty($field['label'])) {
                echo '<label for="more_recent_product_ids">' . $field['label'] . '</label>';
            }
            echo $this->get_field($post, $id);
            if (isset($field['help']) && !empty($field['help'])) {
                echo wc_help_tip($field['help']);
            }
            echo '</p>';
        }
        echo '</div>';

    }

    private function get_field($post, $id) {
        if (!isset($this->related_list[$id])) {
            return '';
        }

        $field = $this->related_list[$id];

        $product_ids = array_filter(array_map('absint', (array) get_post_meta($post->ID, '_' . $id . '_product_ids', true)));
        $json_ids = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (is_object($product)) {
                $json_ids[$product_id] = wp_kses_post(html_entity_decode($product->get_formatted_name(), ENT_QUOTES, get_bloginfo('charset')));
            }
        }

        $html = '';
        $html .= 'id="' . $id . '_product_ids" ';
        $html .= 'name="' . $id . '_product_ids" ';
        $html .= 'data-action="woocommerce_json_search_products" ';
        $html .= 'data-multiple="true" ';
        $html .= 'value="' . implode(',', array_keys($json_ids)) . '"';
        if (isset($field['placeholder']) && !empty($field['placeholder'])) {
            $html .= 'data-placeholder="' . esc_attr($field['placeholder']) . '" ';
        }
        $html .= 'data-exclude="' . intval($post->ID) . '" ';
        $html .= 'data-selected="' . esc_attr(json_encode($json_ids)) . '" ';

        return '<input type="hidden" class="wc-product-search" style="width: 50%;" ' . $html . ' /> ';
    }

    /* Save fields */

    public function save_fields($post_id) {
        foreach ($this->related_list as $id => $field) {
            $field_id = $id . '_product_ids';
            $product_ids = isset($_POST[$field_id]) ? array_filter(array_map('intval', explode(',', $_POST[$field_id]))) : array();
            update_post_meta($post_id, '_' . $field_id, $product_ids);
        }
    }

}

$wpuRelatedProductsCategories = new wpuRelatedProductsCategories();

/*

// How to add categories
add_filter('wpurelatedproductscategories_cats', 'test_wpurelatedproductscategories_cats', 10, 1);
function test_wpurelatedproductscategories_cats($categories) {
    $categories['more_recent'] = array(
        'label' => __('Better products'),
        'placeholder' => __('Search for a product...'),
        'help' => __('Choose a better version of this product.')
    );
    return $categories;
}

// How to load
global $wpuRelatedProductsCategories;
var_dump($wpuRelatedProductsCategories->get_related_products('more_recent', 21));

// Content
array(2) {
  ["infos"]=>
  array(3) {
    ["label"]=>
    string(15) "Better products"
    ["placeholder"]=>
    string(23) "Search for a product..."
    ["help"]=>
    string(40) "Choose a better version of this product."
  }
  ["products"]=>
  array(3) {
    [0]=>
    int(123)
    [1]=>
    int(124)
    [2]=>
    int(125)
  }
}
*/
