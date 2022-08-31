/* A jQuery function that is called when the page is loaded. */
jQuery(function() {

   /* Adding a new row to the table. */
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

   /* Removing the selected rows. */
    jQuery('#<?php echo $this->id; ?>_options').on('click', 'a.remove', function() {
        var answer = confirm("<?php _e('Delete the selected options?', 'mx-wc-local-pickup'); ?>");
        if (answer) {
            jQuery('#<?php echo $this->id; ?>_options table tbody tr th.check-column input:checked').each(function(i, el) {
                jQuery(el).closest('tr').remove();
            });
        }
        return false;
    });




    /* Saving the selected option to the database. */
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
            xmlhttp.open('GET', '/wp-admin/admin-ajax.php' + data, true);
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

});