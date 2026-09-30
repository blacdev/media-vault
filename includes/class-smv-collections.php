<?php
/**
 * Data access for media collections (the things shortcodes render).
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Collections {

	private static function table() {
		$t = SMV_Installer::tables();
		return $t['collections'];
	}

	public static function types() {
		return array(
			'gallery' => array(
				'label' => __( 'Image gallery', 'secure-media-vault' ),
				'desc'  => __( 'Responsive grid with lightbox.', 'secure-media-vault' ),
				'group' => 'image',
			),
			'audio'   => array(
				'label' => __( 'Audio playlist', 'secure-media-vault' ),
				'desc'  => __( 'Player with a track list.', 'secure-media-vault' ),
				'group' => 'audio',
			),
			'mixed'   => array(
				'label' => __( 'Mixed media', 'secure-media-vault' ),
				'desc'  => __( 'Images and audio together.', 'secure-media-vault' ),
				'group' => '',
			),
		);
	}

	public static function default_settings() {
		return array(
			'columns'   => 3,
			'gap'       => 12,
			'ratio'     => 'square', // square | landscape | portrait | original.
			'lightbox'  => true,
			'captions'  => true,
			'autoplay'  => false,
			'loop'      => false,
			'show_list' => true,
			'download'  => false,
		);
	}

	public static function sanitize_settings( $input ) {
		$d     = self::default_settings();
		$input = is_array( $input ) ? $input : array();
		$ratio = isset( $input['ratio'] ) ? sanitize_key( $input['ratio'] ) : $d['ratio'];
		return array(
			'columns'   => isset( $input['columns'] ) ? max( 1, min( 8, absint( $input['columns'] ) ) ) : $d['columns'],
			'gap'       => isset( $input['gap'] ) ? max( 0, min( 64, absint( $input['gap'] ) ) ) : $d['gap'],
			'ratio'     => in_array( $ratio, array( 'square', 'landscape', 'portrait', 'original' ), true ) ? $ratio : $d['ratio'],
			'lightbox'  => ! empty( $input['lightbox'] ),
			'captions'  => ! empty( $input['captions'] ),
			'autoplay'  => ! empty( $input['autoplay'] ),
			'loop'      => ! empty( $input['loop'] ),
			'show_list' => ! empty( $input['show_list'] ),
			'download'  => ! empty( $input['download'] ),
		);
	}

	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$settings      = json_decode( (string) $row->settings, true );
		$items         = json_decode( (string) $row->items, true );
		$row->id       = (int) $row->id;
		$row->settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
		$row->items    = is_array( $items ) ? array_values( array_filter( array_map( 'absint', $items ) ) ) : array();
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = self::table();
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $slug ) ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function all() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC" ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	private static function unique_slug( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::table();
		$base  = sanitize_title( $slug );
		$base  = '' === $base ? 'collection' : $base;
		$try   = $base;
		$n     = 2;
		// phpcs:ignore WordPress.DB
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id != %d", $try, (int) $exclude_id ) ) ) {
			$try = $base . '-' . $n;
			++$n;
		}
		return $try;
	}

	/**
	 * Create or update. Returns the collection id.
	 */
	public static function save( $id, $title, $slug, $type, $settings, $items ) {
		global $wpdb;
		$types = self::types();
		$type  = isset( $types[ $type ] ) ? $type : 'gallery';
		$title = sanitize_text_field( $title );
		$title = '' === $title ? __( 'Untitled collection', 'secure-media-vault' ) : $title;
		$slug  = self::unique_slug( '' === (string) $slug ? $title : $slug, $id );

		// Only keep ids that exist and are public.
		$items   = array_values( array_unique( array_filter( array_map( 'absint', (array) $items ) ) ) );
		$exists  = SMV_Files::get_many( $items );
		$items   = array_values(
			array_filter(
				$items,
				function ( $fid ) use ( $exists ) {
					return isset( $exists[ $fid ] ) && 'public' === $exists[ $fid ]->visibility;
				}
			)
		);
		$now     = current_time( 'mysql', true );
		$payload = array(
			'title'      => $title,
			'slug'       => $slug,
			'type'       => $type,
			'settings'   => wp_json_encode( self::sanitize_settings( $settings ) ),
			'items'      => wp_json_encode( $items ),
			'updated_at' => $now,
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $id && self::get( $id ) ) {
			$wpdb->update( self::table(), $payload, array( 'id' => (int) $id ) );
		} else {
			$payload['created_at'] = $now;
			$wpdb->insert( self::table(), $payload );
			$id = (int) $wpdb->insert_id;
		}
		// phpcs:enable

		self::bust_cache( $id );
		return (int) $id;
	}

	public static function delete( $id ) {
		global $wpdb;
		self::bust_cache( $id );
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function duplicate( $id ) {
		$c = self::get( $id );
		if ( ! $c ) {
			return 0;
		}
		/* translators: %s: collection title */
		return self::save( 0, sprintf( __( '%s (copy)', 'secure-media-vault' ), $c->title ), $c->slug . '-copy', $c->type, $c->settings, $c->items );
	}

	/** Remove a deleted file id from every collection. */
	public static function remove_file_everywhere( $file_id ) {
		global $wpdb;
		$table = self::table();
		$like  = '%' . $wpdb->esc_like( (string) (int) $file_id ) . '%';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, items FROM {$table} WHERE items LIKE %s", $like ) ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $row ) {
			$items = json_decode( (string) $row->items, true );
			if ( ! is_array( $items ) ) {
				continue;
			}
			$new = array_values( array_diff( array_map( 'absint', $items ), array( (int) $file_id ) ) );
			if ( count( $new ) !== count( $items ) ) {
				$wpdb->update( $table, array( 'items' => wp_json_encode( $new ) ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::bust_cache( (int) $row->id );
			}
		}
	}

	/** Collections rendered output is not cached in HTML; bump a version so page caches can vary if they hook in. */
	public static function bust_cache( $id ) {
		do_action( 'smv_collection_updated', (int) $id );
	}

	public static function shortcode_for( $collection ) {
		return sprintf( '[smv_collection id="%d"]', (int) $collection->id );
	}
}
