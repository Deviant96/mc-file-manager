<?php
/**
 * Standard single-request upload handler (Free v1).
 *
 * @package MCFM
 */

namespace MCFM\Upload;

use MCFM\Filesystem\FilesystemException;
use MCFM\Filesystem\FilesystemService;
use MCFM\Security\PathException;
use MCFM\Security\SecurityService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class StandardUploadHandler implements UploadHandlerInterface {

	/**
	 * @return array{entry:array<string,mixed>}
	 * @throws PathException|FilesystemException|\RuntimeException
	 */
	public function handle( WP_REST_Request $request, FilesystemService $fs, SecurityService $security ): array {
		$path  = sanitize_text_field( (string) $request->get_param( 'path' ) );
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			throw new \RuntimeException( __( 'No file uploaded.', 'mc-file-manager' ) );
		}

		$file = $files['file'];
		if ( ! empty( $file['error'] ) ) {
			throw new \RuntimeException( __( 'Upload failed.', 'mc-file-manager' ) );
		}

		$name = sanitize_file_name( $file['name'] );
		$parent_abs = $security->authorize_path( $path );
		$parent_rel = $fs->resolver()->to_relative( $parent_abs );
		$target_rel = ( '' === $parent_rel ? '' : trailingslashit( $parent_rel ) ) . $name;
		$abs        = $security->authorize_path( $target_rel );

		if ( $fs->exists( $abs ) ) {
			throw new \RuntimeException( __( 'A file with that name already exists.', 'mc-file-manager' ) );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			throw new \RuntimeException( __( 'Invalid upload.', 'mc-file-manager' ) );
		}

		if ( ! @move_uploaded_file( $file['tmp_name'], $abs ) ) { // phpcs:ignore
			throw new \RuntimeException( __( 'Unable to store uploaded file.', 'mc-file-manager' ) );
		}

		return array( 'entry' => $fs->entry( $abs ) );
	}
}
