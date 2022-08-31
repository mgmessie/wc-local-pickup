<?php
/*
*Plugin Name: Click & collect
*Plugin URI: https://github.com/mgmessie/wc-local-pickup
*Version: 1.0
*Author: Messie MOUKIMOU 
*Author URI: https://github.com/mgmessie/
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!class_exists('MXWC_Shipping_Options')) {

    load_plugin_textdomain('mx-wc-local-pickup', false, dirname(plugin_basename(__FILE__)) . '/languages');

    function mx_shipping_methods_init()
    {

        class MXWC_Shipping_Options extends WC_Shipping_Method
        {

            /**
             * It's a constructor function that sets the id, title, description, and method title for the
             * shipping method. It also adds two actions to the
             * woocommerce_update_options_shipping_mx_local_shipping hook
             */
            public function __construct()
            {
                $this->id = 'mx_local_shipping';
                $this->method_title = __('click & collect', 'mx-wc-local-pickup');
                $this->title = __('click & collect', 'mx-wc-local-pickup');
                $this->options_array_label = 'mx_shipping_options';
                $this->method_description = __('click & collect avec sélection de point de retrait par l\'utilisateur', 'mx-wc-local-pickup');
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_shipping_options'));
                $this->init();
            }


            /**
             * -------------------------
             * | INIT SETTINGS
             * -------------------------
             *  initializes the shipping method
             */
            function init()
            {
                // Load the settings API
                $this->init_form_fields();
                $this->init_settings();

                /**
                 * Define user set variables
                 */
                /* Setting the title of the page to the value of the title option. */
                $this->title        = $this->get_option('title');
                /* Getting the value of the option 'type' from the database. */
                $this->type         = $this->get_option('type');
                /* Getting the fee from the options table. */
                $this->fee          = $this->get_option('fee');
                /* Getting the value of the type option. */
                $this->type         = $this->get_option('type');
                /* Getting the codes from the database. */
                $this->codes        = $this->get_option('codes');
                /* Getting the availability option from the database. */
                $this->availability = $this->get_option('availability');
                /* Getting the countries from the database. */
                $this->countries    = $this->get_option('countries');



                /* Getting the shipping options from the database. */
                $this->get_shipping_options();

                /* Adding the shipping method to the shipping methods array. */
                add_filter('woocommerce_shipping_methods', array(&$this, 'add_mx_shipping_methods'));
                add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'mx_review_order_shipping_options'));
                add_action('woocommerce_review_order_after_shipping', array(&$this, 'mx_review_order_shipping_options'));
                add_action('woocommerce_checkout_update_order_meta', array(&$this, 'mx_field_update_shipping_order_meta'), 10, 2);


                if (!is_admin()) {
                    add_action('woocommerce_cart_shipping_method_full_label', array(&$this, 'negative_pickup_label'), 10, 2);
                }

                /* Adding a function to the woocommerce_admin_order_data_after_shipping_address hook. */
                if (is_admin()) {
                    add_action('woocommerce_admin_order_data_after_shipping_address', array(&$this, 'mx_display_shipping_admin_order_meta'), 10, 2);
                }
            }

            /*---------------------------------
             * calculate_shipping function
             *----------------------------
             */
            /**
             * If the shipping type is fixed, then the shipping total is the fee. If the shipping type
             * is percent, then the shipping total is the contents cost multiplied by the fee divided
             * by 100. If the shipping type is product, then the shipping total is the fee multiplied
             * by the quantity of each product
             * 
             * @param package This is an array of the cart contents.
             */
            function calculate_shipping($package = array())
            {
                $shipping_total = 0;
                $fee = (trim($this->fee) == '') ? 0 : $this->fee;

                if ($this->type == 'fixed')
                    $shipping_total = $this->fee;

                if ($this->type == 'percent')
                    $shipping_total = $package['contents_cost'] * ($this->fee / 100);

                if ($this->type == 'product') {
                    foreach ($package['contents'] as $item_id => $values) {
                        $_product = $values['data'];

                        if ($values['quantity'] > 0 && $_product->needs_shipping()) {
                            $shipping_total += $this->fee * $values['quantity'];
                        }
                    }
                }

                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $shipping_total
                );

                $this->add_rate($rate);
            }

            /**
             *  Create the settings page for the
             * plugin
             */
            function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Activer Click & collect ', 'mx-wc-local-pickup'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Cela change le titre que l\'utilisateur voit lors du paiement.', 'mx-wc-local-pickup'),
                        'default' => __('Click & collect', 'mx-wc-local-pickup'),
                        'desc_tip' => true,
                    ),

                    'shipping_options_table' => array(
                        'type' => 'shipping_options_table'
                    ),

                );
            }


            /**
             * @admin_options
             * Generates the settings for the plugin
             * 
             */
            function admin_options()
            { ?>
                <h3><?php echo $this->method_title; ?></h3>
                <p><?php _e('Le click & collect est un service permettant aux consommateurs de commander en ligne pour ensuite retirer leur article dans le magasin à proximité.', 'mx-wc-local-pickup'); ?></p>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
            <?php }

            /**
             * --------------------------
             *          FOR FEATURE VERSION
             * ---------------------------
             * If the postcode is in the list of postcodes, or if the postcode matches a pattern, or if
             * the postcode is a wildcard match, then the shipping method is available
             * 
             * @param package The package array that contains the shipping information.
             * 
             * @return The function is_available is being returned.
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

            /**
             * Clean fuction
             * 
             * It takes a string, removes all dashes, and then adds a dash back if the string contains
             * an asterisk
             * 
             * @param code The code of the coupon.
             * 
             * @return the sanitized title of the code, and if the code contains an asterisk, it will
             * add an asterisk to the end of the string.
             */
            function clean($code)
            {
                return str_replace('-', '', sanitize_title($code)) . (strstr($code, '*') ? '*' : '');
            }

            /*
             * validate_shipping_options_table_field function
             * @param key The key of the field to validate.
             */
            function validate_shipping_options_table_field($key)
            {
                return false;
            }


            /**
             * -------------------------------
             * generate_options_table_html
             *------------------------------
             * It generates a table with two columns, one for the name of the pickup location and one
             * for the description of the pickup location
             * 
             * @return the html for the table.
             */
            function generate_shipping_options_table_html()
            {
                ob_start();
            ?>
                <tr valign="top">
                    <th scope="row" class="titledesc"><?php _e('Point de retrait', 'mx-wc-local-pickup'); ?>:</th>
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
                                $i = -1;
                                if ($this->shipping_options) :
                                    for ($i = 0; $i < count($this->shipping_options["shop_name"]); $i++) {

                                ?>
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
                                <?php
                                    }
                                endif; ?>

                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4"><a href="#" class="add button"><?php _e('Ajouter', 'mx-wc-local-pickup'); ?></a> <a href="#" class="remove button"><?php _e('Supprimer', 'mx-wc-local-pickup'); ?></a></th>
                                </tr>
                            </tfoot>
                        </table>
                        <script type="text/javascript">
                            jQuery(function() {

                                jQuery('#<?php echo $this->id; ?>_options').on('click', 'a.add', function() {
                                    var size = jQuery('#<?php echo $this->id; ?>_options tbody .option-tr').size();
                                    jQuery('<tr class="option-tr">' +
                                            '<th class="check-column"><input type="checkbox" name="select" /></th>' +
                                            ' <th class="options-th">' +
                                            '<td><input type="text" name="<?php echo esc_attr($this->id . '_options') ?>[' + size + ']" /></td>' +
                                            '<td><textarea name="<?php echo esc_attr($this->id . '_options_descriptions') ?>[' + size + '] " rows="5" cols="33"></textarea> </td>' +
                                            '</th >< /tr>')
                                        .appendTo('#<?php echo $this->id; ?>_options table tbody');
                                    return false;
                                });

                                // Remove row
                                jQuery('#<?php echo $this->id; ?>_options').on('click', 'a.remove', function() {
                                    var answer = confirm("<?php _e('Voulez-vous Supprimer les élements sélectionnés ?', 'mx-wc-local-pickup'); ?>");
                                    if (answer) {
                                        jQuery('#<?php echo $this->id; ?>_options table tbody tr th.check-column input:checked').each(function(i, el) {
                                            jQuery(el).closest('tr').remove();
                                        });
                                    }
                                    return false;
                                });

                            });
                        </script>
                    </td>
                </tr>
                <?php return ob_get_clean();
            }

            /*
             * process_shipping_options function.
             */
            function process_shipping_options()
            {

                $options = array();

                if (isset($_POST[$this->id . '_options']))
                    $options = ["description" => array_map('wc_clean', $_POST[$this->id . '_options_descriptions']), "shop_name" => array_map('wc_clean', $_POST[$this->id . '_options'])];
                update_option($this->options_array_label, $options);

                $this->get_shipping_options();
            }


            /**
             * It gets the shipping options from the database and stores them in an array.
             */
            function get_shipping_options()
            {
                $this->shipping_options = array_filter((array) get_option($this->options_array_label));
            }

            /**
             * It creates a  shop select element with the options being the shop names.
             */
            function mx_review_order_shipping_options()
            {
                global $woocommerce;
                $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
                if (is_array($chosen_method) && in_array($this->id, $chosen_method)) {
                    echo '<tr class="shipping_option">';
                    echo '<th> ' . __('Boutique', 'mx-wc-local-pickup') . ': </th>';
                    echo '<td><select style="max-width:150px;" name="shipping_option" class="input-select" id="shipping_option" required="required">';
                    echo '<option data-description="' . __('Selectionnez une boutique', 'mx-wc-local-pickup') . '" value=""> ' . __('Selectionnez une boutique', 'mx-wc-local-pickup') . ' </option>';
                    $description = "";
                    for ($i = 0; $i < count($this->shipping_options['shop_name']); $i++) {
                        echo '<option  data-description= "' . $this->shipping_options['description'][$i] . '" value="' . esc_attr($this->shipping_options['shop_name'][$i]) . '" ' . selected($woocommerce->session->_chosen_shipping_option, esc_attr($this->shipping_options['shop_name'][$i])) . '>' . $this->shipping_options['shop_name'][$i] . '</option>';
                        $description = $this->shipping_options['description'][$i];
                    }
                    echo '</select><p class="info-description"></p></td></tr>';
                ?>
                    <script>
                        var options = document.getElementsByName("shipping_option");
                        if (options.length >= 1) {
                            options[0].addEventListener("change", function() {
                                var data = "action=mx_save_selected&shipping_option=" + this.value;
                                var xmlhttp;
                                if (window.XMLHttpRequest) {
                                    xmlhttp = new XMLHttpRequest();
                                } else {
                                    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                                }
                                xmlhttp.open('GET', '<?php echo admin_url('admin-ajax.php'); ?>?' + data, true);
                                xmlhttp.send();
                            });
                        }


                        /* Selecting the option that is selected and getting the data-description
                             attribute. */
                        $(".input-select").change(function() {
                            let str = $(".input-select option:selected").attr("data-description");
                            if (!str.includes("Selectionnez")) {
                                $(".info-description").html(str);

                            } else {
                                $(".info-description").html(" ");
                            }

                        })
                    </script>
<?php
                }
            }

            /**
             * If the shipping method is in the array of shipping methods, and the shipping
             * option is set and not empty, then update the post meta with the shipping
             * option.
             * 
             * @param order_id The order ID
             * @param posted The posted data from the checkout form.
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
             * It displays the shipping option selected by the customer on the admin order
             * page
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
             * @param methods This is an array of shipping methods that are available.
             * 
             * @return The  array with the  object added to it.
             */
            function add_mx_shipping_methods($methods)
            {
                $methods[] = $this;
                return $methods;
            }

            /**
             * If the current page is not the admin page and the shipping method is the one
             * we want to change, then change the label to the label plus the cost
             * 
             * @param label The label for the shipping method.
             * @param method The shipping method object.
             * 
             * @return The label for the shipping method.
             */
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


    /**
     * Display pickup location on the BO
     * It adds a new action to the WooCommerce order page, which displays the selected
     * pickup location
     * 
     * @param order The order object.
     */
    add_action('woocommerce_admin_order_data_after_shipping_address', 'mx_pickup_location_display', 10, 1);
    add_action('woocommerce_order_details_after_order_table', 'mx_pickup_location_display', 10, 1);

    function mx_pickup_location_display($order)
    {

        $selected_option = get_post_meta($order->get_id(), 'mx_shipping_option', true);
        if ($selected_option) {
            echo '<p><strong>' . __('Point de retrait', 'mx-wc-local-pickup') . ' : </strong> ' . get_post_meta($order->get_id(), 'mx_shipping_option', true) . '</p>';
        }
    }

    /**
     * It adds pickup location meta to order emails
     * 
     * @param keys The array of keys to be displayed in the email.
     * 
     * @return The function mx_shipping_option_meta_keys() is being returned.
     */
    add_filter('woocommerce_email_order_meta_keys', 'mx_shipping_option_meta_keys');

    function mx_shipping_option_meta_keys($keys)
    {
        if (isset($_POST['shipping_option']) && !empty($_POST['shipping_option'])) {
            echo '<h2>' . __('Point de retrait', 'mx-wc-local-pickup') . ':</h2>';

            $keys[''] = 'mx_shipping_option';
            return $keys;
        }
    }

    /**
     * It saves the selected shipping option in the session
     */
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

?>