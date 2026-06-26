<?php
/**
 * ZIP create/extract using PHP ZipArchive (post-v1).
 *
 * @package MCFM
 */

namespace MCFM\Service;

use MCFM\Filesystem\FilesystemException;
use MCFM\Filesystem\FilesystemService;
use MCFM\Security\PathException;
use MCFM\Security\SecurityService;

defined( 'ABSPATH' ) || exit;

class ZipService {

	private FilesystemService $fs;
	private SecurityService $security;

	public function __construct( FilesystemService $fs, SecurityService $security ) {
		$this->fs       = $fs;
		$this->security = $security;
	}

	/**
	 * @param array<int,string> $relative_paths
	 * @throws PathException|FilesystemException|\RuntimeException
	 */
	public function create( array $relative_paths, string $archive_name, string $destination_relative ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( __( 'ZIP support is not available on this server.', 'mc-file-manager' ) );
		}

		$name = sanitize_file_name( $archive_name );
		if ( '' === $name ) {
			throw new \RuntimeException( __( 'Invalid archive name.', 'mc-file-manager' ) );
		}
		if ( false === strpos( $name, '.' ) ) {
			$name .= '.zip';
		}

		$parent_abs = $this->security->authorize_path( $destination_relative );
		$parent_rel = $this->fs->resolver()->to_relative( $parent_abs );
		$target_rel = ( '' === $parent_rel ? '' : trailingslashit( $parent_rel ) ) . $name;
		$target_abs = $this->security->authorize_path( $target_rel );

		if ( $this->fs->exists( $target_abs ) ) {
			throw new \RuntimeException( __( 'A file with that name already exists.', 'mc-file-manager' ) );
		}

		$zip = new \ZipArchive();
		$tmp = wp_tempnam( $name );
		if ( false === $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( __( 'Could not create ZIP archive.', 'mc-file-manager' ) );
		}

		foreach ( $relative_paths as $rel ) {
			$abs = $this->security->authorize_path( $rel );
			$arc = $rel;
			if ( $this->fs->is_dir( $abs ) ) {
				$this->add_dir_to_zip( $zip, $abs, $arc );
			} else {
				$zip->addFile( $abs, $arc );
			}
		}

		$zip->close();
		$this->fs->driver()->copy( $tmp, $target_abs );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $this->fs->entry( $target_abs );
	}

	/**
	 * Build a temporary ZIP for download (not saved into the file tree).
	 *
	 * @param array<int,string> $relative_paths
	 * @throws PathException|FilesystemException|\RuntimeException
	 */
	public function create_temp_archive( array $relative_paths ): string {
		if ( empty( $relative_paths ) ) {
			throw new \RuntimeException( __( 'No paths selected.', 'mc-file-manager' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( __( 'ZIP support is not available on this server.', 'mc-file-manager' ) );
		}

		$zip = new \ZipArchive();
		$tmp = wp_tempnam( 'mcfm-download.zip' );
		if ( false === $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( __( 'Could not create ZIP archive.', 'mc-file-manager' ) );
		}

		foreach ( $relative_paths as $rel ) {
			$abs = $this->security->authorize_path( $rel );
			$arc = $rel;
			if ( $this->fs->is_dir( $abs ) ) {
				$this->add_dir_to_zip( $zip, $abs, $arc );
			} elseif ( $this->fs->exists( $abs ) ) {
				$zip->addFile( $abs, $arc );
			}
		}

		$zip->close();
		return $tmp;
	}

	/**
	 * @throws PathException|FilesystemException|\RuntimeException
	 */
	public function extract( string $archive_relative, string $destination_relative ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( __( 'ZIP support is not available on this server.', 'mc-file-manager' ) );
		}

		$archive_abs = $this->security->authorize_path( $archive_relative );
		$dest_abs    = $this->security->authorize_path( $destination_relative );

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $archive_abs ) ) {
			throw new \RuntimeException( __( 'Could not open ZIP archive.', 'mc-file-manager' ) );
		}

		$root = $this->fs->resolver()->root();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );
			if ( false === $entry || '' === $entry ) {
				continue;
			}
			// Reject path traversal inside archives.
			$normalized = wp_normalize_path( $entry );
			if ( 0 === strpos( $normalized, '../' ) || false !== strpos( $normalized, '/../' ) ) {
				$zip->close();
				throw new \RuntimeException( __( 'ZIP archive contains unsafe paths.', 'mc-file-manager' ) );
			}
			$target_abs = wp_normalize_path( trailingslashit( $dest_abs ) . $normalized );
			if ( 0 !== strpos( $target_abs, $root ) ) {
				$zip->close();
				throw new \RuntimeException( __( 'ZIP extraction path escapes the site root.', 'mc-file-manager' ) );
			}
		}

		$zip->extractTo( $dest_abs );
		$zip->close();

		return $this->fs->entry( $dest_abs );
	}

	private function add_dir_to_zip( \ZipArchive $zip, string $abs_dir, string $arc_prefix ): void {
		$items = $this->fs->driver()->list_dir( $abs_dir );
		foreach ( $items as $item ) {
			$arc = ( '' === $arc_prefix ? '' : trailingslashit( $arc_prefix ) ) . $item['name'];
			if ( $this->fs->is_dir( $item['abs'] ) ) {
				$zip->addEmptyDir( trailingslashit( $arc ) );
				$this->add_dir_to_zip( $zip, $item['abs'], $arc );
			} else {
				$zip->addFile( $item['abs'], $arc );
			}
		}
	}
}
