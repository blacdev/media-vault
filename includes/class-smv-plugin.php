<?php
/**
 * Main plugin bootstrap.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

final class SMV_Plugin {

	/** @var SMV_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'secure-media-vault', false, dirname( plugin_basename( SMV_FILE ) ) . '/languages' );

		SMV_Installer::maybe_upgrade();

		SMV_Settings::init();
		SMV_Download::init();
		SMV_Shortcodes::init();
		SMV_Forms::init();
		SMV_CF7::init();

		if ( is_admin() ) {
			SMV_Ajax::init();
			SMV_Admin::init();
		}

		SMV_Dropbox::init();
	}

	/**
	 * Capability required to manage the vault. Filterable for multi-admin setups.
	 */
	public static function capability() {
		return apply_filters( 'smv_capability', 'manage_options' );
	}

	/** Administrators: settings, Dropbox and diagnostics. Never delegated. */
	public static function can_manage() {
		return current_user_can( self::capability() );
	}

	/** Library, collections and the upload form screen. */
	public static function can_access_vault() {
		return self::can_via_setting( 'vault_access', 'vault_users' );
	}

	/** Submissions screen and the private files that belong to submissions. */
	public static function can_access_submissions() {
		return self::can_via_setting( 'submissions_access', 'submissions_users' );
	}

	/** Administrators always pass; otherwise the user must be on the selected list. */
	private static function can_via_setting( $mode_key, $users_key ) {
		if ( self::can_manage() ) {
			return true;
		}
		$uid = get_current_user_id();
		if ( ! $uid || 'users' !== SMV_Settings::get( $mode_key ) ) {
			return false;
		}
		return in_array( $uid, array_map( 'intval', (array) SMV_Settings::get( $users_key ) ), true );
	}

	/** Whether the current user may open the given Media Vault admin page. */
	public static function can_access_page( $slug ) {
		switch ( $slug ) {
			case 'smv-settings':
				return self::can_manage();
			case 'smv-submissions':
				return self::can_access_submissions();
			default:
				return self::can_access_vault();
		}
	}
}
