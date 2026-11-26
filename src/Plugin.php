<?php
/**
 * Main plugin coordinator for MDS Licensing.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\Licensing;

use MDS\Licensing\Admin\ReleaseEditor;
use MDS\Licensing\Api\Abilities;
use MDS\Licensing\Api\RestApi;
use MDS\Licensing\Blocks\SyncCatalogBlock;
use MDS\Licensing\Core\ClearanceStore;
use MDS\Licensing\Core\ContentIdLedger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots all MDS Licensing subsystems once MDS Release Manager is loaded.
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * Prevents duplicate boot.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Registers all hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		ClearanceStore::register_hooks();
		ContentIdLedger::register_hooks();
		ReleaseEditor::register_hooks();
		RestApi::register_hooks();
		Abilities::register_hooks();
		SyncCatalogBlock::register_hooks();
	}
}
