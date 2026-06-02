<?php
/**
 * Plugin Name:       DevBench
 * Plugin URI:        https://github.com/towfique-elahe/devbench
 * Description:        A modern all-in-one developer workbench for WordPress — debug tools, file manager, database browser, search, mail catcher, environment checker, and more, in one clean interface.
 * Version:           1.0.0
 * Author:            Towfique Elahe
 * Author URI:        https://towfiqueelahe.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       devbench
 * Requires at least: 5.5
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'DEVBENCH_VERSION', '1.0.0' );
define( 'DEVBENCH_FILE',    __FILE__ );
define( 'DEVBENCH_PATH',    plugin_dir_path( __FILE__ ) );
define( 'DEVBENCH_URL',     plugin_dir_url( __FILE__ ) );
define( 'DEVBENCH_SLUG',    'devbench' );

// Autoload core classes
foreach ( [ 'helpers', 'debug', 'files', 'database', 'tools', 'search', 'extra', 'admin' ] as $module ) {
    require_once DEVBENCH_PATH . 'includes/class-' . $module . '.php';
}

// Boot mail catcher early (must hook before wp_mail fires)
DevBench_Extra::init_mail_catcher();

// Boot admin UI
if ( is_admin() ) {
    DevBench_Admin::init();
}

// Activation: nothing destructive, just a version flag
register_activation_hook( __FILE__, function () {
    update_option( 'devbench_version', DEVBENCH_VERSION );
} );
