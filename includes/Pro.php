<?php
/**
 * Pro feature gate. Free build returns false; Pro add-on hooks mcfm_is_pro.
 *
 * @package MCFM
 */

namespace MCFM;

defined( 'ABSPATH' ) || exit;

final class Pro {

	public static function is_active(): bool {
		return (bool) apply_filters( 'mcfm_is_pro', false );
	}

	public static function require_pro(): void {
		if ( ! self::is_active() ) {
			throw new \RuntimeException( __( 'This feature requires MC File Manager Pro.', 'mc-file-manager' ) );
		}
	}
}
