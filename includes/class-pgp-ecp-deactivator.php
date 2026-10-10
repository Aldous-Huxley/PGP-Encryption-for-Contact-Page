<?php
/**
 * File: class-pgp-ecp-deactivator.php
 * Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-deactivator.php
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin deactivation routines.
 */
class PGP_ECP_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		pgp_ecp_log( 'Deactivating PGP Encryption for Contact Page' );

		// Clean up temporary data.
		delete_transient( 'pgp_ecp_temp_data' );

		pgp_ecp_log( 'Plugin deactivated successfully' );
	}
}
