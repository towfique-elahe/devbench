<?php
/**
 * Options, transients and cron tooling.
 *
 * The options queries read wp_options directly because they sort and filter by
 * value length and autoload state, which no core API exposes.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Tools {

	/* ---------------- Options ---------------- */

	/**
	 * The autoload column values that mean "autoloaded".
	 *
	 * WordPress 6.6 replaced the yes/no pair with on/off/auto variants, and the
	 * set is filterable, so it has to be read from core rather than hardcoded.
	 *
	 * @return string[]
	 */
	private static function autoload_yes_values() {
		$values = array_values( array_unique( (array) wp_autoload_values_to_autoload() ) );
		return $values ? $values : array( 'yes' );
	}

	/**
	 * A comma-separated list of '%s' placeholders, one per value.
	 *
	 * Mirrors the idiom core uses in wp_load_alloptions(): the fragment is built
	 * only from literal placeholders, so the values stay bound by prepare().
	 *
	 * @param array $values Values that will be bound.
	 * @return string
	 */
	private static function placeholders( array $values ) {
		return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	}

	/**
	 * List options, largest first.
	 *
	 * @param string $search   Substring of the option name.
	 * @param string $autoload 'all', 'yes' or 'no'.
	 * @param int    $limit    Maximum rows.
	 */
	public static function options( $search = '', $autoload = 'all', $limit = 200 ) {
		global $wpdb;

		$limit  = max( 1, min( 1000, (int) $limit ) );
		$values = self::autoload_yes_values();
		$in     = self::placeholders( $values );

		// An empty search yields the pattern '%%', which matches every row, so
		// searching and browsing share the same query shape.
		$like = '%' . $wpdb->esc_like( $search ) . '%';

		/*
		 * The only interpolated fragment below is {$in}: a comma-separated list
		 * of '%s' placeholders produced by self::placeholders(). Every value,
		 * including the autoload vocabulary, is bound through prepare(). The
		 * queries are intentionally uncached because this screen exists to show
		 * the live contents of wp_options.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( 'yes' === $autoload ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s AND autoload IN ( {$in} ) ORDER BY LENGTH(option_value) DESC LIMIT %d",
					array_merge( array( $like ), $values, array( $limit ) )
				),
				ARRAY_A
			);
		} elseif ( 'no' === $autoload ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s AND autoload NOT IN ( {$in} ) ORDER BY LENGTH(option_value) DESC LIMIT %d",
					array_merge( array( $like ), $values, array( $limit ) )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY LENGTH(option_value) DESC LIMIT %d",
					$like,
					$limit
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array_map(
			static function ( $row ) {
				return array(
					'id'       => (int) $row['option_id'],
					'name'     => $row['option_name'],
					'size'     => strlen( $row['option_value'] ),
					'autoload' => $row['autoload'],
					'preview'  => mb_substr( $row['option_value'], 0, 120 ),
				);
			},
			(array) $rows
		);
	}

	/** Total byte size of all autoloaded option values. */
	public static function autoload_size() {
		global $wpdb;

		$values = self::autoload_yes_values();
		$in     = self::placeholders( $values );

		/*
		 * As in options(): {$in} is only '%s' placeholders and $values is bound
		 * through prepare(). Uncached on purpose — this is a live measurement.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ( {$in} )",
				$values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $total;
	}

	/* ---------------- Transients ---------------- */

	public static function transients() {
		global $wpdb;

		$prefix  = $wpdb->esc_like( '_transient_' ) . '%';
		$timeout = $wpdb->esc_like( '_transient_timeout_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient inspector; must read the live table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$prefix,
				$timeout
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$key     = substr( $row['option_name'], strlen( '_transient_' ) );
			$expires = (int) get_option( '_transient_timeout_' . $key );

			if ( 0 === $expires ) {
				$status = 'persistent';
			} elseif ( $expires < time() ) {
				$status = 'expired';
			} else {
				$status = 'active';
			}

			$out[] = array(
				'key'     => $key,
				'size'    => strlen( $row['option_value'] ),
				'expires' => $expires,
				'status'  => $status,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return $b['size'] <=> $a['size'];
			}
		);
		return $out;
	}

	/* ---------------- Cron ---------------- */

	public static function cron_events() {
		$crons     = _get_cron_array();
		$schedules = wp_get_schedules();
		$out       = array();

		foreach ( (array) $crons as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				foreach ( (array) $events as $event ) {
					$schedule = ! empty( $event['schedule'] ) ? $event['schedule'] : '';
					$out[]    = array(
						'hook'     => $hook,
						'next'     => (int) $timestamp,
						'schedule' => $schedule ? $schedule : 'one-time',
						'interval' => isset( $schedules[ $schedule ]['display'] ) ? $schedules[ $schedule ]['display'] : __( 'One-time', 'devbench' ),
						'overdue'  => $timestamp < time(),
					);
				}
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return $a['next'] <=> $b['next'];
			}
		);
		return $out;
	}

	/* ---------------- AJAX ---------------- */

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$hook   = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';

		switch ( $action ) {

			/* Options */
			case 'options_list':
				$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
				$autoload = isset( $_POST['autoload'] ) ? sanitize_key( wp_unslash( $_POST['autoload'] ) ) : 'all';
				wp_send_json_success( self::options( $search, $autoload ) );
				break;

			case 'option_get':
				if ( '' === $name ) {
					wp_send_json_error( __( 'No option specified.', 'devbench' ) );
				}
				wp_send_json_success( array( 'value' => maybe_serialize( get_option( $name ) ) ) );
				break;

			case 'option_update':
				if ( ! DevBench_Helpers::can_write() ) {
					wp_send_json_error( DevBench_Helpers::write_blocked()->get_error_message() );
				}
				if ( '' === $name ) {
					wp_send_json_error( __( 'No option specified.', 'devbench' ) );
				}
				// Option values are stored verbatim: they may be serialized data.
				$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw option payload by design; guarded by capability and nonce.
				update_option( $name, maybe_unserialize( $value ) );
				wp_send_json_success();
				break;

			case 'option_delete':
				if ( ! DevBench_Helpers::can_write() ) {
					wp_send_json_error( DevBench_Helpers::write_blocked()->get_error_message() );
				}
				if ( '' === $name ) {
					wp_send_json_error( __( 'No option specified.', 'devbench' ) );
				}
				delete_option( $name );
				wp_send_json_success();
				break;

			/* Transients */
			case 'transients_list':
				wp_send_json_success( self::transients() );
				break;

			case 'transient_delete':
				$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
				if ( '' === $key ) {
					wp_send_json_error( __( 'No transient specified.', 'devbench' ) );
				}
				delete_transient( $key );
				wp_send_json_success();
				break;

			case 'transients_clear_expired':
				$cleared = 0;
				foreach ( self::transients() as $transient ) {
					if ( 'expired' === $transient['status'] ) {
						delete_transient( $transient['key'] );
						++$cleared;
					}
				}
				wp_send_json_success( array( 'cleared' => $cleared ) );
				break;

			/* Cron */
			case 'cron_list':
				wp_send_json_success( self::cron_events() );
				break;

			case 'cron_run':
				if ( '' === $hook ) {
					wp_send_json_error( __( 'No hook specified.', 'devbench' ) );
				}
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Fires an existing scheduled hook on demand; the name comes from the cron array, not from DevBench.
				do_action( $hook );
				wp_send_json_success();
				break;

			case 'cron_unschedule':
				if ( '' === $hook ) {
					wp_send_json_error( __( 'No hook specified.', 'devbench' ) );
				}
				$timestamp = wp_next_scheduled( $hook );
				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, $hook );
				}
				wp_send_json_success();
				break;
		}

		wp_die();
	}
}
