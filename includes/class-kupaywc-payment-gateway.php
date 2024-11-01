<?php
/**
 * WC_Gateway_KuronekoPayment class.
 *
 * @extends WC_Payment_Gateway
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_KuronekoPayment extends WC_Payment_Gateway {
	const C = '" />&nbsp;/&nbsp;
            <input type="text" id="';

	/**
	 * Constructor for the gateway.
	 *
	 */
	public function __construct() {
		$this->id                 = 'kuronekopayment';
		$this->has_fields         = true;
		$this->method_title       = __( 'KuronekoWebCollect', 'kupaywc' );
		$this->method_description = __( 'Credit card gateway of Kuroneko Web Collect. Only available for billing to Japan.', 'kupaywc' );
		$this->supports           = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled        = $this->get_option( 'enabled' );
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->testmode       = 'yes' === $this->get_option( 'testmode' );
		$this->trader_code    = $this->get_option( 'trader_code' );
		$this->access_key     = $this->get_option( 'access_key' );
		$this->token_code     = $this->get_option( 'token_code' );
		$this->three_d_secure = 'yes' === $this->get_option( 'three_d_secure', 'yes' );
		$this->seccd          = 'yes' === $this->get_option( 'seccd', 'yes' );
		$this->cardmember     = 'yes' === $this->get_option( 'cardmember', 'yes' );
		$this->always_save    = 'yes' === $this->get_option( 'always_save', 'yes' );
		$this->operate_id     = $this->get_option( 'operate_id', '1Gathering' );
		$this->howtopay       = $this->get_option( 'howtopay', '1' );
		$this->logging        = 'yes' === $this->get_option( 'logging', 'yes' );


		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );


	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {

		return in_array( get_woocommerce_currency(), apply_filters( 'kupaywc_supported_currencies', array( 'JPY' ) ) );
	}


	/**
	 * Admin save options.
	 *
	 */
	public function admin_options() {

		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
            <div class="inline error"><p>
                    <strong><?php echo esc_html__( 'Gateway disabled', 'kupaywc' ); ?></strong>: <?php echo esc_html__( 'KuronekoWebCollect does not support your store currency.', 'kupaywc' ); ?>
                </p></div>
			<?php
		}
	}

	/**
	 * Checks if required items are set.
	 *
	 * @return bool
	 */
	public function is_valid_setting() {

		if ( empty( $this->trader_code ) || empty( $this->access_key ) || 'no' === $this->enabled ) {
			return false;
		}

		return true;
	}


	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( ! $this->is_valid_setting() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Initialise gateway settings form fields.
	 *
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters( 'kupaywc_gateway_settings',
			array(
				'enabled'        => array(
					'title'   => __( 'Enable/Disable', 'kupaywc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable KuronekoWebCollect', 'kupaywc' ),
					'default' => 'no',
				),
				'title'          => array(
					'title'       => __( 'Title', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'kupaywc' ),
					'default'     => __( 'Credit Card', 'kupaywc' ),
					'desc_tip'    => true,
				),
				'description'    => array(
					'title'       => __( 'Description', 'kupaywc' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'kupaywc' ),
					'default'     => __( 'Pay with your credit card.', 'kupaywc' ),
				),
				'testmode'       => array(
					'title'       => __( 'Test mode', 'kupaywc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Test mode', 'kupaywc' ),
					'default'     => 'yes',
					'description' => __( 'Connect to test environment and run in test mode.', 'kupaywc' ),
				),
				'trader_code'    => array(
					'title'       => __( 'Trader Code', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( '9 digits on contract sheet with Kuroneko Web Collect.', 'kupaywc' ),
					'desc_tip'    => true,
				),
				'access_key'     => array(
					'title'       => __( 'Access Key', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( 'Last numerics of access URL on contract sheet with Kuroneko Web Collect.', 'kupaywc' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'three_d_secure' => array(
					'title'       => __( '3D Secure', 'kupaywc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use 3D Secure authentication', 'kupaywc' ),
					'default'     => 'off',
					'description' => __( '3D Secure Authentication for Settlement.', 'kupaywc' ),
				),
				'seccd'          => array(
					'title'   => __( 'Security code (authentication assist)', 'kupaywc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Use authentication of Security code (authentication assist)', 'kupaywc' ),
					'default' => 'yes',
				),
				'cardmember'     => array(
					'title'       => __( 'Card Members(registered in KuronekoWebCollect)', 'kupaywc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable payment via saved card', 'kupaywc' ),
					'default'     => 'no',
					'description' => __( 'When this is enabled, members can pay with saved card. Card number will be registered in KuronekoWebCollect, not in your store.Not registered in KuronekoWebCollect when User not member or logout.', 'kupaywc' ),
				),
				'always_save'    => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label'       => __( 'Always registering as a card member', 'kupaywc' ),
					'default'     => 'no',
					'description' => __( 'If this is enabled, the members can not choose the option which they won\'t register the credit card.Not registered in KuronekoWebCollect when User not member or logout.', 'kupaywc' ),
				),
				'operate_id'     => array(
					'title'       => __( 'Operation mode', 'kupaywc' ),
					'type'        => 'select',
					'options'     => array(
						'1Auth'      => __( 'Credit', 'kupaywc' ),
						'1Gathering' => __( 'Credit sales recorded', 'kupaywc' )
					),
					'default'     => '1Auth',
					'description' => __( 'In case of \'Credit\' setting, it need to change to \'Sales recorded\' manually in later. In case of \'Credit sales recorded\' setting, sales will be recorded at the time of purchase.', 'kupaywc' ),
				),
				'howtopay'       => array(
					'title'       => __( 'The number of installments', 'kupaywc' ),
					'type'        => 'select',
					'options'     => array(
						'1' => __( 'Lump-sum payment only', 'kupaywc' ),
						'2' => __( 'Enable installment payments', 'kupaywc' )
					),
					'default'     => '1',
					'description' => __( 'Allow customer to choose the number of installment payments.', 'kupaywc' ),
				),
				'logging'        => array(
					'title'       => __( 'Save the log', 'kupaywc' ),
					'label'       => __( 'Save the log of payment results', 'kupaywc' ),
					'type'        => 'checkbox',
					'description' => __( 'Save the log of payment results to WooCommerce System Status log.', 'kupaywc' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
			)
		);
	}

	/**
	 * Payment form on checkout page.
	 *
	 */
	public function payment_fields() {

		$display_save_payment_method = is_checkout() && $this->cardmember;
		$description                 = $this->get_description() ? $this->get_description() : '';

		ob_start();
		echo '<div id="kuronekopayment-payment-data" name="charge_form">';
		if ( $description ) {
			if ( $this->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'kupaywc' );
				$description = trim( $description );
			}
			echo apply_filters( 'kupaywc_payment_description', wpautop( wp_kses_post( $description ) ), $this->id );
		}
		$this->elements_form();
		echo '<input type="hidden" name="kuronekopayment_token_code" id="kuronekopayment-token-code" value="" />';
		if ( apply_filters( 'kupaywc_display_save_payment_method_checkbox', $display_save_payment_method ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			if ( $this->cardmember ) {
				$this->save_payment_method_checkbox();
			}
		}
		echo '</div>';
		ob_end_flush();
	}

	/**
	 * Renders the KuronkeoPayment elements form.
	 *
	 */
	public function elements_form() {

		$default_fields = array();
		$fields         = array();
		$customer_id    = get_current_user_id();
		$member         = new KUPAYWC_Card_Member( $customer_id );
		//カード登録済みの場合は表示しない
		if ( ! $member->is_card_member() || ! $this->cardmember ) {
			$default_fields['card-number-field'] = '<div class="form-row form-row-wide input-form">
			<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'kupaywc' ) . ' <span class="required">*</span></label>
			<input type="text" id="' . esc_attr( $this->id ) . '-card-number" maxlength="16" placeholder="************1234"/>
		</div>';
			$default_fields['card-owner-field']  = '<div class="form-row form-row-wide input-form">
			<label for="' . esc_attr( $this->id ) . '-card-owner">' . esc_html__( 'Card holder', 'kupaywc' ) . ' <span class="required">*</span></label>
			<input type="text" id="' . esc_attr( $this->id ) . '-card-owner" maxlength="30" placeholder="KURONEKO TARO"/>
		</div>';
			$default_fields['card-expmm-field']  = '<div class="form-row form-row-wide input-form">
			<label>' . esc_html__( 'Expiry (MM/YY)', 'kupaywc' ) . ' <span class="required">*</span></label>
			<input type="text" id="' . esc_attr( $this->id ) . '-card-expmm" maxlength="2" style="width:70px" placeholder="' . esc_attr__( 'MM', 'kupaywc' ) . self::C . esc_attr( $this->id ) . '-card-expyy" maxlength="2" style="width:70px" placeholder="' . esc_attr__( 'YY', 'kupaywc' ) . '" />
		</div>';
		}
		if ( $this->seccd ) {
			$default_fields['card-seccd-field'] = '<div class="form-row form-row-wide input-form">
				<label for="' . esc_attr( $this->id ) . '-card-seccd">' . esc_html__( 'Card code', 'kupaywc' ) . ' <span class="required">*</span></label>
				<input type="text" id="' . esc_attr( $this->id ) . '-card-seccd" maxlength="4" placeholder="1234" style="width:80px"/>
			</div>';
		}
		$howtopay = apply_filters( 'kupaywc_display_howtopay_select', $this->howtopay );
		if ( '1' !== $howtopay ) {
			$paytype_select_field                  = $this->paytype_select_field();
			$default_fields['card-howtopay-field'] = '<div class="form-row form-row-wide input-form">
				<label for="' . esc_attr( $this->id ) . '-card-howtopay">' . esc_html__( 'The number of installments', 'kupaywc' ) . ' <span class="required">*</span></label>
				<span>' . $paytype_select_field . '</span>
			</div>';
		}
		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>
        <fieldset id="<?php echo esc_attr( $this->id ); ?>-card-form" class="wc-payment-form"
                  style="background:transparent;">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
			if ( is_user_logged_in() && $this->cardmember ) :
				$customer_id = get_current_user_id();
				$member = new KUPAYWC_Card_Member( $customer_id );

				if ( $member->is_card_member() && $member->get_auth_key() ) :
					$response = $member->search_card_member();
					if ( '0' === $response['returnCode'] && 0 === (int) $response['cardUnit'] ) {
						$member->delete_auth_key( $member->get_auth_key() );
					} else {
						if ( '0' === $response['returnCode'] && (int) $response['cardUnit'] > 0 ) {
							$cardlast4 = substr( $response['cardData']['maskingCardNo'], - 4 );
						}
						?>
                        <p class="form-row form-row-wide">
                            <input id="<?php esc_attr_e( $this->id ); ?>-card-member-option-saved"
                                name="<?php esc_attr_e( $this->id ); ?>_card_member_option" type="radio"
                                class="input-radio" value="saved" checked="checked"/>
                            <label for="<?php esc_attr_e( $this->id ); ?>-card-member-option-saved"
                                style="display:inline;"><?php echo esc_html__( 'Using the saved credit card.', 'kupaywc' ); ?></label>
                        </p>
                        <p class="form-row form-row-wide">
                            <label for="<?php esc_attr_e( $this->id ); ?>-card-member-cardlast4"
                                style="display:inline;"><?php echo esc_html__( 'Last 4 digits of the saved card number: ', 'kupaywc' ); ?></label>
                            <span id="<?php esc_attr_e( $this->id ); ?>-card-member-cardlast4"><?php esc_attr_e( $cardlast4 ); ?></span>
                        </p>

                        <p class="form-row form-row-wide">
                            <a href="#" id="kuroneko-card-member-change">カード情報の変更はこちら</a>
                        </p>
					<?php } ?>
				<?php endif; ?>

			<?php endif; ?>
			<?php
			foreach ( $fields as $field ) {
				echo $field;
			}
			?>
            <div class="kuronekopayment-errors" role="alert"></div>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
		<?php
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 */
	public function save_payment_method_checkbox() {
		if ( is_user_logged_in() ) {
			$customer_id    = get_current_user_id();
			$member         = new KUPAYWC_Card_Member( $customer_id );
			$is_card_member = $member->is_card_member();
		} else {
			$is_card_member = false;
		}
		if ( ! $is_card_member && is_user_logged_in() ) {
			$save = 'add';
			$text = __( 'Save the card information to your account data. You won\'t need to input the card number from next purchases.', 'kupaywc' );
			if ( $this->always_save ) {
				printf(
					'<input id="%1$s-save-payment-method" name="%1$s_save_payment_method" type="hidden" value="%2$s" />',
					esc_attr( $this->id ),
					esc_attr( $save )
				);
			} else {
				printf(
					'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
					<input id="%1$s-save-payment-method" name="%1$s_save_payment_method" type="hidden" value="delete" style="width:auto;" />
					<input id="%1$s-save-payment-method-checkbox" name="%1$s_save_payment_method" type="checkbox" value="%2$s" style="width:auto;" />
					<label for="%1$s-save-payment-method-checkbox" style="display:inline;">%3$s</label>
				</p>',
					esc_attr( $this->id ),
					esc_attr( $save ),
					esc_html( apply_filters( 'kupaywc_save_to_account_text', $text, $save ) )
				);
			}
		}

	}

	/**
	 * Selection of installments field.
	 *
	 */
	public function paytype_select_field( $paytype = '' ) {
		$field = '<input type="hidden" name="pay_way" value="01" />';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-default" name="' . esc_attr( $this->id ) . '_card_paytype" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : ' ' ) . '>' . esc_html__( 'Lump-sum payment', 'kupaywc' ) . '</option>
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-4535" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'kupaywc' ) . '</option>
				<option value="02"' . ( ( '02' == $paytype ) ? ' selected="selected"' : '' ) . '>2' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="03"' . ( ( '03' == $paytype ) ? ' selected="selected"' : '' ) . '>3' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="05"' . ( ( '05' == $paytype ) ? ' selected="selected"' : '' ) . '>5' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="06"' . ( ( '06' == $paytype ) ? ' selected="selected"' : '' ) . '>6' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="10"' . ( ( '10' == $paytype ) ? ' selected="selected"' : '' ) . '>10' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="12"' . ( ( '12' == $paytype ) ? ' selected="selected"' : '' ) . '>12' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="15"' . ( ( '15' == $paytype ) ? ' selected="selected"' : '' ) . '>15' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="18"' . ( ( '18' == $paytype ) ? ' selected="selected"' : '' ) . '>18' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="20"' . ( ( '20' == $paytype ) ? ' selected="selected"' : '' ) . '>20' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="24"' . ( ( '24' == $paytype ) ? ' selected="selected"' : '' ) . '>24' . esc_html__( '-installments', 'kupaywc' ) . '</option>';
		$field .= '
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-37" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'kupaywc' ) . '</option>
				<option value="03"' . ( ( '03' == $paytype ) ? ' selected="selected"' : '' ) . '>3' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="05"' . ( ( '05' == $paytype ) ? ' selected="selected"' : '' ) . '>5' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="06"' . ( ( '06' == $paytype ) ? ' selected="selected"' : '' ) . '>6' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="10"' . ( ( '10' == $paytype ) ? ' selected="selected"' : '' ) . '>10' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="12"' . ( ( '12' == $paytype ) ? ' selected="selected"' : '' ) . '>12' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="15"' . ( ( '15' == $paytype ) ? ' selected="selected"' : '' ) . '>15' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="18"' . ( ( '18' == $paytype ) ? ' selected="selected"' : '' ) . '>18' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="20"' . ( ( '20' == $paytype ) ? ' selected="selected"' : '' ) . '>20' . esc_html__( '-installments', 'kupaywc' ) . '</option>
				<option value="24"' . ( ( '24' == $paytype ) ? ' selected="selected"' : '' ) . '>24' . esc_html__( '-installments', 'kupaywc' ) . '</option>';

		$field .= '
			</select>';
		$field .= '
			<select id="' . esc_attr( $this->id ) . '-card-paytype-36" name="' . esc_attr( $this->id ) . '_card_paytype" style="display:none;" disabled="disabled" >
				<option value="01"' . ( ( '01' == $paytype ) ? ' selected="selected"' : '' ) . '>' . esc_html__( 'Lump-sum payment', 'kupaywc' ) . '</option>';

		$field .= '
			</select>';

		return $field;
	}

	/**
	 * Outputs scripts.
	 *トークン発行
	 */
	public function payment_scripts() {

		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}
		if ( 'no' === $this->enabled || empty( $this->trader_code ) || empty( $this->access_key ) ) {
			return;
		}
		$api_token_url = KUPAYWC_SLN_Connection::api_token_url();
		wp_enqueue_script( 'kuroneko_tokenjs', $api_token_url, false, false, true );
		wp_register_style( 'kuronekopayment_styles', plugins_url( 'assets/css/kupaywc.css', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION );
		wp_enqueue_style( 'kuronekopayment_styles' );

		wp_register_script( 'kuronekopayment_script', plugins_url( 'assets/js/kupaywc-payment.js', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION, true );

		$kuronekopayment_params = $this->set_kuronekopayment_params();

		wp_localize_script( 'kuronekopayment_script', 'kuronekopayment_params', apply_filters( 'kuronekopayment_params', $kuronekopayment_params ) );
		wp_enqueue_script( 'kuronekopayment_script' );
	}

	public function authDivCode() {
		$secure3d   = $this->three_d_secure;
		$seccd_code = $this->seccd;
		if ( $secure3d ) {
			if ( $seccd_code ) {
				return '3';
			} else {
				return '1';
			}
		} else {
			return '2';
		}
	}

	/**
	 * json テキスト出力.
	 * @fook   set_kuronekopayment_params
	 * @return array $kuronekopayment_params
	 */
	public function set_kuronekopayment_params() {
		$kuronekopayment_params                = array();
		$kuronekopayment_params['trader_code'] = $this->trader_code;
		$kuronekopayment_params['ajax_url']    = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$kuronekopayment_params['nonce']       = array(
			'card_member' => wp_create_nonce( 'kupaywc-get_card_member' ),
		);
		$kuronekopayment_params['auth_div']    = $this->authDivCode();
		$kuronekopayment_params['check_sum']   = hash( 'sha256', $this->access_key . $this->authDivCode() );

		if ( is_user_logged_in() && $this->cardmember ) {
			$member_id = get_current_user_id();
			$member    = new KUPAYWC_Card_Member( $member_id );
			if ( $member->get_auth_key() ) {
				$auth_key                         = $member->get_auth_key();
				$SLN                              = new KUPAYWC_SLN_Connection();
				$params                           = array();
				$param_list                       = array();
				$param_list['function_div']       = 'A03';
				$param_list['trader_code']        = $this->trader_code;
				$param_list['member_id']          = $member_id;
				$param_list['authentication_key'] = $auth_key;
				$param_list['check_sum']          = hash( 'sha256', $member_id . $auth_key . $this->access_key );
				$params['param_list']             = $param_list;
				$params['send_url']               = $SLN->send_url_member();
				$response_data                    = $SLN->connection( $params );

				if ( ! isset( $response_data['returnCode'] ) || '1' === $response_data['returnCode'] ) {
					// エラー時
					$localized_message = '';
					if ( isset( $response_data['errorCode'] ) ) {
						$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
						if ( ! empty( $error_message ) ) {
							$localized_message .= $error_message . '<br />';
						}
						$response_data['errorCode'] = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
					}
					if ( isset( $response_data['creditErrorCode'] ) ) {
						$error_message = KUPAYWCCodes::errorLabel( $response_data['creditErrorCode'] );
						if ( ! empty( $error_message ) ) {
							$localized_message .= $error_message . '<br />';
						}
						$response_data['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_data['creditErrorCode'] );
					}

					if ( empty( $localized_message ) ) {
						$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
					}
					throw new KUPAYWC_Exception( print_r( $response_data, true ), $localized_message );
				}
				if ( isset( $response_data['cardData']['lastCreditDate'] ) ) {
					$kuronekopayment_params['card_no']          = $response_data['cardData']['maskingCardNo'];
					$kuronekopayment_params['card_owner']       = $response_data['cardData']['cardOwner'];
					$kuronekopayment_params['card_exp']         = $response_data['cardData']['maskingCardNo'];
					$kuronekopayment_params['last_credit_date'] = $response_data['cardData']['lastCreditDate'];
					$kuronekopayment_params['card_key']         = $response_data['cardData']['cardKey'];
				}
				$enable_quick = 'saved';
			} else {
				$auth_key     = $member->make_auth_key();
				$enable_quick = 'add';
			}
			$kuronekopayment_params['member_id'] = $member_id;
			$kuronekopayment_params['auth_key']  = $auth_key;
			WC()->session->set( 'member_id', $member_id );
			WC()->session->set( 'auth_key', $auth_key );
			$kuronekopayment_params['check_member_sum'] = hash( 'sha256', $member_id . $auth_key . $this->access_key . $this->authDivCode() );

		} else {
			// お預かり機能のデータ
			$enable_quick = 'none';
		}
		$kuronekopayment_params['enable_quick'] = $enable_quick;

		return $kuronekopayment_params;
	}

	/**
	 * Process the payment.
	 *決済処理
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {


		if ( is_user_logged_in() && $this->cardmember ) {
			$member_id = get_current_user_id();
			$member    = new KUPAYWC_Card_Member( $member_id );

			if ( ! ( $member->get_member_id() && $member->get_auth_key() ) ) {

				if ( 'delete' === wc_clean( wp_unslash( $_POST['kuronekopayment_save_payment_method'] ) ) ) {
					$member->delete_auth_key( $member->get_auth_key() );
				} else {
					if ( WC()->session->get( 'member_id' ) && WC()->session->get( 'auth_key' ) ) {
						$member->set_member_id( WC()->session->get( 'member_id' ) );
						$member->set_auth_key( WC()->session->get( 'auth_key' ) );
					}
				}
			}
		}

		$order = wc_get_order( $order_id );

		$pay_way = 1;
		if ( '2' === $this->howtopay ) {
			$pay_way = isset( $_POST['pay_way'] ) ? wc_clean( $_POST['pay_way'] ) : 1;
		}
		$three_d_param = uniqid( mt_rand() );

		try {
			if ( $this->three_d_secure && $order->get_total() > 0 ) {
				// 3Dセキュア
				$trader_ec_url = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_kuronekopayment&dkey=' . $three_d_param ) );
			}
			$SLN                            = new KUPAYWC_SLN_Connection();
			$params                         = array();
			$param_list                     = array();
			$param_list['function_div']     = 'A08';
			$param_list['trader_code']      = $this->trader_code;
			$param_list['device_div']       = wp_is_mobile() ? 1 : 2;
			$param_list['order_no']         = $order_id;
			$param_list['settle_price']     = $order->get_total();
			$param_list['buyer_name_kanji'] = $order->get_billing_last_name() . $order->get_billing_first_name();
			$param_list['buyer_tel']        = $order->get_billing_phone();
			$param_list['buyer_email']      = $order->get_billing_email();
			$param_list['pay_way']          = $pay_way;
			$param_list['trader_ec_url']    = isset( $trader_ec_url ) ? esc_url( $trader_ec_url ) : '';
			$param_list['ec_cart_code']     = 'woocommerce';
			$token                          = ( isset( $_POST['kuronekopayment_token_code'] ) ) ? wc_clean( wp_unslash( $_POST['kuronekopayment_token_code'] ) ) : '';

			if ( $this->three_d_secure && $order->get_total() > 0 ) {
				// 3Dセキュア
				$x3ds2_account_create_date = '';
				if ( is_user_logged_in() ) {
					$cus = get_user_by( 'id', get_current_user_id() );
					$x3ds2_account_create_date = (new DateTime($cus->user_registered))->format('Ymd');
				}

				$x3ds2_address_match = '02';
				if (
					$order->get_billing_country() === $order->get_shipping_country()
					&& $order->get_billing_state() === $order->get_shipping_state()
					&& $order->get_billing_city() === $order->get_shipping_city()
					&& $order->get_billing_address_1() === $order->get_shipping_address_1()
					&& $order->get_billing_address_2() === $order->get_shipping_address_2()
				) {
					$x3ds2_address_match = '01';
				}

				if ( $x3ds2_account_create_date ) {
					$param_list['x3ds2_account_create_date'] = $x3ds2_account_create_date;
				}
				$param_list['x3ds2_address_match'] = $x3ds2_address_match;
				if ( 'JP' === $order->get_billing_country() ) {
					if ( $order->get_billing_city() ) {
						$param_list['x3ds2_bill_address_city'] = $this->convert_kana( $order->get_billing_city() );
					}
					$param_list['x3ds2_bill_address_country'] = '392';
					if ( $order->get_billing_address_1() ) {
						$param_list['x3ds2_bill_address_line1'] = $this->convert_kana( $order->get_billing_address_1() );
					}
					if ( $order->get_billing_address_2() ) {
						$param_list['x3ds2_bill_address_line2'] = $this->convert_kana( $order->get_billing_address_2() );
					}
					if ( $order->get_billing_postcode() ) {
						$param_list['x3ds2_bill_address_post_code'] = str_replace( '-', '', $order->get_billing_postcode() );
					}
					if ( $order->get_billing_state() ) {
						$param_list['x3ds2_bill_address_state'] = str_replace( 'JP', '', $order->get_billing_state() );
					}
				}
				$param_list['x3ds2_email_address'] = $order->get_billing_email();

				if ( 'JP' === $order->get_billing_country() ) {
					$tel = str_replace( '-', '', $order->get_billing_phone() );
					if ( preg_match( '/^0[7-9]0[0-9]{8}$/', $tel ) ) {
						$param_list['x3ds2_mobile_phone_cc']         = '81';
						$param_list['x3ds2_mobile_phone_subscriber'] = substr( $tel, 1 );
					} else {
						$param_list['x3ds2_home_phone_cc']         = '81';
						$param_list['x3ds2_home_phone_subscriber'] = substr( $tel, 1 );
					}
				}

				if ( 'JP' === $order->get_shipping_country() ) {
					if ( $order->get_shipping_city() ) {
						$param_list['x3ds2_ship_address_city'] = $this->convert_kana( $order->get_shipping_city() );
					}
					$param_list['x3ds2_ship_address_country'] = '392';
					if ( $order->get_shipping_address_1() ) {
						$param_list['x3ds2_ship_address_line1'] = $this->convert_kana( $order->get_shipping_address_1() );
					}
					if ( $order->get_shipping_address_2() ) {
						$param_list['x3ds2_ship_address_line2'] = $this->convert_kana( $order->get_shipping_address_2() );
					}
					if ( $order->get_shipping_postcode() ) {
						$param_list['x3ds2_ship_address_post_code'] = str_replace( '-', '', $order->get_shipping_postcode() );
					}
					if ( $order->get_shipping_state() ) {
						$param_list['x3ds2_ship_address_state'] = str_replace( 'JP', '', $order->get_shipping_state() );
					}
				}

				$param_list['x3ds2_transaction_type'] = '01';
			}

			if ( ! empty( $token ) ) {
				// Refer to token status.
				$param_list['token']  = $token;
				$params['param_list'] = $param_list;
				$params['send_url']   = $SLN->send_url_token();
				$response_token       = $SLN->connection( $params );

				if ( isset( $response_token['threeDToken'] ) && isset( $response_token['threeDAuthHtml'] ) ) {
					$save_data = array(
						'threeDToken' => $response_token['threeDToken'],
					);
					$order->update_meta_data( 'wc_kuronekopayment_dkey_' . $three_d_param, $save_data );
					$order->save();

					$is_block = ( isset( $_POST['is_block'] ) && (boolean) $_POST['is_block'] );
					if ( $is_block ) { // ブロックの決済処理.
						$key = uniqid( mt_rand() );
						$order->update_meta_data( 'wc_kuronekopayment_block_process_payment_transfer_' . $key, $response_token );
						$order->save();

						return array(
							'result'   => 'success',
							'redirect' => str_replace( 'http://', 'https://', add_query_arg( array(
								'wc-api'   => 'wc_kuronekopayment_transfer',
								'order_id' => $order_id,
								'key'      => $key,
							), home_url( '/' ) ) )
						,
						);
					}

					echo $response_token['threeDAuthHtml'];
					exit;
				}
				if ( ! isset( $response_token['returnCode'] ) || '1' === $response_token['returnCode'] ) {
					// エラー時
					$localized_message = '';
					if ( isset( $response_token['errorCode'] ) ) {
						$error_message = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
						if ( ! empty( $error_message ) ) {
							$localized_message .= $error_message . '<br />';
						}
						$response_token['errorCode'] = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
					}
					if ( isset( $response_token['creditErrorCode'] ) ) {
						$error_message = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
						if ( ! empty( $error_message ) ) {
							$localized_message .= $error_message . '<br />';
						}
						$response_token['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
					}

					if ( empty( $localized_message ) ) {
						$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
					}
					throw new KUPAYWC_Exception( print_r( $response_token, true ), $localized_message );
				}


			}

			$flg = false;
			foreach ( $order->get_shipping_methods() as $shipping ) {
				/** @var WC_Order_Item_Shipping $shipping */
				$woocommerce_settings = get_option( 'woocommerce_' . $shipping->get_method_id() . '_' . $shipping->get_instance_id() . '_settings' );
				if ( isset( $woocommerce_settings['kuroneko_delivery_check'] ) && 'yes' === $woocommerce_settings['kuroneko_delivery_check'] ) {
					$flg = true;
				}
			}

			// 配送がヤマト以外の初期決済が「売上確定（実売上）」の場合
			if ( false === $flg && '1Gathering' === $this->operate_id ) {
				$this->shipmentEntry( $order_id, $order_id, $flg );
			}


			if ( $order->get_total() > 0 ) {
				$paytype    = ( isset( $_POST['kuronekopayment_card_paytype'] ) ) ? wp_unslash( $_POST['kuronekopayment_card_paytype'] ) : '01';
				$operate_id = apply_filters( 'kupaywc_card_operate_id', $this->operate_id, $order );

				do_action( 'kupaywc_process_payment', $response_token, $order );
				$this->process_response( $response_token, $order, $flg );

			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( KUPAYWC_Exception $e ) {

			if ( is_user_logged_in() && $this->cardmember ) {
				$member->delete_auth_key( $member->get_auth_key() );
			}

			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );


			do_action( 'kupaywc_process_payment_error', $e, $order );

			$order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Store extra meta data for an order.
	 *
	 */
	public function process_response( $response_token, $order, $flg ) {

		if ( '0' === $response_token['returnCode'] && ! empty( $response_token['crdCResCd'] ) ) {
			$order_id   = $order->get_id();
			$trans_code = $response_token['crdCResCd'];
			$order->update_meta_data( '_kupaywc_trans_code', $trans_code );
			$order->save();

			$SLN                                        = new KUPAYWC_SLN_Connection();
			$params                                     = array();
			$param_list                                 = array();
			$param_list['function_div']                 = 'E04';
			$param_list['trader_code']                  = $this->trader_code;
			$param_list['order_no']                     = $order_id;
			$params['send_url']                         = $SLN->send_trade_info();
			$params['param_list']                       = $param_list;
			$response_data                              = $SLN->connection( $params );
			$response_token['resultData']['statusInfo'] = $response_data['resultData']['statusInfo'];

			KUPAYWC_Payment_Logger::add_log( $response_token, $order_id, $trans_code );

			$order->payment_complete( $trans_code );

			// 配送がヤマト以外の初期決済が「売上確定（実売上）」の場合
			if ( false === $flg && '1Gathering' === $this->operate_id ) {
				$message = __( 'Payment is completed.', 'kupaywc' );
			} else {
				$message = __( 'Credit is completed.', 'kupaywc' );
			}

			$order->add_order_note( $message );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'kupaywc_process_response', $response_token, $order );
	}

	private function shipmentEntry( $order_no, $slip_no, $is_delivery_kuroneko ) {

		$SLN        = new KUPAYWC_SLN_Connection();
		$params     = array();
		$param_list = array();

		$param_list['function_div']          = 'E01';
		$param_list['trader_code']           = $this->trader_code;
		$param_list['order_no']              = $order_no;
		$param_list['slip_no']               = $slip_no;
		$param_list['delivery_service_code'] = $is_delivery_kuroneko ? '00' : '99';
		$params['param_list']                = $param_list;
		$params['send_url']                  = $SLN->send_shipment_entry();
		$response                            = $SLN->connection( $params );

		if ( ! isset( $response['returnCode'] ) || '1' === $response['returnCode'] ) {
			// エラー時
			$localized_message = '';
			if ( isset( $response['errorCode'] ) ) {
				$error_message = KUPAYWCCodes::errorLabel( $response['errorCode'] );
				if ( ! empty( $error_message ) ) {
					$localized_message .= $error_message . '<br />';
				}
				$response['errorCode'] = KUPAYWCCodes::errorLabel( $response['errorCode'] );
			}
			if ( isset( $response['creditErrorCode'] ) ) {
				$error_message = KUPAYWCCodes::errorLabel( $response['creditErrorCode'] );
				if ( ! empty( $error_message ) ) {
					$localized_message .= $error_message . '<br />';
				}
				$response['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response['creditErrorCode'] );
			}

			if ( empty( $localized_message ) ) {
				$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
			}
			throw new KUPAYWC_Exception( print_r( $response, true ), $localized_message );
		}
		$order = wc_get_order( $order_no );
		$order->update_meta_data( '_kupaywc_shipment_entry_end', '1' );
		$order->save();
	}

	/**
	 * Full-width conversion.
	 *
	 * @param string $str String.
	 * @param int    $length Length.
	 *
	 * @return string
	 */
	private function convert_kana( $str, $length = 50 ) {
		$str = str_replace( '~', '～', $str );
		$str = str_replace( '～', '～', $str ); // チルダ
		$str = str_replace( '〜', '～', $str ); // 波ダッシュ

		return mb_substr( mb_convert_kana( $str, 'KAS', 'UTF-8' ), 0, $length );
	}
}
