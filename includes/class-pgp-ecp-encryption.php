<?php
/**
 * File: class-pgp-ecp-encryption.php
 * Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-encryption.php
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Wraps the PHP gnupg extension.
 */
class PGP_ECP_Encryption {

	/**
	 * Gnupg instance.
	 *
	 * @var gnupg
	 */
	private $gpg;

	/**
	 * Armored public key.
	 *
	 * @var string
	 */
	private $public_key;

	/**
	 * Fingerprint of the imported key.
	 *
	 * @var string|null
	 */
	private $fingerprint;

	/**
	 * Constructor.
	 */
	public function __construct() {
		pgp_ecp_log( 'PGP_ECP_Encryption constructor called' );
		$this->gpg         = new gnupg();
		$options           = get_option( 'pgp_ecp_options', array() );
		$this->public_key  = isset( $options['pgp_public_key'] ) ? $options['pgp_public_key'] : '';
		$this->fingerprint = null;
	}

	/**
	 * Import the PGP public key.
	 *
	 * @return bool
	 */
	public function import_public_key() {
		if ( empty( $this->public_key ) ) {
			pgp_ecp_log( 'No PGP public key set.' );
			return false;
		}

		if ( null !== $this->fingerprint ) {
			pgp_ecp_log( 'PGP key already imported, skipping' );
			return true;
		}

		try {
			$import = $this->gpg->import( $this->public_key );
			if ( ! $import || empty( $import['fingerprint'] ) ) {
				pgp_ecp_log( 'Failed to import PGP public key: Invalid key or import error.' );
				return false;
			}

			$this->fingerprint = $import['fingerprint'];
			pgp_ecp_log( 'PGP key imported successfully, fingerprint: ' . $this->fingerprint );
			return true;
		} catch ( Exception $e ) {
			pgp_ecp_log( 'PGP key import failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Encrypt the message.
	 *
	 * @param string $message Plain text message.
	 * @return string|bool Armored ciphertext or false.
	 */
	public function encrypt_message( $message ) {
		try {
			if ( ! $this->fingerprint ) {
				pgp_ecp_log( 'No valid PGP key fingerprint available.' );
				return false;
			}

			$this->gpg->addencryptkey( $this->fingerprint );
			$encrypted = $this->gpg->encrypt( $message );

			if ( ! $encrypted ) {
				pgp_ecp_log( 'Message encryption failed: No encrypted data returned.' );
				return false;
			}

			pgp_ecp_log( 'Message encrypted successfully' );
			return $encrypted;
		} catch ( Exception $e ) {
			pgp_ecp_log( 'Message encryption failed: ' . $e->getMessage() );
			return false;
		}
	}
}
