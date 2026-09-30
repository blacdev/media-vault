<?php
/**
 * Settings view.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

$smv_s         = SMV_Settings::all();
$smv_catalogue = SMV_Settings::type_catalogue();
$smv_connected = SMV_Dropbox::is_connected();
// phpcs:disable WordPress.Security.NonceVerification -- read-only params.
$smv_tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'storage';
$smv_notice = isset( $_GET['smv_notice'] ) ? sanitize_key( wp_unslash( $_GET['smv_notice'] ) ) : '';
// phpcs:enable
$smv_tabs = array(
	'storage' => __( 'Storage', 'secure-media-vault' ),
	'forms'   => __( 'Upload forms', 'secure-media-vault' ),
	'dropbox' => __( 'Dropbox', 'secure-media-vault' ),
	'status'  => __( 'Security & status', 'secure-media-vault' ),
);
$smv_tab  = isset( $smv_tabs[ $smv_tab ] ) ? $smv_tab : 'storage';

$smv_notices = array(
	'dropbox_connected'    => array( 'success', __( 'Dropbox connected. You can now choose a Dropbox storage mode.', 'secure-media-vault' ) ),
	'dropbox_disconnected' => array( 'success', __( 'Dropbox disconnected. Storage switched to your local folder.', 'secure-media-vault' ) ),
	'dropbox_denied'       => array( 'warning', __( 'Dropbox access was not granted.', 'secure-media-vault' ) ),
	'dropbox_state'        => array( 'error', __( 'The Dropbox connection request expired or was invalid. Please try again.', 'secure-media-vault' ) ),
	'dropbox_token'        => array( 'error', __( 'Dropbox did not return a token. Check the App key, App secret and redirect URI, then try again.', 'secure-media-vault' ) ),
	'dropbox_missing_keys' => array( 'error', __( 'Enter and save your Dropbox App key and App secret first.', 'secure-media-vault' ) ),
);

SMV_Admin::header( __( 'Settings', 'secure-media-vault' ), __( 'Configure storage, upload rules, forms and Dropbox.', 'secure-media-vault' ) );

if ( isset( $smv_notices[ $smv_notice ] ) ) {
	printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $smv_notices[ $smv_notice ][0] ), esc_html( $smv_notices[ $smv_notice ][1] ) );
}
settings_errors();

$smv_name = SMV_Settings::OPTION;

/**
 * Renders a grouped file-type checklist.
 */
$smv_type_checks = function ( $field, $selected ) use ( $smv_catalogue, $smv_name ) {
	foreach ( $smv_catalogue as $smv_gkey => $smv_group ) {
		echo '<div class="smv-typeset"><div class="smv-typeset__head"><strong>' . esc_html( $smv_group['label'] ) . '</strong>';
		echo '<button type="button" class="smv-link" data-smv-toggle-all="' . esc_attr( $field . '-' . $smv_gkey ) . '">' . esc_html__( 'Toggle all', 'secure-media-vault' ) . '</button></div><div class="smv-checks" data-smv-group="' . esc_attr( $field . '-' . $smv_gkey ) . '">';
		foreach ( array_keys( $smv_group['types'] ) as $smv_ext ) {
			printf(
				'<label class="smv-check"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s><span>%5$s</span></label>',
				esc_attr( $smv_name ),
				esc_attr( $field ),
				esc_attr( $smv_ext ),
				checked( in_array( $smv_ext, (array) $selected, true ), true, false ),
				esc_html( strtoupper( $smv_ext ) )
			);
		}
		echo '</div></div>';
	}
};
?>

