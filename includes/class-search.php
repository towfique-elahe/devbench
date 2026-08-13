<?php
/**
 * Search & Locator: keyword search across install files and database tables.
 *
 * Both searches run in two phases so the browser can show real progress and so
 * no single request has to scan everything: phase one enumerates the work
 * (file paths / table names), phase two scans one batch per request.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Search {

	const SKIP_DIRS   = array( '.git', 'node_modules', '.svn', 'vendor', 'uploads', 'cache' );
	const SKIP_EXT    = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'mp3', 'mp4', 'zip', 'gz', 'tar', 'woff', 'woff2', 'ttf', 'otf', 'pdf', 'exe', 'bin', 'so', 'dll' );
	const MAX_FILE    = 2097152; // 2 MB.
	const MAX_MATCHES = 15;      // Matching lines kept per file.
	const ROW_LIMIT   = 25;      // Matching rows kept per table.

	/* ---------------- Files ---------------- */

	/**
	 * Phase 1: enumerate candidate files without reading their contents.
	 *
	 * Cheap stat/extension/size filtering only, so the client can scan the
	 * result in progress-tracked batches.
	 *
	 * @param string[] $extensions Optional extension allowlist.
	 * @return string[] Paths relative to ABSPATH.
	 */
	public static function enumerate_files( $extensions = array() ) {
		$base = realpath( ABSPATH );
		if ( false === $base ) {
			return array();
		}

		$exts  = array_filter( array_map( 'strtolower', array_map( 'trim', (array) $extensions ) ) );
		$files = array();

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( Exception $e ) {
			return array();
		}

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$path = $file->getPathname();

			foreach ( self::SKIP_DIRS as $skip ) {
				if ( false !== strpos( $path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR ) ) {
					continue 2;
				}
			}

			$ext = strtolower( $file->getExtension() );
			if ( $exts && ! in_array( $ext, $exts, true ) ) {
				continue;
			}
			if ( ! $exts && in_array( $ext, self::SKIP_EXT, true ) ) {
				continue;
			}
			if ( $file->getSize() > self::MAX_FILE ) {
				continue;
			}

			$files[] = DevBench_Helpers::relative_path( $path );
		}

		return $files;
	}

	/**
	 * Phase 2: search a keyword within one batch of files.
	 *
	 * @param string   $keyword Search term.
	 * @param string[] $paths   Relative paths from enumerate_files().
	 */
	public static function scan_files_batch( $keyword, $paths ) {
		if ( strlen( $keyword ) < 2 ) {
			return array();
		}

		$results = array();

		foreach ( (array) $paths as $relative ) {
			$abs = DevBench_Helpers::safe_path( $relative );
			if ( ! $abs || ! DevBench_FS::is_file( $abs ) ) {
				continue;
			}
			if ( DevBench_FS::size( $abs ) > self::MAX_FILE ) {
				continue;
			}

			$content = DevBench_FS::read( $abs );
			if ( false === $content || false === stripos( $content, $keyword ) ) {
				continue;
			}

			$matches = array();
			foreach ( explode( "\n", $content ) as $number => $line ) {
				if ( false === stripos( $line, $keyword ) ) {
					continue;
				}
				$text      = trim( $line );
				$matches[] = array(
					'line' => $number + 1,
					'text' => mb_strlen( $text ) > 240 ? mb_substr( $text, 0, 240 ) . '…' : $text,
				);
				if ( count( $matches ) >= self::MAX_MATCHES ) {
					break;
				}
			}

			if ( ! $matches ) {
				continue;
			}

			$results[] = array(
				'path'    => $relative,
				'name'    => basename( $relative ),
				'ext'     => strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) ),
				'count'   => count( $matches ),
				'matches' => $matches,
			);
		}

		return $results;
	}

	/* ---------------- Database ---------------- */

	/**
	 * Phase 1: the list of tables that will be scanned.
	 *
	 * @param string[] $tables Optional subset; anything not a real table is dropped.
	 */
	public static function db_scan_list( $tables = array() ) {
		$all = DevBench_Database::table_names();
		return $tables ? array_values( array_intersect( $tables, $all ) ) : array_values( $all );
	}

	/**
	 * Phase 2: scan a single table.
	 *
	 * @return array|null One result row, or null when nothing matched.
	 */
	public static function scan_table( $keyword, $table ) {
		global $wpdb;

		if ( strlen( $keyword ) < 2 ) {
			return null;
		}
		if ( ! DevBench_Database::valid_table( $table ) ) {
			return null;
		}

		$structure = DevBench_Database::structure( $table );
		if ( is_wp_error( $structure ) ) {
			return null;
		}

		// Only text-ish columns are worth a LIKE scan.
		$columns = array();
		foreach ( (array) $structure as $column ) {
			$type = strtolower( $column['Type'] );
			if ( false !== strpos( $type, 'char' ) || false !== strpos( $type, 'text' ) ) {
				$columns[] = $column['Field'];
			}
		}
		if ( ! $columns ) {
			return null;
		}

		$like = '%' . $wpdb->esc_like( $keyword ) . '%';

		// The WHERE fragment is the literal '%i LIKE %s' repeated once per
		// column; every identifier and value is bound through prepare() below.
		$fragment = implode( ' OR ', array_fill( 0, count( $columns ), '%i LIKE %s' ) );

		$args = array( $table );
		foreach ( $columns as $column ) {
			$args[] = $column;
			$args[] = $like;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $fragment contains only repeated literal placeholders; all identifiers and values are bound via prepare().
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . $fragment, $args ) );
		if ( ! $total ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- See above; the row limit is a literal.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ' . $fragment . ' LIMIT ' . self::ROW_LIMIT, $args ), ARRAY_A );

		$hits = array();
		foreach ( (array) $rows as $row ) {
			$primary = array_key_first( $row );
			$cells   = array();

			foreach ( $row as $column => $value ) {
				if ( null === $value || false === stripos( (string) $value, $keyword ) ) {
					continue;
				}
				$plain   = trim( wp_strip_all_tags( (string) $value ) );
				$start   = max( 0, stripos( $plain, $keyword ) - 50 );
				$cells[] = array(
					'col'     => $column,
					'snippet' => ( $start > 0 ? '…' : '' ) . mb_substr( $plain, $start, 150 ),
				);
			}

			if ( $cells ) {
				$hits[] = array(
					'id'    => $row[ $primary ],
					'cells' => $cells,
				);
			}
		}

		if ( ! $hits ) {
			return null;
		}

		return array(
			'table' => $table,
			'total' => $total,
			'shown' => count( $hits ),
			'hits'  => $hits,
		);
	}

	/* ---------------- AJAX ---------------- */

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action  = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		switch ( $action ) {
			case 'enumerate':
				$extensions = isset( $_POST['extensions'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['extensions'] ) ) : array();
				$files      = self::enumerate_files( $extensions );
				wp_send_json_success(
					array(
						'files' => $files,
						'total' => count( $files ),
					)
				);
				break;

			case 'scan_batch':
				$paths   = isset( $_POST['paths'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['paths'] ) ) : array();
				$results = self::scan_files_batch( $keyword, $paths );
				wp_send_json_success( array( 'results' => $results ) );
				break;

			case 'db_tables':
				$tables = isset( $_POST['tables'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['tables'] ) ) : array();
				$list   = self::db_scan_list( $tables );
				wp_send_json_success(
					array(
						'tables' => $list,
						'total'  => count( $list ),
					)
				);
				break;

			case 'scan_table':
				$table = isset( $_POST['table'] ) ? sanitize_text_field( wp_unslash( $_POST['table'] ) ) : '';
				wp_send_json_success( array( 'result' => self::scan_table( $keyword, $table ) ) );
				break;
		}

		wp_die();
	}
}
