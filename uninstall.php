<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForCharitable
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbsch_options = array(
	'sbsch_db_version',
	'sbsch_backfill_cursor',
	'sbsch_year_start',
);

foreach ( $sbsch_options as $sbsch_option ) {
	delete_option( $sbsch_option );
}

if ( is_multisite() ) {
	foreach ( $sbsch_options as $sbsch_option ) {
		delete_site_option( $sbsch_option );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbsch_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbsch_backfill_batch', array(), 'sales-by-state-report-for-charitable' );
}
