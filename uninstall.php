<?php
/**
 * Uninstall cleanup for MDS Licensing.
 *
 * Preserves licensing ledgers unless `mds_lic_delete_data_on_uninstall` is explicitly enabled.
 *
 * @package   MDS\Licensing
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( '1' !== (string) get_option( 'mds_lic_delete_data_on_uninstall', '0' ) ) {
	return;
}

delete_option( 'mds_lic_settings' );
delete_option( 'mds_lic_quote_requests' );
delete_option( 'mds_lic_delete_data_on_uninstall' );

$mds_lic_meta_keys = array(
	'_mds_lic_sync_profile',
	'_mds_lic_clearances',
	'_mds_lic_content_id_allowlist',
);

foreach ( $mds_lic_meta_keys as $mds_lic_key ) {
	delete_metadata( 'post', 0, $mds_lic_key, '', true );
}
