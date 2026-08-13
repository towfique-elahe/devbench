<?php
/**
 * Thin wrapper around the WordPress Filesystem API.
 *
 * Every file operation in DevBench goes through here so the plugin never calls
 * PHP's filesystem functions directly, and so a single place decides whether
 * writes are permitted at all.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_FS {

	/**
	 * Initialise and return the global WP_Filesystem instance.
	 *
	 * @return WP_Filesystem_Base|null Null when no filesystem method is available.
	 */
	public static function get() {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Credentials are only obtainable for the "direct" transport in this
		// context; anything else means we cannot write and callers must say so.
		if ( ! WP_Filesystem() ) {
			return null;
		}

		return $wp_filesystem instanceof WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/** WP_Error returned whenever the filesystem is unreachable. */
	public static function unavailable() {
		return new WP_Error(
			'devbench_fs_unavailable',
			__( 'The WordPress filesystem is not directly writable, so this operation is unavailable.', 'devbench' )
		);
	}

	/* ---------------- Reads ---------------- */

	public static function exists( $path ) {
		$fs = self::get();
		return $fs ? $fs->exists( $path ) : false;
	}

	public static function is_dir( $path ) {
		$fs = self::get();
		return $fs ? $fs->is_dir( $path ) : false;
	}

	public static function is_file( $path ) {
		$fs = self::get();
		return $fs ? $fs->is_file( $path ) : false;
	}

	public static function is_writable( $path ) {
		$fs = self::get();
		return $fs ? $fs->is_writable( $path ) : false;
	}

	public static function size( $path ) {
		$fs = self::get();
		return $fs ? (int) $fs->size( $path ) : 0;
	}

	public static function mtime( $path ) {
		$fs = self::get();
		return $fs ? (int) $fs->mtime( $path ) : 0;
	}

	/** Octal permission string for a path, e.g. "644". */
	public static function perms( $path ) {
		$fs = self::get();
		if ( ! $fs ) {
			return '---';
		}
		$mode = $fs->getchmod( $path );
		return $mode ? substr( $mode, -3 ) : '---';
	}

	/**
	 * Read a file.
	 *
	 * @return string|false File contents, or false on failure.
	 */
	public static function read( $path ) {
		$fs = self::get();
		return $fs ? $fs->get_contents( $path ) : false;
	}

	/** List a directory. Returns an array keyed by filename, or false. */
	public static function dirlist( $path ) {
		$fs = self::get();
		return $fs ? $fs->dirlist( $path ) : false;
	}

	/* ---------------- Writes ---------------- */

	/**
	 * Write a file, preserving its existing permissions where possible.
	 *
	 * @return true|WP_Error
	 */
	public static function write( $path, $contents ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}

		// Keep the current mode so saving a file never widens its permissions.
		$mode = false;
		if ( $fs->exists( $path ) ) {
			$current = $fs->getchmod( $path );
			if ( $current ) {
				$mode = octdec( $current );
			}
		}

		if ( ! $fs->put_contents( $path, $contents, $mode ) ) {
			return new WP_Error( 'devbench_write_failed', __( 'Could not write the file.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function mkdir( $path ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->mkdir( $path, FS_CHMOD_DIR ) ) {
			return new WP_Error( 'devbench_mkdir_failed', __( 'Could not create the folder.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function delete( $path, $recursive = false ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->delete( $path, $recursive ) ) {
			return new WP_Error( 'devbench_delete_failed', __( 'Delete failed.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function move( $source, $destination ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->move( $source, $destination, false ) ) {
			return new WP_Error( 'devbench_move_failed', __( 'Move failed.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function copy( $source, $destination ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->copy( $source, $destination, false ) ) {
			return new WP_Error( 'devbench_copy_failed', __( 'Copy failed.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function chmod( $path, $octal_mode ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->chmod( $path, $octal_mode ) ) {
			return new WP_Error( 'devbench_chmod_failed', __( 'Changing permissions failed.', 'devbench' ) );
		}
		return true;
	}

	/** @return true|WP_Error */
	public static function touch( $path ) {
		$fs = self::get();
		if ( ! $fs ) {
			return self::unavailable();
		}
		if ( ! $fs->touch( $path ) ) {
			return new WP_Error( 'devbench_touch_failed', __( 'Could not create the file.', 'devbench' ) );
		}
		return true;
	}
}
