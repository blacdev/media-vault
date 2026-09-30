<?php
/**
 * Uninstall: removes data only if the user opted in. Files on disk and in Dropbox are never deleted.
 *
 * @package SecureMediaVault
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$smv_settings = get_option( 'smv_settings', array() );

if ( ! empty( $smv_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	foreach ( array( 'smv_files', 'smv_collections', 'smv_submissions' ) as $smv_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$smv_table}" ); // phpcs:ignore WordPress.DB
	}
	foreach ( array( 'smv_settings', 'smv_secrets', 'smv_db_version', 'smv_dropbox_token_expires', 'smv_dropbox_account', 'smv_folder_error' ) as $smv_option ) {
		delete_option( $smv_option );
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_smv\\_%' OR option_name LIKE '\\_transient\\_timeout\\_smv\\_%'" ); // phpcs:ignore WordPress.DB
} else {
	// Always remove credentials so a deleted plugin leaves no live Dropbox tokens behind.
	delete_option( 'smv_secrets' );
}
