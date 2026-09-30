<?php
/**
 * Data access for stored files.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Files {

	private static function table() {
		$t = SMV_Installer::tables();
		return $t['files'];
	}

	public static function insert( array $data ) {
		global $wpdb;
		$data = wp_parse_args(
			$data,
			array(
				'title'          => '',
				'caption'        => '',
				'original_name'  => '',
				'file_name'      => '',
				'rel_path'       => '',
				'thumb_path'     => '',
				'ext'            => '',
				'mime'           => '',
				'file_group'     => '',
				'size'           => 0,
				'width'          => 0,
				'height'         => 0,
				'visibility'     => 'public',
				'source'         => 'admin',
				'storage'        => 'local',
				'storage_target' => 'local',
				'dropbox_path'   => '',
				'dropbox_url'    => '',
				'sync_status'    => '',
				'sync_error'     => '',
				'submission_id'  => 0,
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql', true ),
			)
		);
		$ok   = $wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @param int[] $ids
	 * @return object[] keyed by id
	 */
	public static function get_many( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore WordPress.DB
		$out          = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->id ] = $row;
		}
		return $out;
	}

	/**
	 * @return array{0: object[], 1: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'group'         => '',
				'visibility'    => 'public',
				'search'        => '',
				'submission_id' => 0,
				'page'          => 1,
				'per_page'      => 40,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['visibility'] ) {
			$where[]  = 'visibility = %s';
			$params[] = $args['visibility'];
		}
		if ( $args['group'] && 'all' !== $args['group'] ) {
			$where[]  = 'file_group = %s';
			$params[] = $args['group'];
		} else {
			// Only images and audio are supported; files of other types from older versions stay hidden.
			$where[] = "file_group IN ('image','audio')";
		}
		if ( $args['submission_id'] ) {
			$where[]  = 'submission_id = %d';
			$params[] = (int) $args['submission_id'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR original_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$table    = self::table();
		$sql_w    = implode( ' AND ', $where );
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$sql_w}", $params ) : "SELECT COUNT(*) FROM {$table} WHERE {$sql_w}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$sql_w} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) );
		// phpcs:enable

		return array( $rows, $total );
	}

	/** Bytes used by every stored file (public, private, local or Dropbox) — what the storage limit counts. */
	public static function total_bytes() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(size),0) FROM {$table}" ); // phpcs:ignore WordPress.DB
	}

	public static function counts() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT file_group, COUNT(*) AS c, SUM(size) AS s FROM {$table} WHERE visibility = 'public' AND file_group IN ('image','audio') GROUP BY file_group" ); // phpcs:ignore WordPress.DB
		$out   = array(
			'all'   => 0,
			'bytes' => 0,
		);
		foreach ( $rows as $r ) {
			$out[ $r->file_group ] = (int) $r->c;
			$out['all']           += (int) $r->c;
			$out['bytes']         += (int) $r->s;
		}
		return $out;
	}

	/**
	 * Delete the DB row, local files and Dropbox copy, and detach from collections.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$file = self::get( $id );
		if ( ! $file ) {
			return false;
		}
		SMV_Storage::delete_physical( $file );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		SMV_Collections::remove_file_everywhere( (int) $id );
		return true;
	}

	// -----------------------------------------------------------------------
	// Presentation helpers
	// -----------------------------------------------------------------------

	/** Public URL for a public file (local first, Dropbox fallback). */
	public static function url( $file ) {
		if ( 'private' === $file->visibility ) {
			return SMV_Download::admin_url( (int) $file->id );
		}
		if ( $file->rel_path && 'dropbox' !== $file->storage ) {
			return SMV_Storage::rel_to_url( $file->rel_path );
		}
		if ( $file->dropbox_url ) {
			return $file->dropbox_url;
		}
		return $file->rel_path ? SMV_Storage::rel_to_url( $file->rel_path ) : '';
	}

	public static function thumb_url( $file ) {
		if ( 'image' !== $file->file_group ) {
			return '';
		}
		if ( 'private' === $file->visibility ) {
			return SMV_Download::admin_url( (int) $file->id, true );
		}
		if ( $file->thumb_path ) {
			return SMV_Storage::rel_to_url( $file->thumb_path );
		}
		return self::url( $file );
	}

	/** "44 B", "30.0 KB", "1.2 MB" — no pointless decimals on bytes. */
	public static function human_size( $bytes ) {
		$bytes = (int) $bytes;
		return size_format( $bytes, $bytes >= KB_IN_BYTES ? 1 : 0 );
	}

	public static function to_array( $file ) {
		return array(
			'id'         => (int) $file->id,
			'title'      => $file->title,
			'caption'    => (string) $file->caption,
			'name'       => $file->original_name,
			'ext'        => $file->ext,
			'group'      => $file->file_group,
			'mime'       => $file->mime,
			'size'       => (int) $file->size,
			'sizeHuman'  => self::human_size( $file->size ),
			'width'      => (int) $file->width,
			'height'     => (int) $file->height,
			'url'        => self::url( $file ),
			'thumb'      => self::thumb_url( $file ),
			'storage'    => $file->storage,
			'syncStatus' => $file->sync_status,
			'syncError'  => (string) $file->sync_error,
			'visibility' => $file->visibility,
			'date'       => mysql2date( get_option( 'date_format' ), get_date_from_gmt( $file->created_at ) ),
		);
	}
}
