<?php
/**
 * Admin bootstrap: registers the menu page and mounts the Vue SPA.
 *
 * @package MCFM
 */

namespace MCFM\Admin;

use MCFM\Security\SecurityService;
use MCFM\Service\SettingsService;

defined( 'ABSPATH' ) || exit;

class AdminPage {

	private const MENU_SLUG = 'mc-file-manager';

	private SettingsService $settings;
	private SecurityService $security;
	private string $hook_suffix = '';

	public function __construct( SettingsService $settings, SecurityService $security ) {
		$this->settings = $settings;
		$this->security = $security;
	}

	public function register_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'MC File Manager', 'mc-file-manager' ),
			__( 'File Manager', 'mc-file-manager' ),
			$this->security->required_capability(),
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-media-code',
			80
		);
	}

	public function render_page(): void {
		if ( ! $this->security->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access the file manager.', 'mc-file-manager' ) );
		}
		echo '<div class="wrap mcfm-wrap"><div id="mcfm-app"></div></div>';
	}

	/**
	 * Enqueue the compiled SPA assets only on the plugin page.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$build_url = MCFM_PLUGIN_URL . 'assets/build/';
		$build_dir = MCFM_PLUGIN_DIR . 'assets/build/';
		$js        = $build_dir . 'mcfm-app.js';
		$css       = $build_dir . 'mcfm-app.css';

		$version = file_exists( $js ) ? (string) filemtime( $js ) : MCFM_VERSION;

		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'mcfm-app',
				$build_url . 'mcfm-app.js',
				array(),
				$version,
				true
			);
		}

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'mcfm-app',
				$build_url . 'mcfm-app.css',
				array(),
				$version
			);
		}

		wp_localize_script(
			'mcfm-app',
			'MCFM_BOOT',
			array(
				'restUrl'   => esc_url_raw( rest_url( MCFM_REST_NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'root'      => '',
				'settings'  => $this->settings->all(),
				'maxUpload' => wp_max_upload_size(),
				'user'      => array(
					'id'   => get_current_user_id(),
					'name' => wp_get_current_user()->display_name,
				),
				'version'   => MCFM_VERSION,
			)
		);
	}

	/**
	 * ES module type attribute for the SPA bundle.
	 */
	public static function add_module_type( string $tag, string $handle ): string {
		if ( 'mcfm-app' === $handle ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}
}
