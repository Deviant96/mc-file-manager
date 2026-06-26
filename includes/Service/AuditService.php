<?php
/**
 * Audit service. Records every important action with who/when/what/outcome.
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Repository\LogsRepository;

defined( 'ABSPATH' ) || exit;

class AuditService {

	private LogsRepository $repo;

	public function __construct( LogsRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Record an action.
	 *
	 * @param string $action      One of the known action verbs.
	 * @param string $status      'ok' | 'error'.
	 * @param string $target_path Root-relative target path.
	 * @param string $source_path Root-relative source path (rename/move/copy).
	 * @param string $message     Optional human-readable note.
	 */
	public function log( string $action, string $status = 'ok', string $target_path = '', string $source_path = '', string $message = '' ): void {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$this->repo->insert(
			array(
				'user_id'     => get_current_user_id(),
				'action'      => $action,
				'status'      => $status,
				'target_path' => $target_path,
				'source_path' => $source_path,
				'message'     => $message,
				'request_ip'  => $ip,
			)
		);
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function paginate( int $page, int $per_page, string $action = '' ): array {
		return $this->repo->paginate( $page, $per_page, $action );
	}
}
