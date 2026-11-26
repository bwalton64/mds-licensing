<?php
/**
 * YouTube Content ID & Meta Rights Manager Allowlist / Whitelist Ledger (`_mds_lic_content_id_allowlist`).
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
 * Tracks licensed YouTube/Meta/TikTok channels exempted from automated Content ID claims
 * and generates distributor/CMS allowlist CSVs.
 *
 * @since 0.1.0
 */
final class ContentIdLedger {

	/**
	 * Release post meta key for Content ID allowlist entries (Hub-only).
	 *
	 * @var string
	 */
	public const META_ALLOWLIST = '_mds_lic_content_id_allowlist';

	/**
	 * Registers Multisite sync exclusion hook.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter(
			'mds_rm_sync_excluded_meta_keys',
			static function ( array $keys ): array {
				$keys[] = self::META_ALLOWLIST;
				return array_values( array_unique( $keys ) );
			}
		);
	}

	/**
	 * Reads allowlist entries for a Release.
	 *
	 * @param int $post_id Release post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get( int $post_id ): array {
		$raw = $post_id > 0 && function_exists( 'get_post_meta' )
			? get_post_meta( $post_id, self::META_ALLOWLIST, true )
			: array();
		return self::sanitize( $raw );
	}

	/**
	 * Saves allowlist entries for a Release.
	 *
	 * @param int   $post_id Release post ID.
	 * @param mixed $raw     Raw array or JSON string.
	 * @return array<int, array<string, mixed>>
	 */
	public static function save( int $post_id, $raw ): array {
		$clean = self::sanitize( $raw );
		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_ALLOWLIST, wp_slash( (string) wp_json_encode( $clean, JSON_INVALID_UTF8_SUBSTITUTE ) ) );
		}
		return $clean;
	}

	/**
	 * Sanitizes allowlist rows.
	 *
	 * @param mixed $raw Raw array or JSON string.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$target = sanitize_text_field( (string) ( $row['channel_or_video_url'] ?? '' ) );
			if ( '' === $target ) {
				continue;
			}
			$out[] = array(
				'platform'             => sanitize_text_field( (string) ( $row['platform'] ?? 'YouTube Content ID' ) ),
				'client_name'          => sanitize_text_field( (string) ( $row['client_name'] ?? '' ) ),
				'channel_or_video_url' => $target,
				'license_ref'          => sanitize_text_field( (string) ( $row['license_ref'] ?? '' ) ),
				'expires_at'           => sanitize_text_field( (string) ( $row['expires_at'] ?? '' ) ),
				'active'               => ! array_key_exists( 'active', $row ) || ! empty( $row['active'] ),
			);
		}
		return $out;
	}

	/**
	 * Exports active allowlist entries as a CMS / Distributor CSV.
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function build_csv( int $post_id ): string {
		$title   = get_the_title( $post_id );
		$upc     = (string) get_post_meta( $post_id, 'barcode', true );
		$entries = self::get( $post_id );

		$rows = array(
			array( 'Release Title', 'UPC', 'Platform', 'Client / Brand', 'Channel ID or Video URL', 'License Ref', 'Expires At', 'Status' ),
		);
		foreach ( $entries as $e ) {
			$rows[] = array(
				$title,
				$upc,
				(string) $e['platform'],
				(string) $e['client_name'],
				(string) $e['channel_or_video_url'],
				(string) $e['license_ref'],
				(string) $e['expires_at'],
				! empty( $e['active'] ) ? 'Active' : 'Revoked',
			);
		}

		$lines = array();
		foreach ( $rows as $r ) {
			$lines[] = implode( ',', array_map( static fn( string $c ): string => '"' . str_replace( '"', '""', $c ) . '"', $r ) );
		}
		return implode( "\n", $lines ) . "\n";
	}
}
