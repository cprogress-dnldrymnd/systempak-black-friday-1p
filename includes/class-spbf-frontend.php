<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer-facing messaging, shortcodes, and assets.
 */
class SPBF_Frontend {

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_product_notice' ), 15 );
		add_shortcode( 'spbf_collection', array( $this, 'shortcode_collection' ) );
		add_action( 'woocommerce_cart_totals_before_shipping', array( $this, 'cart_reminder' ) );
		add_action( 'woocommerce_review_order_before_shipping', array( $this, 'cart_reminder' ) );

		// WPC Variations Radio Buttons — badge on promotional SKUs.
		add_filter( 'woovr_variation_name', array( $this, 'filter_woovr_variation_name' ), 20, 2 );
		add_filter( 'woovr_variation_class', array( $this, 'filter_woovr_variation_class' ), 20, 2 );
		add_filter( 'woovr_data_attributes', array( $this, 'filter_woovr_data_attributes' ), 20, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation' ), 20, 3 );
	}

	public function enqueue_assets() {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		wp_enqueue_style(
			'spbf-frontend',
			SPBF_URL . 'assets/css/frontend.css',
			array(),
			SPBF_VERSION
		);
		if ( is_product() ) {
			wp_enqueue_script(
				'spbf-frontend',
				SPBF_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				SPBF_VERSION,
				true
			);
			wp_localize_script(
				'spbf-frontend',
				'spbfFrontend',
				array(
					'badgeLabel' => __( 'Black Friday', 'systempak-bf-1p' ),
				)
			);
		}
	}

	/**
	 * Small badge markup for variation labels / SKU line.
	 *
	 * @return string
	 */
	public static function get_variation_badge_html() {
		return '<span class="spbf-variation-badge">' . esc_html__( 'Black Friday', 'systempak-bf-1p' ) . '</span>';
	}

