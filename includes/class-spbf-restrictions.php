<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping-country restriction for baskets containing promotional products.
 * Allowed countries are configured in settings (default: GB).
 */
class SPBF_Restrictions {

	/** @var bool */
	private static $removing = false;

	public function hooks() {
		add_action( 'woocommerce_check_cart_items', array( $this, 'maybe_remove_promo_for_country' ), 30 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'maybe_remove_on_checkout_update' ), 20 );
		add_action( 'woocommerce_checkout_process', array( $this, 'maybe_remove_promo_for_country' ), 5 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart_country' ), 25, 5 );
		add_filter( 'woocommerce_package_rates', array( $this, 'maybe_flag_packages' ), 100, 2 );
	}

	/**
	 * @return string[] ISO country codes.
	 */
	public static function allowed_countries() {
		$countries = SPBF_Settings::get( 'allowed_countries', array( 'GB' ) );
		if ( ! is_array( $countries ) || empty( $countries ) ) {
			$countries = array( 'GB' );
		}
		return array_values( array_unique( array_map( 'strtoupper', $countries ) ) );
	}

	/**
	 * Human-readable list of allowed destination names.
	 *
	 * @return string
	 */
	public static function get_allowed_countries_label() {
		$codes = self::allowed_countries();
		$all   = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();
		$names = array();
		foreach ( $codes as $code ) {
			$names[] = isset( $all[ $code ] ) ? $all[ $code ] : $code;
		}
		if ( empty( $names ) ) {
			return __( 'the United Kingdom', 'systempak-bf-1p' );
		}
		if ( 1 === count( $names ) ) {
			return $names[0];
		}
		$last = array_pop( $names );
		return implode( ', ', $names ) . ' ' . __( 'and', 'systempak-bf-1p' ) . ' ' . $last;
	}

	/**
	 * Short customer-facing destination rule, e.g. “UK delivery only”.
	 *
	 * @return string
	 */
	public static function get_destination_rule_text() {
		$codes = self::allowed_countries();
		if ( array( 'GB' ) === $codes ) {
			return __( 'UK delivery only', 'systempak-bf-1p' );
		}
		return sprintf(
			/* translators: %s: country list */
			__( 'Delivery only to: %s', 'systempak-bf-1p' ),
			self::get_allowed_countries_label()
		);
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
	 * Resolve destination from checkout POST / update_order_review payload.
	 *
	 * @param array $data Posted fields.
	 * @return string
	 */
	public static function country_from_posted_checkout( $data ) {
		$data = is_array( $data ) ? $data : array();
		$ship_different = ! empty( $data['ship_to_different_address'] );
		if ( $ship_different && ! empty( $data['shipping_country'] ) ) {
			return strtoupper( sanitize_text_field( $data['shipping_country'] ) );
		}
		if ( ! empty( $data['billing_country'] ) ) {
			return strtoupper( sanitize_text_field( $data['billing_country'] ) );
		}
		if ( ! empty( $data['shipping_country'] ) ) {
			return strtoupper( sanitize_text_field( $data['shipping_country'] ) );
		}
		return '';
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
	 * @param string|null $country Country code.
	 * @return bool
	 */
	public static function is_allowed_destination( $country = null ) {
		if ( null === $country ) {
			$country = self::get_customer_shipping_country();
		}
		if ( ! $country ) {
			// No destination chosen yet — don't block browsing; checkout will re-check.
			return true;
		}
		return in_array( strtoupper( $country ), self::allowed_countries(), true );
	}

	/**
	 * Cart / checkout: remove promotional lines when destination is not allowed.
	 */
	public function maybe_remove_promo_for_country() {
		$country = self::get_customer_shipping_country();

		if ( ! empty( $_POST['billing_country'] ) || ! empty( $_POST['shipping_country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$posted = self::country_from_posted_checkout( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( $posted ) {
				$country = $posted;
			}
		}

		$this->remove_promo_items_if_disallowed( $country );
	}

	/**
	 * Checkout AJAX: country changed in the form.
	 *
	 * @param string $post_data Serialized checkout fields.
	 */
	public function maybe_remove_on_checkout_update( $post_data ) {
		$data = array();
		if ( is_string( $post_data ) ) {
			parse_str( $post_data, $data );
		}
		$country = self::country_from_posted_checkout( $data );
		if ( ! $country ) {
			$country = self::get_customer_shipping_country();
		}
		$this->remove_promo_items_if_disallowed( $country );
	}

	/**
	 * Block adding promo SKUs when destination is already known and disallowed.
	 *
	 * @param bool  $passed Validation state.
	 * @param int   $product_id Product ID.
	 * @param int   $quantity Quantity.
	 * @param int   $variation_id Variation ID.
	 * @param array $variations Attributes.
	 * @return bool
	 */
	public function validate_add_to_cart_country( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		if ( ! $passed || ! SPBF_Promotion::is_active_for_current_user() ) {
			return $passed;
		}

		$check_id = $variation_id ? (int) $variation_id : (int) $product_id;
		if ( ! SPBF_Product::is_qualifying_product( $check_id ) ) {
			return $passed;
		}

		$country = self::get_customer_shipping_country();
		if ( ! $country || self::is_allowed_destination( $country ) ) {
			return $passed;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: allowed country names */
				__( 'Black Friday 1p a Box products can only be delivered to %s. Please change your shipping country or choose a different product.', 'systempak-bf-1p' ),
				self::get_allowed_countries_label()
			),
			'error'
		);
		return false;
	}

	/**
	 * Remove promotional cart lines and notify the customer.
	 *
	 * @param string|null $country Country code.
	 */
	private function remove_promo_items_if_disallowed( $country ) {
		if ( self::$removing ) {
			return;
		}
		if ( ! SPBF_Promotion::is_active_for_current_user() ) {
			return;
		}
		if ( ! $country || self::is_allowed_destination( $country ) ) {
			return;
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$removed_names = array();
		self::$removing = true;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			if ( ! SPBF_Product::is_qualifying_product( $item['data']->get_id() ) ) {
				continue;
			}
			$removed_names[] = $item['data']->get_name();
			WC()->cart->remove_cart_item( $cart_item_key );
		}

		self::$removing = false;

		if ( empty( $removed_names ) ) {
			return;
		}

		$removed_names = array_values( array_unique( $removed_names ) );
		wc_add_notice(
			sprintf(
				/* translators: 1: allowed country names 2: removed product names */
				__( 'Black Friday 1p a Box products were removed from your basket because this offer is only available for delivery to %1$s. Removed: %2$s.', 'systempak-bf-1p' ),
				self::get_allowed_countries_label(),
				implode( ', ', $removed_names )
			),
			'notice'
		);
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
