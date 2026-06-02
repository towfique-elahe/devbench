<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Tools {

	/* ---------------- Options ---------------- */

	public static function options( $search = '', $autoload = 'all', $limit = 200 ) {
		global $wpdb;
		$where = '1=1';
		$args  = [];
		if ( $search !== '' ) {
			$where .= ' AND option_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $autoload === 'yes' || $autoload === 'no' ) {
			$where .= ' AND autoload = %s';
			$args[] = $autoload;
		}
		$sql = "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE {$where} ORDER BY LENGTH(option_value) DESC LIMIT %d";
		$args[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return array_map( function ( $r ) {
			return [
				'id'       => (int) $r['option_id'],
				'name'     => $r['option_name'],
				'size'     => strlen( $r['option_value'] ),
				'autoload' => $r['autoload'],
				'preview'  => mb_substr( $r['option_value'], 0, 120 ),
			];
		}, $rows );
	}

	public static function autoload_size() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'" );
	}

	/* ---------------- Transients ---------------- */

	public static function transients() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' AND option_name NOT LIKE '\_transient\_timeout\_%'",
			ARRAY_A
		);
		$out = [];
		foreach ( $rows as $r ) {
			$key     = str_replace( '_transient_', '', $r['option_name'] );
			$timeout = (int) get_option( '_transient_timeout_' . $key );
			$out[]   = [
				'key'     => $key,
				'size'    => strlen( $r['option_value'] ),
				'expires' => $timeout,
				'status'  => $timeout === 0 ? 'persistent' : ( $timeout < time() ? 'expired' : 'active' ),
			];
		}
		usort( $out, fn( $a, $b ) => $b['size'] <=> $a['size'] );
		return $out;
	}

	/* ---------------- Cron ---------------- */

	public static function cron_events() {
		$crons = _get_cron_array();
		$out   = [];
		$schedules = wp_get_schedules();
		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $events ) {
				foreach ( $events as $event ) {
					$out[] = [
						'hook'      => $hook,
						'next'      => $timestamp,
						'schedule'  => $event['schedule'] ?: 'one-time',
						'interval'  => isset( $event['schedule'], $schedules[ $event['schedule'] ] ) ? $schedules[ $event['schedule'] ]['display'] : 'One-time',
						'overdue'   => $timestamp < time(),
					];
				}
			}
		}
		usort( $out, fn( $a, $b ) => $a['next'] <=> $b['next'] );
		return $out;
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );

		switch ( $action ) {

			/* Options */
			case 'options_list':
				wp_send_json_success( self::options(
					sanitize_text_field( $_POST['search'] ?? '' ),
					sanitize_text_field( $_POST['autoload'] ?? 'all' )
				) );
				break;
			case 'option_get':
				$val = get_option( sanitize_text_field( $_POST['name'] ?? '' ) );
				wp_send_json_success( [ 'value' => maybe_serialize( $val ) ] );
				break;
			case 'option_update':
				$name = sanitize_text_field( $_POST['name'] ?? '' );
				$val  = wp_unslash( $_POST['value'] ?? '' );
				update_option( $name, maybe_unserialize( $val ) );
				wp_send_json_success();
				break;
			case 'option_delete':
				delete_option( sanitize_text_field( $_POST['name'] ?? '' ) );
				wp_send_json_success();
				break;

			/* Transients */
			case 'transients_list':
				wp_send_json_success( self::transients() );
				break;
			case 'transient_delete':
				delete_transient( sanitize_text_field( $_POST['key'] ?? '' ) );
				wp_send_json_success();
				break;
			case 'transients_clear_expired':
				$n = 0;
				foreach ( self::transients() as $t ) {
					if ( $t['status'] === 'expired' ) { delete_transient( $t['key'] ); $n++; }
				}
				wp_send_json_success( [ 'cleared' => $n ] );
				break;

			/* Cron */
			case 'cron_list':
				wp_send_json_success( self::cron_events() );
				break;
			case 'cron_run':
				$hook = sanitize_text_field( $_POST['hook'] ?? '' );
				if ( $hook ) { do_action( $hook ); wp_send_json_success(); }
				wp_send_json_error( 'No hook specified.' );
				break;
			case 'cron_unschedule':
				$hook = sanitize_text_field( $_POST['hook'] ?? '' );
				$ts   = wp_next_scheduled( $hook );
				if ( $ts ) wp_unschedule_event( $ts, $hook );
				wp_send_json_success();
				break;
		}
		wp_die();
	}
}
