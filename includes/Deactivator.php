<?php
/**
 * Deactivation routine. Keeps user data intact; only flushes rewrites.
 *
 * @package MCFM
 */

namespace MCFM;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
