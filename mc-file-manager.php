<?php
/**
 * Plugin Name:       MC File Manager
 * Plugin URI:        https://example.com/mc-file-manager
 * Description:       A safe, VS Code-style file manager for the entire WordPress installation. Browse, edit, snapshot, and recover files from a single admin SPA.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            MC
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mc-file-manager
 *
 * @package MCFM
 */

defined( 'ABSPATH' ) || exit;

define( 'MCFM_VERSION', '1.0.0' );
define( 'MCFM_PLUGIN_FILE', __FILE__ );
define( 'MCFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCFM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MCFM_REST_NAMESPACE', 'mcfm/v1' );

require_once MCFM_PLUGIN_DIR . 'includes/class-mcfm-autoloader.php';

\MCFM\Autoloader::register();

register_activation_hook( __FILE__, array( '\MCFM\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\MCFM\Deactivator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
function mcfm() {
	return \MCFM\Plugin::instance();
}

add_action( 'plugins_loaded', 'mcfm' );
