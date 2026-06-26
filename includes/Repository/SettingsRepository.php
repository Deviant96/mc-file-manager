<?php
/**
 * Settings persistence (key/value custom table).
 *
 * @package MCFM
 */

namespace MCFM\Repository;

use MCFM\Database;

defined( 'ABSPATH' ) || exit;

class SettingsRepository {

	public function get_all(): array {
		global $wpdb;
		$table = Database::settings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$table}", ARRAY_A );
		$out  = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$out[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
			}
		}
		return $out;
	}

	public function get( string $key, $default = null ) {
		$all = $this->get_all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public function set( string $key, $value ): void {
		global $wpdb;
		$table = Database::settings_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'setting_key'   => $key,
				'setting_value' => maybe_serialize( $value ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);
	}
}
