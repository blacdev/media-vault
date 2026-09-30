<?php
/**
 * [smv_collection] shortcode rendering.
 *
 * Usage: [smv_collection id="3"]  or  [smv_collection slug="my-gallery"]
 * Optional overrides: columns="4" captions="no" lightbox="no" autoplay="yes" loop="yes"
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Shortcodes {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_everywhere' ), 20 );
		add_shortcode( 'smv_collection', array( __CLASS__, 'collection' ) );

		// Elementor editor preview renders widgets after page load: make sure our assets are there.
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_now' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_now' ) );
	}

	/**
	 * Themes that swap page content with AJAX (e.g. Pro Radio, which keeps its radio player
	 * playing between pages) don't load the CSS/JS a newly shown page asks for.
	 */
	public static function ajax_theme_detected() {
		$theme = strtolower( get_template() . ' ' . get_stylesheet() );
		$found = false !== strpos( $theme, 'proradio' ) || false !== strpos( $theme, 'pro-radio' );
		return (bool) apply_filters( 'smv_ajax_navigation_theme', $found );
	}

	/**
	 * Loading on every page is strictly opt-in ("always"). By default nothing is added to
	 * pages that don't use the plugin, so the rest of the site is untouched.
	 */
	public static function load_everywhere() {
		return 'always' === SMV_Settings::get( 'frontend_assets' );
	}

	public static function maybe_enqueue_everywhere() {
		if ( self::load_everywhere() ) {
			self::enqueue_now();
		}
	}

	private static function front_config() {
		return array(
			'cssUrl'      => esc_url_raw( add_query_arg( 'ver', SMV_VERSION, SMV_URL . 'assets/css/frontend.css' ) ),
			'pauseOthers' => (bool) SMV_Settings::get( 'pause_other_media' ),
			'i18n'        => array(
				'close'     => __( 'Close', 'secure-media-vault' ),
				'next'      => __( 'Next image', 'secure-media-vault' ),
				'prev'      => __( 'Previous image', 'secure-media-vault' ),
				'uploading' => __( 'Uploading…', 'secure-media-vault' ),
				'remove'    => __( 'Remove', 'secure-media-vault' ),
				'tooMany'   => __( 'You selected too many files.', 'secure-media-vault' ),
				'tooBig'    => __( 'is too large.', 'secure-media-vault' ),
				'badType'   => __( 'is not an allowed file type.', 'secure-media-vault' ),
			),
		);
	}

	/**
	 * On AJAX-navigation themes, a gallery/player/form carries its own stylesheet and script
	 * inside its HTML, so it still works when the theme swaps it into the page — without
	 * the plugin having to load anything on pages that don't use it. Printed once per page.
	 */
	public static function inline_assets() {
		static $printed = false;
		if ( $printed || self::load_everywhere() || ! self::ajax_theme_detected() || is_admin() || self::is_elementor_preview() ) {
			return '';
		}
		$printed = true;
		$cfg     = self::front_config();
		$css     = add_query_arg( 'ver', SMV_VERSION, SMV_URL . 'assets/css/frontend.css' );
		$js      = add_query_arg( 'ver', SMV_VERSION, SMV_URL . 'assets/js/frontend.js' );
		// A tiny inline loader instead of <link>/<script src> tags: optimisation plugins move or
		// combine those out of the content, which AJAX page loaders then never see. The data-*
		// attributes ask Autoptimize, LiteSpeed, WP Rocket and Cloudflare to leave it alone.
		$loader = 'window.smvFront=window.smvFront||' . wp_json_encode( $cfg ) . ';'
			. '(function(d){'
			. 'if(!d.getElementById("smv-frontend-css")){var l=d.createElement("link");l.id="smv-frontend-css";l.rel="stylesheet";l.href=' . wp_json_encode( esc_url_raw( $css ) ) . ';d.head.appendChild(l);}'
			. 'if(!window.smvFrontLoaded&&!d.getElementById("smv-frontend-js")){var s=d.createElement("script");s.id="smv-frontend-js";s.src=' . wp_json_encode( esc_url_raw( $js ) ) . ';d.head.appendChild(s);}'
			. '})(document);';
		return '<script data-noptimize="1" data-no-optimize="1" data-no-minify="1" data-cfasync="false" nowprocket>' . $loader . '</script>';
	}

	/** Link target for images: on AJAX themes, "#noajax" makes the theme leave the click to the browser. */
	private static function link_url( $file ) {
		return SMV_Files::url( $file ) . ( self::ajax_theme_detected() ? '#noajax' : '' );
	}

	public static function register_assets() {
		wp_register_style( 'smv-frontend', SMV_URL . 'assets/css/frontend.css', array(), SMV_VERSION );
		wp_register_script( 'smv-frontend', SMV_URL . 'assets/js/frontend.js', array(), SMV_VERSION, true );
		wp_localize_script( 'smv-frontend', 'smvFront', self::front_config() );
	}

	public static function enqueue() {
		// On AJAX-navigation themes the component carries its own assets (see inline_assets()).
		if ( self::ajax_theme_detected() && ! self::load_everywhere() && ! is_admin() && ! self::is_elementor_preview() ) {
			return;
		}
		self::enqueue_now();
	}

	public static function enqueue_now() {
		if ( ! wp_style_is( 'smv-frontend', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'smv-frontend' );
		wp_enqueue_script( 'smv-frontend' );
	}

	private static function is_elementor_preview() {
		return isset( $_GET['elementor-preview'] ) || did_action( 'elementor/preview/init' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function bool_attr( $value, $fallback ) {
		if ( null === $value || '' === $value ) {
			return (bool) $fallback;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on' ), true );
	}

	public static function collection( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => '',
				'slug'     => '',
				'columns'  => '',
				'captions' => null,
				'lightbox' => null,
				'autoplay' => null,
				'loop'     => null,
				'class'    => '',
			),
			$atts,
			'smv_collection'
		);

		$collection = $atts['id'] ? SMV_Collections::get( absint( $atts['id'] ) ) : ( $atts['slug'] ? SMV_Collections::get_by_slug( $atts['slug'] ) : null );
		if ( ! $collection ) {
			return current_user_can( SMV_Plugin::capability() ) ? '<p class="smv-notice">' . esc_html__( 'Secure Media Vault: collection not found.', 'secure-media-vault' ) . '</p>' : '';
		}

		$files = SMV_Files::get_many( $collection->items );
		$items = array();
		foreach ( $collection->items as $fid ) {
			// Only public files of an enabled format are ever shown.
			if ( isset( $files[ $fid ] ) && 'public' === $files[ $fid ]->visibility && in_array( $files[ $fid ]->file_group, SMV_Settings::active_groups(), true ) ) {
				$items[] = $files[ $fid ];
			}
		}
		if ( ! $items ) {
			return current_user_can( SMV_Plugin::capability() ) ? '<p class="smv-notice">' . esc_html__( 'Secure Media Vault: this collection is empty.', 'secure-media-vault' ) . '</p>' : '';
		}

		$s = $collection->settings;
		if ( '' !== $atts['columns'] ) {
			$s['columns'] = max( 1, min( 8, absint( $atts['columns'] ) ) );
		}
		$s['captions'] = self::bool_attr( $atts['captions'], $s['captions'] );
		$s['lightbox'] = self::bool_attr( $atts['lightbox'], $s['lightbox'] );
		$s['autoplay'] = self::bool_attr( $atts['autoplay'], $s['autoplay'] );
		$s['loop']     = self::bool_attr( $atts['loop'], $s['loop'] );

		$extra = implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );

		self::enqueue();

		switch ( $collection->type ) {
			case 'audio':
			case 'video':
				$html = self::render_playlist( $items, $s, $collection->type );
				break;
			case 'files':
				$html = self::render_files( $items );
				break;
			case 'mixed':
				$html = self::render_mixed( $items, $s );
				break;
			default:
				$html = self::render_gallery( $items, $s );
		}

		return self::inline_assets() . sprintf(
			'<div class="smv smv-collection smv-collection--%1$s %2$s" data-smv-collection="%3$d">%4$s</div>',
			esc_attr( $collection->type ),
			esc_attr( $extra ),
			(int) $collection->id,
			$html
		);
	}

	private static function render_gallery( array $items, array $s ) {
		$out = sprintf(
			'<div class="smv-gallery smv-ratio-%1$s" style="--smv-cols:%2$d;--smv-gap:%3$dpx"%4$s>',
			esc_attr( $s['ratio'] ),
			(int) $s['columns'],
			(int) $s['gap'],
			$s['lightbox'] ? ' data-smv-lightbox' : ''
		);
		foreach ( $items as $i => $f ) {
			if ( 'image' !== $f->file_group ) {
				continue;
			}
			$caption = $f->caption ? $f->caption : $f->title;
			$img     = sprintf(
				'<img src="%1$s" alt="%2$s" loading="lazy" decoding="async"%3$s>',
				esc_url( SMV_Files::thumb_url( $f ) ),
				esc_attr( $f->title ),
				$f->width ? sprintf( ' width="%d" height="%d"', (int) $f->width, (int) $f->height ) : ''
			);
			$out    .= '<figure class="smv-gallery__item">';
			if ( $s['lightbox'] ) {
				$out .= sprintf(
					'<a class="smv-gallery__link" href="%1$s" data-smv-caption="%2$s" data-elementor-open-lightbox="no">%3$s</a>',
					esc_url( self::link_url( $f ) ),
					esc_attr( $caption ),
					$img
				);
			} else {
				$out .= $img;
			}
			if ( $s['captions'] && $caption ) {
				$out .= '<figcaption class="smv-gallery__caption">' . esc_html( $caption ) . '</figcaption>';
			}
			$out .= '</figure>';
		}
		return $out . '</div>';
	}

	/** Download link: Dropbox links force a download; "#noajax" keeps AJAX-navigation themes out. */
	private static function download_url( $file ) {
		$url = SMV_Files::url( $file );
		if ( false !== strpos( $url, 'dropbox' ) ) {
			$url = add_query_arg( 'dl', '1', remove_query_arg( 'raw', $url ) );
		}
		if ( self::ajax_theme_detected() ) {
			$url .= '#noajax';
		}
		return $url;
	}

	/**
	 * Audio or video playlist.
	 *
	 * @param object[] $items Files in order.
	 * @param array    $s     Display settings.
	 * @param string   $kind  audio | video.
	 */
	private static function render_playlist( array $items, array $s, $kind = 'audio' ) {
		$kind   = 'video' === $kind ? 'video' : 'audio';
		$tracks = array_values(
			array_filter(
				$items,
				function ( $f ) use ( $kind ) {
					return $kind === $f->file_group;
				}
			)
		);
		if ( ! $tracks ) {
			return '';
		}
		$first = $tracks[0];

		$out  = sprintf(
			'<div class="smv-playlist smv-playlist--%1$s"%2$s%3$s>',
			esc_attr( $kind ),
			$s['autoplay'] ? ' data-smv-autoplay' : '',
			$s['loop'] ? ' data-smv-loop' : ''
		);
		$out .= '<div class="smv-playlist__stage">';
		if ( 'audio' === $kind ) {
			$out .= '<div class="smv-playlist__now"><span class="smv-playlist__eyebrow">' . esc_html__( 'Now playing', 'secure-media-vault' ) . '</span>';
			$out .= '<strong class="smv-playlist__title" aria-live="polite">' . esc_html( $first->title ) . '</strong></div>';
		}
		$out .= sprintf(
			'<%1$s class="smv-playlist__media" controls preload="metadata" playsinline%3$s src="%2$s"></%1$s>',
			$kind,
			esc_url( SMV_Files::url( $first ) ),
			$s['download'] ? '' : ' controlsList="nodownload"'
		);
		$out .= '</div>';

		$hide = ( ! $s['show_list'] || count( $tracks ) < 2 ) ? ' hidden' : '';
		$out .= '<ol class="smv-playlist__list"' . $hide . '>';
		foreach ( $tracks as $i => $f ) {
			$out .= sprintf(
				'<li><button type="button" class="smv-playlist__track%1$s" data-src="%2$s" data-title="%3$s"%4$s><span class="smv-playlist__num">%5$d</span><span class="smv-playlist__name">%6$s</span><span class="smv-playlist__meta">%7$s</span></button></li>',
				0 === $i ? ' is-active' : '',
				esc_url( SMV_Files::url( $f ) ),
				esc_attr( $f->title ),
				0 === $i ? ' aria-current="true"' : '',
				$i + 1,
				esc_html( $f->title ),
				esc_html( strtoupper( $f->ext ) )
			);
		}
		return $out . '</ol></div>';
	}

	private static function file_row( $f ) {
		return sprintf(
			'<li class="smv-files__row"><span class="smv-files__icon smv-files__icon--%1$s" aria-hidden="true">%2$s</span><span class="smv-files__meta"><span class="smv-files__name">%3$s</span><span class="smv-files__info">%4$s · %2$s</span></span><a class="smv-files__btn" href="%5$s" download rel="nofollow">%6$s<span class="screen-reader-text"> %3$s</span></a></li>',
			esc_attr( $f->file_group ),
			esc_html( strtoupper( $f->ext ) ),
			esc_html( $f->title ),
			esc_html( SMV_Files::human_size( $f->size ) ),
			esc_url( self::download_url( $f ) ),
			esc_html__( 'Download', 'secure-media-vault' )
		);
	}

	private static function render_files( array $items ) {
		$out = '<ul class="smv-files">';
		foreach ( $items as $f ) {
			$out .= self::file_row( $f );
		}
		return $out . '</ul>';
	}

	/** Every item shown by its type, in the collection's order. */
	private static function render_mixed( array $items, array $s ) {
		$out   = '';
		$files = array();
		$flush = function () use ( &$files, &$out ) {
			if ( $files ) {
				$out  .= '<ul class="smv-files">' . implode( '', $files ) . '</ul>';
				$files = array();
			}
		};
		foreach ( $items as $f ) {
			$caption = $f->caption ? $f->caption : $f->title;
			$figcap  = $s['captions'] ? '<figcaption class="smv-gallery__caption">' . esc_html( $caption ) . '</figcaption>' : '';
			if ( 'image' === $f->file_group ) {
				$flush();
				$out .= sprintf(
					'<figure class="smv-mixed__item smv-mixed__item--image"%4$s><a class="smv-gallery__link" href="%1$s" data-smv-caption="%3$s" data-elementor-open-lightbox="no"><img src="%2$s" alt="%3$s" loading="lazy" decoding="async"></a>%5$s</figure>',
					esc_url( self::link_url( $f ) ),
					esc_url( SMV_Files::thumb_url( $f ) ),
					esc_attr( $caption ),
					$s['lightbox'] ? ' data-smv-lightbox' : '',
					$figcap
				);
			} elseif ( 'audio' === $f->file_group || 'video' === $f->file_group ) {
				$flush();
				$tag  = 'video' === $f->file_group ? 'video' : 'audio';
				$out .= sprintf(
					'<figure class="smv-mixed__item smv-mixed__item--%1$s"><%1$s controls preload="metadata" playsinline src="%2$s"></%1$s>%3$s</figure>',
					$tag,
					esc_url( SMV_Files::url( $f ) ),
					$figcap
				);
			} else {
				$files[] = self::file_row( $f );
			}
		}
		$flush();
		return '<div class="smv-mixed">' . $out . '</div>';
	}
}
