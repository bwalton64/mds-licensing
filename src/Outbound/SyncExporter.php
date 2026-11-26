<?php
/**
 * DISCO.ac Music Supervisor CSV and Broadcast Cue Sheet (RapidCue / ASCAP / BMI / SoundMouse) exporters.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Outbound;

use MDS\Licensing\Core\SyncStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates DISCO.ac ingestion CSVs and Broadcast Cue Sheets from Release & Sync metadata.
 *
 * @since 0.1.0
 */
final class SyncExporter {

	/**
	 * Builds a DISCO.ac-ready track metadata CSV for music supervisor pitching.
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function build_disco_csv( int $post_id ): string {
		$album  = get_the_title( $post_id );
		$sync   = SyncStore::get( $post_id );
		$tracks = self::get_tracks( $post_id );

		$active_stems = array();
		foreach ( is_array( $sync['stems'] ?? null ) ? $sync['stems'] : array() as $k => $v ) {
			if ( ! empty( $v ) ) {
				$active_stems[] = $k;
			}
		}

		$rows = array(
			array(
				'Track Title',
				'Album',
				'ISRC',
				'ISWC',
				'BPM',
				'Key',
				'Moods',
				'Instruments',
				'Sounds Like',
				'Vocals',
				'One-Stop',
				'Easy-Clear',
				'Master %',
				'Publishing %',
				'Clearance Contact',
				'Stems Available',
				'Pitch Notes',
			),
		);

		foreach ( $tracks as $t ) {
			$rows[] = array(
				(string) ( $t['title'] ?? $album ),
				$album,
				(string) ( $t['isrc'] ?? '' ),
				(string) ( $t['iswc'] ?? '' ),
				(string) ( $sync['bpm'] ?? 0 ),
				(string) ( $sync['musical_key'] ?? '' ),
				implode( '; ', is_array( $sync['moods'] ?? null ) ? $sync['moods'] : array() ),
				implode( '; ', is_array( $sync['instruments'] ?? null ) ? $sync['instruments'] : array() ),
				(string) ( $sync['sounds_like'] ?? '' ),
				(string) ( $sync['vocals'] ?? 'vocals' ),
				! empty( $sync['one_stop'] ) ? 'Yes' : 'No',
				! empty( $sync['easy_clear'] ) ? 'Yes' : 'No',
				(string) ( $sync['master_pct'] ?? 100 ),
				(string) ( $sync['publishing_pct'] ?? 100 ),
				trim( (string) ( $sync['clearance_name'] ?? '' ) . ' <' . (string) ( $sync['clearance_email'] ?? '' ) . '>' ),
				implode( ', ', $active_stems ),
				(string) ( $sync['pitch_notes'] ?? '' ),
			);
		}

		return self::rows_to_csv( $rows );
	}

	/**
	 * Builds a Broadcast Cue Sheet CSV (RapidCue / ASCAP / BMI / SoundMouse format).
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function build_cue_sheet_csv( int $post_id ): string {
		$album  = get_the_title( $post_id );
		$tracks = self::get_tracks( $post_id );

		$rows = array(
			array( 'Cue #', 'Cue Title', 'ISRC', 'ISWC', 'Usage Type', 'Duration (MM:SS)', 'Composer / Writer', 'PRO', 'IPI', 'Share %', 'Publisher' ),
		);

		foreach ( $tracks as $idx => $t ) {
			$dur_sec = (int) ( $t['duration'] ?? 0 );
			$dur_fmt = sprintf( '%02d:%02d', (int) floor( $dur_sec / 60 ), $dur_sec % 60 );
			$splits  = is_array( $t['splits'] ?? null ) ? $t['splits'] : array();
			if ( empty( $splits ) ) {
				$rows[] = array(
					(string) ( $idx + 1 ),
					(string) ( $t['title'] ?? $album ),
					(string) ( $t['isrc'] ?? '' ),
					(string) ( $t['iswc'] ?? '' ),
					'BI (Background Instrumental/Vocal)',
					$dur_fmt,
					'',
					'',
					'',
					'100',
					'',
				);
				continue;
			}
			foreach ( $splits as $sp ) {
				$rows[] = array(
					(string) ( $idx + 1 ),
					(string) ( $t['title'] ?? $album ),
					(string) ( $t['isrc'] ?? '' ),
					(string) ( $t['iswc'] ?? '' ),
					'BI (Background Instrumental/Vocal)',
					$dur_fmt,
					(string) ( $sp['name'] ?? '' ),
					(string) ( $sp['pro'] ?? '' ),
					(string) ( $sp['ipi'] ?? '' ),
					(string) ( $sp['share_pct'] ?? 0 ),
					'publisher' === ( $sp['type'] ?? '' ) ? (string) ( $sp['name'] ?? '' ) : '',
				);
			}
		}

		return self::rows_to_csv( $rows );
	}

	/**
	 * Resolves tracklist from `mds-metadata` if installed, else returns a single fallback track.
	 *
	 * @param int $post_id Release post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_tracks( int $post_id ): array {
		if ( class_exists( '\MDS\Metadata\Core\CatalogMeta' ) ) {
			$tracks = \MDS\Metadata\Core\CatalogMeta::get_tracklist( $post_id );
			if ( ! empty( $tracks ) ) {
				return $tracks;
			}
		}
		return array(
			array(
				'number'   => 1,
				'title'    => get_the_title( $post_id ),
				'isrc'     => '',
				'iswc'     => '',
				'duration' => 0,
				'splits'   => array(),
			),
		);
	}

	/**
	 * Encodes rows into RFC 4180 CSV with LF endings.
	 *
	 * @param array<int, string[]> $rows CSV rows.
	 * @return string
	 */
	private static function rows_to_csv( array $rows ): string {
		$lines = array();
		foreach ( $rows as $r ) {
			$lines[] = implode( ',', array_map( static fn( string $c ): string => '"' . str_replace( '"', '""', $c ) . '"', $r ) );
		}
		return implode( "\n", $lines ) . "\n";
	}
}
