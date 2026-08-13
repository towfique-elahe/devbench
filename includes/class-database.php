<?php
/**
 * Database browser, SQL runner and table export.
 *
 * Every query in this class is a deliberate, uncached inspection of live schema
 * or row data — that is the feature. Table and column identifiers always reach
 * the database through $wpdb->prepare()'s %i placeholder, and free-form SQL is
 * only ever executed for a user who already holds the plugin's capability.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Database {

	const PER_PAGE      = 25;
	const EXPORT_CHUNK  = 500;

	/** Cached list of table names for this request. */
	private static $table_cache = null;

	/** All table names in the current database. */
	public static function table_names() {
		global $wpdb;

		if ( null === self::$table_cache ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema listing has no core API; cached per request in self::$table_cache.
			self::$table_cache = $wpdb->get_col( 'SHOW TABLES' );
		}
		return self::$table_cache;
	}

	/** Whether $table is a real table in this database. */
	public static function valid_table( $table ) {
		return in_array( $table, self::table_names(), true );
	}

	public static function tables() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live table metadata; caching would defeat the purpose of the screen.
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'name'   => $row['Name'],
				// Deliberately not $row['Rows'] — see row_count().
				'rows'   => self::row_count( $row['Name'] ),
				'size'   => (int) $row['Data_length'] + (int) $row['Index_length'],
				'engine' => $row['Engine'],
			);
		}
		return $out;
	}

	/**
	 * Exact number of rows in a table.
	 *
	 * SHOW TABLE STATUS reports an *estimate* for InnoDB — WordPress's default
	 * engine — taken from sampled optimizer statistics. MySQL documents it as
	 * varying from the true value by up to 40-50%, and on a small or recently
	 * created database the samples do not exist yet, so it reports 0 for every
	 * table. The number therefore has to come from COUNT(*).
	 *
	 * That costs one query per table, which is the right trade for a screen
	 * whose entire job is telling you what is actually in the database, and
	 * keeps the sidebar consistent with the count browse() already shows.
	 */
	private static function row_count( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live row count; identifier passed via %i.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/** @return array|WP_Error */
	public static function structure( $table ) {
		global $wpdb;

		if ( ! self::valid_table( $table ) ) {
			return new WP_Error( 'invalid', __( 'Unknown table.', 'devbench' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live schema inspection; identifier passed via %i.
		return $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ), ARRAY_A );
	}

	/** @return array|WP_Error */
	public static function browse( $table, $page = 1 ) {
		global $wpdb;

		if ( ! self::valid_table( $table ) ) {
			return new WP_Error( 'invalid', __( 'Unknown table.', 'devbench' ) );
		}

		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * self::PER_PAGE;

		$total = self::row_count( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Arbitrary table browsing; identifier passed via %i.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i LIMIT %d OFFSET %d', $table, self::PER_PAGE, $offset ),
			ARRAY_A
		);

		if ( $rows ) {
			$columns = array_keys( $rows[0] );
		} else {
			$described = self::structure( $table );
			$columns   = is_wp_error( $described ) ? array() : wp_list_pluck( $described, 'Field' );
		}

		return array(
			'columns'  => $columns,
			'rows'     => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => self::PER_PAGE,
			'pages'    => (int) ceil( $total / self::PER_PAGE ),
		);
	}

	/** @return array|WP_Error */
	public static function run_query( $sql ) {
		global $wpdb;

		if ( ! DevBench_Helpers::can_write() ) {
			return DevBench_Helpers::write_blocked();
		}

		$sql     = trim( $sql );
		$blocked = '/^\s*(DROP\s+DATABASE|DROP\s+TABLE|TRUNCATE)\b/i';

		if ( preg_match( $blocked, $sql ) ) {
			return new WP_Error( 'blocked', __( 'DROP DATABASE, DROP TABLE and TRUNCATE are blocked for safety.', 'devbench' ) );
		}

		$is_select = (bool) preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql );

		$wpdb->suppress_errors( true );

		if ( $is_select ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deliberate SQL console; the query is authored by an administrator who already holds the plugin capability, passes a nonce check and is not blocked by DISALLOW_FILE_EDIT.
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			if ( $wpdb->last_error ) {
				return new WP_Error( 'sql', $wpdb->last_error );
			}
			$rows = $rows ? $rows : array();

			return array(
				'type'    => 'select',
				'columns' => $rows ? array_keys( $rows[0] ) : array(),
				'rows'    => $rows,
				'count'   => count( $rows ),
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deliberate SQL console; see above.
		$affected = $wpdb->query( $sql );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'sql', $wpdb->last_error );
		}

		return array(
			'type'     => 'write',
			'affected' => (int) $affected,
		);
	}

	/**
	 * Dump a table as SQL. Rows are read in chunks so large tables do not have
	 * to be held in memory all at once.
	 *
	 * @return string|WP_Error
	 */
	public static function export( $table ) {
		global $wpdb;

		if ( ! self::valid_table( $table ) ) {
			return new WP_Error( 'invalid', __( 'Unknown table.', 'devbench' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- SHOW CREATE TABLE only reads the schema; identifier passed via %i.
		$create = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );

		if ( ! $create || ! isset( $create[1] ) ) {
			return new WP_Error( 'export_failed', __( 'Could not read the table definition.', 'devbench' ) );
		}

		$name = str_replace( '`', '``', $table );
		$sql  = "-- DevBench export of `{$name}`\n";
		$sql .= '-- ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
		$sql .= "DROP TABLE IF EXISTS `{$name}`;\n" . $create[1] . ";\n\n";

		$offset = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Full-table export; identifier passed via %i.
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i LIMIT %d OFFSET %d', $table, self::EXPORT_CHUNK, $offset ),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$values = array_map(
					static function ( $value ) {
						return null === $value ? 'NULL' : "'" . esc_sql( $value ) . "'";
					},
					array_values( $row )
				);
				$sql .= "INSERT INTO `{$name}` VALUES (" . implode( ', ', $values ) . ");\n";
			}

			$offset += self::EXPORT_CHUNK;
		} while ( count( (array) $rows ) === self::EXPORT_CHUNK );

		return $sql;
	}

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		$table  = isset( $_POST['table'] ) ? sanitize_text_field( wp_unslash( $_POST['table'] ) ) : '';

		$reply = static function ( $result ) {
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}
			wp_send_json_success( $result );
		};

		switch ( $action ) {
			case 'tables':
				wp_send_json_success( self::tables() );
				break;

			case 'structure':
				$reply( self::structure( $table ) );
				break;

			case 'browse':
				$page = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
				$reply( self::browse( $table, $page ) );
				break;

			case 'query':
				$sql = isset( $_POST['sql'] ) ? trim( wp_unslash( $_POST['sql'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Free-form SQL for the query console; sanitizing would corrupt it. Guarded by capability, nonce and a blocklist in run_query().
				$reply( self::run_query( $sql ) );
				break;

			case 'export':
				$sql = self::export( $table );
				$reply( is_wp_error( $sql ) ? $sql : array( 'sql' => $sql ) );
				break;
		}

		wp_die();
	}
}
