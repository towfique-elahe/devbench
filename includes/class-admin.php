<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Admin {

	public static function init() {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_ajax_devbench',      [ __CLASS__, 'route_ajax' ] );
		add_action( 'admin_init',            [ __CLASS__, 'maybe_phpinfo' ] );
	}

	/** Page registry: slug => [title, menu label, group, icon, callback]. */
	public static function pages() {
		return [
			'devbench'           => [ 'Dashboard',         'Dashboard',         'Core',        'dashboard', 'page_dashboard' ],
			'devbench-search'    => [ 'Search & Locator',  'Search & Locator',  'Core',        'search',    'page_search' ],
			'devbench-debug'     => [ 'Debug Manager',      'Debug Manager',     'Core',        'bug',       'page_debug' ],
			'devbench-logs'      => [ 'Log Analyzer',       'Log Analyzer',      'Core',        'chart',     'page_logs' ],
			'devbench-files'     => [ 'File Manager',       'File Manager',      'Core',        'folder',    'page_files' ],
			'devbench-database'  => [ 'Database Manager',   'Database Manager',  'Core',        'database',  'page_database' ],
			'devbench-snippet'   => [ 'Snippet Runner',     'Snippet Runner',    'Core',        'zap',       'page_snippet' ],
			'devbench-options'   => [ 'Options Manager',    'Options Manager',   'WordPress',   'list',      'page_options' ],
			'devbench-transients'=> [ 'Transients',         'Transients',        'WordPress',   'clock',     'page_transients' ],
			'devbench-cron'      => [ 'Cron Manager',       'Cron Manager',      'WordPress',   'repeat',    'page_cron' ],
			'devbench-config'    => [ 'WP Config Editor',   'WP Config Editor',  'WordPress',   'sliders',   'page_config' ],
			'devbench-plugins'   => [ 'Plugins & Themes',   'Plugins & Themes',  'WordPress',   'plug',      'page_plugins' ],
			'devbench-mail'      => [ 'Mail Catcher',       'Mail Catcher',      'WordPress',   'mail',      'page_mail' ],
			'devbench-notes'     => [ 'Quick Notes',        'Quick Notes',       'Utilities',   'note',      'page_notes' ],
			'devbench-env'       => [ 'Environment Checker','Environment Checker','Environment','shield',    'page_env' ],
			'devbench-phpinfo'   => [ 'PHP Info',           'PHP Info',          'Environment', 'server',    'page_phpinfo' ],
			'devbench-system'    => [ 'System Info',        'System Info',       'Environment', 'cpu',       'page_system' ],
		];
	}

	public static function register_menus() {
		$pages = self::pages();
		add_menu_page(
			'DevBench',
			'DevBench',
			'manage_options',
			'devbench',
			[ __CLASS__, 'page_dashboard' ],
			'dashicons-editor-code',
			75
		);
		foreach ( $pages as $slug => $p ) {
			add_submenu_page(
				'devbench',
				$p[0],
				$p[1],
				'manage_options',
				$slug,
				[ __CLASS__, $p[4] ]
			);
		}
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, 'devbench' ) === false ) return;
		wp_enqueue_style( 'devbench', DEVBENCH_URL . 'admin/assets/css/devbench.css', [], DEVBENCH_VERSION );
		wp_enqueue_script( 'devbench', DEVBENCH_URL . 'admin/assets/js/devbench.js', [ 'jquery' ], DEVBENCH_VERSION, true );
		wp_localize_script( 'devbench', 'DevBench', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'devbench_nonce' ),
		] );
	}

	public static function route_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

		switch ( sanitize_text_field( $_POST['module'] ?? '' ) ) {
			case 'debug':    DevBench_Debug::handle_ajax();    break;
			case 'files':    DevBench_Files::handle_ajax();    break;
			case 'database': DevBench_Database::handle_ajax(); break;
			case 'tools':    DevBench_Tools::handle_ajax();    break;
			case 'search':   DevBench_Search::handle_ajax();   break;
			case 'extra':    DevBench_Extra::handle_ajax();    break;
		}
		wp_die();
	}

	/** Serve standalone phpinfo() in an iframe (before WP renders admin chrome). */
	public static function maybe_phpinfo() {
		if ( isset( $_GET['devbench_phpinfo'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'devbench_phpinfo' );
			phpinfo();
			exit;
		}
	}

	/* ----- Page renderers (each includes its template) ----- */

	private static function render( $template ) {
		require DEVBENCH_PATH . 'admin/pages/' . $template . '.php';
	}

	public static function page_dashboard()  { self::render( 'dashboard' ); }
	public static function page_search()      { self::render( 'search' ); }
	public static function page_debug()       { self::render( 'debug' ); }
	public static function page_logs()        { self::render( 'logs' ); }
	public static function page_files()       { self::render( 'files' ); }
	public static function page_database()    { self::render( 'database' ); }
	public static function page_snippet()     { self::render( 'snippet' ); }
	public static function page_options()     { self::render( 'options' ); }
	public static function page_transients()  { self::render( 'transients' ); }
	public static function page_cron()        { self::render( 'cron' ); }
	public static function page_config()      { self::render( 'config' ); }
	public static function page_plugins()     { self::render( 'plugins' ); }
	public static function page_mail()        { self::render( 'mail' ); }
	public static function page_notes()       { self::render( 'notes' ); }
	public static function page_env()         { self::render( 'env' ); }
	public static function page_phpinfo()     { self::render( 'phpinfo' ); }
	public static function page_system()      { self::render( 'system' ); }
}
