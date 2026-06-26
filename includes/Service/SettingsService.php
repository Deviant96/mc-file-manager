<?php
/**
 * Settings service: typed access with defaults and an exportable schema.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Repository\SettingsRepository;

defined( 'ABSPATH' ) || exit;

class SettingsService {

	private SettingsRepository $repo;

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	public function __construct( SettingsRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Default settings. Keys are stable; values are the v1 defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'warn_before_edit'    => true,
			'max_editable_bytes'  => 100 * 1024 * 1024, // 100 MB.
			'snapshot_retention'  => 5,
			'trash_enabled'       => true,
			'theme'               => 'vscode', // 'vscode' | 'wordpress'.
			'uninstall_drop_data' => false,
			'uninstall_drop_files' => false,
		);
	}

	public function seed_defaults(): void {
		$existing = $this->repo->get_all();
		foreach ( self::defaults() as $key => $value ) {
			if ( ! array_key_exists( $key, $existing ) ) {
				$this->repo->set( $key, $value );
			}
		}
		$this->cache = null;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$this->cache = array_merge( self::defaults(), $this->repo->get_all() );
		}
		return $this->cache;
	}

	public function get( string $key, $default = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a partial settings update, coercing to known types.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,mixed> The full, updated settings.
	 */
	public function update( array $values ): array {
		$defaults = self::defaults();
		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$coerced = $this->coerce( $key, $value, $defaults[ $key ] );
			$this->repo->set( $key, $coerced );
		}
		$this->cache = null;
		return $this->all();
	}

	private function coerce( string $key, $value, $default ) {
		if ( is_bool( $default ) ) {
			return (bool) rest_sanitize_boolean( $value );
		}
		if ( is_int( $default ) ) {
			$int = (int) $value;
			if ( 'max_editable_bytes' === $key ) {
				$int = max( 1024, $int );
			}
			if ( 'snapshot_retention' === $key ) {
				$int = max( 0, min( 100, $int ) );
			}
			return $int;
		}
		if ( 'theme' === $key ) {
			return in_array( $value, array( 'vscode', 'wordpress' ), true ) ? $value : $default;
		}
		return sanitize_text_field( (string) $value );
	}

	public function warn_before_edit(): bool {
		return (bool) $this->get( 'warn_before_edit', true );
	}

	public function max_editable_bytes(): int {
		return (int) $this->get( 'max_editable_bytes', 100 * 1024 * 1024 );
	}

	public function snapshot_retention(): int {
		return (int) $this->get( 'snapshot_retention', 5 );
	}

	public function trash_enabled(): bool {
		return (bool) $this->get( 'trash_enabled', true );
	}
}
