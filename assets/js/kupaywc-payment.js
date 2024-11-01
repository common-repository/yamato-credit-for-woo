jQuery(function ($) {
    var kupaywc_payment = {
        init: function () {

            if ($('form.woocommerce-checkout').length) {
                this.form = $('form.woocommerce-checkout');
            }

            $(document.body).on('click', '#place_order', function () {
                if (kupaywc_payment.isKuronekoPaymentChosen()) {
                    if (kuronekopayment_params.is_user_logged_in && kuronekopayment_params.cardmember && kuronekopayment_params.is_card_member) {
                        var card_member = kupaywc_payment.getCardMemberOption();
                        if (card_member == 'saved' || card_member == 'unsaved' || card_member == 'change') {
                            kupaywc_payment.getToken();
                            return false;
                        } else {
                            kupaywc_payment.submit_error('<div class="woocommerce-error">' + kuronekopayment_params.message.error_card_member_option + '</div>');
                            return false;
                        }
                    } else {
                        kupaywc_payment.getToken();
                        return false;
                    }
                }
                if(kupaywc_payment.isKuronekoPaymentPaypayChosen()){
                    var formElement = document.checkout;
                    var place_order = document.createElement("input");
                    place_order.value = "";
                    place_order.type = "hidden";
                    place_order.name = "woocommerce_checkout_place_order";
                    formElement.appendChild(place_order);
                    formElement.submit();

                    return false;
                }
            });
            $(document.body).on('click', '#kuroneko-card-member-change', function () {
                $.ajax({
                    url: kupaywc_payment.getAjaxURL('change_card_member'),
                    type: 'POST',
                    cache: false,
                    data: {
                        security: kuronekopayment_params.nonce.card_member,
                        // customer_id: kuronekopayment_request_params.customer_id
                    }
                }).done(function (retVal) {
                    if ('0' === retVal.returnCode) {
                        location.reload();
                    }
                }).fail(function (retVal) {
                    window.console.log(retVal);
                });
                return false;
            });
        },

        getAjaxURL: function (endpoint) {
            return kuronekopayment_params.ajax_url
                .toString()
                .replace('%%endpoint%%', 'kupaywc_' + endpoint);
        },

        isKuronekoPaymentChosen: function () {
            return $('#payment_method_kuronekopayment').is(':checked');
        },

        isKuronekoPaymentPaypayChosen: function () {
            return $('#payment_method_kuronekopayment_paypay').is(':checked');
        },

        getCardMemberOption: function () {
            return $('input[name="kuronekopayment_card_member_option"]:checked').val();
        },

        block: function () {
			$( 'form.woocommerce-checkout' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },

        unblock: function () {
			$( 'form.woocommerce-checkout' ).unblock();
        },

        reset: function () {
            $('.kuronekopayment-error').remove();
        },

        getPayType: function () {
            var paytype = '01';
            if (!$('#kuronekopayment-card-paytype-default').prop('disabled')) {
                paytype = $('#kuronekopayment-card-paytype-default option:selected').val();
            } else if (!$('#kuronekopayment-card-paytype-4535').prop('disabled')) {
                paytype = $('#kuronekopayment-card-paytype-4535 option:selected').val();
            } else if (!$('#kuronekopayment-card-paytype-37').prop('disabled')) {
                paytype = $('#kuronekopayment-card-paytype-37 option:selected').val();
            } else if (!$('#kuronekopayment-card-paytype-36').prop('disabled')) {
                paytype = $('#kuronekopayment-card-paytype-36 option:selected').val();
            }
            return paytype;
        },

        getToken: function () {
            kupaywc_payment.block();

            var elements = document.querySelectorAll('input[type="text"]');
            Array.prototype.forEach.call(elements, function (element) {
                element.style.backgroundColor = "#fff";
            });

            // コールバック関数(「正常」の場合)
            var callbackSuccess = function (response) {
                var formElement = document.checkout;
                // form に発行したトークンを追加する
                document.getElementById("kuronekopayment-token-code").value = response.token;
                var place_order = document.createElement("input");
                place_order.value = "";
                place_order.type = "hidden";
                place_order.name = "woocommerce_checkout_place_order";
                formElement.appendChild(place_order);
                var pay_type = document.createElement("input");
                pay_type.value = kupaywc_payment.getPayType();
                pay_type.name = "pay_type";
                pay_type.type = "hidden";
                formElement.appendChild(pay_type);
                formElement.submit();
            };

            // コールバック関数(「異常」の場合)
            var callbackFailure = function (response) {
                //エラー情報を取得
                var errorInfo = response.errorInfo;

                //errorItem の内容に応じてテキストボックスの背景色を変更する関数
                function changeColor(errorItem) {
                    switch (errorItem) {
                        case "cardNo":
                            document.getElementById('kuronekopayment-card-number').style.backgroundColor = "#fdeef1";
                            break;
                        case "cardOwner":
                            document.getElementById('kuronekopayment-card-owner').style.backgroundColor = "#fdeef1";
                            break;
                        case "cardExp":
                            document.getElementById('kuronekopayment-card-expmm').style.backgroundColor = "#fdeef1";
                            document.getElementById('kuronekopayment-card-expyy').style.backgroundColor = "#fdeef1";
                            break;
                        case "securityCode":
                            document.getElementById('kuronekopayment-card-seccd').style.backgroundColor = "#fdeef1";
                            break;
                    }
                }

                //エラーの数だけ処理を繰り返す
                for (var i = 0; i < errorInfo.length; i++) {
                    if (errorInfo[i].errorItem) {
                        changeColor(errorInfo[i].errorItem);
                    }
                    //メッセージを alert で出力
                    alert(errorInfo[i].errorCode + " : " + errorInfo[i].errorMsg);
                }
                kupaywc_payment.unblock();
            };

            // トークン発行 API へ渡すパラメータ
            var createTokenInfo = {
                traderCode: kuronekopayment_params.trader_code,
                authDiv: kuronekopayment_params.auth_div,
                cardNo: document.getElementById('kuronekopayment-card-number') !== null ? document.getElementById('kuronekopayment-card-number').value : '',
                cardOwner: document.getElementById('kuronekopayment-card-owner') !== null ? document.getElementById('kuronekopayment-card-owner').value : '',
                cardExp: document.getElementById('kuronekopayment-card-expmm') !== null || document.getElementById('kuronekopayment-card-expyy') !== null ? document.getElementById('kuronekopayment-card-expmm').value + document.getElementById('kuronekopayment-card-expyy').value : '',
                securityCode: document.getElementById('kuronekopayment-card-seccd') !== null ? document.getElementById('kuronekopayment-card-seccd').value : ''
            };

            if ($('input[type="checkbox"]#kuronekopayment-save-payment-method').length > 0) {
                if ($('input[type="checkbox"]#kuronekopayment-save-payment-method').prop('checked')) {
                    kuronekopayment_params.enable_quick = 'restore'
                } else {
                    kuronekopayment_params.enable_quick = 'none'
                }
            }

            if ('add' === kuronekopayment_params.enable_quick || 'restore' === kuronekopayment_params.enable_quick) {
                createTokenInfo.memberId = kuronekopayment_params.member_id
                createTokenInfo.authKey = kuronekopayment_params.auth_key
                createTokenInfo.checkSum = kuronekopayment_params.check_member_sum
                createTokenInfo.cardKey = ''
                createTokenInfo.lastCreditDate = ''
                createTokenInfo.optServDiv = '01'
            } else if ('saved' === kuronekopayment_params.enable_quick) {
                createTokenInfo.memberId = kuronekopayment_params.member_id
                createTokenInfo.authKey = kuronekopayment_params.auth_key
                createTokenInfo.checkSum = kuronekopayment_params.check_member_sum
                createTokenInfo.cardKey = kuronekopayment_params.card_key
                createTokenInfo.lastCreditDate = kuronekopayment_params.last_credit_date
                createTokenInfo.optServDiv = '01'
                createTokenInfo.cardNo = kuronekopayment_params.card_no
                createTokenInfo.cardOwner = kupaywc_payment.card_owner
                createTokenInfo.cardExp = kupaywc_payment.card_exp
            } else {
                createTokenInfo.memberId = ''
                createTokenInfo.authKey = ''
                createTokenInfo.checkSum = kuronekopayment_params.check_sum
                createTokenInfo.cardKey = ''
                createTokenInfo.lastCreditDate = ''
                createTokenInfo.optServDiv = '00'
            }

            // webコレクトが提供する JavaScript 関数を実行し、トークンを発行する。
            WebcollectTokenLib.createToken(createTokenInfo, callbackSuccess, callbackFailure);

        },
    };

    kupaywc_payment.init();
});

function setToken(token, card) {
    document.getElementById("kuronekopayment-token-code").value = token;
    var place_order = document.createElement("input");
    place_order.value = "";
    place_order.name = "woocommerce_checkout_place_order";
    document.checkout.appendChild(place_order);
    document.checkout.submit();
}
