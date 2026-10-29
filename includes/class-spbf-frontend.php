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

		$qualifies = SPBF_Product::is_qualifying_product( $product->get_id() );
		if ( ! $qualifies && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				if ( SPBF_Product::is_qualifying_product( $child_id ) ) {
					$qualifies = true;
					break;
				}
			}
		}
		if ( ! $qualifies ) {
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
					<strong><?php esc_html_e( 'Black Friday – 1p a Box', 'systempak-bf-1p' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: per-box fee 2: promo qty 3: max */
							__( 'Promotional delivery £%1$s applies to each Black Friday box (%2$d in basket). Max %3$d per product. UK delivery only; regional surcharges may still apply.', 'systempak-bf-1p' ),
							wc_format_decimal( $per_box, 2 ),
							$qty,
							$max
						)
					);
					?>
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

		ob_start();
		include SPBF_PATH . 'templates/collection.php';
		$html = ob_get_clean();

		if ( $include_private ) {
			remove_filter( 'woocommerce_shortcode_products_query', array( $this, 'allow_private_in_products_shortcode' ), 20 );
		}

		return $html;
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
