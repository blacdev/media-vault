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
}
