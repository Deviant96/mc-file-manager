<?php
/**
 * High-level filesystem facade used by services and REST controllers.
 * Combines the driver with the path resolver to produce safe, UI-ready data.
 *
 * @package MCFM
 */

namespace MCFM\Filesystem;

use MCFM\Security\PathResolver;

defined( 'ABSPATH' ) || exit;

class FilesystemService {

	private FilesystemDriver $driver;
	private PathResolver $resolver;

	public function __construct( FilesystemDriver $driver, PathResolver $resolver ) {
		$this->driver   = $driver;
		$this->resolver = $resolver;
	}

	public function driver(): FilesystemDriver {
		return $this->driver;
	}

	public function resolver(): PathResolver {
		return $this->resolver;
	}

	public function exists( string $abs ): bool {
		return $this->driver->exists( $abs );
	}

	public function is_dir( string $abs ): bool {
		return $this->driver->is_dir( $abs );
	}

	public function read( string $abs ): string {
		return $this->driver->read( $abs );
	}

	public function write( string $abs, string $contents ): void {
		$this->driver->write( $abs, $contents );
	}

	/**
	 * Build a UI entry descriptor for an absolute path.
	 *
	 * @return array<string,mixed>
	 */
	public function entry( string $abs ): array {
		$stat     = $this->driver->stat( $abs );
		$relative = $this->resolver->to_relative( $abs );
		$is_dir   = 'dir' === $stat['type'];

		return array(
			'name'        => $stat['name'],
			'path'        => $relative,
			'type'        => $stat['type'],
			'isDir'       => $is_dir,
			'size'        => $stat['size'],
			'mtime'       => $stat['mtime'],
			'modified'    => $stat['mtime'] ? gmdate( 'c', $stat['mtime'] ) : null,
			'permissions' => $stat['permissions'],
			'readable'    => $stat['is_readable'],
			'writable'    => $stat['is_writable'],
			'ext'         => $is_dir ? '' : strtolower( pathinfo( $stat['name'], PATHINFO_EXTENSION ) ),
		);
	}

	/**
	 * List a directory as UI entries, folders first then files, alpha sorted.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_entries( string $abs ): array {
		$raw     = $this->driver->list_dir( $abs );
		$entries = array();
		foreach ( $raw as $item ) {
			try {
				$entries[] = $this->entry( $item['abs'] );
			} catch ( FilesystemException $e ) {
				continue; // Skip unreadable entries rather than failing the listing.
			}
		}

		usort(
			$entries,
			static function ( $a, $b ) {
				if ( $a['isDir'] !== $b['isDir'] ) {
					return $a['isDir'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $entries;
	}

	/**
	 * Whether a directory has at least one subdirectory (for lazy tree hints).
	 */
	public function has_subdirectories( string $abs ): bool {
		try {
			foreach ( $this->driver->list_dir( $abs ) as $item ) {
				if ( $this->driver->is_dir( $item['abs'] ) ) {
					return true;
				}
			}
		} catch ( FilesystemException $e ) {
			return false;
		}
		return false;
	}

	/**
	 * Directory-only listing for the tree, with a hasChildren hint.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_directories( string $abs ): array {
		$raw  = $this->driver->list_dir( $abs );
		$dirs = array();
		foreach ( $raw as $item ) {
			if ( ! $this->driver->is_dir( $item['abs'] ) ) {
				continue;
			}
			$dirs[] = array(
				'name'        => $item['name'],
				'path'        => $this->resolver->to_relative( $item['abs'] ),
				'hasChildren' => $this->has_subdirectories( $item['abs'] ),
			);
		}
		usort( $dirs, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
		return $dirs;
	}
}
