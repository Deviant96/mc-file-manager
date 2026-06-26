<?php
/**
 * Audit log persistence.
 *
 * @package MCFM
 */

namespace MCFM\Repository;

use MCFM\Database;

defined( 'ABSPATH' ) || exit;

class LogsRepository {

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Database::logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'user_id'     => (int) ( $data['user_id'] ?? 0 ),
				'action'      => (string) ( $data['action'] ?? '' ),
				'target_path' => isset( $data['target_path'] ) ? (string) $data['target_path'] : null,
				'source_path' => isset( $data['source_path'] ) ? (string) $data['source_path'] : null,
				'status'      => (string) ( $data['status'] ?? 'ok' ),
				'message'     => isset( $data['message'] ) ? (string) $data['message'] : null,
				'request_ip'  => isset( $data['request_ip'] ) ? (string) $data['request_ip'] : null,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function paginate( int $page = 1, int $per_page = 50, string $action = '' ): array {
		global $wpdb;
		$table    = Database::logs_table();
		$page     = max( 1, $page );
		$per_page = min( 200, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = '';
		$params = array();
		if ( '' !== $action ) {
			$where    = 'WHERE action = %s';
			$params[] = $action;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;
		$list_sql      = "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$items         = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}
}
