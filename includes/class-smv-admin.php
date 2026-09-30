<?php
/**
 * Admin menus, assets and page routing.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SMV_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_notices', array( __CLASS__, 'folder_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'quota_notice' ) );
	}

	/** Warn on Media Vault screens when storage is at 90 % of the limit or more. */
	public static function quota_notice() {
		if ( ! self::current_page() || ! current_user_can( SMV_Plugin::capability() ) ) {
			return;
		}
		$q = SMV_Storage::quota_status();
		if ( ! $q['limit'] || $q['percent'] < 90 ) {
			return;
		}
		$msg = $q['full']
			/* translators: %s: storage limit */
			? sprintf( __( 'Storage limit of %s reached. New uploads are refused and the upload form is paused until you delete files or raise the limit.', 'secure-media-vault' ), size_format( $q['limit'] ) )
			/* translators: 1: percent used, 2: storage limit, 3: space left */
			: sprintf( __( 'Storage is %1$s%% full (limit %2$s, %3$s left).', 'secure-media-vault' ), number_format_i18n( $q['percent'], 1 ), size_format( $q['limit'] ), size_format( $q['left'], 1 ) );
		printf(
			'<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s <a href="%4$s">%5$s</a></p></div>',
			$q['full'] ? 'error' : 'warning',
			esc_html__( 'Media Vault:', 'secure-media-vault' ),
			esc_html( $msg ),
			esc_url( admin_url( 'admin.php?page=smv-settings' ) ),
			esc_html__( 'Change the limit', 'secure-media-vault' )
		);
	}

	/** Friendly warning instead of PHP warnings when the uploads folder can't be written. */
	public static function folder_notice() {
		$dir = get_option( 'smv_folder_error' );
		if ( ! $dir || ! current_user_can( SMV_Plugin::capability() ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
			esc_html__( 'Secure Media Vault:', 'secure-media-vault' ),
			esc_html__( 'the upload folder could not be created or is not writable. Uploads are paused until the folder permissions are fixed. Your site is otherwise unaffected.', 'secure-media-vault' ),
			esc_html( $dir )
		);
	}

	public static function pages() {
		return array(
			'smv-library'     => __( 'Library', 'secure-media-vault' ),
			'smv-collections' => __( 'Collections', 'secure-media-vault' ),
			'smv-forms'       => __( 'Upload form', 'secure-media-vault' ),
			'smv-submissions' => __( 'Submissions', 'secure-media-vault' ),
			'smv-settings'    => __( 'Settings', 'secure-media-vault' ),
		);
	}

	private static function new_submissions() {
		global $wpdb;
		$t = SMV_Installer::tables();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['submissions']} WHERE status = 'new'" ); // phpcs:ignore WordPress.DB
	}

	public static function menu() {
		$cap  = SMV_Plugin::capability();
		$new  = self::new_submissions();
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="black" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4Zm0 6a2.5 2.5 0 0 1 1 4.79V15h-2v-3.21A2.5 2.5 0 0 1 12 7Z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		add_menu_page( __( 'Media Vault', 'secure-media-vault' ), __( 'Media Vault', 'secure-media-vault' ), $cap, 'smv-library', array( __CLASS__, 'render' ), $icon, 11 );

		foreach ( self::pages() as $slug => $label ) {
			$menu_label = $label;
			if ( 'smv-submissions' === $slug && $new ) {
				$menu_label .= ' <span class="awaiting-mod">' . (int) $new . '</span>';
			}
			add_submenu_page( 'smv-library', $label . ' ‹ ' . __( 'Media Vault', 'secure-media-vault' ), $menu_label, $cap, $slug, array( __CLASS__, 'render' ) );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smv-settings' ) ) . '">' . esc_html__( 'Settings', 'secure-media-vault' ) . '</a>' );
		return $links;
	}

	private static function current_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return array_key_exists( $page, self::pages() ) ? $page : '';
	}

	public static function body_class( $classes ) {
		return self::current_page() ? $classes . ' smv-screen' : $classes;
	}

	public static function assets() {
		$page = self::current_page();
		if ( ! $page ) {
			return;
		}
		wp_enqueue_style( 'smv-admin', SMV_URL . 'assets/css/admin.css', array(), SMV_VERSION );
		wp_enqueue_script( 'smv-admin', SMV_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), SMV_VERSION, true );

		$allowed = (array) SMV_Settings::get( 'allowed_types' );
		wp_localize_script(
			'smv-admin',
			'smvAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'smv_admin' ),
				'page'           => $page,
				'maxBytes'       => (int) min( SMV_Settings::get( 'max_size_mb' ) * MB_IN_BYTES, wp_max_upload_size() ),
				'maxHuman'       => size_format( min( SMV_Settings::get( 'max_size_mb' ) * MB_IN_BYTES, wp_max_upload_size() ) ),
				'allowed'        => $allowed,
				'accept'       => implode( ',', array_map( function ( $e ) { return '.' . $e; }, $allowed ) ), // phpcs:ignore
				'dropbox'        => SMV_Dropbox::is_connected(),
				'storageMode'    => SMV_Settings::get( 'storage_mode' ),
				'collectionsUrl' => admin_url( 'admin.php?page=smv-collections' ),
				'types'          => SMV_Collections::types(),
				'i18n'           => array(
					'copied'         => __( 'Copied to clipboard', 'secure-media-vault' ),
					'copyFailed'     => __( 'Press Ctrl+C to copy', 'secure-media-vault' ),
					'saved'          => __( 'Saved', 'secure-media-vault' ),
					'saving'         => __( 'Saving…', 'secure-media-vault' ),
					'saveChanges'    => __( 'Save changes', 'secure-media-vault' ),
					'createdCopied'  => __( 'Collection created — shortcode copied', 'secure-media-vault' ),
					'deleted'        => __( 'Deleted', 'secure-media-vault' ),
					'uploaded'       => __( 'Uploaded', 'secure-media-vault' ),
					'uploadFailed'   => __( 'Upload failed', 'secure-media-vault' ),
					'tooBig'         => __( 'File is larger than the maximum size', 'secure-media-vault' ),
					'badType'        => __( 'File type not allowed', 'secure-media-vault' ),
					'confirmDelete'  => __( 'Delete this file permanently? It will also be removed from every collection and from Dropbox.', 'secure-media-vault' ),
					/* translators: %d: number of files */
					'confirmDeleteN' => __( 'Delete %d files permanently? They will also be removed from every collection and from Dropbox.', 'secure-media-vault' ),
					'confirmDelCol'  => __( 'Delete this collection? Pages using its shortcode will show nothing. Files stay in your library.', 'secure-media-vault' ),
					'confirmDelSub'  => __( 'Delete this submission and its private files permanently?', 'secure-media-vault' ),
					'confirmLeave'   => __( 'You have unsaved changes.', 'secure-media-vault' ),
					'loadMore'       => __( 'Load more', 'secure-media-vault' ),
					'noResults'      => __( 'No files match your filters.', 'secure-media-vault' ),
					'emptyLibrary'   => __( 'Your library is empty. Drop files above to get started.', 'secure-media-vault' ),
					/* translators: %d: number of selected files */
					'addN'           => __( 'Add %d selected', 'secure-media-vault' ),
					/* translators: %d: number of selected files */
					'selected'       => __( '%d selected', 'secure-media-vault' ),
					'error'          => __( 'Something went wrong. Please try again.', 'secure-media-vault' ),
					'published'      => __( 'Moved to library', 'secure-media-vault' ),
					'synced'         => __( 'Synced to Dropbox', 'secure-media-vault' ),
					'checking'       => __( 'Checking…', 'secure-media-vault' ),
					'remove'         => __( 'Remove', 'secure-media-vault' ),
					'noItems'        => __( 'No media yet. Click “Add media” to choose files from your library.', 'secure-media-vault' ),
					'storage'        => array(
						'local'   => __( 'Local', 'secure-media-vault' ),
						'dropbox' => __( 'Dropbox', 'secure-media-vault' ),
						'both'    => __( 'Local + Dropbox', 'secure-media-vault' ),
					),
				),
			)
		);
	}

	public static function render() {
		if ( ! current_user_can( SMV_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'secure-media-vault' ) );
		}
		$page = self::current_page();
		$map  = array(
			'smv-library'     => 'library',
			'smv-collections' => isset( $_GET['edit'] ) || isset( $_GET['new'] ) ? 'collection-edit' : 'collections', // phpcs:ignore WordPress.Security.NonceVerification
			'smv-forms'       => 'forms',
			'smv-submissions' => 'submissions',
			'smv-settings'    => 'settings',
		);
		$view = isset( $map[ $page ] ) ? $map[ $page ] : 'library';

		echo '<div class="wrap smv-admin smv-admin--' . esc_attr( $view ) . '">';
		include SMV_DIR . 'admin/views/' . $view . '.php';
		echo '</div>';
	}

	/**
	 * Shared page header.
	 *
	 * @param string $title    Page title.
	 * @param string $subtitle Short description.
	 * @param string $actions  Pre-escaped HTML for header actions.
	 */
	public static function header( $title, $subtitle = '', $actions = '' ) {
		$current = self::current_page();
		?>
		<header class="smv-header">
			<div class="smv-header__brand">
				<span class="smv-header__logo" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4Zm0 6a2.5 2.5 0 0 1 1 4.79V15h-2v-3.21A2.5 2.5 0 0 1 12 7Z"/></svg>
				</span>
				<div>
					<h1 class="smv-header__title"><?php echo esc_html( $title ); ?></h1>
					<?php if ( $subtitle ) : ?>
						<p class="smv-header__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="smv-header__actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by callers. ?></div>
		</header>
		<nav class="smv-tabs" aria-label="<?php esc_attr_e( 'Media Vault sections', 'secure-media-vault' ); ?>">
			<?php foreach ( self::pages() as $slug => $label ) : ?>
				<a class="smv-tabs__item<?php echo $current === $slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"<?php echo $current === $slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<hr class="wp-header-end">
		<?php
	}

	/** Small inline SVG icon set used across views. */
	public static function icon( $name, $size = 16 ) {
		$paths = array(
			'upload'     => 'M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
			'plus'       => 'M12 5v14M5 12h14',
			'copy'       => 'M9 9h10v10H9zM5 15V5h10',
			'trash'      => 'M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3',
			'edit'       => 'M4 20h4L19 9l-4-4L4 16v4Z',
			'search'     => 'm20 20-4.2-4.2M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z',
			'download'   => 'M12 4v12m0 0 4-4m-4 4-4-4M4 20h16',
			'check'      => 'm5 12 5 5 9-10',
			'grid'       => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
			'music'      => 'M9 18V5l11-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm11-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
			'film'       => 'M4 4h16v16H4zM8 4v16M16 4v16M4 8h4M4 16h4M16 8h4M16 16h4',
			'file'       => 'M14 3H6v18h12V7l-4-4Zm0 0v4h4',
			'image'      => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15 9h.01',
			'inbox'      => 'M4 13h4l2 3h4l2-3h4M4 13l2-8h12l2 8v6H4v-6Z',
			'cloud'      => 'M7 18a4 4 0 0 1-.5-7.97A6 6 0 0 1 18 9a4.5 4.5 0 0 1-.5 9H7Z',
			'shield'     => 'M12 3 4 6v6c0 4.5 3.2 8.3 8 9 4.8-.7 8-4.5 8-9V6l-8-3Z',
			'external'   => 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
			'x'          => 'M6 6l12 12M18 6 6 18',
			'arrow-left' => 'M19 12H5m0 0 6-6m-6 6 6 6',
			'lock'       => 'M6 11h12v10H6zM8 11V7a4 4 0 1 1 8 0v4',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="smv-icon" viewBox="0 0 24 24" width="%1$d" height="%1$d" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="%2$s"/></svg>',
			(int) $size,
			esc_attr( $paths[ $name ] )
		);
	}
}
