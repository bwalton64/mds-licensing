<?php
/**
 * REST API endpoints for MDS Licensing (`mds-lic/v1`).
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Api;

use MDS\Licensing\Config;
use MDS\Licensing\Core\ClearanceStore;
use MDS\Licensing\Core\ContentIdLedger;
use MDS\Licensing\Core\SyncStore;
use MDS\Licensing\Integrations\EasySongClient;
use MDS\Licensing\Outbound\SplitSheetGenerator;
use MDS\Licensing\Outbound\SyncExporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all `mds-lic/v1` REST API endpoints.
 *
 * @since 0.1.0
 */
final class RestApi {

	/**
	 * Registers REST API hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers all `mds-lic/v1` routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$can_edit_release = static function ( \WP_REST_Request $request ): bool {
			$post_id = (int) $request->get_param( 'id' );
			return $post_id > 0 ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
		};

		register_rest_route(
			Config::REST_NAMESPACE,
			'/releases/(?P<id>\d+)/licensing',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'handle_get_licensing' ),
					'permission_callback' => $can_edit_release,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'handle_save_licensing' ),
					'permission_callback' => $can_edit_release,
				),
			)
		);

		register_rest_route(
			Config::REST_NAMESPACE,
			'/clearances/easysong-lookup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle_easysong_lookup' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);

		register_rest_route(
			Config::REST_NAMESPACE,
			'/sync-quote',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle_sync_quote' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST handler: GET `/releases/{id}/licensing`.
	 *
	 * @param \WP_REST_Request $request Request with Release `id`.
	 * @return \WP_REST_Response
	 */
	public static function handle_get_licensing( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		return rest_ensure_response( self::build_release_payload( $post_id ) );
	}

	/**
	 * REST handler: POST `/releases/{id}/licensing`.
	 *
	 * @param \WP_REST_Request $request Request with Release `id` and licensing payloads.
	 * @return \WP_REST_Response
	 */
	public static function handle_save_licensing( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$body    = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		if ( isset( $body['sync'] ) ) {
			SyncStore::save( $post_id, $body['sync'] );
		}
		if ( isset( $body['clearances'] ) ) {
			ClearanceStore::save( $post_id, $body['clearances'] );
		}
		if ( isset( $body['allowlist'] ) ) {
			ContentIdLedger::save( $post_id, $body['allowlist'] );
		}

		$payload            = self::build_release_payload( $post_id );
		$payload['message'] = __( 'Licensing, clearances, and Content ID allowlist saved.', 'mds-licensing' );

		return rest_ensure_response( $payload );
	}

