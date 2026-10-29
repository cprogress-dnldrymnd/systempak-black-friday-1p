<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule + visibility gate for the promotion.
 */
class SPBF_Promotion {

	/**
	 * @return DateTimeImmutable
	 */
	public static function now_in_timezone() {
		$tz_string = SPBF_Settings::get( 'timezone', 'Europe/London' );
		try {
			$tz = new DateTimeZone( $tz_string );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'Europe/London' );
		}
		return new DateTimeImmutable( 'now', $tz );
	}

	/**
	 * @param string $datetime Y-m-d H:i:s in promo timezone.
	 * @return DateTimeImmutable|null
	 */
	public static function parse_local( $datetime ) {
		$tz_string = SPBF_Settings::get( 'timezone', 'Europe/London' );
		try {
			$tz = new DateTimeZone( $tz_string );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'Europe/London' );
		}
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', trim( (string) $datetime ), $tz );
		if ( ! $dt ) {
			try {
				$dt = new DateTimeImmutable( (string) $datetime, $tz );
			} catch ( Exception $e ) {
				return null;
			}
		}
		return $dt;
	}

	/**
	 * Whether "now" is inside the configured window (inclusive start, exclusive end).
	 *
	 * @return bool
	 */
	public static function is_within_schedule() {
		$start = self::parse_local( SPBF_Settings::get( 'start_datetime' ) );
		$end   = self::parse_local( SPBF_Settings::get( 'end_datetime' ) );
		if ( ! $start || ! $end ) {
			return false;
		}
		$now = self::now_in_timezone();
		return ( $now >= $start && $now < $end );
	}

	/**
	 * Whether the current request/user should receive promotional behaviour.
	 *
	 * - off: never
	 * - admin_only: manage_woocommerce users only (schedule ignored so you can test early)
	 * - live: only inside schedule for everyone
	 *
	 * @return bool
	 */
	public static function is_active_for_current_user() {
		$visibility = SPBF_Settings::get( 'visibility', 'admin_only' );

		if ( 'off' === $visibility ) {
			return false;
		}

		if ( 'admin_only' === $visibility ) {
			return current_user_can( 'manage_woocommerce' );
		}

		// live
		return self::is_within_schedule();
	}

	/**
	 * During Admin testing only, include private products in the collection for testers.
	 *
	 * @return bool
	 */
	public static function should_include_private_products() {
		return 'admin_only' === SPBF_Settings::get( 'visibility', 'admin_only' )
			&& current_user_can( 'manage_woocommerce' )
			&& self::is_active_for_current_user();
	}

	/**
	 * Formatted end date for customer messaging.
	 *
	 * @return string
	 */
	public static function get_end_label() {
		$end = self::parse_local( SPBF_Settings::get( 'end_datetime' ) );
		if ( ! $end ) {
			return '';
		}
		return $end->format( 'g:ia, l j F Y' );
	}
}
