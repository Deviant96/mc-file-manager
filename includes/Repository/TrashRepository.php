<?php
/**
 * Trash metadata persistence.
 *
 * @package MCFM
 */

namespace MCFM\Repository;

use MCFM\Database;

defined( 'ABSPATH' ) || exit;

class TrashRepository {

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Database::trash_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'original_path' => (string) $data['original_path'],
				'trash_path'    => (string) $data['trash_path'],
				'restore_path'  => (string) ( $data['restore_path'] ?? $data['original_path'] ),
				'item_type'     => (string) ( $data['item_type'] ?? 'file' ),
				'item_size'     => (int) ( $data['item_size'] ?? 0 ),
				'deleted_by'    => (int) ( $data['deleted_by'] ?? 0 ),
				'deleted_at'    => current_time( 'mysql', true ),
				'status'        => 'trashed',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?array {
		global $wpdb;
		$table = Database::trash_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_trashed(): array {
		global $wpdb;
		$table = Database::trash_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'trashed' ORDER BY deleted_at DESC", ARRAY_A );
		return $rows ? $rows : array();
	}

	public function update_status( int $id, string $status ): void {
		global $wpdb;
		$table = Database::trash_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public function delete( int $id ): void {
		global $wpdb;
		$table = Database::trash_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
