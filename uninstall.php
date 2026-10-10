<?php
/**
 * File: uninstall.php
 * Path: pgp-encryption-for-contact-page/uninstall.php
 *
 * Removes plugin data when the plugin is deleted.
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly.
}

// Remove plugin options and transients.
delete_option( 'pgp_ecp_options' );
delete_transient( 'pgp_ecp_temp_data' );

// Remove the plugin's private debug log directory (wp-content/uploads/pgp-ecp-logs).
$pgp_ecp_uninstall_upload = wp_upload_dir( null, false );
$pgp_ecp_uninstall_logdir = trailingslashit( $pgp_ecp_uninstall_upload['basedir'] ) . 'pgp-ecp-logs';
if ( is_dir( $pgp_ecp_uninstall_logdir ) ) {
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( WP_Filesystem() && $wp_filesystem ) {
		$wp_filesystem->delete( $pgp_ecp_uninstall_logdir, true, 'd' );
	}
}
