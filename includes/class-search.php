<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Search {

	const SKIP_DIRS = [ '.git', 'node_modules', '.svn', 'vendor', 'uploads', 'cache' ];
	const SKIP_EXT  = [ 'jpg','jpeg','png','gif','webp','ico','mp3','mp4','zip','gz','tar','woff','woff2','ttf','otf','pdf','exe','bin','so','dll' ];
	const MAX_FILE  = 2097152; // 2 MB
	const MAX_HITS  = 200;

	public static function files( $keyword, $extensions = [] ) {
		if ( strlen( $keyword ) < 2 ) return [];
		$base    = realpath( ABSPATH );
		$results = [];
		$count   = 0;
		$exts    = array_filter( array_map( 'strtolower', array_map( 'trim', $extensions ) ) );

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iter as $file ) {
			if ( $count >= self::MAX_HITS ) break;
			if ( ! $file->isFile() ) continue;
			$path = $file->getPathname();

			foreach ( self::SKIP_DIRS as $skip ) {
				if ( strpos( $path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR ) !== false ) continue 2;
			}
			$ext = strtolower( $file->getExtension() );
			if ( $exts && ! in_array( $ext, $exts, true ) ) continue;
			if ( ! $exts && in_array( $ext, self::SKIP_EXT, true ) ) continue;
			if ( $file->getSize() > self::MAX_FILE ) continue;

			$content = @file_get_contents( $path );
			if ( $content === false || stripos( $content, $keyword ) === false ) continue;

			$matches = [];
			$lines   = explode( "\n", $content );
			foreach ( $lines as $n => $line ) {
				if ( stripos( $line, $keyword ) !== false ) {
					$t = trim( $line );
					$matches[] = [ 'line' => $n + 1, 'text' => mb_strlen( $t ) > 240 ? mb_substr( $t, 0, 240 ) . '…' : $t ];
					if ( count( $matches ) >= 15 ) break;
				}
			}
			if ( ! $matches ) continue;
			$results[] = [
				'path'    => DevBench_Helpers::relative_path( $path ),
				'name'    => $file->getFilename(),
				'ext'     => $ext,
				'count'   => count( $matches ),
				'matches' => $matches,
			];
			$count++;
		}
		usort( $results, fn( $a, $b ) => $b['count'] <=> $a['count'] );
		return $results;
	}

	public static function database( $keyword, $tables = [] ) {
		global $wpdb;
		if ( strlen( $keyword ) < 2 ) return [];
		$all  = $wpdb->get_col( 'SHOW TABLES' );
		$scan = $tables ? array_intersect( $tables, $all ) : $all;
		$like = '%' . $wpdb->esc_like( $keyword ) . '%';
		$out  = [];

		foreach ( $scan as $table ) {
			$cols = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
			$text = [];
			foreach ( $cols as $c ) {
				$type = strtolower( $c['Type'] );
				if ( strpos( $type, 'char' ) !== false || strpos( $type, 'text' ) !== false ) {
					$text[] = $c['Field'];
				}
			}
			if ( ! $text ) continue;

			$where = implode( ' OR ', array_map( fn( $c ) => "`{$c}` LIKE %s", $text ) );
			$args  = array_fill( 0, count( $text ), $like );
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $args ) );
			if ( ! $total ) continue;

			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where} LIMIT 25", $args ), ARRAY_A );
			$hits = [];
			foreach ( $rows as $row ) {
				$pk    = array_key_first( $row );
				$cells = [];
				foreach ( $row as $col => $val ) {
					if ( $val !== null && stripos( $val, $keyword ) !== false ) {
						$plain = trim( strip_tags( $val ) );
						$pos   = stripos( $plain, $keyword );
						$start = max( 0, $pos - 50 );
						$snip  = mb_substr( $plain, $start, 150 );
						$cells[] = [ 'col' => $col, 'snippet' => ( $start > 0 ? '…' : '' ) . $snip ];
					}
				}
				if ( $cells ) $hits[] = [ 'id' => $row[ $pk ], 'cells' => $cells ];
			}
			if ( $hits ) {
				$out[] = [ 'table' => $table, 'total' => $total, 'shown' => count( $hits ), 'hits' => $hits ];
			}
		}
		return $out;
	}

	/**
	 * Phase 1 (files): enumerate candidate files without reading their
	 * contents. Cheap stat/extension/size filtering only — returns the list
	 * of relative paths so the client can scan them in progress-tracked batches.
	 */
	public static function enumerate_files( $extensions = [] ) {
		$base  = realpath( ABSPATH );
		$exts  = array_filter( array_map( 'strtolower', array_map( 'trim', $extensions ) ) );
		$files = [];

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iter as $file ) {
			if ( ! $file->isFile() ) continue;
			$path = $file->getPathname();
			foreach ( self::SKIP_DIRS as $skip ) {
				if ( strpos( $path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR ) !== false ) continue 2;
			}
			$ext = strtolower( $file->getExtension() );
			if ( $exts && ! in_array( $ext, $exts, true ) ) continue;
			if ( ! $exts && in_array( $ext, self::SKIP_EXT, true ) ) continue;
			if ( $file->getSize() > self::MAX_FILE ) continue;
			$files[] = DevBench_Helpers::relative_path( $path );
		}
		return $files;
	}

	/**
	 * Phase 2 (files): search the keyword within a specific set of files
	 * (a batch of relative paths produced by enumerate_files()).
	 */
	public static function scan_files_batch( $keyword, $paths ) {
		if ( strlen( $keyword ) < 2 ) return [];
		$results = [];

		foreach ( (array) $paths as $rel ) {
			$abs = DevBench_Helpers::safe_path( $rel );
			if ( ! $abs || ! is_file( $abs ) ) continue;
			if ( filesize( $abs ) > self::MAX_FILE ) continue;

			$content = @file_get_contents( $abs );
			if ( $content === false || stripos( $content, $keyword ) === false ) continue;

			$matches = [];
			$lines   = explode( "\n", $content );
			foreach ( $lines as $n => $line ) {
				if ( stripos( $line, $keyword ) !== false ) {
					$t = trim( $line );
					$matches[] = [ 'line' => $n + 1, 'text' => mb_strlen( $t ) > 240 ? mb_substr( $t, 0, 240 ) . '…' : $t ];
					if ( count( $matches ) >= 15 ) break;
				}
			}
			if ( ! $matches ) continue;
			$results[] = [
				'path'    => $rel,
				'name'    => basename( $rel ),
				'ext'     => strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ),
				'count'   => count( $matches ),
				'matches' => $matches,
			];
		}
		return $results;
	}

	/** Phase 1 (database): the list of tables that will be scanned. */
	public static function db_scan_list( $tables = [] ) {
		global $wpdb;
		$all = $wpdb->get_col( 'SHOW TABLES' );
		return $tables ? array_values( array_intersect( $tables, $all ) ) : $all;
	}

	/** Phase 2 (database): scan a single table; returns one result row or null. */
	public static function scan_table( $keyword, $table ) {
		global $wpdb;
		if ( strlen( $keyword ) < 2 ) return null;
		if ( ! in_array( $table, $wpdb->get_col( 'SHOW TABLES' ), true ) ) return null;

		$cols = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
		$text = [];
		foreach ( $cols as $c ) {
			$type = strtolower( $c['Type'] );
			if ( strpos( $type, 'char' ) !== false || strpos( $type, 'text' ) !== false ) {
				$text[] = $c['Field'];
			}
		}
		if ( ! $text ) return null;

		$like  = '%' . $wpdb->esc_like( $keyword ) . '%';
		$where = implode( ' OR ', array_map( fn( $c ) => "`{$c}` LIKE %s", $text ) );
		$args  = array_fill( 0, count( $text ), $like );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $args ) );
		if ( ! $total ) return null;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where} LIMIT 25", $args ), ARRAY_A );
		$hits = [];
		foreach ( $rows as $row ) {
			$pk    = array_key_first( $row );
			$cells = [];
			foreach ( $row as $col => $val ) {
				if ( $val !== null && stripos( $val, $keyword ) !== false ) {
					$plain = trim( strip_tags( $val ) );
					$pos   = stripos( $plain, $keyword );
					$start = max( 0, $pos - 50 );
					$snip  = mb_substr( $plain, $start, 150 );
					$cells[] = [ 'col' => $col, 'snippet' => ( $start > 0 ? '…' : '' ) . $snip ];
				}
			}
			if ( $cells ) $hits[] = [ 'id' => $row[ $pk ], 'cells' => $cells ];
		}
		return $hits ? [ 'table' => $table, 'total' => $total, 'shown' => count( $hits ), 'hits' => $hits ] : null;
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );
		switch ( $action ) {
			case 'files':
				$r = self::files(
					sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
					array_map( 'sanitize_text_field', (array) ( $_POST['extensions'] ?? [] ) )
				);
				wp_send_json_success( [ 'results' => $r, 'total' => count( $r ) ] );
				break;
			case 'database':
				$r = self::database(
					sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
					array_map( 'sanitize_text_field', (array) ( $_POST['tables'] ?? [] ) )
				);
				wp_send_json_success( [ 'results' => $r, 'total' => count( $r ) ] );
				break;
			case 'enumerate':
				$files = self::enumerate_files(
					array_map( 'sanitize_text_field', (array) ( $_POST['extensions'] ?? [] ) )
				);
				wp_send_json_success( [ 'files' => $files, 'total' => count( $files ) ] );
				break;
			case 'scan_batch':
				$r = self::scan_files_batch(
					sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
					array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['paths'] ?? [] ) ) )
				);
				wp_send_json_success( [ 'results' => $r ] );
				break;
			case 'db_tables':
				$t = self::db_scan_list(
					array_map( 'sanitize_text_field', (array) ( $_POST['tables'] ?? [] ) )
				);
				wp_send_json_success( [ 'tables' => $t, 'total' => count( $t ) ] );
				break;
			case 'scan_table':
				$r = self::scan_table(
					sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
					sanitize_text_field( wp_unslash( $_POST['table'] ?? '' ) )
				);
				wp_send_json_success( [ 'result' => $r ] );
				break;
		}
		wp_die();
	}
}
