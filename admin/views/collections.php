<?php
/**
 * Collections list view.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

$smv_collections = SMV_Collections::all();
$smv_types       = SMV_Collections::types();
$smv_new_url     = admin_url( 'admin.php?page=smv-collections&new=1' );

SMV_Admin::header(
	__( 'Collections', 'secure-media-vault' ),
	__( 'Build a gallery or playlist once, place its shortcode anywhere. Edit it here and every page updates.', 'secure-media-vault' ),
	'<a class="smv-btn smv-btn--primary" href="' . esc_url( $smv_new_url ) . '">' . SMV_Admin::icon( 'plus' ) . '<span>' . esc_html__( 'New collection', 'secure-media-vault' ) . '</span></a>'
);
?>

<?php if ( ! $smv_collections ) : ?>
	<div class="smv-card smv-empty smv-empty--hero">
		<span class="smv-empty__icon"><?php echo SMV_Admin::icon( 'grid', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<h2><?php esc_html_e( 'Create your first collection', 'secure-media-vault' ); ?></h2>
		<p><?php esc_html_e( 'A collection is a reusable set of media with its own shortcode.', 'secure-media-vault' ); ?></p>
		<div class="smv-typegrid smv-typegrid--static">
			<?php foreach ( $smv_types as $smv_key => $smv_type ) : ?>
				<a class="smv-typecard" href="<?php echo esc_url( add_query_arg( 'type', $smv_key, $smv_new_url ) ); ?>">
					<span class="smv-typecard__icon smv-typecard__icon--<?php echo esc_attr( $smv_key ); ?>"></span>
					<strong><?php echo esc_html( $smv_type['label'] ); ?></strong>
					<span><?php echo esc_html( $smv_type['desc'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
<?php else : ?>
	<div class="smv-card smv-card--flush">
		<table class="smv-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Collection', 'secure-media-vault' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'secure-media-vault' ); ?></th>
					<th scope="col" class="smv-num"><?php esc_html_e( 'Items', 'secure-media-vault' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'secure-media-vault' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'secure-media-vault' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'secure-media-vault' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $smv_collections as $smv_c ) :
					$smv_edit = admin_url( 'admin.php?page=smv-collections&edit=' . $smv_c->id );
					$smv_sc   = SMV_Collections::shortcode_for( $smv_c );
					$smv_prev = SMV_Files::get_many( array_slice( $smv_c->items, 0, 4 ) );
					?>
					<tr data-smv-collection-row="<?php echo (int) $smv_c->id; ?>">
						<td>
							<a class="smv-colname" href="<?php echo esc_url( $smv_edit ); ?>">
								<span class="smv-stack">
									<?php foreach ( array_slice( $smv_c->items, 0, 3 ) as $smv_fid ) : ?>
										<?php
										if ( isset( $smv_prev[ $smv_fid ] ) ) :
											$smv_thumb = SMV_Files::thumb_url( $smv_prev[ $smv_fid ] );
											?>
											<span class="smv-stack__item smv-stack__item--<?php echo esc_attr( $smv_prev[ $smv_fid ]->file_group ); ?>"<?php echo $smv_thumb ? ' style="background-image:url(\'' . esc_url( $smv_thumb ) . '\')"' : ''; ?>></span>
										<?php endif; ?>
									<?php endforeach; ?>
									<?php if ( ! $smv_c->items ) : ?>
										<span class="smv-stack__item smv-stack__item--empty"></span>
									<?php endif; ?>
								</span>
								<span>
									<strong><?php echo esc_html( $smv_c->title ); ?></strong>
									<span class="smv-muted">/<?php echo esc_html( $smv_c->slug ); ?></span>
								</span>
							</a>
						</td>
						<td><span class="smv-badge smv-badge--<?php echo esc_attr( $smv_c->type ); ?>"><?php echo esc_html( isset( $smv_types[ $smv_c->type ] ) ? $smv_types[ $smv_c->type ]['label'] : $smv_c->type ); ?></span></td>
						<td class="smv-num"><?php echo (int) count( $smv_c->items ); ?></td>
						<td>
							<button type="button" class="smv-code" data-smv-copy="<?php echo esc_attr( $smv_sc ); ?>" title="<?php esc_attr_e( 'Click to copy', 'secure-media-vault' ); ?>">
								<code><?php echo esc_html( $smv_sc ); ?></code><?php echo SMV_Admin::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</button>
						</td>
						<td class="smv-muted"><?php echo esc_html( human_time_diff( strtotime( $smv_c->updated_at . ' UTC' ) ) ); ?> <?php esc_html_e( 'ago', 'secure-media-vault' ); ?></td>
						<td class="smv-actions">
							<a class="smv-iconbtn" href="<?php echo esc_url( $smv_edit ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'secure-media-vault' ); ?>" title="<?php esc_attr_e( 'Edit', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
							<button type="button" class="smv-iconbtn" data-smv-dup-collection="<?php echo (int) $smv_c->id; ?>" aria-label="<?php esc_attr_e( 'Duplicate', 'secure-media-vault' ); ?>" title="<?php esc_attr_e( 'Duplicate', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<button type="button" class="smv-iconbtn smv-iconbtn--danger" data-smv-del-collection="<?php echo (int) $smv_c->id; ?>" aria-label="<?php esc_attr_e( 'Delete', 'secure-media-vault' ); ?>" title="<?php esc_attr_e( 'Delete', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<details class="smv-card smv-help-card">
		<summary><?php esc_html_e( 'Shortcode options', 'secure-media-vault' ); ?></summary>
		<p><?php esc_html_e( 'Use the id or the slug. Any display option can be overridden per page:', 'secure-media-vault' ); ?></p>
		<pre class="smv-pre">[smv_collection id="1"]
[smv_collection slug="summer-tour"]
[smv_collection id="1" columns="4" captions="no" lightbox="no"]
[smv_collection id="2" autoplay="yes" loop="yes"]</pre>
	</details>
<?php endif; ?>
