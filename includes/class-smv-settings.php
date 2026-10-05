<?php
/**
 * Settings registry, defaults, sanitisation and file type catalogue.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Settings {

	const OPTION  = 'smv_settings';
	const SECRETS = 'smv_secrets';

	/** @var array|null */
	private static $cache = null;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	public static function register() {
		register_setting(
			'smv_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Every file format the plugin knows about, grouped. JPG, JPEG, PNG, MP3 and WAV are always
	 * available; all other formats are off until enabled in Settings → Storage → File formats.
	 * Scriptable formats (PHP, HTML, SVG, JS, executables…) are never supported.
	 */
	public static function full_catalogue() {
		return array(
			'image'    => array(
				'label' => __( 'Images', 'secure-media-vault' ),
				'types' => array(
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'gif'  => 'image/gif',
					'webp' => 'image/webp',
					'avif' => 'image/avif',
				),
			),
			'audio'    => array(
				'label' => __( 'Audio', 'secure-media-vault' ),
				'types' => array(
					'mp3'  => 'audio/mpeg',
					'wav'  => 'audio/wav',
					'm4a'  => 'audio/mp4',
					'ogg'  => 'audio/ogg',
					'flac' => 'audio/flac',
					'aac'  => 'audio/aac',
				),
			),
			'video'    => array(
				'label' => __( 'Video', 'secure-media-vault' ),
				'types' => array(
					'mp4'  => 'video/mp4',
					'm4v'  => 'video/mp4',
					'webm' => 'video/webm',
					'mov'  => 'video/quicktime',
				),
			),
			'document' => array(
				'label' => __( 'Documents', 'secure-media-vault' ),
				'types' => array(
					'pdf'  => 'application/pdf',
					'doc'  => 'application/msword',
					'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'xls'  => 'application/vnd.ms-excel',
					'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'ppt'  => 'application/vnd.ms-powerpoint',
					'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
					'txt'  => 'text/plain',
					'csv'  => 'text/csv',
					'zip'  => 'application/zip',
				),
			),
		);
	}

	/** Formats that are always available. */
	public static function core_types() {
		return array( 'jpg', 'jpeg', 'png', 'mp3', 'wav' );
	}

	/** Optional formats (off until enabled), as ext => group key. */
	public static function extra_types() {
		$out = array();
		foreach ( self::full_catalogue() as $key => $group ) {
			foreach ( array_keys( $group['types'] ) as $ext ) {
				if ( ! in_array( $ext, self::core_types(), true ) ) {
					$out[ $ext ] = $key;
				}
			}
		}
		return $out;
	}

	/**
	 * The formats currently in use: the core formats plus any enabled in Settings.
	 * Groups without any enabled format are left out.
	 *
	 * @param string[]|null $enabled Enabled optional formats; defaults to the saved setting.
	 */
	public static function type_catalogue( $enabled = null ) {
		$enabled = null === $enabled ? (array) self::get( 'enabled_formats' ) : (array) $enabled;
		$active  = array_merge( self::core_types(), $enabled );
		$out     = array();
		foreach ( self::full_catalogue() as $key => $group ) {
			$group['types'] = array_intersect_key( $group['types'], array_flip( $active ) );
			if ( $group['types'] ) {
				$out[ $key ] = $group;
			}
		}
		return $out;
	}

	/** Group keys (image, audio, video, document) that have at least one enabled format. */
	public static function active_groups() {
		return array_keys( self::type_catalogue() );
	}

	/** Every usable extension mapped to its MIME type. */
	public static function all_types( $enabled = null ) {
		$out = array();
		foreach ( self::type_catalogue( $enabled ) as $group ) {
			$out += $group['types'];
		}
		return $out;
	}

	/** Returns the group key (image/audio/video/document) for any known extension, or ''. */
	public static function group_for_ext( $ext ) {
		$ext = strtolower( (string) $ext );
		foreach ( self::full_catalogue() as $key => $group ) {
			if ( isset( $group['types'][ $ext ] ) ) {
				return $key;
			}
		}
		return '';
	}

	public static function defaults() {
		return array(
			// General.
			'folder'               => 'secure-media-vault',
			'enabled_formats'      => array(), // Optional formats switched on (none by default).
			'allowed_types'        => array( 'jpg', 'jpeg', 'png', 'mp3', 'wav' ),
			'max_size_mb'          => 128,
			'quota_gb'             => 50, // Total storage limit for everything in the vault. 0 = no limit.
			// Where files are stored, chosen separately for each place files come from: local | both | dropbox.
			'storage_mode'         => 'local', // Library (your own uploads).
			'storage_mode_form'    => 'local', // The [smv_upload_form] upload form.
			'storage_mode_cf7'     => 'local', // Contact Form 7 forms (each form can override it).
			'delete_on_uninstall'  => false,
			// Theme compatibility.
			'frontend_assets'      => 'auto', // auto (only where used) | always (every page, opt-in).
			'pause_other_media'    => true,
			// Contact Form 7.
			'cf7_mode'             => 'per_form', // per_form (switched on in each CF7 form's "Media Vault" tab) | off.
			'cf7_forms'            => array(),
			// Who can open Media Vault in the dashboard (administrators always can): admins | users.
			'vault_access'         => 'admins', // Library, collections, upload form screen.
			'vault_users'          => array(),
			'submissions_access'   => 'admins', // Submissions screen and their files.
			'submissions_users'    => array(),
			// Forms.
			'form_access'          => 'logged_in', // logged_in | anyone.
			'form_allowed_types'   => array( 'jpg', 'jpeg', 'png', 'mp3', 'wav' ),
			'form_max_files'       => 5,
			'form_max_size_mb'     => 20,
			'form_rate_limit'      => 10,
			'form_notify'          => true,
			'form_notify_email'    => '',
			'form_success_message' => __( 'Thank you — your files were uploaded securely.', 'secure-media-vault' ),
			// Dropbox.
			'dropbox_app_key'      => '',
			'dropbox_folder'       => '/Secure Media Vault',
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved                          = get_option( self::OPTION, array() );
			self::$cache                    = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
			self::$cache['enabled_formats'] = array_values( array_intersect( array_keys( self::extra_types() ), (array) self::$cache['enabled_formats'] ) );
			// Only formats that are currently enabled can be allowed.
			$valid = array_keys( self::all_types() );
			foreach ( array( 'allowed_types', 'form_allowed_types' ) as $key ) {
				self::$cache[ $key ] = array_values( array_intersect( (array) self::$cache[ $key ], $valid ) );
			}
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	// -----------------------------------------------------------------------
	// Secrets (encrypted at rest)
	// -----------------------------------------------------------------------

	public static function get_secret( $key ) {
		$secrets = get_option( self::SECRETS, array() );
		return isset( $secrets[ $key ] ) ? SMV_Crypto::decrypt( $secrets[ $key ] ) : '';
	}

	public static function set_secret( $key, $value ) {
		$secrets = get_option( self::SECRETS, array() );
		$secrets = is_array( $secrets ) ? $secrets : array();
		if ( '' === (string) $value ) {
			unset( $secrets[ $key ] );
		} else {
			$secrets[ $key ] = SMV_Crypto::encrypt( $value );
		}
		update_option( self::SECRETS, $secrets, false );
	}

	public static function has_secret( $key ) {
		$secrets = get_option( self::SECRETS, array() );
		return ! empty( $secrets[ $key ] );
	}

	// -----------------------------------------------------------------------
	// Sanitisation
	// -----------------------------------------------------------------------

	public static function sanitize_folder( $folder ) {
		$folder = strtolower( (string) $folder );
		$folder = str_replace( '\\', '/', $folder );
		$parts  = array();
		foreach ( explode( '/', $folder ) as $part ) {
			$part = preg_replace( '/[^a-z0-9_-]/', '', $part );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		$folder = implode( '/', $parts );
		return '' === $folder ? 'secure-media-vault' : $folder;
	}

	public static function sanitize_dropbox_folder( $folder ) {
		$folder = str_replace( '\\', '/', (string) $folder );
		$parts  = array();
		foreach ( explode( '/', $folder ) as $part ) {
			$part = trim( preg_replace( '/[\x00-\x1F<>:"|?*]/', '', $part ) );
			if ( '' !== $part && '.' !== $part && '..' !== $part ) {
				$parts[] = $part;
			}
		}
		return '/' . implode( '/', $parts );
	}

	private static function sanitize_types( $input, $enabled_formats ) {
		$valid = array_keys( self::all_types( $enabled_formats ) );
		$input = is_array( $input ) ? array_map( 'sanitize_key', $input ) : array();
		return array_values( array_intersect( $valid, $input ) );
	}

	public static function sanitize( $input ) {
		// If register_setting's default filter passes the stored value through, don't reprocess it.
		if ( ! is_array( $input ) ) {
			return self::all();
		}

		$d   = self::defaults();
		$out = array();

		$old_folder    = self::get( 'folder' );
		$out['folder'] = isset( $input['folder'] ) ? self::sanitize_folder( $input['folder'] ) : $d['folder'];

		$extras                 = isset( $input['enabled_formats'] ) ? array_map( 'sanitize_key', (array) $input['enabled_formats'] ) : array();
		$out['enabled_formats'] = array_values( array_intersect( array_keys( self::extra_types() ), $extras ) );

		$out['allowed_types'] = self::sanitize_types( isset( $input['allowed_types'] ) ? $input['allowed_types'] : array(), $out['enabled_formats'] );
		$out['max_size_mb']   = isset( $input['max_size_mb'] ) ? max( 1, min( 10240, absint( $input['max_size_mb'] ) ) ) : $d['max_size_mb'];
		$quota                = isset( $input['quota_gb'] ) ? (float) str_replace( ',', '.', (string) $input['quota_gb'] ) : $d['quota_gb'];
		$out['quota_gb']      = max( 0, min( 100000, round( $quota, 2 ) ) );

		foreach ( array( 'storage_mode', 'storage_mode_form', 'storage_mode_cf7' ) as $key ) {
			$mode        = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : 'local';
			$out[ $key ] = in_array( $mode, array( 'local', 'dropbox', 'both' ), true ) ? $mode : 'local';
		}

		$out['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		$assets                   = isset( $input['frontend_assets'] ) ? sanitize_key( $input['frontend_assets'] ) : 'auto';
		$out['frontend_assets']   = 'always' === $assets ? 'always' : 'auto'; // "shortcode" (older versions) = auto.
		$out['pause_other_media'] = ! empty( $input['pause_other_media'] );

		$cf7              = isset( $input['cf7_mode'] ) ? sanitize_key( $input['cf7_mode'] ) : 'per_form';
		$out['cf7_mode']  = 'off' === $cf7 ? 'off' : 'per_form';
		$out['cf7_forms'] = isset( $input['cf7_forms'] ) ? array_values( array_filter( array_map( 'absint', (array) $input['cf7_forms'] ) ) ) : array();

		foreach ( array( 'vault', 'submissions' ) as $scope ) {
			$mode                      = isset( $input[ $scope . '_access' ] ) ? sanitize_key( $input[ $scope . '_access' ] ) : 'admins';
			$out[ $scope . '_access' ] = 'users' === $mode ? 'users' : 'admins';
			$ids                       = isset( $input[ $scope . '_users' ] ) ? array_filter( array_map( 'absint', (array) $input[ $scope . '_users' ] ) ) : array();
			$out[ $scope . '_users' ]  = array_values( array_filter( array_unique( $ids ), 'get_userdata' ) );
		}

		$access             = isset( $input['form_access'] ) ? sanitize_key( $input['form_access'] ) : 'logged_in';
		$out['form_access'] = in_array( $access, array( 'logged_in', 'anyone' ), true ) ? $access : 'logged_in';

		$out['form_allowed_types']   = self::sanitize_types( isset( $input['form_allowed_types'] ) ? $input['form_allowed_types'] : array(), $out['enabled_formats'] );
		$out['form_max_files']       = isset( $input['form_max_files'] ) ? max( 1, min( 50, absint( $input['form_max_files'] ) ) ) : $d['form_max_files'];
		$out['form_max_size_mb']     = isset( $input['form_max_size_mb'] ) ? max( 1, min( 2048, absint( $input['form_max_size_mb'] ) ) ) : $d['form_max_size_mb'];
		$out['form_rate_limit']      = isset( $input['form_rate_limit'] ) ? max( 1, min( 1000, absint( $input['form_rate_limit'] ) ) ) : $d['form_rate_limit'];
		$out['form_notify']          = ! empty( $input['form_notify'] );
		$out['form_notify_email']    = isset( $input['form_notify_email'] ) ? sanitize_email( $input['form_notify_email'] ) : '';
		$out['form_success_message'] = isset( $input['form_success_message'] ) ? sanitize_text_field( $input['form_success_message'] ) : $d['form_success_message'];

		$out['dropbox_app_key'] = isset( $input['dropbox_app_key'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $input['dropbox_app_key'] ) : '';
		$out['dropbox_folder']  = isset( $input['dropbox_folder'] ) ? self::sanitize_dropbox_folder( $input['dropbox_folder'] ) : $d['dropbox_folder'];

		// App secret is stored encrypted, separately. Empty submission keeps the saved one.
		if ( ! empty( $input['dropbox_app_secret'] ) ) {
			self::set_secret( 'app_secret', preg_replace( '/[^A-Za-z0-9]/', '', $input['dropbox_app_secret'] ) );
		}
		if ( ! empty( $input['dropbox_forget_secret'] ) ) {
			self::set_secret( 'app_secret', '' );
			SMV_Dropbox::disconnect();
		}

		// Dropbox storage requires a connection.
		$forced = false;
		foreach ( array( 'storage_mode', 'storage_mode_form', 'storage_mode_cf7' ) as $key ) {
			if ( 'local' !== $out[ $key ] && ! SMV_Dropbox::is_connected() ) {
				$out[ $key ] = 'local';
				$forced      = true;
			}
		}
		if ( $forced ) {
			add_settings_error( self::OPTION, 'smv_dropbox', __( 'Storage was set to “Local folder” because Dropbox is not connected yet.', 'secure-media-vault' ), 'warning' );
		}

		self::$cache = null;

		// Prepare the (possibly new) folder right away so protection files exist.
		if ( $old_folder !== $out['folder'] ) {
			SMV_Storage::prepare_folders( $out['folder'] );
		}

		return $out;
	}
}