	/**
	 * REST handler: POST `/clearances/easysong-lookup`.
	 *
	 * @param \WP_REST_Request $request Request with `title` and optional `artist`.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_easysong_lookup( \WP_REST_Request $request ) {
		$title  = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		$artist = sanitize_text_field( (string) ( $request->get_param( 'artist' ) ?? '' ) );
		$res    = EasySongClient::lookup_song( $title, $artist );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( $res );
	}

	/**
	 * Public REST handler: POST `/sync-quote` (Sync Licensing Quote Request from frontend block).
	 *
	 * @param \WP_REST_Request $request Quote submission payload.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_sync_quote( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		// Honeypot anti-spam field.
		if ( ! empty( $body['website_url'] ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}

		// Per-IP transient rate limiting (max 5 quote requests per 10 minutes) to prevent wp_mail() & option-table abuse.
		if ( function_exists( 'get_transient' ) && function_exists( 'set_transient' ) ) {
			$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$rate_key  = 'mds_lic_sq_rl_' . md5( $remote_ip );
			$attempts  = (int) get_transient( $rate_key );
			if ( $attempts >= 5 ) {
				return new \WP_Error(
					'mds_lic_quote_rate_limited',
					__( 'Too many quote requests. Please wait a few minutes and try again.', 'mds-licensing' ),
					array( 'status' => 429 )
				);
			}
			set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
		}

		$raw_release_id = (int) ( $body['release_id'] ?? 0 );
		$release_id     = ( $raw_release_id > 0 && 'release' === get_post_type( $raw_release_id ) && 'publish' === get_post_status( $raw_release_id ) )
			? $raw_release_id
			: 0;
		$name           = sanitize_text_field( (string) ( $body['requester_name'] ?? '' ) );
		$email          = sanitize_email( (string) ( $body['requester_email'] ?? '' ) );
		$company        = sanitize_text_field( (string) ( $body['company'] ?? '' ) );
		$media_type     = sanitize_text_field( (string) ( $body['media_type'] ?? 'Film / TV' ) );
		$territory      = sanitize_text_field( (string) ( $body['territory'] ?? 'Worldwide' ) );
		$term           = sanitize_text_field( (string) ( $body['term'] ?? 'Perpetuity' ) );
		$budget         = sanitize_text_field( (string) ( $body['budget'] ?? '' ) );
		$notes          = sanitize_textarea_field( (string) ( $body['notes'] ?? '' ) );

		if ( '' === $name || '' === $email ) {
			return new \WP_Error( 'mds_lic_quote_invalid', __( 'Please provide your name and email address.', 'mds-licensing' ), array( 'status' => 400 ) );
		}

		$release_title = $release_id > 0 ? get_the_title( $release_id ) : __( 'General Catalog', 'mds-licensing' );
		$entry         = array(
			'id'            => uniqid( 'sq_', true ),
			'release_id'    => $release_id,
			'release_title' => $release_title,
			'name'          => $name,
			'email'         => $email,
			'company'       => $company,
			'media_type'    => $media_type,
			'territory'     => $territory,
			'term'          => $term,
			'budget'        => $budget,
			'notes'         => $notes,
			'submitted_at'  => gmdate( 'c' ),
		);

		$quotes   = get_option( Config::OPTION_QUOTES, array() );
		$quotes   = is_array( $quotes ) ? $quotes : array();
		$quotes[] = $entry;
		if ( count( $quotes ) > 500 ) {
			$quotes = array_slice( $quotes, -500 );
		}
		update_option( Config::OPTION_QUOTES, $quotes, false );

		$sync   = $release_id > 0 ? SyncStore::get( $release_id ) : array();
		$notify = ! empty( $sync['clearance_email'] ) ? (string) $sync['clearance_email'] : Config::get_settings()['quote_notify_email'];
		if ( '' !== $notify && function_exists( 'wp_mail' ) ) {
			$subject = sprintf( '[Sync Quote Request] %s — %s', $release_title, $media_type );
			$msg     = sprintf(
				"New Sync Licensing Quote Request:\n\nRelease: %s (ID: %d)\nRequester: %s <%s>\nCompany: %s\nMedia Buy: %s\nTerritory: %s\nTerm: %s\nBudget: %s\n\nNotes:\n%s\n",
				$release_title,
				$release_id,
				$name,
				$email,
				$company,
				$media_type,
				$territory,
				$term,
				$budget,
				$notes
			);
			wp_mail( $notify, $subject, $msg );
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Your sync licensing quote request has been submitted.', 'mds-licensing' ),
			)
		);
	}

	/**
	 * Builds the full licensing payload for a Release post.
	 *
	 * @param int $post_id Release post ID.
	 * @return array<string, mixed>
	 */
	private static function build_release_payload( int $post_id ): array {
		return array(
			'sync'                => SyncStore::get( $post_id ),
			'clearances'          => ClearanceStore::get( $post_id ),
			'allowlist'           => ContentIdLedger::get( $post_id ),
			'easysong_configured' => EasySongClient::is_configured(),
			'rates'               => array(
				'2026_per_track' => ClearanceStore::RATE_2026_PER_TRACK,
				'2026_per_min'   => ClearanceStore::RATE_2026_PER_MIN,
			),
			'exports'             => array(
				'disco_csv'        => SyncExporter::build_disco_csv( $post_id ),
				'cue_sheet_csv'    => SyncExporter::build_cue_sheet_csv( $post_id ),
				'split_sheet_html' => SplitSheetGenerator::build_html( $post_id ),
				'allowlist_csv'    => ContentIdLedger::build_csv( $post_id ),
			),
		);
	}
}
