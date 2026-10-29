<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compulsory £10.95 per promotional box — cart fee, independent of shipping methods.
 * Sits alongside Flexible Shipping / Conditional Extra Fees / pallet rules.
 */
class SPBF_Fees {

	const FEE_ID = 'spbf_black_friday_delivery';

	public function hooks() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_promotional_delivery_fee' ), 20 );
	}

	/**
	 * @return float
	 */
	public static function get_per_box() {
		return (float) wc_format_decimal( SPBF_Settings::get( 'delivery_per_box', '10.95' ) );
	}

	/**
	 * @param WC_Cart $cart Cart.
	 */
	public function add_promotional_delivery_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$qty = SPBF_Product::get_promo_qty_in_cart( $cart );
		if ( $qty < 1 ) {
			return;
		}

		$per_box = self::get_per_box();
		$amount  = $per_box * $qty;
		$base_label = SPBF_Settings::get( 'fee_label', __( 'Black Friday delivery (per box)', 'systempak-bf-1p' ) );
		$label      = sprintf(
			/* translators: 1: fee base label 2: qty 3: plain per-box amount */
			__( '%1$s — %2$d × £%3$s', 'systempak-bf-1p' ),
			$base_label,
			$qty,
			wc_format_decimal( $per_box, 2 )
		);

		$taxable   = ( 'yes' === SPBF_Settings::get( 'fee_taxable', 'yes' ) );
		$tax_class = SPBF_Settings::get( 'fee_tax_class', '' );

		$cart->add_fee( $label, $amount, $taxable, $tax_class );
	}
}
