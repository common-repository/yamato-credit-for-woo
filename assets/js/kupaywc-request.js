jQuery(document).ready(function ($) {

    var kupaywc_request = {

        $checkout_form: $('form.checkout'),

        init: function () {

            this.$checkout_form.on('click', 'input[name="payment_method"]', this.paymentMethodSelected);

            $(document).on('change', '#kuronekopayment-card-number', function () {
                kupaywc_request.changePayType($(this).val());
            });
        },

        getAjaxURL: function (endpoint) {
            return kuronekopayment_request_params.ajax_url
                .toString()
                .replace('%%endpoint%%', 'kupaywc_' + endpoint);
        },

        getCardMember: function () {
            $.ajax({
                url: kupaywc_request.getAjaxURL('get_card_member'),
                type: 'POST',
                cache: false,
                data: {
                    security: kuronekopayment_request_params.nonce.card_member,
                    customer_id: kuronekopayment_request_params.customer_id
                }
            }).done(function (retVal) {
                if ('success' == retVal.status) {
                    $('#kuronekopayment-card-member-option-saved').prop('checked', true);
                    $('#kuronekopayment-card-member-cardlast4').text(retVal.cardlast4);
                    kupaywc_request.changePayType(retVal.cardfirst4);
                }
            }).fail(function (retVal) {
                window.console.log(retVal);
            });
            return false;
        },

        paymentMethodSelected: function () {
            if ($('#payment_method_kuronekopayment').is(':checked') && kuronekopayment_request_params.customer_id.length > 0) {
                kupaywc_request.getCardMember();
            }
        },

        changePayType: function (cnum) {
            var first_c = cnum.substr(0, 1);
            var second_c = cnum.substr(1, 1);
            if ('4' == first_c || '5' == first_c || ('3' == first_c && '5' == second_c)) {
                $('#kuronekopayment-card-paytype-default').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-4535').removeAttr('disabled').css('display', 'inline');
                $('#kuronekopayment-card-paytype-37').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-36').attr('disabled', 'disabled').css('display', 'none');
            } else if ('3' == first_c && '6' == second_c) {
                $('#kuronekopayment-card-paytype-default').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-4535').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-37').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-36').removeAttr('disabled').css('display', 'inline');
            } else if ('3' == first_c && '7' == second_c) {
                $('#kuronekopayment-card-paytype-default').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-4535').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-37').removeAttr('disabled').css('display', 'inline');
                $('#kuronekopayment-card-paytype-36').attr('disabled', 'disabled').css('display', 'none');
            } else {
                $('#kuronekopayment-card-paytype-default').removeAttr('disabled').css('display', 'inline');
                $('#kuronekopayment-card-paytype-4535').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-37').attr('disabled', 'disabled').css('display', 'none');
                $('#kuronekopayment-card-paytype-36').attr('disabled', 'disabled').css('display', 'none');
            }
        }
    };

    kupaywc_request.init();

});
