<?php
/**
 * Canonical path resolver. Every filesystem path passes through here.
 *
 * Enforces the root jail and blocks traversal, null bytes, encoded tricks,
 * and symlink escapes.
 *
 * @package MCFM
 */

namespace MCFM\Security;

defined( 'ABSPATH' ) || exit;

class PathResolver {

	private string $root;

	public function __construct( string $root ) {
		$real        = realpath( $root );
		$this->root  = rtrim( wp_normalize_path( $real ? $real : $root ), '/' );
	}

	public function root(): string {
		return $this->root;
	}

	/**
	 * Resolve a client-supplied relative path into a safe absolute path.
	 *
	 * @param string $relative Relative path from the root (may start with /).
	 * @return string Absolute, normalized, jailed path.
	 * @throws PathException When the path is invalid or escapes the root.
	 */
	public function resolve( string $relative ): string {
		$relative = (string) wp_unslash( $relative );

		// Reject null bytes and control characters outright.
		if ( strpos( $relative, "\0" ) !== false || preg_match( '/[\x00-\x1F]/', $relative ) ) {
			throw new PathException( 'Invalid characters in path.' );
		}

		// Defend against double-encoded traversal by decoding once and checking.
		$decoded = rawurldecode( $relative );
		if ( strpos( $decoded, "\0" ) !== false ) {
			throw new PathException( 'Invalid characters in path.' );
		}

		$normalized = wp_normalize_path( $decoded );

		// Strip a leading root if the client echoed back an absolute path.
		if ( 0 === strpos( $normalized, $this->root ) ) {
			$normalized = substr( $normalized, strlen( $this->root ) );
		}

		$normalized = ltrim( $normalized, '/' );

		// Collapse the path segment-by-segment, refusing to go above root.
		$segments = array();
		foreach ( explode( '/', $normalized ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				if ( empty( $segments ) ) {
					throw new PathException( 'Path escapes the allowed root.' );
				}
				array_pop( $segments );
				continue;
			}
			$segments[] = $segment;
		}

		$candidate = $this->root;
		if ( ! empty( $segments ) ) {
			$candidate .= '/' . implode( '/', $segments );
		}

		return $this->verify_within_root( $candidate );
	}

	/**
	 * Ensure a resolved candidate truly resides inside the root, following
	 * symlinks where the target exists.
	 */
	private function verify_within_root( string $candidate ): string {
		$candidate = wp_normalize_path( $candidate );

		// If the target exists, canonicalize it (resolves symlinks).
		$real = realpath( $candidate );
		if ( false !== $real ) {
			$real = wp_normalize_path( $real );
			if ( ! $this->is_inside( $real ) ) {
				throw new PathException( 'Resolved path is outside the allowed root.' );
			}
			return $real;
		}

		// Target does not exist yet (e.g. new file/folder). Canonicalize the
		// closest existing ancestor and confirm it is jailed.
		$parent = dirname( $candidate );
		$guard  = 0;
		while ( $parent && $parent !== $this->root && false === realpath( $parent ) ) {
			$parent = dirname( $parent );
			if ( ++$guard > 4096 ) {
				throw new PathException( 'Unable to resolve path.' );
			}
		}

		$real_parent = realpath( $parent );
		if ( false === $real_parent ) {
			$real_parent = $this->root;
		}
		$real_parent = wp_normalize_path( $real_parent );

		if ( ! $this->is_inside( $real_parent, true ) ) {
			throw new PathException( 'Resolved path is outside the allowed root.' );
		}

		return $candidate;
	}

	/**
	 * Whether an absolute path is the root or sits beneath it.
	 */
	public function is_inside( string $abs, bool $inclusive_root = true ): bool {
		$abs = wp_normalize_path( $abs );
		if ( $abs === $this->root ) {
			return $inclusive_root;
		}
		return 0 === strpos( $abs, $this->root . '/' );
	}

	/**
	 * Convert an absolute jailed path to a root-relative path (forward slashes).
	 */
	public function to_relative( string $abs ): string {
		$abs = wp_normalize_path( $abs );
		if ( $abs === $this->root ) {
			return '';
		}
		if ( 0 === strpos( $abs, $this->root . '/' ) ) {
			return ltrim( substr( $abs, strlen( $this->root ) ), '/' );
		}
		return ltrim( $abs, '/' );
	}
}
