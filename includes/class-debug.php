<?php
/**
 * Debug constant toggles and debug.log access.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Debug {

	const CONSTANTS = array(
		'WP_DEBUG',
		'WP_DEBUG_LOG',
		'WP_DEBUG_DISPLAY',
		'SCRIPT_DEBUG',
		'SAVEQUERIES',
	);

	public static function log_path() {
		// Respect a custom WP_DEBUG_LOG path if set to a string.
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			return WP_DEBUG_LOG;
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	public static function log_size() {
		$path = self::log_path();
		return DevBench_FS::exists( $path ) ? DevBench_FS::size( $path ) : 0;
	}

	public static function log_exists() {
		return DevBench_FS::exists( self::log_path() );
	}

	/**
	 * Return the last N lines of the debug log.
	 *
	 * Uses SplFileObject rather than reading the whole file so a multi-megabyte
	 * log does not have to fit in memory.
	 *
	 * @param int $lines Number of trailing lines to return.
	 * @return string
	 */
	public static function tail( $lines = 1000 ) {
		$path = self::log_path();
		if ( ! DevBench_FS::exists( $path ) ) {
			return '';
		}

		try {
			$file = new SplFileObject( $path, 'r' );
		} catch ( Exception $e ) {
			return '';
		}

		$file->seek( PHP_INT_MAX );
		$total = $file->key();
		$start = max( 0, $total - $lines );
		$out   = '';

		$file->seek( $start );
		while ( ! $file->eof() ) {
			$out .= $file->fgets();
		}
		return $out;
	}

	/** @return true|WP_Error */
	public static function clear_log() {
		if ( ! DevBench_Helpers::can_write() ) {
			return DevBench_Helpers::write_blocked();
		}
		$path = self::log_path();
		if ( ! DevBench_FS::exists( $path ) ) {
			return new WP_Error( 'not_found', __( 'There is no debug log to clear.', 'devbench' ) );
		}
		if ( ! DevBench_FS::is_writable( $path ) ) {
			return new WP_Error( 'not_writable', __( 'The debug log is not writable.', 'devbench' ) );
		}
		return DevBench_FS::write( $path, '' );
	}

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';

		switch ( $action ) {

			case 'toggle':
				$name    = isset( $_POST['constant'] ) ? sanitize_text_field( wp_unslash( $_POST['constant'] ) ) : '';
				$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_key( wp_unslash( $_POST['enabled'] ) );

				if ( ! in_array( $name, self::CONSTANTS, true ) ) {
					wp_send_json_error( __( 'Unknown constant.', 'devbench' ) );
				}

				$result = DevBench_Helpers::set_config_constant( $name, $enabled ? 'true' : 'false' );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success();
				break;

			case 'read_log':
				wp_send_json_success(
					array(
						'content' => self::tail( 1500 ),
						'size'    => DevBench_Helpers::filesize( self::log_size() ),
						'exists'  => self::log_exists(),
					)
				);
				break;

			case 'clear_log':
				$result = self::clear_log();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success();
				break;
		}

		wp_die();
	}
}
