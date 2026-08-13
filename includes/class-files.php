<?php
/**
 * File manager.
 *
 * Reads are open to anyone holding the plugin capability; every write also
 * requires DevBench_Helpers::can_write(), which honours DISALLOW_FILE_EDIT and
 * DISALLOW_FILE_MODS. All paths are resolved through DevBench_Helpers::safe_path()
 * so nothing outside ABSPATH is reachable, and all I/O goes through the
 * WordPress Filesystem API.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

class DevBench_Files {

	const MAX_EDIT_SIZE = 5242880; // 5 MB.

	const ALLOWED_UPLOAD = array(
		'php', 'phtml', 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'css', 'scss', 'sass', 'less',
		'html', 'htm', 'twig', 'json', 'yml', 'yaml', 'toml', 'xml', 'txt', 'md', 'rst', 'log',
		'sql', 'csv', 'tsv', 'env', 'ini', 'cfg', 'conf', 'htaccess', 'htpasswd',
		'sh', 'bash', 'zsh', 'py', 'rb', 'pl', 'lua', 'go', 'rs', 'java', 'c', 'cpp', 'h',
		'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp',
		'woff', 'woff2', 'ttf', 'otf', 'eot',
		'mp3', 'wav', 'ogg', 'mp4', 'webm',
		'zip', 'gz', 'tar', 'pdf', 'map', 'lock', 'wasm',
	);

	/** Guard used by every write operation. @return true|WP_Error */
	private static function guard_write() {
		if ( ! DevBench_Helpers::can_write() ) {
			return DevBench_Helpers::write_blocked();
		}
		if ( ! DevBench_FS::get() ) {
			return DevBench_FS::unavailable();
		}
		return true;
	}

	/* ---------------- Listing ---------------- */

	/** @return array|WP_Error */
	public static function list_dir( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! DevBench_FS::is_dir( $abs ) ) {
			return new WP_Error( 'invalid_dir', __( 'Directory not found.', 'devbench' ) );
		}

		$list = DevBench_FS::dirlist( $abs );
		if ( false === $list ) {
			return new WP_Error( 'unreadable', __( 'Could not read the directory.', 'devbench' ) );
		}

		$items = array();
		foreach ( (array) $list as $entry ) {
			$name = isset( $entry['name'] ) ? $entry['name'] : '';
			if ( '' === $name || '.' === $name || '..' === $name ) {
				continue;
			}
			$items[] = self::describe( $abs . '/' . $name, $name, $entry );
		}

		usort( $items, array( __CLASS__, 'compare_items' ) );

		return array(
			'path'  => DevBench_Helpers::relative_path( $abs ) ? DevBench_Helpers::relative_path( $abs ) : '/',
			'items' => $items,
		);
	}

	/** Directories first, then case-insensitive name order. */
	private static function compare_items( $a, $b ) {
		if ( $a['type'] !== $b['type'] ) {
			return 'dir' === $a['type'] ? -1 : 1;
		}
		return strcasecmp( $a['name'], $b['name'] );
	}

	/**
	 * Normalise one directory entry for the UI.
	 *
	 * @param string $path  Absolute path.
	 * @param string $name  Entry name.
	 * @param array  $entry Raw dirlist() entry, when available.
	 */
	private static function describe( $path, $name, $entry = array() ) {
		$is_dir = isset( $entry['type'] ) ? 'd' === $entry['type'] : DevBench_FS::is_dir( $path );

		if ( $is_dir ) {
			$size = 0;
		} elseif ( isset( $entry['size'] ) ) {
			$size = (int) $entry['size'];
		} else {
			$size = DevBench_FS::size( $path );
		}

		$modified = isset( $entry['lastmodunix'] ) ? (int) $entry['lastmodunix'] : DevBench_FS::mtime( $path );
		$perms    = isset( $entry['permsn'] ) ? substr( $entry['permsn'], -3 ) : DevBench_FS::perms( $path );

		return array(
			'name'     => $name,
			'type'     => $is_dir ? 'dir' : 'file',
			'size'     => $size,
			'modified' => $modified,
			'writable' => DevBench_FS::is_writable( $path ),
			'ext'      => $is_dir ? '' : strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
			'perms'    => $perms,
			'path'     => DevBench_Helpers::relative_path( $path ),
		);
	}

	/* ---------------- Read / write ---------------- */

	/** @return array|WP_Error */
	public static function read_file( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! DevBench_FS::is_file( $abs ) ) {
			return new WP_Error( 'not_found', __( 'File not found.', 'devbench' ) );
		}

		$size = DevBench_FS::size( $abs );
		if ( $size > self::MAX_EDIT_SIZE ) {
			return new WP_Error( 'too_large', __( 'File is too large to edit (over 5 MB).', 'devbench' ) );
		}

		$content = DevBench_FS::read( $abs );
		if ( false === $content ) {
			return new WP_Error( 'read_failed', __( 'Could not read the file.', 'devbench' ) );
		}

		return array(
			'content'  => $content,
			'writable' => DevBench_Helpers::can_write() && DevBench_FS::is_writable( $abs ),
			'size'     => DevBench_Helpers::filesize( $size ),
			'perms'    => DevBench_FS::perms( $abs ),
		);
	}

	/** @return true|WP_Error */
	public static function write_file( $relative, $content ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs ) {
			return new WP_Error( 'invalid', __( 'Invalid path.', 'devbench' ) );
		}
		if ( DevBench_FS::exists( $abs ) && ! DevBench_FS::is_writable( $abs ) ) {
			return new WP_Error( 'not_writable', __( 'File is not writable.', 'devbench' ) );
		}

		return DevBench_FS::write( $abs, $content );
	}

	/** @return array|WP_Error */
	public static function create_file( $dir_relative, $name ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! DevBench_FS::is_dir( $dir ) ) {
			return new WP_Error( 'invalid', __( 'Invalid directory.', 'devbench' ) );
		}

		$name = sanitize_file_name( $name );
		if ( ! $name ) {
			return new WP_Error( 'invalid', __( 'Invalid file name.', 'devbench' ) );
		}

		$target = $dir . '/' . $name;
		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'File already exists.', 'devbench' ) );
		}

		$result = DevBench_FS::touch( $target );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'path' => DevBench_Helpers::relative_path( $target ) );
	}

	/** @return true|WP_Error */
	public static function create_dir( $dir_relative, $name ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! DevBench_FS::is_dir( $dir ) ) {
			return new WP_Error( 'invalid', __( 'Invalid directory.', 'devbench' ) );
		}

		$name = sanitize_file_name( $name );
		if ( ! $name ) {
			return new WP_Error( 'invalid', __( 'Invalid folder name.', 'devbench' ) );
		}

		$target = $dir . '/' . $name;
		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'Folder already exists.', 'devbench' ) );
		}

		return DevBench_FS::mkdir( $target );
	}

	/** @return true|WP_Error */
	public static function delete( $relative ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! DevBench_FS::exists( $abs ) ) {
			return new WP_Error( 'invalid', __( 'Invalid path.', 'devbench' ) );
		}

		// Refuse to delete the WordPress root itself.
		if ( rtrim( $abs, '/' ) === rtrim( DevBench_Helpers::safe_path( '/' ), '/' ) ) {
			return new WP_Error( 'refused', __( 'Refusing to delete the WordPress root directory.', 'devbench' ) );
		}

		return DevBench_FS::delete( $abs, DevBench_FS::is_dir( $abs ) );
	}

	/** @return true|WP_Error */
	public static function rename( $relative, $new_name ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! DevBench_FS::exists( $abs ) ) {
			return new WP_Error( 'invalid', __( 'Invalid path.', 'devbench' ) );
		}

		$new_name = sanitize_file_name( $new_name );
		if ( ! $new_name ) {
			return new WP_Error( 'invalid', __( 'Invalid name.', 'devbench' ) );
		}

		$target = dirname( $abs ) . '/' . $new_name;
		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'Target already exists.', 'devbench' ) );
		}

		return DevBench_FS::move( $abs, $target );
	}

	/** @return true|WP_Error */
	public static function chmod_item( $relative, $mode ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! DevBench_FS::exists( $abs ) ) {
			return new WP_Error( 'invalid', __( 'Invalid path.', 'devbench' ) );
		}
		if ( ! preg_match( '/^[0-7]{3,4}$/', $mode ) ) {
			return new WP_Error( 'invalid', __( 'Invalid octal mode.', 'devbench' ) );
		}

		return DevBench_FS::chmod( $abs, octdec( $mode ) );
	}

	/** @return true|WP_Error */
	public static function copy_item( $src, $dest_dir, $new_name = '' ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$source      = DevBench_Helpers::safe_path( $src );
		$destination = DevBench_Helpers::safe_path( $dest_dir );

		if ( ! $source || ! DevBench_FS::is_file( $source ) ) {
			return new WP_Error( 'invalid', __( 'Source file not found.', 'devbench' ) );
		}
		if ( ! $destination || ! DevBench_FS::is_dir( $destination ) ) {
			return new WP_Error( 'invalid', __( 'Destination directory not found.', 'devbench' ) );
		}

		$name   = $new_name ? sanitize_file_name( $new_name ) : basename( $source );
		$target = $destination . '/' . $name;

		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'Target already exists.', 'devbench' ) );
		}

		return DevBench_FS::copy( $source, $target );
	}

	/** @return true|WP_Error */
	public static function move_item( $src, $dest_dir, $new_name = '' ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$source      = DevBench_Helpers::safe_path( $src );
		$destination = DevBench_Helpers::safe_path( $dest_dir );

		if ( ! $source || ! DevBench_FS::exists( $source ) ) {
			return new WP_Error( 'invalid', __( 'Source not found.', 'devbench' ) );
		}
		if ( ! $destination || ! DevBench_FS::is_dir( $destination ) ) {
			return new WP_Error( 'invalid', __( 'Destination directory not found.', 'devbench' ) );
		}

		$name   = $new_name ? sanitize_file_name( $new_name ) : basename( $source );
		$target = $destination . '/' . $name;

		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'Target already exists.', 'devbench' ) );
		}

		return DevBench_FS::move( $source, $target );
	}

	/** @return array|WP_Error */
	public static function bulk_delete( $paths ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$deleted = 0;
		$errors  = array();

		foreach ( (array) $paths as $path ) {
			$result = self::delete( $path );
			if ( is_wp_error( $result ) ) {
				$errors[] = basename( $path );
			} else {
				++$deleted;
			}
		}

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}

	/** Filter the current directory by filename. */
	public static function search_dir( $dir_relative, $query ) {
		$listing = self::list_dir( $dir_relative );
		if ( is_wp_error( $listing ) ) {
			return array();
		}
		if ( '' === $query ) {
			return $listing['items'];
		}

		$results = array();
		foreach ( $listing['items'] as $item ) {
			if ( false !== stripos( $item['name'], $query ) ) {
				$results[] = $item;
			}
		}
		return $results;
	}

	/* ---------------- Download ---------------- */

	/**
	 * Validate a download request and describe the file to stream.
	 *
	 * Reading is gated by the plugin capability only — the same bar as opening
	 * the file in the editor — so this deliberately does not require can_write().
	 *
	 * @return array|WP_Error {path, name, size}
	 */
	public static function prepare_download( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );

		if ( ! $abs || ! DevBench_FS::is_file( $abs ) ) {
			return new WP_Error( 'not_found', __( 'File not found.', 'devbench' ) );
		}

		return array(
			'path' => $abs,
			'name' => sanitize_file_name( basename( $abs ) ),
			'size' => DevBench_FS::size( $abs ),
		);
	}

	/* ---------------- Archive ---------------- */

	/**
	 * Create a zip archive from the given paths.
	 *
	 * Entries are stored relative to the browsed directory, so unzipping
	 * reproduces exactly what was selected without the absolute path attached.
	 *
	 * @param string[] $paths        Relative paths to include.
	 * @param string   $dir_relative Directory the archive is written into.
	 * @param string   $name         Optional archive filename.
	 * @return array|WP_Error
	 */
	public static function zip( $paths, $dir_relative, $name = '' ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! DevBench_FS::is_dir( $dir ) ) {
			return new WP_Error( 'invalid', __( 'Invalid directory.', 'devbench' ) );
		}

		$sources = array();
		foreach ( (array) $paths as $path ) {
			$abs = DevBench_Helpers::safe_path( $path );
			if ( $abs && DevBench_FS::exists( $abs ) ) {
				$sources[] = $abs;
			}
		}
		if ( ! $sources ) {
			return new WP_Error( 'empty', __( 'Nothing to archive.', 'devbench' ) );
		}

		$name = $name ? sanitize_file_name( $name ) : '';
		if ( '' === $name ) {
			$name = 'devbench-archive-' . gmdate( 'Ymd-His' );
		}
		if ( '.zip' !== strtolower( substr( $name, -4 ) ) ) {
			$name .= '.zip';
		}

		$target = $dir . '/' . $name;
		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'An archive with that name already exists.', 'devbench' ) );
		}

		// ZipArchive when the host has ext-zip, otherwise the PclZip library
		// WordPress already bundles — no third-party code is shipped either way.
		$result = class_exists( 'ZipArchive' )
			? self::zip_with_ziparchive( $sources, $target )
			: self::zip_with_pclzip( $sources, $dir, $target );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'path' => DevBench_Helpers::relative_path( $target ),
			'name' => $name,
			'size' => DevBench_Helpers::filesize( DevBench_FS::size( $target ) ),
		);
	}

	/** @return true|WP_Error */
	private static function zip_with_ziparchive( $sources, $target ) {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zip_failed', __( 'Could not create the archive.', 'devbench' ) );
		}

		foreach ( $sources as $abs ) {
			$local = basename( $abs );

			if ( ! DevBench_FS::is_dir( $abs ) ) {
				$zip->addFile( $abs, $local );
				continue;
			}

			$zip->addEmptyDir( $local );
			foreach ( self::walk( $abs ) as $child => $is_dir ) {
				// Never swallow the archive being written.
				if ( $child === $target ) {
					continue;
				}
				$entry = $local . '/' . ltrim( str_replace( $abs, '', $child ), '/\\' );
				if ( $is_dir ) {
					$zip->addEmptyDir( $entry );
				} else {
					$zip->addFile( $child, $entry );
				}
			}
		}

		return $zip->close() ? true : new WP_Error( 'zip_failed', __( 'Could not finalise the archive.', 'devbench' ) );
	}

	/** @return true|WP_Error */
	private static function zip_with_pclzip( $sources, $base, $target ) {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$archive = new PclZip( $target );

		// Stripping the browsed directory gives the same relative entry names
		// the ZipArchive path produces via basename().
		$result = $archive->create( $sources, PCLZIP_OPT_REMOVE_PATH, $base );

		if ( 0 === $result ) {
			return new WP_Error( 'zip_failed', $archive->errorInfo( true ) );
		}
		return true;
	}

	/**
	 * Every descendant of a directory.
	 *
	 * @return array Absolute path => whether it is a directory.
	 */
	private static function walk( $dir ) {
		$out = array();

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( Exception $e ) {
			return $out;
		}

		foreach ( $iterator as $item ) {
			$out[ $item->getPathname() ] = $item->isDir();
		}
		return $out;
	}

	/* ---------------- Upload ---------------- */

	/**
	 * Store an uploaded file in the given directory.
	 *
	 * @param string $dir_relative Target directory, relative to ABSPATH.
	 * @param array  $file         One entry from $_FILES.
	 * @return true|WP_Error
	 */
	public static function upload( $dir_relative, $file ) {
		$guard = self::guard_write();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! DevBench_FS::is_dir( $dir ) ) {
			return new WP_Error( 'invalid', __( 'Invalid directory.', 'devbench' ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'No valid upload was received.', 'devbench' ) );
		}
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'The upload did not complete.', 'devbench' ) );
		}

		$name = sanitize_file_name( $file['name'] );
		if ( ! $name ) {
			return new WP_Error( 'invalid', __( 'Invalid file name.', 'devbench' ) );
		}

		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( $ext && ! in_array( $ext, self::ALLOWED_UPLOAD, true ) ) {
			/* translators: %s: file extension, without the leading dot. */
			return new WP_Error( 'type', sprintf( __( 'File type .%s is not allowed.', 'devbench' ), $ext ) );
		}

		// For types WordPress knows, reject a name whose contents do not match it.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $name );
		if ( ! empty( $checked['proper_filename'] ) && $checked['proper_filename'] !== $name ) {
			return new WP_Error( 'type_mismatch', __( 'The file contents do not match its extension.', 'devbench' ) );
		}

		$target = $dir . '/' . $name;
		if ( DevBench_FS::exists( $target ) ) {
			return new WP_Error( 'exists', __( 'A file with that name already exists.', 'devbench' ) );
		}

		return DevBench_FS::move( $file['tmp_name'], $target );
	}

	/* ---------------- AJAX ---------------- */

	public static function handle_ajax() {
		check_ajax_referer( 'devbench_nonce', 'nonce' );
		if ( ! DevBench_Helpers::can_manage() ) {
			wp_send_json_error( __( 'Permission denied.', 'devbench' ), 403 );
		}

		$action   = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';
		$path     = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$name     = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		$new_name = isset( $_POST['new_name'] ) ? sanitize_file_name( wp_unslash( $_POST['new_name'] ) ) : '';
		$src      = isset( $_POST['src'] ) ? sanitize_text_field( wp_unslash( $_POST['src'] ) ) : '';
		$dest_dir = isset( $_POST['dest_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['dest_dir'] ) ) : '';

		$reply = static function ( $result ) {
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}
			wp_send_json_success( is_array( $result ) ? $result : array() );
		};

		switch ( $action ) {
			case 'list':
				$reply( self::list_dir( '' === $path ? '/' : $path ) );
				break;

			case 'read':
				$reply( self::read_file( $path ) );
				break;

			case 'write':
				// File bodies are stored verbatim; sanitizing would corrupt source code.
				$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw file contents by design; write access is gated by capability, nonce and DISALLOW_FILE_EDIT.
				$reply( self::write_file( $path, $content ) );
				break;

			case 'create_file':
				$reply( self::create_file( $path, $name ) );
				break;

			case 'mkdir':
				$reply( self::create_dir( $path, $name ) );
				break;

			case 'delete':
				$reply( self::delete( $path ) );
				break;

			case 'rename':
				$reply( self::rename( $path, $new_name ) );
				break;

			case 'chmod':
				$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
				$reply( self::chmod_item( $path, $mode ) );
				break;

			case 'copy':
				$reply( self::copy_item( $src, $dest_dir, $new_name ) );
				break;

			case 'move':
				$reply( self::move_item( $src, $dest_dir, $new_name ) );
				break;

			case 'bulk_delete':
				$paths = isset( $_POST['paths'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['paths'] ) ) : array();
				$reply( self::bulk_delete( $paths ) );
				break;

			case 'zip':
				$paths = isset( $_POST['paths'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['paths'] ) ) : array();
				$reply( self::zip( $paths, '' === $path ? '/' : $path, $name ) );
				break;

			case 'search':
				$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
				wp_send_json_success( self::search_dir( '' === $path ? '/' : $path, $query ) );
				break;

			case 'upload':
				if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
					wp_send_json_error( __( 'No file received.', 'devbench' ) );
				}
				$upload = array(
					'name'     => isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '',
					'tmp_name' => sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ),
					'error'    => isset( $_FILES['file']['error'] ) ? absint( wp_unslash( $_FILES['file']['error'] ) ) : 0,
				);
				$reply( self::upload( $path, $upload ) );
				break;
		}

		wp_die();
	}
}
