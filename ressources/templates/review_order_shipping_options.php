<?php

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
        // foreach ($this->shipping_options['shop_name'] as $option) {

        // }
        echo '</select><p class="info-description">' . $description . '</p></td></tr>';
        //  var_dump($this->shipping_options);

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
            /* The above code is using jQuery to change the text of the info-description
            div to the text of the selected option in the input-select select element. */

            $(".input-select").change(function() {
                let str = $(".input-select option:selected").attr("data-description");
                if (!str.includes("Select")) {
                    $(".info-description").html(str);

                } else {
                    $(".info-description").html(" ");
                }
                console.log($(".input-select option:selected").attr("data-description"));
            })
        </script>
<?php
    }
}
