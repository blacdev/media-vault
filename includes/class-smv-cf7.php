<?php
/**
 * Contact Form 7 integration.
 *
 * Opt-in per form, from a "Media Vault" tab in the Contact Form 7 editor. For forms that
 * are switched on:
 * - Files sent through CF7 [file] fields are saved privately in the vault (and Dropbox, if
 *   enabled) together with the other answers, and appear under Media Vault → Submissions.
 * - The email becomes a notification: stored files are not attached, and it carries a link
 *   to the submission in Media Vault (automatically, or wherever [mediavault-link] is placed).
 * Forms that aren't switched on are not touched at all.
 * - [mediavault collection:3] (or collection:my-slug) shows a collection inside a CF7 form.
 *
 * Nothing here runs unless Contact Form 7 is active.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_CF7 {

	const META = '_smv_vault';

	/** @var int Submission id saved for the CF7 request being processed. */
	private static $last_submission = 0;

	/** @var string[] Real paths of the CF7 temp files that were stored in the vault. */
	private static $stored_paths = array();

	public static function active() {
		return defined( 'WPCF7_VERSION' ) && class_exists( 'WPCF7_Submission' );
	}

	public static function init() {
		if ( ! self::active() ) {
			return;
		}
		// Early priority: CF7 deletes its temporary upload files right after sending mail.
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'capture' ), 1, 3 );
		add_filter( 'wpcf7_special_mail_tags', array( __CLASS__, 'mail_tag' ), 10, 3 );
		add_filter( 'wpcf7_mail_components', array( __CLASS__, 'mail_components' ), 20, 3 );
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'editor_panel' ) );
		add_action( 'wpcf7_after_save', array( __CLASS__, 'save_panel' ) );
		self::migrate_legacy_setting();
		add_action( 'wpcf7_init', array( __CLASS__, 'register_form_tag' ) );
		if ( did_action( 'wpcf7_init' ) ) {
			self::register_form_tag();
		}
	}

	/** All CF7 forms as id => title (for the settings screen). */
	public static function forms() {
		if ( ! self::active() ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'any',
			)
		);
		$out   = array();
		foreach ( $posts as $p ) {
			$out[ (int) $p->ID ] = $p->post_title;
		}
		return $out;
	}

	/** Per-form options, stored on the CF7 form. */
	public static function form_settings( $form_id ) {
		$saved = get_post_meta( (int) $form_id, self::META, true );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'enabled' => ! empty( $saved['enabled'] ),
			'attach'  => ! empty( $saved['attach'] ),                  // Keep emailing the files too.
			'link'    => ! isset( $saved['link'] ) || ! empty( $saved['link'] ), // Add the link automatically.
			// '' = use Settings → Storage → Contact Form 7; otherwise local | both | dropbox for this form only.
			'storage' => isset( $saved['storage'] ) && in_array( $saved['storage'], array( 'local', 'both', 'dropbox' ), true ) ? $saved['storage'] : '',
		);
	}

	private static function enabled_for( $form_id ) {
		if ( 'off' === SMV_Settings::get( 'cf7_mode' ) ) {
			return false;
		}
		$opts = self::form_settings( $form_id );
		return $opts['enabled'];
	}

	/** Version 1.3.0 had a site-wide "selected forms" list; move it onto those forms once. */
	private static function migrate_legacy_setting() {
		$mode = SMV_Settings::get( 'cf7_mode' );
		if ( 'all' !== $mode && 'selected' !== $mode ) {
			return;
		}
		if ( 'selected' === $mode ) {
			foreach ( (array) SMV_Settings::get( 'cf7_forms' ) as $fid ) {
				if ( $fid && ! get_post_meta( (int) $fid, self::META, true ) ) {
					update_post_meta(
						(int) $fid,
						self::META,
						array(
							'enabled' => 1,
							'attach'  => 0,
							'link'    => 1,
						)
					);
				}
			}
		}
		$all              = SMV_Settings::all();
		$all['cf7_mode']  = 'per_form';
		$all['cf7_forms'] = array();
		update_option( SMV_Settings::OPTION, $all );
		SMV_Settings::flush_cache();
	}

	// -----------------------------------------------------------------------
	// "Media Vault" tab in the Contact Form 7 editor
	// -----------------------------------------------------------------------

	public static function editor_panel( $panels ) {
		if ( current_user_can( SMV_Plugin::capability() ) ) {
			$panels['smv-vault-panel'] = array(
				'title'    => __( 'Media Vault', 'secure-media-vault' ),
				'callback' => array( __CLASS__, 'render_panel' ),
			);
		}
		return $panels;
	}

	public static function render_panel( $contact_form ) {
		$opts = self::form_settings( $contact_form->id() );
		$off  = 'off' === SMV_Settings::get( 'cf7_mode' );
		wp_nonce_field( 'smv_cf7_panel', 'smv_cf7_nonce' );
		?>
		<h2><?php esc_html_e( 'Media Vault', 'secure-media-vault' ); ?></h2>
		<?php if ( $off ) : ?>
			<p style="color:#b32d2e"><strong><?php esc_html_e( 'The Contact Form 7 integration is switched off in Media Vault → Settings → Upload forms, so these options have no effect right now.', 'secure-media-vault' ); ?></strong></p>
		<?php endif; ?>
		<fieldset>
			<legend><?php esc_html_e( 'Only this form is affected. Your other forms stay exactly as they are.', 'secure-media-vault' ); ?></legend>
			<p>
				<label>
					<input type="checkbox" name="smv_cf7[enabled]" value="1" <?php checked( $opts['enabled'] ); ?>>
					<strong><?php esc_html_e( 'Save files sent through this form to Media Vault', 'secure-media-vault' ); ?></strong>
				</label><br>
				<span class="description"><?php esc_html_e( 'Files from this form’s [file] fields are stored privately with all the answers (and copied to Dropbox if enabled). They appear under Media Vault → Submissions. The two options below apply only when this is ticked.', 'secure-media-vault' ); ?></span>
			</p>
			<div style="margin-left:24px">
				<p>
					<label>
						<input type="checkbox" name="smv_cf7[no_attach]" value="1" <?php checked( ! $opts['attach'] ); ?>>
						<?php /* Only used when the option above is ticked. */ ?>
						<?php esc_html_e( 'Don’t attach the files to the email — send the Media Vault link instead', 'secure-media-vault' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="smv_cf7[link]" value="1" <?php checked( $opts['link'] ); ?>>
						<?php esc_html_e( 'Add the Media Vault link to the email automatically', 'secure-media-vault' ); ?>
					</label><br>
					<span class="description">
						<?php
						printf(
							/* translators: %s: mail tag */
							esc_html__( 'Or untick this and place %s wherever you want the link in the Mail tab.', 'secure-media-vault' ),
							'<code>[mediavault-link]</code>'
						);
						?>
					</span>
				</p>
				<p>
					<label for="smv-cf7-storage"><?php esc_html_e( 'Where to store this form’s files:', 'secure-media-vault' ); ?></label><br>
					<?php
					$smv_modes     = self::storage_labels();
					$smv_default   = SMV_Storage::mode_for_source( 'cf7' );
					$smv_connected = SMV_Dropbox::is_connected();
					?>
					<select id="smv-cf7-storage" name="smv_cf7[storage]">
						<option value="" <?php selected( $opts['storage'], '' ); ?>>
							<?php
							/* translators: %s: storage option name */
							printf( esc_html__( 'Same as the Contact Form 7 setting (%s)', 'secure-media-vault' ), esc_html( $smv_modes[ $smv_default ] ) );
							?>
						</option>
						<?php foreach ( $smv_modes as $smv_key => $smv_label ) : ?>
							<option value="<?php echo esc_attr( $smv_key ); ?>" <?php selected( $opts['storage'], $smv_key ); ?> <?php disabled( 'local' !== $smv_key && ! $smv_connected ); ?>><?php echo esc_html( $smv_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! $smv_connected ) : ?>
						<br><span class="description"><?php esc_html_e( 'Connect Dropbox in Media Vault → Settings → Dropbox to use the Dropbox options.', 'secure-media-vault' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="description"><?php esc_html_e( 'The visitor’s acknowledgement email (Mail (2)) never receives the link or the stored files. Files the vault refuses for safety (scripts, HTML, SVG, programs, or contents that don’t match the extension) are left to Contact Form 7’s normal handling.', 'secure-media-vault' ); ?></p>
			</div>
		</fieldset>
		<?php
	}

	public static function save_panel( $contact_form ) {
		// Only when saved from the CF7 editor with our tab present.
		if ( ! isset( $_POST['smv_cf7_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smv_cf7_nonce'] ) ), 'smv_cf7_panel' ) ) {
			return;
		}
		$id = (int) $contact_form->id();
		if ( ! $id || ! current_user_can( SMV_Plugin::capability() ) || ! current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
			return;
		}
		$in = isset( $_POST['smv_cf7'] ) && is_array( $_POST['smv_cf7'] ) ? wp_unslash( $_POST['smv_cf7'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only booleans are read.
		update_post_meta(
			$id,
			self::META,
			array(
				'enabled' => empty( $in['enabled'] ) ? 0 : 1,
				'attach'  => empty( $in['no_attach'] ) ? 1 : 0,
				'link'    => empty( $in['link'] ) ? 0 : 1,
				'storage' => isset( $in['storage'] ) && in_array( $in['storage'], array( 'local', 'both', 'dropbox' ), true ) ? $in['storage'] : '',
			)
		);
	}

	public static function storage_labels() {
		return array(
			'local'   => __( 'Local folder', 'secure-media-vault' ),
			'both'    => __( 'Local + Dropbox backup', 'secure-media-vault' ),
			'dropbox' => __( 'Dropbox only', 'secure-media-vault' ),
		);
	}

	/** "your-phone-number" → "Phone number". */
	private static function label_for( $name ) {
		$label = preg_replace( '/^your[-_]/', '', (string) $name );
		$label = trim( str_replace( array( '-', '_' ), ' ', $label ) );
		return '' === $label ? (string) $name : ucfirst( $label );
	}

	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', $value ), 'strlen' ) );
		}
		return trim( (string) $value );
	}

	/**
	 * Copy the CF7 uploads into the vault. Never aborts or alters the CF7 submission.
	 *
	 * @param WPCF7_ContactForm $contact_form
	 * @param bool              $abort       Passed by reference by CF7; left untouched.
	 * @param WPCF7_Submission  $submission
	 */
	public static function capture( $contact_form, &$abort = false, $submission = null ) {
		self::$last_submission = 0;
		self::$stored_paths    = array();
		try {
			if ( ! $contact_form || ! self::enabled_for( $contact_form->id() ) ) {
				return;
			}
			$submission = $submission ? $submission : WPCF7_Submission::get_instance();
			if ( ! $submission ) {
				return;
			}

			// Collect file paths: CF7 5.4+ gives field => array of paths, older versions a string.
			$files    = array();
			$tmp_root = function_exists( 'wpcf7_upload_tmp_dir' ) ? realpath( wpcf7_upload_tmp_dir() ) : false;
			foreach ( (array) $submission->uploaded_files() as $paths ) {
				foreach ( (array) $paths as $path ) {
					$real = realpath( (string) $path );
					// Only accept files inside CF7's own temporary upload folder.
					if ( $real && $tmp_root && 0 === strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $tmp_root ) ) ) ) {
						$files[] = $real;
					}
				}
			}
			if ( ! $files ) {
				return; // Forms without uploads are left completely alone.
			}

			// Answers: name / email / message into their own columns, everything else as fields.
			$posted  = (array) $submission->get_posted_data();
			$name    = '';
			$email   = '';
			$message = '';
			$fields  = array();
			$skip    = array( 'file', 'submit', 'quiz', 'captchac', 'captchar', 'recaptcha', 'hidden', 'mediavault' );
			foreach ( $contact_form->scan_form_tags() as $tag ) {
				if ( empty( $tag->name ) || in_array( $tag->basetype, $skip, true ) || ! array_key_exists( $tag->name, $posted ) ) {
					continue;
				}
				$value = self::flatten( $posted[ $tag->name ] );
				if ( 'acceptance' === $tag->basetype ) {
					$value = $value ? __( 'Yes', 'secure-media-vault' ) : __( 'No', 'secure-media-vault' );
				}
				if ( '' === $email && 'email' === $tag->basetype && is_email( $value ) ) {
					$email = sanitize_email( $value );
					continue;
				}
				if ( '' === $name && 'text' === $tag->basetype && false !== strpos( strtolower( $tag->name ), 'name' ) ) {
					$name = substr( sanitize_text_field( $value ), 0, 190 );
					continue;
				}
				if ( '' === $message && 'textarea' === $tag->basetype && false !== strpos( strtolower( $tag->name ), 'message' ) ) {
					$message = substr( sanitize_textarea_field( $value ), 0, 5000 );
					continue;
				}
				$fields[] = array(
					'label' => substr( sanitize_text_field( self::label_for( $tag->name ) ), 0, 120 ),
					'value' => substr( 'textarea' === $tag->basetype ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ), 0, 5000 ),
				);
			}

			global $wpdb;
			$tables = SMV_Installer::tables();
			$ip     = (string) $submission->get_meta( 'remote_ip' );
			$url    = (string) $submission->get_meta( 'url' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$tables['submissions'],
				array(
					/* translators: %s: Contact Form 7 form title */
					'form_label' => substr( sprintf( __( 'Contact Form 7: %s', 'secure-media-vault' ), $contact_form->title() ), 0, 190 ),
					'name'       => $name,
					'email'      => $email,
					'message'    => $message,
					'fields'     => $fields ? wp_json_encode( $fields ) : '',
					'ip'         => filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '',
					'user_id'    => get_current_user_id(),
					'page_url'   => substr( esc_url_raw( $url ), 0, 500 ),
					'status'     => 'new',
					'created_at' => current_time( 'mysql', true ),
				)
			);
			$submission_id = (int) $wpdb->insert_id;
			if ( ! $submission_id ) {
				return;
			}

			$stored = 0;
			foreach ( $files as $path ) {
				$result = SMV_Storage::import_file(
					$path,
					wp_basename( $path ),
					array(
						'visibility'    => 'private',
						// CF7 decides which types its form accepts; the vault still refuses anything
						// outside its own safe list (no scripts, HTML, SVG, executables).
						'allowed'       => array_keys( SMV_Settings::all_types() ),
						'max_bytes'     => max( (int) SMV_Settings::get( 'max_size_mb' ), (int) SMV_Settings::get( 'form_max_size_mb' ) ) * MB_IN_BYTES,
						'source'        => 'cf7',
						'submission_id' => $submission_id,
						'storage'       => self::form_settings( $contact_form->id() )['storage'], // '' = Contact Form 7 setting.
					)
				);
				if ( ! is_wp_error( $result ) ) {
					++$stored;
					self::$stored_paths[] = wp_normalize_path( $path );
				}
			}

			if ( ! $stored ) {
				$wpdb->delete( $tables['submissions'], array( 'id' => $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return;
			}
			self::$last_submission = $submission_id;
		} catch ( \Throwable $e ) {
			// Never let the vault interfere with the visitor's CF7 submission.
			self::$last_submission = 0;
			self::$stored_paths    = array();
		}
	}

	/** [mediavault-link] in a CF7 mail template → link to the saved submission. */
	public static function mail_tag( $output, $name, $html = false ) {
		if ( 'mediavault-link' !== $name ) {
			return $output;
		}
		if ( ! self::$last_submission || 'mail' !== self::current_template() ) {
			return ''; // Never give the visitor (Mail 2) an admin link.
		}
		$url = self::submission_url();
		return $html ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View the files in Media Vault', 'secure-media-vault' ) . '</a>' : $url;
	}

	private static function submission_url() {
		return admin_url( 'admin.php?page=smv-submissions&submission=' . self::$last_submission );
	}

	private static function current_template( $mail = null ) {
		if ( $mail instanceof WPCF7_Mail ) {
			return $mail->name();
		}
		return class_exists( 'WPCF7_Mail' ) ? (string) WPCF7_Mail::get_current_template_name() : '';
	}

	/**
	 * For enabled forms: drop the stored files from the email(s) and add the vault link to
	 * the main email (not the visitor's acknowledgement).
	 */
	public static function mail_components( $components, $contact_form = null, $mail = null ) {
		if ( ! self::$last_submission || ! $contact_form || ! self::enabled_for( $contact_form->id() ) ) {
			return $components;
		}
		$opts     = self::form_settings( $contact_form->id() );
		$template = self::current_template( $mail );

		if ( ! $opts['attach'] && ! empty( $components['attachments'] ) ) {
			$stored                    = self::$stored_paths;
			$components['attachments'] = array_values(
				array_filter(
					(array) $components['attachments'],
					function ( $file ) use ( $stored ) {
						$real = realpath( (string) $file );
						return ! ( $real && in_array( wp_normalize_path( $real ), $stored, true ) );
					}
				)
			);
		}

		if ( 'mail' === $template && $opts['link'] && isset( $components['body'] ) ) {
			$url = self::submission_url();
			if ( false === strpos( $components['body'], $url ) && false === strpos( $components['body'], esc_url( $url ) ) ) {
				$html                = $mail instanceof WPCF7_Mail && $mail->get( 'use_html' );
				$components['body'] .= $html
					? '<p><strong>' . esc_html__( 'Files:', 'secure-media-vault' ) . '</strong> <a href="' . esc_url( $url ) . '">' . esc_html__( 'View them in Media Vault', 'secure-media-vault' ) . '</a></p>'
					: "\n\n" . __( 'Files: view them in Media Vault', 'secure-media-vault' ) . "\n" . $url . "\n";
			}
		}
		return $components;
	}

	public static function register_form_tag() {
		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			wpcf7_add_form_tag( 'mediavault', array( __CLASS__, 'render_form_tag' ), array( 'name-attr' => false ) );
		}
	}

	/** [mediavault collection:3] or [mediavault collection:summer-tour] inside a CF7 form. */
	public static function render_form_tag( $tag ) {
		$ref = '';
		foreach ( (array) $tag->options as $opt ) {
			if ( 0 === strpos( $opt, 'collection:' ) ) {
				$ref = substr( $opt, strlen( 'collection:' ) );
			}
		}
		$ref = sanitize_title( $ref );
		if ( '' === $ref ) {
			return '';
		}
		return SMV_Shortcodes::collection( ctype_digit( $ref ) ? array( 'id' => $ref ) : array( 'slug' => $ref ) );
	}
}
