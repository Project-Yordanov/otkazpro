<?php
/**
 * Uninstall: delete settings. Withdrawal records are kept as evidence unless
 * "Delete data on uninstall" is enabled.
 *
 * @package PY\Otkaz
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$py_otkaz_delete_data = 'yes' === get_option( 'py_otkaz_delete_data' );

// Settings.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'py\\_otkaz\\_%' AND option_name <> 'py_otkaz_db_version'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
// Transients (rate limit, success tokens, release cache).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_py\\_otkaz\\_%' OR option_name LIKE '\\_transient\\_timeout\\_py\\_otkaz\\_%' OR option_name LIKE '\\_site\\_transient\\_py\\_otkaz\\_%' OR option_name LIKE '\\_site\\_transient\\_timeout\\_py\\_otkaz\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_clear_scheduled_hook( 'py_otkaz_daily_cleanup' );
wp_clear_scheduled_hook( 'py_otkaz_cache_check_once' );

if ( $py_otkaz_delete_data ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}py_otkaz_submissions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	delete_option( 'py_otkaz_db_version' );

	// Order history meta (HPOS + legacy) and product / category flags.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)", '_py_otkaz_history', '_py_otkaz_excluded', '_py_otkaz_reason', '_py_otkaz_reason_text' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN (%s, %s, %s)", 'py_otkaz_excluded', 'py_otkaz_reason', 'py_otkaz_reason_text' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$py_otkaz_hpos_meta = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $py_otkaz_hpos_meta ) ) === $py_otkaz_hpos_meta ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$py_otkaz_hpos_meta} WHERE meta_key = %s", '_py_otkaz_history' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
