=== Kuroneko Web Collect for Woo ===
Contributors: collne, uscnanbu
Tags: credit card, payment, woocommerce, kuroneko web collect
Donate link: https://www.welcart.com/
Requires at least: 5.5
Tested up to: 6.6.2
Requires PHP: 7.4 - 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==
Kuroneko Web Collect for Woo plugin allows you to accept Credit Cards via Kuroneko Web Collect system Powered by YAMATO TRANSPORT CO., LTD..
This plugin acts as an addon to add a payment method on WooCommerce checkout page.
On the checkout page, our plugin connects to the "Kuroneko Web Collect" system.

The payment methods provided are as follows.
(Available currency is only JPY.)
* Credit card
* PayPay settlement
PayPay settlement is available from Kuroneko web Collect for Woo 1.1.0.
You need to contact Yamato Transport separately to use PayPay settlement.

= Mandatory requirements =
* Account for "Kuroneko Web Collect".

= About Kuroneko Web Collect system =
"Kuroneko Web Collect" is an online payment service that enables payments on the Internet and can be used by both individuals and corporations.

* In order to safely protect credit card information handled by payment processing companies, we operate in accordance with the PCI DSS.
* Compatible with "3D secure" and "security code", we are implementing thorough security measures.

* Service:
  [https://business.kuronekoyamato.co.jp/service/lineup/payment_creditcard/](https://business.kuronekoyamato.co.jp/service/lineup/payment_creditcard/)
* Privacy Policy:
  [https://www.kuronekoyamato.co.jp/ytc/privacy/](https://www.kuronekoyamato.co.jp/ytc/privacy/)

= User Manual =
https://www.collne.com/dl/woo/kuroneko-web-collect-for-woo.pdf

= About the use of this service =
In order to use this service, you need to apply for it from the below link.
[https://entry-form.kuronekoyamato.co.jp/form/order_entrance.php](https://entry-form.kuronekoyamato.co.jp/form/order_entrance.php)
* The information sent to the dedicated form is YAMATO TRANSPORT CO., LTD., and after the application is completed, the "Kuroneko Web Collect" representative will contact you.
* To use the "Kuroneko Web Collect", a review by YAMATO TRANSPORT CO., LTD. and each payment institution is required. Depending on the result of the examination, we may not be able to meet your request.


== Installation ==

= Minimum Requirements =

* WooCommerce 3.5 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Kuroneko Web Collect for Woo, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type Kuroneko Web Collect for Woo and click Search Plugins. Once you've found our plugin you can view details about it such as the the rating and description. Most importantly, of course, you can install it by simply clicking Install Now.

= Manual installation =

Download page
https://ja.wordpress.org/plugins/yamato-credit-for-woo/

1.Go to WordPress Plugins > Add New
2.Click Upload Plugin Zip File
3.Upload the zipped Kuroneko Web Collect for Woo file and click "Upload Now"
4.Go to Installed Plugins
5.Activate the "Kuroneko Web Collect for Woo"


== Frequently Asked Questions ==


== Changelog ==
= 2.0.0 - 2024-9-30 =
* Fixed - Added support for blocking certain payment methods.
* Fixed - Added support for high-performance order storage.

= 1.1.5 - 2024-7-8 =
* Update - Added shipping information to the 3DS2.0 merchant transmission items.

= 1.1.4 - 2024-3-19 =
* Fixed - Fixed error message.
* Update - Updated readme.

= 1.1.3 - 2023-10-13 =
* Tested - Tested up to WordPress 6.3.
* Update - Updated readme.

= 1.1.2 - 2023-5-31 =
* Update - Added permission check to management screen form submission.

= 1.1.1 - 2022-11-08 =
* Fixed - Fixed the bug that "No Merchant Code" error when confirming sales for credit card payment.

= 1.1.0 - 2022-11-01 =
* Tested - Tested up to WordPress 6.0.
* Update - Added Paypay payment.

= 1.0.6 - 2021-07-26 =
* Tested - Tested up to WordPress 5.8.

= 1.0.5 - 2020-12-10 =
* Improved the function that the checkbox is checked when you click the label.
* Fixed the bug that the close button of the payment dialog in the admin screen is broken when you update to WordPress 5.6.

= 1.0.4 - 2020-09-02 =
* Fixed - Fixed the bug that a completion email is delivered to the customer even though the order status cannot be updated in the event of an incorrect voucher number etc.
* Fixed - Fixed the bug that the order status of the Kuroneko delivery can be updated to "completed" from the order list screen.
* Fixed - Fixed the bug that the invoice number was not set in the mail depends on the specific environment.

= 1.0.3 - 2020-08-19 =
* Changed - Changed the plugin name.
* Fixed - Fixed the bug related 3D-Secure.

= 1.0.2 - 2020-07-27 =
* Fixed - Fixed the bug that a card registration check box is appeared although a customer doesn't log in to a member.

= 1.0.1 - 2020-07-06 =
* Feature - Compatible with WordPress Official Plugin Coding rule.

= 1.0.0 - 2020-04-13 =
* Feature - Yamato Credit Card Payment
