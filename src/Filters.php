<?php
/**
 * The values the report's three filters can take.
 *
 * @package SalesByStateReportForCharitable
 */

namespace SBSCH;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the filter options and validates what comes back from the browser.
 */
class Filters {

	/**
	 * Option holding the first year offered by the year filter.
	 */
	const YEAR_START_OPTION = 'sbsch_year_start';

	/**
	 * Number of years offered when the list is first created.
	 */
	const YEAR_WINDOW = 10;

	/**
	 * The columns shown in the report table and summary.
	 *
	 * @return array<string,array{label:string,type:string}>
	 */
	public static function measures() {
		return array(
			'amount' => array(
				'label' => __( 'Donations', 'sales-by-state-report-for-charitable' ),
				'type'  => 'currency',
			),
		);
	}

	/**
	 * Measure keys.
	 *
	 * @return string[]
	 */
	public static function measure_keys() {
		return array_keys( self::measures() );
	}

	/**
	 * Donation statuses the filter offers.
	 *
	 * Keys are Charitable post status slugs.
	 *
	 * @return array<string,string>
	 */
	public static function order_statuses() {
		if ( function_exists( 'charitable_get_valid_donation_statuses' ) ) {
			$statuses = charitable_get_valid_donation_statuses();
		} else {
			$statuses = array(
				'charitable-completed' => __( 'Paid', 'sales-by-state-report-for-charitable' ),
				'charitable-pending'   => __( 'Pending', 'sales-by-state-report-for-charitable' ),
				'charitable-failed'    => __( 'Failed', 'sales-by-state-report-for-charitable' ),
				'charitable-cancelled' => __( 'Cancelled', 'sales-by-state-report-for-charitable' ),
				'charitable-refunded'  => __( 'Refunded', 'sales-by-state-report-for-charitable' ),
			);
		}

		$offered = array();

		foreach ( (array) $statuses as $key => $label ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$offered[ $key ] = html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
		}

		return $offered;
	}

	/**
	 * The statuses ticked when the report is opened with no explicit filter.
	 *
	 * Defaults to Charitable's Paid status (`charitable-completed`).
	 *
	 * @return string[]
	 */
	public static function default_statuses() {
		$defaults = array( 'charitable-completed' );

		/**
		 * Filters the donation statuses the report starts on.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $statuses Status keys.
		 */
		$statuses = (array) apply_filters( 'sbsch_default_statuses', $defaults );

		return array_values( array_intersect( array_map( 'strval', $statuses ), self::status_keys() ) );
	}

	/**
	 * Status slugs as strings.
	 *
	 * @return string[]
	 */
	public static function status_keys() {
		return array_map( 'strval', array_keys( self::order_statuses() ) );
	}

	/**
	 * Reduce a request value to statuses this report recognises.
	 *
	 * @param string|array $value Comma-separated list or array of statuses.
	 * @return string[]
	 */
	public static function normalize_statuses( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$offered  = self::status_keys();
		$accepted = array();

		foreach ( $value as $status ) {
			$status = sanitize_key( trim( (string) $status ) );

			if ( in_array( $status, $offered, true ) ) {
				$accepted[] = $status;
			}
		}

		return array_values( array_unique( $accepted ) );
	}

	/**
	 * Years the filter offers, newest first.
	 *
	 * @return int[]
	 */
	public static function years() {
		$current  = self::default_year();
		$earliest = (int) get_option( self::YEAR_START_OPTION, 0 );

		if ( $earliest <= 0 ) {
			$earliest = $current - ( self::YEAR_WINDOW - 1 );
			update_option( self::YEAR_START_OPTION, $earliest, false );
		}

		if ( $earliest > $current ) {
			$earliest = $current;
		}

		return array_map( 'intval', range( $current, $earliest ) );
	}

	/**
	 * The year the report opens on.
	 *
	 * @return int
	 */
	public static function default_year() {
		return (int) current_time( 'Y' );
	}

	/**
	 * Reduce a request value to a year the filter offers.
	 *
	 * @param mixed $year Requested year.
	 * @return int
	 */
	public static function normalize_year( $year ) {
		$year = (int) $year;

		return in_array( $year, self::years(), true ) ? $year : self::default_year();
	}

	/**
	 * The country the report opens on.
	 *
	 * @return string Two-letter country code.
	 */
	public static function default_country() {
		$code = 'US';

		if ( function_exists( 'charitable_get_option' ) ) {
			$base = strtoupper( (string) charitable_get_option( 'country', 'US' ) );

			if ( preg_match( '/^[A-Z]{2}$/', $base ) ) {
				$code = $base;
			}
		}

		if ( self::states_for( $code ) ) {
			return $code;
		}

		foreach ( array_keys( self::countries_with_states() ) as $candidate ) {
			return $candidate;
		}

		return $code;
	}

	/**
	 * Reduce a request value to a two-letter country code.
	 *
	 * @param mixed $country Requested country.
	 * @return string
	 */
	public static function normalize_country( $country ) {
		$country = strtoupper( (string) $country );

		return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : self::default_country();
	}

	/**
	 * Countries the country dropdown always offers.
	 *
	 * @return array<string,string>
	 */
	public static function countries_with_states() {
		return array(
			'US' => __( 'United States', 'sales-by-state-report-for-charitable' ),
			'CA' => __( 'Canada', 'sales-by-state-report-for-charitable' ),
			'GB' => __( 'United Kingdom', 'sales-by-state-report-for-charitable' ),
		);
	}

	/**
	 * State code => name for a country.
	 *
	 * US, Canada, and the UK use the same fixed catalogs as the other
	 * Sales by State reports.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function states_for( $country ) {
		return Regions::states_for( $country );
	}
}
