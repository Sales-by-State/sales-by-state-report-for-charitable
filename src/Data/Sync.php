<?php
/**
 * Keeps the report table in step with Charitable donations.
 *
 * @package SalesByStateReportForCharitable
 */

namespace SBSCH\Data;

use SBSCH\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per donation.
 */
class Sync {

	/**
	 * Post meta keys that affect the report row.
	 *
	 * @var string[]
	 */
	const MONEY_META = array( 'donor', 'currency' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'charitable_after_save_donation', array( $this, 'on_donation_id' ), 20, 1 );
		add_action( 'charitable_donation_status_changed', array( $this, 'on_status_changed' ), 20, 1 );
		add_action( 'save_post_donation', array( $this, 'on_save_post' ), 20, 1 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 20, 1 );
		add_action( 'wp_trash_post', array( $this, 'on_donation_id' ), 20, 1 );
		add_action( 'untrashed_post', array( $this, 'on_donation_id' ), 20, 1 );
		add_action( 'updated_post_meta', array( $this, 'on_meta' ), 20, 4 );
		add_action( 'added_post_meta', array( $this, 'on_meta' ), 20, 4 );
	}

	/**
	 * Handle a hook that passes a donation ID first.
	 *
	 * @param mixed $donation_id Donation ID.
	 * @return void
	 */
	public function on_donation_id( $donation_id ) {
		$donation_id = (int) $donation_id;

		if ( ! $donation_id ) {
			return;
		}

		$type = get_post_type( $donation_id );

		if ( $type && 'donation' !== $type ) {
			return;
		}

		$this->upsert( $donation_id );
	}

	/**
	 * Handle Charitable's status change, which passes the donation object.
	 *
	 * @param mixed $donation Donation object.
	 * @return void
	 */
	public function on_status_changed( $donation ) {
		$this->upsert( $this->id_from( $donation ) );
	}

	/**
	 * Handle save_post for the donation post type.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_save_post( $post_id ) {
		$this->upsert( (int) $post_id );
	}

	/**
	 * Remove the row when a donation post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_deleted_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id ) {
			return;
		}

		if ( 'donation' !== get_post_type( $post_id ) && ! $this->row_exists( $post_id ) ) {
			return;
		}

		$this->delete( $post_id );
	}

	/**
	 * Refresh the row when donor address or currency meta changes.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_meta( $meta_id, $object_id, $meta_key = '', $meta_value = null ) {
		unset( $meta_id, $meta_value );

		if ( ! in_array( (string) $meta_key, self::MONEY_META, true ) ) {
			return;
		}

		if ( 'donation' !== get_post_type( (int) $object_id ) ) {
			return;
		}

		$this->upsert( (int) $object_id );
	}

	/**
	 * Insert or update the row for one donation.
	 *
	 * @param int $order_id Donation ID.
	 * @return bool
	 */
	public function upsert( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$row = self::build_row( $order_id );

		if ( ! $row ) {
			$this->delete( $order_id );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for a donation.
	 *
	 * @param int $order_id Donation ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => (int) $order_id ), array( '%d' ) );
	}

	/**
	 * Build the row for a donation from Charitable tables.
	 *
	 * Charitable has no shipping address. The report groups by the donor
	 * country and state stored on the donation. Gross and net are the donated
	 * amount; Charitable core does not store tax or shipping on a donation.
	 *
	 * @param int $order_id Donation ID.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_status, post_date_gmt, post_type
				 FROM {$wpdb->posts}
				 WHERE ID = %d",
				$order_id
			)
		);

		if ( ! $post || 'donation' !== $post->post_type ) {
			return false;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return false;
		}

		$donor    = self::donor_from_meta( $order_id );
		$country  = self::country_code( $donor['country'] ?? '' );
		$state    = self::state_code( $donor['state'] ?? '', $country );
		$total    = self::donation_amount( $order_id );
		$created  = self::normalize_datetime( $post->post_date_gmt );
		$paid     = self::is_paid_status( $post->post_status ) ? $created : null;
		$currency = self::donation_currency( $order_id );

		return array(
			'order_id'         => (int) $post->ID,
			'status'           => substr( sanitize_key( (string) $post->post_status ), 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid,
			'billing_country'  => $country,
			'billing_state'    => substr( $state, 0, 50 ),
			'shipping_country' => $country,
			'shipping_state'   => substr( $state, 0, 50 ),
			'currency'         => $currency,
			'total_sales'      => $total,
			'tax_total'        => 0,
			'shipping_total'   => 0,
			'net_total'        => $total,
		);
	}

	/**
	 * Sum of campaign donation amounts for one donation.
	 *
	 * @param int $donation_id Donation ID.
	 * @return float
	 */
	private static function donation_amount( $donation_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$amount = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM( amount ) FROM {$wpdb->prefix}charitable_campaign_donations WHERE donation_id = %d",
				(int) $donation_id
			)
		);

		return round( (float) $amount, 2 );
	}

	/**
	 * Donor address stored on the donation.
	 *
	 * @param int $donation_id Donation ID.
	 * @return array<string,mixed>
	 */
	private static function donor_from_meta( $donation_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'donor' LIMIT 1",
				(int) $donation_id
			)
		);

		$value = maybe_unserialize( $value );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Currency stored on the donation, else Charitable's site currency.
	 *
	 * @param int $donation_id Donation ID.
	 * @return string
	 */
	private static function donation_currency( $donation_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$currency = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'currency' LIMIT 1",
				(int) $donation_id
			)
		);

		$currency = strtoupper( substr( (string) $currency, 0, 3 ) );

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) && function_exists( 'charitable_get_currency' ) ) {
			$currency = strtoupper( substr( (string) charitable_get_currency(), 0, 3 ) );
		}

		return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'USD';
	}

	/**
	 * Whether a donation status counts as paid for date_paid.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private static function is_paid_status( $status ) {
		$paid = array( 'charitable-completed', 'charitable-refunded', 'charitable-preapproved' );

		if ( function_exists( 'charitable_get_approval_statuses' ) ) {
			$paid = array_merge( $paid, (array) charitable_get_approval_statuses() );
		}

		return in_array( (string) $status, array_unique( $paid ), true );
	}

	/**
	 * Two-letter country code.
	 *
	 * @param mixed $country Country.
	 * @return string
	 */
	private static function country_code( $country ) {
		$country = trim( (string) $country );

		if ( preg_match( '/^[A-Za-z]{2}$/', $country ) ) {
			return strtoupper( $country );
		}

		$names = array(
			'united states'  => 'US',
			'usa'            => 'US',
			'canada'         => 'CA',
			'united kingdom' => 'GB',
			'great britain'  => 'GB',
			'england'        => 'GB',
		);

		$key = strtolower( $country );

		return isset( $names[ $key ] ) ? $names[ $key ] : strtoupper( substr( $country, 0, 2 ) );
	}

	/**
	 * State / county as Charitable stores it.
	 *
	 * US and Canada use two-letter codes. The UK stores the county name.
	 *
	 * @param mixed  $state   State.
	 * @param string $country Country code.
	 * @return string
	 */
	private static function state_code( $state, $country ) {
		$state = trim( (string) $state );

		if ( in_array( $country, array( 'US', 'CA' ), true ) ) {
			return strtoupper( $state );
		}

		return $state;
	}

	/**
	 * Pull a donation ID from a Charitable object or scalar.
	 *
	 * @param mixed $donation Donation object or ID.
	 * @return int
	 */
	private function id_from( $donation ) {
		if ( is_object( $donation ) && isset( $donation->ID ) ) {
			return (int) $donation->ID;
		}

		if ( is_object( $donation ) && method_exists( $donation, 'get_donation_id' ) ) {
			return (int) $donation->get_donation_id();
		}

		return (int) $donation;
	}

	/**
	 * Whether a report row already exists.
	 *
	 * Used after delete, when get_post_type() may already return empty.
	 *
	 * @param int $order_id Donation ID.
	 * @return bool
	 */
	private function row_exists( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}sbsch_order_state WHERE order_id = %d LIMIT 1",
				(int) $order_id
			)
		);
	}

	/**
	 * Normalise a datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		$value = (string) $value;

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		$ts = strtotime( $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
