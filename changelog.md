# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-27

### Changed
- Initial `1.0.0` release: Sync licensing & music supervisor pitch hub (DISCO.ac CSV, Broadcast Cue Sheets), mechanical cover licensing (2026 US CRB calculator + Easy Song API), sample clearance ledger, printable Split Sheet Agreements, YouTube/Meta Content ID allowlists, and `mds-lic/sync-catalog` block.
- Hardened `POST /mds-lic/v1/sync-quote` with per-IP transient rate limiting (HTTP `429`) and published `release` validation, added `wpml-config.xml`, and switched autoloader bootstrap to `file_exists()` for Windows LocalWP resilience.

# Changelog

All notable changes to MDS Licensing are documented in this file.

## [0.1.0] - 2026-11-26

### Added
- Initial companion plugin (`MDS\Licensing`) hooking into `mds_rm_loaded` and `window.mdsRmReleaseTools`.
- Sync Licensing Profile (`_mds_lic_sync_profile`), DISCO.ac CSV exporter, and Broadcast Cue Sheet exporter (`SyncStore`, `SyncExporter`).
- Cover Song Mechanical Calculator (2026 US CRB `$0.131`/track & `$0.0252`/min), Sample/Interpolation Clearance Ledger, and Printable Split Sheet Agreement generator (`ClearanceStore`, `SplitSheetGenerator`, `EasySongClient`).
- YouTube / Meta Content ID Allowlist Ledger & CSV exporter (`ContentIdLedger`).
- Release Tools ▾ **Sync & Licensing** modal (`js/licensing-hub.js`), `mds-lic/v1` REST API, WordPress 7.x Ability (`mds-lic/get-sync-profile`), and frontend **`mds-lic/sync-catalog`** Gutenberg block + **`[sync-catalog]`** shortcode with Sync Quote Request workflow.
