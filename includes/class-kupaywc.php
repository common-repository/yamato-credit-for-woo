<?php

class Woo_KuronekoPay {

	/**
	 * @var $instance mixed Instance of this class.
	 */
	protected static $instance = null;

	/**
	 * @var $kuroneko_order_invoice_number string|int Invoice Number.
	 */
	public $kuroneko_order_invoice_number = '';

	/**
	 * @var $error WP_Error object.
	 */
	public $error;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->error = new WP_Error();

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		register_activation_hook( KUPAYWC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( KUPAYWC_PLUGIN_FILE, array( $this, 'deactivate' ) );

		do_action( 'kupaywc_loaded' );
		add_action( 'wc_ajax_kupaywc_change_card_member', array( $this, 'ajax_change_card_member' ) );
	}


	/**
	 * お預かりカードの変更.
	 *
	 * @return void
	 * @throws KUPAYWC_Exception
	 */
	public function ajax_change_card_member() {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( is_user_logged_in() && $settings['cardmember'] ) {
			$member_id     = get_current_user_id();
			$member        = new KUPAYWC_Card_Member( $member_id );
			$response_info = $member->search_card_member();

			if ( ! isset( $response_info['returnCode'] ) || '1' === $response_info['returnCode'] ) {
				// エラー時
				$localized_message = '';
				if ( isset( $response_info['errorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_info['errorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response_info['errorCode'] = KUPAYWCCodes::errorLabel( $response_info['errorCode'] );
				}
				if ( isset( $response_info['creditErrorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_info['creditErrorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response_info['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_info['creditErrorCode'] );
				}

				if ( empty( $localized_message ) ) {
					$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
				}
				throw new KUPAYWC_Exception( print_r( $response_info, true ), $localized_message );
			}
			$param_list                     = array();
			$param_list['card_key']         = $response_info['cardData']['cardKey'];
			$param_list['last_credit_date'] = $response_info['cardData']['lastCreditDate'];
			$response_delete                = $member->delete_card_member( $param_list );
			if ( '0' === $response_delete['returnCode'] ) {
				wp_send_json( $response_delete );
			} else {
				// エラー時
				$localized_message = '';
				if ( isset( $response_delete['errorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_delete['errorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response_delete['errorCode'] = KUPAYWCCodes::errorLabel( $response_delete['errorCode'] );
				}
				if ( isset( $response_delete['creditErrorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_delete['creditErrorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response_delete['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_delete['creditErrorCode'] );
				}

				if ( empty( $localized_message ) ) {
					$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
				}
				throw new KUPAYWC_Exception( print_r( $response_delete, true ), $localized_message );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * This function is hooked into admin_init to affect admin only.
	 */
	public function admin_init() {
		if ( ! is_plugin_active( KUPAYWC_PLUGIN_BASENAME ) ) {
			return;
		}

		include_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-admin-order.php';
	}

	/**
	 * Initial processing.
	 */
	public function init() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		load_plugin_textdomain( 'kupaywc', false, plugin_basename( dirname( KUPAYWC_PLUGIN_FILE ) ) . '/languages' );

		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-logger.php';
		include_once KUPAYWC_PLUGIN_DIR . '/includes/kupaywc-functions.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-sln-connection.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-card-member.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-logger.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-request.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-response-handler.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-response-handler-paypay.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-gateway.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-gateway-paypay.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-payment-support.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-myaccount.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-exception.php';
		require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-codes.php';

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'plugin_action_links_' . KUPAYWC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
			add_filter( 'woocommerce_get_sections_checkout', array( $this, 'get_sections_checkout' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );
		add_action( 'woocommerce_email_customer_details', array( $this, 'kupaywc_email_customer_details' ), 10, 4 );
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = ( $screen ) ? $screen->id : '';

		switch ( $screen_id ) {
			case 'woocommerce_page_wc-settings':
				wp_enqueue_script( 'kuronekopayment_admin_scripts', plugins_url( 'assets/js/kupaywc-admin.js', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION, true );
				break;
			case 'shop_order':
			case 'woocommerce_page_wc-orders':
				wp_enqueue_script( 'jquery-ui-dialog' );
				break;
		}
	}

	/**
	 * Enqueue admin styles.
	 */
	function admin_styles() {
		$screen    = get_current_screen();
		$screen_id = ( $screen ) ? $screen->id : '';

		wp_enqueue_style( 'kuronekopayment_admin_styles', plugins_url( 'assets/css/kupaywc-admin.css', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION );

		if ( 'shop_order' === $screen_id || 'woocommerce_page_wc-orders' === $screen_id ) {
			global $wp_scripts;
			$ui = $wp_scripts->query( 'jquery-ui-core' );
			$ui_themes = apply_filters( 'kupaywc_jquery_ui_themes', 'smoothness' );
			wp_enqueue_style( 'jquery-ui-kuroneko', "//code.jquery.com/ui/{$ui->ver}/themes/{$ui_themes}/jquery-ui.css" );
		}
	}

	/**
	 * Run when plugin is activated.
	 */
	function activate() {
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
			deactivate_plugins( KUPAYWC_PLUGIN_BASENAME );
		} else {
			require_once KUPAYWC_PLUGIN_DIR . '/includes/class-kupaywc-install.php';
			KUPAYWC_Install::create_tables();
		}
	}

	/**
	 * Run when plugin is deactivated.
	 */
	function deactivate() {
	}

	/**
	 * Add the gateways to WooCommerce.
	 *
	 * @param mixed $methods Gateways.
	 *
	 * @return mixed
	 */
	public function add_gateways( $methods ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
			$methods[] = 'WC_Gateway_KuronekoPayment_Subscriptions';
		} else {
			$methods[] = 'WC_Gateway_KuronekoPayment';
			$methods[] = 'WC_Gateway_KuronekoPayment_PayPay';
		}

		return $methods;
	}

	/**
	 * Adds plugin action links.
	 *
	 * @param array|string[] $links 支払方法のリンク.
	 *
	 * @return array|string[]
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="admin.php?page=wc-settings&tab=checkout&section=kuronekopayment">' . esc_html__( 'Settings', 'kupaywc' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Modifies the order of the gateways displayed in admin.
	 *
	 * @param mixed $sections 支払方法.
	 *
	 * @return mixed
	 */
	public function get_sections_checkout( $sections ) {
		unset( $sections['kuronekopayment'] );
		$sections['kuronekopayment'] = __( 'KuronekoWebCollect', 'kupaywc' );

		return $sections;
	}

	/**
	 * Payment detail in emails.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $sent_to_admin Sent to admin.
	 * @param string   $plain_text Mail Plain text.
	 * @param mixed    $email Email.
	 */
	public function kupaywc_email_customer_details( $order, $sent_to_admin, $plain_text, $email ) {

		if ( 'kuronekopayment' !== $order->get_payment_method() ) {
			return;
		}

		$is_delivery_kuroneko = false;
		foreach ( $order->get_shipping_methods() as $shipping ) {
			/** @var WC_Order_Item_Shipping $shipping */
			$woocommerce_settings = get_option( 'woocommerce_' . $shipping->get_method_id() . '_' . $shipping->get_instance_id() . '_settings' );
			if ( isset( $woocommerce_settings['kuroneko_delivery_check'] ) && 'yes' === $woocommerce_settings['kuroneko_delivery_check'] ) {
				$is_delivery_kuroneko = true;
			}
		}
		$ship_no = $is_delivery_kuroneko ? $order->get_meta( 'kuroneko_order_invoice_number', true ) : '';
		// ポストメタに送り状番号が無い場合はグローバル変数から取得する.
		global $kupaywc;
		if ( $is_delivery_kuroneko && ! $ship_no ) {
			$ship_no = $kupaywc->kuroneko_order_invoice_number;
		}
		if ( ! $ship_no ) {
			return;
		}

		if ( $plain_text ) {
			echo esc_html__( 'Kuroneko Invoice Number', 'kupaywc' ) . "\n\n";
			echo esc_html( $ship_no ) . "\n\n";
		} else {
			$text_align = ( is_rtl() ) ? 'right' : 'left';
			$email      = '<table style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;border-spacing: 0;" border="0">
				<tr>
					<th scope="row" colspan="2" style="color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left">' . esc_html__( 'Kuroneko Invoice Number', 'kupaywc' ) . ':</th>
					<td style="color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;text-align:left"><span>' . esc_html( $ship_no ) . '</span></td>
				</tr>
			</table>';
			echo $email;
		}
	}
}
