<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/*
* generate_options_table_html function
*/
function generate_shipping_options_table_html()
{
    ob_start();
?>
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
