<?php
/**
 * Sync Licensing & Music Supervisor Metadata storage (`_mds_lic_sync_profile`).
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Core;

use MDS\Licensing\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages One-Stop status, Master/Publishing ownership %, clearance contacts,
 * BPM/key/moods/instruments, and stems availability for Releases.
 *
 * @since 0.1.0
 */
final class SyncStore {

	/**
	 * Release post meta key for Sync Licensing metadata (synced to Spokes for public Sync Catalogs).
	 *
	 * @var string
	 */
	public const META_SYNC = '_mds_lic_sync_profile';

	/**
	 * Standard stem / alternate version keys.
	 *
	 * @var string[]
	 */
	public const STEM_KEYS = array(
		'main',
		'instrumental',
		'tv_track',
		'acapella',
		'cutdown_30s',
		'cutdown_60s',
		'multitrack_stems',
	);

	/**
	 * Reads the Sync Licensing profile for a Release post.
	 *
	 * @param int $post_id Release post ID.
	 * @return array<string, mixed>
	 */
	public static function get( int $post_id ): array {
		$raw = $post_id > 0 && function_exists( 'get_post_meta' )
			? get_post_meta( $post_id, self::META_SYNC, true )
			: array();
		return self::sanitize( $raw );
	}

	/**
	 * Saves the Sync Licensing profile for a Release post.
	 *
	 * @param int   $post_id Release post ID.
	 * @param mixed $raw     Raw array or JSON string.
	 * @return array<string, mixed>
	 */
	public static function save( int $post_id, $raw ): array {
		$clean = self::sanitize( $raw );
		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_SYNC, wp_slash( (string) wp_json_encode( $clean, JSON_INVALID_UTF8_SUBSTITUTE ) ) );
		}
		return $clean;
	}

	/**
	 * Sanitizes a Sync Licensing profile payload.
	 *
	 * @param mixed $raw Raw array or JSON string.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = Config::get_settings();

		$vocals = (string) ( $raw['vocals'] ?? 'vocals' );
		if ( ! in_array( $vocals, array( 'vocals', 'instrumental', 'both' ), true ) ) {
			$vocals = 'vocals';
		}

		$stems = array();
		$in_st = is_array( $raw['stems'] ?? null ) ? $raw['stems'] : array();
		foreach ( self::STEM_KEYS as $sk ) {
			$stems[ $sk ] = ! empty( $in_st[ $sk ] );
		}

		return array(
			'sync_available'  => ! array_key_exists( 'sync_available', $raw ) || ! empty( $raw['sync_available'] ),
			'one_stop'        => array_key_exists( 'one_stop', $raw ) ? ! empty( $raw['one_stop'] ) : $defaults['default_one_stop'],
			'easy_clear'      => ! empty( $raw['easy_clear'] ),
			'master_pct'      => max( 0.0, min( 100.0, (float) ( $raw['master_pct'] ?? 100 ) ) ),
			'publishing_pct'  => max( 0.0, min( 100.0, (float) ( $raw['publishing_pct'] ?? 100 ) ) ),
			'clearance_name'  => sanitize_text_field( (string) ( $raw['clearance_name'] ?? $defaults['default_clearance_name'] ) ),
			'clearance_email' => sanitize_email( (string) ( $raw['clearance_email'] ?? $defaults['default_clearance_email'] ) ),
			'clearance_phone' => sanitize_text_field( (string) ( $raw['clearance_phone'] ?? $defaults['default_clearance_phone'] ) ),
			'bpm'             => max( 0, min( 300, (int) ( $raw['bpm'] ?? 0 ) ) ),
			'musical_key'     => sanitize_text_field( (string) ( $raw['musical_key'] ?? '' ) ),
			'moods'           => self::sanitize_tag_list( $raw['moods'] ?? array() ),
			'instruments'     => self::sanitize_tag_list( $raw['instruments'] ?? array() ),
			'sounds_like'     => sanitize_text_field( (string) ( $raw['sounds_like'] ?? '' ) ),
			'vocals'          => $vocals,
			'stems'           => $stems,
			'pitch_notes'     => sanitize_textarea_field( (string) ( $raw['pitch_notes'] ?? '' ) ),
		);
	}

	/**
	 * Normalizes comma-separated strings or arrays into clean unique tag lists.
	 *
	 * @param mixed $raw Raw string or array.
	 * @return string[]
	 */
	private static function sanitize_tag_list( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		$out = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $item ) {
				$clean = sanitize_text_field( (string) $item );
				if ( '' !== $clean && ! in_array( $clean, $out, true ) ) {
					$out[] = $clean;
				}
			}
		}
		return $out;
	}
}
