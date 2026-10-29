<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevent coupons / BOGO from discounting Black Friday promotional products or the per-box fee.
 */
class SPBF_Coupons {

	public function hooks() {
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'exclude_promo_product' ), 100, 4 );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( $this, 'block_fee_discounts' ), 100, 5 );

		// Buy One Get One Free — do not let promo SKUs count toward / receive gifts.
		add_filter( 'wc_bogof_should_add_cart_rule', array( $this, 'exclude_from_bogof' ), 100, 3 );
	}

	/**
	 * @param bool       $valid Valid for product.
	 * @param WC_Product $product Product.
	 * @param WC_Coupon  $coupon Coupon.
	 * @param array      $values Cart item values.
	 * @return bool
	 */
	public function exclude_promo_product( $valid, $product, $coupon, $values = array() ) {
		if ( ! $valid || ! SPBF_Promotion::is_active_for_current_user() ) {
			return $valid;
		}
		if ( $product instanceof WC_Product && SPBF_Product::is_qualifying_product( $product->get_id() ) ) {
			return false;
		}
		return $valid;
	}

	/**
	 * Cart/percentage coupons should not reduce fee lines that belong to this promo.
	 * WooCommerce coupons generally do not discount fees, but this is a safety net for
	 * plugins that extend discount_amount to fees.
	 *
	 * @param float     $discount Discount.
	 * @param float     $discounting_amount Amount being discounted.
	 * @param array|null $cart_item Cart item or null for cart-level.
	 * @param bool      $single Single.
	 * @param WC_Coupon $coupon Coupon.
	 * @return float
	 */
	public function block_fee_discounts( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return $discount;
		}

		if ( is_array( $cart_item ) && ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
			if ( SPBF_Product::is_qualifying_product( $cart_item['data']->get_id() ) ) {
				return 0;
			}
		}

		return $discount;
	}

	/**
	 * @param bool           $should Whether to add cart rule.
	 * @param array          $cart_item Cart item.
	 * @param WC_BOGOF_Rule  $rule Rule object.
	 * @return bool
	 */
	public function exclude_from_bogof( $should, $cart_item, $rule = null ) {
		if ( ! $should || ! SPBF_Promotion::is_active_for_current_user() ) {
			return $should;
		}
		if ( is_array( $cart_item ) && ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
			if ( SPBF_Product::is_qualifying_product( $cart_item['data']->get_id() ) ) {
				return false;
			}
		}
		// Product ID style payload from product rules getter.
		if ( is_array( $cart_item ) && ! empty( $cart_item['product_id'] ) ) {
			if ( SPBF_Product::is_qualifying_product( (int) $cart_item['product_id'] ) ) {
				return false;
			}
		}
		return $should;
	}
}
