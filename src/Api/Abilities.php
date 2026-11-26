<?php
/**
 * WordPress 7.x Abilities API & MCP registration for MDS Licensing.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Api;

use MDS\Licensing\Core\SyncStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes safe read-only Sync Licensing metadata as a WordPress 7.x Ability (`meta.mcp.public`).
 *
 * @since 0.1.0
 */
final class Abilities {

	/**
	 * Registers Abilities API hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Registers `mds-lic/get-sync-profile`.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'mds-lic/get-sync-profile',
			array(
				'label'               => __( 'Get Release Sync Licensing Profile', 'mds-licensing' ),
				'description'         => __( 'Returns One-Stop/Easy-Clear status, Master/Publishing %, BPM, musical key, moods, instruments, and available stems for a Release.', 'mds-licensing' ),
				'category'            => 'mds-licensing',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( array $input ): array {
					$post_id = (int) ( $input['post_id'] ?? 0 );
					return array(
						'post_id' => $post_id,
						'title'   => $post_id > 0 ? get_the_title( $post_id ) : '',
						'sync'    => SyncStore::get( $post_id ),
					);
				},
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'mcp' => array( 'public' => true ),
				),
			)
		);
	}
}
