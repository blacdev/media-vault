<?php
/**
 * Upload form builder + how-to guide.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

$smv_access = SMV_Settings::get( 'form_access' );

SMV_Admin::header(
	__( 'Upload form', 'secure-media-vault' ),
	__( 'Build a secure upload form, copy its shortcode and paste it into any page.', 'secure-media-vault' )
);
?>

<div class="smv-columns smv-columns--builder">
	<div class="smv-columns__main">
		<div class="smv-card" data-smv-form-builder>
			<h2 class="smv-card__title"><?php esc_html_e( 'Upload form builder', 'secure-media-vault' ); ?></h2>
			<p class="smv-help">
				<?php
				echo 'anyone' === $smv_access
					? esc_html__( 'Forms currently accept uploads from anyone (with spam protection).', 'secure-media-vault' )
					: esc_html__( 'Forms currently accept uploads from logged-in users only.', 'secure-media-vault' );
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-settings&tab=forms' ) ); ?>"><?php esc_html_e( 'Change', 'secure-media-vault' ); ?></a>
			</p>

			<div class="smv-field">
				<label for="smv-fb-label"><?php esc_html_e( 'Internal label', 'secure-media-vault' ); ?></label>
				<input type="text" id="smv-fb-label" class="smv-input" data-fb="label" placeholder="<?php esc_attr_e( 'e.g. Demo submissions', 'secure-media-vault' ); ?>">
			</div>
			<div class="smv-field">
				<label for="smv-fb-title"><?php esc_html_e( 'Heading shown to visitors', 'secure-media-vault' ); ?></label>
				<input type="text" id="smv-fb-title" class="smv-input" data-fb="title">
			</div>
			<fieldset class="smv-field">
				<legend><?php esc_html_e( 'Fields', 'secure-media-vault' ); ?></legend>
				<?php
				foreach ( array(
					'name'    => __( 'Name', 'secure-media-vault' ),
					'email'   => __( 'Email', 'secure-media-vault' ),
					'message' => __( 'Message', 'secure-media-vault' ),
				) as $smv_k => $smv_l ) :
					?>
					<div class="smv-fbrow">
						<label><input type="checkbox" data-fb-field="<?php echo esc_attr( $smv_k ); ?>" checked> <?php echo esc_html( $smv_l ); ?></label>
						<label class="smv-muted"><input type="checkbox" data-fb-req="<?php echo esc_attr( $smv_k ); ?>" <?php checked( 'message' !== $smv_k ); ?>> <?php esc_html_e( 'required', 'secure-media-vault' ); ?></label>
					</div>
				<?php endforeach; ?>
			</fieldset>
			<fieldset class="smv-field">
				<legend><?php esc_html_e( 'Accepted types', 'secure-media-vault' ); ?></legend>
				<div class="smv-checks">
					<?php foreach ( (array) SMV_Settings::get( 'form_allowed_types' ) as $smv_ext ) : ?>
						<label><input type="checkbox" data-fb-type="<?php echo esc_attr( $smv_ext ); ?>" checked> <?php echo esc_html( strtoupper( $smv_ext ) ); ?></label>
					<?php endforeach; ?>
				</div>
				<p class="smv-help"><?php esc_html_e( 'Limited to the types enabled for forms in Settings.', 'secure-media-vault' ); ?></p>
			</fieldset>
			<fieldset class="smv-field">
				<legend><?php esc_html_e( 'Your own fields', 'secure-media-vault' ); ?></legend>
				<ul class="smv-cf-list" data-fb-custom></ul>
				<button type="button" class="smv-btn smv-btn--ghost smv-btn--sm" data-fb-add><?php echo SMV_Admin::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Add field', 'secure-media-vault' ); ?></span></button>
				<p class="smv-help"><?php esc_html_e( 'Phone, company, a dropdown, a consent tick box — as many as you need.', 'secure-media-vault' ); ?></p>
				<template data-fb-row>
					<li class="smv-cf">
						<div class="smv-cf__top">
							<input type="text" class="smv-input" data-cf="label" maxlength="120" placeholder="<?php esc_attr_e( 'Label, e.g. Phone number', 'secure-media-vault' ); ?>" aria-label="<?php esc_attr_e( 'Field label', 'secure-media-vault' ); ?>">
							<button type="button" class="smv-iconbtn smv-iconbtn--danger" data-cf-remove aria-label="<?php esc_attr_e( 'Remove field', 'secure-media-vault' ); ?>"><?php echo SMV_Admin::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						</div>
						<select class="smv-input" data-cf="type" aria-label="<?php esc_attr_e( 'Field type', 'secure-media-vault' ); ?>">
							<option value="text"><?php esc_html_e( 'Short text', 'secure-media-vault' ); ?></option>
							<option value="textarea"><?php esc_html_e( 'Long text', 'secure-media-vault' ); ?></option>
							<option value="email"><?php esc_html_e( 'Email', 'secure-media-vault' ); ?></option>
							<option value="tel"><?php esc_html_e( 'Phone', 'secure-media-vault' ); ?></option>
							<option value="url"><?php esc_html_e( 'Website / link', 'secure-media-vault' ); ?></option>
							<option value="number"><?php esc_html_e( 'Number', 'secure-media-vault' ); ?></option>
							<option value="date"><?php esc_html_e( 'Date', 'secure-media-vault' ); ?></option>
							<option value="select"><?php esc_html_e( 'Dropdown (pick one)', 'secure-media-vault' ); ?></option>
							<option value="radio"><?php esc_html_e( 'Buttons (pick one)', 'secure-media-vault' ); ?></option>
							<option value="checkbox"><?php esc_html_e( 'Checkboxes / tick box', 'secure-media-vault' ); ?></option>
						</select>
						<input type="text" class="smv-input" data-cf="options" placeholder="<?php esc_attr_e( 'Choices, separated by commas', 'secure-media-vault' ); ?>" aria-label="<?php esc_attr_e( 'Choices', 'secure-media-vault' ); ?>" hidden>
						<p class="smv-help" data-cf-hint hidden><?php esc_html_e( 'Leave choices empty for a single tick box (e.g. “I agree to the terms”).', 'secure-media-vault' ); ?></p>
						<div class="smv-cf__flags">
							<label><input type="checkbox" data-cf="required"> <?php esc_html_e( 'Required', 'secure-media-vault' ); ?></label>
							<label><input type="checkbox" data-cf="half"> <?php esc_html_e( 'Half width', 'secure-media-vault' ); ?></label>
						</div>
					</li>
				</template>
			</fieldset>
			<div class="smv-field">
				<label for="smv-fb-max"><?php esc_html_e( 'Max files per upload', 'secure-media-vault' ); ?></label>
				<input type="number" id="smv-fb-max" class="smv-input" data-fb="max_files" min="1" max="<?php echo (int) SMV_Settings::get( 'form_max_files' ); ?>" value="<?php echo (int) SMV_Settings::get( 'form_max_files' ); ?>">
			</div>
			<div class="smv-field">
				<label for="smv-fb-button"><?php esc_html_e( 'Button text', 'secure-media-vault' ); ?></label>
				<input type="text" id="smv-fb-button" class="smv-input" data-fb="button" placeholder="<?php esc_attr_e( 'Upload files', 'secure-media-vault' ); ?>">
			</div>

			<div class="smv-shortcode">
				<span class="smv-shortcode__label"><?php esc_html_e( 'Your shortcode', 'secure-media-vault' ); ?></span>
				<button type="button" class="smv-code smv-code--block" data-smv-copy="[smv_upload_form]" data-fb-output><code>[smv_upload_form]</code><?php echo SMV_Admin::icon( 'copy', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</div>
	</div>

	<aside class="smv-columns__side">
		<div class="smv-card">
			<h2 class="smv-card__title"><?php esc_html_e( 'How to add it to your site', 'secure-media-vault' ); ?></h2>
			<ol class="smv-steps">
				<li>
					<h3><?php esc_html_e( 'Build your form', 'secure-media-vault' ); ?></h3>
					<p><?php esc_html_e( 'Use the builder on this page. Pick the standard fields (name, email, message), the file types you accept and how many files per upload. Click “Add field” to ask anything else — phone number, company, a dropdown, a consent checkbox…', 'secure-media-vault' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Copy the shortcode', 'secure-media-vault' ); ?></h3>
					<p><?php esc_html_e( 'The grey box at the bottom of the builder updates as you go. Click it to copy.', 'secure-media-vault' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Paste it into a page', 'secure-media-vault' ); ?></h3>
					<p><?php esc_html_e( 'Edit any page or post. In the block editor, add a “Shortcode” block (type /shortcode), paste, then Publish or Update. In the classic editor, or a page builder’s shortcode/text widget, just paste it where the form should appear.', 'secure-media-vault' ); ?></p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Choose who can use it', 'secure-media-vault' ); ?></h3>
					<p>
						<?php
						echo 'anyone' === $smv_access
							? esc_html__( 'Right now anyone can upload (protected by spam checks and a per-visitor hourly limit).', 'secure-media-vault' )
							: esc_html__( 'Right now only logged-in users can upload; visitors see a “Log in” button instead of the form.', 'secure-media-vault' );
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-settings&tab=forms' ) ); ?>"><?php esc_html_e( 'Change in Settings → Upload forms', 'secure-media-vault' ); ?></a>
						<?php esc_html_e( '(also: size limit, success message and email notifications).', 'secure-media-vault' ); ?>
					</p>
				</li>
				<li>
					<h3><?php esc_html_e( 'Receive the files', 'secure-media-vault' ); ?></h3>
					<p>
						<?php esc_html_e( 'Every upload appears under Submissions with the answers to your fields. Files stay private: click Download to get a copy, or “Move to library” to use a file in a gallery or playlist.', 'secure-media-vault' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-submissions' ) ); ?>"><?php esc_html_e( 'Open Submissions', 'secure-media-vault' ); ?></a>
					</p>
				</li>
			</ol>
		</div>
	</aside>
</div>

<div class="smv-card smv-guide-ref">
			<h3 class="smv-guide__h" style="margin-top:0"><?php esc_html_e( 'Adding your own fields by hand', 'secure-media-vault' ); ?></h3>
			<p class="smv-muted"><?php esc_html_e( 'The builder writes this for you, but you can also type it. Add a custom attribute with fields separated by commas. Each field is: Label, then optional parts separated by colons.', 'secure-media-vault' ); ?></p>
			<pre class="smv-pre">[smv_upload_form custom="Phone:tel:required:half, Company:half, Genre:select(Rock|Pop|Jazz):required, I agree to the terms:checkbox:required"]</pre>
			<table class="smv-reftable">
				<tbody>
					<tr><th scope="row"><code>Label</code></th><td><?php esc_html_e( 'What the visitor sees. Required, always first. Avoid commas, colons, brackets and |.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>:type</code></th><td><code>text</code> (<?php esc_html_e( 'default', 'secure-media-vault' ); ?>) · <code>textarea</code> · <code>email</code> · <code>tel</code> · <code>url</code> · <code>number</code> · <code>date</code> · <code>select</code> · <code>radio</code> · <code>checkbox</code></td></tr>
					<tr><th scope="row"><code>(A|B|C)</code></th><td><?php esc_html_e( 'Choices for select (dropdown), radio (pick one) and checkbox (pick several). A checkbox without choices is a single tick box, e.g. consent.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>:required</code></th><td><?php esc_html_e( 'Visitor must fill it in.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>:half</code></th><td><?php esc_html_e( 'Half width, so two half fields sit side by side (they stack on phones).', 'secure-media-vault' ); ?></td></tr>
				</tbody>
			</table>

			<h3 class="smv-guide__h"><?php esc_html_e( 'All shortcode options', 'secure-media-vault' ); ?></h3>
			<table class="smv-reftable">
				<tbody>
					<tr><th scope="row"><code>label</code></th><td><?php esc_html_e( 'Internal name shown on this page, so you can tell forms apart. Visitors never see it.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>title</code>, <code>description</code></th><td><?php esc_html_e( 'Heading and intro text above the form.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>fields</code></th><td><?php esc_html_e( 'Standard fields to show: any of name,email,message (default: all three). Use fields="" to show none.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>required</code></th><td><?php esc_html_e( 'Which standard fields are mandatory (default: name,email).', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>custom</code></th><td><?php esc_html_e( 'Your own extra fields — see above.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>types</code></th><td><?php esc_html_e( 'File types for this form, e.g. mp3,wav. Can only narrow the types enabled in Settings.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>max_files</code></th><td><?php esc_html_e( 'Files per upload. Can only be lower than the Settings limit.', 'secure-media-vault' ); ?></td></tr>
					<tr><th scope="row"><code>button</code></th><td><?php esc_html_e( 'Submit button text.', 'secure-media-vault' ); ?></td></tr>
				</tbody>
			</table>

			<h3 class="smv-guide__h"><?php esc_html_e( 'Using your existing Contact Form 7 forms', 'secure-media-vault' ); ?></h3>
			<?php if ( SMV_CF7::active() ) : ?>
				<p class="smv-muted">
					<?php
					if ( 'off' === SMV_Settings::get( 'cf7_mode' ) ) {
						esc_html_e( 'The Contact Form 7 integration is currently off.', 'secure-media-vault' );
					} else {
						esc_html_e( 'Open a Contact Form 7 form, go to its “Media Vault” tab and tick “Save files sent through this form to Media Vault”. That form’s files then arrive here with all the answers, and its email carries a link to them instead of attachments. Forms you don’t switch on stay exactly as they are.', 'secure-media-vault' );
					}
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smv-settings&tab=forms#smv-cf7' ) ); ?>"><?php esc_html_e( 'See which forms are connected', 'secure-media-vault' ); ?></a>
				</p>
				<table class="smv-reftable">
					<tbody>
						<tr><th scope="row"><code>[mediavault-link]</code></th><td><?php esc_html_e( 'Optional: place it in the Mail tab to choose where the link to the files appears. Otherwise it is added at the end automatically.', 'secure-media-vault' ); ?></td></tr>
						<tr><th scope="row"><code>[mediavault collection:3]</code></th><td><?php esc_html_e( 'Add to the Form tab to show a gallery or playlist inside the form (collection id or slug).', 'secure-media-vault' ); ?></td></tr>
					</tbody>
				</table>
				<p class="smv-help"><?php esc_html_e( 'Don’t put [smv_upload_form] inside a Contact Form 7 form: it is a form of its own, and forms can’t be nested.', 'secure-media-vault' ); ?></p>
			<?php else : ?>
				<p class="smv-muted"><?php esc_html_e( 'If you use Contact Form 7, files from its [file] fields can be saved here automatically once it is active.', 'secure-media-vault' ); ?></p>
			<?php endif; ?>

			<h3 class="smv-guide__h"><?php esc_html_e( 'Good to know', 'secure-media-vault' ); ?></h3>
			<ul class="smv-checklist">
				<li><?php esc_html_e( 'You can use several forms on different pages — give each a different label.', 'secure-media-vault' ); ?></li>
				<li><?php esc_html_e( 'Contact Form 7 forms are supported (see above). Other form plugins (WPForms, Gravity Forms, Elementor Forms…) are not connected; you can place this form on the same page as one of them.', 'secure-media-vault' ); ?></li>
				<li><?php esc_html_e( 'If you use a page-caching plugin, exclude pages that contain the form — its security token expires after about a day.', 'secure-media-vault' ); ?></li>
				<li>
					<?php
					/* translators: %s: server upload limit */
					printf( esc_html__( 'Your server accepts at most %s per upload request, whatever the settings say.', 'secure-media-vault' ), '<strong>' . esc_html( size_format( wp_max_upload_size() ) ) . '</strong>' );
					?>
				</li>
			</ul>
</div>
