<?php
/**
 * Collection editor.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- read-only routing params.
$smv_id         = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$smv_collection = $smv_id ? SMV_Collections::get( $smv_id ) : null;
$smv_types      = SMV_Collections::types();
$smv_type       = $smv_collection ? $smv_collection->type : ( isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'gallery' );
$smv_type       = isset( $smv_types[ $smv_type ] ) ? $smv_type : 'gallery';
// phpcs:enable

if ( $smv_id && ! $smv_collection ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Collection not found.', 'secure-media-vault' ) . '</p></div>';
	return;
}

$smv_settings = $smv_collection ? $smv_collection->settings : SMV_Collections::default_settings();
$smv_items    = array();
if ( $smv_collection ) {
	$smv_files = SMV_Files::get_many( $smv_collection->items );
	foreach ( $smv_collection->items as $smv_fid ) {
		if ( isset( $smv_files[ $smv_fid ] ) ) {
			$smv_items[] = SMV_Files::to_array( $smv_files[ $smv_fid ] );
		}
	}
}

$smv_data = array(
	'id'       => $smv_collection ? (int) $smv_collection->id : 0,
	'title'    => $smv_collection ? $smv_collection->title : '',
	'slug'     => $smv_collection ? $smv_collection->slug : '',
	'type'     => $smv_type,
	'settings' => $smv_settings,
	'items'    => $smv_items,
);

SMV_Admin::header(
	$smv_collection ? __( 'Edit collection', 'secure-media-vault' ) : __( 'New collection', 'secure-media-vault' ),
	__( 'Changes apply everywhere this shortcode is used.', 'secure-media-vault' ),
	'<a class="smv-btn smv-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=smv-collections' ) ) . '">' . SMV_Admin::icon( 'arrow-left' ) . '<span>' . esc_html__( 'All collections', 'secure-media-vault' ) . '</span></a>'
);
?>
<script type="application/json" id="smv-collection-data"><?php echo wp_json_encode( $smv_data ); ?></script>

<form class="smv-editor" data-smv-editor autocomplete="off">
	<div class="smv-editor__main">
		<div class="smv-card">
			<label class="screen-reader-text" for="smv-c-title"><?php esc_html_e( 'Collection title', 'secure-media-vault' ); ?></label>
			<input type="text" id="smv-c-title" class="smv-input smv-input--title" data-smv-c-title placeholder="<?php esc_attr_e( 'Name your collection', 'secure-media-vault' ); ?>" maxlength="200" required>
			<div class="smv-slugrow">
				<label for="smv-c-slug"><?php esc_html_e( 'Slug', 'secure-media-vault' ); ?></label>
				<input type="text" id="smv-c-slug" class="smv-input smv-input--inline" data-smv-c-slug placeholder="<?php esc_attr_e( 'auto', 'secure-media-vault' ); ?>" maxlength="190" pattern="[a-z0-9-]*">
			</div>
		</div>

		<div class="smv-card">
			<div class="smv-card__head">
				<div>
					<h2 class="smv-card__title"><?php esc_html_e( 'Media', 'secure-media-vault' ); ?> <span class="smv-pill" data-smv-c-count>0</span></h2>
					<p class="smv-help"><?php esc_html_e( 'Drag to reorder. The order here is the order on your site.', 'secure-media-vault' ); ?></p>
				</div>
				<button type="button" class="smv-btn smv-btn--secondary" data-smv-open-picker><?php echo SMV_Admin::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Add media', 'secure-media-vault' ); ?></span></button>
			</div>
			<ul class="smv-items" data-smv-c-items></ul>
			<div class="smv-empty smv-empty--inline" data-smv-c-empty>
				<span class="smv-empty__icon"><?php echo SMV_Admin::icon( 'grid', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<p><?php esc_html_e( 'No media yet. Click “Add media” to choose files from your library or upload new ones.', 'secure-media-vault' ); ?></p>
			</div>
		</div>
	</div>

	<aside class="smv-editor__side">
		<div class="smv-card smv-card--sticky">
			<div class="smv-savebar">
				<span class="smv-savebar__state" data-smv-c-state aria-live="polite"></span>
				<button type="submit" class="smv-btn smv-btn--primary" data-smv-c-save><?php echo $smv_collection ? esc_html__( 'Save changes', 'secure-media-vault' ) : esc_html__( 'Create collection', 'secure-media-vault' ); ?></button>
			</div>

			<div class="smv-shortcode" data-smv-c-shortcode-wrap <?php echo $smv_collection ? '' : 'hidden'; ?>>
				<span class="smv-shortcode__label"><?php esc_html_e( 'Shortcode', 'secure-media-vault' ); ?></span>
				<button type="button" class="smv-code smv-code--block" data-smv-copy="<?php echo $smv_collection ? esc_attr( SMV_Collections::shortcode_for( $smv_collection ) ) : ''; ?>" data-smv-c-shortcode>
					<code><?php echo $smv_collection ? esc_html( SMV_Collections::shortcode_for( $smv_collection ) ) : ''; ?></code><?php echo SMV_Admin::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<p class="smv-help"><?php esc_html_e( 'Paste into any post, page or Shortcode block.', 'secure-media-vault' ); ?></p>
			</div>
		</div>

		<div class="smv-card">
			<h2 class="smv-card__title"><?php esc_html_e( 'Type', 'secure-media-vault' ); ?></h2>
			<div class="smv-typegrid" role="radiogroup" aria-label="<?php esc_attr_e( 'Collection type', 'secure-media-vault' ); ?>">
				<?php foreach ( $smv_types as $smv_key => $smv_t ) : ?>
					<label class="smv-typecard smv-typecard--radio">
						<input type="radio" name="smv_type" value="<?php echo esc_attr( $smv_key ); ?>" data-smv-c-type <?php checked( $smv_type, $smv_key ); ?>>
						<span class="smv-typecard__icon smv-typecard__icon--<?php echo esc_attr( $smv_key ); ?>"></span>
						<strong><?php echo esc_html( $smv_t['label'] ); ?></strong>
						<span><?php echo esc_html( $smv_t['desc'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="smv-card">
			<h2 class="smv-card__title"><?php esc_html_e( 'Display', 'secure-media-vault' ); ?></h2>

			<div class="smv-field" data-smv-show-for="gallery">
				<label for="smv-o-columns"><?php esc_html_e( 'Columns', 'secure-media-vault' ); ?> <output data-smv-out="columns"></output></label>
				<input type="range" id="smv-o-columns" min="1" max="8" step="1" data-smv-opt="columns">
			</div>
			<div class="smv-field" data-smv-show-for="gallery">
				<label for="smv-o-gap"><?php esc_html_e( 'Spacing', 'secure-media-vault' ); ?> <output data-smv-out="gap"></output></label>
				<input type="range" id="smv-o-gap" min="0" max="48" step="2" data-smv-opt="gap">
			</div>
			<div class="smv-field" data-smv-show-for="gallery">
				<label for="smv-o-ratio"><?php esc_html_e( 'Thumbnail shape', 'secure-media-vault' ); ?></label>
				<select id="smv-o-ratio" class="smv-input" data-smv-opt="ratio">
					<option value="square"><?php esc_html_e( 'Square', 'secure-media-vault' ); ?></option>
					<option value="landscape"><?php esc_html_e( 'Landscape (4:3)', 'secure-media-vault' ); ?></option>
					<option value="portrait"><?php esc_html_e( 'Portrait (3:4)', 'secure-media-vault' ); ?></option>
					<option value="original"><?php esc_html_e( 'Original', 'secure-media-vault' ); ?></option>
				</select>
			</div>

			<?php
			$smv_toggles = array(
				'lightbox'  => array( __( 'Open images in a lightbox', 'secure-media-vault' ), 'gallery mixed' ),
				'captions'  => array( __( 'Show captions', 'secure-media-vault' ), 'gallery mixed' ),
				'show_list' => array( __( 'Show track list', 'secure-media-vault' ), 'audio' ),
				'autoplay'  => array( __( 'Continue to next track automatically', 'secure-media-vault' ), 'audio' ),
				'loop'      => array( __( 'Loop playlist', 'secure-media-vault' ), 'audio' ),
				'download'  => array( __( 'Allow download button in player', 'secure-media-vault' ), 'audio' ),
			);
			foreach ( $smv_toggles as $smv_key => $smv_toggle ) :
				?>
				<label class="smv-switch" data-smv-show-for="<?php echo esc_attr( $smv_toggle[1] ); ?>">
					<input type="checkbox" data-smv-opt="<?php echo esc_attr( $smv_key ); ?>">
					<span class="smv-switch__track" aria-hidden="true"></span>
					<span class="smv-switch__label"><?php echo esc_html( $smv_toggle[0] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</aside>
</form>

<!-- Media picker -->
<div class="smv-modal" data-smv-picker hidden>
	<div class="smv-modal__backdrop" data-smv-picker-close></div>
	<div class="smv-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smv-picker-title">
		<header class="smv-modal__head">
			<h2 id="smv-picker-title"><?php esc_html_e( 'Add media', 'secure-media-vault' ); ?></h2>
			<button type="button" class="smv-iconbtn" data-smv-picker-close aria-label="<?php esc_attr_e( 'Close', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'x', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</header>
		<div class="smv-modal__toolbar">
			<div class="smv-chips" data-smv-picker-filters>
				<button type="button" class="smv-chip" data-smv-filter="all"><?php esc_html_e( 'All', 'secure-media-vault' ); ?></button>
				<button type="button" class="smv-chip" data-smv-filter="image"><?php esc_html_e( 'Images', 'secure-media-vault' ); ?></button>
				<button type="button" class="smv-chip" data-smv-filter="audio"><?php esc_html_e( 'Audio', 'secure-media-vault' ); ?></button>
			</div>
			<label class="smv-search">
				<span class="screen-reader-text"><?php esc_html_e( 'Search files', 'secure-media-vault' ); ?></span>
				<?php echo SMV_Admin::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input type="search" placeholder="<?php esc_attr_e( 'Search files…', 'secure-media-vault' ); ?>" data-smv-search>
			</label>
		</div>
		<div class="smv-modal__body">
			<div class="smv-dropzone smv-dropzone--compact" data-smv-dropzone tabindex="0" role="button">
				<input type="file" multiple class="smv-visually-hidden" data-smv-file-input tabindex="-1" aria-hidden="true">
				<?php echo SMV_Admin::icon( 'upload', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php echo wp_kses( __( '<strong>Upload new files</strong> — drop here or click to browse', 'secure-media-vault' ), array( 'strong' => array() ) ); ?></span>
			</div>
			<ul class="smv-queue" data-smv-queue aria-live="polite"></ul>
			<div class="smv-grid smv-grid--picker" data-smv-grid></div>
			<div class="smv-empty" data-smv-empty hidden><p data-smv-empty-text></p></div>
			<div class="smv-center"><button type="button" class="smv-btn smv-btn--ghost" data-smv-more hidden><?php esc_html_e( 'Load more', 'secure-media-vault' ); ?></button></div>
		</div>
		<footer class="smv-modal__foot">
			<span class="smv-muted" data-smv-picker-count></span>
			<div>
				<button type="button" class="smv-btn smv-btn--ghost" data-smv-picker-close><?php esc_html_e( 'Cancel', 'secure-media-vault' ); ?></button>
				<button type="button" class="smv-btn smv-btn--primary" data-smv-picker-add disabled><?php esc_html_e( 'Add selected', 'secure-media-vault' ); ?></button>
			</div>
		</footer>
	</div>
</div>
