<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Force £0.01 pricing for qualifying products while the offer is active.
 * Runs after Tier Pricing Table (priority 99999) so promo wins over quantity tiers.
 */
class SPBF_Pricing {

	public function hooks() {
		// After tier-pricing-table cart service (99999).
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_prices' ), 100000, 1 );

		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_price' ), 1000, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_price' ), 1000, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 1000, 3 );

		// Do not mark as "on sale" — avoids theme “100% OFF” badges and strikethrough regular price.
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'disable_on_sale_flag' ), 1000, 2 );
		add_filter( 'woocommerce_sale_flash', array( $this, 'hide_sale_flash' ), 1000, 3 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 1000, 2 );
		add_filter( 'tiered_pricing_table/catalog_pricing/price_html', array( $this, 'filter_tier_catalog_price_html' ), 1000, 3 );

		// Skip tiered pricing recalculation for promo SKUs.
		add_filter( 'tiered_pricing_table/cart/need_price_recalculation', array( $this, 'skip_tier_pricing' ), 100, 2 );
		add_filter( 'tiered_pricing_table/cart/need_price_recalculation/item', array( $this, 'skip_tier_pricing' ), 100, 2 );

		// Hide the tier pricing table UI while promo is active for this product.
		add_filter( 'tiered_pricing_table/should_render_pricing_table', array( $this, 'maybe_hide_tier_table' ), 100, 3 );

		// Bust variation price transient cache when promo state changes for a viewer.
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_prices_hash' ), 100, 3 );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private function is_active_promo_product( $product ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return false;
		}
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		return SPBF_Product::is_qualifying_product( $product->get_id() );
	}

	/**
	 * @return float
	 */
	public static function get_promo_price() {
		return (float) wc_format_decimal( SPBF_Settings::get( 'promo_price', '0.01' ) );
	}

	/**
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		$price = self::get_promo_price();

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			if ( ! SPBF_Product::is_qualifying_product( $item['data']->get_id() ) ) {
				continue;
			}
			$item['data']->set_price( $price );
			$item['data']->add_meta_data( 'spbf_promo_price_applied', 'yes', true );
		}
	}

	/**
	 * @param mixed      $price Price.
	 * @param WC_Product $product Product.
	 * @return mixed
	 */
	public function filter_price( $price, $product ) {
		// Keep admin product screens on catalogue prices; frontend + AJAX still apply promo.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price;
		}
		if ( ! $this->is_active_promo_product( $product ) ) {
			return $price;
		}
		return self::get_promo_price();
	}

	/**
	 * @param mixed      $price Price.
	 * @param WC_Product $variation Variation.
	 * @param WC_Product $product Parent.
	 * @return mixed
	 */
	public function filter_variation_price( $price, $variation, $product ) {
		return $this->filter_price( $price, $variation );
	}

	/**
	 * @param bool       $on_sale On sale.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function disable_on_sale_flag( $on_sale, $product ) {
		if ( $this->is_active_promo_product( $product ) ) {
			return false;
		}
		return $on_sale;
	}

	/**
	 * @param string     $html Sale flash HTML.
	 * @param WP_Post    $post Post.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function hide_sale_flash( $html, $post, $product ) {
		if ( $this->is_active_promo_product( $product ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * Show a clean £0.01 price with no strikethrough / % OFF treatment.
	 *
	 * @param string     $html Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_price_html( $html, $product ) {
		if ( ! $this->is_active_promo_product( $product ) ) {
			return $html;
		}

		$price = self::get_promo_price();
		return wc_price(
			wc_get_price_to_display(
				$product,
				array(
					'price' => $price,
				)
			)
		) . $product->get_price_suffix( $price );
	}

	/**
	 * Override Tier Pricing catalog price HTML for promo products.
	 *
	 * @param string     $html Tier HTML.
	 * @param string     $default Default HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_tier_catalog_price_html( $html, $default, $product ) {
		if ( $this->is_active_promo_product( $product ) ) {
			return $this->filter_price_html( '', $product );
		}
		return $html;
	}

	/**
	 * @param bool  $need Whether tier pricing should recalculate.
	 * @param array $cart_item Cart item.
	 * @return bool
	 */
	public function skip_tier_pricing( $need, $cart_item ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return $need;
		}
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $need;
		}
		if ( SPBF_Product::is_qualifying_product( $cart_item['data']->get_id() ) ) {
			return false;
		}
		return $need;
	}

	/**
	 * @param bool $should Whether to render the table.
	 * @param int  $parent_product_id Parent product ID.
	 * @param int  $variation_id Variation ID.
	 * @return bool
	 */
	public function maybe_hide_tier_table( $should, $parent_product_id, $variation_id = null ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return $should;
		}
		$check_id = $variation_id ? (int) $variation_id : (int) $parent_product_id;
		if ( SPBF_Product::is_qualifying_product( $check_id ) || SPBF_Product::is_qualifying_product( (int) $parent_product_id ) ) {
			return false;
		}
		return $should;
	}

	/**
	 * @param array      $hash Hash fragments.
	 * @param WC_Product $product Product.
	 * @param bool       $display Display prices including tax.
	 * @return array
	 */
	public function variation_prices_hash( $hash, $product, $display ) {
		if ( SPBF_Promotion::is_active_for_current_user() ) {
			$hash[] = 'spbf-active-' . self::get_promo_price();
		} else {
			$hash[] = 'spbf-inactive';
		}
		return $hash;
	}
}
