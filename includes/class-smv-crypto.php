<?php
/**
 * Authenticated encryption for secrets stored in the database (Dropbox tokens, app secret).
 *
 * Uses AES-256-GCM with a key derived from SMV_ENCRYPTION_KEY (if defined in wp-config.php)
 * or the site's AUTH salts. Changing the key/salts invalidates stored secrets; you'd then
 * simply reconnect Dropbox.
 *
 * @package SecureMediaVault
 */

defined( 'ABSPATH' ) || exit;

class SMV_Crypto {

	const PREFIX = 'smv1:';

	private static function key() {
		$material = defined( 'SMV_ENCRYPTION_KEY' ) && SMV_ENCRYPTION_KEY
			? SMV_ENCRYPTION_KEY
			: wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		return hash( 'sha256', 'smv-secrets|' . $material, true );
	}

	public static function available() {
		return function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}

	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}
		if ( ! self::available() ) {
			// Extremely rare on modern hosts. Store base64 so it's at least not plain text in dumps.
			return 'b64:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return '';
		}
		return self::PREFIX . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function decrypt( $payload ) {
		$payload = (string) $payload;
		if ( '' === $payload ) {
			return '';
		}
		if ( 0 === strpos( $payload, 'b64:' ) ) {
			return (string) base64_decode( substr( $payload, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		if ( 0 !== strpos( $payload, self::PREFIX ) || ! self::available() ) {
			return '';
		}
		$raw = base64_decode( substr( $payload, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $plain ? '' : $plain;
	}
}
