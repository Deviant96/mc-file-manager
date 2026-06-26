<?php
/**
 * Trash service. Hybrid delete that preserves the original folder structure
 * inside a managed trash directory, with restore and purge support.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Filesystem\FilesystemException;
use MCFM\Filesystem\FilesystemService;
use MCFM\Repository\TrashRepository;
use MCFM\Security\PathResolver;

defined( 'ABSPATH' ) || exit;

class TrashService {

	private FilesystemService $fs;
	private PathResolver $resolver;
	private TrashRepository $repo;
	private string $trash_dir;

	public function __construct( FilesystemService $fs, PathResolver $resolver, TrashRepository $repo, string $trash_dir ) {
		$this->fs        = $fs;
		$this->resolver  = $resolver;
		$this->repo      = $repo;
		$this->trash_dir = rtrim( wp_normalize_path( $trash_dir ), '/' );
	}

	/**
	 * Move an absolute path into the trash, preserving its relative structure.
	 *
	 * @return int Trash record id.
	 * @throws FilesystemException
	 */
	public function trash( string $abs ): int {
		$relative = $this->resolver->to_relative( $abs );
		if ( '' === $relative ) {
			throw new FilesystemException( 'Cannot delete the root directory.' );
		}

		$is_dir = $this->fs->is_dir( $abs );
		$size   = $is_dir ? 0 : (int) $this->fs->driver()->stat( $abs )['size'];

		$token     = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false );
		$container = $this->trash_dir . '/' . $token;
		$dest      = $container . '/' . $relative;

		wp_mkdir_p( dirname( $dest ) );
		$this->fs->driver()->move( $abs, $dest );

		return $this->repo->insert(
			array(
				'original_path' => $relative,
				'trash_path'    => $dest,
				'restore_path'  => $relative,
				'item_type'     => $is_dir ? 'dir' : 'file',
				'item_size'     => $size,
				'deleted_by'    => get_current_user_id(),
			)
		);
	}

	/**
	 * Restore a trashed item back to its original location.
	 *
	 * @return string The restored root-relative path.
	 * @throws FilesystemException
	 */
	public function restore( int $id ): string {
		$row = $this->repo->find( $id );
		if ( ! $row || 'trashed' !== $row['status'] ) {
			throw new FilesystemException( 'Trash item not found.' );
		}

		$restore_abs = $this->resolver->resolve( $row['restore_path'] );
		if ( $this->fs->exists( $restore_abs ) ) {
			throw new FilesystemException( 'A file already exists at the original location.' );
		}

		wp_mkdir_p( dirname( $restore_abs ) );
		$this->fs->driver()->move( $row['trash_path'], $restore_abs );
		$this->repo->update_status( $id, 'restored' );

		// Clean up the now-empty trash container.
		$this->cleanup_container( $row['trash_path'] );

		return $this->resolver->to_relative( $restore_abs );
	}

	/**
	 * Permanently delete a trashed item.
	 *
	 * @throws FilesystemException
	 */
	public function purge( int $id ): void {
		$row = $this->repo->find( $id );
		if ( ! $row ) {
			throw new FilesystemException( 'Trash item not found.' );
		}
		if ( 'trashed' === $row['status'] && $this->fs->exists( $row['trash_path'] ) ) {
			$this->fs->driver()->delete( $row['trash_path'] );
			$this->cleanup_container( $row['trash_path'] );
		}
		$this->repo->delete( $id );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_items(): array {
		$rows = $this->repo->list_trashed();
		return array_map(
			static function ( $row ) {
				return array(
					'id'           => (int) $row['id'],
					'originalPath' => $row['original_path'],
					'type'         => $row['item_type'],
					'size'         => (int) $row['item_size'],
					'deletedBy'    => (int) $row['deleted_by'],
					'deletedAt'    => $row['deleted_at'],
				);
			},
			$rows
		);
	}

	/**
	 * Remove the trash container directory if it is now empty.
	 */
	private function cleanup_container( string $trash_path ): void {
		$container = $this->trash_dir;
		$relative  = ltrim( str_replace( $container, '', wp_normalize_path( $trash_path ) ), '/' );
		$top       = explode( '/', $relative )[0] ?? '';
		if ( '' === $top ) {
			return;
		}
		$container_path = $container . '/' . $top;
		if ( is_dir( $container_path ) && $this->is_empty_recursive( $container_path ) ) {
			@$this->fs->driver()->delete( $container_path ); // phpcs:ignore
		}
	}

	private function is_empty_recursive( string $dir ): bool {
		try {
			foreach ( $this->fs->driver()->list_dir( $dir ) as $item ) {
				if ( $this->fs->is_dir( $item['abs'] ) ) {
					if ( ! $this->is_empty_recursive( $item['abs'] ) ) {
						return false;
					}
				} else {
					return false;
				}
			}
		} catch ( FilesystemException $e ) {
			return false;
		}
		return true;
	}
}
