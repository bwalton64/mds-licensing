<?php
/**
 * Plugin Name: MDS Licensing
 * Plugin URI: https://mustydungeon.com
 * Description: Sync licensing, music supervisor pitch hub (DISCO.ac CSV, Cue Sheets), mechanical cover licensing (2026 CRB calculator + Easy Song API), sample clearance ledger, printable Split Sheets, and YouTube/Meta Content ID allowlists for MDS Release Manager.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Update URI: https://github.com/bwalton64/mds-licensing
 * Text Domain: mds-licensing
 * Domain Path: /languages
 * Author: Musty Dungeon Studios
 * Author URI: https://mustydungeon.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_LIC_VERSION' ) ) {
	define( 'MDS_LIC_VERSION', '1.0.0' );
}
if ( ! defined( 'MDS_LIC_FILE' ) ) {
	define( 'MDS_LIC_FILE', __FILE__ );
}
if ( ! defined( 'MDS_LIC_PATH' ) ) {
	define( 'MDS_LIC_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MDS_LIC_URL' ) ) {
	define( 'MDS_LIC_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MDS_LIC_BASENAME' ) ) {
	define( 'MDS_LIC_BASENAME', plugin_basename( __FILE__ ) );
}

$mds_lic_autoload = MDS_LIC_PATH . 'vendor/autoload.php';
if ( file_exists( $mds_lic_autoload ) ) {
	require_once $mds_lic_autoload;
}

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'mds-licensing', false, dirname( MDS_LIC_BASENAME ) . '/languages' );
	}
);

add_action(
	'mds_rm_loaded',
	static function (): void {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::register_github_updater(
				array(
					'basename'       => MDS_LIC_BASENAME,
					'slug'           => 'mds-licensing',
					'repo'           => 'bwalton64/mds-licensing',
					'version'        => MDS_LIC_VERSION,
					'name'           => 'MDS Licensing',
					'author'         => 'Musty Dungeon Studios',
					'homepage'       => 'https://mustydungeon.com',
					'token_constant' => 'MDS_LIC_GITHUB_TOKEN',
					'requires'       => '7.0',
					'tested'         => '7.0',
					'requires_php'   => '8.0',
				)
			);
		}

		if ( class_exists( \MDS\Licensing\Plugin::class ) ) {
			\MDS\Licensing\Plugin::boot();
		}
	}
);
