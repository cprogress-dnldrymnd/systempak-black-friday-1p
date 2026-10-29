<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 */
class SPBF_Plugin {

	/** @var SPBF_Plugin|null */
	private static $instance = null;

	/** @var SPBF_Settings */
	public $settings;

	/** @var SPBF_Product */
	public $product;

	/** @var SPBF_Pricing */
	public $pricing;

	/** @var SPBF_Quantity */
	public $quantity;

	/** @var SPBF_Fees */
	public $fees;

	/** @var SPBF_Restrictions */
	public $restrictions;

	/** @var SPBF_Coupons */
	public $coupons;

	/** @var SPBF_Frontend */
	public $frontend;

	/**
	 * @return SPBF_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		$this->settings     = new SPBF_Settings();
		$this->product      = new SPBF_Product();
		$this->pricing      = new SPBF_Pricing();
		$this->quantity     = new SPBF_Quantity();
		$this->fees         = new SPBF_Fees();
		$this->restrictions = new SPBF_Restrictions();
		$this->coupons      = new SPBF_Coupons();
		$this->frontend     = new SPBF_Frontend();

		$this->settings->hooks();
		$this->product->hooks();
		$this->pricing->hooks();
		$this->quantity->hooks();
		$this->fees->hooks();
		$this->restrictions->hooks();
		$this->coupons->hooks();
		$this->frontend->hooks();

		add_action( 'init', array( $this, 'maybe_ensure_category' ), 20 );
	}

	/**
	 * Create the product category once terms are available.
	 */
	public function maybe_ensure_category() {
		SPBF_Product::ensure_category();
	}

	public static function activate() {
		$existing = get_option( SPBF_OPTION_KEY );
		if ( false === $existing ) {
			add_option( SPBF_OPTION_KEY, SPBF_Settings::defaults() );
		}
		// Category may not exist yet during activation — created on init.
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
