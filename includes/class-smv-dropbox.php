<?php
/**
 * Dropbox integration: OAuth 2 (PKCE + offline refresh token), uploads, shared links, deletion.
 *
 * Setup: create a "Scoped access" app at https://www.dropbox.com/developers/apps with the
 * permissions files.content.write, files.content.read, sharing.write, sharing.read and
 * account_info.read, then add the redirect URI shown on the settings page.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Dropbox {

	const AUTH_URL    = 'https://www.dropbox.com/oauth2/authorize';
	const TOKEN_URL   = 'https://api.dropboxapi.com/oauth2/token';
	const API_URL     = 'https://api.dropboxapi.com/2/';
	const CONTENT_URL = 'https://content.dropboxapi.com/2/';
	const CHUNK       = 8388608; // 8 MB.
	const SINGLE_MAX  = 104857600; // Use sessions above 100 MB (API limit is 150 MB).

	public static function init() {
		add_action( 'admin_post_smv_dropbox_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_smv_dropbox_callback', array( __CLASS__, 'handle_callback' ) );
		add_action( 'admin_post_smv_dropbox_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=smv_dropbox_callback' );
	}

	public static function is_configured() {
		return '' !== SMV_Settings::get( 'dropbox_app_key' ) && SMV_Settings::has_secret( 'app_secret' );
	}

	public static function is_connected() {
		return SMV_Settings::has_secret( 'refresh_token' );
	}

	public static function account_label() {
		return (string) get_option( 'smv_dropbox_account', '' );
	}

	public static function disconnect() {
		$token = SMV_Settings::get_secret( 'access_token' );
		if ( $token ) {
			// Best effort revoke.
			wp_remote_post(
				self::API_URL . 'auth/token/revoke',
				array(
					'timeout' => 10,
					'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				)
			);
		}
		SMV_Settings::set_secret( 'refresh_token', '' );
		SMV_Settings::set_secret( 'access_token', '' );
		delete_option( 'smv_dropbox_token_expires' );
		delete_option( 'smv_dropbox_account' );
	}

	// -----------------------------------------------------------------------
	// OAuth flow
	// -----------------------------------------------------------------------

	private static function settings_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'smv-settings',
					'tab'  => 'dropbox',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	private static function b64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function handle_connect() {
		if ( ! current_user_can( SMV_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'secure-media-vault' ), 403 );
		}
		check_admin_referer( 'smv_dropbox_connect' );

		if ( ! self::is_configured() ) {
			wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_missing_keys' ) ) );
			exit;
		}

		$state    = self::b64url( random_bytes( 24 ) );
		$verifier = self::b64url( random_bytes( 48 ) );
		set_transient(
			'smv_oauth_' . get_current_user_id(),
			array(
				'state'    => $state,
				'verifier' => $verifier,
			),
			15 * MINUTE_IN_SECONDS
		);

		$url = add_query_arg(
			array(
				'client_id'             => rawurlencode( SMV_Settings::get( 'dropbox_app_key' ) ),
				'response_type'         => 'code',
				'token_access_type'     => 'offline',
				'redirect_uri'          => rawurlencode( self::redirect_uri() ),
				'state'                 => rawurlencode( $state ),
				'code_challenge'        => rawurlencode( self::b64url( hash( 'sha256', $verifier, true ) ) ),
				'code_challenge_method' => 'S256',
			),
			self::AUTH_URL
		);

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- external, fixed host.
		exit;
	}

	public static function handle_callback() {
		if ( ! current_user_can( SMV_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'secure-media-vault' ), 403 );
		}

		$key     = 'smv_oauth_' . get_current_user_id();
		$pending = get_transient( $key );
		delete_transient( $key );

		// phpcs:disable WordPress.Security.NonceVerification -- OAuth state parameter is the CSRF token here.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable

		if ( $error ) {
			wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_denied' ) ) );
			exit;
		}
		if ( ! is_array( $pending ) || '' === $state || ! hash_equals( $pending['state'], $state ) || '' === $code ) {
			wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_state' ) ) );
			exit;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'client_id'     => SMV_Settings::get( 'dropbox_app_key' ),
					'client_secret' => SMV_Settings::get_secret( 'app_secret' ),
					'redirect_uri'  => self::redirect_uri(),
					'code_verifier' => $pending['verifier'],
				),
			)
		);

		$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['refresh_token'] ) || empty( $body['access_token'] ) ) {
			wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_token' ) ) );
			exit;
		}

		SMV_Settings::set_secret( 'refresh_token', $body['refresh_token'] );
		self::store_access_token( $body['access_token'], isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 14400 );

		$account = self::api( 'users/get_current_account' );
		if ( ! is_wp_error( $account ) ) {
			$label = isset( $account['email'] ) ? $account['email'] : '';
			if ( isset( $account['name']['display_name'] ) ) {
				$label = $account['name']['display_name'] . ( $label ? ' — ' . $label : '' );
			}
			update_option( 'smv_dropbox_account', sanitize_text_field( $label ), false );
		}

		wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_connected' ) ) );
		exit;
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( SMV_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'secure-media-vault' ), 403 );
		}
		check_admin_referer( 'smv_dropbox_disconnect' );
		self::disconnect();

		// Fall back to local storage so uploads keep working.
		$settings = SMV_Settings::all();
		foreach ( array( 'storage_mode', 'storage_mode_form', 'storage_mode_cf7' ) as $key ) {
			$settings[ $key ] = 'local';
		}
		update_option( SMV_Settings::OPTION, $settings );
		wp_safe_redirect( self::settings_url( array( 'smv_notice' => 'dropbox_disconnected' ) ) );
		exit;
	}

	private static function store_access_token( $token, $expires_in ) {
		SMV_Settings::set_secret( 'access_token', $token );
		update_option( 'smv_dropbox_token_expires', time() + max( 60, $expires_in - 300 ), false );
	}

	/**
	 * Returns a valid short-lived access token, refreshing if needed.
	 *
	 * @return string|WP_Error
	 */
	private static function access_token() {
		$token   = SMV_Settings::get_secret( 'access_token' );
		$expires = (int) get_option( 'smv_dropbox_token_expires', 0 );
		if ( $token && $expires > time() ) {
			return $token;
		}

		$refresh = SMV_Settings::get_secret( 'refresh_token' );
		if ( ! $refresh ) {
			return new WP_Error( 'smv_dropbox', __( 'Dropbox is not connected.', 'secure-media-vault' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh,
					'client_id'     => SMV_Settings::get( 'dropbox_app_key' ),
					'client_secret' => SMV_Settings::get_secret( 'app_secret' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : __( 'Could not refresh the Dropbox token. Please reconnect.', 'secure-media-vault' );
			return new WP_Error( 'smv_dropbox', $msg );
		}
		self::store_access_token( $body['access_token'], isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 14400 );
		return $body['access_token'];
	}

	// -----------------------------------------------------------------------
	// API helpers
	// -----------------------------------------------------------------------

	/**
	 * RPC-style endpoint call.
	 *
	 * @return array|WP_Error Decoded JSON body.
	 */
	public static function api( $endpoint, $args = null ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_post(
			self::API_URL . $endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => null === $args ? 'null' : wp_json_encode( $args ),
			)
		);
		return self::parse( $response );
	}

	/**
	 * Content endpoint call (upload). $args goes in the Dropbox-API-Arg header.
	 */
	private static function content( $endpoint, $args, $body ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_post(
			self::CONTENT_URL . $endpoint,
			array(
				'timeout' => 300,
				'headers' => array(
					'Authorization'   => 'Bearer ' . $token,
					'Content-Type'    => 'application/octet-stream',
					// json_encode escapes non-ASCII as \uXXXX, which Dropbox requires for HTTP headers.
					'Dropbox-API-Arg' => json_encode( $args, JSON_UNESCAPED_SLASHES ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				),
				'body'    => $body,
			)
		);
		return self::parse( $response );
	}

	private static function parse( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		if ( $code >= 200 && $code < 300 ) {
			return is_array( $body ) ? $body : array();
		}
		$summary = is_array( $body ) && isset( $body['error_summary'] ) ? $body['error_summary'] : wp_strip_all_tags( (string) $raw );
		$err     = new WP_Error( 'smv_dropbox', sprintf( 'Dropbox (%d): %s', $code, substr( $summary, 0, 300 ) ) );
		$err->add_data(
			array(
				'status' => $code,
				'body'   => $body,
			)
		);
		return $err;
	}

	/**
	 * Upload a local file to Dropbox.
	 *
	 * @param string $local_path Absolute path on disk.
	 * @param string $remote     Dropbox path, e.g. /Secure Media Vault/media/photo.jpg.
	 * @return array|WP_Error    File metadata (path_display, id…).
	 */
	public static function upload( $local_path, $remote ) {
		$size = filesize( $local_path );
		if ( false === $size ) {
			return new WP_Error( 'smv_dropbox', __( 'Local file is missing.', 'secure-media-vault' ) );
		}
		$commit = array(
			'path'       => $remote,
			'mode'       => 'add',
			'autorename' => true,
			'mute'       => true,
		);

		if ( $size <= self::SINGLE_MAX ) {
			$data = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $data ) {
				return new WP_Error( 'smv_dropbox', __( 'Could not read local file.', 'secure-media-vault' ) );
			}
			return self::content( 'files/upload', $commit, $data );
		}

		// Chunked upload session for large files.
		$fh = fopen( $local_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return new WP_Error( 'smv_dropbox', __( 'Could not read local file.', 'secure-media-vault' ) );
		}
		$chunk = fread( $fh, self::CHUNK ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$start = self::content( 'files/upload_session/start', array( 'close' => false ), $chunk );
		if ( is_wp_error( $start ) || empty( $start['session_id'] ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return is_wp_error( $start ) ? $start : new WP_Error( 'smv_dropbox', 'Upload session failed.' );
		}
		$session = $start['session_id'];
		$offset  = strlen( $chunk );

		while ( $offset < $size ) {
			$chunk   = fread( $fh, self::CHUNK ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$is_last = ( $offset + strlen( $chunk ) ) >= $size;
			$cursor  = array(
				'session_id' => $session,
				'offset'     => $offset,
			);
			if ( $is_last ) {
				$result = self::content(
					'files/upload_session/finish',
					array(
						'cursor' => $cursor,
						'commit' => $commit,
					),
					$chunk
				);
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return $result;
			}
			$result = self::content(
				'files/upload_session/append_v2',
				array(
					'cursor' => $cursor,
					'close'  => false,
				),
				$chunk
			);
			if ( is_wp_error( $result ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return $result;
			}
			$offset += strlen( $chunk );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'smv_dropbox', 'Upload ended unexpectedly.' );
	}

	/**
	 * Returns a direct-render public link (raw=1) for a Dropbox path.
	 *
	 * @return string|WP_Error
	 */
	public static function shared_link( $path ) {
		$result = self::api( 'sharing/create_shared_link_with_settings', array( 'path' => $path ) );
		$url    = '';
		if ( ! is_wp_error( $result ) && ! empty( $result['url'] ) ) {
			$url = $result['url'];
		} else {
			$list = self::api(
				'sharing/list_shared_links',
				array(
					'path'        => $path,
					'direct_only' => true,
				)
			);
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			if ( ! empty( $list['links'][0]['url'] ) ) {
				$url = $list['links'][0]['url'];
			}
		}
		if ( '' === $url ) {
			return is_wp_error( $result ) ? $result : new WP_Error( 'smv_dropbox', 'No shared link returned.' );
		}
		return add_query_arg( 'raw', '1', remove_query_arg( 'dl', $url ) );
	}

	/**
	 * Short-lived (4h) direct link for private files.
	 *
	 * @return string|WP_Error
	 */
	public static function temporary_link( $path ) {
		$result = self::api( 'files/get_temporary_link', array( 'path' => $path ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['link'] ) ? $result['link'] : new WP_Error( 'smv_dropbox', 'No link returned.' );
	}

	public static function delete( $path ) {
		return self::api( 'files/delete_v2', array( 'path' => $path ) );
	}

	/** Used by the settings "Test connection" button. */
	public static function test() {
		$account = self::api( 'users/get_current_account' );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$space = self::api( 'users/get_space_usage' );
		return array(
			'account' => isset( $account['email'] ) ? $account['email'] : '',
			'used'    => ! is_wp_error( $space ) && isset( $space['used'] ) ? size_format( $space['used'], 1 ) : '',
			'total'   => ! is_wp_error( $space ) && isset( $space['allocation']['allocated'] ) ? size_format( $space['allocation']['allocated'], 1 ) : '',
		);
	}
}
