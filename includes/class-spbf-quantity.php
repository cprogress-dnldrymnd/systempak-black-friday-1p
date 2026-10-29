<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforce max quantity per promotional product (default 4).
 */
class SPBF_Quantity {

	public function hooks() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 5 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_cart_update' ), 20, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_contents' ) );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'limit_quantity_input' ), 20, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'limit_variation_qty' ), 20, 3 );
	}

	/**
	 * @return int
	 */
	public static function get_max() {
		return max( 1, absint( SPBF_Settings::get( 'max_qty_per_product', 4 ) ) );
	}

	/**
	 * @param bool $passed Validation state.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity Quantity being added.
	 * @param int  $variation_id Variation ID.
	 * @param array $variations Variation attributes.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		if ( ! $passed || ! SPBF_Promotion::is_active_for_current_user() ) {
			return $passed;
		}

		$check_id = $variation_id ? (int) $variation_id : (int) $product_id;
		$qualifies = SPBF_Product::is_qualifying_product( $check_id ) || SPBF_Product::is_qualifying_product( (int) $product_id );
		if ( ! $qualifies ) {
			return $passed;
		}

		$max       = self::get_max();
		$in_cart   = SPBF_Product::get_product_qty_in_cart( $check_id );
		$new_total = $in_cart + (int) $quantity;

		if ( $new_total > $max ) {
			/* translators: %d: maximum quantity */
			wc_add_notice(
				sprintf(
					__( 'Black Friday offer limit: you can buy a maximum of %d boxes of this product.', 'systempak-bf-1p' ),
					$max
				),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * @param bool   $passed Validation.
	 * @param string $cart_item_key Key.
	 * @param array  $values Cart item.
	 * @param int    $quantity New qty.
	 * @return bool
	 */
	public function validate_cart_update( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! $passed || ! SPBF_Promotion::is_active_for_current_user() ) {
			return $passed;
		}
		if ( empty( $values['data'] ) || ! $values['data'] instanceof WC_Product ) {
			return $passed;
		}
		if ( ! SPBF_Product::is_qualifying_product( $values['data']->get_id() ) ) {
			return $passed;
		}

		$max = self::get_max();
		if ( (int) $quantity > $max ) {
			wc_add_notice(
				sprintf(
					__( 'Black Friday offer limit: maximum %d boxes per promotional product.', 'systempak-bf-1p' ),
					$max
				),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Checkout / cart page safety net.
	 */
	public function validate_cart_contents() {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! WC()->cart ) {
			return;
		}

		$max   = self::get_max();
		$totals = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! SPBF_Product::is_promo_cart_item( $item ) ) {
				continue;
			}
			$id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'];
			if ( ! isset( $totals[ $id ] ) ) {
				$totals[ $id ] = 0;
			}
			$totals[ $id ] += (int) $item['quantity'];
		}

		foreach ( $totals as $product_id => $qty ) {
			if ( $qty > $max ) {
				$product = wc_get_product( $product_id );
				$name    = $product ? $product->get_name() : '#' . $product_id;
				wc_add_notice(
					sprintf(
						/* translators: 1: product name 2: max qty */
						__( 'Black Friday offer: “%1$s” is limited to %2$d boxes. Please reduce the quantity before checkout.', 'systempak-bf-1p' ),
						$name,
						$max
					),
					'error'
				);
			}
		}
	}

	/**
	 * @param array      $args Qty input args.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public function limit_quantity_input( $args, $product ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! $product instanceof WC_Product ) {
			return $args;
		}
		if ( ! SPBF_Product::is_qualifying_product( $product->get_id() ) ) {
			return $args;
		}

		$max = self::get_max();
		$args['max_value'] = isset( $args['max_value'] ) && $args['max_value'] > 0
			? min( (float) $args['max_value'], $max )
			: $max;

		if ( isset( $args['input_value'] ) && (float) $args['input_value'] > $max ) {
			$args['input_value'] = $max;
		}

		return $args;
	}

	/**
	 * @param array      $data Variation JSON data.
	 * @param WC_Product $product Parent.
	 * @param WC_Product $variation Variation.
	 * @return array
	 */
	public function limit_variation_qty( $data, $product, $variation ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! $variation instanceof WC_Product ) {
			return $data;
		}
		if ( ! SPBF_Product::is_qualifying_product( $variation->get_id() ) && ! SPBF_Product::is_qualifying_product( $product->get_id() ) ) {
			return $data;
		}

		$max = self::get_max();
		if ( empty( $data['max_qty'] ) || (float) $data['max_qty'] > $max ) {
			$data['max_qty'] = $max;
		}
		return $data;
	}
}
