<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Database {

	const PER_PAGE = 25;

	public static function tables() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$out  = [];
		foreach ( $rows as $r ) {
			$out[] = [
				'name'   => $r['Name'],
				'rows'   => (int) $r['Rows'],
				'size'   => (int) $r['Data_length'] + (int) $r['Index_length'],
				'engine' => $r['Engine'],
			];
		}
		return $out;
	}

	public static function structure( $table ) {
		global $wpdb;
		if ( ! self::valid_table( $table ) ) return new WP_Error( 'invalid', 'Unknown table.' );
		return $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
	}

	public static function browse( $table, $page = 1 ) {
		global $wpdb;
		if ( ! self::valid_table( $table ) ) return new WP_Error( 'invalid', 'Unknown table.' );
		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", self::PER_PAGE, $offset ),
			ARRAY_A
		);
		$cols = $rows ? array_keys( $rows[0] ) : array_map( fn( $c ) => $c['Field'], $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ) );
		return [
			'columns'   => $cols,
			'rows'      => $rows,
			'total'     => $total,
			'page'      => $page,
			'per_page'  => self::PER_PAGE,
			'pages'     => (int) ceil( $total / self::PER_PAGE ),
		];
	}

	public static function run_query( $sql ) {
		global $wpdb;
		$sql     = trim( $sql );
		$blocked = '/^\s*(DROP\s+DATABASE|DROP\s+TABLE|TRUNCATE)\b/i';
		if ( preg_match( $blocked, $sql ) ) {
			return new WP_Error( 'blocked', 'DROP DATABASE, DROP TABLE, and TRUNCATE are blocked for safety.' );
		}
		$is_select = (bool) preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql );

		$wpdb->suppress_errors( true );
		if ( $is_select ) {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
			if ( $wpdb->last_error ) return new WP_Error( 'sql', $wpdb->last_error );
			return [
				'type'    => 'select',
				'columns' => $rows ? array_keys( $rows[0] ) : [],
				'rows'    => $rows ?: [],
				'count'   => count( $rows ?: [] ),
			];
		}
		$affected = $wpdb->query( $sql );
		if ( $wpdb->last_error ) return new WP_Error( 'sql', $wpdb->last_error );
		return [ 'type' => 'write', 'affected' => (int) $affected ];
	}

	public static function export( $table ) {
		global $wpdb;
		if ( ! self::valid_table( $table ) ) return new WP_Error( 'invalid', 'Unknown table.' );
		$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		$sql    = "-- DevBench export of `{$table}`\n-- " . date( 'Y-m-d H:i:s' ) . "\n\n";
		$sql   .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n";
		$rows   = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
		foreach ( $rows as $row ) {
			$vals = array_map( function ( $v ) use ( $wpdb ) {
				return is_null( $v ) ? 'NULL' : "'" . esc_sql( $v ) . "'";
			}, array_values( $row ) );
			$sql .= "INSERT INTO `{$table}` VALUES (" . implode( ', ', $vals ) . ");\n";
		}
		return $sql;
	}

	private static function valid_table( $table ) {
		global $wpdb;
		static $cache = null;
		if ( $cache === null ) $cache = $wpdb->get_col( 'SHOW TABLES' );
		return in_array( $table, $cache, true );
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );
		$reply  = fn( $r ) => is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success( $r );

		switch ( $action ) {
			case 'tables':
				wp_send_json_success( self::tables() );
				break;
			case 'structure':
				$reply( self::structure( sanitize_text_field( $_POST['table'] ?? '' ) ) );
				break;
			case 'browse':
				$reply( self::browse( sanitize_text_field( $_POST['table'] ?? '' ), (int) ( $_POST['page'] ?? 1 ) ) );
				break;
			case 'query':
				$reply( self::run_query( wp_unslash( $_POST['sql'] ?? '' ) ) );
				break;
			case 'export':
				$sql = self::export( sanitize_text_field( $_POST['table'] ?? '' ) );
				$reply( is_wp_error( $sql ) ? $sql : [ 'sql' => $sql ] );
				break;
		}
		wp_die();
	}
}