<div class="smv-settings">
	<nav class="smv-subnav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'secure-media-vault' ); ?>">
		<?php foreach ( $smv_tabs as $smv_key => $smv_label ) : ?>
			<button type="button" role="tab" class="smv-subnav__item<?php echo $smv_tab === $smv_key ? ' is-active' : ''; ?>" aria-selected="<?php echo $smv_tab === $smv_key ? 'true' : 'false'; ?>" aria-controls="smv-panel-<?php echo esc_attr( $smv_key ); ?>" data-smv-tab="<?php echo esc_attr( $smv_key ); ?>">
				<?php
				$smv_icons = array(
					'storage' => 'shield',
					'forms'   => 'inbox',
					'dropbox' => 'cloud',
					'status'  => 'check',
				);
				echo SMV_Admin::icon( $smv_icons[ $smv_key ] ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
				<span><?php echo esc_html( $smv_label ); ?></span>
				<?php if ( 'dropbox' === $smv_key ) : ?>
					<span class="smv-dot<?php echo $smv_connected ? ' smv-dot--ok' : ''; ?>" aria-hidden="true"></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php" class="smv-settings__form" data-smv-settings-form>
		<?php settings_fields( 'smv_settings_group' ); ?>
		<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( admin_url( 'admin.php?page=smv-settings&tab=' . $smv_tab ) ); ?>" data-smv-referer>

		<!-- STORAGE -->
		<section class="smv-panel" id="smv-panel-storage" role="tabpanel" data-smv-panel="storage" <?php echo 'storage' === $smv_tab ? '' : 'hidden'; ?>>
			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Upload folder', 'secure-media-vault' ); ?></h2>
				<div class="smv-field">
					<label for="smv-folder"><?php esc_html_e( 'Folder name', 'secure-media-vault' ); ?></label>
					<div class="smv-prefixed">
						<span class="smv-prefixed__prefix">wp-content/uploads/</span>
						<input type="text" id="smv-folder" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[folder]" value="<?php echo esc_attr( $smv_s['folder'] ); ?>" pattern="[a-z0-9_\-/]+" required>
					</div>
					<p class="smv-help"><?php esc_html_e( 'Lowercase letters, numbers, dashes and slashes. The folder is created and protected automatically. Existing files stay where they are if you change this.', 'secure-media-vault' ); ?></p>
				</div>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Where files are stored', 'secure-media-vault' ); ?></h2>
				<p class="smv-help" style="margin:-6px 0 16px"><?php esc_html_e( 'Choose separately for each place files come from. Local folder is fastest (served from your own server). Local + Dropbox keeps a copy of every file in Dropbox. Dropbox only saves server space (images in galleries keep a small local preview; private submissions are downloaded straight from Dropbox).', 'secure-media-vault' ); ?></p>
				<?php
				$smv_sources = array(
					'storage_mode'      => array( __( 'Library', 'secure-media-vault' ), __( 'Files you upload yourself in Media Vault → Library.', 'secure-media-vault' ) ),
					'storage_mode_form' => array( __( 'Upload form', 'secure-media-vault' ), __( 'Files sent through the [smv_upload_form] form.', 'secure-media-vault' ) ),
					'storage_mode_cf7'  => array( __( 'Contact Form 7', 'secure-media-vault' ), __( 'Files from connected Contact Form 7 forms. Each form can override this in its Media Vault tab.', 'secure-media-vault' ) ),
				);
				$smv_modes   = array(
					'local'   => __( 'Local folder', 'secure-media-vault' ),
					'both'    => __( 'Local + Dropbox backup', 'secure-media-vault' ),
					'dropbox' => __( 'Dropbox only', 'secure-media-vault' ),
				);
				foreach ( $smv_sources as $smv_key => $smv_src ) :
					?>
					<div class="smv-storage-row">
						<div class="smv-storage-row__label">
							<strong><?php echo esc_html( $smv_src[0] ); ?></strong>
							<span class="smv-muted"><?php echo esc_html( $smv_src[1] ); ?></span>
						</div>
						<div class="smv-segment" role="radiogroup" aria-label="<?php echo esc_attr( $smv_src[0] ); ?>">
							<?php
							foreach ( $smv_modes as $smv_mode => $smv_label ) :
								$smv_disabled = 'local' !== $smv_mode && ! $smv_connected;
								?>
								<label class="smv-segment__opt<?php echo $smv_disabled ? ' is-disabled' : ''; ?>"<?php echo $smv_disabled ? ' title="' . esc_attr__( 'Connect Dropbox to enable', 'secure-media-vault' ) . '"' : ''; ?>>
									<input type="radio" name="<?php echo esc_attr( $smv_name . '[' . $smv_key . ']' ); ?>" value="<?php echo esc_attr( $smv_mode ); ?>" <?php checked( $smv_s[ $smv_key ], $smv_mode ); ?> <?php disabled( $smv_disabled ); ?>>
									<span><?php echo esc_html( $smv_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $smv_connected ) : ?>
					<p class="smv-help"><?php esc_html_e( 'The Dropbox options become available once Dropbox is connected (Dropbox tab).', 'secure-media-vault' ); ?></p>
				<?php endif; ?>
				<p class="smv-help"><?php esc_html_e( 'Changes apply to new files. Files already stored stay where they are.', 'secure-media-vault' ); ?></p>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Storage limit', 'secure-media-vault' ); ?></h2>
				<?php $smv_q = SMV_Storage::quota_status(); ?>
				<div class="smv-field">
					<label for="smv-quota"><?php esc_html_e( 'Maximum total storage for all uploads', 'secure-media-vault' ); ?></label>
					<div class="smv-suffixed">
						<input type="number" id="smv-quota" class="smv-input smv-input--num" name="<?php echo esc_attr( $smv_name ); ?>[quota_gb]" value="<?php echo esc_attr( (float) $smv_s['quota_gb'] ); ?>" min="0" max="100000" step="0.5">
						<span>GB</span>
					</div>
					<p class="smv-help">
						<?php esc_html_e( 'Counts every file in Media Vault from anyone — your Library uploads, the upload form and Contact Form 7 — including private submissions and files kept on Dropbox. When the limit is reached, new uploads are refused and the upload form shows a “not accepting files” message. Deleting files frees space immediately. Use 0 for no limit.', 'secure-media-vault' ); ?>
					</p>
					<p class="smv-help">
						<?php
						if ( $smv_q['limit'] ) {
							/* translators: 1: used, 2: limit, 3: percent */
							printf( esc_html__( 'Currently using %1$s of %2$s (%3$s%%).', 'secure-media-vault' ), '<strong>' . esc_html( $smv_q['used'] ? size_format( $smv_q['used'], 1 ) : '0 B' ) . '</strong>', esc_html( size_format( $smv_q['limit'] ) ), esc_html( number_format_i18n( $smv_q['percent'], 1 ) ) );
						} else {
							/* translators: %s: used */
							printf( esc_html__( 'Currently using %s. No limit is set.', 'secure-media-vault' ), '<strong>' . esc_html( $smv_q['used'] ? size_format( $smv_q['used'], 1 ) : '0 B' ) . '</strong>' );
						}
						?>
					</p>
				</div>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Library upload rules', 'secure-media-vault' ); ?></h2>
				<div class="smv-field">
					<label for="smv-max"><?php esc_html_e( 'Maximum file size', 'secure-media-vault' ); ?></label>
					<div class="smv-suffixed">
						<input type="number" id="smv-max" class="smv-input smv-input--num" name="<?php echo esc_attr( $smv_name ); ?>[max_size_mb]" value="<?php echo (int) $smv_s['max_size_mb']; ?>" min="1" max="10240">
						<span>MB</span>
					</div>
					<p class="smv-help">
						<?php
						/* translators: %s: server upload limit */
						printf( esc_html__( 'Your server currently allows up to %s per upload (set by PHP upload_max_filesize / post_max_size).', 'secure-media-vault' ), '<strong>' . esc_html( size_format( wp_max_upload_size() ) ) . '</strong>' );
						?>
					</p>
				</div>
				<fieldset class="smv-field">
					<legend><?php esc_html_e( 'Allowed file types', 'secure-media-vault' ); ?></legend>
					<?php $smv_type_checks( 'allowed_types', $smv_s['allowed_types'] ); ?>
					<p class="smv-help"><?php esc_html_e( 'Scriptable formats (PHP, HTML, SVG, JS, executables) are never accepted. Every file’s contents are checked against its extension.', 'secure-media-vault' ); ?></p>
				</fieldset>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Theme compatibility', 'secure-media-vault' ); ?></h2>
				<?php if ( SMV_Shortcodes::ajax_theme_detected() ) : ?>
					<div class="smv-alert smv-alert--success" style="margin:0 0 14px">
						<?php
						/* translators: %s: theme name */
						printf( esc_html__( 'Detected %s, which loads pages with AJAX to keep its player running. With “Automatic”, each gallery, playlist and form brings its own style and script along, so it works after AJAX page changes — and pages that don’t use Media Vault stay completely untouched.', 'secure-media-vault' ), '<strong>' . esc_html( wp_get_theme()->get( 'Name' ) ) . '</strong>' );
						?>
					</div>
				<?php endif; ?>
				<fieldset class="smv-field">
					<legend><?php esc_html_e( 'Load gallery, player and form styles/scripts', 'secure-media-vault' ); ?></legend>
					<?php
					$smv_asset_modes = array(
						'auto'   => __( 'Automatic (recommended) — only on pages that use a Media Vault shortcode. Nothing is added to the rest of your site.', 'secure-media-vault' ),
						'always' => __( 'On every page — only if a gallery or form doesn’t work after clicking between pages. Check your site’s layout after switching.', 'secure-media-vault' ),
					);
					foreach ( $smv_asset_modes as $smv_key => $smv_label ) :
						?>
						<label class="smv-inline-check" style="margin:6px 0">
							<input type="radio" name="<?php echo esc_attr( $smv_name ); ?>[frontend_assets]" value="<?php echo esc_attr( $smv_key ); ?>" <?php checked( $smv_s['frontend_assets'], $smv_key ); ?>>
							<span><?php echo esc_html( $smv_label ); ?></span>
						</label>
					<?php endforeach; ?>
					<p class="smv-help"><?php esc_html_e( 'If your site uses a speed/optimisation plugin that combines CSS or JavaScript, clear its cache after changing this.', 'secure-media-vault' ); ?></p>
				</fieldset>
				<label class="smv-switch">
					<input type="checkbox" name="<?php echo esc_attr( $smv_name ); ?>[pause_other_media]" value="1" <?php checked( $smv_s['pause_other_media'] ); ?>>
					<span class="smv-switch__track" aria-hidden="true"></span>
					<span class="smv-switch__label"><?php esc_html_e( 'One sound at a time: pause the site’s other audio (e.g. the theme’s radio player) when a Media Vault player starts, and vice versa', 'secure-media-vault' ); ?></span>
				</label>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Uninstall', 'secure-media-vault' ); ?></h2>
				<label class="smv-switch">
					<input type="checkbox" name="<?php echo esc_attr( $smv_name ); ?>[delete_on_uninstall]" value="1" <?php checked( $smv_s['delete_on_uninstall'] ); ?>>
					<span class="smv-switch__track" aria-hidden="true"></span>
					<span class="smv-switch__label"><?php esc_html_e( 'Remove settings, collections and file records when the plugin is deleted', 'secure-media-vault' ); ?></span>
				</label>
				<p class="smv-help"><?php esc_html_e( 'Your actual files in the upload folder and on Dropbox are never deleted by uninstalling.', 'secure-media-vault' ); ?></p>
			</div>
		</section>

		<!-- FORMS -->
		<section class="smv-panel" id="smv-panel-forms" role="tabpanel" data-smv-panel="forms" <?php echo 'forms' === $smv_tab ? '' : 'hidden'; ?>>
			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Who can upload', 'secure-media-vault' ); ?></h2>
				<div class="smv-radiocards smv-radiocards--2">
					<label class="smv-radiocard">
						<input type="radio" name="<?php echo esc_attr( $smv_name ); ?>[form_access]" value="logged_in" <?php checked( $smv_s['form_access'], 'logged_in' ); ?>>
						<span class="smv-radiocard__icon"><?php echo SMV_Admin::icon( 'lock', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<strong><?php esc_html_e( 'Logged-in users only', 'secure-media-vault' ); ?></strong>
						<span><?php esc_html_e( 'Recommended. Visitors see a login button instead of the form.', 'secure-media-vault' ); ?></span>
					</label>
					<label class="smv-radiocard">
						<input type="radio" name="<?php echo esc_attr( $smv_name ); ?>[form_access]" value="anyone" <?php checked( $smv_s['form_access'], 'anyone' ); ?>>
						<span class="smv-radiocard__icon"><?php echo SMV_Admin::icon( 'inbox', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<strong><?php esc_html_e( 'Anyone', 'secure-media-vault' ); ?></strong>
						<span><?php esc_html_e( 'Protected by honeypot, timing checks and rate limiting.', 'secure-media-vault' ); ?></span>
					</label>
				</div>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Limits', 'secure-media-vault' ); ?></h2>
				<div class="smv-grid3">
					<div class="smv-field">
						<label for="smv-fmax"><?php esc_html_e( 'Files per upload', 'secure-media-vault' ); ?></label>
						<input type="number" id="smv-fmax" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[form_max_files]" value="<?php echo (int) $smv_s['form_max_files']; ?>" min="1" max="50">
					</div>
					<div class="smv-field">
						<label for="smv-fsize"><?php esc_html_e( 'Max size per file (MB)', 'secure-media-vault' ); ?></label>
						<input type="number" id="smv-fsize" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[form_max_size_mb]" value="<?php echo (int) $smv_s['form_max_size_mb']; ?>" min="1" max="2048">
					</div>
					<div class="smv-field">
						<label for="smv-frate"><?php esc_html_e( 'Uploads per hour, per visitor', 'secure-media-vault' ); ?></label>
						<input type="number" id="smv-frate" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[form_rate_limit]" value="<?php echo (int) $smv_s['form_rate_limit']; ?>" min="1" max="1000">
					</div>
				</div>
				<fieldset class="smv-field">
					<legend><?php esc_html_e( 'File types accepted by forms', 'secure-media-vault' ); ?></legend>
					<?php $smv_type_checks( 'form_allowed_types', $smv_s['form_allowed_types'] ); ?>
				</fieldset>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'After upload', 'secure-media-vault' ); ?></h2>
				<div class="smv-field">
					<label for="smv-fmsg"><?php esc_html_e( 'Success message', 'secure-media-vault' ); ?></label>
					<input type="text" id="smv-fmsg" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[form_success_message]" value="<?php echo esc_attr( $smv_s['form_success_message'] ); ?>">
				</div>
				<label class="smv-switch">
					<input type="checkbox" name="<?php echo esc_attr( $smv_name ); ?>[form_notify]" value="1" <?php checked( $smv_s['form_notify'] ); ?>>
					<span class="smv-switch__track" aria-hidden="true"></span>
					<span class="smv-switch__label"><?php esc_html_e( 'Email me when someone uploads', 'secure-media-vault' ); ?></span>
				</label>
				<div class="smv-field">
					<label for="smv-femail"><?php esc_html_e( 'Notification email', 'secure-media-vault' ); ?></label>
					<input type="email" id="smv-femail" class="smv-input" name="<?php echo esc_attr( $smv_name ); ?>[form_notify_email]" value="<?php echo esc_attr( $smv_s['form_notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				</div>
			</div>
			<div class="smv-card" id="smv-cf7">
				<h2 class="smv-card__title"><?php esc_html_e( 'Contact Form 7', 'secure-media-vault' ); ?></h2>
				<?php if ( ! SMV_CF7::active() ) : ?>
					<p class="smv-muted"><?php esc_html_e( 'Contact Form 7 is not active on this site. When it is, files sent through its forms can be saved here automatically.', 'secure-media-vault' ); ?></p>
				<?php else : ?>
					<?php $smv_cf7_forms = SMV_CF7::forms(); ?>
					<p class="smv-help" style="margin:-6px 0 14px"><?php esc_html_e( 'Choose per form: open a form in Contact Form 7 and use its “Media Vault” tab. Forms you switch on save their files privately here with all the answers, and their email carries a link to the files instead of the attachments. All other forms stay exactly as they are.', 'secure-media-vault' ); ?></p>
					<fieldset class="smv-field">
						<legend><?php esc_html_e( 'Integration', 'secure-media-vault' ); ?></legend>
						<label class="smv-inline-check" style="margin:6px 0">
							<input type="radio" name="<?php echo esc_attr( $smv_name ); ?>[cf7_mode]" value="per_form" <?php checked( 'off' !== $smv_s['cf7_mode'] ); ?>>
							<span><?php esc_html_e( 'On — each form decides in its own “Media Vault” tab (off by default for every form)', 'secure-media-vault' ); ?></span>
						</label>
						<label class="smv-inline-check" style="margin:6px 0">
							<input type="radio" name="<?php echo esc_attr( $smv_name ); ?>[cf7_mode]" value="off" <?php checked( 'off', $smv_s['cf7_mode'] ); ?>>
							<span><?php esc_html_e( 'Off for all forms', 'secure-media-vault' ); ?></span>
						</label>
					</fieldset>
					<div class="smv-field">
						<span style="display:block;font-weight:500;margin-bottom:6px"><?php esc_html_e( 'Your forms', 'secure-media-vault' ); ?></span>
						<?php if ( ! $smv_cf7_forms ) : ?>
							<p class="smv-muted"><?php esc_html_e( 'No Contact Form 7 forms yet.', 'secure-media-vault' ); ?></p>
						<?php else : ?>
							<table class="smv-reftable">
								<tbody>
									<?php
									foreach ( $smv_cf7_forms as $smv_fid => $smv_ftitle ) :
										$smv_fopts = SMV_CF7::form_settings( $smv_fid );
										?>
										<tr>
											<th scope="row" style="width:auto;white-space:normal"><?php echo esc_html( $smv_ftitle ); ?></th>
											<td>
												<?php if ( $smv_fopts['enabled'] ) : ?>
													<span class="smv-badge smv-badge--ok"><?php esc_html_e( 'Saves files to Media Vault', 'secure-media-vault' ); ?></span>
													<span class="smv-muted"><?php echo $smv_fopts['attach'] ? esc_html__( '· files also attached to the email', 'secure-media-vault' ) : esc_html__( '· email sends a link instead of files', 'secure-media-vault' ); ?></span>
												<?php else : ?>
													<span class="smv-badge"><?php esc_html_e( 'Not connected — works as usual', 'secure-media-vault' ); ?></span>
												<?php endif; ?>
											</td>
											<td class="smv-actions"><a class="smv-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcf7&post=' . (int) $smv_fid . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit form → Media Vault tab', 'secure-media-vault' ); ?></a></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
					<details class="smv-nginx">
						<summary><?php esc_html_e( 'Extra tags you can use in Contact Form 7', 'secure-media-vault' ); ?></summary>
						<table class="smv-reftable">
							<tbody>
								<tr><th scope="row"><code>[mediavault-link]</code></th><td><?php esc_html_e( 'Optional: place it in the Mail tab where you want the link to the files. Without it, the link is added at the end of the email automatically (you can turn that off in the form’s Media Vault tab).', 'secure-media-vault' ); ?></td></tr>
								<tr><th scope="row"><code>[mediavault collection:3]</code></th><td><?php esc_html_e( 'Put this in the Form tab to show a gallery or playlist inside the form. Use the collection id or its slug, e.g. collection:summer-tour.', 'secure-media-vault' ); ?></td></tr>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			</div>
		</section>

		<!-- DROPBOX -->
		<section class="smv-panel" id="smv-panel-dropbox" role="tabpanel" data-smv-panel="dropbox" <?php echo 'dropbox' === $smv_tab ? '' : 'hidden'; ?>>
			<div class="smv-card smv-connect<?php echo $smv_connected ? ' is-connected' : ''; ?>">
				<div class="smv-connect__status">
					<span class="smv-connect__logo" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="28" height="28"><path fill="currentColor" d="m6 2 6 3.8L6 9.6 0 5.8 6 2Zm12 0 6 3.8-6 3.8-6-3.8L18 2ZM0 13.4l6-3.8 6 3.8-6 3.8-6-3.8Zm18-3.8 6 3.8-6 3.8-6-3.8 6-3.8ZM6 18.4l6-3.8 6 3.8-6 3.8-6-3.8Z"/></svg>
					</span>
					<div>
						<strong>
							<?php echo $smv_connected ? esc_html__( 'Connected to Dropbox', 'secure-media-vault' ) : esc_html__( 'Dropbox is not connected', 'secure-media-vault' ); ?>
						</strong>
						<div class="smv-muted">
							<?php
							if ( $smv_connected ) {
								echo esc_html( SMV_Dropbox::account_label() );
							} elseif ( SMV_Dropbox::is_configured() ) {
								esc_html_e( 'App credentials saved. Click Connect to authorise.', 'secure-media-vault' );
							} else {
								esc_html_e( 'Follow the three steps below.', 'secure-media-vault' );
							}
							?>
						</div>
					</div>
				</div>
				<div class="smv-connect__actions">
					<?php if ( $smv_connected ) : ?>
						<button type="button" class="smv-btn smv-btn--ghost" data-smv-dropbox-test><?php esc_html_e( 'Test connection', 'secure-media-vault' ); ?></button>
						<a class="smv-btn smv-btn--danger-ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smv_dropbox_disconnect' ), 'smv_dropbox_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'secure-media-vault' ); ?></a>
					<?php elseif ( SMV_Dropbox::is_configured() ) : ?>
						<a class="smv-btn smv-btn--primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smv_dropbox_connect' ), 'smv_dropbox_connect' ) ); ?>"><?php esc_html_e( 'Connect Dropbox', 'secure-media-vault' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="smv-connect__result" data-smv-dropbox-result aria-live="polite"></div>
			</div>

			<div class="smv-card">
				<ol class="smv-steps">
					<li>
						<h3><?php esc_html_e( 'Create a Dropbox app', 'secure-media-vault' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: %s: link to Dropbox App Console */
								esc_html__( 'Open the %s, choose “Scoped access” and “App folder” (recommended) or “Full Dropbox”.', 'secure-media-vault' ),
								'<a href="https://www.dropbox.com/developers/apps/create" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Dropbox App Console', 'secure-media-vault' ) . '</a>'
							);
							?>
							<?php esc_html_e( 'On the Permissions tab enable:', 'secure-media-vault' ); ?>
							<code>files.content.write</code> <code>files.content.read</code> <code>sharing.write</code> <code>sharing.read</code> <code>account_info.read</code>
						</p>
					</li>
					<li>
						<h3><?php esc_html_e( 'Add this redirect URI', 'secure-media-vault' ); ?></h3>
						<p><?php esc_html_e( 'On the Settings tab of your app, under OAuth 2 → Redirect URIs, add:', 'secure-media-vault' ); ?></p>
						<button type="button" class="smv-code smv-code--block" data-smv-copy="<?php echo esc_attr( SMV_Dropbox::redirect_uri() ); ?>"><code><?php echo esc_html( SMV_Dropbox::redirect_uri() ); ?></code><?php echo SMV_Admin::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					</li>
					<li>
						<h3><?php esc_html_e( 'Enter your app credentials', 'secure-media-vault' ); ?></h3>
						<div class="smv-grid2">
							<div class="smv-field">
								<label for="smv-dbkey"><?php esc_html_e( 'App key', 'secure-media-vault' ); ?></label>
								<input type="text" id="smv-dbkey" class="smv-input smv-input--mono" name="<?php echo esc_attr( $smv_name ); ?>[dropbox_app_key]" value="<?php echo esc_attr( $smv_s['dropbox_app_key'] ); ?>" autocomplete="off" spellcheck="false">
							</div>
							<div class="smv-field">
								<label for="smv-dbsecret"><?php esc_html_e( 'App secret', 'secure-media-vault' ); ?></label>
								<input type="password" id="smv-dbsecret" class="smv-input smv-input--mono" name="<?php echo esc_attr( $smv_name ); ?>[dropbox_app_secret]" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo SMV_Settings::has_secret( 'app_secret' ) ? esc_attr__( '•••••••••• saved (encrypted)', 'secure-media-vault' ) : ''; ?>">
								<?php if ( SMV_Settings::has_secret( 'app_secret' ) ) : ?>
									<label class="smv-muted smv-inline-check"><input type="checkbox" name="<?php echo esc_attr( $smv_name ); ?>[dropbox_forget_secret]" value="1"> <?php esc_html_e( 'Forget saved secret and disconnect', 'secure-media-vault' ); ?></label>
								<?php endif; ?>
							</div>
						</div>
						<div class="smv-field">
							<label for="smv-dbfolder"><?php esc_html_e( 'Dropbox folder', 'secure-media-vault' ); ?></label>
							<input type="text" id="smv-dbfolder" class="smv-input smv-input--mono" name="<?php echo esc_attr( $smv_name ); ?>[dropbox_folder]" value="<?php echo esc_attr( $smv_s['dropbox_folder'] ); ?>">
							<p class="smv-help"><?php esc_html_e( 'Files go into /media and /private sub-folders here. With an “App folder” app this path is inside Apps/<your app name>.', 'secure-media-vault' ); ?></p>
						</div>
						<p class="smv-help"><?php esc_html_e( 'Save settings, then click “Connect Dropbox” above. Secrets and tokens are encrypted before being stored.', 'secure-media-vault' ); ?></p>
					</li>
				</ol>
			</div>
		</section>

		<!-- STATUS -->
		<section class="smv-panel" id="smv-panel-status" role="tabpanel" data-smv-panel="status" <?php echo 'status' === $smv_tab ? '' : 'hidden'; ?>>
			<div class="smv-card">
				<div class="smv-card__head">
					<h2 class="smv-card__title"><?php esc_html_e( 'Folder protection check', 'secure-media-vault' ); ?></h2>
					<button type="button" class="smv-btn smv-btn--secondary" data-smv-health><?php esc_html_e( 'Run check', 'secure-media-vault' ); ?></button>
				</div>
				<div data-smv-health-result aria-live="polite" class="smv-muted"><?php esc_html_e( 'Verifies that private submissions cannot be opened directly from the web.', 'secure-media-vault' ); ?></div>
				<details class="smv-nginx">
					<summary><?php esc_html_e( 'Using nginx? Add this to your server block', 'secure-media-vault' ); ?></summary>
					<pre class="smv-pre">location ~* ^/wp-content/uploads/<?php echo esc_html( $smv_s['folder'] ); ?>/private/ { deny all; return 404; }
location ~* ^/wp-content/uploads/<?php echo esc_html( $smv_s['folder'] ); ?>/.*\.(php|phtml|phar|html?|svg|js)$ { deny all; return 404; }</pre>
				</details>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'How your files are protected', 'secure-media-vault' ); ?></h2>
				<ul class="smv-checklist">
					<li><?php esc_html_e( 'Only administrators can upload to the library, manage collections or read submissions (capability + nonce on every request).', 'secure-media-vault' ); ?></li>
					<li><?php esc_html_e( 'Strict allow-list of file types; contents are sniffed so a renamed script is rejected. SVG, HTML, PHP and executables are never allowed.', 'secure-media-vault' ); ?></li>
					<li><?php esc_html_e( 'Uploaded files are renamed; the folder blocks script execution and directory listings.', 'secure-media-vault' ); ?></li>
					<li><?php esc_html_e( 'Form submissions are stored privately with random names and are only downloadable by administrators through an authenticated link.', 'secure-media-vault' ); ?></li>
					<li><?php esc_html_e( 'Public forms use a signed configuration, honeypot, timing check and per-visitor rate limit.', 'secure-media-vault' ); ?></li>
					<li><?php esc_html_e( 'Dropbox uses OAuth 2 with PKCE; the app secret and tokens are encrypted with AES-256-GCM.', 'secure-media-vault' ); ?></li>
				</ul>
				<?php if ( ! SMV_Crypto::available() ) : ?>
					<div class="smv-alert smv-alert--warning"><?php esc_html_e( 'The OpenSSL extension is missing on this server, so secrets are only obfuscated. Ask your host to enable OpenSSL.', 'secure-media-vault' ); ?></div>
				<?php endif; ?>
				<p class="smv-help">
					<?php
					/* translators: %s: constant name */
					printf( esc_html__( 'Tip: define %s in wp-config.php to use your own encryption key instead of the WordPress salts.', 'secure-media-vault' ), '<code>SMV_ENCRYPTION_KEY</code>' );
					?>
				</p>
			</div>

			<div class="smv-card">
				<h2 class="smv-card__title"><?php esc_html_e( 'Environment', 'secure-media-vault' ); ?></h2>
				<dl class="smv-meta smv-meta--wide">
					<dt><?php esc_html_e( 'Upload folder', 'secure-media-vault' ); ?></dt>
					<dd><code><?php echo esc_html( SMV_Storage::base_dir() ); ?></code></dd>
					<dt><?php esc_html_e( 'Writable', 'secure-media-vault' ); ?></dt>
					<dd><?php echo wp_is_writable( SMV_Storage::base_dir() ) ? esc_html__( 'Yes', 'secure-media-vault' ) : '<strong class="smv-danger">' . esc_html__( 'No — fix folder permissions', 'secure-media-vault' ) . '</strong>'; ?></dd>
					<dt><?php esc_html_e( 'Server upload limit', 'secure-media-vault' ); ?></dt>
					<dd><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></dd>
					<dt><?php esc_html_e( 'Plugin version', 'secure-media-vault' ); ?></dt>
					<dd><?php echo esc_html( SMV_VERSION ); ?></dd>
				</dl>
			</div>
		</section>

		<div class="smv-settings__save" data-smv-save-row <?php echo 'status' === $smv_tab ? 'hidden' : ''; ?>>
			<?php submit_button( __( 'Save settings', 'secure-media-vault' ), 'smv-btn smv-btn--primary', 'submit', false ); ?>
		</div>
	</form>
</div>
