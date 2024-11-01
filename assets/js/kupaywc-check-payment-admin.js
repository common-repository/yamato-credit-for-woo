jQuery(document).ready(function ($) {

    $(".woocommerce-save-button").on("click", checkTextareaValue);


    function checkTextareaValue(event) {
        if ($("#woocommerce_kuronekopayment_three_d_secure:checked").val() ||
            $("#woocommerce_kuronekopayment_seccd:checked").val()) {
            return true
        }
        alert('3Dセキュア認証もしくはセキュリティコードの認証のどちらかにチェックを入れてください');
        return false
    }

});