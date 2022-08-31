<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!class_exists('MXWC_Shipping_Options')) {
    class MXWC_Shipping_Options extends WC_Shipping_Method
    {

        public function __construct()
        {
            $this->id = 'mx_local_shipping';
            $this->method_title = __('click & collect', 'mx-wc-local-pickup');
            $this->title = __('click & collect', 'mx-wc-local-pickup');
            $this->options_array_label = 'mx_shipping_options';
            $this->method_description = __('click & collect with user selectable options', 'mx-wc-local-pickup');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_shipping_options'));
            $this->init();
        }

        /*
     * Init settings
     */
        function init()
        {
            // Load the settings API
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option('title');
            $this->type         = $this->get_option('type');

            $this->get_shipping_options();

            add_filter('woocommerce_shipping_methods', array(&$this, 'add_mx_shipping_methods'));
            add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'mx_review_order_shipping_options'));
            add_action('woocommerce_review_order_after_shipping', array(&$this, 'mx_review_order_shipping_options'));
            add_action('woocommerce_checkout_update_order_meta', array(&$this, 'mx_field_update_shipping_order_meta'), 10, 2);

            if (!is_admin()) {
                add_action('woocommerce_cart_shipping_method_full_label', array(&$this, 'negative_pickup_label'), 10, 2);
            }

            if (is_admin()) {
                add_action('woocommerce_admin_order_data_after_shipping_address', array(&$this, 'mx_display_shipping_admin_order_meta'), 10, 2);
            }
        }


        /*
     * init_form_fields function
     */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Local Pickup Extended ', 'mx-wc-local-pickup'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'mx-wc-local-pickup'),
                    'default' => __('Local Pickup Extended', 'mx-wc-local-pickup'),
                    'desc_tip' => true,
                ),

                'shipping_options_table' => array(
                    'type' => 'shipping_options_table'
                ),

            );
        }

        /*
     * admin_options function
     */
        function admin_options()
        { ?>
            <h3><?php echo $this->method_title; ?></h3>
            <p><?php _e('Le click & collect est un service permettant aux consommateurs de commander en ligne pour ensuite retirer leur article dans un magasin de proximité', 'mx-wc-local-pickup'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
        <?php }

        /*
     * is_available function
     */
        function is_available($package)
        {

            if ($this->enabled == "no")
                return false;

            // If post codes are listed, use them
            $codes = '';
            if ($this->codes != '') {
                foreach (explode(',', $this->codes) as $code) {
                    $codes[] = $this->clean($code);
                }
            }

            if (is_array($codes)) {

                $found_match = false;

                if (in_array($this->clean($package['destination']['postcode']), $codes)) {
                    $found_match = true;
                }

                // Pattern match
                if (!$found_match) {

                    $customer_postcode = $this->clean($package['destination']['postcode']);
                    foreach ($codes as $c) {
                        $pattern = '/^' . str_replace('_', '[0-9a-zA-Z]', $c) . '$/i';
                        if (preg_match($pattern, $customer_postcode)) {
                            $found_match = true;
                            break;
                        }
                    }
                }


                // Wildcard search
                if (!$found_match) {

                    $customer_postcode = $this->clean($package['destination']['postcode']);
                    $customer_postcode_length = strlen($customer_postcode);

                    for ($i = 0; $i <= $customer_postcode_length; $i++) {

                        if (in_array($customer_postcode, $codes)) {
                            $found_match = true;
                        }

                        $customer_postcode = substr($customer_postcode, 0, -2) . '*';
                    }
                }

                if (!$found_match) {
                    return false;
                }
            }

            if ($this->availability == 'specific') {
                $ship_to_countries = $this->countries;
            } else {
                $ship_to_countries = array_keys(WC()->countries->get_shipping_countries());
            }

            if (is_array($ship_to_countries)) {
                if (!in_array($package['destination']['country'], $ship_to_countries)) {
                    return false;
                }
            }

            // Gr8, we passed! Let's proceed
            return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true, $package);
        }

        /*
     * clean function
     */
        function clean($code)
        {
            return str_replace('-', '', sanitize_title($code)) . (strstr($code, '*') ? '*' : '');
        }

        /*
     * validate_shipping_options_table_field function
     */
        function validate_shipping_options_table_field($key)
        {
            return false;
        }

        /*
     * generate_options_table_html function
     */
        function generate_shipping_options_table_html()
        {
            ob_start(); ?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php _e('Pickup Options', 'mx-wc-local-pickup'); ?>:</th>
                <td class="forminp" id="<?php echo $this->id; ?>_options">
                    <table class="shippingrows widefat" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox"></th>
                                <th class="options-th">
                                <td><?php _e('Boutiques', 'mx-wc-local-pickup'); ?></td>
                                <td><?php _e('Descriptions', 'mx-wc-local-pickup'); ?></td>
                                </th>
                            </tr>

                        </thead>

                        <tbody>
                            <?php

                            if ($this->shipping_options) :
                                for ($i = 0; $i < count($this->shipping_options["shop_name"]); $i++) { ?>
                                    <tr class="option-tr">
                                        <th class="check-column"><input type="checkbox" name="select" /></th>
                                        <th class="options-th">
                                        <td>

                                            <input type="text" name="<?php echo esc_attr($this->id . '_options[' . $i . ']') ?>" value="<?php echo $this->shipping_options["shop_name"][$i]; ?>">
                                        </td>
                                        <td>
                                            <textarea name="<?php echo esc_attr($this->id . '_options_descriptions[' . $i . ']') ?>" value="<?php echo $this->shipping_options["description"][$i]; ?>" rows="5" cols="33"><?php echo $this->shipping_options["description"][$i]; ?></textarea>
                                        </td>

                                        </th>
                                    </tr>

                            <?php }
                            // var_dump($this->shipping_options);
                            endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4"><a href="#" class="add button"><?php _e('Add Option', 'mx-wc-local-pickup'); ?></a> <a href="#" class="remove button"><?php _e('Delete selected options', 'mx-wc-local-pickup'); ?></a></th>
                            </tr>
                        </tfoot>
                    </table>

                </td>
            </tr>
<?php
            return ob_get_clean();
        }


        /*
     * process_shipping_options function.
     */
        function process_shipping_options()
        {

            /* Saving the data from the form to the database. */
            $options = array();

            if (isset($_POST[$this->id . '_options']))
                $options = ["description" => array_map('wc_clean', $_POST[$this->id . '_options_descriptions']), "shop_name" => array_map('wc_clean', $_POST[$this->id . '_options'])];
            update_option($this->options_array_label, $options);

            $this->get_shipping_options();
        }

        /*
     * get_shipping_options function.
     */
        function get_shipping_options()
        {
            $this->shipping_options = array_filter((array) get_option($this->options_array_label));
        }

        function mx_review_order_shipping_options()
        {
            global $woocommerce;
            $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
            if (is_array($chosen_method) && in_array($this->id, $chosen_method)) {
                echo '<tr class="shipping_option">';
                echo '<th> ' . __('Boutique', 'mx-wc-local-pickup') . ': </th>';
                echo '<td><select style="max-width:150px;" name="shipping_option" class="input-select" id="shipping_option" required="required">';
                echo '<option value=""> ' . __('Selectionnez une boutique', 'mx-wc-local-pickup') . ' </option>';
                $description = "";
                for ($i = 0; $i < count($this->shipping_options['shop_name']); $i++) {
                    echo '<option  data-description= "' . $this->shipping_options['description'][$i] . '" value="' . esc_attr($this->shipping_options['shop_name'][$i]) . '" ' . selected($woocommerce->session->_chosen_shipping_option, esc_attr($this->shipping_options['shop_name'][$i])) . '>' . $this->shipping_options['shop_name'][$i] . '</option>';
                    $description = $this->shipping_options['description'][$i];
                }
                echo '</select><p class="info-description">' . $description . '</p></td></tr>';
            }
        }

        /**
         * If the shipping method is the one I'm using, and the shipping option is set, then update
         * the order meta with the shipping option.
         * 
         * @param order_id The order ID
         * @param posted The posted data from the checkout form
         */
        function mx_field_update_shipping_order_meta($order_id, $posted)
        {
            global $woocommerce;
            if (is_array($posted['shipping_method']) && in_array($this->id, $posted['shipping_method'])) {
                if (isset($_POST['shipping_option']) && !empty($_POST['shipping_option'])) {
                    update_post_meta($order_id, 'mx_shipping_option', sanitize_text_field($_POST['shipping_option']));
                    $woocommerce->session->_chosen_shipping_option = sanitize_text_field($_POST['shipping_option']);
                }
            } else {
                $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
                $chosen_option = $woocommerce->session->_chosen_shipping_option;
                if (is_array($chosen_method) && in_array($this->id, $chosen_method) && $chosen_option) {
                    update_post_meta($order_id, 'mx_shipping_option', $woocommerce->session->_chosen_shipping_option);
                }
            }
        }

        /**
         * It displays the shipping option selected by the customer on the admin order page.
         * 
         * @param order The order object
         */
        function mx_display_shipping_admin_order_meta($order)
        {
            $selected_option = get_post_meta($order->id, 'mx_shipping_option', true);
            if ($selected_option) {
                echo '<p><strong>' . $this->title . ':</strong> ' . get_post_meta($order->id, 'mx_shipping_option', true) . '</p>';
            }
        }

        /**
         * It adds the shipping method to the list of available shipping methods.
         * 
         * @param methods The array of shipping methods that are currently available.
         * 
         * @return The array of shipping methods.
         */
        function add_mx_shipping_methods($methods)
        {
            $methods[] = $this;
            return $methods;
        }

        function negative_pickup_label($label, $method)
        {
            if (!is_admin() && $method->id == 'mx_local_shipping') {
                $label = $method->label . ' : ' . wc_price($method->cost);
            }
            return $label;
        }
    }

    new MXWC_Shipping_Options();
}
