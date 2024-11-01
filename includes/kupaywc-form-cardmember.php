<?php
/**
 * Edit cardmember form.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'kupaywc_before_edit_cardmember_form' );
?>
<form name="kuronekopayment_cardmember" class="kupaywc-edit-kuronekocardmember-form" action="" method="post">

	<?php do_action( 'kupaywc_edit_cardmember_form_start' ); ?>

    <fieldset id="kuronekopayment-card-form" class="wc-payment-form" style="background:transparent;">
		<?php if ( ! empty( $cardlast4 ) ) : ?>
            <p class="form-row form-row-wide">
                <label for="kuronekopayment-card-member-cardlast4"
                       style="display:inline;"><?php echo esc_html__( 'Last 4 digits of the saved card number: ', 'kupaywc' ); ?></label>
                <span id="kuronekopayment-card-member-cardlast4"><?php echo esc_html( $cardlast4 ); ?></span>
            </p>
		<?php endif; ?>
        <div class="clear"></div>
    </fieldset>

	<?php do_action( 'kupaywc_edit_cardmember_form' ); ?>
    <p>
		<?php wp_nonce_field( 'kupaywc_edit_cardmember' ); ?>
		<?php if ( ! empty( $cardlast4 ) && $deletable ) : ?>
            <button type="submit" class="woocommerce-Button button" id="delete-cardmember"
                    value="delete"><?php echo esc_html__( 'Delete the saved card member', 'kupaywc' ); ?></button>
		<?php endif; ?>
        <input type="hidden" id="edit-kuronekocardmember-action" name="action" value=""/>
        <input type="hidden" id="kuronekopayment-token-code" name="kuronekopayment_token_code" value=""/>
    </p>

	<?php do_action( 'kupaywc_edit_cardmember_form_end' ); ?>
</form>
<?php do_action( 'kupaywc_after_edit_cardmember_form' ); ?>
