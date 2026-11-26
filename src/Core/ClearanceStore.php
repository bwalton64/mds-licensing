<?php
/**
 * Cover Song Mechanical Licensing (2026 CRB Phonorecords IV Calculator) and
 * Sample / Interpolation Clearance Ledger (`_mds_lic_clearances`).
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages cover song mechanical licenses, US statutory mechanical royalty math,
 * and sample/interpolation clearance ledgers (excluded from Multisite Spoke sync).
 *
 * @since 0.1.0
 */
final class ClearanceStore {

	/**
	 * Release post meta key for private clearance ledgers.
	 *
	 * @var string
	 */
	public const META_CLEARANCES = '_mds_lic_clearances';

	/**
	 * 2026 US CRB Phonorecords IV statutory rate per track (<= 5 min).
	 *
	 * @var float
	 */
	public const RATE_2026_PER_TRACK = 0.131;

	/**
	 * 2026 US CRB Phonorecords IV statutory rate per minute (> 5 min).
	 *
	 * @var float
	 */
	public const RATE_2026_PER_MIN = 0.0252;

	/**
	 * 2025 US CRB Phonorecords IV statutory rate per track (<= 5 min).
	 *
	 * @var float
	 */
	public const RATE_2025_PER_TRACK = 0.127;

	/**
	 * 2025 US CRB Phonorecords IV statutory rate per minute (> 5 min).
	 *
	 * @var float
	 */
	public const RATE_2025_PER_MIN = 0.0245;

	/**
	 * Registers Multisite sync exclusion hook so private legal clearances stay on the Hub.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter(
			'mds_rm_sync_excluded_meta_keys',
			static function ( array $keys ): array {
				$keys[] = self::META_CLEARANCES;
				return array_values( array_unique( $keys ) );
			}
		);
	}

	/**
	 * Calculates US statutory mechanical royalties under CRB Phonorecords IV.
	 *
	 * @param int $units            Physical or permanent download units.
	 * @param int $duration_seconds Track duration in seconds (0 defaults to <= 5 min).
	 * @param int $year             Rate year (`2025` or `2026`).
	 * @return array{year:int,units:int,per_unit_rate:float,total_usd:float}
	 */
	public static function calculate_statutory_mechanical( int $units, int $duration_seconds = 0, int $year = 2026 ): array {
		$units     = max( 0, $units );
		$base_rate = 2025 === $year ? self::RATE_2025_PER_TRACK : self::RATE_2026_PER_TRACK;
		$min_rate  = 2025 === $year ? self::RATE_2025_PER_MIN : self::RATE_2026_PER_MIN;

		$per_unit = $base_rate;
		if ( $duration_seconds > 300 ) {
			$mins     = (int) ceil( $duration_seconds / 60 );
			$per_unit = max( $base_rate, round( $mins * $min_rate, 4 ) );
		}

		return array(
			'year'          => 2025 === $year ? 2025 : 2026,
			'units'         => $units,
			'per_unit_rate' => $per_unit,
			'total_usd'     => round( $per_unit * $units, 2 ),
		);
	}

	/**
	 * Reads clearance ledgers for a Release.
	 *
	 * @param int $post_id Release post ID.
	 * @return array{covers:array<int,array<string,mixed>>,samples:array<int,array<string,mixed>>}
	 */
	public static function get( int $post_id ): array {
		$raw = $post_id > 0 && function_exists( 'get_post_meta' )
			? get_post_meta( $post_id, self::META_CLEARANCES, true )
			: array();
		return self::sanitize( $raw );
	}

	/**
	 * Saves clearance ledgers for a Release.
	 *
	 * @param int   $post_id Release post ID.
	 * @param mixed $raw     Raw array or JSON string.
	 * @return array{covers:array<int,array<string,mixed>>,samples:array<int,array<string,mixed>>}
	 */
	public static function save( int $post_id, $raw ): array {
		$clean = self::sanitize( $raw );
		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_CLEARANCES, wp_slash( (string) wp_json_encode( $clean, JSON_INVALID_UTF8_SUBSTITUTE ) ) );
		}
		return $clean;
	}

	/**
	 * Sanitizes cover mechanicals and sample clearance rows.
	 *
	 * @param mixed $raw Raw array or JSON string.
	 * @return array{covers:array<int,array<string,mixed>>,samples:array<int,array<string,mixed>>}
	 */
	public static function sanitize( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		$raw = is_array( $raw ) ? $raw : array();

		$covers = array();
		if ( isset( $raw['covers'] ) && is_array( $raw['covers'] ) ) {
			foreach ( $raw['covers'] as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$orig_title = sanitize_text_field( (string) ( $c['original_title'] ?? '' ) );
				if ( '' === $orig_title ) {
					continue;
				}
				$units    = max( 0, (int) ( $c['units_licensed'] ?? 0 ) );
				$dur      = max( 0, (int) ( $c['duration_seconds'] ?? 0 ) );
				$calc     = self::calculate_statutory_mechanical( $units, $dur, 2026 );
				$covers[] = array(
					'track_title'        => sanitize_text_field( (string) ( $c['track_title'] ?? '' ) ),
					'original_title'     => $orig_title,
					'original_writer'    => sanitize_text_field( (string) ( $c['original_writer'] ?? '' ) ),
					'original_publisher' => sanitize_text_field( (string) ( $c['original_publisher'] ?? '' ) ),
					'original_iswc'      => sanitize_text_field( (string) ( $c['original_iswc'] ?? '' ) ),
					'provider'           => sanitize_text_field( (string) ( $c['provider'] ?? 'Easy Song' ) ),
					'license_number'     => sanitize_text_field( (string) ( $c['license_number'] ?? '' ) ),
					'units_licensed'     => $units,
					'duration_seconds'   => $dur,
					'estimated_cost_usd' => $calc['total_usd'],
					'status'             => 'cleared' === ( $c['status'] ?? '' ) ? 'cleared' : 'pending',
				);
			}
		}

		$samples = array();
		if ( isset( $raw['samples'] ) && is_array( $raw['samples'] ) ) {
			foreach ( $raw['samples'] as $s ) {
				if ( ! is_array( $s ) ) {
					continue;
				}
				$work = sanitize_text_field( (string) ( $s['sampled_work'] ?? '' ) );
				if ( '' === $work ) {
					continue;
				}
				$samples[] = array(
					'track_title'      => sanitize_text_field( (string) ( $s['track_title'] ?? '' ) ),
					'sampled_work'     => $work,
					'sampled_artist'   => sanitize_text_field( (string) ( $s['sampled_artist'] ?? '' ) ),
					'rights_type'      => sanitize_text_field( (string) ( $s['rights_type'] ?? 'Master + Publishing' ) ),
					'clearance_status' => sanitize_key( (string) ( $s['clearance_status'] ?? 'pending' ) ),
					'share_pct'        => max( 0.0, min( 100.0, (float) ( $s['share_pct'] ?? 0 ) ) ),
					'fee_usd'          => max( 0.0, (float) ( $s['fee_usd'] ?? 0 ) ),
					'reference_note'   => sanitize_text_field( (string) ( $s['reference_note'] ?? '' ) ),
				);
			}
		}

		return array(
			'covers'  => $covers,
			'samples' => $samples,
		);
	}
}
