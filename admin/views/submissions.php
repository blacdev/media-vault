<?php
/**
 * Form submissions inbox + form shortcode builder.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$smv_t        = SMV_Installer::tables();
$smv_per_page = 15;
// phpcs:disable WordPress.Security.NonceVerification -- read-only params.
$smv_paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$smv_single = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0;
// phpcs:enable

// phpcs:disable WordPress.DB
if ( $smv_single ) {
	$smv_total = 1;
	$smv_subs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$smv_t['submissions']} WHERE id = %d", $smv_single ) );
} else {
	$smv_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$smv_t['submissions']}" );
	$smv_subs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$smv_t['submissions']} ORDER BY id DESC LIMIT %d OFFSET %d", $smv_per_page, ( $smv_paged - 1 ) * $smv_per_page ) );
}
// phpcs:enable

SMV_Admin::header(
	__( 'Submissions', 'secure-media-vault' ),
	__( 'Files sent through your upload forms. They are private until you move them to the library.', 'secure-media-vault' ),
	'<a class="smv-btn smv-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=smv-forms' ) ) . '">' . SMV_Admin::icon( 'edit' ) . '<span>' . esc_html__( 'Upload form builder', 'secure-media-vault' ) . '</span></a>'
);
?>

<div class="smv-inbox">
	<div>
		<?php if ( $smv_single ) : ?>
			<p><a class="smv-link" href="<?php echo esc_url( admin_url( 'admin.php?page=smv-submissions' ) ); ?>">← <?php esc_html_e( 'All submissions', 'secure-media-vault' ); ?></a></p>
		<?php endif; ?>


		<?php if ( ! $smv_subs ) : ?>
			<div class="smv-card smv-empty smv-empty--hero">
				<span class="smv-empty__icon"><?php echo SMV_Admin::icon( 'inbox', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<h2><?php esc_html_e( 'No submissions yet', 'secure-media-vault' ); ?></h2>
				<p><?php esc_html_e( 'Files people send through your upload form or your connected Contact Form 7 forms will appear here.', 'secure-media-vault' ); ?></p>
				<a class="smv-btn smv-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=smv-forms' ) ); ?>"><?php esc_html_e( 'Build an upload form', 'secure-media-vault' ); ?></a>
			</div>
		<?php endif; ?>

		<?php
		foreach ( $smv_subs as $smv_sub ) :
			list( $smv_files ) = SMV_Files::query(
				array(
					'visibility'    => '',
					'submission_id' => (int) $smv_sub->id,
					'per_page'      => 100,
				)
			);
			?>
			<article class="smv-card smv-sub<?php echo 'new' === $smv_sub->status ? ' is-new' : ''; ?>" data-smv-sub="<?php echo (int) $smv_sub->id; ?>">
				<header class="smv-sub__head">
					<div class="smv-sub__who">
						<span class="smv-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $smv_sub->name ? $smv_sub->name : ( $smv_sub->email ? $smv_sub->email : '?' ), 0, 1 ) ) ); ?></span>
						<div>
							<strong><?php echo esc_html( $smv_sub->name ? $smv_sub->name : __( 'Anonymous', 'secure-media-vault' ) ); ?></strong>
							<?php if ( 'new' === $smv_sub->status ) : ?>
								<span class="smv-badge smv-badge--new"><?php esc_html_e( 'New', 'secure-media-vault' ); ?></span>
							<?php endif; ?>
							<div class="smv-muted">
								<?php if ( $smv_sub->email ) : ?>
									<a href="mailto:<?php echo esc_attr( $smv_sub->email ); ?>"><?php echo esc_html( $smv_sub->email ); ?></a> ·
								<?php endif; ?>
								<?php echo esc_html( $smv_sub->form_label ); ?> ·
								<time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $smv_sub->created_at . ' UTC' ) ) ); ?>"><?php echo esc_html( get_date_from_gmt( $smv_sub->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></time>
							</div>
						</div>
					</div>
					<button type="button" class="smv-btn smv-btn--danger-ghost smv-btn--sm" data-smv-del-sub="<?php echo (int) $smv_sub->id; ?>"><?php echo SMV_Admin::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Delete', 'secure-media-vault' ); ?></span></button>
				</header>

				<?php
				$smv_cf = isset( $smv_sub->fields ) ? json_decode( (string) $smv_sub->fields, true ) : null;
				if ( is_array( $smv_cf ) && $smv_cf ) :
					?>
					<dl class="smv-sub__fields">
						<?php foreach ( $smv_cf as $smv_row ) : ?>
							<?php if ( is_array( $smv_row ) && isset( $smv_row['label'] ) ) : ?>
								<div>
									<dt><?php echo esc_html( $smv_row['label'] ); ?></dt>
									<dd><?php echo '' === (string) $smv_row['value'] ? '<span class="smv-muted">—</span>' : esc_html( $smv_row['value'] ); ?></dd>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>

				<?php if ( $smv_sub->message ) : ?>
					<blockquote class="smv-sub__msg"><?php echo nl2br( esc_html( $smv_sub->message ) ); ?></blockquote>
				<?php endif; ?>

				<ul class="smv-subfiles">
					<?php foreach ( $smv_files as $smv_f ) : ?>
						<li class="smv-subfile" data-smv-subfile="<?php echo (int) $smv_f->id; ?>">
							<?php if ( 'image' === $smv_f->file_group ) : ?>
								<img class="smv-subfile__thumb" src="<?php echo esc_url( SMV_Files::thumb_url( $smv_f ) ); ?>" alt="" loading="lazy">
							<?php else : ?>
								<span class="smv-subfile__thumb smv-subfile__thumb--<?php echo esc_attr( $smv_f->file_group ); ?>"><?php echo esc_html( strtoupper( $smv_f->ext ) ); ?></span>
							<?php endif; ?>
							<div class="smv-subfile__meta">
								<strong><?php echo esc_html( $smv_f->original_name ); ?></strong>
								<span class="smv-muted"><?php echo esc_html( SMV_Files::human_size( $smv_f->size ) ); ?> · <?php echo esc_html( strtoupper( $smv_f->ext ) ); ?>
									<?php if ( 'public' === $smv_f->visibility ) : ?>
										· <span class="smv-badge smv-badge--ok"><?php esc_html_e( 'In library', 'secure-media-vault' ); ?></span>
									<?php else : ?>
										· <span class="smv-badge"><?php echo SMV_Admin::icon( 'lock', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Private', 'secure-media-vault' ); ?></span>
									<?php endif; ?>
									<?php
									$smv_where = array(
										'local'   => __( 'Local folder', 'secure-media-vault' ),
										'both'    => __( 'Local + Dropbox', 'secure-media-vault' ),
										'dropbox' => __( 'Dropbox', 'secure-media-vault' ),
									);
									?>
									· <span class="smv-badge"><?php echo SMV_Admin::icon( 'local' === $smv_f->storage ? 'shield' : 'cloud', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( isset( $smv_where[ $smv_f->storage ] ) ? $smv_where[ $smv_f->storage ] : $smv_f->storage ); ?></span>
									<?php if ( 'failed' === $smv_f->sync_status ) : ?>
										· <span class="smv-badge" style="background:#fcf0f1;color:#8a2424" title="<?php echo esc_attr( $smv_f->sync_error ); ?>"><?php esc_html_e( 'Dropbox copy failed', 'secure-media-vault' ); ?></span>
										<button type="button" class="smv-link" data-smv-retry="<?php echo (int) $smv_f->id; ?>"><?php esc_html_e( 'Retry', 'secure-media-vault' ); ?></button>
									<?php endif; ?>
								</span>
								<?php if ( 'audio' === $smv_f->file_group && 'private' === $smv_f->visibility ) : ?>
									<audio controls preload="none" src="<?php echo esc_url( SMV_Download::admin_url( (int) $smv_f->id, true ) ); ?>"></audio>
								<?php endif; ?>
							</div>
							<div class="smv-subfile__actions">
								<?php if ( 'private' === $smv_f->visibility ) : ?>
									<a class="smv-btn smv-btn--ghost smv-btn--sm" href="<?php echo esc_url( SMV_Download::admin_url( (int) $smv_f->id ) ); ?>"><?php echo SMV_Admin::icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Download', 'secure-media-vault' ); ?></span></a>
									<button type="button" class="smv-btn smv-btn--secondary smv-btn--sm" data-smv-publish="<?php echo (int) $smv_f->id; ?>"><?php esc_html_e( 'Move to library', 'secure-media-vault' ); ?></button>
								<?php else : ?>
									<a class="smv-btn smv-btn--ghost smv-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=smv-library' ) ); ?>"><?php esc_html_e( 'View in library', 'secure-media-vault' ); ?></a>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
					<?php if ( ! $smv_files ) : ?>
						<li class="smv-muted"><?php esc_html_e( 'No files remain for this submission.', 'secure-media-vault' ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( $smv_sub->page_url || $smv_sub->ip ) : ?>
					<footer class="smv-sub__foot smv-muted">
						<?php if ( $smv_sub->page_url ) : ?>
							<?php esc_html_e( 'From', 'secure-media-vault' ); ?> <a href="<?php echo esc_url( $smv_sub->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( $smv_sub->page_url, PHP_URL_PATH ) ? wp_parse_url( $smv_sub->page_url, PHP_URL_PATH ) : '/' ); ?></a>
						<?php endif; ?>
						<?php if ( $smv_sub->ip ) : ?>
							· IP <?php echo esc_html( $smv_sub->ip ); ?>
						<?php endif; ?>
					</footer>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>

		<?php
		$smv_pages = (int) ceil( $smv_total / $smv_per_page );
		if ( ! $smv_single && $smv_pages > 1 ) :
			?>
			<nav class="smv-pagination">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $smv_paged,
							'total'   => $smv_pages,
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>

</div>

<?php
// Mark shown submissions as read (after rendering so the "New" badge is visible this once).
$smv_ids = wp_list_pluck( $smv_subs, 'id' );
if ( $smv_ids ) {
	$smv_ids = array_map( 'absint', $smv_ids );
	$wpdb->query( "UPDATE {$smv_t['submissions']} SET status = 'read' WHERE id IN (" . implode( ',', $smv_ids ) . ')' ); // phpcs:ignore WordPress.DB
}
