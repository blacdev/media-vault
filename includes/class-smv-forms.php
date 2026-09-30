<?php
/**
 * [smv_upload_form] — protected front-end upload form.
 *
 * Usage:
 *   [smv_upload_form]
 *   [smv_upload_form label="Demo submissions" title="Send us your demo" fields="name,email,message" required="name,email" types="mp3,wav" max_files="3" button="Send"]
 *
 * Extra fields (any number, up to 25) via the "custom" attribute:
 *   custom="Phone:tel:required:half, Company:half, Genre:select(Rock|Pop|Jazz):required, I agree to the terms:checkbox:required"
 *   Format per field:  Label[:type][(Option 1|Option 2)][:required][:half]   — fields separated by commas.
 *   Types: text (default), textarea, email, tel, url, number, date, select, radio, checkbox.
 *
 * Files are stored privately (never publicly reachable) and appear under Media Vault → Submissions.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Forms {

	const MIN_SECONDS = 3;

	public static function init() {
		add_shortcode( 'smv_upload_form', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_smv_form_submit', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_smv_form_submit', array( __CLASS__, 'handle' ) );
	}

	// -----------------------------------------------------------------------
	// Custom fields
	// -----------------------------------------------------------------------

	const MAX_CUSTOM = 25;

	public static function custom_types() {
		return array( 'text', 'textarea', 'email', 'tel', 'url', 'number', 'date', 'select', 'radio', 'checkbox' );
	}

	/** Labels/options may not contain the characters used by the field syntax. */
	private static function clean_label( $text ) {
		$text = sanitize_text_field( wp_specialchars_decode( (string) $text, ENT_QUOTES ) );
		$text = str_replace( array( ',', ':', '(', ')', '|', '[', ']', '"' ), '', $text );
		return trim( substr( $text, 0, 120 ) );
	}

	/**
	 * Parse the "custom" shortcode attribute into field definitions.
	 *
	 * @param string $spec e.g. "Phone:tel:required:half, Genre:select(Rock|Pop):required".
	 * @return array[] Each: key, label, type, options, required, half.
	 */
	public static function parse_custom( $spec ) {
		$defs = array();
		$used = array(
			'name'    => true,
			'email'   => true,
			'message' => true,
		);
		foreach ( explode( ',', (string) $spec ) as $chunk ) {
			$chunk   = trim( $chunk );
			$options = array();
			if ( '' === $chunk ) {
				continue;
			}
			if ( preg_match( '/\(([^)]*)\)/', $chunk, $m ) ) {
				foreach ( explode( '|', $m[1] ) as $opt ) {
					$opt = self::clean_label( $opt );
					if ( '' !== $opt && ! in_array( $opt, $options, true ) ) {
						$options[] = $opt;
					}
				}
				$chunk = str_replace( $m[0], '', $chunk );
			}
			$parts = array_map( 'trim', explode( ':', $chunk ) );
			$label = self::clean_label( array_shift( $parts ) );
			if ( '' === $label ) {
				continue;
			}
			$type     = 'text';
			$required = false;
			$half     = false;
			foreach ( $parts as $part ) {
				$part = strtolower( $part );
				if ( in_array( $part, self::custom_types(), true ) ) {
					$type = $part;
				} elseif ( 'required' === $part ) {
					$required = true;
				} elseif ( 'half' === $part ) {
					$half = true;
				}
			}
			if ( in_array( $type, array( 'select', 'radio' ), true ) && ! $options ) {
				$type = 'text'; // A choice field without choices makes no sense.
			}
			if ( ! in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$options = array();
			}

			$base = substr( sanitize_key( str_replace( ' ', '_', remove_accents( $label ) ) ), 0, 40 );
			$base = '' === $base ? 'field' : $base;
			$key  = $base;
			$n    = 2;
			while ( isset( $used[ $key ] ) ) {
				$key = $base . '_' . $n;
				++$n;
			}
			$used[ $key ] = true;

			$defs[] = array(
				'key'      => $key,
				'label'    => $label,
				'type'     => $type,
				'options'  => array_slice( $options, 0, 50 ),
				'required' => $required,
				'half'     => $half,
			);
			if ( count( $defs ) >= self::MAX_CUSTOM ) {
				break;
			}
		}
		return $defs;
	}

	private static function render_custom_field( array $f, $uid ) {
		$id       = $uid . '-cf-' . $f['key'];
		$name     = 'smv_cf[' . $f['key'] . ']';
		$req      = $f['required'] ? ' required' : '';
		$classes  = 'smv-form__field smv-form__field--' . $f['type'] . ( $f['half'] ? ' smv-form__field--half' : '' );
		$optional = $f['required'] ? '' : ' <span class="smv-form__optional">' . esc_html__( '(optional)', 'secure-media-vault' ) . '</span>';

		// Grouped choices use fieldset/legend for accessibility.
		if ( 'radio' === $f['type'] || ( 'checkbox' === $f['type'] && $f['options'] ) ) {
			$multi = 'checkbox' === $f['type'];
			$html  = sprintf( '<fieldset class="%1$s"%2$s><legend>%3$s%4$s</legend><div class="smv-form__choices">', esc_attr( $classes ), $f['required'] ? ' data-smv-required' : '', esc_html( $f['label'] ), $optional );
			foreach ( $f['options'] as $opt ) {
				$html .= sprintf(
					'<label class="smv-choice"><input type="%1$s" name="%2$s" value="%3$s"> <span>%4$s</span></label>',
					$multi ? 'checkbox' : 'radio',
					esc_attr( $multi ? $name . '[]' : $name ),
					esc_attr( $opt ),
					esc_html( $opt )
				);
			}
			return $html . '</div></fieldset>';
		}

		// Single checkbox, e.g. consent.
		if ( 'checkbox' === $f['type'] ) {
			return sprintf(
				'<div class="%1$s"><label class="smv-choice" for="%2$s"><input type="checkbox" id="%2$s" name="%3$s" value="yes"%4$s> <span>%5$s%6$s</span></label></div>',
				esc_attr( $classes ),
				esc_attr( $id ),
				esc_attr( $name ),
				$req,
				esc_html( $f['label'] ),
				$optional
			);
		}

		$label = sprintf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $f['label'] ), $optional );

		if ( 'textarea' === $f['type'] ) {
			$control = sprintf( '<textarea id="%1$s" name="%2$s" rows="4" maxlength="5000"%3$s></textarea>', esc_attr( $id ), esc_attr( $name ), $req );
		} elseif ( 'select' === $f['type'] ) {
			$control = sprintf( '<select id="%1$s" name="%2$s"%3$s><option value="">%4$s</option>', esc_attr( $id ), esc_attr( $name ), $req, esc_html__( 'Choose…', 'secure-media-vault' ) );
			foreach ( $f['options'] as $opt ) {
				$control .= sprintf( '<option value="%1$s">%2$s</option>', esc_attr( $opt ), esc_html( $opt ) );
			}
			$control .= '</select>';
		} else {
			$extra   = array(
				'email'  => ' autocomplete="email"',
				'tel'    => ' autocomplete="tel" inputmode="tel"',
				'url'    => ' inputmode="url" placeholder="https://"',
				'number' => ' inputmode="decimal" step="any"',
			);
			$control = sprintf(
				'<input type="%1$s" id="%2$s" name="%3$s" maxlength="500"%4$s%5$s>',
				esc_attr( $f['type'] ),
				esc_attr( $id ),
				esc_attr( $name ),
				isset( $extra[ $f['type'] ] ) ? $extra[ $f['type'] ] : '',
				$req
			);
		}
		return '<div class="' . esc_attr( $classes ) . '">' . $label . $control . '</div>';
	}

	/**
	 * Validate submitted custom values against their (signed) definitions.
	 *
	 * @return array|WP_Error List of array( label, value ).
	 */
	private static function validate_custom( array $defs, $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( $defs as $f ) {
			$raw   = isset( $input[ $f['key'] ] ) ? $input[ $f['key'] ] : '';
			$value = '';
			/* translators: %s: field label */
			$invalid = new WP_Error( 'smv_field', sprintf( __( 'Please check the “%s” field.', 'secure-media-vault' ), $f['label'] ) );

			if ( 'checkbox' === $f['type'] && $f['options'] ) {
				$picked = array_values( array_intersect( $f['options'], array_map( 'sanitize_text_field', (array) $raw ) ) );
				$value  = implode( ', ', $picked );
			} elseif ( is_array( $raw ) ) {
				return $invalid;
			} else {
				$raw = trim( (string) $raw );
				switch ( $f['type'] ) {
					case 'textarea':
						$value = substr( sanitize_textarea_field( $raw ), 0, 5000 );
						break;
					case 'email':
						$value = sanitize_email( $raw );
						if ( '' !== $raw && ! is_email( $value ) ) {
							return $invalid;
						}
						break;
					case 'tel':
						$value = sanitize_text_field( $raw );
						if ( '' !== $value && ! preg_match( '/^[0-9+().\-\s]{3,40}$/', $value ) ) {
							return $invalid;
						}
						break;
					case 'url':
						$value = esc_url_raw( $raw, array( 'http', 'https' ) );
						if ( '' !== $raw && ( '' === $value || ! filter_var( $value, FILTER_VALIDATE_URL ) ) ) {
							return $invalid;
						}
						break;
					case 'number':
						$value = sanitize_text_field( $raw );
						if ( '' !== $value && ! is_numeric( $value ) ) {
							return $invalid;
						}
						break;
					case 'date':
						$value = sanitize_text_field( $raw );
						if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
							return $invalid;
						}
						break;
					case 'select':
					case 'radio':
						$value = sanitize_text_field( $raw );
						if ( '' !== $value && ! in_array( $value, $f['options'], true ) ) {
							return $invalid;
						}
						break;
					case 'checkbox':
						$value = '' !== $raw ? __( 'Yes', 'secure-media-vault' ) : '';
						break;
					default:
						$value = substr( sanitize_text_field( $raw ), 0, 500 );
				}
			}

			if ( $f['required'] && '' === $value ) {
				/* translators: %s: field label */
				return new WP_Error( 'smv_field', sprintf( __( 'Please fill in the “%s” field.', 'secure-media-vault' ), $f['label'] ) );
			}
			$out[] = array(
				'label' => $f['label'],
				'value' => $value,
			);
		}
		return $out;
	}

	/** Re-normalise definitions coming back from the signed config. */
	private static function normalize_defs( $defs ) {
		$out = array();
		foreach ( is_array( $defs ) ? array_slice( $defs, 0, self::MAX_CUSTOM ) : array() as $d ) {
			if ( ! is_array( $d ) || empty( $d['key'] ) || empty( $d['label'] ) ) {
				continue;
			}
			$out[] = array(
				'key'      => sanitize_key( $d['key'] ),
				'label'    => self::clean_label( $d['label'] ),
				'type'     => in_array( isset( $d['type'] ) ? $d['type'] : '', self::custom_types(), true ) ? $d['type'] : 'text',
				'options'  => array_map( array( __CLASS__, 'clean_label' ), isset( $d['options'] ) ? (array) $d['options'] : array() ),
				'required' => ! empty( $d['required'] ),
				'half'     => ! empty( $d['half'] ),
			);
		}
		return $out;
	}

	// -----------------------------------------------------------------------
	// Signed configuration (so visitors can't tamper with limits)
	// -----------------------------------------------------------------------

	private static function sign( $data ) {
		return hash_hmac( 'sha256', $data, wp_salt( 'nonce' ) );
	}

	private static function effective_config( array $atts ) {
		$global_types = (array) SMV_Settings::get( 'form_allowed_types' );
		$types        = array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['types'] ) ) );
		$types        = $types ? array_values( array_intersect( $types, $global_types ) ) : $global_types;
		$fields       = array_values( array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $atts['fields'] ) ) ), array( 'name', 'email', 'message' ) ) );
		$required     = array_values( array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $atts['required'] ) ) ), $fields ) );
		$max_files    = (int) SMV_Settings::get( 'form_max_files' );
		if ( '' !== (string) $atts['max_files'] ) {
			$max_files = max( 1, min( $max_files, absint( $atts['max_files'] ) ) );
		}
		return array(
			'label'     => sanitize_text_field( $atts['label'] ),
			'types'     => $types,
			'fields'    => $fields,
			'required'  => $required,
			'max_files' => $max_files,
			'custom'    => self::parse_custom( $atts['custom'] ),
		);
	}

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'label'       => __( 'Upload form', 'secure-media-vault' ),
				'title'       => '',
				'description' => '',
				'fields'      => 'name,email,message',
				'required'    => 'name,email',
				'types'       => '',
				'max_files'   => '',
				'custom'      => '',
				'button'      => __( 'Upload files', 'secure-media-vault' ),
			),
			$atts,
			'smv_upload_form'
		);

		SMV_Shortcodes::enqueue();

		if ( 'logged_in' === SMV_Settings::get( 'form_access' ) && ! is_user_logged_in() ) {
			return SMV_Shortcodes::inline_assets() . sprintf(
				'<div class="smv smv-form smv-form--locked"><p>%1$s</p><a class="smv-btn" href="%2$s">%3$s</a></div>',
				esc_html__( 'Please log in to upload files.', 'secure-media-vault' ),
				esc_url( wp_login_url( get_permalink() ) ),
				esc_html__( 'Log in', 'secure-media-vault' )
			);
		}

		// Storage limit reached: show a friendly notice instead of a form that would fail.
		if ( SMV_Storage::quota_status()['full'] ) {
			return SMV_Shortcodes::inline_assets() . '<div class="smv smv-form smv-form--locked"><p>' . esc_html__( 'We’re not accepting new files at the moment. Please check back later.', 'secure-media-vault' ) . '</p></div>';
		}

		$cfg       = self::effective_config( $atts );
		$cfg_json  = wp_json_encode( $cfg );
		$cfg_b64   = base64_encode( $cfg_json ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$ts        = (string) time();
		$max_bytes = min( SMV_Settings::get( 'form_max_size_mb' ) * MB_IN_BYTES, wp_max_upload_size() );
		$accept    = implode( ',', array_map( function ( $e ) { return '.' . $e; }, $cfg['types'] ) ); // phpcs:ignore
		$uid       = 'smv-form-' . wp_unique_id();

		// Status from a no-JS submission redirect.
		$status_html = '';
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['smv_status'] ) ) {
			$ok  = 'ok' === sanitize_key( wp_unslash( $_GET['smv_status'] ) );
			$msg = SMV_Settings::get( 'form_success_message' );
			if ( ! $ok ) {
				$token = isset( $_GET['smv_msg'] ) ? sanitize_key( wp_unslash( $_GET['smv_msg'] ) ) : '';
				$saved = $token ? get_transient( 'smv_msg_' . $token ) : false;
				$msg   = $saved ? $saved : __( 'Upload failed. Please try again.', 'secure-media-vault' );
			}
			$status_html = sprintf( '<div class="smv-form__status is-%1$s">%2$s</div>', $ok ? 'success' : 'error', esc_html( $msg ) );
		}
		// phpcs:enable

		$type_labels = strtoupper( implode( ', ', array_unique( array_diff( $cfg['types'], array( 'jpeg' ) ) ) ) );

		ob_start();
		echo SMV_Shortcodes::inline_assets(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder.
		?>
		<form class="smv smv-form" id="<?php echo esc_attr( $uid ); ?>" method="post" enctype="multipart/form-data" novalidate
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			data-max-files="<?php echo (int) $cfg['max_files']; ?>"
			data-max-bytes="<?php echo (int) $max_bytes; ?>"
			data-types="<?php echo esc_attr( implode( ',', $cfg['types'] ) ); ?>"
			data-success="<?php echo esc_attr( SMV_Settings::get( 'form_success_message' ) ); ?>">

			<?php if ( $atts['title'] ) : ?>
				<h3 class="smv-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<?php if ( $atts['description'] ) : ?>
				<p class="smv-form__desc"><?php echo esc_html( $atts['description'] ); ?></p>
			<?php endif; ?>

			<div class="smv-form__status-wrap" role="status" aria-live="polite"><?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></div>

			<div class="smv-form__grid">
				<?php
				$labels = array(
					'name'    => __( 'Your name', 'secure-media-vault' ),
					'email'   => __( 'Email address', 'secure-media-vault' ),
					'message' => __( 'Message', 'secure-media-vault' ),
				);
				// Order: name, email, your custom fields, then message.
				$order = array_values( array_diff( $cfg['fields'], array( 'message' ) ) );
				$order = array_merge( $order, array( '__custom' ), in_array( 'message', $cfg['fields'], true ) ? array( 'message' ) : array() );
				foreach ( $order as $field ) :
					if ( '__custom' === $field ) {
						foreach ( $cfg['custom'] as $custom ) {
							echo self::render_custom_field( $custom, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder.
						}
						continue;
					}
					$req = in_array( $field, $cfg['required'], true );
					$fid = $uid . '-' . $field;
					?>
					<div class="smv-form__field">
						<label for="<?php echo esc_attr( $fid ); ?>">
							<?php echo esc_html( $labels[ $field ] ); ?>
							<?php if ( ! $req ) : ?>
								<span class="smv-form__optional"><?php esc_html_e( '(optional)', 'secure-media-vault' ); ?></span>
							<?php endif; ?>
						</label>
						<?php if ( 'message' === $field ) : ?>
							<textarea id="<?php echo esc_attr( $fid ); ?>" name="smv_message" rows="4" maxlength="5000" <?php echo $req ? 'required' : ''; ?>></textarea>
						<?php else : ?>
							<input id="<?php echo esc_attr( $fid ); ?>" type="<?php echo 'email' === $field ? 'email' : 'text'; ?>" name="smv_<?php echo esc_attr( $field ); ?>" maxlength="190" autocomplete="<?php echo 'email' === $field ? 'email' : 'name'; ?>" <?php echo $req ? 'required' : ''; ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="smv-drop" data-smv-drop>
				<input class="smv-drop__input" id="<?php echo esc_attr( $uid ); ?>-files" type="file" name="smv_files[]" multiple accept="<?php echo esc_attr( $accept ); ?>" required>
				<label class="smv-drop__label" for="<?php echo esc_attr( $uid ); ?>-files">
					<svg class="smv-drop__icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
					<span class="smv-drop__title"><?php echo wp_kses( __( 'Drag files here or <u>browse</u>', 'secure-media-vault' ), array( 'u' => array() ) ); ?></span>
					<span class="smv-drop__hint">
						<?php
						printf(
							/* translators: 1: max number of files, 2: allowed types, 3: size */
							esc_html__( 'Up to %1$d files · %2$s · max %3$s each', 'secure-media-vault' ),
							(int) $cfg['max_files'],
							esc_html( $type_labels ),
							esc_html( size_format( $max_bytes ) )
						);
						?>
					</span>
				</label>
			</div>
			<ul class="smv-form__queue" aria-label="<?php esc_attr_e( 'Selected files', 'secure-media-vault' ); ?>"></ul>

			<div class="smv-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'secure-media-vault' ); ?><input type="text" name="smv_website" value="" tabindex="-1" autocomplete="off"></label>
			</div>

			<input type="hidden" name="action" value="smv_form_submit">
			<input type="hidden" name="smv_cfg" value="<?php echo esc_attr( $cfg_b64 ); ?>">
			<input type="hidden" name="smv_sig" value="<?php echo esc_attr( self::sign( $cfg_b64 ) ); ?>">
			<input type="hidden" name="smv_ts" value="<?php echo esc_attr( $ts ); ?>">
			<input type="hidden" name="smv_ts_sig" value="<?php echo esc_attr( self::sign( 'ts|' . $ts ) ); ?>">
			<input type="hidden" name="smv_return" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( add_query_arg( array() ) ) ); ?>">
			<?php wp_nonce_field( 'smv_form_submit', 'smv_nonce', false ); ?>

			<div class="smv-form__progress" hidden><span class="smv-form__bar"></span></div>

			<button type="submit" class="smv-btn smv-form__submit"><?php echo esc_html( $atts['button'] ); ?></button>
			<p class="smv-form__secure">
				<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Z"/></svg>
				<?php esc_html_e( 'Files are transferred securely and stored privately.', 'secure-media-vault' ); ?>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// Submit
	// -----------------------------------------------------------------------

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/** Filter to trust a proxy header if your site is behind a CDN you control. */
		$ip = apply_filters( 'smv_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function respond( $ok, $message, $redirect_to ) {
		$is_ajax = ! empty( $_POST['smv_ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $is_ajax ) {
			if ( $ok ) {
				wp_send_json_success( array( 'message' => $message ) );
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
		}
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		$args        = array( 'smv_status' => $ok ? 'ok' : 'error' );
		if ( ! $ok ) {
			// Pass the message by reference so the URL can't be used to display arbitrary text.
			$token = strtolower( wp_generate_password( 12, false ) );
			set_transient( 'smv_msg_' . $token, $message, 5 * MINUTE_IN_SECONDS );
			$args['smv_msg'] = $token;
		}
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'smv_status', 'smv_msg' ), $redirect_to ) ) );
		exit;
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified immediately below.
		$return = isset( $_POST['smv_return'] ) ? esc_url_raw( wp_unslash( $_POST['smv_return'] ) ) : home_url( '/' );
		$fail   = __( 'Your upload could not be processed. Please reload the page and try again.', 'secure-media-vault' );

		if ( ! isset( $_POST['smv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smv_nonce'] ) ), 'smv_form_submit' ) ) {
			self::respond( false, $fail, $return );
		}
		// phpcs:enable

		if ( 'logged_in' === SMV_Settings::get( 'form_access' ) && ! is_user_logged_in() ) {
			self::respond( false, __( 'Please log in to upload files.', 'secure-media-vault' ), $return );
		}

		// Honeypot — pretend success so bots learn nothing.
		if ( ! empty( $_POST['smv_website'] ) ) {
			self::respond( true, SMV_Settings::get( 'form_success_message' ), $return );
		}

		// Signed timestamp — too fast means a bot, too old means a stale page.
		$ts     = isset( $_POST['smv_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['smv_ts'] ) ) : '';
		$ts_sig = isset( $_POST['smv_ts_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['smv_ts_sig'] ) ) : '';
		if ( ! $ts || ! hash_equals( self::sign( 'ts|' . $ts ), $ts_sig ) ) {
			self::respond( false, $fail, $return );
		}
		$age = time() - (int) $ts;
		if ( $age < self::MIN_SECONDS ) {
			self::respond( false, __( 'That was fast! Please wait a moment and try again.', 'secure-media-vault' ), $return );
		}
		if ( $age > DAY_IN_SECONDS ) {
			self::respond( false, $fail, $return );
		}

		// Signed config.
		$cfg_b64 = isset( $_POST['smv_cfg'] ) ? sanitize_text_field( wp_unslash( $_POST['smv_cfg'] ) ) : '';
		$sig     = isset( $_POST['smv_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['smv_sig'] ) ) : '';
		if ( ! $cfg_b64 || ! hash_equals( self::sign( $cfg_b64 ), $sig ) ) {
			self::respond( false, $fail, $return );
		}
		$cfg = json_decode( (string) base64_decode( $cfg_b64, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! is_array( $cfg ) ) {
			self::respond( false, $fail, $return );
		}
		// Re-apply current global limits in case settings tightened since the page was rendered.
		$cfg['types']     = array_values( array_intersect( (array) $cfg['types'], (array) SMV_Settings::get( 'form_allowed_types' ) ) );
		$cfg['max_files'] = min( (int) $cfg['max_files'], (int) SMV_Settings::get( 'form_max_files' ) );
		$cfg['fields']    = array_values( array_intersect( (array) $cfg['fields'], array( 'name', 'email', 'message' ) ) );
		$cfg['required']  = array_values( array_intersect( (array) $cfg['required'], $cfg['fields'] ) );
		$cfg['custom']    = self::normalize_defs( isset( $cfg['custom'] ) ? $cfg['custom'] : array() );

		// Rate limit per IP.
		$ip     = self::client_ip();
		$rl_key = 'smv_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$count  = (int) get_transient( $rl_key );
		if ( $count >= (int) SMV_Settings::get( 'form_rate_limit' ) && ! current_user_can( SMV_Plugin::capability() ) ) {
			self::respond( false, __( 'Too many uploads from your connection. Please try again later.', 'secure-media-vault' ), $return );
		}

		// Fields.
		$name    = in_array( 'name', $cfg['fields'], true ) && isset( $_POST['smv_name'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['smv_name'] ) ), 0, 190 ) : '';
		$email   = in_array( 'email', $cfg['fields'], true ) && isset( $_POST['smv_email'] ) ? sanitize_email( wp_unslash( $_POST['smv_email'] ) ) : '';
		$message = in_array( 'message', $cfg['fields'], true ) && isset( $_POST['smv_message'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['smv_message'] ) ), 0, 5000 ) : '';

		$values = array(
			'name'    => $name,
			'email'   => $email,
			'message' => $message,
		);
		$names  = array(
			'name'    => __( 'Your name', 'secure-media-vault' ),
			'email'   => __( 'Email address', 'secure-media-vault' ),
			'message' => __( 'Message', 'secure-media-vault' ),
		);
		foreach ( $cfg['required'] as $req ) {
			if ( '' === $values[ $req ] ) {
				/* translators: %s: field label */
				self::respond( false, sprintf( __( 'Please fill in the “%s” field.', 'secure-media-vault' ), $names[ $req ] ), $return );
			}
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			self::respond( false, __( 'Please enter a valid email address.', 'secure-media-vault' ), $return );
		}

		$custom = self::validate_custom( $cfg['custom'], isset( $_POST['smv_cf'] ) ? wp_unslash( $_POST['smv_cf'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated per field type.
		if ( is_wp_error( $custom ) ) {
			self::respond( false, $custom->get_error_message(), $return );
		}

		// Files.
		$files = isset( $_FILES['smv_files'] ) ? SMV_Storage::normalize_files( $_FILES['smv_files'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated in SMV_Storage.
		if ( ! $files ) {
			self::respond( false, __( 'Please choose at least one file.', 'secure-media-vault' ), $return );
		}
		if ( count( $files ) > $cfg['max_files'] ) {
			/* translators: %d: max files */
			self::respond( false, sprintf( __( 'You can upload up to %d files at once.', 'secure-media-vault' ), $cfg['max_files'] ), $return );
		}

		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		global $wpdb;
		$tables = SMV_Installer::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$tables['submissions'],
			array(
				'form_label' => substr( sanitize_text_field( (string) $cfg['label'] ), 0, 190 ),
				'name'       => $name,
				'email'      => $email,
				'message'    => $message,
				'fields'     => $custom ? wp_json_encode( $custom ) : '',
				'ip'         => $ip,
				'user_id'    => get_current_user_id(),
				'page_url'   => substr( esc_url_raw( $return ), 0, 500 ),
				'status'     => 'new',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$submission_id = (int) $wpdb->insert_id;

		$stored = array();
		$errors = array();
		foreach ( $files as $file ) {
			$result = SMV_Storage::handle_upload(
				$file,
				array(
					'visibility'    => 'private',
					'allowed'       => $cfg['types'],
					'max_bytes'     => SMV_Settings::get( 'form_max_size_mb' ) * MB_IN_BYTES,
					'source'        => 'form',
					'submission_id' => $submission_id,
				)
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = sanitize_file_name( $file['name'] ) . ': ' . $result->get_error_message();
			} else {
				$stored[] = $result;
			}
		}

		if ( ! $stored ) {
			$wpdb->delete( $tables['submissions'], array( 'id' => $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::respond( false, implode( ' ', $errors ), $return );
		}

		self::notify( $submission_id, $name, $email, $message, $stored, $cfg['label'], $custom );

		$msg = SMV_Settings::get( 'form_success_message' );
		if ( $errors ) {
			/* translators: %s: list of errors */
			$msg .= ' ' . sprintf( __( 'Some files were skipped: %s', 'secure-media-vault' ), implode( ' ', $errors ) );
		}
		self::respond( true, $msg, $return );
	}

	private static function notify( $submission_id, $name, $email, $message, array $stored, $label, array $custom = array() ) {
		if ( ! SMV_Settings::get( 'form_notify' ) ) {
			return;
		}
		$to = SMV_Settings::get( 'form_notify_email' );
		$to = $to ? $to : get_option( 'admin_email' );

		$lines   = array();
		$lines[] = sprintf( 'Form: %s', $label );
		if ( $name ) {
			$lines[] = sprintf( 'Name: %s', $name );
		}
		if ( $email ) {
			$lines[] = sprintf( 'Email: %s', $email );
		}
		foreach ( $custom as $row ) {
			$lines[] = sprintf( '%s: %s', $row['label'], '' === $row['value'] ? '—' : $row['value'] );
		}
		if ( $message ) {
			$lines[] = "Message:\n" . $message;
		}
		$lines[] = '';
		$lines[] = sprintf( 'Files (%d):', count( $stored ) );
		foreach ( $stored as $f ) {
			$lines[] = sprintf( ' - %s (%s)', $f->original_name, SMV_Files::human_size( $f->size ) );
		}
		$lines[] = '';
		$lines[] = 'Review: ' . admin_url( 'admin.php?page=smv-submissions&submission=' . (int) $submission_id );

		$headers = array();
		if ( $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
		}

		wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( '[%s] New secure upload', 'secure-media-vault' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			implode( "\n", $lines ),
			$headers
		);
	}
}
