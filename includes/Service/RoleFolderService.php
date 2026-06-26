<?php
/**
 * Role-based folder visibility (Pro). Restricts paths per WordPress role.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Pro;
use MCFM\Security\PathResolver;

defined( 'ABSPATH' ) || exit;

class RoleFolderService {

	private SettingsService $settings;
	private PathResolver $resolver;

	public function __construct( SettingsService $settings, PathResolver $resolver ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	public function register(): void {
		add_filter( 'mcfm_authorize_path', array( $this, 'filter_path' ), 10, 3 );
	}

	/**
	 * @param bool   $allowed
	 * @param string $relative
	 * @param int    $user_id
	 */
	public function filter_path( $allowed, $relative, $user_id ): bool {
		if ( ! $allowed || ! Pro::is_active() ) {
			return (bool) $allowed;
		}

		$rules = $this->settings->get( 'role_folder_rules', array() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return true;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;
		$prefixes = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$role = isset( $rule['role'] ) ? (string) $rule['role'] : '';
			$paths = isset( $rule['paths'] ) && is_array( $rule['paths'] ) ? $rule['paths'] : array();
			if ( '' === $role || ! in_array( $role, $roles, true ) ) {
				continue;
			}
			foreach ( $paths as $p ) {
				$p = trim( wp_normalize_path( (string) $p ), '/' );
				if ( '' !== $p ) {
					$prefixes[] = $p;
				}
			}
		}

		if ( empty( $prefixes ) ) {
			return true;
		}

		$rel = trim( wp_normalize_path( $relative ), '/' );
		foreach ( $prefixes as $prefix ) {
			if ( '' === $rel || 0 === strpos( $rel, $prefix ) || $rel === $prefix ) {
				return true;
			}
		}

		return false;
	}
}
