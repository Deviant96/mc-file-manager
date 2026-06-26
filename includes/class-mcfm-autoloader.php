<?php
/**
 * PSR-4 style autoloader for the MCFM namespace.
 *
 * @package MCFM
 */

namespace MCFM;

defined( 'ABSPATH' ) || exit;

class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Map a fully qualified class name to a file under includes/.
	 *
	 * Example: MCFM\Filesystem\LocalDriver => includes/Filesystem/LocalDriver.php
	 */
	public static function autoload( string $class ): void {
		$prefix = 'MCFM\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = MCFM_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
