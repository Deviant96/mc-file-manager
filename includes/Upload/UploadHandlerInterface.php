<?php
/**
 * Upload handler interface. Standard handler today; chunk handler reserved for Pro.
 *
 * @package MCFM
 */

namespace MCFM\Upload;

use MCFM\Filesystem\FilesystemService;
use MCFM\Security\SecurityService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

interface UploadHandlerInterface {

	/**
	 * @return array{entry:array<string,mixed>}
	 */
	public function handle( WP_REST_Request $request, FilesystemService $fs, SecurityService $security ): array;
}
