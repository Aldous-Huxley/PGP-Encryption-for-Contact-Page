<?php
/**
 * File: pgp-encryption-for-contact-page.php
 * Path: pgp-encryption-for-contact-page/pgp-encryption-for-contact-page.php
 *
 * Plugin Name:       PGP Encryption for Contact Page
 * Plugin URI:        https://blockstreamtechnologies.llc/pgp-encryption-for-wordpress-contact-pages/
 * Description:       Adds PGP encryption to WordPress contact forms, allowing secure email communication with public key encryption. <strong>Like this plugin? Please <a href="https://buymeacoffee.com/stanghellini" title="Buy the developer of PGP Encryption for Contact Page a coffee">donate</a>. &hearts; Thank you!</strong>
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Robert Stanghellini
 * Author URI:        https://blockstreamtechnologies.llc
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pgp-encryption-for-contact-page
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'PGP_ECP_VERSION', '2.1.0' );
define( 'PGP_ECP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PGP_ECP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if debugging is enabled. Controlled only by the plugin's "Enable Debugging Logs"
 * admin option; independent of WP_DEBUG.
 *
 * @return bool
 */
function pgp_ecp_is_debug_enabled() {
	$options = get_option( 'pgp_ecp_options', array() );
	return ! empty( $options['pgp_enable_debugging'] );
}

/**
 * Redact an email address for logging: first character of the local part, fixed 8 asterisks,
 * "@", fixed 5 asterisks, "." and the last domain label only (e.g. "A********@*****.com").
 * Asterisk counts are fixed so the real length is not revealed.
 *
 * @param mixed $email Email address (any input is accepted).
 * @return string Redacted value; "[redacted-email]" for empty or invalid input.
 */
function pgp_ecp_redact_email( $email ) {
	$fallback = '[redacted-email]';
	if ( ! is_string( $email ) ) {
		return $fallback;
	}
	$email = trim( $email );
	$at    = strrpos( $email, '@' );
	if ( false === $at || 0 === $at ) {
		return $fallback;
	}
	$local  = substr( $email, 0, $at );
	$domain = rtrim( substr( $email, $at + 1 ), '.' );
	$labels = explode( '.', $domain );
	$tld    = end( $labels );
	if ( '' === $local || count( $labels ) < 2 || ! preg_match( '/^[a-z0-9-]{1,63}$/i', $tld ) ) {
		return $fallback;
	}
	$first = function_exists( 'mb_substr' ) ? mb_substr( $local, 0, 1, 'UTF-8' ) : substr( $local, 0, 1 );
	if ( ! preg_match( '/^[\p{L}\p{N}]$/u', $first ) ) {
		$first = '*';
	}
	return $first . '********@*****.' . strtolower( $tld );
}

/**
 * Replace every email-like substring in a string with its redacted form (defense in depth).
 *
 * @param string $text Text to scrub.
 * @return string
 */
function pgp_ecp_scrub_emails( $text ) {
	$result = preg_replace_callback(
		'/[^\s@<>()\[\]:;,"\'\/\\\\]+@[^\s@<>()\[\]:;,"\'\/\\\\]+/u',
		static function ( $m ) {
			return pgp_ecp_redact_email( $m[0] );
		},
		(string) $text
	);
	// On a regex failure (e.g. invalid UTF-8) never fall back to the raw text.
	return null === $result ? '[log message withheld: could not be scrubbed]' : $result;
}

/**
 * Directory that holds the plugin's private debug log.
 *
 * @return string Absolute path without trailing slash.
 */
function pgp_ecp_log_dir() {
	$upload = wp_upload_dir( null, false );
	return trailingslashit( $upload['basedir'] ) . 'pgp-ecp-logs';
}

/**
 * Absolute path of the plugin's private debug log. The file name is derived from the
 * site's secret salts (wp_hash), so it is unguessable and stable without a new option.
 *
 * @return string
 */
function pgp_ecp_log_file() {
	return pgp_ecp_log_dir() . '/debug-' . substr( wp_hash( 'pgp-ecp-debug-log' ), 0, 24 ) . '.log';
}

/**
 * Write raw content to a file with PHP's error_log() mode 3 (append). Used for the log and its
 * guard files so it works with the same permissions as the log itself, independent of FS_METHOD.
 *
 * @param string $content Content to append.
 * @param string $file    Absolute file path.
 * @return bool
 */
function pgp_ecp_append_file( $content, $file ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Authorized debug feature: mode 3 appends to the plugin's own protected log directory only when the admin enables "Enable Debugging Logs".
	return error_log( $content, 3, $file );
}

