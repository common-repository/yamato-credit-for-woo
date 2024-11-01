<?php
/**
 * Plugin Name: Kuroneko Web Collect for Woo
 * Plugin URI: https://www.collne.com/yamato-credit-for-woo/
 * Description: When using KuronekoWebCollect, the payment of credit card will be available.
 * Author: Welcart Inc., yamatofinancial
 * Author URI: https://www.welcart.com/
 * Version: 2.0.0
 * WC requires at least: 3.5
 * WC tested up to: 9.3.3
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kupaywc
 * Domain Path: /languages
 */

use Automattic\WooCommerce\Blocks\Registry\FactoryType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KUPAYWC_VERSION', '2.0.0' );
define( 'KUPAYWC_PLUGIN_FILE', __FILE__ );
define( 'KUPAYWC_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'KUPAYWC_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'KUPAYWC_PLUGIN_BASENAME', untrailingslashit( plugin_basename( __FILE__ ) ) );

if ( ! class_exists( 'Woo_KuronekoPay' ) ) {
	include_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc.php';
}

global $kupaywc;
$kupaywc = Woo_KuronekoPay::get_instance();

add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_kuroneko_pay_woocommerce_block_support' );
function woocommerce_gateway_kuroneko_pay_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-kupaywc-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Woo_KuronekoPay_Blocks_Support() );
			}
		);

		require_once dirname( __FILE__ ) . '/includes/class-kupaywc-blocks-support-paypay.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Woo_KuronekoPay_Blocks_Support_PayPay() );
			}
		);
	}
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
