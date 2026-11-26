<?php
/**
 * Frontend Sync Catalog & Quote Request block (`mds-lic/sync-catalog`) and `[sync-catalog]` shortcode.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing\Blocks;

use MDS\Licensing\Config;
use MDS\Licensing\Core\SyncStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the filterable Sync Catalog & Quote Request UI.
 *
 * @since 0.1.0
 */
final class SyncCatalogBlock {

	/**
	 * Registers block and shortcode hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_block_and_shortcode' ) );
	}

	/**
	 * Registers the `mds-lic/sync-catalog` block and `[sync-catalog]` shortcode.
	 *
	 * @return void
	 */
	public static function register_block_and_shortcode(): void {
		$dir = MDS_LIC_PATH . 'blocks/sync-catalog';
		if ( function_exists( 'register_block_type' ) && file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'sync-catalog', array( self::class, 'handle_shortcode' ) );
		}
	}

	/**
	 * Shortcode handler for `[sync-catalog]`.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function handle_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit'         => 24,
				'one_stop_only' => 'false',
				'show_quote'    => 'true',
			),
			is_array( $atts ) ? $atts : array(),
			'sync-catalog'
		);

		$css = 'blocks/sync-catalog/style.css';
		if ( file_exists( MDS_LIC_PATH . $css ) ) {
			wp_enqueue_style( 'mds-lic-sync-catalog', MDS_LIC_URL . $css, array(), (string) filemtime( MDS_LIC_PATH . $css ) );
		}
		$js = 'blocks/sync-catalog/view.js';
		if ( file_exists( MDS_LIC_PATH . $js ) ) {
			wp_enqueue_script( 'mds-lic-sync-catalog-view', MDS_LIC_URL . $js, array(), (string) filemtime( MDS_LIC_PATH . $js ), true );
		}

		return self::render_html(
			array(
				'limit'       => max( 1, min( 100, (int) $atts['limit'] ) ),
				'oneStopOnly' => in_array( strtolower( (string) $atts['one_stop_only'] ), array( '1', 'true', 'yes' ), true ),
				'showQuote'   => ! in_array( strtolower( (string) $atts['show_quote'] ), array( '0', 'false', 'no' ), true ),
			),
			false
		);
	}

	/**
	 * Renders escaped Sync Catalog HTML with client-side filter bar and Sync Quote Request dialog.
	 *
	 * @param array<string, mixed> $attributes Block or shortcode attributes.
	 * @param bool                 $is_block   Whether rendered via `render.php`.
	 * @return string
	 */
	public static function render_html( array $attributes, bool $is_block = false ): string {
		$limit         = max( 1, min( 100, (int) ( $attributes['limit'] ?? 24 ) ) );
		$one_stop_only = ! empty( $attributes['oneStopOnly'] );
		$show_quote    = ! array_key_exists( 'showQuote', $attributes ) || ! empty( $attributes['showQuote'] );

		$posts = function_exists( 'get_posts' )
			? get_posts(
				array(
					'post_type'      => 'release',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			)
			: array();

		$items = array();
		foreach ( $posts as $post ) {
			$sync = SyncStore::get( (int) $post->ID );
			if ( empty( $sync['sync_available'] ) ) {
				continue;
			}
			if ( $one_stop_only && empty( $sync['one_stop'] ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
				'url'   => function_exists( 'get_permalink' ) ? (string) get_permalink( $post ) : '',
				'sync'  => $sync,
			);
		}

		$quote_endpoint = function_exists( 'rest_url' ) ? rest_url( Config::REST_NAMESPACE . '/sync-quote' ) : '/wp-json/' . Config::REST_NAMESPACE . '/sync-quote';
		$wrapper_attr   = $is_block && function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes(
				array(
					'class'               => 'mds-lic-catalog',
					'data-quote-endpoint' => esc_url( $quote_endpoint ),
				)
			)
			: 'class="mds-lic-catalog" data-quote-endpoint="' . esc_url( $quote_endpoint ) . '"';

		$h  = '<section ' . $wrapper_attr . ' aria-label="' . esc_attr__( 'Sync Licensing Catalog', 'mds-licensing' ) . '">';
		$h .= '<div class="mds-lic-catalog__filters">';
		$h .= '<input type="search" class="mds-lic-catalog__search" placeholder="' . esc_attr__( 'Filter by title, mood, instrument, or sounds-like…', 'mds-licensing' ) . '" aria-label="' . esc_attr__( 'Search sync catalog', 'mds-licensing' ) . '" />';
		$h .= '<label class="mds-lic-catalog__check"><input type="checkbox" class="mds-lic-catalog__onestop" /> ' . esc_html__( 'One-Stop Only', 'mds-licensing' ) . '</label>';
		$h .= '<label class="mds-lic-catalog__check"><input type="checkbox" class="mds-lic-catalog__stems" /> ' . esc_html__( 'Instrumental Available', 'mds-licensing' ) . '</label>';
		$h .= '</div>';

		if ( empty( $items ) ) {
			$h .= '<p class="mds-lic-catalog__empty">' . esc_html__( 'No sync-cleared releases are currently listed.', 'mds-licensing' ) . '</p>';
		} else {
			$h .= '<div class="mds-lic-catalog__grid">';
			foreach ( $items as $item ) {
				$s          = $item['sync'];
				$moods_str  = implode( ', ', is_array( $s['moods'] ?? null ) ? $s['moods'] : array() );
				$inst_str   = implode( ', ', is_array( $s['instruments'] ?? null ) ? $s['instruments'] : array() );
				$search_idx = strtolower( $item['title'] . ' ' . $moods_str . ' ' . $inst_str . ' ' . (string) ( $s['sounds_like'] ?? '' ) );
				$has_inst   = ! empty( $s['stems']['instrumental'] ) ? '1' : '0';

				$h .= '<article class="mds-lic-catalog__card" data-search="' . esc_attr( $search_idx ) . '" data-onestop="' . ( ! empty( $s['one_stop'] ) ? '1' : '0' ) . '" data-instrumental="' . esc_attr( $has_inst ) . '">';
				$h .= '<div class="mds-lic-catalog__card-head">';
				$h .= '<h3 class="mds-lic-catalog__title"><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></h3>';
				if ( ! empty( $s['one_stop'] ) ) {
					$h .= '<span class="mds-lic-catalog__badge mds-lic-catalog__badge--onestop">' . esc_html__( 'One-Stop', 'mds-licensing' ) . '</span>';
				}
				$h .= '</div>';

				$meta_bits = array();
				if ( ! empty( $s['bpm'] ) ) {
					$meta_bits[] = (int) $s['bpm'] . ' BPM';
				}
				if ( ! empty( $s['musical_key'] ) ) {
					$meta_bits[] = (string) $s['musical_key'];
				}
				if ( ! empty( $meta_bits ) ) {
					$h .= '<p class="mds-lic-catalog__meta">' . esc_html( implode( ' • ', $meta_bits ) ) . '</p>';
				}
				if ( '' !== $moods_str ) {
					$h .= '<p class="mds-lic-catalog__tags"><strong>' . esc_html__( 'Moods:', 'mds-licensing' ) . '</strong> ' . esc_html( $moods_str ) . '</p>';
				}
				if ( $show_quote ) {
					$h .= '<button type="button" class="mds-lic-catalog__quote-btn" data-release-id="' . esc_attr( (string) $item['id'] ) . '" data-release-title="' . esc_attr( $item['title'] ) . '">' . esc_html__( 'Request Sync Quote', 'mds-licensing' ) . '</button>';
				}
				$h .= '</article>';
			}
			$h .= '</div>';
		}

		if ( $show_quote ) {
			$h .= '<dialog class="mds-lic-dialog" aria-labelledby="mds-lic-dialog-title">';
			$h .= '<form method="dialog" class="mds-lic-quote-form">';
			$h .= '<h3 id="mds-lic-dialog-title">' . esc_html__( 'Request Sync License Quote', 'mds-licensing' ) . '</h3>';
			$h .= '<p class="mds-lic-quote-target"></p>';
			$h .= '<input type="hidden" name="release_id" value="0" />';
			$h .= '<input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true" />';
			$h .= '<label>' . esc_html__( 'Your Name *', 'mds-licensing' ) . '<input type="text" name="requester_name" required /></label>';
			$h .= '<label>' . esc_html__( 'Email Address *', 'mds-licensing' ) . '<input type="email" name="requester_email" required /></label>';
			$h .= '<label>' . esc_html__( 'Production / Brand / Agency', 'mds-licensing' ) . '<input type="text" name="company" /></label>';
			$h .= '<label>' . esc_html__( 'Media Usage', 'mds-licensing' ) . '<select name="media_type"><option>Film / TV</option><option>Commercial / Brand Ad</option><option>Video Game</option><option>YouTube / Podcast / Social</option><option>Corporate / Internal</option></select></label>';
			$h .= '<label>' . esc_html__( 'Territory & Term', 'mds-licensing' ) . '<input type="text" name="territory" value="Worldwide / Perpetuity" /></label>';
			$h .= '<label>' . esc_html__( 'Project Notes / Budget', 'mds-licensing' ) . '<textarea name="notes" rows="3"></textarea></label>';
			$h .= '<p class="mds-lic-quote-status" role="status" aria-live="polite"></p>';
			$h .= '<div class="mds-lic-dialog__actions">';
			$h .= '<button type="submit" class="mds-lic-catalog__quote-btn">' . esc_html__( 'Send Quote Request', 'mds-licensing' ) . '</button>';
			$h .= '<button type="button" class="mds-lic-dialog__close">' . esc_html__( 'Close', 'mds-licensing' ) . '</button>';
			$h .= '</div>';
			$h .= '</form></dialog>';
		}

		$h .= '</section>';
		return $h;
	}
}
