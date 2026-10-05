<?php
/**
 * Admin AJAX endpoints. Every endpoint requires the matching access level (vault, submissions or administrator) and a valid nonce.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Ajax {

	public static function init() {
		$actions = array(
			'upload',
			'library',
			'update_file',
			'delete_files',
			'retry_sync',
			'save_collection',
			'delete_collection',
			'duplicate_collection',
			'dropbox_test',
			'health_check',
			'publish_file',
			'delete_submission',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_smv_' . $action, array( __CLASS__, 'guard' ) );
		}
	}

	/** Central permission gate, then dispatch. */
	public static function guard() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		switch ( $action ) {
			case 'smv_delete_submission':
			case 'smv_publish_file':
				$allowed = SMV_Plugin::can_access_submissions();
				break;
			case 'smv_dropbox_test':
			case 'smv_health_check':
				$allowed = SMV_Plugin::can_manage();
				break;
			default:
				$allowed = SMV_Plugin::can_access_vault();
		}
		if ( ! $allowed ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'secure-media-vault' ) ), 403 );
		}
		if ( ! check_ajax_referer( 'smv_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please reload the page.', 'secure-media-vault' ) ), 403 );
		}
		$method = 'action_' . substr( $action, 4 );
		if ( 0 !== strpos( $action, 'smv_' ) || ! method_exists( __CLASS__, $method ) ) {
			wp_send_json_error( array( 'message' => 'Unknown action.' ), 400 );
		}
		self::$method();
	}

	// phpcs:disable WordPress.Security.NonceVerification -- nonce verified in guard().

	private static function int_param( $key ) {
		return isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
	}

	private static function action_upload() {
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file received.', 'secure-media-vault' ) ), 400 );
		}
		$result = SMV_Storage::handle_upload( $_FILES['file'], array( 'source' => 'admin' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated in SMV_Storage.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'file' => SMV_Files::to_array( $result ) ) );
	}

	private static function action_library() {
		$group                = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';
		list( $rows, $total ) = SMV_Files::query(
			array(
				'group'    => $group,
				'search'   => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
				'page'     => max( 1, self::int_param( 'page' ) ),
				'per_page' => 48,
			)
		);
		wp_send_json_success(
			array(
				'items'  => array_map( array( 'SMV_Files', 'to_array' ), $rows ),
				'total'  => $total,
				'counts' => SMV_Files::counts(),
			)
		);
	}

	private static function action_update_file() {
		$id   = self::int_param( 'id' );
		$file = SMV_Files::get( $id );
		if ( ! $file ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'secure-media-vault' ) ), 404 );
		}
		SMV_Files::update(
			$id,
			array(
				'title'   => isset( $_POST['title'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['title'] ) ), 0, 250 ) : $file->title,
				'caption' => isset( $_POST['caption'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ), 0, 2000 ) : $file->caption,
			)
		);
		wp_send_json_success( array( 'file' => SMV_Files::to_array( SMV_Files::get( $id ) ) ) );
	}

	private static function action_delete_files() {
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$ids = array_slice( array_filter( $ids ), 0, 200 );
		foreach ( $ids as $id ) {
			SMV_Files::delete( $id );
		}
		wp_send_json_success( array( 'deleted' => $ids ) );
	}

	private static function action_retry_sync() {
		if ( ! SMV_Dropbox::is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'Dropbox is not connected.', 'secure-media-vault' ) ), 400 );
		}
		$id     = self::int_param( 'id' );
		$result = SMV_Storage::sync_to_dropbox( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'file' => SMV_Files::to_array( SMV_Files::get( $id ) ) ) );
	}

	private static function action_save_collection() {
		$settings = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised in save().
		$items    = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id       = SMV_Collections::save(
			self::int_param( 'id' ),
			isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'gallery',
			is_array( $settings ) ? $settings : array(),
			is_array( $items ) ? $items : array()
		);
		$c        = SMV_Collections::get( $id );
		wp_send_json_success(
			array(
				'id'        => $id,
				'slug'      => $c->slug,
				'shortcode' => SMV_Collections::shortcode_for( $c ),
				'count'     => count( $c->items ),
				'editUrl'   => admin_url( 'admin.php?page=smv-collections&edit=' . $id ),
			)
		);
	}

	private static function action_delete_collection() {
		SMV_Collections::delete( self::int_param( 'id' ) );
		wp_send_json_success();
	}

	private static function action_duplicate_collection() {
		$id = SMV_Collections::duplicate( self::int_param( 'id' ) );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Collection not found.', 'secure-media-vault' ) ), 404 );
		}
		wp_send_json_success( array( 'id' => $id ) );
	}

	private static function action_dropbox_test() {
		$result = SMV_Dropbox::test();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	private static function action_health_check() {
		wp_send_json_success( SMV_Storage::health_check() );
	}

	private static function action_publish_file() {
		$result = SMV_Storage::publish( self::int_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success();
	}

	private static function action_delete_submission() {
		global $wpdb;
		$id            = self::int_param( 'id' );
		list( $files ) = SMV_Files::query(
			array(
				'visibility'    => '',
				'submission_id' => $id,
				'per_page'      => 200,
			)
		);
		foreach ( $files as $f ) {
			// Files already published to the library are kept.
			if ( 'private' === $f->visibility ) {
				SMV_Files::delete( (int) $f->id );
			}
		}
		$t = SMV_Installer::tables();
		$wpdb->delete( $t['submissions'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	// phpcs:enable
}
