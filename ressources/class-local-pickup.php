<?php

/** 
 * Plugin Name : Click & collect
 * Plugin URI: COMMING
 *  Text Domain : COMMING
 *  Version :
 *  Author : Messie MOUK
 **/
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!class_exists('MXWC_Shipping_Options')) {

    load_plugin_textdomain('mx-wc-local-pickup', false, dirname(plugin_basename(__FILE__)) . '/languages');

    function mx_shipping_methods_init()
    {
    }

    /*
      * display pickup location meta on the Order details page
     */

    add_action('woocommerce_order_details_after_order_table', 'mx_shipping_display_custom_order_meta', 10, 1);

    function mx_shipping_display_custom_order_meta($order)
    {
        $selected_option = get_post_meta($order->id, 'mx_shipping_option', true);
        if ($selected_option) {
            echo '<p><strong>' . __('Pickup Location', 'mx-wc-local-pickup') . ':</strong> ' . get_post_meta($order->id, 'mx_shipping_option', true) . '</p>';
        }
    }

    /*
     * add pickup location meta to order emails
     */

    add_filter('woocommerce_email_order_meta_keys', 'mx_shipping_option_meta_keys');

    function mx_shipping_option_meta_keys($keys)
    {
        if (isset($_POST['shipping_option']) && !empty($_POST['shipping_option'])) {
            echo '<h2>' . __('Pickup Location', 'mx-wc-local-pickup') . ':</h2>';

            $keys[''] = 'mx_shipping_option';
            return $keys;
        }
    }

    add_action('woocommerce_shipping_init', 'mx_shipping_methods_init');
    add_action('wp_ajax_mx_save_selected', 'mx_save_selected');
    add_action('wp_ajax_nopriv_mx_save_selected', 'mx_save_selected');
    function mx_save_selected()
    {
        if (isset($_GET['shipping_option']) && !empty($_GET['shipping_option'])) {
            global $woocommerce;
            $selected_option = $_GET['shipping_option'];
            $woocommerce->session->_chosen_shipping_option = sanitize_text_field($selected_option);
        }
        die();
    }
}
