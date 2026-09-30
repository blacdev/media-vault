<?php
/**
 * Authenticated streaming of private files (form submissions) to administrators.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Download {

	public static function init() {
		add_action( 'admin_post_smv_download', array( __CLASS__, 'handle' ) );
	}

	public static function admin_url( $id, $inline = false ) {
		$args = array(
			'action' => 'smv_download',
			'id'     => (int) $id,
		);
		if ( $inline ) {
			$args['inline'] = 1;
		}
		// Raw (unescaped) URL: callers escape for their context (HTML attribute vs JSON).
		$args['_wpnonce'] = wp_create_nonce( 'smv_download_' . (int) $id );
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	public static function handle() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified below.
		if ( ! current_user_can( SMV_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this file.', 'secure-media-vault' ), 403 );
		}
		check_admin_referer( 'smv_download_' . $id );

		$file = SMV_Files::get( $id );
		if ( ! $file ) {
			wp_die( esc_html__( 'File not found.', 'secure-media-vault' ), 404 );
		}

		$abs = $file->rel_path ? SMV_Storage::rel_to_abs( $file->rel_path ) : '';
		if ( ! $abs || ! is_file( $abs ) ) {
			if ( $file->dropbox_path && SMV_Dropbox::is_connected() ) {
				$link = SMV_Dropbox::temporary_link( $file->dropbox_path );
				if ( ! is_wp_error( $link ) ) {
					wp_redirect( $link ); // phpcs:ignore WordPress.Security.SafeRedirect -- Dropbox CDN host.
					exit;
				}
			}
			wp_die( esc_html__( 'The file is missing from storage.', 'secure-media-vault' ), 404 );
		}

		$inline_ok = in_array( $file->file_group, array( 'image', 'audio', 'video' ), true ) || 'pdf' === $file->ext;
		$inline    = ! empty( $_GET['inline'] ) && $inline_ok; // phpcs:ignore WordPress.Security.NonceVerification
		$type      = $inline ? $file->mime : 'application/octet-stream';
		$name      = sanitize_file_name( $file->original_name );
		$ascii     = preg_replace( '/[^A-Za-z0-9._-]/', '_', $name );

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Length: ' . filesize( $abs ) );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox" );
		header( 'X-Robots-Tag: noindex, nofollow' );
		readfile( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
