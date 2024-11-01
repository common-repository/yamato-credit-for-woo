jQuery(function ($) {

    var kupaywc_myaccount = {

        init: function () {

            if ($('form.kupaywc-edit-kuronekocardmember-form').length) {
                this.form = $('form.kupaywc-edit-kuronekocardmember-form');
            }

            $(document).on('click', '#delete-cardmember', function () {
                if (!window.confirm(kuronekopayment_params.message.confirm_delete)) {
                    return false;
                }
                $('#edit-kuronekocardmember-action').val('delete_cardmember');
            });
        },

        block: function () {
            kupaywc_myaccount.form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },

        unblock: function () {
            kupaywc_myaccount.form.unblock();
        },

        reset: function () {
            $('.kuronekopayment-error').remove();
        },

        submit_error: function (error_message) {
            $('.woocommerce-NoticeGroup-myaccount, .woocommerce-error, .woocommerce-message').remove();
            kupaywc_myaccount.form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-myaccount">' + error_message + '</div>');
            kupaywc_myaccount.form.removeClass('processing').unblock();
            kupaywc_myaccount.form.find('.input-text, select').trigger('validate').blur();
            kupaywc_myaccount.scroll_to_notices();
            $(document.body).trigger('myaccount_error');
        },

        scroll_to_notices: function () {
            var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-myaccount'),
                isSmoothScrollSupported = 'scrollBehavior' in document.documentElement.style;

            if (!scrollElement.length) {
                scrollElement = $('.form.myaccount');
            }

            if (scrollElement.length) {
                if (isSmoothScrollSupported) {
                    scrollElement[0].scrollIntoView({
                        behavior: 'smooth'
                    });
                } else {
                    $('html, body').animate({
                        scrollTop: (scrollElement.offset().top - 100)
                    }, 1000);
                }
            }
        }
    };

    kupaywc_myaccount.init();
});

