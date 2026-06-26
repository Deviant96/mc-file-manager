<?php
/**
 * Uninstall cleanup. Honors the configurable cleanup settings.
 *
 * @package MCFM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mcfm-autoloader.php';

if ( ! defined( 'MCFM_PLUGIN_DIR' ) ) {
	define( 'MCFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

\MCFM\Autoloader::register();

global $wpdb;
$settings_table = $wpdb->prefix . 'mcfm_settings';

// Read cleanup preferences directly (services may not be bootstrapped here).
$drop_data  = false;
$drop_files = false;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table ) );
if ( $table_exists === $settings_table ) {
	$rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$settings_table}", ARRAY_A );
	foreach ( (array) $rows as $row ) {
		if ( 'uninstall_drop_data' === $row['setting_key'] ) {
			$drop_data = (bool) maybe_unserialize( $row['setting_value'] );
		}
		if ( 'uninstall_drop_files' === $row['setting_key'] ) {
			$drop_files = (bool) maybe_unserialize( $row['setting_value'] );
		}
	}
}
// phpcs:enable

if ( $drop_data ) {
	\MCFM\Database::drop();
	delete_option( 'mcfm_version' );
}

if ( $drop_files ) {
	$uploads = wp_get_upload_dir();
	$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
	foreach ( array( 'mcfm-snapshots', 'mcfm-trash', 'mcfm-temp' ) as $dir ) {
		$target = trailingslashit( $base ) . $dir;
		if ( is_dir( $target ) ) {
			mcfm_uninstall_rrmdir( $target );
		}
	}
}

/**
 * Recursively remove a directory during uninstall.
 */
function mcfm_uninstall_rrmdir( $dir ) {
	$items = @scandir( $dir ); // phpcs:ignore
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			mcfm_uninstall_rrmdir( $path );
		} else {
			@unlink( $path ); // phpcs:ignore
		}
	}
	@rmdir( $dir ); // phpcs:ignore
}
