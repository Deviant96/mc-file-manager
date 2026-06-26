<?php
/**
 * Tracks which users have files open for concurrent-edit warnings (v1.1).
 *
 * @package MCFM
 */

namespace MCFM\Service;

defined( 'ABSPATH' ) || exit;

class OpenRegistryService {

	private const TTL = 90; // seconds

	private function key( string $relative_path ): string {
		return 'mcfm_open_' . md5( $relative_path );
	}

	/**
	 * @return array<string,array{id:int,name:string,seen:int}>
	 */
	private function read( string $relative_path ): array {
		$data = get_transient( $this->key( $relative_path ) );
		return is_array( $data ) ? $data : array();
	}

	private function write( string $relative_path, array $sessions ): void {
		set_transient( $this->key( $relative_path ), $sessions, self::TTL );
	}

	public function open( int $user_id, string $display_name, string $relative_path ): void {
		if ( '' === $relative_path || $user_id < 1 ) {
			return;
		}
		$sessions = $this->read( $relative_path );
		$sessions[ (string) $user_id ] = array(
			'id'   => $user_id,
			'name' => $display_name,
			'seen' => time(),
		);
		$this->write( $relative_path, $sessions );
	}

	public function heartbeat( int $user_id, string $relative_path ): void {
		$sessions = $this->read( $relative_path );
		$key      = (string) $user_id;
		if ( ! isset( $sessions[ $key ] ) ) {
			return;
		}
		$sessions[ $key ]['seen'] = time();
		$this->write( $relative_path, $sessions );
	}

	public function close( int $user_id, string $relative_path ): void {
		$sessions = $this->read( $relative_path );
		unset( $sessions[ (string) $user_id ] );
		$this->write( $relative_path, $sessions );
	}

	/**
	 * @return array<int,array{id:int,name:string}>
	 */
	public function peers( int $user_id, string $relative_path ): array {
		$now      = time();
		$sessions = $this->read( $relative_path );
		$peers    = array();

		foreach ( $sessions as $key => $session ) {
			if ( ! is_array( $session ) ) {
				continue;
			}
			if ( (int) ( $session['seen'] ?? 0 ) < $now - self::TTL ) {
				continue;
			}
			if ( (int) ( $session['id'] ?? 0 ) === $user_id ) {
				continue;
			}
			$peers[] = array(
				'id'   => (int) $session['id'],
				'name' => (string) ( $session['name'] ?? 'User' ),
			);
		}

		return $peers;
	}
}
