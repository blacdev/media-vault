<?php
/**
 * Activation, database schema and upgrades.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Installer {

	public static function activate() {
		self::create_tables();
		SMV_Storage::prepare_folders( SMV_Settings::get( 'folder' ) );
		if ( false === get_option( SMV_Settings::OPTION ) ) {
			add_option( SMV_Settings::OPTION, SMV_Settings::defaults() );
		}
		update_option( 'smv_db_version', SMV_DB_VERSION, true ); // Autoloaded: read on every request, must not cost a query.
	}

	public static function deactivate() {
		// Nothing scheduled; data and files are kept until the plugin is deleted.
	}

	public static function maybe_upgrade() {
		if ( get_option( 'smv_db_version' ) !== SMV_DB_VERSION ) {
			self::activate();
		}
	}

	public static function tables() {
		global $wpdb;
		return array(
			'files'       => $wpdb->prefix . 'smv_files',
			'collections' => $wpdb->prefix . 'smv_collections',
			'submissions' => $wpdb->prefix . 'smv_submissions',
		);
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta(
			"CREATE TABLE {$t['files']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL DEFAULT '',
				caption text NULL,
				original_name varchar(255) NOT NULL DEFAULT '',
				file_name varchar(255) NOT NULL DEFAULT '',
				rel_path varchar(500) NOT NULL DEFAULT '',
				thumb_path varchar(500) NOT NULL DEFAULT '',
				ext varchar(16) NOT NULL DEFAULT '',
				mime varchar(100) NOT NULL DEFAULT '',
				file_group varchar(20) NOT NULL DEFAULT '',
				size bigint(20) unsigned NOT NULL DEFAULT 0,
				width int(10) unsigned NOT NULL DEFAULT 0,
				height int(10) unsigned NOT NULL DEFAULT 0,
				visibility varchar(10) NOT NULL DEFAULT 'public',
				source varchar(20) NOT NULL DEFAULT 'admin',
				storage varchar(10) NOT NULL DEFAULT 'local',
				storage_target varchar(10) NOT NULL DEFAULT '',
				dropbox_path varchar(500) NOT NULL DEFAULT '',
				dropbox_url text NULL,
				sync_status varchar(20) NOT NULL DEFAULT '',
				sync_error text NULL,
				submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY file_group (file_group),
				KEY visibility (visibility),
				KEY submission_id (submission_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['collections']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL DEFAULT '',
				slug varchar(191) NOT NULL DEFAULT '',
				type varchar(20) NOT NULL DEFAULT 'gallery',
				settings longtext NULL,
				items longtext NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['submissions']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_label varchar(191) NOT NULL DEFAULT '',
				name varchar(191) NOT NULL DEFAULT '',
				email varchar(191) NOT NULL DEFAULT '',
				message text NULL,
				fields longtext NULL,
				ip varchar(64) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				page_url varchar(500) NOT NULL DEFAULT '',
				status varchar(10) NOT NULL DEFAULT 'new',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY status (status)
			) $charset;"
		);
	}
}
