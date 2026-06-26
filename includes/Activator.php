<?php
/**
 * Activation routine: install tables, default settings, storage dirs.
 *
 * @package MCFM
 */

namespace MCFM;

use MCFM\Repository\SettingsRepository;
use MCFM\Service\SettingsService;

defined( 'ABSPATH' ) || exit;

class Activator {

	public static function activate(): void {
		Database::install();
		self::seed_settings();
		self::prepare_storage();
		update_option( 'mcfm_version', MCFM_VERSION );
		flush_rewrite_rules();
	}

	private static function seed_settings(): void {
		$service = new SettingsService( new SettingsRepository() );
		$service->seed_defaults();
	}

	/**
	 * Create managed storage directories with hardening files.
	 */
	private static function prepare_storage(): void {
		$dirs = array(
			Plugin::storage_dir( 'mcfm-snapshots' ),
			Plugin::storage_dir( 'mcfm-trash' ),
			Plugin::storage_dir( 'mcfm-temp' ),
		);

		foreach ( $dirs as $dir ) {
			wp_mkdir_p( $dir );
			$htaccess = trailingslashit( $dir ) . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Require all denied\n" ); // phpcs:ignore
			}
			$index = trailingslashit( $dir ) . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php // Silence is golden.\n" ); // phpcs:ignore
			}
		}
	}
}
