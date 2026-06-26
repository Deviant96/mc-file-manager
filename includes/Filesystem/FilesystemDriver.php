<?php
/**
 * Filesystem driver contract. The UI never talks to raw PHP file functions;
 * everything flows through a driver so future SFTP/remote drivers can be added.
 *
 * All methods operate on absolute paths that have already been jailed and
 * authorized by the security layer.
 *
 * @package MCFM
 */

namespace MCFM\Filesystem;

defined( 'ABSPATH' ) || exit;

interface FilesystemDriver {

	public function exists( string $abs ): bool;

	public function is_dir( string $abs ): bool;

	public function is_file( string $abs ): bool;

	/**
	 * @return array<int,array<string,mixed>> Raw entries with name/abs path.
	 */
	public function list_dir( string $abs ): array;

	public function read( string $abs ): string;

	public function write( string $abs, string $contents ): void;

	public function make_dir( string $abs ): void;

	public function make_file( string $abs, string $contents = '' ): void;

	public function rename( string $from_abs, string $to_abs ): void;

	public function copy( string $from_abs, string $to_abs ): void;

	public function move( string $from_abs, string $to_abs ): void;

	public function delete( string $abs ): void;

	/**
	 * @return array<string,mixed> name,size,mtime,type,permissions,is_readable,is_writable
	 */
	public function stat( string $abs ): array;
}
