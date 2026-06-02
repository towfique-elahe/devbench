<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Helpers {

	/** Human-readable file size. */
	public static function filesize( $bytes ) {
		$bytes = (float) $bytes;
		if ( $bytes <= 0 ) return '0 B';
		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		$i = (int) floor( log( $bytes, 1024 ) );
		$i = min( $i, count( $units ) - 1 );
		return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ $i ];
	}

	/** Convert php.ini shorthand (128M, 1G) to bytes. */
	public static function to_bytes( $val ) {
		$val  = trim( (string) $val );
		if ( $val === '' ) return 0;
		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$num  = (float) $val;
		switch ( $last ) {
			case 'g': $num *= 1024;
			case 'm': $num *= 1024;
			case 'k': $num *= 1024;
		}
		return (int) $num;
	}

	/** Locate wp-config.php (standard location, then one level up). */
	public static function wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$parent = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent;
		}
		return '';
	}

	/**
	 * Add, update, or insert a constant in wp-config.php.
	 * $value should already be a PHP literal fragment for non-strings
	 * (true/false/123) or a raw string (will be quoted) when $is_string.
	 */
	public static function set_config_constant( $name, $literal ) {
		$path = self::wp_config_path();
		if ( ! $path || ! is_writable( $path ) ) {
			return new WP_Error( 'not_writable', 'wp-config.php is not writable.' );
		}
		$content = file_get_contents( $path );
		$pattern = "/define\(\s*(['\"])" . preg_quote( $name, '/' ) . "\\1\s*,\s*[^;]+\);/";
		$line    = "define( '{$name}', {$literal} );";

		if ( preg_match( $pattern, $content ) ) {
			$content = preg_replace( $pattern, $line, $content );
		} else {
			// Insert just before the "stop editing" marker, else after <?php.
			$marker = "/* That's all, stop editing!";
			if ( strpos( $content, $marker ) !== false ) {
				$content = str_replace( $marker, $line . "\n\n" . $marker, $content );
			} else {
				$content = preg_replace( '/^<\?php/', "<?php\n" . $line, $content, 1 );
			}
		}
		return file_put_contents( $path, $content ) !== false ? true : new WP_Error( 'write_failed', 'Failed to write wp-config.php.' );
	}

	/** Remove a constant definition from wp-config.php. */
	public static function delete_config_constant( $name ) {
		$path = self::wp_config_path();
		if ( ! $path || ! is_writable( $path ) ) {
			return new WP_Error( 'not_writable', 'wp-config.php is not writable.' );
		}
		$content = file_get_contents( $path );
		$pattern = "/[ \t]*define\(\s*(['\"])" . preg_quote( $name, '/' ) . "\\1\s*,\s*[^;]+\);[ \t]*\n?/";
		$content = preg_replace( $pattern, '', $content );
		return file_put_contents( $path, $content ) !== false ? true : new WP_Error( 'write_failed', 'Failed to write wp-config.php.' );
	}

	/** Read a constant's current runtime value as a display string. */
	public static function constant_display( $name ) {
		if ( ! defined( $name ) ) return [ 'value' => 'undefined', 'type' => 'undefined' ];
		$v = constant( $name );
		if ( is_bool( $v ) )   return [ 'value' => $v ? 'true' : 'false', 'type' => 'bool' ];
		if ( is_int( $v ) )    return [ 'value' => (string) $v, 'type' => 'int' ];
		if ( is_null( $v ) )   return [ 'value' => 'null', 'type' => 'null' ];
		return [ 'value' => (string) $v, 'type' => 'string' ];
	}

	/**
	 * Validate a path stays within ABSPATH (prevents directory traversal).
	 * Accepts a path relative to ABSPATH; returns absolute real path or false.
	 */
	public static function safe_path( $relative ) {
		$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
		$base     = realpath( ABSPATH );
		$target   = realpath( $base . '/' . $relative );

		// For not-yet-existing files (new file/dir), validate the parent.
		if ( $target === false ) {
			$parent = realpath( dirname( $base . '/' . $relative ) );
			if ( $parent === false ) return false;
			if ( strpos( $parent, $base ) !== 0 ) return false;
			return $base . '/' . $relative;
		}
		if ( strpos( $target, $base ) !== 0 ) return false;
		return $target;
	}

	/** Relative path from ABSPATH, normalised to forward slashes. */
	public static function relative_path( $absolute ) {
		$base = realpath( ABSPATH );
		$rel  = str_replace( $base, '', $absolute );
		return str_replace( '\\', '/', $rel );
	}

	/** Octal permission string for a path, e.g. "0644" -> "644". */
	public static function perms( $path ) {
		return substr( sprintf( '%o', fileperms( $path ) ), -3 );
	}

	/** Inline SVG icon set (stroke-based, 20x20, currentColor). */
	public static function icon( $name, $size = 18 ) {
		$paths = [
			'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
			'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
			'bug'       => '<path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6"/><path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/><path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/><path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/>',
			'chart'     => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
			'folder'    => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
			'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
			'terminal'  => '<polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/>',
			'code'      => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
			'settings'  => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
			'clock'     => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'mail'      => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
			'server'    => '<rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>',
			'info'      => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
			'note'      => '<path d="M15.5 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"/><path d="M15 3v6h6"/>',
			'shield'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
			'zap'       => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
			'check'     => '<path d="M20 6 9 17l-5-5"/>',
			'plug'      => '<path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/>',
			'refresh'   => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
			'external'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
			'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
			'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
			'list'      => '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>',
			'repeat'    => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
			'sliders'   => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
			'cpu'       => '<rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/>',
			'copy'      => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
			'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
		];
		$d = $paths[ $name ] ?? $paths['info'];
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="db-icon">%2$s</svg>',
			$size,
			$d
		);
	}
}
