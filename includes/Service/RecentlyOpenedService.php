<?php
/**
 * Per-user recently opened files (Pro).
 *
 * @package MCFM
 */

namespace MCFM\Service;

defined( 'ABSPATH' ) || exit;

class RecentlyOpenedService {

	private const META_KEY = 'mcfm_recent_files';
	private const MAX      = 20;

	/**
	 * @return array<int,array{path:string,name:string,opened_at:string}>
	 */
	public function list_for_user( int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Record a file open and return the updated list.
	 *
	 * @return array<int,array{path:string,name:string,opened_at:string}>
	 */
	public function track( int $user_id, string $relative_path, string $name ): array {
		if ( $user_id < 1 || '' === $relative_path ) {
			return array();
		}

		$items = $this->list_for_user( $user_id );
		$items = array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $relative_path ) {
					return is_array( $item ) && ( $item['path'] ?? '' ) !== $relative_path;
				}
			)
		);

		array_unshift(
			$items,
			array(
				'path'      => $relative_path,
				'name'      => $name,
				'opened_at' => gmdate( 'c' ),
			)
		);

		$items = array_slice( $items, 0, self::MAX );
		update_user_meta( $user_id, self::META_KEY, $items );

		return $items;
	}
}
