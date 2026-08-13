<?php
/**
 * Data gathering for the read-only report screens.
 *
 * The dashboard, log analyzer, environment checker and system info pages used
 * to assemble their data inline. Collecting it here keeps those templates to
 * presentation only, and keeps the queries in one reviewable place.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Reports {

	/** Bytes of autoloaded options above which the dashboard warns. */
	const AUTOLOAD_WARN = 819200; // 800 KB.

	/* ---------------- Dashboard ---------------- */

	/**
	 * Everything the dashboard renders.
	 *
	 * @return array
	 */
	public static function dashboard() {
		global $wpdb;

		$memory_limit       = ini_get( 'memory_limit' );
		$memory_limit_bytes = DevBench_Helpers::to_bytes( $memory_limit );
		$memory_used        = memory_get_usage( true );

		$disk_free  = self::disk_free();
		$disk_total = self::disk_total();

		$mail_log = (array) get_option( 'devbench_mail_log', array() );
		$notes    = (array) get_option( 'devbench_notes', array() );

		return array(
			'php_version'   => PHP_VERSION,
			'php_sapi'      => php_sapi_name(),
			'wp_version'    => get_bloginfo( 'version' ),
			'site_name'     => get_bloginfo( 'name' ),
			'mysql_version' => $wpdb->db_version(),
			'db_prefix'     => $wpdb->prefix,
			'debug_on'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log_on'  => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'log_size'      => DevBench_Debug::log_size(),
			'table_count'   => count( DevBench_Database::table_names() ),
			'plugin_count'  => count( (array) get_option( 'active_plugins', array() ) ),
			'autoload'      => DevBench_Tools::autoload_size(),
			'mail_count'    => count( $mail_log ),
			'mail_on'       => (bool) get_option( 'devbench_mail_catcher', false ),
			'note_count'    => count( $notes ),
			'memory_limit'  => $memory_limit,
			'memory_used'   => $memory_used,
			'memory_pct'    => $memory_limit_bytes > 0 ? (int) round( $memory_used / $memory_limit_bytes * 100 ) : 0,
			'disk_free'     => $disk_free,
			'disk_total'    => $disk_total,
			'disk_pct'      => $disk_total ? (int) round( ( 1 - $disk_free / $disk_total ) * 100 ) : 0,
			'post_count'    => (int) wp_count_posts()->publish,
			'user_count'    => (int) count_users()['total_users'],
			'recent_errors' => self::recent_errors(),
		);
	}

	/** Free bytes on the WordPress volume, or 0 when the host hides it. */
	private static function disk_free() {
		if ( ! function_exists( 'disk_free_space' ) ) {
			return 0;
		}
		$bytes = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Hosts commonly disable this; a warning here is not actionable.
		return $bytes ? (float) $bytes : 0;
	}

	/** Total bytes on the WordPress volume, or 0 when the host hides it. */
	private static function disk_total() {
		if ( ! function_exists( 'disk_total_space' ) ) {
			return 0;
		}
		$bytes = @disk_total_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- See disk_free().
		return $bytes ? (float) $bytes : 0;
	}

	/** The five most recent error lines from debug.log. */
	private static function recent_errors( $limit = 5 ) {
		if ( ! DevBench_Debug::log_exists() ) {
			return array();
		}

		$recent = array();
		foreach ( array_reverse( explode( "\n", DevBench_Debug::tail( 60 ) ) ) as $line ) {
			if ( preg_match( '/PHP (Fatal|Parse|Warning|Notice|Deprecated)/i', $line ) ) {
				$recent[] = trim( $line );
				if ( count( $recent ) >= $limit ) {
					break;
				}
			}
		}
		return $recent;
	}

	/* ---------------- Log analyzer ---------------- */

	/**
	 * Parse debug.log into deduplicated, counted error groups.
	 *
	 * A new entry starts at a "[timestamp]" line; every line after it (Stack
	 * trace, "#0 …", "thrown in …") belongs to the same entry, so a fatal and
	 * its whole trace count as one error.
	 *
	 * @return array {
	 *     @type array $groups Entries keyed by signature, sorted by count desc.
	 *     @type array $types  Count per error type.
	 *     @type int   $total  Total entries parsed.
	 * }
	 */
	public static function log_summary() {
		$raw   = DevBench_Debug::log_exists() ? DevBench_Debug::tail( 5000 ) : '';
		$lines = $raw ? explode( "\n", $raw ) : array();

		$entries = array();
		$current = null;

		foreach ( $lines as $line ) {
			$line   = rtrim( $line, "\r\n" );
			$is_new = (bool) preg_match( '/^\[\d{1,2}-[A-Za-z]{3}-\d{4}/', $line );

			if ( $is_new || null === $current ) {
				if ( null !== $current && '' !== trim( $current ) ) {
					$entries[] = $current;
				}
				$current = $line;
			} else {
				$current .= "\n" . $line;
			}
		}
		if ( null !== $current && '' !== trim( $current ) ) {
			$entries[] = $current;
		}

		$groups = array();
		$types  = array(
			'Fatal'      => 0,
			'Parse'      => 0,
			'Warning'    => 0,
			'Notice'     => 0,
			'Deprecated' => 0,
			'Database'   => 0,
			'Other'      => 0,
		);

		foreach ( $entries as $entry ) {
			$first = strtok( $entry, "\n" );        // Type and message come from line one.
			$trace = substr_count( $entry, "\n" );  // Number of continuation lines.

			// Guard the regexes below against pathological single lines.
			if ( strlen( $first ) > 4000 ) {
				$first = substr( $first, 0, 4000 ) . '…';
			}

			$kind = self::classify_log_line( $first );

			// Message = first line without the timestamp and the "PHP X:" prefix.
			$message = preg_replace( '/^\[[^\]]+\]\s*/', '', $first );
			$message = null === $message ? $first : $message;
			$stripped = preg_replace( '/^PHP [A-Za-z ]+:\s*/', '', $message );
			$message  = null === $stripped ? $message : $stripped;

			// Location: "… in FILE on line N" or "in FILE:N", anywhere in the entry.
			$file = '';
			$line = '';
			if ( preg_match( '/ in (.+?) on line (\d+)/', $entry, $match ) ) {
				$file = $match[1];
				$line = $match[2];
			} elseif ( preg_match( '/in ([^\s():]+\.php):(\d+)/', $entry, $match ) ) {
				$file = $match[1];
				$line = $match[2];
			}

			// Group by type plus the message with numbers normalised away.
			$normalised = preg_replace( '/\d+/', '#', $message );
			$key        = md5( $kind . ( null === $normalised ? $message : $normalised ) );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'type'    => $kind,
					'message' => $message,
					'file'    => $file,
					'line'    => $line,
					'count'   => 0,
				);
			}

			++$groups[ $key ]['count'];
			$groups[ $key ]['full']  = $entry; // Keep the most recent occurrence.
			$groups[ $key ]['trace'] = $trace;

			++$types[ $kind ];
		}

		uasort(
			$groups,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return array(
			'groups' => $groups,
			'types'  => $types,
			'total'  => array_sum( $types ),
		);
	}

	/** Classify one log line into a display type. */
	private static function classify_log_line( $line ) {
		if ( preg_match( '/PHP (Fatal error|Parse error)/i', $line ) ) {
			return false !== stripos( $line, 'parse' ) ? 'Parse' : 'Fatal';
		}
		if ( false !== stripos( $line, 'PHP Warning' ) ) {
			return 'Warning';
		}
		if ( false !== stripos( $line, 'PHP Notice' ) ) {
			return 'Notice';
		}
		if ( false !== stripos( $line, 'PHP Deprecated' ) ) {
			return 'Deprecated';
		}
		if ( false !== stripos( $line, 'WordPress database error' ) ) {
			return 'Database';
		}
		return 'Other';
	}

	/* ---------------- Environment checker ---------------- */

	/**
	 * Run the environment checks.
	 *
	 * @return array {
	 *     @type array $checks  Flat list of checks.
	 *     @type array $grouped Checks keyed by group.
	 *     @type int   $pass    Passing count.
	 *     @type int   $warn    Warning count.
	 *     @type int   $fail    Failing count.
	 * }
	 */
	public static function environment_checks() {
		global $wpdb;

		$checks = array();

		$add = static function ( $group, $label, $status, $value, $detail = '', $fix = '' ) use ( &$checks ) {
			$checks[] = compact( 'group', 'label', 'status', 'value', 'detail', 'fix' );
		};

		/* PHP */
		$php_ok = version_compare( PHP_VERSION, '8.0', '>=' );
		$add(
			__( 'PHP', 'devbench' ),
			__( 'PHP Version', 'devbench' ),
			$php_ok ? 'pass' : 'warn',
			PHP_VERSION,
			$php_ok ? __( 'Modern PHP detected.', 'devbench' ) : __( 'PHP 8.0 or later is recommended.', 'devbench' ),
			$php_ok ? '' : __( 'Upgrade PHP in your hosting panel.', 'devbench' )
		);

		$memory = DevBench_Helpers::to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory >= 268435456 ) {
			$memory_status = 'pass';
		} elseif ( $memory >= 67108864 ) {
			$memory_status = 'warn';
		} else {
			$memory_status = 'fail';
		}
		$add(
			__( 'PHP', 'devbench' ),
			__( 'Memory Limit', 'devbench' ),
			$memory_status,
			ini_get( 'memory_limit' ),
			__( '256 MB or more is recommended.', 'devbench' ),
			'fail' === $memory_status ? __( 'Raise memory_limit.', 'devbench' ) : ''
		);

		$exec = (int) ini_get( 'max_execution_time' );
		$add(
			__( 'PHP', 'devbench' ),
			__( 'Max Execution Time', 'devbench' ),
			( 0 === $exec || $exec >= 30 ) ? 'pass' : 'warn',
			0 === $exec ? __( 'Unlimited', 'devbench' ) : $exec . 's',
			__( '30 seconds or more is recommended.', 'devbench' )
		);

		$upload = DevBench_Helpers::to_bytes( ini_get( 'upload_max_filesize' ) );
		$add(
			__( 'PHP', 'devbench' ),
			__( 'Upload Max Filesize', 'devbench' ),
			$upload >= 33554432 ? 'pass' : 'warn',
			ini_get( 'upload_max_filesize' ),
			__( '32 MB or more is recommended for media.', 'devbench' )
		);

		$post_ok = DevBench_Helpers::to_bytes( ini_get( 'post_max_size' ) ) >= $upload;
		$add(
			__( 'PHP', 'devbench' ),
			__( 'post_max_size is at least upload_max_filesize', 'devbench' ),
			$post_ok ? 'pass' : 'fail',
			ini_get( 'post_max_size' ),
			$post_ok ? __( 'Configured correctly.', 'devbench' ) : __( 'post_max_size must be at least the upload size.', 'devbench' ),
			$post_ok ? '' : __( 'Increase post_max_size.', 'devbench' )
		);

		$opcache = function_exists( 'opcache_get_status' ) && @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Throws when OPcache is disabled at runtime, which is exactly what is being tested.
		$add(
			__( 'PHP', 'devbench' ),
			__( 'OPcache', 'devbench' ),
			$opcache ? 'pass' : 'warn',
			$opcache ? __( 'Enabled', 'devbench' ) : __( 'Disabled', 'devbench' ),
			$opcache ? __( 'Bytecode caching is active.', 'devbench' ) : __( 'Enable OPcache for performance.', 'devbench' )
		);

		/* WordPress */
		$wp_ok = version_compare( get_bloginfo( 'version' ), '6.0', '>=' );
		$add(
			__( 'WordPress', 'devbench' ),
			__( 'WP Version', 'devbench' ),
			$wp_ok ? 'pass' : 'warn',
			get_bloginfo( 'version' ),
			$wp_ok ? __( 'Recent enough.', 'devbench' ) : __( 'Upgrade WordPress.', 'devbench' )
		);

		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$add(
			__( 'WordPress', 'devbench' ),
			'WP_DEBUG',
			$debug ? 'warn' : 'pass',
			$debug ? __( 'On', 'devbench' ) : __( 'Off', 'devbench' ),
			$debug ? __( 'Disable on production.', 'devbench' ) : __( 'Off, as expected.', 'devbench' ),
			$debug ? __( 'Set WP_DEBUG to false in the WP Config Editor.', 'devbench' ) : ''
		);

		$display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
		$add(
			__( 'WordPress', 'devbench' ),
			'WP_DEBUG_DISPLAY',
			$display ? 'fail' : 'pass',
			$display ? __( 'On', 'devbench' ) : __( 'Off', 'devbench' ),
			$display ? __( 'Errors are visible to visitors.', 'devbench' ) : __( 'Hidden from visitors.', 'devbench' ),
			$display ? __( 'Set WP_DEBUG_DISPLAY to false.', 'devbench' ) : ''
		);

		/* Security */
		$no_edit = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$add(
			__( 'Security', 'devbench' ),
			'DISALLOW_FILE_EDIT',
			$no_edit ? 'pass' : 'warn',
			$no_edit ? 'true' : __( 'not set', 'devbench' ),
			$no_edit ? __( 'The admin file editor is disabled.', 'devbench' ) : __( 'The theme and plugin editor is reachable.', 'devbench' ),
			$no_edit ? '' : __( "Add define('DISALLOW_FILE_EDIT', true) to wp-config.php.", 'devbench' )
		);

		$https = is_ssl() || 0 === strpos( get_site_url(), 'https://' );
		$add(
			__( 'Security', 'devbench' ),
			__( 'HTTPS', 'devbench' ),
			$https ? 'pass' : 'warn',
			$https ? __( 'Active', 'devbench' ) : __( 'Inactive', 'devbench' ),
			$https ? __( 'Served over HTTPS.', 'devbench' ) : __( 'Install an SSL certificate.', 'devbench' )
		);

		$config          = DevBench_Helpers::wp_config_path();
		$config_writable = $config && DevBench_FS::is_writable( $config );
		$add(
			__( 'Security', 'devbench' ),
			'wp-config.php',
			$config_writable ? 'warn' : 'pass',
			$config_writable ? __( 'Writable', 'devbench' ) : __( 'Read-only', 'devbench' ),
			$config_writable ? __( 'Fine for development; restrict on production.', 'devbench' ) : __( 'Not web-writable. Good.', 'devbench' )
		);

		/* Database */
		$mysql_ok = version_compare( $wpdb->db_version(), '5.7', '>=' );
		$add(
			__( 'Database', 'devbench' ),
			__( 'MySQL Version', 'devbench' ),
			$mysql_ok ? 'pass' : 'warn',
			'MySQL ' . $wpdb->db_version(),
			$mysql_ok ? __( 'Sufficient.', 'devbench' ) : __( 'MySQL 5.7+ or MariaDB 10.3+ is recommended.', 'devbench' )
		);

		$autoload    = DevBench_Tools::autoload_size();
		$autoload_ok = $autoload < self::AUTOLOAD_WARN;
		$add(
			__( 'Database', 'devbench' ),
			__( 'Autoload Size', 'devbench' ),
			$autoload_ok ? 'pass' : 'fail',
			DevBench_Helpers::filesize( $autoload ),
			$autoload_ok ? __( 'Healthy.', 'devbench' ) : __( 'Too much data is autoloaded on every request.', 'devbench' ),
			$autoload_ok ? '' : __( 'Clean up autoloaded options.', 'devbench' )
		);

		/* Extensions */
		foreach ( array( 'curl', 'gd', 'mbstring', 'xml', 'zip', 'intl', 'imagick' ) as $extension ) {
			$loaded = extension_loaded( $extension );
			$add(
				__( 'PHP Extensions', 'devbench' ),
				$extension,
				$loaded ? 'pass' : 'warn',
				$loaded ? __( 'Loaded', 'devbench' ) : __( 'Missing', 'devbench' ),
				$loaded ? '' : __( 'Recommended extension.', 'devbench' )
			);
		}

		$grouped = array();
		foreach ( $checks as $check ) {
			$grouped[ $check['group'] ][] = $check;
		}

		return array(
			'checks'  => $checks,
			'grouped' => $grouped,
			'pass'    => self::count_status( $checks, 'pass' ),
			'warn'    => self::count_status( $checks, 'warn' ),
			'fail'    => self::count_status( $checks, 'fail' ),
		);
	}

	private static function count_status( $checks, $status ) {
		$count = 0;
		foreach ( $checks as $check ) {
			if ( $check['status'] === $status ) {
				++$count;
			}
		}
		return $count;
	}

	/* ---------------- System info ---------------- */

	/**
	 * System info, as sections of label => value rows.
	 *
	 * Boolean values are rendered as Yes/No badges by the template.
	 *
	 * @return array
	 */
	public static function system_info() {
		global $wpdb;

		$theme = wp_get_theme();

		$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '—';
		$root   = isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '—';

		$opcache = ( function_exists( 'opcache_get_status' ) && @opcache_get_status( false ) ) ? true : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Throws when OPcache is disabled, which is the state being reported.

		return array(
			'wordpress' => array(
				'icon' => 'server',
				'name' => __( 'WordPress', 'devbench' ),
				'rows' => array(
					__( 'Version', 'devbench' )     => get_bloginfo( 'version' ),
					__( 'Site URL', 'devbench' )    => get_site_url(),
					__( 'Home URL', 'devbench' )    => get_home_url(),
					__( 'Multisite', 'devbench' )   => is_multisite(),
					'ABSPATH'                       => ABSPATH,
					__( 'Content dir', 'devbench' ) => WP_CONTENT_DIR,
					__( 'Locale', 'devbench' )      => get_locale(),
					__( 'Timezone', 'devbench' )    => wp_timezone_string(),
					__( 'Charset', 'devbench' )     => get_bloginfo( 'charset' ),
				),
			),
			'php'       => array(
				'icon' => 'code',
				'name' => __( 'PHP', 'devbench' ),
				'rows' => array(
					__( 'Version', 'devbench' )        => PHP_VERSION,
					__( 'SAPI', 'devbench' )           => php_sapi_name(),
					__( 'Memory limit', 'devbench' )   => ini_get( 'memory_limit' ),
					__( 'Max execution', 'devbench' )  => ini_get( 'max_execution_time' ) . 's',
					__( 'Upload max', 'devbench' )     => ini_get( 'upload_max_filesize' ),
					__( 'Post max', 'devbench' )       => ini_get( 'post_max_size' ),
					__( 'Max input vars', 'devbench' ) => ini_get( 'max_input_vars' ),
					__( 'Display errors', 'devbench' ) => ini_get( 'display_errors' ) ? __( 'On', 'devbench' ) : __( 'Off', 'devbench' ),
					__( 'OPcache', 'devbench' )        => $opcache,
				),
			),
			'database'  => array(
				'icon' => 'database',
				'name' => __( 'Database', 'devbench' ),
				'rows' => array(
					__( 'MySQL version', 'devbench' ) => $wpdb->db_version(),
					__( 'Database', 'devbench' )      => DB_NAME,
					__( 'Host', 'devbench' )          => DB_HOST,
					__( 'Prefix', 'devbench' )        => $wpdb->prefix,
					__( 'Tables', 'devbench' )        => count( DevBench_Database::table_names() ),
					__( 'Charset', 'devbench' )       => $wpdb->charset,
					__( 'Collate', 'devbench' )       => $wpdb->collate ? $wpdb->collate : '—',
				),
			),
			'server'    => array(
				'icon' => 'settings',
				'name' => __( 'Active Theme & Server', 'devbench' ),
				'rows' => array(
					__( 'Theme', 'devbench' )         => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
					__( 'Template', 'devbench' )      => get_template(),
					__( 'Server', 'devbench' )        => $server,
					__( 'Document root', 'devbench' ) => $root,
					__( 'OS', 'devbench' )            => PHP_OS,
					__( 'cURL', 'devbench' )          => function_exists( 'curl_version' ) ? curl_version()['version'] : __( 'n/a', 'devbench' ),
					__( 'Free disk', 'devbench' )     => DevBench_Helpers::filesize( self::disk_free() ),
				),
			),
		);
	}
}
