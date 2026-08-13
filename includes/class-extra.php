<?php
/**
 * Mail catcher, wp-config editor, plugins/themes, notes and the snippet runner.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Extra {

	/** Constants whose values are never shown or editable in the UI. */
	const SECRET_CONSTANT_PATTERN = '/(PASSWORD|SECRET|_KEY$|_SALT$)/i';

	const MAX_MAIL_LOG = 50;

	/* ---------------- Mail Catcher ---------------- */

	public static function init_mail_catcher() {
		if ( get_option( 'devbench_mail_catcher', false ) ) {
			add_filter( 'pre_wp_mail', array( __CLASS__, 'catch_mail' ), 99, 2 );
		}
	}

	/**
	 * Record an outgoing mail and stop it being delivered.
	 *
	 * Hooked to pre_wp_mail rather than wp_mail: pre_wp_mail short-circuits
	 * delivery, and once it does the wp_mail filter never runs, so capture and
	 * cancellation have to happen in the same callback.
	 *
	 * @param null|bool $short_circuit Incoming short-circuit value (ignored).
	 * @param array     $atts          wp_mail() arguments.
	 * @return false Always false, so nothing is sent.
	 */
	public static function catch_mail( $short_circuit, $atts ) {
		$to = isset( $atts['to'] ) ? $atts['to'] : '';

		$log = (array) get_option( 'devbench_mail_log', array() );
		array_unshift(
			$log,
			array(
				'id'      => uniqid( 'm_' ),
				'time'    => current_time( 'mysql' ),
				'to'      => is_array( $to ) ? implode( ', ', $to ) : (string) $to,
				'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
				'message' => isset( $atts['message'] ) ? (string) $atts['message'] : '',
				'headers' => isset( $atts['headers'] ) && is_array( $atts['headers'] )
					? implode( "\n", $atts['headers'] )
					: ( isset( $atts['headers'] ) ? (string) $atts['headers'] : '' ),
			)
		);

		update_option( 'devbench_mail_log', array_slice( $log, 0, self::MAX_MAIL_LOG ), false );

		return false;
	}

	/* ---------------- WP Config Editor ---------------- */

	/** Whether a constant holds a credential or key and must stay hidden. */
	private static function is_secret( $name ) {
		return (bool) preg_match( self::SECRET_CONSTANT_PATTERN, $name );
	}

	/** Parse the constants currently defined in wp-config.php. */
	public static function config_constants() {
		$path = DevBench_Helpers::wp_config_path();
		if ( ! $path ) {
			return array();
		}

		$content = DevBench_FS::read( $path );
		if ( false === $content ) {
			return array();
		}

		preg_match_all( "/define\(\s*['\"]([^'\"]+)['\"]\s*,\s*([^;]+?)\s*\)\s*;/", $content, $matches, PREG_SET_ORDER );

		$out = array();
		foreach ( $matches as $match ) {
			$name = $match[1];
			$raw  = trim( $match[2] );

			if ( 'true' === $raw || 'false' === $raw ) {
				$type  = 'bool';
				$value = $raw;
			} elseif ( is_numeric( $raw ) ) {
				$type  = 'int';
				$value = $raw;
			} else {
				$type  = 'string';
				$value = trim( $raw, " '\"" );
			}

			$secret = self::is_secret( $name );

			$out[] = array(
				'name'      => $name,
				'value'     => $secret ? '••••••••' : $value,
				'type'      => $type,
				'protected' => $secret,
			);
		}

		return $out;
	}

	/** @return true|WP_Error */
	private static function set_constant( $name, $value, $type ) {
		$name = strtoupper( trim( $name ) );

		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
			return new WP_Error( 'invalid', __( 'Invalid constant name.', 'devbench' ) );
		}
		if ( self::is_secret( $name ) ) {
			return new WP_Error( 'protected', __( 'Keys, salts and passwords cannot be edited from DevBench.', 'devbench' ) );
		}

		if ( 'bool' === $type ) {
			$literal = ( 'true' === $value || '1' === $value ) ? 'true' : 'false';
		} elseif ( 'int' === $type ) {
			$literal = (string) (int) $value;
		} else {
			$literal = "'" . addslashes( $value ) . "'";
		}

		return DevBench_Helpers::set_config_constant( $name, $literal );
	}

	/** @return true|WP_Error */
	private static function delete_constant( $name ) {
		$name = strtoupper( trim( $name ) );

		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
			return new WP_Error( 'invalid', __( 'Invalid constant name.', 'devbench' ) );
		}
		if ( self::is_secret( $name ) ) {
			return new WP_Error( 'protected', __( 'Keys, salts and passwords cannot be deleted from DevBench.', 'devbench' ) );
		}

		return DevBench_Helpers::delete_config_constant( $name );
	}

	/* ---------------- Plugins & Themes ---------------- */

	public static function plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$out    = array();

		foreach ( $all as $file => $data ) {
			$out[] = array(
				'file'        => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'author'      => wp_strip_all_tags( $data['Author'] ),
				'description' => wp_strip_all_tags( $data['Description'] ),
				'active'      => in_array( $file, $active, true ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	public static function themes() {
		$current = get_stylesheet();
		$out     = array();

		foreach ( wp_get_themes() as $slug => $theme ) {
			$out[] = array(
				'slug'       => $slug,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'author'     => wp_strip_all_tags( $theme->get( 'Author' ) ),
				'screenshot' => $theme->get_screenshot(),
				'active'     => $slug === $current,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	/* ---------------- Snippet Runner ---------------- */

	/**
	 * Execute a PHP snippet in the WordPress context and capture its output.
	 *
	 * This is the plugin's headline "run PHP" feature. It is reachable only by a
	 * user who passes DevBench_Helpers::can_write(), i.e. holds the plugin
	 * capability and is not blocked by DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS —
	 * exactly the same bar WordPress applies to its own theme/plugin editor,
	 * which grants equivalent code execution.
	 *
	 * @param string $code PHP source, without opening tags.
	 * @return array|WP_Error
	 */
	public static function run_snippet( $code ) {
		if ( ! DevBench_Helpers::can_write() ) {
			return DevBench_Helpers::write_blocked();
		}

		$errors = array();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Temporarily installed so snippet warnings/notices can be reported back in the UI; restored below.
		set_error_handler(
			static function ( $number, $message, $file, $line ) use ( &$errors ) {
				$errors[] = "[{$number}] {$message} in " . basename( $file ) . ":{$line}";
				return true;
			}
		);

		ob_start();
		try {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged, Generic.PHP.ForbiddenFunctions.Found -- Running administrator-authored PHP is this tool's entire purpose; see the method docblock for the access controls.
			eval( $code );
		} catch ( \Throwable $e ) {
			$errors[] = get_class( $e ) . ': ' . $e->getMessage() . ' (line ' . $e->getLine() . ')';
		}
		$output = ob_get_clean();

		restore_error_handler();

		return array(
			'output' => $output,
			'errors' => $errors ? implode( "\n", $errors ) : null,
		);
	}

	/* ---------------- AJAX ---------------- */

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		switch ( $action ) {

			/* Mail */
			case 'mail_toggle':
				$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_key( wp_unslash( $_POST['enabled'] ) );
				update_option( 'devbench_mail_catcher', $enabled );
				wp_send_json_success();
				break;

			case 'mail_list':
				wp_send_json_success( (array) get_option( 'devbench_mail_log', array() ) );
				break;

			case 'mail_clear':
				update_option( 'devbench_mail_log', array(), false );
				wp_send_json_success();
				break;

			case 'mail_delete':
				$log = array_values(
					array_filter(
						(array) get_option( 'devbench_mail_log', array() ),
						static function ( $mail ) use ( $id ) {
							return isset( $mail['id'] ) && $mail['id'] !== $id;
						}
					)
				);
				update_option( 'devbench_mail_log', $log, false );
				wp_send_json_success();
				break;

			/* Config */
			case 'config_list':
				wp_send_json_success( self::config_constants() );
				break;

			case 'config_set':
				$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
				$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'string';

				$result = self::set_constant( $name, $value, $type );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success();
				break;

			case 'config_delete':
				$result = self::delete_constant( $name );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success();
				break;

			/* Plugins & Themes */
			case 'plugins_list':
				wp_send_json_success( self::plugins() );
				break;

			case 'plugin_toggle':
				if ( ! current_user_can( 'activate_plugins' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
				}
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$file     = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
				$activate = isset( $_POST['activate'] ) && '1' === sanitize_key( wp_unslash( $_POST['activate'] ) );

				if ( ! array_key_exists( $file, get_plugins() ) ) {
					wp_send_json_error( __( 'Unknown plugin.', 'devbench' ) );
				}

				if ( $activate ) {
					$result = activate_plugin( $file );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( $result->get_error_message() );
					}
				} else {
					deactivate_plugins( $file );
				}
				wp_send_json_success();
				break;

			case 'themes_list':
				wp_send_json_success( self::themes() );
				break;

			case 'theme_activate':
				if ( ! current_user_can( 'switch_themes' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
				}
				$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
				if ( ! wp_get_theme( $slug )->exists() ) {
					wp_send_json_error( __( 'Theme not found.', 'devbench' ) );
				}
				switch_theme( $slug );
				wp_send_json_success();
				break;

			/* Notes */
			case 'note_list':
				wp_send_json_success( (array) get_option( 'devbench_notes', array() ) );
				break;

			case 'note_save':
				$notes = (array) get_option( 'devbench_notes', array() );
				$entry = array(
					'id'      => $id ? $id : uniqid( 'n_' ),
					'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Untitled', 'devbench' ),
					'body'    => isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '',
					'pinned'  => isset( $_POST['pinned'] ) && '1' === sanitize_key( wp_unslash( $_POST['pinned'] ) ),
					'updated' => time(),
				);

				if ( $id ) {
					$found = false;
					foreach ( $notes as $index => $note ) {
						if ( isset( $note['id'] ) && $note['id'] === $id ) {
							$notes[ $index ] = $entry;
							$found           = true;
							break;
						}
					}
					if ( ! $found ) {
						array_unshift( $notes, $entry );
					}
				} else {
					array_unshift( $notes, $entry );
				}

				usort(
					$notes,
					static function ( $a, $b ) {
						$pinned = $b['pinned'] <=> $a['pinned'];
						return $pinned ? $pinned : ( $b['updated'] <=> $a['updated'] );
					}
				);

				update_option( 'devbench_notes', $notes, false );
				wp_send_json_success();
				break;

			case 'note_delete':
				$notes = array_values(
					array_filter(
						(array) get_option( 'devbench_notes', array() ),
						static function ( $note ) use ( $id ) {
							return isset( $note['id'] ) && $note['id'] !== $id;
						}
					)
				);
				update_option( 'devbench_notes', $notes, false );
				wp_send_json_success();
				break;

			/* Snippet */
			case 'snippet_run':
				// Snippet bodies are executed verbatim; sanitizing would corrupt them.
				$code = isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw PHP by design; see run_snippet() for the access controls.
				if ( ! trim( $code ) ) {
					wp_send_json_error( __( 'No code provided.', 'devbench' ) );
				}

				$result = self::run_snippet( $code );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success( $result );
				break;
		}

		wp_die();
	}
}
