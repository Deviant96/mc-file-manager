<?php
/**
 * Central security policy: capabilities, nonces, and path authorization.
 *
 * @package MCFM
 */

namespace MCFM\Security;

defined( 'ABSPATH' ) || exit;

class SecurityService {

	public const CAPABILITY = 'manage_options';

	private PathResolver $resolver;

	public function __construct( PathResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public function resolver(): PathResolver {
		return $this->resolver;
	}

	/**
	 * The capability required to use the file manager. Filterable so future
	 * role-based scoping can extend this without touching call sites.
	 */
	public function required_capability(): string {
		return (string) apply_filters( 'mcfm_required_capability', self::CAPABILITY );
	}

	public function current_user_can_manage(): bool {
		return current_user_can( $this->required_capability() );
	}

	/**
	 * REST permission callback. Verifies capability; nonce is validated by the
	 * WordPress REST cookie auth layer via the X-WP-Nonce header.
	 */
	public function rest_permission_check(): bool {
		return $this->current_user_can_manage();
	}

	/**
	 * Resolve and authorize a client path. Returns the absolute jailed path.
	 *
	 * @throws PathException
	 */
	public function authorize_path( string $relative ): string {
		$abs = $this->resolver->resolve( $relative );

		/**
		 * Future role-based folder restrictions hook here. Returning false
		 * denies access to the resolved path.
		 */
		$allowed = apply_filters( 'mcfm_authorize_path', true, $this->resolver->to_relative( $abs ), get_current_user_id() );
		if ( ! $allowed ) {
			throw new PathException( 'Access to this path is not permitted.' );
		}

		return $abs;
	}

	public function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}
}
