<?php
/**
 * Revision service. Snapshots files to disk before each save and enforces a
 * configurable retention limit. Metadata lives in the DB; bytes live on disk.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Filesystem\FilesystemException;
use MCFM\Filesystem\FilesystemService;
use MCFM\Repository\SnapshotsRepository;
use MCFM\Security\PathResolver;

defined( 'ABSPATH' ) || exit;

class RevisionService {

	private FilesystemService $fs;
	private PathResolver $resolver;
	private SnapshotsRepository $repo;
	private string $snapshot_dir;
	private SettingsService $settings;

	public function __construct( FilesystemService $fs, PathResolver $resolver, SnapshotsRepository $repo, string $snapshot_dir, SettingsService $settings ) {
		$this->fs           = $fs;
		$this->resolver     = $resolver;
		$this->repo         = $repo;
		$this->snapshot_dir = rtrim( wp_normalize_path( $snapshot_dir ), '/' );
		$this->settings     = $settings;
	}

	/**
	 * Snapshot the current on-disk contents of a file before it is overwritten.
	 *
	 * @return int|null Snapshot id, or null when there is nothing to snapshot.
	 */
	public function snapshot( string $abs ): ?int {
		if ( ! $this->fs->exists( $abs ) || $this->fs->is_dir( $abs ) ) {
			return null;
		}

		$relative = $this->resolver->to_relative( $abs );
		$hash     = sha1( $relative );
		$version  = $this->repo->latest_version( $hash ) + 1;
		$dir      = $this->snapshot_dir . '/' . $hash;
		wp_mkdir_p( $dir );

		$snapshot_path = $dir . '/v' . $version . '-' . basename( $relative );
		$contents      = $this->fs->read( $abs );
		$this->fs->write( $snapshot_path, $contents );

		$id = $this->repo->insert(
			array(
				'original_path'  => $relative,
				'snapshot_path'  => $snapshot_path,
				'version_number' => $version,
				'file_size'      => strlen( $contents ),
				'path_hash'      => $hash,
				'created_by'     => get_current_user_id(),
			)
		);

		$this->prune( $hash );
		return $id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for( string $abs ): array {
		$relative = $this->resolver->to_relative( $abs );
		$rows     = $this->repo->for_path( sha1( $relative ) );
		return array_map(
			static function ( $row ) {
				return array(
					'id'        => (int) $row['id'],
					'version'   => (int) $row['version_number'],
					'size'      => (int) $row['file_size'],
					'createdBy' => (int) $row['created_by'],
					'createdAt' => $row['created_at'],
				);
			},
			$rows
		);
	}

	public function read_snapshot( int $id ): string {
		$row = $this->repo->find( $id );
		if ( ! $row || ! file_exists( $row['snapshot_path'] ) ) {
			throw new FilesystemException( 'Snapshot not found.' );
		}
		return $this->fs->read( $row['snapshot_path'] );
	}

	/**
	 * Return the absolute original path for a snapshot (after re-jailing).
	 */
	public function original_abs_for( int $id ): string {
		$row = $this->repo->find( $id );
		if ( ! $row ) {
			throw new FilesystemException( 'Snapshot not found.' );
		}
		return $this->resolver->resolve( $row['original_path'] );
	}

	/**
	 * Delete a snapshot row and its on-disk file.
	 */
	public function delete_snapshot( int $id ): void {
		$row = $this->repo->find( $id );
		if ( ! $row ) {
			throw new FilesystemException( 'Snapshot not found.' );
		}
		if ( file_exists( $row['snapshot_path'] ) ) {
			@unlink( $row['snapshot_path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$this->repo->delete( $id );
	}

	/**
	 * Attach snapshot summary fields to directory listing entries.
	 *
	 * @param array<int,array<string,mixed>> $entries
	 * @return array<int,array<string,mixed>>
	 */
	public function enrich_entries( array $entries ): array {
		$file_paths = array();
		foreach ( $entries as $entry ) {
			if ( empty( $entry['isDir'] ) && ! empty( $entry['path'] ) ) {
				$file_paths[] = (string) $entry['path'];
			}
		}

		$summaries = $this->repo->summaries_for_paths( $file_paths );

		foreach ( $entries as &$entry ) {
			if ( ! empty( $entry['isDir'] ) ) {
				$entry['hasSnapshot']    = false;
				$entry['lastSnapshotAt'] = null;
				$entry['snapshotCount']  = 0;
				continue;
			}
			$path   = (string) ( $entry['path'] ?? '' );
			$meta   = $summaries[ $path ] ?? null;
			$entry['hasSnapshot']    = $meta ? $meta['hasSnapshot'] : false;
			$entry['lastSnapshotAt'] = $meta ? $meta['lastSnapshotAt'] : null;
			$entry['snapshotCount']  = $meta ? $meta['snapshotCount'] : 0;
		}
		unset( $entry );

		return $entries;
	}

	/**
	 * Keep only the newest N snapshots for a path; delete older files + rows.
	 */
	private function prune( string $hash ): void {
		$retention = $this->settings->snapshot_retention();
		if ( $retention <= 0 ) {
			return;
		}
		$rows = $this->repo->for_path( $hash ); // DESC by version.
		if ( count( $rows ) <= $retention ) {
			return;
		}
		$stale = array_slice( $rows, $retention );
		foreach ( $stale as $row ) {
			if ( file_exists( $row['snapshot_path'] ) ) {
				@unlink( $row['snapshot_path'] ); // phpcs:ignore
			}
			$this->repo->delete( (int) $row['id'] );
		}
	}
}