	/**
	 * @param string     $name Variation name HTML.
	 * @param WC_Product $variation Variation product.
	 * @return string
	 */
	public function filter_woovr_variation_name( $name, $variation ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! $variation instanceof WC_Product ) {
			return $name;
		}
		if ( ! SPBF_Product::is_qualifying_product( $variation->get_id() ) ) {
			return $name;
		}
		return $name . ' ' . self::get_variation_badge_html();
	}

	/**
	 * @param string     $class Variation wrapper classes.
	 * @param WC_Product $variation Variation product.
	 * @return string
	 */
	public function filter_woovr_variation_class( $class, $variation ) {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! $variation instanceof WC_Product ) {
			return $class;
		}
		if ( SPBF_Product::is_qualifying_product( $variation->get_id() ) ) {
			$class .= ' spbf-has-offer';
		}
		return $class;
	}

	/**
	 * @param array      $attrs Data attributes.
	 * @param WC_Product $variation Variation product.
	 * @return array
	 */
	public function filter_woovr_data_attributes( $attrs, $variation ) {
		if ( ! is_array( $attrs ) ) {
			return $attrs;
		}
		$attrs['spbf'] = 'no';
		if ( SPBF_Promotion::is_active_for_current_user() && $variation instanceof WC_Product && SPBF_Product::is_qualifying_product( $variation->get_id() ) ) {
			$attrs['spbf'] = 'yes';
		}
		return $attrs;
	}

	/**
	 * @param array      $data Variation JSON.
	 * @param WC_Product $product Parent.
	 * @param WC_Product $variation Variation.
	 * @return array
	 */
	public function filter_available_variation( $data, $product, $variation ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$data['spbf_1p_offer'] = false;
		if ( SPBF_Promotion::is_active_for_current_user() && $variation instanceof WC_Product && SPBF_Product::is_qualifying_product( $variation->get_id() ) ) {
			$data['spbf_1p_offer'] = true;
		}
		return $data;
	}

	/**
	 * Product page offer callout.
	 */
	public function render_product_notice() {
		if ( 'yes' !== SPBF_Settings::get( 'show_product_notice', 'yes' ) ) {
			return;
		}
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! SPBF_Product::has_promo_offer( $product->get_id() ) ) {
			return;
		}

		include SPBF_PATH . 'templates/product-notice.php';
	}

	/**
	 * Basket / checkout reminder when promo lines present.
	 */
	public function cart_reminder() {
		static $shown = false;
		if ( $shown || ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		if ( ! SPBF_Restrictions::cart_has_promo_items() ) {
			return;
		}
		$shown = true;

		$per_box = SPBF_Fees::get_per_box();
		$qty     = SPBF_Product::get_promo_qty_in_cart();
		$max     = SPBF_Quantity::get_max();
		?>
		<tr class="spbf-cart-reminder">
			<td colspan="2">
				<div class="spbf-notice spbf-notice--compact">
					<strong class="spbf-notice__title"><?php esc_html_e( 'Black Friday – 1p a Box', 'systempak-bf-1p' ); ?></strong>
					<p class="spbf-notice__copy">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: per-box fee 2: promo qty 3: max 4: destination rule */
								__( 'Promotional delivery £%1$s applies to each Black Friday box (%2$d in basket). Max %3$d per product. %4$s; regional surcharges may still apply.', 'systempak-bf-1p' ),
								wc_format_decimal( $per_box, 2 ),
								$qty,
								$max,
								SPBF_Restrictions::get_destination_rule_text()
							)
						);
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * [spbf_collection]
	 *
	 * @param array $atts Shortcode atts.
	 * @return string
	 */
	public function shortcode_collection( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'columns' => 4,
			),
			$atts,
			'spbf_collection'
		);

		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return '<div class="spbf-notice">' . esc_html__( 'Black Friday collection is hidden because the promotion is not active for this viewer. (Admins see this message; customers see nothing.)', 'systempak-bf-1p' ) . '</div>';
			}
			return '';
		}

		$include_private = SPBF_Promotion::should_include_private_products();
		$product_ids     = SPBF_Product::get_qualifying_product_ids( $include_private );
		$columns         = max( 1, absint( $atts['columns'] ) );

		if ( $include_private ) {
			add_filter( 'woocommerce_shortcode_products_query', array( $this, 'allow_private_in_products_shortcode' ), 20, 3 );
		}

		$this->begin_collection_loop();

		ob_start();
		include SPBF_PATH . 'templates/collection.php';
		$html = ob_get_clean();

		$this->end_collection_loop();

		if ( $include_private ) {
			remove_filter( 'woocommerce_shortcode_products_query', array( $this, 'allow_private_in_products_shortcode' ), 20 );
		}

		return $html;
	}

	/**
	 * Swap Free Sample for the normal loop Add to cart / Select options button.
	 */
	private function begin_collection_loop() {
		if ( function_exists( 'custom_button_below_listing' ) ) {
			remove_action( 'woocommerce_after_shop_loop_item', 'custom_button_below_listing', 15 );
		}
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_collection_add_to_cart' ), 15 );
	}

	/**
	 * Restore theme Free Sample button after the collection shortcode.
	 */
	private function end_collection_loop() {
		remove_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_collection_add_to_cart' ), 15 );
		if ( function_exists( 'custom_button_below_listing' ) ) {
			add_action( 'woocommerce_after_shop_loop_item', 'custom_button_below_listing', 15 );
		}
	}

	/**
	 * Normal WooCommerce loop CTA for collection cards.
	 */
	public function render_collection_add_to_cart() {
		echo '<div class="spbf-collection__atc">';
		woocommerce_template_loop_add_to_cart();
		echo '</div>';
	}

	/**
	 * Let WooCommerce [products] shortcode render private items while admin-testing.
	 *
	 * @param array  $query_args Query args.
	 * @param array  $attributes Shortcode attributes.
	 * @param string $type Shortcode type.
	 * @return array
	 */
	public function allow_private_in_products_shortcode( $query_args, $attributes = array(), $type = '' ) {
		$query_args['post_status'] = array( 'publish', 'private' );

		// Private/test SKUs are often catalog-hidden; keep ID-based results visible for admins.
		if ( ! empty( $query_args['tax_query'] ) && is_array( $query_args['tax_query'] ) ) {
			foreach ( $query_args['tax_query'] as $key => $tax ) {
				if ( ! is_array( $tax ) ) {
					continue;
				}
				if ( isset( $tax['taxonomy'] ) && 'product_visibility' === $tax['taxonomy'] ) {
					unset( $query_args['tax_query'][ $key ] );
				}
			}
			$query_args['tax_query'] = array_values( $query_args['tax_query'] );
		}

		return $query_args;
	}
}
