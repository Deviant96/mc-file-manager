<?php
/**
 * Revision snapshot metadata persistence.
 *
 * @package MCFM
 */

namespace MCFM\Repository;

use MCFM\Database;

defined( 'ABSPATH' ) || exit;

class SnapshotsRepository {

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Database::snapshots_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'original_path'  => (string) $data['original_path'],
				'snapshot_path'  => (string) $data['snapshot_path'],
				'version_number' => (int) $data['version_number'],
				'file_size'      => (int) ( $data['file_size'] ?? 0 ),
				'path_hash'      => (string) $data['path_hash'],
				'created_by'     => (int) ( $data['created_by'] ?? 0 ),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?array {
		global $wpdb;
		$table = Database::snapshots_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function for_path( string $path_hash ): array {
		global $wpdb;
		$table = Database::snapshots_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE path_hash = %s ORDER BY version_number DESC", $path_hash ), ARRAY_A );
		return $rows ? $rows : array();
	}

	public function latest_version( string $path_hash ): int {
		global $wpdb;
		$table = Database::snapshots_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version_number) FROM {$table} WHERE path_hash = %s", $path_hash ) );
		return (int) $max;
	}

	public function delete( int $id ): void {
		global $wpdb;
		$table = Database::snapshots_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
