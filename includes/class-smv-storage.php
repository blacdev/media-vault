<?php
/**
 * File validation, storage on disk, folder protection and Dropbox sync.
 *
 * Layout inside wp-content/uploads/{folder}/:
 *   media/YYYY/MM/   public files used by collections (scripts blocked from executing)
 *   private/YYYY/MM/ form submissions (all direct web access denied; random names, no real extension)
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Storage {

	// -----------------------------------------------------------------------
	// Paths
	// -----------------------------------------------------------------------

	private static function uploads() {
		return wp_upload_dir( null, false );
	}

	public static function uploads_basedir() {
		$u = self::uploads();
		return wp_normalize_path( untrailingslashit( $u['basedir'] ) );
	}

	public static function base_dir( $folder = null ) {
		$folder = null === $folder ? SMV_Settings::get( 'folder' ) : $folder;
		return self::uploads_basedir() . '/' . SMV_Settings::sanitize_folder( $folder );
	}

	/** Absolute path for a stored relative path, or '' if it escapes the uploads dir. */
	public static function rel_to_abs( $rel ) {
		$rel = ltrim( wp_normalize_path( (string) $rel ), '/' );
		if ( '' === $rel || false !== strpos( $rel, '..' ) ) {
			return '';
		}
		$abs  = self::uploads_basedir() . '/' . $rel;
		$real = realpath( $abs );
		if ( false === $real ) {
			return $abs; // Not on disk (yet) — caller checks existence.
		}
		$real = wp_normalize_path( $real );
		$base = wp_normalize_path( (string) realpath( self::uploads_basedir() ) );
		return 0 === strpos( $real, trailingslashit( $base ) ) ? $real : '';
	}

	public static function rel_to_url( $rel ) {
		$u    = self::uploads();
		$segs = array_map( 'rawurlencode', explode( '/', ltrim( (string) $rel, '/' ) ) );
		return set_url_scheme( untrailingslashit( $u['baseurl'] ) . '/' . implode( '/', $segs ) );
	}

	private static function abs_to_rel( $abs ) {
		return ltrim( substr( wp_normalize_path( $abs ), strlen( self::uploads_basedir() ) ), '/' );
	}

	// -----------------------------------------------------------------------
	// Folder protection
	// -----------------------------------------------------------------------

	/**
	 * Create the folders and protection files. Never emits PHP warnings (an unwritable
	 * uploads folder must not break activation or the site); returns false on failure
	 * so the admin can be told instead.
	 */
	public static function prepare_folders( $folder = null ) {
		$base = self::base_dir( $folder );
		$ok   = true;
		foreach ( array( $base, $base . '/media', $base . '/private' ) as $dir ) {
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$ok = false;
				continue;
			}
			self::write_if_missing( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! $ok || ! wp_is_writable( $base ) ) {
			update_option( 'smv_folder_error', $base, false );
			return false;
		}
		delete_option( 'smv_folder_error' );

		$deny_all = "# Secure Media Vault — deny all direct access\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";

		$no_scripts = "# Secure Media Vault — never execute or render active content from this folder\n"
			. "<FilesMatch \"(?i)\\.(php[0-9]?|pht|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|bash|shtml|html?|xhtml|svgz?|js|mjs|htaccess)$\">\n"
			. "\t<IfModule mod_authz_core.c>\n\t\tRequire all denied\n\t</IfModule>\n"
			. "\t<IfModule !mod_authz_core.c>\n\t\tOrder allow,deny\n\t\tDeny from all\n\t</IfModule>\n"
			. "</FilesMatch>\n"
			. "<IfModule mod_headers.c>\n\tHeader set X-Content-Type-Options \"nosniff\"\n</IfModule>\n";

		self::write_if_missing( $base . '/.htaccess', $no_scripts );
		self::write_if_missing( $base . '/private/.htaccess', $deny_all );
		self::write_if_missing(
			$base . '/private/web.config',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"
		);
		return true;
	}

	private static function write_if_missing( $path, $contents ) {
		if ( ! file_exists( $path ) && is_dir( dirname( $path ) ) && wp_is_writable( dirname( $path ) ) ) {
			@file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Checks whether the private folder is really blocked from the web (e.g. nginx ignores .htaccess).
	 *
	 * @return array{status: string, message: string}
	 */
	public static function health_check() {
		self::prepare_folders();
		$base  = self::base_dir();
		$probe = $base . '/private/smv-probe.txt';
		if ( false === @file_put_contents( $probe, 'smv-probe' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
			return array(
				'status'   => 'unknown',
				'writable' => false,
				'message'  => __( 'The upload folder is not writable, so the check could not run. Ask your host to fix the folder permissions.', 'secure-media-vault' ),
			);
		}
		$res = wp_remote_get(
			self::rel_to_url( self::abs_to_rel( $probe ) ),
			array(
				'timeout'   => 8,
				'sslverify' => false,
			)
		);
		wp_delete_file( $probe );

		$writable = wp_is_writable( $base );

		if ( is_wp_error( $res ) ) {
			return array(
				'status'   => 'unknown',
				'writable' => $writable,
				'message'  => __( 'Could not reach your own site to verify folder protection (loopback request failed). Private files still use random, unguessable names.', 'secure-media-vault' ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $code && 'smv-probe' === trim( wp_remote_retrieve_body( $res ) ) ) {
			return array(
				'status'   => 'exposed',
				'writable' => $writable,
				'message'  => __( 'Your web server ignores .htaccess rules (common on nginx), so the private folder is reachable by URL. Private files still use random, unguessable names, but add the nginx rule below for full protection.', 'secure-media-vault' ),
			);
		}
		return array(
			'status'   => 'protected',
			'writable' => $writable,
			/* translators: %d: HTTP status code */
			'message'  => sprintf( __( 'Private folder is blocked from direct web access (HTTP %d).', 'secure-media-vault' ), $code ),
		);
	}

	// -----------------------------------------------------------------------
	// Storage limit
	// -----------------------------------------------------------------------

	/**
	 * Where files from a given place are stored (local | both | dropbox).
	 * admin = Library uploads, form = [smv_upload_form], cf7 = Contact Form 7.
	 */
	public static function mode_for_source( $source ) {
		$keys = array(
			'admin' => 'storage_mode',
			'form'  => 'storage_mode_form',
			'cf7'   => 'storage_mode_cf7',
		);
		$mode = SMV_Settings::get( isset( $keys[ $source ] ) ? $keys[ $source ] : 'storage_mode' );
		return in_array( $mode, array( 'local', 'both', 'dropbox' ), true ) ? $mode : 'local';
	}

	/** Limit in bytes, or 0 for no limit. */
	public static function quota_bytes() {
		$gb = (float) SMV_Settings::get( 'quota_gb' );
		return $gb > 0 ? (int) round( $gb * GB_IN_BYTES ) : 0;
	}

	/** @return array{used:int, limit:int, left:int, percent:float, full:bool} */
	public static function quota_status() {
		$used  = SMV_Files::total_bytes();
		$limit = self::quota_bytes();
		return array(
			'used'    => $used,
			'limit'   => $limit,
			'left'    => $limit ? max( 0, $limit - $used ) : PHP_INT_MAX,
			'percent' => $limit ? min( 100, round( $used / $limit * 100, 1 ) ) : 0,
			'full'    => $limit > 0 && $used >= $limit,
		);
	}

	/** @return true|WP_Error */
	private static function check_quota( $size, $source ) {
		$q = self::quota_status();
		if ( ! $q['limit'] || $size <= $q['left'] ) {
			return true;
		}
		if ( 'admin' === $source ) {
			/* translators: 1: space left, 2: storage limit */
			return new WP_Error( 'smv_quota', sprintf( __( 'Storage limit reached: only %1$s left of your %2$s limit. Delete files or raise the limit in Settings → Storage.', 'secure-media-vault' ), size_format( $q['left'], 1 ), size_format( $q['limit'] ) ) );
		}
		// Visitors don't need to know about the site's storage.
		return new WP_Error( 'smv_quota', __( 'We can’t accept more files right now. Please try again later.', 'secure-media-vault' ) );
	}

	// -----------------------------------------------------------------------
	// Uploads
	// -----------------------------------------------------------------------

	/**
	 * Turn a $_FILES entry that may hold multiple files into a flat list.
	 */
	public static function normalize_files( $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
			return array();
		}
		if ( ! is_array( $entry['name'] ) ) {
			return array( $entry );
		}
		$out = array();
		foreach ( array_keys( $entry['name'] ) as $i ) {
			if ( '' === $entry['name'][ $i ] ) {
				continue;
			}
			$out[] = array(
				'name'     => $entry['name'][ $i ],
				'type'     => $entry['type'][ $i ],
				'tmp_name' => $entry['tmp_name'][ $i ],
				'error'    => $entry['error'][ $i ],
				'size'     => $entry['size'][ $i ],
			);
		}
		return $out;
	}

	private static function upload_error_message( $code ) {
		switch ( (int) $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The file is larger than the server allows.', 'secure-media-vault' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The file was only partially uploaded. Please try again.', 'secure-media-vault' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was received.', 'secure-media-vault' );
			default:
				return __( 'The server could not store the uploaded file.', 'secure-media-vault' );
		}
	}

	/**
	 * Validate and store one uploaded file.
	 *
	 * @param array $file    Single $_FILES-style array.
	 * @param array $context visibility, allowed (ext[]), max_bytes, source, submission_id.
	 * @return object|WP_Error Stored file row.
	 */
	public static function handle_upload( array $file, array $context ) {
		$original = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'smv_upload', self::upload_error_message( isset( $file['error'] ) ? $file['error'] : -1 ) );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'smv_upload', __( 'Invalid upload.', 'secure-media-vault' ) );
		}

		return self::store_file( $file['tmp_name'], $original, $context, true );
	}

	/**
	 * Import a file that another plugin (e.g. Contact Form 7) already received on this server.
	 * Runs exactly the same validation as uploads; the source file is copied, never moved.
	 *
	 * @return object|WP_Error Stored file row.
	 */
	public static function import_file( $path, $original_name, array $context ) {
		if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'smv_upload', __( 'The file could not be read.', 'secure-media-vault' ) );
		}
		return self::store_file( $path, (string) $original_name, $context, false );
	}

	/**
	 * Shared validation + storage.
	 *
	 * @param string $tmp       Path of the received file.
	 * @param string $original  Original file name as sent by the visitor.
	 * @param array  $context   visibility, allowed (ext[]), max_bytes, source, submission_id.
	 * @param bool   $is_upload True for a PHP upload (moved), false for an import (copied).
	 */
	private static function store_file( $tmp, $original, array $context, $is_upload ) {
		$context = wp_parse_args(
			$context,
			array(
				'visibility'    => 'public',
				'allowed'       => SMV_Settings::get( 'allowed_types' ),
				'max_bytes'     => SMV_Settings::get( 'max_size_mb' ) * MB_IN_BYTES,
				'source'        => 'admin',
				'submission_id' => 0,
				'storage'       => '', // Empty = the setting for this source.
			)
		);
		$target  = in_array( $context['storage'], array( 'local', 'both', 'dropbox' ), true ) ? $context['storage'] : self::mode_for_source( $context['source'] );

		$size      = (int) filesize( $tmp );
		$max_bytes = min( (int) $context['max_bytes'], (int) wp_max_upload_size() );
		if ( $size <= 0 ) {
			return new WP_Error( 'smv_upload', __( 'The file is empty.', 'secure-media-vault' ) );
		}
		if ( $size > $max_bytes ) {
			/* translators: %s: maximum size */
			return new WP_Error( 'smv_upload', sprintf( __( 'The file is too large. Maximum size is %s.', 'secure-media-vault' ), size_format( $max_bytes ) ) );
		}

		$quota = self::check_quota( $size, $context['source'] );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}

		// Extension must be on the allow-list.
		$clean_name = sanitize_file_name( wp_basename( $original ) );
		$ext        = strtolower( (string) pathinfo( $clean_name, PATHINFO_EXTENSION ) );
		$allowed    = array_intersect_key( SMV_Settings::all_types(), array_flip( (array) $context['allowed'] ) );
		if ( '' === $ext || ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error( 'smv_upload', __( 'This file type is not allowed.', 'secure-media-vault' ) );
		}

		// Content sniffing: real MIME must match the extension (blocks renamed executables / polyglots).
		$check = wp_check_filetype_and_ext( $tmp, $clean_name, $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'smv_upload', __( 'The file contents do not match its type.', 'secure-media-vault' ) );
		}
		$ext   = strtolower( $check['ext'] );
		$mime  = $check['type'];
		$group = SMV_Settings::group_for_ext( $ext );

		$width  = 0;
		$height = 0;
		if ( 'image' === $group ) {
			$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $tmp ) : @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! $info || empty( $info[0] ) ) {
				return new WP_Error( 'smv_upload', __( 'This image appears to be corrupt.', 'secure-media-vault' ) );
			}
			$width  = (int) $info[0];
			$height = (int) $info[1];
		}

		// Destination.
		if ( ! self::prepare_folders() ) {
			return new WP_Error( 'smv_upload', __( 'The upload folder is not writable. Please check folder permissions (Media Vault → Settings → Security & status).', 'secure-media-vault' ) );
		}
		$sub = 'private' === $context['visibility'] ? 'private' : 'media';
		$dir = self::base_dir() . '/' . $sub . '/' . gmdate( 'Y/m' );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'smv_upload', __( 'Could not create the upload folder. Please check folder permissions.', 'secure-media-vault' ) );
		}
		$stem = sanitize_title( pathinfo( $clean_name, PATHINFO_FILENAME ) );
		$stem = '' === $stem ? 'file' : substr( $stem, 0, 80 );

		if ( 'private' === $sub ) {
			// Unguessable, non-executable name.
			$file_name = bin2hex( random_bytes( 16 ) ) . '.bin';
		} else {
			$file_name = $stem . '-' . strtolower( wp_generate_password( 6, false ) ) . '.' . $ext;
		}
		$dest = $dir . '/' . $file_name;

		$stored = $is_upload ? @move_uploaded_file( $tmp, $dest ) : @copy( $tmp, $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $stored ) {
			return new WP_Error( 'smv_upload', __( 'Could not save the file. Check that the uploads folder is writable.', 'secure-media-vault' ) );
		}
		$stat  = stat( dirname( $dest ) );
		$perms = $stat['mode'] & 0000666;
		chmod( $dest, $perms ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$thumb_rel = 'image' === $group && 'media' === $sub ? self::make_thumb( $dest ) : '';

		$title = ucwords( str_replace( array( '-', '_' ), ' ', pathinfo( $clean_name, PATHINFO_FILENAME ) ) );

		$id = SMV_Files::insert(
			array(
				'title'          => sanitize_text_field( $title ),
				'original_name'  => $clean_name,
				'file_name'      => $file_name,
				'rel_path'       => self::abs_to_rel( $dest ),
				'thumb_path'     => $thumb_rel,
				'ext'            => $ext,
				'mime'           => $mime,
				'file_group'     => $group,
				'size'           => $size,
				'width'          => $width,
				'height'         => $height,
				'visibility'     => 'private' === $sub ? 'private' : 'public',
				'source'         => sanitize_key( $context['source'] ),
				'storage_target' => $target,
				'submission_id'  => (int) $context['submission_id'],
			)
		);

		if ( ! $id ) {
			wp_delete_file( $dest );
			return new WP_Error( 'smv_upload', __( 'Could not record the file in the database.', 'secure-media-vault' ) );
		}

		if ( 'local' !== $target && SMV_Dropbox::is_connected() ) {
			self::sync_to_dropbox( $id );
		}

		return SMV_Files::get( $id );
	}

	/** Creates an 800px preview next to the image. Returns its relative path or ''. */
	private static function make_thumb( $abs ) {
		if ( 'gif' === strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) ) ) {
			return ''; // Keep animations: galleries show the original GIF.
		}
		$editor = wp_get_image_editor( $abs );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$size = $editor->get_size();
		if ( $size['width'] <= 800 && $size['height'] <= 800 ) {
			return '';
		}
		$editor->resize( 800, 800, false );
		$saved = $editor->save( preg_replace( '/(\.[a-z0-9]+)$/i', '-thumb$1', $abs ) );
		return is_wp_error( $saved ) ? '' : self::abs_to_rel( $saved['path'] );
	}

	/**
	 * Push a stored file to Dropbox according to the storage mode.
	 *
	 * @return true|WP_Error
	 */
	public static function sync_to_dropbox( $id ) {
		$file = SMV_Files::get( $id );
		if ( ! $file ) {
			return new WP_Error( 'smv_sync', 'File not found.' );
		}
		// The storage chosen when the file arrived (older files: the current setting for their source).
		$mode = in_array( $file->storage_target, array( 'both', 'dropbox' ), true ) ? $file->storage_target : self::mode_for_source( $file->source );
		if ( 'local' === $mode ) {
			return new WP_Error( 'smv_sync', __( 'This file is set to be stored locally only.', 'secure-media-vault' ) );
		}
		$abs = self::rel_to_abs( $file->rel_path );
		if ( ! $abs || ! file_exists( $abs ) ) {
			return new WP_Error( 'smv_sync', __( 'Local copy is missing.', 'secure-media-vault' ) );
		}

		$sub    = 'private' === $file->visibility ? 'private' : 'media';
		$name   = 'private' === $file->visibility ? $file->id . '-' . $file->original_name : $file->file_name;
		$remote = rtrim( SMV_Settings::get( 'dropbox_folder' ), '/' ) . '/' . $sub . '/' . mysql2date( 'Y/m', $file->created_at ) . '/' . $name;

		$meta = SMV_Dropbox::upload( $abs, $remote );
		if ( is_wp_error( $meta ) ) {
			SMV_Files::update(
				$id,
				array(
					'sync_status' => 'failed',
					'sync_error'  => $meta->get_error_message(),
				)
			);
			return $meta;
		}

		$update = array(
			'dropbox_path' => isset( $meta['path_display'] ) ? $meta['path_display'] : $remote,
			'sync_status'  => 'ok',
			'sync_error'   => '',
			'storage'      => 'both',
		);

		if ( 'public' === $file->visibility ) {
			$link = SMV_Dropbox::shared_link( $update['dropbox_path'] );
			if ( is_wp_error( $link ) ) {
				// Keep the local copy serving; flag the problem.
				$update['sync_status'] = 'failed';
				$update['sync_error']  = $link->get_error_message();
				SMV_Files::update( $id, $update );
				return $link;
			}
			$update['dropbox_url'] = esc_url_raw( $link );
		}

		if ( 'dropbox' === $mode ) {
			wp_delete_file( $abs );
			$update['rel_path'] = '';
			$update['storage']  = 'dropbox';
		}

		SMV_Files::update( $id, $update );
		return true;
	}

	/** Remove local copies and the Dropbox copy of a file. */
	public static function delete_physical( $file ) {
		foreach ( array( $file->rel_path, $file->thumb_path ) as $rel ) {
			if ( $rel ) {
				$abs = self::rel_to_abs( $rel );
				if ( $abs && is_file( $abs ) ) {
					wp_delete_file( $abs );
				}
			}
		}
		if ( $file->dropbox_path && SMV_Dropbox::is_connected() ) {
			SMV_Dropbox::delete( $file->dropbox_path );
		}
	}

	/**
	 * Move a private submission into the public media library.
	 *
	 * @return true|WP_Error
	 */
	public static function publish( $id ) {
		$file = SMV_Files::get( $id );
		if ( ! $file || 'private' !== $file->visibility ) {
			return new WP_Error( 'smv_publish', __( 'File not found.', 'secure-media-vault' ) );
		}

		$src      = $file->rel_path ? self::rel_to_abs( $file->rel_path ) : '';
		$tmp_used = false;
		if ( ! $src || ! file_exists( $src ) ) {
			if ( ! $file->dropbox_path ) {
				return new WP_Error( 'smv_publish', __( 'The file is missing.', 'secure-media-vault' ) );
			}
			$link = SMV_Dropbox::temporary_link( $file->dropbox_path );
			if ( is_wp_error( $link ) ) {
				return $link;
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$src = download_url( $link, 300 );
			if ( is_wp_error( $src ) ) {
				return $src;
			}
			$tmp_used = true;
		}

		$dir = self::base_dir() . '/media/' . gmdate( 'Y/m' );
		wp_mkdir_p( $dir );
		$stem = sanitize_title( pathinfo( $file->original_name, PATHINFO_FILENAME ) );
		$stem = '' === $stem ? 'file' : substr( $stem, 0, 80 );
		$name = $stem . '-' . strtolower( wp_generate_password( 6, false ) ) . '.' . $file->ext;
		$dest = $dir . '/' . $name;

		$ok = $tmp_used ? rename( $src, $dest ) : copy( $src, $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $ok ) {
			return new WP_Error( 'smv_publish', __( 'Could not copy the file.', 'secure-media-vault' ) );
		}

		// Clean up the private copies.
		$old_dropbox = $file->dropbox_path;
		if ( ! $tmp_used ) {
			wp_delete_file( $src );
		}
		if ( $old_dropbox && SMV_Dropbox::is_connected() ) {
			SMV_Dropbox::delete( $old_dropbox );
		}

		SMV_Files::update(
			$id,
			array(
				'visibility'     => 'public',
				'file_name'      => $name,
				'rel_path'       => self::abs_to_rel( $dest ),
				'thumb_path'     => 'image' === $file->file_group ? self::make_thumb( $dest ) : '',
				'storage'        => 'local',
				'storage_target' => self::mode_for_source( 'admin' ), // Now a Library file.
				'dropbox_path'   => '',
				'dropbox_url'    => '',
				'sync_status'    => '',
				'sync_error'     => '',
			)
		);

		if ( 'local' !== self::mode_for_source( 'admin' ) && SMV_Dropbox::is_connected() ) {
			self::sync_to_dropbox( $id );
		}
		return true;
	}
}
