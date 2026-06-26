<?php
/**
 * Custom table installer.
 *
 * @package MCFM
 */

namespace MCFM;

defined( 'ABSPATH' ) || exit;

class Database {

	public static function logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcfm_logs';
	}

	public static function trash_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcfm_trash';
	}

	public static function snapshots_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcfm_snapshots';
	}

	public static function settings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcfm_settings';
	}

	/**
	 * Create or upgrade all custom tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$logs            = self::logs_table();
		$trash           = self::trash_table();
		$snapshots       = self::snapshots_table();
		$settings        = self::settings_table();

		$schema = array();

		$schema[] = "CREATE TABLE {$logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(40) NOT NULL DEFAULT '',
			target_path TEXT NULL,
			source_path TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'ok',
			message TEXT NULL,
			request_ip VARCHAR(45) NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$trash} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			original_path TEXT NOT NULL,
			trash_path TEXT NOT NULL,
			restore_path TEXT NULL,
			item_type VARCHAR(10) NOT NULL DEFAULT 'file',
			item_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			deleted_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			deleted_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			status VARCHAR(20) NOT NULL DEFAULT 'trashed',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$snapshots} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			original_path TEXT NOT NULL,
			snapshot_path TEXT NOT NULL,
			version_number INT UNSIGNED NOT NULL DEFAULT 1,
			file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			path_hash CHAR(40) NOT NULL DEFAULT '',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY path_hash (path_hash),
			KEY created_at (created_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$settings} (
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (setting_key)
		) {$charset_collate};";

		foreach ( $schema as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop all custom tables (used by uninstall when configured).
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( array( self::logs_table(), self::trash_table(), self::snapshots_table(), self::settings_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
