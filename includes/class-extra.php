<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Extra {

	/* ---------------- Mail Catcher ---------------- */

	public static function init_mail_catcher() {
		if ( get_option( 'devbench_mail_catcher', false ) ) {
			add_filter( 'wp_mail', [ __CLASS__, 'catch_mail' ] );
			add_filter( 'pre_wp_mail', '__return_false', 99 );
		}
	}

	public static function catch_mail( $args ) {
		$log = get_option( 'devbench_mail_log', [] );
		array_unshift( $log, [
			'id'      => uniqid( 'm_' ),
			'time'    => current_time( 'mysql' ),
			'to'      => is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : $args['to'],
			'subject' => $args['subject'] ?? '',
			'message' => $args['message'] ?? '',
			'headers' => is_array( $args['headers'] ?? '' ) ? implode( "\n", $args['headers'] ) : ( $args['headers'] ?? '' ),
		] );
		update_option( 'devbench_mail_log', array_slice( $log, 0, 50 ), false );
		return $args;
	}

	/* ---------------- WP Config Editor ---------------- */

	public static function config_constants() {
		$path = DevBench_Helpers::wp_config_path();
		if ( ! $path ) return [];
		$content = file_get_contents( $path );
		preg_match_all( "/define\(\s*['\"]([^'\"]+)['\"]\s*,\s*([^;]+?)\s*\)\s*;/", $content, $m, PREG_SET_ORDER );
		$out = [];
		foreach ( $m as $row ) {
			$raw = trim( $row[2] );
			if ( $raw === 'true' || $raw === 'false' ) {
				$type = 'bool'; $val = $raw;
			} elseif ( is_numeric( $raw ) ) {
				$type = 'int'; $val = $raw;
			} else {
				$type = 'string'; $val = trim( $raw, " '\"" );
			}
			$out[] = [ 'name' => $row[1], 'value' => $val, 'type' => $type ];
		}
		return $out;
	}

	private static function set_constant( $name, $value, $type ) {
		$name = strtoupper( trim( $name ) );
		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
			return new WP_Error( 'invalid', 'Invalid constant name.' );
		}
		if ( $type === 'bool' ) {
			$literal = ( $value === 'true' || $value === '1' ) ? 'true' : 'false';
		} elseif ( $type === 'int' ) {
			$literal = (string) (int) $value;
		} else {
			$literal = "'" . addslashes( $value ) . "'";
		}
		return DevBench_Helpers::set_config_constant( $name, $literal );
	}

	/* ---------------- Plugins & Themes ---------------- */

	public static function plugins() {
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', [] );
		$out    = [];
		foreach ( $all as $file => $d ) {
			$out[] = [
				'file'        => $file,
				'name'        => $d['Name'],
				'version'     => $d['Version'],
				'author'      => wp_strip_all_tags( $d['Author'] ),
				'description' => wp_strip_all_tags( $d['Description'] ),
				'active'      => in_array( $file, $active, true ),
			];
		}
		usort( $out, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
		return $out;
	}

	public static function themes() {
		$current = get_stylesheet();
		$out     = [];
		foreach ( wp_get_themes() as $slug => $t ) {
			$out[] = [
				'slug'       => $slug,
				'name'       => $t->get( 'Name' ),
				'version'    => $t->get( 'Version' ),
				'author'     => wp_strip_all_tags( $t->get( 'Author' ) ),
				'screenshot' => $t->get_screenshot(),
				'active'     => $slug === $current,
			];
		}
		usort( $out, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
		return $out;
	}

	/* ---------------- Snippet Runner ---------------- */

	public static function run_snippet( $code ) {
		$errors = [];
		set_error_handler( function ( $no, $str, $file, $line ) use ( &$errors ) {
			$errors[] = "[{$no}] {$str} in " . basename( $file ) . ":{$line}";
			return true;
		} );
		ob_start();
		try {
			eval( $code ); // phpcs:ignore Squiz.PHP.Eval
		} catch ( \Throwable $e ) {
			$errors[] = get_class( $e ) . ': ' . $e->getMessage() . ' (line ' . $e->getLine() . ')';
		}
		$output = ob_get_clean();
		restore_error_handler();
		return [ 'output' => $output, 'errors' => $errors ? implode( "\n", $errors ) : null ];
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );

		switch ( $action ) {

			/* Mail */
			case 'mail_toggle':
				update_option( 'devbench_mail_catcher', ( $_POST['enabled'] ?? '' ) === '1' );
				wp_send_json_success();
				break;
			case 'mail_list':
				wp_send_json_success( get_option( 'devbench_mail_log', [] ) );
				break;
			case 'mail_clear':
				update_option( 'devbench_mail_log', [], false );
				wp_send_json_success();
				break;
			case 'mail_delete':
				$id  = sanitize_text_field( $_POST['id'] ?? '' );
				$log = array_values( array_filter( get_option( 'devbench_mail_log', [] ), fn( $m ) => $m['id'] !== $id ) );
				update_option( 'devbench_mail_log', $log, false );
				wp_send_json_success();
				break;

			/* Config */
			case 'config_list':
				wp_send_json_success( self::config_constants() );
				break;
			case 'config_set':
				$r = self::set_constant(
					sanitize_text_field( $_POST['name'] ?? '' ),
					wp_unslash( $_POST['value'] ?? '' ),
					sanitize_text_field( $_POST['type'] ?? 'string' )
				);
				is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success();
				break;
			case 'config_delete':
				$r = DevBench_Helpers::delete_config_constant( sanitize_text_field( $_POST['name'] ?? '' ) );
				is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success();
				break;

			/* Plugins & Themes */
			case 'plugins_list':
				wp_send_json_success( self::plugins() );
				break;
			case 'plugin_toggle':
				if ( ! function_exists( 'activate_plugin' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$file = sanitize_text_field( $_POST['file'] ?? '' );
				if ( ( $_POST['activate'] ?? '' ) === '1' ) {
					$r = activate_plugin( $file );
					is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success();
				} else {
					deactivate_plugins( $file );
					wp_send_json_success();
				}
				break;
			case 'themes_list':
				wp_send_json_success( self::themes() );
				break;
			case 'theme_activate':
				$slug = sanitize_text_field( $_POST['slug'] ?? '' );
				if ( ! wp_get_theme( $slug )->exists() ) wp_send_json_error( 'Theme not found.' );
				switch_theme( $slug );
				wp_send_json_success();
				break;

			/* Notes */
			case 'note_list':
				wp_send_json_success( get_option( 'devbench_notes', [] ) );
				break;
			case 'note_save':
				$notes = get_option( 'devbench_notes', [] );
				$id    = sanitize_text_field( $_POST['id'] ?? '' );
				$entry = [
					'id'      => $id ?: uniqid( 'n_' ),
					'title'   => sanitize_text_field( $_POST['title'] ?? 'Untitled' ),
					'body'    => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
					'pinned'  => ( $_POST['pinned'] ?? '' ) === '1',
					'updated' => time(),
				];
				if ( $id ) {
					$found = false;
					foreach ( $notes as &$n ) if ( $n['id'] === $id ) { $n = $entry; $found = true; break; }
					unset( $n );
					if ( ! $found ) array_unshift( $notes, $entry );
				} else {
					array_unshift( $notes, $entry );
				}
				usort( $notes, fn( $a, $b ) => ( $b['pinned'] <=> $a['pinned'] ) ?: ( $b['updated'] <=> $a['updated'] ) );
				update_option( 'devbench_notes', $notes, false );
				wp_send_json_success();
				break;
			case 'note_delete':
				$id    = sanitize_text_field( $_POST['id'] ?? '' );
				$notes = array_values( array_filter( get_option( 'devbench_notes', [] ), fn( $n ) => $n['id'] !== $id ) );
				update_option( 'devbench_notes', $notes, false );
				wp_send_json_success();
				break;

			/* Snippet */
			case 'snippet_run':
				$code = wp_unslash( $_POST['code'] ?? '' );
				if ( ! $code ) wp_send_json_error( 'No code provided.' );
				wp_send_json_success( self::run_snippet( $code ) );
				break;
		}
		wp_die();
	}
}
