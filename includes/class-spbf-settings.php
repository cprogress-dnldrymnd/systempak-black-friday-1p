<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings storage and admin UI.
 */
class SPBF_Settings {

	/**
	 * Default options. Visibility defaults to admin_only so customers never see the offer.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'visibility'           => 'admin_only', // off | admin_only | live
			'start_datetime'       => '2026-11-27 00:00:00',
			'end_datetime'         => '2026-11-30 17:00:00',
			'timezone'             => 'Europe/London',
			'promo_price'          => '0.01',
			'delivery_per_box'     => '10.95',
			'max_qty_per_product'  => 4,
			'fee_taxable'          => 'yes',
			'fee_tax_class'        => '',
			'fee_label'            => 'Black Friday delivery (per box)',
			'collection_page_id'   => 0,
			'show_product_notice'  => 'yes',
			'show_banner'          => 'yes',
			'allowed_countries'    => array( 'GB' ),
		);
	}

	/**
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( SPBF_OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Optional default override.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Register WooCommerce submenu and settings.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_status_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SPBF_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'spbf-settings' ) ) {
			return;
		}
		wp_enqueue_style(
			'spbf-admin',
			SPBF_URL . 'assets/css/admin.css',
			array(),
			SPBF_VERSION
		);
	}

	/**
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings = admin_url( 'admin.php?page=spbf-settings' );
		$docs     = admin_url( 'admin.php?page=spbf-settings&tab=docs' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $docs ) . '">' . esc_html__( 'How to use', 'systempak-bf-1p' ) . '</a>',
			'<a href="' . esc_url( $settings ) . '">' . esc_html__( 'Settings', 'systempak-bf-1p' ) . '</a>'
		);
		return $links;
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Black Friday 1p Offer', 'systempak-bf-1p' ),
			__( 'Black Friday 1p', 'systempak-bf-1p' ),
			'manage_woocommerce',
			'spbf-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( 'spbf_settings_group', SPBF_OPTION_KEY, array( $this, 'sanitize' ) );
	}

	/**
	 * @param array $input Raw POST.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$out      = self::get_all();

		if ( ! is_array( $input ) ) {
			return $out;
		}

		$visibility = isset( $input['visibility'] ) ? sanitize_key( $input['visibility'] ) : 'admin_only';
		if ( ! in_array( $visibility, array( 'off', 'admin_only', 'live' ), true ) ) {
			$visibility = 'admin_only';
		}
		$out['visibility'] = $visibility;

		$out['start_datetime'] = isset( $input['start_datetime'] ) ? sanitize_text_field( $input['start_datetime'] ) : $defaults['start_datetime'];
		$out['end_datetime']   = isset( $input['end_datetime'] ) ? sanitize_text_field( $input['end_datetime'] ) : $defaults['end_datetime'];
		$out['timezone']       = isset( $input['timezone'] ) ? sanitize_text_field( $input['timezone'] ) : $defaults['timezone'];

		$out['promo_price']         = isset( $input['promo_price'] ) ? wc_format_decimal( $input['promo_price'] ) : $defaults['promo_price'];
		$out['delivery_per_box']    = isset( $input['delivery_per_box'] ) ? wc_format_decimal( $input['delivery_per_box'] ) : $defaults['delivery_per_box'];
		$out['max_qty_per_product'] = isset( $input['max_qty_per_product'] ) ? max( 1, absint( $input['max_qty_per_product'] ) ) : 4;

		$out['fee_taxable']   = ( ! empty( $input['fee_taxable'] ) && 'yes' === $input['fee_taxable'] ) ? 'yes' : 'no';
		$out['fee_tax_class'] = isset( $input['fee_tax_class'] ) ? sanitize_text_field( $input['fee_tax_class'] ) : '';
		$out['fee_label']     = isset( $input['fee_label'] ) ? sanitize_text_field( $input['fee_label'] ) : $defaults['fee_label'];

		$out['collection_page_id']  = isset( $input['collection_page_id'] ) ? absint( $input['collection_page_id'] ) : 0;
		$out['show_product_notice'] = ( ! empty( $input['show_product_notice'] ) && 'yes' === $input['show_product_notice'] ) ? 'yes' : 'no';
		$out['show_banner']         = ( ! empty( $input['show_banner'] ) && 'yes' === $input['show_banner'] ) ? 'yes' : 'no';

		return $out;
	}

	/**
	 * Persistent admin banner showing live/test/off status.
	 */
	public function admin_status_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( false === strpos( $screen->id, 'spbf' ) && 'plugins' !== $screen->id && 'woocommerce_page_wc-settings' !== $screen->id ) ) {
			// Keep notice light — only on our settings page.
			if ( ! $screen || false === strpos( (string) $screen->id, 'spbf' ) ) {
				return;
			}
		}

		$vis     = SPBF_Settings::get( 'visibility' );
		$active  = SPBF_Promotion::is_active_for_current_user();
		$in_win  = SPBF_Promotion::is_within_schedule();

		if ( 'off' === $vis ) {
			$class = 'notice-warning';
			$msg   = __( 'Black Friday 1p offer is OFF. Customers and admins will see normal pricing.', 'systempak-bf-1p' );
		} elseif ( 'admin_only' === $vis ) {
			$class = 'notice-info';
			$msg   = __( 'Black Friday 1p offer is in ADMIN TESTING ONLY mode. Customers cannot see or use the promotion.', 'systempak-bf-1p' );
			if ( $active ) {
				$msg .= ' ' . __( 'You currently qualify as an admin tester — promo rules are applied for your session.', 'systempak-bf-1p' );
			}
		} else {
			if ( $in_win ) {
				$class = 'notice-success';
				$msg   = __( 'Black Friday 1p offer is LIVE (within scheduled window).', 'systempak-bf-1p' );
			} else {
				$class = 'notice-warning';
				$msg   = __( 'Black Friday 1p offer is set to Live but outside the scheduled window — promotional pricing is inactive.', 'systempak-bf-1p' );
			}
		}

		printf( '<div class="notice %1$s"><p><strong>%2$s</strong> %3$s</p></div>', esc_attr( $class ), esc_html__( 'SystemPAK BF 1p:', 'systempak-bf-1p' ), esc_html( $msg ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'settings', 'docs' ), true ) ) {
			$tab = 'settings';
		}

		$settings_url = admin_url( 'admin.php?page=spbf-settings&tab=settings' );
		$docs_url     = admin_url( 'admin.php?page=spbf-settings&tab=docs' );
		$cat_link     = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
		$visibility   = self::get( 'visibility', 'admin_only' );
		$active       = SPBF_Promotion::is_active_for_current_user();
		?>
		<div class="wrap spbf-wrap">
			<header class="spbf-hero">
				<div>
					<p class="spbf-hero__eyebrow"><?php esc_html_e( 'SystemPAK · WooCommerce promotion', 'systempak-bf-1p' ); ?></p>
					<h1 class="spbf-hero__title"><?php esc_html_e( 'Black Friday 1p a Box', 'systempak-bf-1p' ); ?></h1>
					<p class="spbf-hero__subtitle"><?php esc_html_e( 'Configure pricing, delivery fee, schedule and visibility. Customers stay unaffected until you switch to Live.', 'systempak-bf-1p' ); ?></p>
				</div>
				<div class="spbf-hero__actions">
					<?php if ( $active ) : ?>
						<span class="spbf-pill spbf-pill--ok"><?php esc_html_e( 'Active for you', 'systempak-bf-1p' ); ?></span>
					<?php else : ?>
						<span class="spbf-pill spbf-pill--off"><?php esc_html_e( 'Inactive for you', 'systempak-bf-1p' ); ?></span>
					<?php endif; ?>
					<?php if ( 'admin_only' === $visibility ) : ?>
						<span class="spbf-pill spbf-pill--info"><?php esc_html_e( 'Admin testing', 'systempak-bf-1p' ); ?></span>
					<?php elseif ( 'live' === $visibility ) : ?>
						<span class="spbf-pill spbf-pill--warn"><?php esc_html_e( 'Live mode', 'systempak-bf-1p' ); ?></span>
					<?php else : ?>
						<span class="spbf-pill spbf-pill--off"><?php esc_html_e( 'Off', 'systempak-bf-1p' ); ?></span>
					<?php endif; ?>
					<a class="spbf-btn spbf-btn--ghost" href="<?php echo esc_url( $cat_link ); ?>"><?php esc_html_e( 'Manage category', 'systempak-bf-1p' ); ?></a>
					<a class="spbf-btn spbf-btn--light" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'How to use', 'systempak-bf-1p' ); ?></a>
				</div>
			</header>

			<nav class="spbf-tabs" aria-label="<?php esc_attr_e( 'Black Friday sections', 'systempak-bf-1p' ); ?>">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="<?php echo 'settings' === $tab ? 'is-active' : ''; ?>"><?php esc_html_e( 'Settings', 'systempak-bf-1p' ); ?></a>
				<a href="<?php echo esc_url( $docs_url ); ?>" class="<?php echo 'docs' === $tab ? 'is-active' : ''; ?>"><?php esc_html_e( 'How to use', 'systempak-bf-1p' ); ?></a>
			</nav>

			<?php
			if ( 'docs' === $tab ) {
				include SPBF_PATH . 'templates/admin-docs.php';
			} else {
				$this->render_settings_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Settings form tab.
	 */
	private function render_settings_tab() {
		$s         = self::get_all();
		$active    = SPBF_Promotion::is_active_for_current_user();
		$in_window = SPBF_Promotion::is_within_schedule();
		$now_local = SPBF_Promotion::now_in_timezone()->format( 'Y-m-d H:i:s T' );
		$cat_link  = admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' );
		$docs_url  = admin_url( 'admin.php?page=spbf-settings&tab=docs' );
		$opt       = SPBF_OPTION_KEY;
		?>
		<div class="spbf-status-grid">
			<div class="spbf-stat">
				<p class="spbf-stat__label"><?php esc_html_e( 'Promo timezone now', 'systempak-bf-1p' ); ?></p>
				<p class="spbf-stat__value"><?php echo esc_html( $now_local ); ?></p>
			</div>
			<div class="spbf-stat">
				<p class="spbf-stat__label"><?php esc_html_e( 'Inside schedule window', 'systempak-bf-1p' ); ?></p>
				<p class="spbf-stat__value">
					<?php if ( $in_window ) : ?>
						<span class="spbf-pill spbf-pill--ok"><?php esc_html_e( 'Yes', 'systempak-bf-1p' ); ?></span>
					<?php else : ?>
						<span class="spbf-pill spbf-pill--warn"><?php esc_html_e( 'No', 'systempak-bf-1p' ); ?></span>
					<?php endif; ?>
				</p>
			</div>
			<div class="spbf-stat">
				<p class="spbf-stat__label"><?php esc_html_e( 'Active for your session', 'systempak-bf-1p' ); ?></p>
				<p class="spbf-stat__value">
					<?php if ( $active ) : ?>
						<span class="spbf-pill spbf-pill--ok"><?php esc_html_e( 'Yes — promo rules apply', 'systempak-bf-1p' ); ?></span>
					<?php else : ?>
						<span class="spbf-pill spbf-pill--off"><?php esc_html_e( 'No', 'systempak-bf-1p' ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'spbf_settings_group' ); ?>

			<section class="spbf-card">
				<div class="spbf-card__header">
					<h2><?php esc_html_e( 'Visibility', 'systempak-bf-1p' ); ?></h2>
					<p><?php esc_html_e( 'Keep Admin testing only until staging is signed off. Live still respects the schedule below.', 'systempak-bf-1p' ); ?></p>
				</div>
				<div class="spbf-card__body">
					<div class="spbf-choice-grid">
						<label class="spbf-choice">
							<input type="radio" name="<?php echo esc_attr( $opt ); ?>[visibility]" value="off" <?php checked( $s['visibility'], 'off' ); ?> />
							<span>
								<span class="spbf-choice__title"><?php esc_html_e( 'Off', 'systempak-bf-1p' ); ?></span>
								<span class="spbf-choice__desc"><?php esc_html_e( 'Promotion completely disabled for everyone.', 'systempak-bf-1p' ); ?></span>
							</span>
						</label>
						<label class="spbf-choice">
							<input type="radio" name="<?php echo esc_attr( $opt ); ?>[visibility]" value="admin_only" <?php checked( $s['visibility'], 'admin_only' ); ?> />
							<span>
								<span class="spbf-choice__title"><?php esc_html_e( 'Admin testing only', 'systempak-bf-1p' ); ?></span>
								<span class="spbf-choice__desc"><?php esc_html_e( 'Shop managers / admins only. Safe default — customers never see the offer.', 'systempak-bf-1p' ); ?></span>
							</span>
						</label>
						<label class="spbf-choice">
							<input type="radio" name="<?php echo esc_attr( $opt ); ?>[visibility]" value="live" <?php checked( $s['visibility'], 'live' ); ?> />
							<span>
								<span class="spbf-choice__title"><?php esc_html_e( 'Live', 'systempak-bf-1p' ); ?></span>
								<span class="spbf-choice__desc"><?php esc_html_e( 'Public, but only inside the scheduled start/end window.', 'systempak-bf-1p' ); ?></span>
							</span>
						</label>
					</div>
				</div>
			</section>

			<section class="spbf-card">
				<div class="spbf-card__header">
					<h2><?php esc_html_e( 'Schedule', 'systempak-bf-1p' ); ?></h2>
					<p><?php esc_html_e( 'Used automatically in Live mode. Format: YYYY-MM-DD HH:MM:SS', 'systempak-bf-1p' ); ?></p>
				</div>
				<div class="spbf-card__body">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="spbf_start"><?php esc_html_e( 'Start (local)', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_start" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[start_datetime]" value="<?php echo esc_attr( $s['start_datetime'] ); ?>" placeholder="2026-11-27 00:00:00" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spbf_end"><?php esc_html_e( 'End (local)', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_end" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[end_datetime]" value="<?php echo esc_attr( $s['end_datetime'] ); ?>" placeholder="2026-11-30 17:00:00" />
								<p class="description"><?php esc_html_e( 'Offer ends at this exact time (default Cyber Monday 5:00pm).', 'systempak-bf-1p' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spbf_tz"><?php esc_html_e( 'Timezone', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_tz" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[timezone]" value="<?php echo esc_attr( $s['timezone'] ); ?>" />
							</td>
						</tr>
					</table>
				</div>
			</section>

			<section class="spbf-card">
				<div class="spbf-card__header">
					<h2><?php esc_html_e( 'Pricing & delivery fee', 'systempak-bf-1p' ); ?></h2>
					<p><?php esc_html_e( 'Confirm VAT treatment with SystemPAK before switching Visibility to Live.', 'systempak-bf-1p' ); ?></p>
				</div>
				<div class="spbf-card__body">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="spbf_price"><?php esc_html_e( 'Promotional price', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_price" type="text" class="small-text" name="<?php echo esc_attr( $opt ); ?>[promo_price]" value="<?php echo esc_attr( $s['promo_price'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Per box. Follows store tax settings.', 'systempak-bf-1p' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spbf_fee"><?php esc_html_e( 'Delivery charge per box', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_fee" type="text" class="small-text" name="<?php echo esc_attr( $opt ); ?>[delivery_per_box]" value="<?php echo esc_attr( $s['delivery_per_box'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spbf_fee_label"><?php esc_html_e( 'Fee label', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_fee_label" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[fee_label]" value="<?php echo esc_attr( $s['fee_label'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fee taxable?', 'systempak-bf-1p' ); ?></th>
							<td>
								<label class="spbf-switch-row">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[fee_taxable]" value="yes" <?php checked( $s['fee_taxable'], 'yes' ); ?> />
									<span><?php esc_html_e( 'Charge tax on the promotional delivery fee', 'systempak-bf-1p' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spbf_max"><?php esc_html_e( 'Max qty per product', 'systempak-bf-1p' ); ?></label></th>
							<td>
								<input id="spbf_max" type="number" min="1" class="small-text" name="<?php echo esc_attr( $opt ); ?>[max_qty_per_product]" value="<?php echo esc_attr( $s['max_qty_per_product'] ); ?>" />
							</td>
						</tr>
					</table>
				</div>
			</section>

			<section class="spbf-card">
				<div class="spbf-card__header">
					<h2><?php esc_html_e( 'Storefront', 'systempak-bf-1p' ); ?></h2>
					<p><?php esc_html_e( 'Collection page, product messaging and homepage banner.', 'systempak-bf-1p' ); ?></p>
				</div>
				<div class="spbf-card__body">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Collection page', 'systempak-bf-1p' ); ?></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => $opt . '[collection_page_id]',
										'selected'          => (int) $s['collection_page_id'],
										'show_option_none'  => __( '— Select —', 'systempak-bf-1p' ),
										'option_none_value' => '0',
										'class'             => 'regular-text',
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'Page should contain [spbf_collection]. Used as the banner CTA link.', 'systempak-bf-1p' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Product page notice', 'systempak-bf-1p' ); ?></th>
							<td>
								<label class="spbf-switch-row">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_product_notice]" value="yes" <?php checked( $s['show_product_notice'], 'yes' ); ?> />
									<span><?php esc_html_e( 'Show Black Friday messaging near Add to Basket', 'systempak-bf-1p' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Homepage banner', 'systempak-bf-1p' ); ?></th>
							<td>
								<label class="spbf-switch-row">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_banner]" value="yes" <?php checked( $s['show_banner'], 'yes' ); ?> />
									<span><?php esc_html_e( 'Show homepage banner when the promotion is active for the viewer', 'systempak-bf-1p' ); ?></span>
								</label>
								<p class="description"><code>[spbf_banner]</code> · <code>[spbf_collection]</code></p>
							</td>
						</tr>
					</table>
				</div>
			</section>

			<div class="spbf-actions">
				<?php submit_button( __( 'Save settings', 'systempak-bf-1p' ), 'primary', 'submit', false ); ?>
				<a class="spbf-btn" style="background:#eef2f3;color:#1c4350;border:1px solid #d5dce0;" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Open documentation', 'systempak-bf-1p' ); ?></a>
			</div>
		</form>

		<div class="spbf-callout">
			<div>
				<h2><?php esc_html_e( 'Assign products via category', 'systempak-bf-1p' ); ?></h2>
				<p><?php esc_html_e( 'Add SKUs to the “Black Friday 1p Offer” product category. That is the only way to include a product in the promotion.', 'systempak-bf-1p' ); ?></p>
			</div>
			<div class="spbf-callout__buttons">
				<a class="spbf-btn spbf-btn--primary" href="<?php echo esc_url( $cat_link ); ?>"><?php esc_html_e( 'Open category', 'systempak-bf-1p' ); ?></a>
				<a class="spbf-btn" style="background:#fff;border:1px solid #d5dce0;color:#1c4350;" href="<?php echo esc_url( $docs_url ); ?>"><?php esc_html_e( 'Testing guide', 'systempak-bf-1p' ); ?></a>
			</div>
		</div>
		<?php
	}
}
