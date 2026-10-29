<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UK-only shipping restriction for baskets containing promotional products.
 */
class SPBF_Restrictions {

	public function hooks() {
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_shipping_country' ), 30 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_country' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'maybe_flag_packages' ), 100, 2 );
	}

	/**
	 * @return string[]
	 */
	public static function allowed_countries() {
		$countries = SPBF_Settings::get( 'allowed_countries', array( 'GB' ) );
		if ( ! is_array( $countries ) || empty( $countries ) ) {
			$countries = array( 'GB' );
		}
		return array_map( 'strtoupper', $countries );
	}

	/**
	 * @return string|null Country code or null if unknown yet.
	 */
	public static function get_customer_shipping_country() {
		if ( ! WC()->customer ) {
			return null;
		}
		$country = WC()->customer->get_shipping_country();
		if ( ! $country ) {
			$country = WC()->customer->get_billing_country();
		}
		return $country ? strtoupper( $country ) : null;
	}

	/**
	 * @return bool
	 */
	public static function cart_has_promo_items() {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( SPBF_Product::is_promo_cart_item( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public static function is_allowed_destination() {
		$country = self::get_customer_shipping_country();
		if ( ! $country ) {
			// No destination chosen yet — don't block browsing; checkout will re-check.
			return true;
		}
		return in_array( $country, self::allowed_countries(), true );
	}

	public function validate_shipping_country() {
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		if ( ! self::cart_has_promo_items() ) {
			return;
		}
		if ( self::is_allowed_destination() ) {
			return;
		}

		wc_add_notice(
			__( 'Black Friday 1p a Box products are available for UK delivery only. Please remove them from your basket or change the shipping destination to the United Kingdom.', 'systempak-bf-1p' ),
			'error'
		);
	}

	public function validate_checkout_country() {
		if ( ! SPBF_Promotion::is_active_for_current_user() || ! self::cart_has_promo_items() ) {
			return;
		}

		$country = '';
		if ( ! empty( $_POST['ship_to_different_address'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$country = isset( $_POST['shipping_country'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			$country = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$country = strtoupper( $country );
		if ( $country && ! in_array( $country, self::allowed_countries(), true ) ) {
			wc_add_notice(
				__( 'Black Friday 1p a Box products can only be shipped to the United Kingdom. Please update your shipping address or remove the promotional products from your basket.', 'systempak-bf-1p' ),
				'error'
			);
		}
	}

	/**
	 * No rate removal — existing shipping stays. Hook kept for future package tagging if needed.
	 *
	 * @param array $rates Rates.
	 * @param array $package Package.
	 * @return array
	 */
	public function maybe_flag_packages( $rates, $package ) {
		return $rates;
	}
}
