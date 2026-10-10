<?php
/**
 * File: class-pgp-ecp-activator.php
 * Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-activator.php
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin activation routines.
 */
class PGP_ECP_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		pgp_ecp_log( 'Activating PGP Encryption for Contact Page' );

		if ( ! extension_loaded( 'gnupg' ) ) {
			pgp_ecp_log( 'Activation failed - PHP gnupg extension not loaded.' );
			wp_die( esc_html__( 'PGP Encryption for Contact Page requires the PHP gnupg extension. Please install it and try again.', 'pgp-encryption-for-contact-page' ) );
		}

		// Set default options.
		$options = get_option( 'pgp_ecp_options', array() );
		if ( empty( $options ) ) {
			$options = array(
				'pgp_public_key'           => '',
				'pgp_recipient_email'      => '',
				'pgp_admin_url'            => '',
				'pgp_enable_debugging'     => 0,
				'pgp_enable_turnstile'     => 0,
				'pgp_turnstile_site_key'   => '',
				'pgp_turnstile_secret_key' => '',
			);
			update_option( 'pgp_ecp_options', $options );
			pgp_ecp_log( 'Default options set during activation' );
		}

		pgp_ecp_log( 'Plugin activated successfully' );
	}
}
