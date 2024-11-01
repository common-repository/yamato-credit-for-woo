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
final class Woo_KuronekoPay_Blocks_Support extends AbstractPaymentMethodType {
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
	protected $name = 'kuronekopayment';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
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
		$member      = new KUPAYWC_card_member( $customer_id );
		$language = array(
			'card_number' => __( 'Card number', 'kupaywc' ),
			'card_holder' => __( 'Card holder', 'kupaywc' ),
			'expiry_mm_yy' => __( 'Expiry (MM/YY)', 'kupaywc' ),
			'expiry_mm' => __( 'MM', 'kupaywc' ),
			'expiry_yy' => __( 'YY', 'kupaywc' ),
			'card_code' => __( 'Card code', 'kupaywc' ),
			'howtopay' => __( 'The number of installments', 'kupaywc' ),
			'lump_sum_payment' => __( 'Lump-sum payment', 'kupaywc' ),
			'installments' => __( '-installments', 'kupaywc' ),
			'using_saved_card' => __( 'Using the saved credit card.', 'kupaywc' ),
			'last_4_digits' => __( 'Last 4 digits of the saved card number: ', 'kupaywc' ),
		);

		$data = array(
			'title'                        => $this->get_setting( 'title' ),
			'description'                  => $this->get_setting( 'description' ),
			'supports'                     => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'cardlast4'                    => '',
			'seccd'                        => 'yes' === $this->get_setting( 'seccd', 'yes' ),
			'cardmember'                   => 'yes' === $this->get_setting( 'cardmember', 'yes' ),
			'always_save'                  => 'yes' === $this->get_setting( 'always_save', 'yes' ),
			'howtopay'                     => $this->get_setting( 'howtopay', '1' ),
			'is_card_member'               => false,
			'is_user_logged_in'            => is_user_logged_in(),
			'kupaywc_save_to_account_text' => apply_filters( 'kupaywc_save_to_account_text', __( 'Save the card information to your account data. You won\'t need to input the card number from next purchases.', 'kupaywc' ), 'add' ),
			'language'                     => $language
		);

		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		if ( is_page( $checkout_page_id ) ) {
			if ( $member->is_card_member() ) {
				$data['is_card_member'] = true;
				$response_member        = $member->search_card_member();
				if ( '0' === $response_member['returnCode'] && 0 === (int) $response_member['cardUnit'] ) {
					$member->delete_auth_key( $member->get_auth_key() );
				}
				if ( '0' === $response_member['returnCode'] && isset( $response_member['cardData'] ) ) {
					$data['cardlast4']  = substr( $response_member['cardData']['maskingCardNo'], - 4 );
					$data['cardfirst4'] = substr( $response_member['cardData']['maskingCardNo'], 0, 4 );
					$data['card_owner'] = $response_member['cardData']['cardOwner'];
					$data['status']     = 'success';
				} else {
					$data['status'] = 'fail';
				}
			}
		}

		return $data;
	}
}
