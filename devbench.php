<?php
/**
 * Plugin Name:       DevBench
 * Plugin URI:        https://github.com/towfique-elahe/devbench
 * Description:       An all-in-one developer workbench: debug tools, file manager, database browser, search, mail catcher and environment checks.
 * Version:           1.3.0
 * Author:            Towfique Elahe
 * Author URI:        https://towfiqueelahe.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       devbench
 * Requires at least: 6.6
 * Requires PHP:      7.4
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

define( 'DEVBENCH_VERSION', '1.3.0' );
define( 'DEVBENCH_FILE', __FILE__ );
define( 'DEVBENCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEVBENCH_URL', plugin_dir_url( __FILE__ ) );
define( 'DEVBENCH_SLUG', 'devbench' );
// Kept in step with the "Donate link" header in readme.txt.
define( 'DEVBENCH_DONATE_URL', 'https://towfiqueelahe.com/support/' );

// Autoload core classes. Order matters: helpers depends on the filesystem wrapper.
foreach ( array( 'fs', 'helpers', 'debug', 'files', 'database', 'tools', 'search', 'extra', 'reports', 'admin' ) as $devbench_module ) {
	require_once DEVBENCH_PATH . 'includes/class-' . $devbench_module . '.php';
}
unset( $devbench_module );

// Boot mail catcher early (must hook before wp_mail fires).
DevBench_Extra::init_mail_catcher();

// Boot admin UI.
if ( is_admin() ) {
	DevBench_Admin::init();
}

/**
 * Activation: record the installed version. Nothing destructive happens here.
 */
function devbench_activate() {
	update_option( 'devbench_version', DEVBENCH_VERSION );
}
register_activation_hook( __FILE__, 'devbench_activate' );
