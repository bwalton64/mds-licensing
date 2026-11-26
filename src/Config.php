<?php
/**
 * Configuration and option helpers for MDS Licensing.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central configuration constants and settings storage for MDS Licensing.
 *
 * @since 0.1.0
 */
final class Config {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'mds-lic/v1';

	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	public const OPTION_SETTINGS = 'mds_lic_settings';

	/**
	 * Option key for stored Sync Quote Requests.
	 *
	 * @var string
	 */
	public const OPTION_QUOTES = 'mds_lic_quote_requests';

	/**
	 * Returns sanitized plugin settings.
	 *
	 * @return array{default_clearance_name:string,default_clearance_email:string,default_clearance_phone:string,default_one_stop:bool,quote_notify_email:string,easy_song_api_key:string}
	 */
	public static function get_settings(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION_SETTINGS, array() ) : array();
		return self::sanitize_settings( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Updates plugin settings.
	 *
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array{default_clearance_name:string,default_clearance_email:string,default_clearance_phone:string,default_one_stop:bool,quote_notify_email:string,easy_song_api_key:string}
	 */
	public static function update_settings( array $input ): array {
		$merged = array_merge( self::get_settings(), $input );
		$clean  = self::sanitize_settings( $merged );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_SETTINGS, $clean, false );
		}
		return $clean;
	}

	/**
	 * Sanitizes plugin settings.
	 *
	 * @param array<string, mixed> $raw Raw array.
	 * @return array{default_clearance_name:string,default_clearance_email:string,default_clearance_phone:string,default_one_stop:bool,quote_notify_email:string,easy_song_api_key:string}
	 */
	public static function sanitize_settings( array $raw ): array {
		$admin_email = function_exists( 'get_option' ) ? (string) get_option( 'admin_email', '' ) : '';
		return array(
			'default_clearance_name'  => sanitize_text_field( (string) ( $raw['default_clearance_name'] ?? '' ) ),
			'default_clearance_email' => sanitize_email( (string) ( $raw['default_clearance_email'] ?? $admin_email ) ),
			'default_clearance_phone' => sanitize_text_field( (string) ( $raw['default_clearance_phone'] ?? '' ) ),
			'default_one_stop'        => ! array_key_exists( 'default_one_stop', $raw ) || ! empty( $raw['default_one_stop'] ),
			'quote_notify_email'      => sanitize_email( (string) ( $raw['quote_notify_email'] ?? $admin_email ) ),
			'easy_song_api_key'       => sanitize_text_field( (string) ( $raw['easy_song_api_key'] ?? '' ) ),
		);
	}
}
