<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Debug {

	const CONSTANTS = [
		'WP_DEBUG',
		'WP_DEBUG_LOG',
		'WP_DEBUG_DISPLAY',
		'SCRIPT_DEBUG',
		'SAVEQUERIES',
	];

	public static function log_path() {
		// Respect a custom WP_DEBUG_LOG path if set to a string.
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			return WP_DEBUG_LOG;
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	public static function log_size() {
		$p = self::log_path();
		return file_exists( $p ) ? filesize( $p ) : 0;
	}

	public static function log_exists() {
		return file_exists( self::log_path() );
	}

	/** Return the last N lines of the debug log. */
	public static function tail( $lines = 1000 ) {
		$path = self::log_path();
		if ( ! file_exists( $path ) ) return '';
		$f = new SplFileObject( $path, 'r' );
		$f->seek( PHP_INT_MAX );
		$total = $f->key();
		$start = max( 0, $total - $lines );
		$out   = '';
		$f->seek( $start );
		while ( ! $f->eof() ) {
			$out .= $f->fgets();
		}
		return $out;
	}

	public static function clear_log() {
		$path = self::log_path();
		if ( file_exists( $path ) && is_writable( $path ) ) {
			return file_put_contents( $path, '' ) !== false;
		}
		return false;
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );

		switch ( $action ) {

			case 'toggle':
				$name = sanitize_text_field( $_POST['constant'] ?? '' );
				$on   = ( $_POST['enabled'] ?? '' ) === '1';
				if ( ! in_array( $name, self::CONSTANTS, true ) ) {
					wp_send_json_error( 'Unknown constant.' );
				}
				$result = DevBench_Helpers::set_config_constant( $name, $on ? 'true' : 'false' );
				is_wp_error( $result )
					? wp_send_json_error( $result->get_error_message() )
					: wp_send_json_success();
				break;

			case 'read_log':
				wp_send_json_success( [
					'content' => self::tail( 1500 ),
					'size'    => DevBench_Helpers::filesize( self::log_size() ),
					'exists'  => self::log_exists(),
				] );
				break;

			case 'clear_log':
				self::clear_log()
					? wp_send_json_success()
					: wp_send_json_error( 'Could not clear log (not writable).' );
				break;
		}
		wp_die();
	}
}
