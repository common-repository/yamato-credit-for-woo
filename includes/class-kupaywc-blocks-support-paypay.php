<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Stripe_Blocks_Support class.
 *
 * @extends AbstractPaymentMethodType
 */
final class Woo_KuronekoPay_Blocks_Support_PayPay extends AbstractPaymentMethodType {
	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Dummy
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'kuronekopayment_paypay';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$api_token_url = KUPAYWC_SLN_Connection::api_token_url();
		wp_enqueue_script( 'kuroneko_tokenjs', $api_token_url, false, KUPAYWC_VERSION, true );

		$script_path       = '/build/index.js';
		$script_asset_path = KUPAYWC_PLUGIN_DIR . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => KUPAYWC_VERSION,
			);
		$script_url        = KUPAYWC_PLUGIN_URL . $script_path;

		wp_register_script(
			'wc-kuronekopayment-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-kuronekopayment-payments-blocks', 'kupaywc', plugin_dir_path( __FILE__ ) . 'languages' );
		}

		return array( 'wc-kuronekopayment-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {

		$customer_id = get_current_user_id();

		$description = $this->gateway->get_option( 'description' );
		if ( 'yes' === $this->gateway->get_option( 'testmode' ) ) {
			$description .= ' ' . __( 'TESTMODE RUNNING.', 'kupaywc' );
			$description = trim( $description );
		}
		$description = apply_filters( 'kupaywc_payment_paypay_description', wpautop( wp_kses_post( $description ) ), $this->name );

		$data = array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $description,
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'testmode'    => 'yes' === $this->gateway->get_option( 'testmode' ),
		);

		return $data;
	}
}
