<?php
/**
 * Main plugin orchestrator and lightweight service container.
 *
 * @package MCFM
 */

namespace MCFM;

use MCFM\Admin\AdminPage;
use MCFM\Filesystem\FilesystemService;
use MCFM\Filesystem\LocalDriver;
use MCFM\Repository\LogsRepository;
use MCFM\Repository\SettingsRepository;
use MCFM\Repository\SnapshotsRepository;
use MCFM\Repository\TrashRepository;
use MCFM\Rest\RestController;
use MCFM\Security\PathResolver;
use MCFM\Security\SecurityService;
use MCFM\Service\AuditService;
use MCFM\Service\PreviewService;
use MCFM\Service\RevisionService;
use MCFM\Service\SettingsService;
use MCFM\Service\TrashService;
use MCFM\Service\RecentlyOpenedService;
use MCFM\Service\OpenRegistryService;
use MCFM\Service\ZipService;
use MCFM\Service\AdvancedSearchService;
use MCFM\Service\RoleFolderService;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	/** @var array<string,object> */
	private array $services = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function boot(): void {
		$this->register_services();
		$this->register_hooks();
	}

	/**
	 * Wire up the layered services. Order matters for dependencies.
	 */
	private function register_services(): void {
		// Repositories.
		$settings_repo  = new SettingsRepository();
		$logs_repo      = new LogsRepository();
		$trash_repo     = new TrashRepository();
		$snapshots_repo = new SnapshotsRepository();

		// Settings sits near the bottom; many services read it.
		$settings = new SettingsService( $settings_repo );

		// Security: resolves and jails every path.
		$resolver = new PathResolver( $this->root_path() );
		$security = new SecurityService( $resolver );

		// Audit.
		$audit = new AuditService( $logs_repo );

		// Filesystem abstraction.
		$driver     = new LocalDriver();
		$filesystem = new FilesystemService( $driver, $resolver );

		// Trash + revisions operate on top of the filesystem.
		$trash    = new TrashService( $filesystem, $resolver, $trash_repo, $this->trash_dir() );
		$revision = new RevisionService( $filesystem, $resolver, $snapshots_repo, $this->snapshot_dir(), $settings );
		$preview  = new PreviewService( $filesystem, $resolver );
		$recent   = new RecentlyOpenedService();
		$registry = new OpenRegistryService();
		$zip      = new ZipService( $filesystem, $security );
		$adv_search = new AdvancedSearchService();

		$role_folders = new RoleFolderService( $settings, $resolver );
		$role_folders->register();

		$this->services = array(
			'settings_repo'  => $settings_repo,
			'logs_repo'      => $logs_repo,
			'trash_repo'     => $trash_repo,
			'snapshots_repo' => $snapshots_repo,
			'settings'       => $settings,
			'resolver'       => $resolver,
			'security'       => $security,
			'audit'          => $audit,
			'filesystem'     => $filesystem,
			'trash'          => $trash,
			'revision'       => $revision,
			'preview'        => $preview,
			'recent'         => $recent,
			'open_registry'  => $registry,
			'zip'            => $zip,
			'advanced_search' => $adv_search,
		);
	}

	private function register_hooks(): void {
		$admin = new AdminPage( $this->get( 'settings' ), $this->get( 'security' ) );
		add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_filter( 'script_loader_tag', array( AdminPage::class, 'add_module_type' ), 10, 2 );

		$rest = new RestController( $this->services );
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
	}

	/**
	 * Retrieve a registered service.
	 */
	public function get( string $key ): object {
		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \RuntimeException( esc_html( "Unknown service: {$key}" ) );
		}
		return $this->services[ $key ];
	}

	/**
	 * The jailed root: the WordPress installation directory (ABSPATH).
	 */
	public function root_path(): string {
		$root = defined( 'ABSPATH' ) ? ABSPATH : getcwd();
		return rtrim( wp_normalize_path( $root ), '/' );
	}

	public function snapshot_dir(): string {
		return self::storage_dir( 'mcfm-snapshots' );
	}

	public function trash_dir(): string {
		return self::storage_dir( 'mcfm-trash' );
	}

	public function temp_dir(): string {
		return self::storage_dir( 'mcfm-temp' );
	}

	/**
	 * Resolve a managed storage directory inside uploads.
	 */
	public static function storage_dir( string $name ): string {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		return rtrim( wp_normalize_path( $base ), '/' ) . '/' . $name;
	}
}