/**
 * Create the log directory with index.php and a deny-all .htaccess.
 * The last failure reason is kept in a static for pgp_ecp_log_status().
 *
 * @param string|null $error Receives a human-readable failure reason.
 * @return bool True when the directory is writable and both guard files exist.
 */
function pgp_ecp_ensure_log_dir( &$error = null ) {
	static $ready      = null;
	static $last_error = '';
	if ( true === $ready ) {
		return true;
	}
	$dir      = pgp_ecp_log_dir();
	$index    = $dir . '/index.php';
	$htaccess = $dir . '/.htaccess';

	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		$last_error = sprintf( 'could not create directory %s (check that the uploads folder exists and is writable by the web server)', $dir );
	} elseif ( ! wp_is_writable( $dir ) ) {
		$last_error = sprintf( 'directory %s is not writable by the web server', $dir );
	} else {
		if ( ! file_exists( $index ) ) {
			pgp_ecp_append_file( "<?php\n// Silence is golden.\n", $index );
		}
		if ( ! file_exists( $htaccess ) ) {
			pgp_ecp_append_file( "# Deny all web access to PGP ECP debug logs.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n", $htaccess );
		}
		if ( file_exists( $index ) && file_exists( $htaccess ) ) {
			$ready = true;
			return true;
		}
		$last_error = sprintf( 'could not create the protection files index.php/.htaccess in %s', $dir );
	}
	$error = $last_error;
	return false;
}

/**
 * Report whether the plugin can write its debug log (used by the settings page).
 *
 * @return array{ok: bool, file: string, error: string}
 */
function pgp_ecp_log_status() {
	$error = '';
	$ok    = pgp_ecp_ensure_log_dir( $error );
	$file  = pgp_ecp_log_file();
	if ( $ok && file_exists( $file ) && ! wp_is_writable( $file ) ) {
		$ok    = false;
		$error = sprintf( 'log file %s exists but is not writable', $file );
	}
	return array(
		'ok'    => $ok,
		'file'  => $file,
		'error' => $error,
	);
}

/**
 * Write a debug message to the plugin's private log file, only when the admin debug option is on
 * (WP_DEBUG is not required).
 * Email addresses are always redacted. Fails closed: nothing is logged if the protected
 * log directory cannot be prepared.
 *
 * @param string $message Message to log.
 * @return void
 */
function pgp_ecp_log( $message ) {
	// wp_hash() (used for the log file name) is pluggable and not available before plugins finish loading.
	if ( ! function_exists( 'wp_hash' ) || ! pgp_ecp_is_debug_enabled() || ! pgp_ecp_ensure_log_dir() ) {
		return;
	}
	$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] PGP_ECP: ' . pgp_ecp_scrub_emails( $message ) . "\n";
	pgp_ecp_append_file( $line, pgp_ecp_log_file() );
}

// Load dependencies.
require_once PGP_ECP_PLUGIN_DIR . 'includes/class-pgp-ecp-activator.php';
require_once PGP_ECP_PLUGIN_DIR . 'includes/class-pgp-ecp-deactivator.php';
require_once PGP_ECP_PLUGIN_DIR . 'includes/class-pgp-ecp-admin.php';
require_once PGP_ECP_PLUGIN_DIR . 'includes/class-pgp-ecp-frontend.php';
require_once PGP_ECP_PLUGIN_DIR . 'includes/class-pgp-ecp-encryption.php';

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'PGP_ECP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PGP_ECP_Deactivator', 'deactivate' ) );

/**
 * Initialize admin and frontend.
 *
 * @return void
 */
function pgp_ecp_init() {
	pgp_ecp_log( 'Plugin initialized; initializing admin and frontend' );

	// Check for the gnupg extension.
	if ( ! extension_loaded( 'gnupg' ) ) {
		pgp_ecp_log( 'PHP gnupg extension not loaded.' );
		add_action( 'admin_notices', 'pgp_ecp_missing_gnupg_notice' );
		return;
	}

	if ( is_admin() ) {
		new PGP_ECP_Admin();
	}

	new PGP_ECP_Frontend();
}
add_action( 'plugins_loaded', 'pgp_ecp_init' );

/**
 * Admin notice shown when the gnupg extension is missing.
 *
 * @return void
 */
function pgp_ecp_missing_gnupg_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'PGP Encryption for Contact Page requires the PHP gnupg extension. Please install it to use this plugin.', 'pgp-encryption-for-contact-page' ); ?></p>
	</div>
	<?php
}
