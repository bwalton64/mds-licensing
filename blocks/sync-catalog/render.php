<?php
/**
 * Server-side render template for `mds-lic/sync-catalog`.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare(strict_types=1);

use MDS\Licensing\Blocks\SyncCatalogBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped inside SyncCatalogBlock::render_html().
echo SyncCatalogBlock::render_html( is_array( $attributes ?? null ) ? $attributes : array(), true );
