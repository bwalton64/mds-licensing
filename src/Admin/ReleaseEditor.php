<?php
/**
 * Enqueues the Sync & Licensing Hub modal on Release CPT edit screens.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Admin;

use MDS\Licensing\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates MDS Licensing workflows into MDS Release Manager's top-bar `Release Tools ▾`.
 *
 * @since 0.1.0
 */
final class ReleaseEditor {

	/**
	 * Registers admin enqueue hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ), 26 );
	}

	/**
	 * Enqueues `js/licensing-hub.js` on Release edit screens (Hub-only on Multisite).
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( class_exists( '\MDS\ReleaseManager\Api\ExtensionApi' ) && ! \MDS\ReleaseManager\Api\ExtensionApi::is_hub() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'release' !== $screen->post_type ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post ID inspection.
		if ( $post_id <= 0 && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$post_id = (int) $GLOBALS['post']->ID;
		}

		$js_rel = 'js/licensing-hub.js';
		if ( ! file_exists( MDS_LIC_PATH . $js_rel ) ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_script(
			'mds-lic-hub',
			MDS_LIC_URL . $js_rel,
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ),
			(string) filemtime( MDS_LIC_PATH . $js_rel ),
			true
		);
		wp_set_script_translations( 'mds-lic-hub', 'mds-licensing' );

		$boot    = array(
			'postId'   => $post_id,
			'restBase' => '/' . Config::REST_NAMESPACE,
		);
		$encoded = wp_json_encode( $boot, JSON_INVALID_UTF8_SUBSTITUTE );
		wp_add_inline_script(
			'mds-lic-hub',
			'window.mdsLicensingEditor = ' . ( is_string( $encoded ) ? $encoded : '{}' ) . ';',
			'before'
		);
	}
}
