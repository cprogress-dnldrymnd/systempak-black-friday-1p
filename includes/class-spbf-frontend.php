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
		add_shortcode( 'spbf_banner', array( $this, 'shortcode_banner' ) );
		add_action( 'woocommerce_cart_totals_before_shipping', array( $this, 'cart_reminder' ) );
		add_action( 'woocommerce_review_order_before_shipping', array( $this, 'cart_reminder' ) );
		// Auto-inject on the static front page (Elementor-safe; no builder edit required).
		add_action( 'wp_body_open', array( $this, 'maybe_render_homepage_banner' ), 5 );
	}

	/**
	 * Output banner at top of the site front page when enabled.
	 */
	public function maybe_render_homepage_banner() {
		if ( ! is_front_page() ) {
			return;
		}
		if ( 'yes' !== SPBF_Settings::get( 'show_banner', 'yes' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode returns escaped markup.
		echo $this->shortcode_banner();
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

		ob_start();
		$product_ids = SPBF_Product::get_qualifying_product_ids();
		$columns     = max( 1, absint( $atts['columns'] ) );
		include SPBF_PATH . 'templates/collection.php';
		return ob_get_clean();
	}

	/**
	 * [spbf_banner]
	 *
	 * @return string
	 */
	public function shortcode_banner() {
		if ( ! SPBF_Promotion::should_show_marketing() ) {
			return '';
		}

		$page_id = absint( SPBF_Settings::get( 'collection_page_id', 0 ) );
		$url     = $page_id ? get_permalink( $page_id ) : '';

		ob_start();
		include SPBF_PATH . 'templates/banner.php';
		return ob_get_clean();
	}
}
