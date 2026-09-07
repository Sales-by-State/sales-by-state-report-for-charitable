<?php
/**
 * Plugin Name:          Sales by State Report for Charitable
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of Charitable donations by state / county / province for a given country, filterable by donation status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://www.businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://www.businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-charitable
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     charitable
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForCharitable
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSCH_VERSION', '1.0.0' );
define( 'SBSCH_FILE', __FILE__ );
define( 'SBSCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSCH_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSCH\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSCH_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'Charitable' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for Charitable requires Charitable to be installed and active.', 'sales-by-state-report-for-charitable' )
					);
				}
			);

			return;
		}

		SBSCH\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSCH_FILE,
	function () {
		require_once SBSCH_DIR . 'src/Install/Schema.php';
		SBSCH\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSCH_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbsch_backfill_batch', array(), 'sales-by-state-report-for-charitable' );
		}
	}
);
