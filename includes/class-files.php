<?php
defined( 'ABSPATH' ) || exit;

class DevBench_Files {

	const MAX_EDIT_SIZE = 5242880; // 5 MB

	const ALLOWED_UPLOAD = [
		'php','phtml','js','mjs','cjs','jsx','ts','tsx','css','scss','sass','less',
		'html','htm','twig','json','yml','yaml','toml','xml','txt','md','rst','log',
		'sql','csv','tsv','env','ini','cfg','conf','htaccess','htpasswd',
		'sh','bash','zsh','py','rb','pl','lua','go','rs','java','c','cpp','h',
		'jpg','jpeg','png','gif','webp','avif','svg','ico','bmp',
		'woff','woff2','ttf','otf','eot',
		'mp3','wav','ogg','mp4','webm',
		'zip','gz','tar','pdf','map','lock','wasm',
	];

	/** List a directory's contents. */
	public static function list_dir( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! is_dir( $abs ) ) {
			return new WP_Error( 'invalid_dir', 'Directory not found.' );
		}
		$items = [];
		foreach ( new DirectoryIterator( $abs ) as $f ) {
			if ( $f->isDot() ) continue;
			$items[] = self::describe( $f->getFileInfo() );
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['type'] !== $b['type'] ) return $a['type'] === 'dir' ? -1 : 1;
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return [
			'path'  => DevBench_Helpers::relative_path( $abs ) ?: '/',
			'items' => $items,
		];
	}

	private static function describe( SplFileInfo $f ) {
		$is_dir = $f->isDir();
		return [
			'name'     => $f->getFilename(),
			'type'     => $is_dir ? 'dir' : 'file',
			'size'     => $is_dir ? 0 : $f->getSize(),
			'modified' => $f->getMTime(),
			'writable' => $f->isWritable(),
			'ext'      => $is_dir ? '' : strtolower( $f->getExtension() ),
			'perms'    => DevBench_Helpers::perms( $f->getPathname() ),
			'path'     => DevBench_Helpers::relative_path( $f->getPathname() ),
		];
	}

	public static function read_file( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs || ! is_file( $abs ) ) return new WP_Error( 'not_found', 'File not found.' );
		if ( filesize( $abs ) > self::MAX_EDIT_SIZE ) return new WP_Error( 'too_large', 'File is too large to edit (>5MB).' );
		$content = file_get_contents( $abs );
		if ( $content === false ) return new WP_Error( 'read_failed', 'Could not read file.' );
		return [
			'content'  => $content,
			'writable' => is_writable( $abs ),
			'size'     => DevBench_Helpers::filesize( filesize( $abs ) ),
			'perms'    => DevBench_Helpers::perms( $abs ),
		];
	}

	public static function write_file( $relative, $content ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs ) return new WP_Error( 'invalid', 'Invalid path.' );
		if ( file_exists( $abs ) && ! is_writable( $abs ) ) return new WP_Error( 'not_writable', 'File is not writable.' );
		return file_put_contents( $abs, $content ) !== false ? true : new WP_Error( 'write_failed', 'Write failed.' );
	}

	public static function create_file( $dir_relative, $name ) {
		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! is_dir( $dir ) ) return new WP_Error( 'invalid', 'Invalid directory.' );
		$name = sanitize_file_name( $name );
		if ( ! $name ) return new WP_Error( 'invalid', 'Invalid file name.' );
		$target = $dir . '/' . $name;
		if ( file_exists( $target ) ) return new WP_Error( 'exists', 'File already exists.' );
		return file_put_contents( $target, '' ) !== false
			? [ 'path' => DevBench_Helpers::relative_path( $target ) ]
			: new WP_Error( 'failed', 'Could not create file.' );
	}

	public static function create_dir( $dir_relative, $name ) {
		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! is_dir( $dir ) ) return new WP_Error( 'invalid', 'Invalid directory.' );
		$name = sanitize_file_name( $name );
		if ( ! $name ) return new WP_Error( 'invalid', 'Invalid folder name.' );
		$target = $dir . '/' . $name;
		if ( file_exists( $target ) ) return new WP_Error( 'exists', 'Folder already exists.' );
		return mkdir( $target, 0755 ) ? true : new WP_Error( 'failed', 'Could not create folder.' );
	}

	public static function delete( $relative ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs ) return new WP_Error( 'invalid', 'Invalid path.' );
		return is_dir( $abs ) ? self::rrmdir( $abs ) : ( unlink( $abs ) ? true : new WP_Error( 'failed', 'Delete failed.' ) );
	}

	private static function rrmdir( $dir ) {
		foreach ( scandir( $dir ) as $item ) {
			if ( $item === '.' || $item === '..' ) continue;
			$path = $dir . '/' . $item;
			is_dir( $path ) ? self::rrmdir( $path ) : unlink( $path );
		}
		return rmdir( $dir ) ? true : new WP_Error( 'failed', 'Could not remove directory.' );
	}

	public static function rename( $relative, $new_name ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs ) return new WP_Error( 'invalid', 'Invalid path.' );
		$new_name = sanitize_file_name( $new_name );
		if ( ! $new_name ) return new WP_Error( 'invalid', 'Invalid name.' );
		$target = dirname( $abs ) . '/' . $new_name;
		if ( file_exists( $target ) ) return new WP_Error( 'exists', 'Target already exists.' );
		return rename( $abs, $target ) ? true : new WP_Error( 'failed', 'Rename failed.' );
	}

	public static function chmod_item( $relative, $mode ) {
		$abs = DevBench_Helpers::safe_path( $relative );
		if ( ! $abs ) return new WP_Error( 'invalid', 'Invalid path.' );
		if ( ! preg_match( '/^[0-7]{3,4}$/', $mode ) ) return new WP_Error( 'invalid', 'Invalid octal mode.' );
		return chmod( $abs, octdec( $mode ) ) ? true : new WP_Error( 'failed', 'chmod failed.' );
	}

	public static function copy_item( $src, $dest_dir, $new_name = '' ) {
		$s = DevBench_Helpers::safe_path( $src );
		$d = DevBench_Helpers::safe_path( $dest_dir );
		if ( ! $s || ! is_file( $s ) ) return new WP_Error( 'invalid', 'Source file not found.' );
		if ( ! $d || ! is_dir( $d ) ) return new WP_Error( 'invalid', 'Destination directory not found.' );
		$name   = $new_name ? sanitize_file_name( $new_name ) : basename( $s );
		$target = $d . '/' . $name;
		if ( file_exists( $target ) ) return new WP_Error( 'exists', 'Target already exists.' );
		return copy( $s, $target ) ? true : new WP_Error( 'failed', 'Copy failed.' );
	}

	public static function move_item( $src, $dest_dir, $new_name = '' ) {
		$s = DevBench_Helpers::safe_path( $src );
		$d = DevBench_Helpers::safe_path( $dest_dir );
		if ( ! $s ) return new WP_Error( 'invalid', 'Source not found.' );
		if ( ! $d || ! is_dir( $d ) ) return new WP_Error( 'invalid', 'Destination directory not found.' );
		$name   = $new_name ? sanitize_file_name( $new_name ) : basename( $s );
		$target = $d . '/' . $name;
		if ( file_exists( $target ) ) return new WP_Error( 'exists', 'Target already exists.' );
		return rename( $s, $target ) ? true : new WP_Error( 'failed', 'Move failed.' );
	}

	public static function bulk_delete( $paths ) {
		$deleted = 0; $errors = [];
		foreach ( (array) $paths as $p ) {
			$r = self::delete( sanitize_text_field( $p ) );
			is_wp_error( $r ) ? $errors[] = basename( $p ) : $deleted++;
		}
		return [ 'deleted' => $deleted, 'errors' => $errors ];
	}

	public static function search_dir( $dir_relative, $query ) {
		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! is_dir( $dir ) ) return [];
		$results = [];
		foreach ( new DirectoryIterator( $dir ) as $f ) {
			if ( $f->isDot() ) continue;
			if ( stripos( $f->getFilename(), $query ) !== false ) {
				$results[] = self::describe( $f->getFileInfo() );
			}
		}
		usort( $results, function ( $a, $b ) {
			if ( $a['type'] !== $b['type'] ) return $a['type'] === 'dir' ? -1 : 1;
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return $results;
	}

	public static function upload( $dir_relative, $file ) {
		$dir = DevBench_Helpers::safe_path( $dir_relative );
		if ( ! $dir || ! is_dir( $dir ) ) return new WP_Error( 'invalid', 'Invalid directory.' );
		$name = sanitize_file_name( $file['name'] );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( $ext && ! in_array( $ext, self::ALLOWED_UPLOAD, true ) ) {
			return new WP_Error( 'type', "File type .{$ext} is not allowed." );
		}
		$target = $dir . '/' . $name;
		return move_uploaded_file( $file['tmp_name'], $target ) ? true : new WP_Error( 'failed', 'Upload failed.' );
	}

	public static function handle_ajax() {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );
		$reply  = function ( $r, $extra = [] ) {
			if ( is_wp_error( $r ) ) wp_send_json_error( $r->get_error_message() );
			wp_send_json_success( is_array( $r ) ? $r : $extra );
		};

		switch ( $action ) {
			case 'list':
				$reply( self::list_dir( sanitize_text_field( $_POST['path'] ?? '/' ) ) );
				break;
			case 'read':
				$reply( self::read_file( sanitize_text_field( $_POST['path'] ?? '' ) ) );
				break;
			case 'write':
				$reply( self::write_file( sanitize_text_field( $_POST['path'] ?? '' ), wp_unslash( $_POST['content'] ?? '' ) ) );
				break;
			case 'create_file':
				$reply( self::create_file( sanitize_text_field( $_POST['path'] ?? '' ), $_POST['name'] ?? '' ) );
				break;
			case 'mkdir':
				$reply( self::create_dir( sanitize_text_field( $_POST['path'] ?? '' ), $_POST['name'] ?? '' ) );
				break;
			case 'delete':
				$reply( self::delete( sanitize_text_field( $_POST['path'] ?? '' ) ) );
				break;
			case 'rename':
				$reply( self::rename( sanitize_text_field( $_POST['path'] ?? '' ), $_POST['new_name'] ?? '' ) );
				break;
			case 'chmod':
				$reply( self::chmod_item( sanitize_text_field( $_POST['path'] ?? '' ), sanitize_text_field( $_POST['mode'] ?? '' ) ) );
				break;
			case 'copy':
				$reply( self::copy_item( sanitize_text_field( $_POST['src'] ?? '' ), sanitize_text_field( $_POST['dest_dir'] ?? '' ), $_POST['new_name'] ?? '' ) );
				break;
			case 'move':
				$reply( self::move_item( sanitize_text_field( $_POST['src'] ?? '' ), sanitize_text_field( $_POST['dest_dir'] ?? '' ), $_POST['new_name'] ?? '' ) );
				break;
			case 'bulk_delete':
				$reply( self::bulk_delete( (array) ( $_POST['paths'] ?? [] ) ) );
				break;
			case 'search':
				wp_send_json_success( self::search_dir( sanitize_text_field( $_POST['path'] ?? '/' ), sanitize_text_field( $_POST['query'] ?? '' ) ) );
				break;
			case 'upload':
				if ( empty( $_FILES['file'] ) ) wp_send_json_error( 'No file received.' );
				$reply( self::upload( sanitize_text_field( $_POST['path'] ?? '' ), $_FILES['file'] ) );
				break;
		}
		wp_die();
	}
}
