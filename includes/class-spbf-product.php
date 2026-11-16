<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product qualification:
 * - Simple products: Black Friday 1p Offer category
 * - Variable products: category required; all variations by default, or specific via product meta box
 */
class SPBF_Product {

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 20 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_meta_box' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_admin_assets' ) );
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
	 * @return int Category term ID or 0.
	 */
	public static function get_category_term_id() {
		$term = get_term_by( 'slug', SPBF_CATEGORY_SLUG, 'product_cat' );
		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}

	/**
	 * @param int $product_id Parent product ID.
	 * @return bool
	 */
	public static function parent_in_offer_category( $product_id ) {
		return has_term( SPBF_CATEGORY_SLUG, 'product_cat', absint( $product_id ) );
	}

	/**
	 * Variation inclusion mode for a variable parent: all|specific.
	 * Defaults to all; legacy accordion flags with a partial set infer specific.
	 *
	 * @param int $product_id Parent product ID.
	 * @return string
	 */
	public static function get_variation_mode( $product_id ) {
		$product_id = absint( $product_id );
		$mode       = get_post_meta( $product_id, SPBF_VARIATION_MODE_META, true );
		if ( 'all' === $mode || 'specific' === $mode ) {
			return $mode;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return 'all';
		}

		$children = $product->get_children();
		if ( empty( $children ) ) {
			return 'all';
		}

		$yes = 0;
		foreach ( $children as $child_id ) {
			if ( 'yes' === get_post_meta( $child_id, SPBF_PRODUCT_META, true ) ) {
				++$yes;
			}
		}

		if ( $yes > 0 && $yes < count( $children ) ) {
			return 'specific';
		}

		return 'all';
	}

	/**
	 * Whether this exact product/variation ID is a promotional SKU.
	 * Variable parents are never cart/price SKUs — use has_promo_offer() instead.
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
			$parent_id = $product->get_parent_id();
			if ( ! $parent_id || ! self::parent_in_offer_category( $parent_id ) ) {
				return false;
			}

			if ( 'specific' === self::get_variation_mode( $parent_id ) ) {
				return 'yes' === get_post_meta( $product_id, SPBF_PRODUCT_META, true );
			}

			return true;
		}

		if ( $product->is_type( 'variable' ) ) {
			return false;
		}

		return self::parent_in_offer_category( $product_id );
	}

	/**
	 * Whether a product page / collection card should show as part of the offer.
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
			if ( ! self::parent_in_offer_category( $product_id ) ) {
				return false;
			}
			if ( 'specific' !== self::get_variation_mode( $product_id ) ) {
				return true;
			}
			foreach ( $product->get_children() as $child_id ) {
				if ( 'yes' === get_post_meta( $child_id, SPBF_PRODUCT_META, true ) ) {
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
		if ( ! self::parent_in_offer_category( $product_id ) ) {
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
	 * Parent product IDs for the collection page (BF category only).
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
			if ( self::has_promo_offer( $product_id ) ) {
				$ids[] = (int) $product_id;
			}
		}

		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		return apply_filters( 'spbf_qualifying_product_ids', $ids, $include_private );
	}

	/**
	 * Register the Black Friday variations meta box.
	 */
	public function register_meta_box() {
		add_meta_box(
			'spbf_variation_offer',
			__( 'Black Friday 1p Offer — Variations', 'systempak-bf-1p' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$product = wc_get_product( $post->ID );
		$term_id = self::get_category_term_id();
		$in_cat  = self::parent_in_offer_category( $post->ID );
		$is_var  = $product && $product->is_type( 'variable' );
		$mode    = self::get_variation_mode( $post->ID );

		wp_nonce_field( 'spbf_save_variation_offer', 'spbf_variation_offer_nonce' );
		?>
		<div
			id="spbf-variation-offer"
			class="spbf-variation-offer"
			data-category-term-id="<?php echo esc_attr( (string) $term_id ); ?>"
			data-is-variable="<?php echo $is_var ? '1' : '0'; ?>"
			data-in-category="<?php echo $in_cat ? '1' : '0'; ?>"
		>
			<p class="spbf-variation-offer__gate spbf-variation-offer__gate--type" hidden>
				<?php esc_html_e( 'This box is for variable products only. Switch the product type to Variable product to choose which variations are in the offer.', 'systempak-bf-1p' ); ?>
			</p>
			<p class="spbf-variation-offer__gate spbf-variation-offer__gate--category" hidden>
				<?php esc_html_e( 'Add this product to the “Black Friday 1p Offer” category to configure variation inclusion. By default every variation is included.', 'systempak-bf-1p' ); ?>
			</p>

			<div class="spbf-variation-offer__body" <?php echo ( $is_var && $in_cat ) ? '' : 'hidden'; ?>>
				<p class="spbf-variation-offer__intro">
					<?php esc_html_e( 'This product is in the Black Friday 1p Offer category. Choose whether every variation is included, or only specific ones.', 'systempak-bf-1p' ); ?>
				</p>

				<fieldset class="spbf-variation-offer__modes">
					<legend class="screen-reader-text"><?php esc_html_e( 'Variation inclusion', 'systempak-bf-1p' ); ?></legend>
					<label class="spbf-variation-offer__mode">
						<input type="radio" name="spbf_variation_mode" value="all" <?php checked( $mode, 'all' ); ?> />
						<span><?php esc_html_e( 'All variations', 'systempak-bf-1p' ); ?></span>
					</label>
					<label class="spbf-variation-offer__mode">
						<input type="radio" name="spbf_variation_mode" value="specific" <?php checked( $mode, 'specific' ); ?> />
						<span><?php esc_html_e( 'Specific variations', 'systempak-bf-1p' ); ?></span>
					</label>
				</fieldset>

				<div class="spbf-variation-offer__list-wrap" <?php echo ( 'specific' === $mode ) ? '' : 'hidden'; ?>>
					<?php if ( ! $is_var || ! $product->get_children() ) : ?>
						<p class="description"><?php esc_html_e( 'No variations found yet. Create variations first, then return here to select them.', 'systempak-bf-1p' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Tick the variations that should be in the Black Friday offer. Unticked variations keep normal / tier pricing.', 'systempak-bf-1p' ); ?></p>
						<ul class="spbf-variation-offer__list">
							<?php foreach ( $product->get_children() as $variation_id ) : ?>
								<?php
								$variation = wc_get_product( $variation_id );
								if ( ! $variation ) {
									continue;
								}
								$label   = $variation->get_name();
								$sku     = $variation->get_sku();
								$checked = ( 'yes' === get_post_meta( $variation_id, SPBF_PRODUCT_META, true ) );
								?>
								<li>
									<label>
										<input type="checkbox" name="spbf_variation_ids[]" value="<?php echo esc_attr( (string) $variation_id ); ?>" <?php checked( $checked ); ?> />
										<span class="spbf-variation-offer__name"><?php echo esc_html( $label ); ?></span>
										<span class="spbf-variation-offer__sku"><?php echo $sku ? esc_html( $sku ) : esc_html__( '(no SKU)', 'systempak-bf-1p' ); ?></span>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int $product_id Product ID.
	 */
	public function save_meta_box( $product_id ) {
		if ( ! isset( $_POST['spbf_variation_offer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbf_variation_offer_nonce'] ) ), 'spbf_save_variation_offer' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		// Category terms may already be saved on this request; fall back to posted checklist.
		$in_category = self::parent_in_offer_category( $product_id );
		if ( ! $in_category && ! empty( $_POST['tax_input']['product_cat'] ) && is_array( $_POST['tax_input']['product_cat'] ) ) {
			$term_id = self::get_category_term_id();
			$posted  = array_map( 'absint', wp_unslash( $_POST['tax_input']['product_cat'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$in_category = $term_id && in_array( $term_id, $posted, true );
		}

		if ( ! $in_category ) {
			return;
		}

		$mode = ( isset( $_POST['spbf_variation_mode'] ) && 'specific' === $_POST['spbf_variation_mode'] ) ? 'specific' : 'all';
		update_post_meta( $product_id, SPBF_VARIATION_MODE_META, $mode );

		$selected = array();
		if ( ! empty( $_POST['spbf_variation_ids'] ) && is_array( $_POST['spbf_variation_ids'] ) ) {
			$selected = array_map( 'absint', wp_unslash( $_POST['spbf_variation_ids'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		foreach ( $product->get_children() as $variation_id ) {
			$variation_id = (int) $variation_id;
			if ( 'specific' === $mode ) {
				update_post_meta( $variation_id, SPBF_PRODUCT_META, in_array( $variation_id, $selected, true ) ? 'yes' : 'no' );
			} else {
				delete_post_meta( $variation_id, SPBF_PRODUCT_META );
			}
		}
	}

	/**
	 * Assets for the product editor meta box.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_product_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'spbf-admin',
			SPBF_URL . 'assets/css/admin.css',
			array(),
			SPBF_VERSION
		);
		wp_enqueue_script(
			'spbf-product-admin',
			SPBF_URL . 'assets/js/product-admin.js',
			array(),
			SPBF_VERSION,
			true
		);
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
