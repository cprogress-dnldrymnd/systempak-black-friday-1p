<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product qualification:
 * - Simple products: Black Friday 1p Offer category
 * - Variable products: per-variation checkbox (only selected variations qualify)
 */
class SPBF_Product {

	public function hooks() {
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_help' ), 5, 3 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_checkbox' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_checkbox' ), 10, 2 );
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
	 * Whether this exact product/variation ID is a promotional SKU.
	 * For variable parents this is always false — use has_promo_offer() instead.
	 *
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

		if ( $product->is_type( 'variation' ) ) {
			return 'yes' === get_post_meta( $product_id, SPBF_PRODUCT_META, true );
		}

		// Variable parent is never itself a cart/price SKU for this promo.
		if ( $product->is_type( 'variable' ) ) {
			return false;
		}

		// Simple / other product types: category assignment.
		return has_term( SPBF_CATEGORY_SLUG, 'product_cat', $product_id );
	}

	/**
	 * Whether a product page / collection card should be treated as part of the offer.
	 * True for simples in the category, or variable parents with at least one flagged variation.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public static function has_promo_offer( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		if ( $product->is_type( 'variation' ) ) {
			return self::is_qualifying_product( $product_id );
		}

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				if ( self::is_qualifying_product( $child_id ) ) {
					return true;
				}
			}
			return false;
		}

		return self::is_qualifying_product( $product_id );
	}

	/**
	 * Qualifying variation IDs for a variable product.
	 *
	 * @param int $product_id Parent product ID.
	 * @return int[]
	 */
	public static function get_qualifying_variation_ids( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$ids = array();
		foreach ( $product->get_children() as $child_id ) {
			if ( self::is_qualifying_product( $child_id ) ) {
				$ids[] = (int) $child_id;
			}
		}
		return $ids;
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
	 * Parent product IDs for the collection page.
	 *
	 * @param bool|null $include_private Include private products (defaults from promo testing mode).
	 * @return int[] Parent product IDs.
	 */
	public static function get_qualifying_product_ids( $include_private = null ) {
		if ( null === $include_private ) {
			$include_private = SPBF_Promotion::should_include_private_products();
		}

		$statuses = array( 'publish' );
		if ( $include_private ) {
			$statuses[] = 'private';
		}

		$ids = array();

		// Simple products in the BF category.
		$by_cat = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => $statuses,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
				'tax_query'              => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => SPBF_CATEGORY_SLUG,
					),
				),
			)
		);

		foreach ( (array) $by_cat as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			// Variable parents in the category only appear if they also have flagged variations.
			if ( $product->is_type( 'variable' ) ) {
				if ( self::has_promo_offer( $product_id ) ) {
					$ids[] = (int) $product_id;
				}
				continue;
			}
			$ids[] = (int) $product_id;
		}

		// Variable parents discovered via variation meta (even if parent not in category).
		$variation_ids = get_posts(
			array(
				'post_type'              => 'product_variation',
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_key'               => SPBF_PRODUCT_META,
				'meta_value'             => 'yes',
			)
		);

		foreach ( (array) $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}
			$parent_id = $variation->get_parent_id();
			if ( ! $parent_id ) {
				continue;
			}
			$parent = wc_get_product( $parent_id );
			if ( ! $parent ) {
				continue;
			}
			$status = $parent->get_status();
			if ( ! in_array( $status, $statuses, true ) ) {
				continue;
			}
			$ids[] = (int) $parent_id;
		}

		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		return apply_filters( 'spbf_qualifying_product_ids', $ids, $include_private );
	}

	/**
	 * One-time note near the first variation row is enough; keep markup light.
	 *
	 * @param int     $loop Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation Variation post.
	 */
	public function render_variation_help( $loop, $variation_data, $variation ) {
		if ( 0 !== (int) $loop ) {
			return;
		}
		echo '<p class="form-row form-row-full spbf-variation-help" style="margin:0 0 12px;padding:10px 12px;background:#e8eef0;border-left:3px solid #1c4350;">';
		echo esc_html__( 'Black Friday: tick “Include in Black Friday 1p Offer” on each variation that should be in the promotion. Unticked variations keep normal / tier pricing.', 'systempak-bf-1p' );
		echo '</p>';
	}

	/**
	 * @param int     $loop Loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation Variation post.
	 */
	public function render_variation_checkbox( $loop, $variation_data, $variation ) {
		$checked = get_post_meta( $variation->ID, SPBF_PRODUCT_META, true );
		?>
		<div class="form-row form-row-full options">
			<label>
				<input type="checkbox" class="checkbox" name="<?php echo esc_attr( SPBF_PRODUCT_META . '[' . $loop . ']' ); ?>" <?php checked( $checked, 'yes' ); ?> />
				<?php esc_html_e( 'Include in Black Friday 1p Offer', 'systempak-bf-1p' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * @param int $variation_id Variation ID.
	 * @param int $loop Loop index.
	 */
	public function save_variation_checkbox( $variation_id, $loop ) {
		$value = isset( $_POST[ SPBF_PRODUCT_META ][ $loop ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $variation_id, SPBF_PRODUCT_META, $value );
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
		echo self::has_promo_offer( $post_id ) ? '✓' : '—';
	}
}
