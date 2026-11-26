<?php
/**
 * Optional Easy Song B2B Rights Clearance API client.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Integrations;

use MDS\Licensing\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects optionally to Easy Song's B2B Music Rights Clearance API for cover-song lookups.
 *
 * @since 0.1.0
 */
final class EasySongClient {

	/**
	 * Resolves the configured Easy Song API key (`MDS_LIC_EASY_SONG_API_KEY` constant or option).
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		if ( defined( 'MDS_LIC_EASY_SONG_API_KEY' ) && is_string( MDS_LIC_EASY_SONG_API_KEY ) && '' !== MDS_LIC_EASY_SONG_API_KEY ) {
			return MDS_LIC_EASY_SONG_API_KEY;
		}
		return Config::get_settings()['easy_song_api_key'];
	}

	/**
	 * Whether Easy Song API credentials are configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Queries Easy Song's B2B API for cover-song clearance metadata.
	 *
	 * @param string $title  Original song title.
	 * @param string $artist Original recording artist or writer.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function lookup_song( string $title, string $artist = '' ) {
		$key = self::get_api_key();
		if ( '' === $key ) {
			return new \WP_Error(
				'mds_lic_easysong_unconfigured',
				__( 'Easy Song API key is not configured. You can still track cover licenses manually and use the built-in 2026 CRB statutory calculator.', 'mds-licensing' ),
				array( 'status' => 400 )
			);
		}

		$url = add_query_arg(
			array(
				'title'  => sanitize_text_field( $title ),
				'artist' => sanitize_text_field( $artist ),
			),
			'https://api.easysong.com/v1/songs/search'
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			return new \WP_Error(
				'mds_lic_easysong_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Easy Song API returned HTTP %d.', 'mds-licensing' ),
					$code
				),
				array( 'status' => $code )
			);
		}

		return array(
			'results' => is_array( $body ) ? $body : array(),
		);
	}
}
