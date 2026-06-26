<?php
/**
 * Local filesystem driver for v1.
 *
 * @package MCFM
 */

namespace MCFM\Filesystem;

defined( 'ABSPATH' ) || exit;

class LocalDriver implements FilesystemDriver {

	public function exists( string $abs ): bool {
		return file_exists( $abs );
	}

	public function is_dir( string $abs ): bool {
		return is_dir( $abs );
	}

	public function is_file( string $abs ): bool {
		return is_file( $abs );
	}

	public function list_dir( string $abs ): array {
		if ( ! is_dir( $abs ) ) {
			throw new FilesystemException( 'Not a directory.' );
		}

		$entries = @scandir( $abs ); // phpcs:ignore
		if ( false === $entries ) {
			throw new FilesystemException( 'Unable to read directory.' );
		}

		$out = array();
		foreach ( $entries as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$child = $abs . '/' . $name;
			$out[] = array(
				'name' => $name,
				'abs'  => wp_normalize_path( $child ),
			);
		}
		return $out;
	}

	public function read( string $abs ): string {
		if ( ! is_file( $abs ) ) {
			throw new FilesystemException( 'File not found.' );
		}
		$contents = @file_get_contents( $abs ); // phpcs:ignore
		if ( false === $contents ) {
			throw new FilesystemException( 'Unable to read file.' );
		}
		return $contents;
	}

	public function write( string $abs, string $contents ): void {
		$bytes = @file_put_contents( $abs, $contents ); // phpcs:ignore
		if ( false === $bytes ) {
			throw new FilesystemException( 'Unable to write file.' );
		}
	}

	public function make_dir( string $abs ): void {
		if ( file_exists( $abs ) ) {
			throw new FilesystemException( 'A file or folder with that name already exists.' );
		}
		if ( ! wp_mkdir_p( $abs ) ) {
			throw new FilesystemException( 'Unable to create folder.' );
		}
	}

	public function make_file( string $abs, string $contents = '' ): void {
		if ( file_exists( $abs ) ) {
			throw new FilesystemException( 'A file or folder with that name already exists.' );
		}
		$dir = dirname( $abs );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$this->write( $abs, $contents );
	}

	public function rename( string $from_abs, string $to_abs ): void {
		if ( ! file_exists( $from_abs ) ) {
			throw new FilesystemException( 'Source not found.' );
		}
		if ( file_exists( $to_abs ) ) {
			throw new FilesystemException( 'Destination already exists.' );
		}
		if ( ! @rename( $from_abs, $to_abs ) ) { // phpcs:ignore
			throw new FilesystemException( 'Unable to rename.' );
		}
	}

	public function copy( string $from_abs, string $to_abs ): void {
		if ( ! file_exists( $from_abs ) ) {
			throw new FilesystemException( 'Source not found.' );
		}
		if ( file_exists( $to_abs ) ) {
			throw new FilesystemException( 'Destination already exists.' );
		}
		if ( is_dir( $from_abs ) ) {
			$this->copy_dir( $from_abs, $to_abs );
		} elseif ( ! @copy( $from_abs, $to_abs ) ) { // phpcs:ignore
			throw new FilesystemException( 'Unable to copy file.' );
		}
	}

	private function copy_dir( string $from, string $to ): void {
		if ( ! wp_mkdir_p( $to ) ) {
			throw new FilesystemException( 'Unable to create destination folder.' );
		}
		$items = $this->list_dir( $from );
		foreach ( $items as $item ) {
			$src = $item['abs'];
			$dst = $to . '/' . $item['name'];
			if ( is_dir( $src ) ) {
				$this->copy_dir( $src, $dst );
			} elseif ( ! @copy( $src, $dst ) ) { // phpcs:ignore
				throw new FilesystemException( 'Unable to copy file.' );
			}
		}
	}

	public function move( string $from_abs, string $to_abs ): void {
		$this->rename( $from_abs, $to_abs );
	}

	public function delete( string $abs ): void {
		if ( ! file_exists( $abs ) ) {
			throw new FilesystemException( 'Path not found.' );
		}
		if ( is_dir( $abs ) ) {
			$this->delete_dir( $abs );
		} elseif ( ! @unlink( $abs ) ) { // phpcs:ignore
			throw new FilesystemException( 'Unable to delete file.' );
		}
	}

	private function delete_dir( string $dir ): void {
		$items = $this->list_dir( $dir );
		foreach ( $items as $item ) {
			if ( is_dir( $item['abs'] ) ) {
				$this->delete_dir( $item['abs'] );
			} elseif ( ! @unlink( $item['abs'] ) ) { // phpcs:ignore
				throw new FilesystemException( 'Unable to delete file.' );
			}
		}
		if ( ! @rmdir( $dir ) ) { // phpcs:ignore
			throw new FilesystemException( 'Unable to delete folder.' );
		}
	}

	public function stat( string $abs ): array {
		if ( ! file_exists( $abs ) ) {
			throw new FilesystemException( 'Path not found.' );
		}
		$is_dir = is_dir( $abs );
		$perms  = @fileperms( $abs ); // phpcs:ignore
		return array(
			'name'        => basename( $abs ),
			'size'        => $is_dir ? 0 : (int) @filesize( $abs ), // phpcs:ignore
			'mtime'       => (int) @filemtime( $abs ), // phpcs:ignore
			'type'        => $is_dir ? 'dir' : 'file',
			'permissions' => false !== $perms ? substr( sprintf( '%o', $perms ), -4 ) : '----',
			'is_readable' => is_readable( $abs ),
			'is_writable' => is_writable( $abs ),
		);
	}
}
