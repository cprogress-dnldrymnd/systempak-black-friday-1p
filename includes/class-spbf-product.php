<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product qualification via the Black Friday 1p Offer category.
 */
class SPBF_Product {

	public function hooks() {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_admin_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
	}

	/**
	 * Ensure the Black Friday product category exists.
	 */
	public static function ensure_category() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}
		if ( ! term_exists( SPBF_CATEGORY_SLUG, 'product_cat' ) ) {
			wp_insert_term(
				'Black Friday 1p Offer',
				'product_cat',
				array(
					'slug'        => SPBF_CATEGORY_SLUG,
					'description' => 'Products included in the SystemPAK Black Friday 1p a Box promotion.',
				)
			);
		}
	}

	/**
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public static function is_qualifying_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		// Variations inherit the parent product's category.
		$parent_id = $product->get_parent_id();
		if ( $parent_id && has_term( SPBF_CATEGORY_SLUG, 'product_cat', $parent_id ) ) {
			return true;
		}

		return has_term( SPBF_CATEGORY_SLUG, 'product_cat', $product_id );
	}

	/**
	 * Whether a cart line is a promotional Black Friday item while the offer is active.
	 *
	 * @param array $cart_item Cart item.
	 * @return bool
	 */
	public static function is_promo_cart_item( $cart_item ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return false;
		}
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		return self::is_qualifying_product( $product->get_id() );
	}

	/**
	 * Count total promotional boxes currently in the cart.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return int
	 */
	public static function get_promo_qty_in_cart( $cart = null ) {
		$cart = $cart instanceof WC_Cart ? $cart : ( WC()->cart ? WC()->cart : null );
		if ( ! $cart ) {
			return 0;
		}
		$total = 0;
		foreach ( $cart->get_cart() as $item ) {
			if ( self::is_promo_cart_item( $item ) ) {
				$total += (int) $item['quantity'];
			}
		}
		return $total;
	}

	/**
	 * Quantity of a specific product already in the cart (promo lines only).
	 *
	 * @param int          $product_id Product/variation ID.
	 * @param string|null  $exclude_key Cart item key to exclude.
	 * @param WC_Cart|null $cart Cart.
	 * @return int
	 */
	public static function get_product_qty_in_cart( $product_id, $exclude_key = null, $cart = null ) {
		$cart = $cart instanceof WC_Cart ? $cart : ( WC()->cart ? WC()->cart : null );
		if ( ! $cart ) {
			return 0;
		}
		$product_id = absint( $product_id );
		$qty        = 0;
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $exclude_key && $key === $exclude_key ) {
				continue;
			}
			if ( ! self::is_promo_cart_item( $item ) ) {
				continue;
			}
			$line_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'];
			if ( $line_id === $product_id ) {
				$qty += (int) $item['quantity'];
			}
		}
		return $qty;
	}

	/**
	 * Product IDs currently assigned to the offer (for collection page).
	 *
	 * @return int[] Parent product IDs.
	 */
	public static function get_qualifying_product_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => SPBF_CATEGORY_SLUG,
					),
				),
			)
		);

		$ids = array_values( array_unique( array_map( 'absint', (array) $ids ) ) );
		return apply_filters( 'spbf_qualifying_product_ids', $ids );
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_admin_column( $columns ) {
		$columns['spbf_1p'] = __( 'BF 1p', 'systempak-bf-1p' );
		return $columns;
	}

	/**
	 * @param string $column Column key.
	 * @param int    $post_id Product ID.
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( 'spbf_1p' !== $column ) {
			return;
		}
		echo self::is_qualifying_product( $post_id ) ? '✓' : '—';
	}
}
