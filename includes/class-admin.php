<?php
/**
 * Admin menus, asset loading and the single AJAX entry point.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_devbench', array( __CLASS__, 'route_ajax' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_phpinfo' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );

		// DevBench supplies its own full-height chrome, so WordPress's admin
		// footer is dropped on its screens only. Priority 11 on update_footer
		// beats core's own core_update_footer() at 10.
		add_filter( 'admin_footer_text', array( __CLASS__, 'clear_admin_footer' ) );
		add_filter( 'update_footer', array( __CLASS__, 'clear_admin_footer' ), 11 );
	}

	/** Whether the screen being rendered belongs to DevBench. */
	public static function is_devbench_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && false !== strpos( $screen->id, 'devbench' );
	}

	/** Marks DevBench screens so the stylesheet can adjust WordPress's chrome. */
	public static function body_class( $classes ) {
		if ( self::is_devbench_screen() ) {
			$classes .= ' devbench-screen';
		}
		return $classes;
	}

	/** Empties the admin footer text on DevBench screens, leaving it untouched elsewhere. */
	public static function clear_admin_footer( $text ) {
		return self::is_devbench_screen() ? '' : $text;
	}

	/** Page registry: slug => [title, menu label, group, icon, template]. */
	public static function pages() {
		return array(
			'devbench'            => array( __( 'Dashboard', 'devbench' ), __( 'Dashboard', 'devbench' ), __( 'Core', 'devbench' ), 'dashboard', 'dashboard' ),
			'devbench-search'     => array( __( 'Search & Locator', 'devbench' ), __( 'Search & Locator', 'devbench' ), __( 'Core', 'devbench' ), 'search', 'search' ),
			'devbench-debug'      => array( __( 'Debug Manager', 'devbench' ), __( 'Debug Manager', 'devbench' ), __( 'Core', 'devbench' ), 'bug', 'debug' ),
			'devbench-logs'       => array( __( 'Log Analyzer', 'devbench' ), __( 'Log Analyzer', 'devbench' ), __( 'Core', 'devbench' ), 'chart', 'logs' ),
			'devbench-files'      => array( __( 'File Manager', 'devbench' ), __( 'File Manager', 'devbench' ), __( 'Core', 'devbench' ), 'folder', 'files' ),
			'devbench-database'   => array( __( 'Database Manager', 'devbench' ), __( 'Database Manager', 'devbench' ), __( 'Core', 'devbench' ), 'database', 'database' ),
			'devbench-snippet'    => array( __( 'Snippet Runner', 'devbench' ), __( 'Snippet Runner', 'devbench' ), __( 'Core', 'devbench' ), 'zap', 'snippet' ),
			'devbench-options'    => array( __( 'Options Manager', 'devbench' ), __( 'Options Manager', 'devbench' ), __( 'WordPress', 'devbench' ), 'list', 'options' ),
			'devbench-transients' => array( __( 'Transients', 'devbench' ), __( 'Transients', 'devbench' ), __( 'WordPress', 'devbench' ), 'clock', 'transients' ),
			'devbench-cron'       => array( __( 'Cron Manager', 'devbench' ), __( 'Cron Manager', 'devbench' ), __( 'WordPress', 'devbench' ), 'repeat', 'cron' ),
			'devbench-config'     => array( __( 'WP Config Editor', 'devbench' ), __( 'WP Config Editor', 'devbench' ), __( 'WordPress', 'devbench' ), 'sliders', 'config' ),
			'devbench-plugins'    => array( __( 'Plugins & Themes', 'devbench' ), __( 'Plugins & Themes', 'devbench' ), __( 'WordPress', 'devbench' ), 'plug', 'plugins' ),
			'devbench-mail'       => array( __( 'Mail Catcher', 'devbench' ), __( 'Mail Catcher', 'devbench' ), __( 'WordPress', 'devbench' ), 'mail', 'mail' ),
			'devbench-notes'      => array( __( 'Quick Notes', 'devbench' ), __( 'Quick Notes', 'devbench' ), __( 'Utilities', 'devbench' ), 'note', 'notes' ),
			'devbench-env'        => array( __( 'Environment Checker', 'devbench' ), __( 'Environment Checker', 'devbench' ), __( 'Environment', 'devbench' ), 'shield', 'env' ),
			'devbench-phpinfo'    => array( __( 'PHP Info', 'devbench' ), __( 'PHP Info', 'devbench' ), __( 'Environment', 'devbench' ), 'server', 'phpinfo' ),
			'devbench-system'     => array( __( 'System Info', 'devbench' ), __( 'System Info', 'devbench' ), __( 'Environment', 'devbench' ), 'cpu', 'system' ),
		);
	}

	public static function register_menus() {
		$capability = DevBench_Helpers::capability();

		add_menu_page(
			'DevBench',
			'DevBench',
			$capability,
			'devbench',
			static function () {
				self::render( 'dashboard' );
			},
			'dashicons-editor-code',
			75
		);

		foreach ( self::pages() as $slug => $page ) {
			$template = $page[4];

			/*
			 * The dashboard shares its slug with the parent menu, so WordPress
			 * resolves it to the same page hook that add_menu_page() just
			 * registered a callback on. Passing another callback here would
			 * hook the same action twice and render the page twice, so this
			 * entry only supplies the menu label.
			 */
			$callback = ( 'devbench' === $slug )
				? ''
				: static function () use ( $template ) {
					self::render( $template );
				};

			add_submenu_page(
				'devbench',
				$page[0],
				$page[1],
				$capability,
				$slug,
				$callback
			);
		}
	}

	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'devbench' ) ) {
			return;
		}

		wp_enqueue_style( 'devbench', DEVBENCH_URL . 'admin/assets/css/devbench.css', array(), DEVBENCH_VERSION );
		wp_enqueue_script( 'devbench', DEVBENCH_URL . 'admin/assets/js/devbench.js', array( 'jquery' ), DEVBENCH_VERSION, true );
		wp_localize_script(
			'devbench',
			'DevBench',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'devbench_nonce' ),
			)
		);
	}

	/**
	 * Single AJAX entry point. Verifies the nonce and capability once here;
	 * each module handler re-verifies both before touching request data.
	 */
	public static function route_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );

		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';

		switch ( $module ) {
			case 'debug':
				DevBench_Debug::handle_ajax();
				break;
			case 'files':
				DevBench_Files::handle_ajax();
				break;
			case 'database':
				DevBench_Database::handle_ajax();
				break;
			case 'tools':
				DevBench_Tools::handle_ajax();
				break;
			case 'search':
				DevBench_Search::handle_ajax();
				break;
			case 'extra':
				DevBench_Extra::handle_ajax();
				break;
			default:
				wp_send_json_error( __( 'Unknown module.', 'devbench' ), 400 );
		}

		wp_die();
	}

	/** Serve standalone phpinfo() in an iframe (before WP renders admin chrome). */
	public static function maybe_phpinfo() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified by check_admin_referer() below.
		if ( ! isset( $_GET['devbench_phpinfo'] ) ) {
			return;
		}
		if ( ! DevBench_Helpers::can_manage() ) {
			return;
		}
		check_admin_referer( 'devbench_phpinfo' );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo -- This screen exists solely to show phpinfo() to an authenticated administrator.
		phpinfo();
		exit;
	}

	/**
	 * Render a page template. The capability is re-checked here so a template
	 * can never render from an unexpected call path.
	 *
	 * @param string $template Template basename from the page registry.
	 */
	private static function render( $template ) {
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'devbench' ) );
		}
		require DEVBENCH_PATH . 'admin/pages/' . $template . '.php';
	}
}
