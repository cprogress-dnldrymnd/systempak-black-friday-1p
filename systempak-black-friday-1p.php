<?php
/**
 * Plugin Name: SystemPAK Black Friday 1p a Box
 * Description: Black Friday “1p a Box” promotion — £0.01 pricing, max 4 per product, £10.95 per-box promotional delivery, UK-only, coupon exclusion. Defaults to admin-only testing so customers never see it until you go live.
 * Version: 1.0.5
 * Author: SystemPAK
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Text Domain: systempak-bf-1p
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPBF_VERSION', '1.0.5' );
define( 'SPBF_FILE', __FILE__ );
define( 'SPBF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPBF_URL', plugin_dir_url( __FILE__ ) );
define( 'SPBF_OPTION_KEY', 'spbf_settings' );
define( 'SPBF_CATEGORY_SLUG', 'black-friday-1p-offer' );

require_once SPBF_PATH . 'includes/class-spbf-settings.php';
require_once SPBF_PATH . 'includes/class-spbf-promotion.php';
require_once SPBF_PATH . 'includes/class-spbf-product.php';
require_once SPBF_PATH . 'includes/class-spbf-pricing.php';
require_once SPBF_PATH . 'includes/class-spbf-quantity.php';
require_once SPBF_PATH . 'includes/class-spbf-fees.php';
require_once SPBF_PATH . 'includes/class-spbf-restrictions.php';
require_once SPBF_PATH . 'includes/class-spbf-coupons.php';
require_once SPBF_PATH . 'includes/class-spbf-frontend.php';
require_once SPBF_PATH . 'includes/class-spbf-plugin.php';

/**
 * Bootstrap after plugins (and WooCommerce) are loaded.
 */
function spbf_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'SystemPAK Black Friday 1p a Box requires WooCommerce.', 'systempak-bf-1p' ) . '</p></div>';
		} );
		return;
	}

	SPBF_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'spbf_init' );

register_activation_hook( __FILE__, array( 'SPBF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SPBF_Plugin', 'deactivate' ) );
