<?php
/**
 * Library view.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

$smv_counts = SMV_Files::counts();
$smv_mode   = SMV_Settings::get( 'storage_mode' );
$smv_modes  = array(
	'local'   => __( 'Local folder', 'secure-media-vault' ),
	'dropbox' => __( 'Dropbox', 'secure-media-vault' ),
	'both'    => __( 'Local + Dropbox backup', 'secure-media-vault' ),
);

SMV_Admin::header(
	__( 'Library', 'secure-media-vault' ),
	__( 'Every file you upload lives here, ready to add to collections.', 'secure-media-vault' ),
	'<button type="button" class="smv-btn smv-btn--primary" data-smv-toggle-upload>' . SMV_Admin::icon( 'upload' ) . '<span>' . esc_html__( 'Upload files', 'secure-media-vault' ) . '</span></button>'
);
?>

<section class="smv-stats" aria-label="<?php esc_attr_e( 'Library overview', 'secure-media-vault' ); ?>">
	<div class="smv-stat">
		<span class="smv-stat__label"><?php esc_html_e( 'Files', 'secure-media-vault' ); ?></span>
		<span class="smv-stat__value" data-smv-count="all"><?php echo esc_html( number_format_i18n( $smv_counts['all'] ) ); ?></span>
	</div>
	<div class="smv-stat">
		<?php $smv_q = SMV_Storage::quota_status(); ?>
		<span class="smv-stat__label"><?php esc_html_e( 'Storage used', 'secure-media-vault' ); ?></span>
		<span class="smv-stat__value">
			<?php echo esc_html( $smv_q['used'] ? size_format( $smv_q['used'], 1 ) : '0 B' ); ?>
			<?php if ( $smv_q['limit'] ) : ?>
				<small class="smv-muted" style="font-size:13px;font-weight:400">
					<?php
					/* translators: %s: storage limit */
					printf( esc_html__( 'of %s', 'secure-media-vault' ), esc_html( size_format( $smv_q['limit'] ) ) );
					?>
				</small>
			<?php endif; ?>
		</span>
		<?php if ( $smv_q['limit'] ) : ?>
			<span class="smv-meter<?php echo $smv_q['percent'] >= 95 ? ' is-danger' : ( $smv_q['percent'] >= 80 ? ' is-warn' : '' ); ?>" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $smv_q['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Storage used', 'secure-media-vault' ); ?>"><span style="width:<?php echo esc_attr( max( 1, $smv_q['percent'] ) ); ?>%"></span></span>
		<?php else : ?>
			<span class="smv-muted" style="font-size:12px"><?php esc_html_e( 'No storage limit', 'secure-media-vault' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="smv-stat">
		<span class="smv-stat__label"><?php esc_html_e( 'Library storage', 'secure-media-vault' ); ?></span>
		<span class="smv-stat__value smv-stat__value--sm">
			<?php echo SMV_Admin::icon( 'local' === $smv_mode ? 'shield' : 'cloud' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo esc_html( $smv_modes[ $smv_mode ] ); ?>
		</span>
	</div>
	<div class="smv-stat">
		<span class="smv-stat__label"><?php esc_html_e( 'Max upload', 'secure-media-vault' ); ?></span>
		<span class="smv-stat__value smv-stat__value--sm"><?php echo esc_html( size_format( min( SMV_Settings::get( 'max_size_mb' ) * MB_IN_BYTES, wp_max_upload_size() ) ) ); ?></span>
	</div>
</section>

<section class="smv-uploader" data-smv-uploader hidden>
	<div class="smv-dropzone" data-smv-dropzone tabindex="0" role="button" aria-describedby="smv-drop-hint">
		<input type="file" multiple class="smv-visually-hidden" data-smv-file-input tabindex="-1" aria-hidden="true">
		<span class="smv-dropzone__icon"><?php echo SMV_Admin::icon( 'upload', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<strong class="smv-dropzone__title"><?php esc_html_e( 'Drop files to upload', 'secure-media-vault' ); ?></strong>
		<span class="smv-dropzone__hint" id="smv-drop-hint">
			<?php
			printf(
				/* translators: 1: max size, 2: allowed types */
				esc_html__( 'or click to browse · up to %1$s each · %2$s', 'secure-media-vault' ),
				esc_html( size_format( min( SMV_Settings::get( 'max_size_mb' ) * MB_IN_BYTES, wp_max_upload_size() ) ) ),
				esc_html( strtoupper( implode( ', ', (array) SMV_Settings::get( 'allowed_types' ) ) ) )
			);
			?>
		</span>
	</div>
	<ul class="smv-queue" data-smv-queue aria-live="polite"></ul>
</section>

<div class="smv-toolbar">
	<div class="smv-chips" role="tablist" aria-label="<?php esc_attr_e( 'Filter by type', 'secure-media-vault' ); ?>">
		<?php
		// "All" plus one filter per enabled group (Video / Documents only once enabled in Settings).
		$smv_filters = array( 'all' => __( 'All', 'secure-media-vault' ) );
		foreach ( SMV_Settings::type_catalogue() as $smv_gkey => $smv_group ) {
			$smv_filters[ $smv_gkey ] = $smv_group['label'];
		}
		foreach ( $smv_filters as $smv_key => $smv_label ) :
			?>
			<button type="button" role="tab" class="smv-chip<?php echo 'all' === $smv_key ? ' is-active' : ''; ?>" aria-selected="<?php echo 'all' === $smv_key ? 'true' : 'false'; ?>" data-smv-filter="<?php echo esc_attr( $smv_key ); ?>">
				<?php echo esc_html( $smv_label ); ?>
				<span class="smv-chip__count" data-smv-count="<?php echo esc_attr( $smv_key ); ?>"><?php echo (int) ( isset( $smv_counts[ $smv_key ] ) ? $smv_counts[ $smv_key ] : 0 ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>
	<div class="smv-toolbar__right">
		<label class="smv-search">
			<span class="screen-reader-text"><?php esc_html_e( 'Search files', 'secure-media-vault' ); ?></span>
			<?php echo SMV_Admin::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="search" placeholder="<?php esc_attr_e( 'Search files…', 'secure-media-vault' ); ?>" data-smv-search>
		</label>
		<button type="button" class="smv-btn smv-btn--ghost" data-smv-select-mode aria-pressed="false"><?php echo SMV_Admin::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Select', 'secure-media-vault' ); ?></span></button>
	</div>
</div>

<div class="smv-bulkbar" data-smv-bulkbar hidden>
	<span data-smv-bulk-count></span>
	<button type="button" class="smv-btn smv-btn--danger smv-btn--sm" data-smv-bulk-delete><?php echo SMV_Admin::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Delete selected', 'secure-media-vault' ); ?></span></button>
	<button type="button" class="smv-btn smv-btn--ghost smv-btn--sm" data-smv-bulk-cancel><?php esc_html_e( 'Cancel', 'secure-media-vault' ); ?></button>
</div>

<div class="smv-library" data-smv-library>
	<div class="smv-grid" data-smv-grid aria-busy="true"></div>
	<div class="smv-empty" data-smv-empty hidden>
		<span class="smv-empty__icon"><?php echo SMV_Admin::icon( 'image', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<p data-smv-empty-text></p>
	</div>
	<div class="smv-center"><button type="button" class="smv-btn smv-btn--ghost" data-smv-more hidden><?php esc_html_e( 'Load more', 'secure-media-vault' ); ?></button></div>
</div>

<!-- Detail drawer -->
<div class="smv-drawer" data-smv-drawer hidden>
	<div class="smv-drawer__backdrop" data-smv-drawer-close></div>
	<aside class="smv-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="smv-drawer-title">
		<header class="smv-drawer__head">
			<h2 id="smv-drawer-title"><?php esc_html_e( 'File details', 'secure-media-vault' ); ?></h2>
			<button type="button" class="smv-iconbtn" data-smv-drawer-close aria-label="<?php esc_attr_e( 'Close', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'x', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</header>
		<div class="smv-drawer__body">
			<div class="smv-drawer__preview" data-smv-d-preview></div>
			<form class="smv-drawer__form" data-smv-d-form>
				<div class="smv-field">
					<label for="smv-d-title"><?php esc_html_e( 'Title', 'secure-media-vault' ); ?></label>
					<input type="text" id="smv-d-title" class="smv-input" data-smv-d-title maxlength="250">
				</div>
				<div class="smv-field">
					<label for="smv-d-caption"><?php esc_html_e( 'Caption', 'secure-media-vault' ); ?></label>
					<textarea id="smv-d-caption" class="smv-input" rows="3" data-smv-d-caption maxlength="2000"></textarea>
					<p class="smv-help"><?php esc_html_e( 'Shown under images and in the lightbox when captions are on.', 'secure-media-vault' ); ?></p>
				</div>
				<div class="smv-field">
					<label for="smv-d-url"><?php esc_html_e( 'File URL', 'secure-media-vault' ); ?></label>
					<div class="smv-copyfield">
						<input type="text" id="smv-d-url" class="smv-input" readonly data-smv-d-url>
						<button type="button" class="smv-btn smv-btn--ghost smv-btn--sm" data-smv-copy-from="#smv-d-url"><?php echo SMV_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Copy', 'secure-media-vault' ); ?></span></button>
					</div>
				</div>
				<dl class="smv-meta" data-smv-d-meta></dl>
				<div class="smv-alert smv-alert--warning" data-smv-d-sync hidden></div>
			</form>
		</div>
		<footer class="smv-drawer__foot">
			<button type="button" class="smv-btn smv-btn--danger-ghost" data-smv-d-delete><?php echo SMV_Admin::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Delete', 'secure-media-vault' ); ?></span></button>
			<div class="smv-drawer__foot-right">
				<button type="button" class="smv-btn smv-btn--ghost" data-smv-d-retry hidden><?php echo SMV_Admin::icon( 'cloud' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Retry Dropbox sync', 'secure-media-vault' ); ?></span></button>
				<button type="button" class="smv-btn smv-btn--primary" data-smv-d-save><?php esc_html_e( 'Save changes', 'secure-media-vault' ); ?></button>
			</div>
		</footer>
	</aside>
</div>
