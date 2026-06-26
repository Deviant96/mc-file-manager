<?php
/**
 * REST API controller. Registers and handles all /mcfm/v1 endpoints.
 * Every route is capability-checked; nonce is enforced by the WP REST layer.
 *
 * @package MCFM
 */

namespace MCFM\Rest;

use MCFM\Filesystem\FilesystemException;
use MCFM\Filesystem\FilesystemService;
use MCFM\Security\PathException;
use MCFM\Security\SecurityService;
use MCFM\Service\AuditService;
use MCFM\Service\PreviewService;
use MCFM\Service\RevisionService;
use MCFM\Service\SettingsService;
use MCFM\Service\TrashService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class RestController {

	private SecurityService $security;
	private FilesystemService $fs;
	private AuditService $audit;
	private TrashService $trash;
	private RevisionService $revision;
	private PreviewService $preview;
	private SettingsService $settings;

	/**
	 * @param array<string,object> $services
	 */
	public function __construct( array $services ) {
		$this->security = $services['security'];
		$this->fs       = $services['filesystem'];
		$this->audit    = $services['audit'];
		$this->trash    = $services['trash'];
		$this->revision = $services['revision'];
		$this->preview  = $services['preview'];
		$this->settings = $services['settings'];
	}

	private function permission(): callable {
		return array( $this->security, 'rest_permission_check' );
	}

	public function register_routes(): void {
		$ns   = MCFM_REST_NAMESPACE;
		$perm = $this->permission();

		$get  = WP_REST_Server::READABLE;
		$post = WP_REST_Server::CREATABLE;

		register_rest_route( $ns, '/tree', array( 'methods' => $get, 'callback' => array( $this, 'get_tree' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/children', array( 'methods' => $get, 'callback' => array( $this, 'get_children' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/breadcrumbs', array( 'methods' => $get, 'callback' => array( $this, 'get_breadcrumbs' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/list', array( 'methods' => $get, 'callback' => array( $this, 'get_list' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/folder', array( 'methods' => $post, 'callback' => array( $this, 'create_folder' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/file', array(
			array( 'methods' => $get, 'callback' => array( $this, 'read_file' ), 'permission_callback' => $perm ),
			array( 'methods' => $post, 'callback' => array( $this, 'create_file' ), 'permission_callback' => $perm ),
		) );
		register_rest_route( $ns, '/rename', array( 'methods' => $post, 'callback' => array( $this, 'rename' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/move', array( 'methods' => $post, 'callback' => array( $this, 'move' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/copy', array( 'methods' => $post, 'callback' => array( $this, 'copy' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/delete', array( 'methods' => $post, 'callback' => array( $this, 'delete' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/restore', array( 'methods' => $post, 'callback' => array( $this, 'restore' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/save', array( 'methods' => $post, 'callback' => array( $this, 'save_file' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/snapshots', array( 'methods' => $get, 'callback' => array( $this, 'list_snapshots' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/snapshot', array( 'methods' => $post, 'callback' => array( $this, 'create_snapshot' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/snapshot/(?P<id>\d+)', array( 'methods' => $get, 'callback' => array( $this, 'read_snapshot' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/snapshot/restore', array( 'methods' => $post, 'callback' => array( $this, 'restore_snapshot' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/search', array( 'methods' => $get, 'callback' => array( $this, 'search' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/upload', array( 'methods' => $post, 'callback' => array( $this, 'upload' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/download', array( 'methods' => $get, 'callback' => array( $this, 'download' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/raw', array( 'methods' => $get, 'callback' => array( $this, 'raw' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/properties', array( 'methods' => $get, 'callback' => array( $this, 'properties' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/logs', array( 'methods' => $get, 'callback' => array( $this, 'logs' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/trash', array( 'methods' => $get, 'callback' => array( $this, 'list_trash' ), 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/purge', array( 'methods' => $post, 'callback' => array( $this, 'purge' ), 'permission_callback' => $perm ) );

		register_rest_route( $ns, '/settings', array(
			array( 'methods' => $get, 'callback' => array( $this, 'get_settings' ), 'permission_callback' => $perm ),
			array( 'methods' => $post, 'callback' => array( $this, 'update_settings' ), 'permission_callback' => $perm ),
		) );
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	private function ok( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	private function fail( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function param_path( WP_REST_Request $request, string $key = 'path' ): string {
		return (string) $request->get_param( $key );
	}

	/* ------------------------------------------------------------------ *
	 * Tree & navigation
	 * ------------------------------------------------------------------ */

	public function get_tree( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( '' );
			$this->audit->log( 'browse', 'ok', '' );
			return $this->ok(
				array(
					'root'     => array( 'name' => __( 'WordPress Root', 'mc-file-manager' ), 'path' => '' ),
					'children' => $this->fs->list_directories( $abs ),
				)
			);
		} catch ( \Throwable $e ) {
			return $this->fail( 'mcfm_tree', $e->getMessage() );
		}
	}

	public function get_children( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			if ( ! $this->fs->is_dir( $abs ) ) {
				return $this->fail( 'mcfm_not_dir', __( 'Not a directory.', 'mc-file-manager' ) );
			}
			return $this->ok( array( 'children' => $this->fs->list_directories( $abs ) ) );
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_children', $e->getMessage() );
		}
	}

	public function get_breadcrumbs( WP_REST_Request $request ) {
		try {
			$abs      = $this->security->authorize_path( $this->param_path( $request ) );
			$relative = $this->fs->resolver()->to_relative( $abs );
			$crumbs   = array( array( 'name' => __( 'Root', 'mc-file-manager' ), 'path' => '' ) );
			$acc      = '';
			if ( '' !== $relative ) {
				foreach ( explode( '/', $relative ) as $segment ) {
					$acc      = '' === $acc ? $segment : $acc . '/' . $segment;
					$crumbs[] = array( 'name' => $segment, 'path' => $acc );
				}
			}
			return $this->ok( array( 'breadcrumbs' => $crumbs ) );
		} catch ( PathException $e ) {
			return $this->fail( 'mcfm_breadcrumbs', $e->getMessage() );
		}
	}

	public function get_list( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			if ( ! $this->fs->is_dir( $abs ) ) {
				return $this->fail( 'mcfm_not_dir', __( 'Not a directory.', 'mc-file-manager' ) );
			}
			$this->audit->log( 'browse', 'ok', $this->fs->resolver()->to_relative( $abs ) );
			return $this->ok(
				array(
					'path'    => $this->fs->resolver()->to_relative( $abs ),
					'entries' => $this->fs->list_entries( $abs ),
				)
			);
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_list', $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Create / mutate
	 * ------------------------------------------------------------------ */

	public function create_folder( WP_REST_Request $request ) {
		return $this->create_node( $request, true );
	}

	public function create_file( WP_REST_Request $request ) {
		return $this->create_node( $request, false );
	}

	private function create_node( WP_REST_Request $request, bool $is_dir ) {
		$parent = $this->param_path( $request );
		$name   = sanitize_file_name( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return $this->fail( 'mcfm_name', __( 'A valid name is required.', 'mc-file-manager' ) );
		}
		try {
			$parent_abs = $this->security->authorize_path( $parent );
			$target_rel = ( '' === $parent ? '' : trailingslashit( $this->fs->resolver()->to_relative( $parent_abs ) ) ) . $name;
			$abs        = $this->security->authorize_path( $target_rel );

			if ( $is_dir ) {
				$this->fs->driver()->make_dir( $abs );
			} else {
				$this->fs->driver()->make_file( $abs, (string) $request->get_param( 'content' ) );
			}

			$rel = $this->fs->resolver()->to_relative( $abs );
			$this->audit->log( $is_dir ? 'create folder' : 'create file', 'ok', $rel );
			return $this->ok( array( 'entry' => $this->fs->entry( $abs ) ), 201 );
		} catch ( PathException | FilesystemException $e ) {
			$this->audit->log( $is_dir ? 'create folder' : 'create file', 'error', $name, '', $e->getMessage() );
			return $this->fail( 'mcfm_create', $e->getMessage() );
		}
	}

	public function rename( WP_REST_Request $request ) {
		$path = $this->param_path( $request );
		$name = sanitize_file_name( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return $this->fail( 'mcfm_name', __( 'A valid name is required.', 'mc-file-manager' ) );
		}
		try {
			$abs    = $this->security->authorize_path( $path );
			$target = trailingslashit( dirname( $this->fs->resolver()->to_relative( $abs ) ) ) . $name;
			$target = ltrim( str_replace( './', '', $target ), '/' );
			$to_abs = $this->security->authorize_path( $target );

			$this->fs->driver()->rename( $abs, $to_abs );
			$this->audit->log( 'rename', 'ok', $this->fs->resolver()->to_relative( $to_abs ), $this->fs->resolver()->to_relative( $abs ) );
			return $this->ok( array( 'entry' => $this->fs->entry( $to_abs ) ) );
		} catch ( PathException | FilesystemException $e ) {
			$this->audit->log( 'rename', 'error', $path, '', $e->getMessage() );
			return $this->fail( 'mcfm_rename', $e->getMessage() );
		}
	}

	public function move( WP_REST_Request $request ) {
		return $this->transfer( $request, 'move' );
	}

	public function copy( WP_REST_Request $request ) {
		return $this->transfer( $request, 'copy' );
	}

	private function transfer( WP_REST_Request $request, string $op ) {
		$source = (string) $request->get_param( 'source' );
		$dest   = (string) $request->get_param( 'destination' );
		try {
			$src_abs  = $this->security->authorize_path( $source );
			$dest_abs = $this->security->authorize_path( $dest );

			// Destination is a directory: keep the source name inside it.
			if ( $this->fs->is_dir( $dest_abs ) ) {
				$dest_rel = trailingslashit( $this->fs->resolver()->to_relative( $dest_abs ) ) . basename( $src_abs );
				$dest_abs = $this->security->authorize_path( $dest_rel );
			}

			if ( 'move' === $op ) {
				$this->fs->driver()->move( $src_abs, $dest_abs );
			} else {
				$this->fs->driver()->copy( $src_abs, $dest_abs );
			}

			$this->audit->log( $op, 'ok', $this->fs->resolver()->to_relative( $dest_abs ), $this->fs->resolver()->to_relative( $src_abs ) );
			return $this->ok( array( 'entry' => $this->fs->entry( $dest_abs ) ) );
		} catch ( PathException | FilesystemException $e ) {
			$this->audit->log( $op, 'error', $dest, $source, $e->getMessage() );
			return $this->fail( 'mcfm_' . $op, $e->getMessage() );
		}
	}

	public function delete( WP_REST_Request $request ) {
		$paths = $request->get_param( 'paths' );
		if ( ! is_array( $paths ) ) {
			$single = $this->param_path( $request );
			$paths  = '' !== $single ? array( $single ) : array();
		}
		if ( empty( $paths ) ) {
			return $this->fail( 'mcfm_delete', __( 'No paths provided.', 'mc-file-manager' ) );
		}

		$results = array();
		$errors  = array();
		foreach ( $paths as $path ) {
			try {
				$abs = $this->security->authorize_path( (string) $path );
				$rel = $this->fs->resolver()->to_relative( $abs );
				if ( $this->settings->trash_enabled() ) {
					$id        = $this->trash->trash( $abs );
					$results[] = array( 'path' => $rel, 'trashId' => $id, 'trashed' => true );
				} else {
					$this->fs->driver()->delete( $abs );
					$results[] = array( 'path' => $rel, 'trashed' => false );
				}
				$this->audit->log( 'delete', 'ok', $rel );
			} catch ( PathException | FilesystemException $e ) {
				$errors[] = array( 'path' => (string) $path, 'message' => $e->getMessage() );
				$this->audit->log( 'delete', 'error', (string) $path, '', $e->getMessage() );
			}
		}

		return $this->ok( array( 'deleted' => $results, 'errors' => $errors ) );
	}

	public function restore( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		try {
			$restored = $this->trash->restore( $id );
			$this->audit->log( 'restore', 'ok', $restored );
			return $this->ok( array( 'restored' => $restored ) );
		} catch ( FilesystemException | PathException $e ) {
			$this->audit->log( 'restore', 'error', (string) $id, '', $e->getMessage() );
			return $this->fail( 'mcfm_restore', $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Editor
	 * ------------------------------------------------------------------ */

	public function read_file( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			if ( ! $this->fs->exists( $abs ) || $this->fs->is_dir( $abs ) ) {
				return $this->fail( 'mcfm_not_file', __( 'File not found.', 'mc-file-manager' ) );
			}

			$entry    = $this->fs->entry( $abs );
			$kind      = $this->preview->classify( $entry['name'] );
			$max       = $this->settings->max_editable_bytes();
			$too_large = $entry['size'] > $max;

			$payload = array(
				'entry'    => $entry,
				'kind'     => $kind,
				'language' => $this->preview->monaco_language( $entry['name'] ),
				'tooLarge' => $too_large,
				'maxBytes' => $max,
				'content'  => null,
			);

			if ( ! $too_large && 'text' === $kind ) {
				$payload['content'] = $this->fs->read( $abs );
			}

			$this->audit->log( 'open', 'ok', $entry['path'] );
			return $this->ok( $payload );
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_read', $e->getMessage() );
		}
	}

	public function save_file( WP_REST_Request $request ) {
		$path    = $this->param_path( $request );
		$content = (string) $request->get_param( 'content' );
		try {
			$abs = $this->security->authorize_path( $path );
			if ( $this->fs->is_dir( $abs ) ) {
				return $this->fail( 'mcfm_not_file', __( 'Cannot save a directory.', 'mc-file-manager' ) );
			}

			$max = $this->settings->max_editable_bytes();
			if ( strlen( $content ) > $max ) {
				return $this->fail( 'mcfm_too_large', __( 'Content exceeds the maximum editable size.', 'mc-file-manager' ), 413 );
			}

			// Non-negotiable: snapshot before overwrite.
			$snapshot_id = $this->revision->snapshot( $abs );
			$this->fs->write( $abs, $content );

			$rel = $this->fs->resolver()->to_relative( $abs );
			$this->audit->log( 'save', 'ok', $rel, '', $snapshot_id ? 'snapshot #' . $snapshot_id : '' );
			return $this->ok(
				array(
					'entry'      => $this->fs->entry( $abs ),
					'snapshotId' => $snapshot_id,
				)
			);
		} catch ( PathException | FilesystemException $e ) {
			$this->audit->log( 'save', 'error', $path, '', $e->getMessage() );
			return $this->fail( 'mcfm_save', $e->getMessage() );
		}
	}

	public function create_snapshot( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			$id  = $this->revision->snapshot( $abs );
			return $this->ok( array( 'snapshotId' => $id ) );
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_snapshot', $e->getMessage() );
		}
	}

	public function list_snapshots( WP_REST_Request $request ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			return $this->ok( array( 'snapshots' => $this->revision->list_for( $abs ) ) );
		} catch ( PathException $e ) {
			return $this->fail( 'mcfm_snapshots', $e->getMessage() );
		}
	}

	public function read_snapshot( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		try {
			return $this->ok( array( 'content' => $this->revision->read_snapshot( $id ) ) );
		} catch ( FilesystemException $e ) {
			return $this->fail( 'mcfm_snapshot_read', $e->getMessage() );
		}
	}

	public function restore_snapshot( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		try {
			$abs     = $this->revision->original_abs_for( $id );
			$content = $this->revision->read_snapshot( $id );
			// Snapshot the current state before rolling back.
			$this->revision->snapshot( $abs );
			$this->fs->write( $abs, $content );
			$rel = $this->fs->resolver()->to_relative( $abs );
			$this->audit->log( 'restore', 'ok', $rel, '', 'snapshot rollback #' . $id );
			return $this->ok( array( 'entry' => $this->fs->entry( $abs ), 'content' => $content ) );
		} catch ( FilesystemException | PathException $e ) {
			return $this->fail( 'mcfm_snapshot_restore', $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Search
	 * ------------------------------------------------------------------ */

	public function search( WP_REST_Request $request ) {
		$query = trim( (string) $request->get_param( 'query' ) );
		$path  = $this->param_path( $request );
		if ( strlen( $query ) < 1 ) {
			return $this->ok( array( 'results' => array() ) );
		}
		try {
			$base    = $this->security->authorize_path( $path );
			$results = array();
			$this->search_walk( $base, strtolower( $query ), $results, 0 );
			$this->audit->log( 'search', 'ok', $this->fs->resolver()->to_relative( $base ), '', $query );
			return $this->ok( array( 'results' => array_slice( $results, 0, 500 ) ) );
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_search', $e->getMessage() );
		}
	}

	private function search_walk( string $abs, string $needle, array &$results, int $depth ): void {
		if ( $depth > 25 || count( $results ) >= 500 ) {
			return;
		}
		try {
			$items = $this->fs->driver()->list_dir( $abs );
		} catch ( FilesystemException $e ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( false !== strpos( strtolower( $item['name'] ), $needle ) ) {
				try {
					$results[] = $this->fs->entry( $item['abs'] );
				} catch ( FilesystemException $e ) {
					continue;
				}
			}
			if ( $this->fs->is_dir( $item['abs'] ) ) {
				$this->search_walk( $item['abs'], $needle, $results, $depth + 1 );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Upload / download
	 * ------------------------------------------------------------------ */

	public function upload( WP_REST_Request $request ) {
		$path  = $this->param_path( $request );
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return $this->fail( 'mcfm_upload', __( 'No file uploaded.', 'mc-file-manager' ) );
		}
		$file = $files['file'];
		if ( ! empty( $file['error'] ) ) {
			return $this->fail( 'mcfm_upload', __( 'Upload failed.', 'mc-file-manager' ) );
		}

		$name = sanitize_file_name( $file['name'] );
		try {
			$parent_abs = $this->security->authorize_path( $path );
			$target_rel = ( '' === $path ? '' : trailingslashit( $this->fs->resolver()->to_relative( $parent_abs ) ) ) . $name;
			$abs        = $this->security->authorize_path( $target_rel );

			if ( $this->fs->exists( $abs ) ) {
				return $this->fail( 'mcfm_exists', __( 'A file with that name already exists.', 'mc-file-manager' ) );
			}
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				return $this->fail( 'mcfm_upload', __( 'Invalid upload.', 'mc-file-manager' ) );
			}
			if ( ! @move_uploaded_file( $file['tmp_name'], $abs ) ) { // phpcs:ignore
				return $this->fail( 'mcfm_upload', __( 'Unable to store uploaded file.', 'mc-file-manager' ) );
			}

			$rel = $this->fs->resolver()->to_relative( $abs );
			$this->audit->log( 'upload', 'ok', $rel );
			return $this->ok( array( 'entry' => $this->fs->entry( $abs ) ), 201 );
		} catch ( PathException | FilesystemException $e ) {
			$this->audit->log( 'upload', 'error', $name, '', $e->getMessage() );
			return $this->fail( 'mcfm_upload', $e->getMessage() );
		}
	}

	public function download( WP_REST_Request $request ) {
		return $this->stream( $request, true );
	}

	public function raw( WP_REST_Request $request ) {
		return $this->stream( $request, false );
	}

	private function stream( WP_REST_Request $request, bool $as_attachment ) {
		try {
			$abs = $this->security->authorize_path( $this->param_path( $request ) );
			if ( ! $this->fs->exists( $abs ) || $this->fs->is_dir( $abs ) ) {
				return $this->fail( 'mcfm_not_file', __( 'File not found.', 'mc-file-manager' ), 404 );
			}
			$rel = $this->fs->resolver()->to_relative( $abs );
			$this->audit->log( $as_attachment ? 'download' : 'open', 'ok', $rel );

			$mime = $this->preview->mime_for( $abs );
			nocache_headers();
			header( 'Content-Type: ' . $mime );
			header( 'Content-Length: ' . filesize( $abs ) );
			if ( $as_attachment ) {
				header( 'Content-Disposition: attachment; filename="' . basename( $abs ) . '"' );
			} else {
				header( 'Content-Disposition: inline; filename="' . basename( $abs ) . '"' );
			}
			readfile( $abs ); // phpcs:ignore
			exit;
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_download', $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Properties / logs / trash / settings
	 * ------------------------------------------------------------------ */

	public function properties( WP_REST_Request $request ) {
		try {
			$abs   = $this->security->authorize_path( $this->param_path( $request ) );
			$entry = $this->fs->entry( $abs );
			$snaps = $this->fs->is_dir( $abs ) ? array() : $this->revision->list_for( $abs );
			return $this->ok(
				array(
					'entry'        => $entry,
					'preview'      => $this->preview->classify( $entry['name'] ),
					'snapshots'    => $snaps,
					'hasSnapshots' => ! empty( $snaps ),
				)
			);
		} catch ( PathException | FilesystemException $e ) {
			return $this->fail( 'mcfm_properties', $e->getMessage() );
		}
	}

	public function logs( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) ( $request->get_param( 'per_page' ) ?: 50 );
		$action   = sanitize_text_field( (string) $request->get_param( 'action_filter' ) );
		$result   = $this->audit->paginate( $page, $per_page, $action );
		return $this->ok(
			array(
				'items' => $result['items'],
				'total' => $result['total'],
				'page'  => $page,
			)
		);
	}

	public function list_trash( WP_REST_Request $request ) {
		return $this->ok( array( 'items' => $this->trash->list_items() ) );
	}

	public function purge( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		try {
			$this->trash->purge( $id );
			$this->audit->log( 'purge', 'ok', (string) $id );
			return $this->ok( array( 'purged' => $id ) );
		} catch ( FilesystemException $e ) {
			return $this->fail( 'mcfm_purge', $e->getMessage() );
		}
	}

	public function get_settings( WP_REST_Request $request ) {
		return $this->ok( array( 'settings' => $this->settings->all() ) );
	}

	public function update_settings( WP_REST_Request $request ) {
		$incoming = $request->get_json_params();
		if ( ! is_array( $incoming ) ) {
			$incoming = $request->get_params();
		}
		$updated = $this->settings->update( is_array( $incoming ) ? $incoming : array() );
		$this->audit->log( 'settings', 'ok', '', '', 'updated settings' );
		return $this->ok( array( 'settings' => $updated ) );
	}
}
