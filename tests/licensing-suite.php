<?php
/**
 * Standalone PHP test suite for MDS Licensing.
 *
 * Run with: php tests/licensing-suite.php
 *
 * @package MDS\Licensing
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['mds_lic_test_options']   = array();
$GLOBALS['mds_lic_test_post_meta'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	/** Minimal WP_Error stub. */
	class WP_Error {
		/** @var string */
		private string $code;
		/** @var string */
		private string $message;
		/**
		 * @param string $code Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
function sanitize_key( string $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''; }
function sanitize_text_field( string $str ): string { return trim( strip_tags( $str ) ); }
function sanitize_textarea_field( string $str ): string { return trim( strip_tags( $str ) ); }
function sanitize_email( string $email ): string { return trim( $email ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $data, int $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_slash( $value ) { return $value; }
function get_option( string $key, $default = false ) { return $GLOBALS['mds_lic_test_options'][ $key ] ?? $default; }
function update_option( string $key, $value, $autoload = null ): bool { unset( $autoload ); $GLOBALS['mds_lic_test_options'][ $key ] = $value; return true; }
function get_post_meta( int $post_id, string $key, bool $single = false ) { unset( $single ); return $GLOBALS['mds_lic_test_post_meta'][ $post_id ][ $key ] ?? ''; }
function update_post_meta( int $post_id, string $key, $value ): bool { $GLOBALS['mds_lic_test_post_meta'][ $post_id ][ $key ] = $value; return true; }
function get_the_title( int $post_id = 0 ): string { unset( $post_id ); return 'Sync Test Release'; }

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use MDS\Licensing\Core\ClearanceStore;
use MDS\Licensing\Core\ContentIdLedger;
use MDS\Licensing\Core\SyncStore;
use MDS\Licensing\Integrations\EasySongClient;
use MDS\Licensing\Outbound\SplitSheetGenerator;
use MDS\Licensing\Outbound\SyncExporter;

$failures = 0;
function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "[PASS] {$label}\n";
	} else {
		echo "[FAIL] {$label}\n";
		++$failures;
	}
}

// 1. SyncStore & DISCO / Cue Sheet exporters.
SyncStore::save(
	42,
	array(
		'one_stop'        => true,
		'master_pct'      => 100,
		'publishing_pct'  => 100,
		'bpm'             => 124,
		'musical_key'     => 'Am',
		'moods'           => 'Dark, Driving',
		'instruments'     => 'Synth, Drums',
		'clearance_name'  => 'Brian Walton',
		'clearance_email' => 'sync@mustydungeon.com',
		'stems'           => array( 'main' => true, 'instrumental' => true ),
	)
);
$sync = SyncStore::get( 42 );
assert_true( true === $sync['one_stop'] && 124 === $sync['bpm'] && in_array( 'Dark', $sync['moods'], true ), 'SyncStore saves and sanitizes One-Stop, BPM, and moods' );

$disco = SyncExporter::build_disco_csv( 42 );
assert_true( false !== strpos( $disco, '"One-Stop"' ) && false !== strpos( $disco, '"124"' ) && false !== strpos( $disco, 'instrumental' ), 'SyncExporter builds DISCO.ac CSV with BPM, One-Stop, and stems' );

$cue = SyncExporter::build_cue_sheet_csv( 42 );
assert_true( false !== strpos( $cue, '"Cue Title"' ) && false !== strpos( $cue, 'Sync Test Release' ), 'SyncExporter builds Broadcast Cue Sheet CSV' );

// 2. 2026 CRB Phonorecords IV Statutory Mechanical Calculator & ClearanceStore.
$calc_std  = ClearanceStore::calculate_statutory_mechanical( 1000, 210, 2026 );
$calc_long = ClearanceStore::calculate_statutory_mechanical( 1000, 365, 2026 ); // 7 min * $0.0252 = $0.1764
assert_true( 131.0 === $calc_std['total_usd'] && 176.4 === $calc_long['total_usd'], 'ClearanceStore calculates 2026 US CRB mechanicals ($0.131/trk and $0.0252/min > 5m)' );

ClearanceStore::save(
	42,
	array(
		'covers'  => array(
			array(
				'original_title'   => 'Classic Cover',
				'original_writer'  => 'Jane Composer',
				'units_licensed'   => 500,
				'duration_seconds' => 200,
			),
		),
		'samples' => array(
			array(
				'sampled_work'     => 'Amen Break',
				'clearance_status' => 'cleared',
				'share_pct'        => 15,
			),
		),
	)
);
$clr = ClearanceStore::get( 42 );
assert_true( 65.5 === $clr['covers'][0]['estimated_cost_usd'] && 'cleared' === $clr['samples'][0]['clearance_status'], 'ClearanceStore persists covers with auto-calculated royalties and sample clearances' );

// 3. Printable Split Sheet Agreement.
$split_html = SplitSheetGenerator::build_html( 42 );
assert_true( false !== strpos( $split_html, 'Split Sheet Agreement' ) && false !== strpos( $split_html, 'Sync Test Release' ), 'SplitSheetGenerator builds printable Split Sheet Agreement HTML' );

// 4. Content ID Allowlist Ledger.
ContentIdLedger::save(
	42,
	array(
		array(
			'platform'             => 'YouTube Content ID',
			'client_name'          => 'Acme Brand',
			'channel_or_video_url' => 'https://youtube.com/@acmebrand',
			'license_ref'          => 'LIC-2026-001',
			'active'               => true,
		),
	)
);
$cid_csv = ContentIdLedger::build_csv( 42 );
assert_true( false !== strpos( $cid_csv, 'https://youtube.com/@acmebrand' ) && false !== strpos( $cid_csv, 'LIC-2026-001' ), 'ContentIdLedger exports YouTube/Meta allowlist CSV' );

// 5. EasySongClient graceful unconfigured check.
$es = EasySongClient::lookup_song( 'Test Song' );
assert_true( is_wp_error( $es ) && 'mds_lic_easysong_unconfigured' === $es->get_error_code(), 'EasySongClient reports unconfigured gracefully when no API key is set' );

echo "\nTotal failures: {$failures}\n";
exit( $failures > 0 ? 1 : 0 );
