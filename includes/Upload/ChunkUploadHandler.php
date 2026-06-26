<?php
/**
 * Chunk upload handler stub (Pro). Falls back with a clear message until implemented.
 *
 * @package MCFM
 */

namespace MCFM\Upload;

use MCFM\Filesystem\FilesystemService;
use MCFM\Security\SecurityService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ChunkUploadHandler implements UploadHandlerInterface {

	/**
	 * @return array{entry:array<string,mixed>}
	 */
	public function handle( WP_REST_Request $request, FilesystemService $fs, SecurityService $security ): array {
		throw new \RuntimeException( __( 'Chunked uploads are not yet implemented.', 'mc-file-manager' ) );
	}
}
