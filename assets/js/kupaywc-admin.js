jQuery(function ($) {

    var kupaywc_admin = {

        init: function () {

            if ($('#woocommerce_kuronekopayment_cardmember').is(':checked')) {
                $('#woocommerce_kuronekopayment_always_save').closest('tr').show();
            } else {
                $('#woocommerce_kuronekopayment_always_save').closest('tr').hide();
            }

            $(document).on('change', '#woocommerce_kuronekopayment_cardmember', function () {
                if ($(this).is(':checked')) {
                    $('#woocommerce_kuronekopayment_always_save').closest('tr').show();
                } else {
                    $('#woocommerce_kuronekopayment_always_save').prop('checked', false);
                    $('#woocommerce_kuronekopayment_always_save').closest('tr').hide();
                }
            });

            $(document).on('change', '#woocommerce_kuronekopayment_linktype', function () {
                if ($(this).is(':checked')) {
                    $('#woocommerce_kuronekopayment_token_code').closest('tr').hide();
                    $('#woocommerce_kuronekopayment_three_d_secure').closest('tr').hide();
                } else {
                    $('#woocommerce_kuronekopayment_token_code').closest('tr').show();
                    $('#woocommerce_kuronekopayment_three_d_secure').closest('tr').show();
                }
            });

        }
    };

    kupaywc_admin.init();
});
