<?php
/**
 * File: class-pgp-ecp-admin.php
 * Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-admin.php
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin settings page.
 */
class PGP_ECP_Admin {

	/**
	 * Cloudflare Turnstile dashboard URL (a plain hyperlink shown to admins, not a loaded asset).
	 */
	const TURNSTILE_DASHBOARD_URL = 'https://dash.cloudflare.com/turnstile'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Plain hyperlink to the Cloudflare dashboard for admins; no asset is loaded.

	/**
	 * Constructor.
	 */
	public function __construct() {
		pgp_ecp_log( 'PGP_ECP_Admin constructor called' );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_post_pgp_ecp_delete_log', array( $this, 'handle_delete_log' ) );
		add_action( 'admin_post_pgp_ecp_view_log', array( $this, 'handle_view_log' ) );
	}

	/**
	 * Maximum number of bytes of the log shown by "View debug log" (the tail of the file).
	 */
	const VIEW_LOG_MAX_BYTES = 512000;

	/**
	 * Show the plugin's debug log (admin-post.php, opened in a new tab).
	 * Requires manage_options and a valid nonce. Output is HTML-escaped; the file itself is never
	 * exposed through a public URL.
	 *
	 * @return void
	 */
	public function handle_view_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the debug log.', 'pgp-encryption-for-contact-page' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pgp_ecp_view_log', 'pgp_ecp_view_log_nonce' );

		$file      = pgp_ecp_log_file();
		$exists    = file_exists( $file ) && is_readable( $file );
		$size      = $exists ? (int) filesize( $file ) : 0;
		$truncated = $size > self::VIEW_LOG_MAX_BYTES;
		$content   = '';
		if ( $exists && $size > 0 ) {
			$offset = $truncated ? $size - self::VIEW_LOG_MAX_BYTES : 0;
			// Local file read of a bounded tail (not a remote request).
			$content = (string) file_get_contents( $file, false, null, $offset, self::VIEW_LOG_MAX_BYTES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local read with offset/length; WP_Filesystem has no partial-read API.
			if ( $truncated ) {
				$newline = strpos( $content, "\n" );
				if ( false !== $newline ) {
					$content = substr( $content, $newline + 1 ); // Start at a full line.
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'" );
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="robots" content="noindex,nofollow">
<title><?php esc_html_e( 'PGP Encryption for Contact Page: debug log', 'pgp-encryption-for-contact-page' ); ?></title>
<style>body{font-family:sans-serif;margin:1em}pre{white-space:pre-wrap;word-wrap:break-word;background:#f6f7f7;border:1px solid #ccd0d4;padding:1em;font-family:monospace;font-size:12px}</style>
</head>
<body>
<h1><?php esc_html_e( 'Plugin debug log', 'pgp-encryption-for-contact-page' ); ?></h1>
		<?php if ( ! $exists ) : ?>
<p><?php esc_html_e( 'No plugin debug log file exists.', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php else : ?>
<p>
			<?php
			printf(
				/* translators: 1: file path, 2: file size. */
				esc_html__( 'File: %1$s (%2$s)', 'pgp-encryption-for-contact-page' ),
				'<code>' . esc_html( $file ) . '</code>',
				esc_html( size_format( $size, 1 ) )
			);
			?>
</p>
			<?php if ( $truncated ) : ?>
<p><strong>
				<?php
				/* translators: %s: size of the displayed part. */
				printf( esc_html__( 'The log is larger than %s; only the most recent part is shown.', 'pgp-encryption-for-contact-page' ), esc_html( size_format( self::VIEW_LOG_MAX_BYTES ) ) );
				?>
</strong></p>
			<?php endif; ?>
<pre><?php echo esc_html( $content ); ?></pre>
		<?php endif; ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Handle the "Delete debug log" request (admin-post.php).
	 * Requires manage_options and a valid nonce; deletes only the plugin's own log file.
	 *
	 * @return void
	 */
	public function handle_delete_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete the debug log.', 'pgp-encryption-for-contact-page' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pgp_ecp_delete_log', 'pgp_ecp_delete_log_nonce' );

		$file = pgp_ecp_log_file();
		if ( ! file_exists( $file ) ) {
			$result = 'missing';
		} else {
			wp_delete_file( $file );
			clearstatcache( true, $file );
			$result = file_exists( $file ) ? 'failed' : 'deleted';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'pgp-ecp-settings',
					'pgp_ecp_log'    => $result,
					'_pgp_ecp_nonce' => wp_create_nonce( 'pgp_ecp_log_notice' ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render the result notice of a debug log deletion.
	 *
	 * @return void
	 */
	private function render_delete_log_notice() {
		if ( ! isset( $_GET['pgp_ecp_log'], $_GET['_pgp_ecp_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_pgp_ecp_nonce'] ) ), 'pgp_ecp_log_notice' ) ) {
			return;
		}
		$result   = sanitize_key( wp_unslash( $_GET['pgp_ecp_log'] ) );
		$messages = array(
			'deleted' => array( 'success', __( 'The plugin debug log was deleted.', 'pgp-encryption-for-contact-page' ) ),
			'missing' => array( 'info', __( 'There was no plugin debug log to delete.', 'pgp-encryption-for-contact-page' ) ),
			'failed'  => array( 'error', __( 'The plugin debug log could not be deleted. Check file permissions.', 'pgp-encryption-for-contact-page' ) ),
		);
		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $result ][0] ),
			esc_html( $messages[ $result ][1] )
		);
	}

	/**
	 * Render the debug log status and the "Delete debug log" button.
	 *
	 * @return void
	 */
	private function render_debug_log_box() {
		$file   = pgp_ecp_log_file();
		$exists = file_exists( $file );
		if ( pgp_ecp_is_debug_enabled() ) {
			$status = pgp_ecp_log_status();
			if ( ! $status['ok'] ) {
				printf(
					'<div class="notice notice-error inline"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: log file path, 2: reason. */
							__( 'Debug logging is enabled but the plugin cannot write its log file %1$s: %2$s.', 'pgp-encryption-for-contact-page' ),
							$status['file'],
							$status['error']
						)
					)
				);
			}
		}
		?>
		<h2><?php esc_html_e( 'Debug Log', 'pgp-encryption-for-contact-page' ); ?></h2>
		<p>
			<?php
			if ( $exists ) {
				printf(
					/* translators: 1: file path, 2: human-readable file size. */
					esc_html__( 'Log file: %1$s (%2$s)', 'pgp-encryption-for-contact-page' ),
					'<code>' . esc_html( $file ) . '</code>',
					esc_html( size_format( (int) filesize( $file ), 1 ) )
				);
			} else {
				esc_html_e( 'No plugin debug log file exists.', 'pgp-encryption-for-contact-page' );
			}
			?>
		</p>
		<p class="description"><?php esc_html_e( 'The plugin writes debug output only to its own protected file (email addresses are redacted). Deleting it does not affect wp-content/debug.log.', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php
		$view_url = wp_nonce_url( add_query_arg( 'action', 'pgp_ecp_view_log', admin_url( 'admin-post.php' ) ), 'pgp_ecp_view_log', 'pgp_ecp_view_log_nonce' );
		if ( $exists ) {
			printf(
				'<p><a href="%1$s" class="button" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( $view_url ),
				esc_html__( 'View debug log', 'pgp-encryption-for-contact-page' )
			);
		} else {
			printf(
				'<p><button type="button" class="button" disabled="disabled">%s</button></p>',
				esc_html__( 'View debug log', 'pgp-encryption-for-contact-page' )
			);
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pgp_ecp_delete_log" />
			<?php wp_nonce_field( 'pgp_ecp_delete_log', 'pgp_ecp_delete_log_nonce' ); ?>
			<?php
			submit_button(
				__( 'Delete debug log', 'pgp-encryption-for-contact-page' ),
				'delete',
				'pgp_ecp_delete_log_submit',
				false,
				$exists ? array() : array( 'disabled' => 'disabled' )
			);
			?>
		</form>
		<?php
	}

	/**
	 * Enqueue admin assets on the plugin settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_pgp-ecp-settings' !== $hook ) {
			return;
		}
		pgp_ecp_log( 'Enqueuing admin assets for hook: ' . $hook );
		wp_enqueue_style( 'pgp-ecp-admin', PGP_ECP_PLUGIN_URL . 'assets/css/admin.css', array(), PGP_ECP_VERSION );
		wp_enqueue_script( 'pgp-ecp-admin-js', PGP_ECP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), PGP_ECP_VERSION, true );
		wp_localize_script(
			'pgp-ecp-admin-js',
			'pgp_ecp_admin_vars',
			array(
				'debug_enabled' => pgp_ecp_is_debug_enabled(),
			)
		);
	}

	/**
	 * Add the settings page to the admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		pgp_ecp_log( 'add_settings_page called' );
		add_options_page(
			__( 'PGP Encryption Settings', 'pgp-encryption-for-contact-page' ),
			__( 'PGP Encryption', 'pgp-encryption-for-contact-page' ),
			'manage_options',
			'pgp-ecp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		pgp_ecp_log( 'register_settings called' );
		register_setting(
			'pgp_ecp_options_group',
			'pgp_ecp_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		add_settings_section(
			'pgp_ecp_main_section',
			__( 'Main Settings', 'pgp-encryption-for-contact-page' ),
			array( $this, 'render_main_section' ),
			'pgp-ecp-settings'
		);

		$fields = array(
			'pgp_public_key'           => array( __( 'PGP Public Key', 'pgp-encryption-for-contact-page' ), 'render_public_key_field' ),
			'pgp_recipient_email'      => array( __( 'Recipient Email', 'pgp-encryption-for-contact-page' ), 'render_recipient_email_field' ),
			'pgp_admin_url'            => array( __( 'Custom Admin URL', 'pgp-encryption-for-contact-page' ), 'render_admin_url_field' ),
			'pgp_enable_debugging'     => array( __( 'Enable Debugging Logs', 'pgp-encryption-for-contact-page' ), 'render_enable_debugging_field' ),
			'pgp_enable_turnstile'     => array( __( 'Enable Cloudflare Turnstile', 'pgp-encryption-for-contact-page' ), 'render_enable_turnstile_field' ),
			'pgp_turnstile_site_key'   => array( __( 'Cloudflare Turnstile Site Key', 'pgp-encryption-for-contact-page' ), 'render_turnstile_site_key_field' ),
			'pgp_turnstile_secret_key' => array( __( 'Cloudflare Turnstile Secret Key', 'pgp-encryption-for-contact-page' ), 'render_turnstile_secret_key_field' ),
		);

		foreach ( $fields as $id => $field ) {
			add_settings_field( $id, $field[0], array( $this, $field[1] ), 'pgp-ecp-settings', 'pgp_ecp_main_section' );
		}
	}

	/**
	 * Render the main settings section intro.
	 *
	 * @return void
	 */
	public function render_main_section() {
		pgp_ecp_log( 'Main Settings section rendered' );
		echo '<p>' . esc_html__( 'Configure the settings below to enable PGP encryption and Cloudflare Turnstile for your contact form.', 'pgp-encryption-for-contact-page' ) . '</p>';
	}

	/**
	 * Sanitize and validate options.
	 *
	 * @param array $input Input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_options( $input ) {
		pgp_ecp_log( 'sanitize_options called' );

		// Only validate on a real settings-form save. Nonce is verified by options.php (settings_fields()) before this callback runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$option_page = isset( $_POST['option_page'] ) ? sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) : '';
		$action      = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$has_options = isset( $_POST['pgp_ecp_options'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'pgp_ecp_options_group' !== $option_page || 'update' !== $action || ! $has_options || ! is_array( $input ) ) {
			pgp_ecp_log( 'Not a form submission, skipping validation and returning existing options' );
			return get_option( 'pgp_ecp_options', array() );
		}

		$sanitized                             = array();
		$sanitized['pgp_public_key']           = sanitize_textarea_field( isset( $input['pgp_public_key'] ) ? $input['pgp_public_key'] : '' );
		$sanitized['pgp_recipient_email']      = sanitize_email( isset( $input['pgp_recipient_email'] ) ? $input['pgp_recipient_email'] : '' );
		$sanitized['pgp_admin_url']            = esc_url_raw( isset( $input['pgp_admin_url'] ) ? $input['pgp_admin_url'] : '' );
		$sanitized['pgp_enable_debugging']     = isset( $input['pgp_enable_debugging'] ) ? 1 : 0;
		$sanitized['pgp_enable_turnstile']     = isset( $input['pgp_enable_turnstile'] ) ? 1 : 0;
		$sanitized['pgp_turnstile_site_key']   = sanitize_text_field( isset( $input['pgp_turnstile_site_key'] ) ? $input['pgp_turnstile_site_key'] : '' );
		$sanitized['pgp_turnstile_secret_key'] = sanitize_text_field( isset( $input['pgp_turnstile_secret_key'] ) ? $input['pgp_turnstile_secret_key'] : '' );

		pgp_ecp_log( 'Turnstile site key length: ' . strlen( $sanitized['pgp_turnstile_site_key'] ) . ', first 5 chars: ' . substr( $sanitized['pgp_turnstile_site_key'], 0, 5 ) );
		pgp_ecp_log( 'Turnstile secret key length: ' . strlen( $sanitized['pgp_turnstile_secret_key'] ) );
		pgp_ecp_log( 'Enable Turnstile: ' . ( $sanitized['pgp_enable_turnstile'] ? 'enabled' : 'disabled' ) );
		pgp_ecp_log( 'Enable Debugging: ' . ( $sanitized['pgp_enable_debugging'] ? 'enabled' : 'disabled' ) );

		// Clear Turnstile keys if disabled.
		if ( ! $sanitized['pgp_enable_turnstile'] ) {
			$sanitized['pgp_turnstile_site_key']   = '';
			$sanitized['pgp_turnstile_secret_key'] = '';
			pgp_ecp_log( 'Turnstile disabled, clearing site and secret keys' );
		}

		// Validate public key.
		if ( empty( $sanitized['pgp_public_key'] ) ) {
			add_settings_error( 'pgp_ecp_options', 'pgp_key_empty', __( 'PGP public key is required for form encryption. Please paste a valid key starting with "-----BEGIN PGP PUBLIC KEY BLOCK-----".', 'pgp-encryption-for-contact-page' ), 'warning' );
		} elseif ( false === strpos( $sanitized['pgp_public_key'], 'BEGIN PGP PUBLIC KEY BLOCK' ) ) {
			add_settings_error( 'pgp_ecp_options', 'pgp_key_invalid', __( 'Invalid PGP public key format. It must start with "-----BEGIN PGP PUBLIC KEY BLOCK-----".', 'pgp-encryption-for-contact-page' ), 'error' );
			$sanitized['pgp_public_key'] = '';
		} else {
			try {
				$gpg    = new gnupg();
				$import = $gpg->import( $sanitized['pgp_public_key'] );
				if ( ! $import || empty( $import['fingerprint'] ) ) {
					add_settings_error( 'pgp_ecp_options', 'pgp_key_invalid', __( 'The PGP public key could not be imported. Ensure it is a valid armored key.', 'pgp-encryption-for-contact-page' ), 'error' );
					$sanitized['pgp_public_key'] = '';
				}
			} catch ( Exception $e ) {
				/* translators: %s: error message from gnupg. */
				add_settings_error( 'pgp_ecp_options', 'pgp_key_invalid', sprintf( __( 'PGP key import failed: %s', 'pgp-encryption-for-contact-page' ), esc_html( $e->getMessage() ) ), 'error' );
				pgp_ecp_log( 'PGP key import failed: ' . $e->getMessage() );
				$sanitized['pgp_public_key'] = '';
			}
		}

		// Validate email.
		if ( ! empty( $sanitized['pgp_recipient_email'] ) && ! is_email( $sanitized['pgp_recipient_email'] ) ) {
			add_settings_error( 'pgp_ecp_options', 'pgp_email_invalid', __( 'Invalid recipient email address.', 'pgp-encryption-for-contact-page' ), 'error' );
			$sanitized['pgp_recipient_email'] = '';
		}

		// Validate admin URL.
		if ( ! empty( $sanitized['pgp_admin_url'] ) ) {
			if ( ! filter_var( $sanitized['pgp_admin_url'], FILTER_VALIDATE_URL ) ) {
				add_settings_error( 'pgp_ecp_options', 'pgp_admin_url_invalid', __( 'Invalid admin URL. Use a valid URL (e.g., https://example.com/custom-admin) or leave blank to use the default WordPress admin URL.', 'pgp-encryption-for-contact-page' ), 'error' );
				$sanitized['pgp_admin_url'] = '';
			} else {
				$custom_admin_url = trailingslashit( $sanitized['pgp_admin_url'] ) . 'admin-ajax.php';
				$response         = wp_remote_head( $custom_admin_url, array( 'timeout' => 5 ) );
				if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
					$error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
					add_settings_error(
						'pgp_ecp_options',
						'pgp_admin_url_invalid',
						/* translators: 1: tested URL, 2: error description. */
						sprintf( __( 'Custom admin URL (%1$s) is invalid (%2$s). Cleared to use default WordPress admin URL.', 'pgp-encryption-for-contact-page' ), esc_html( $custom_admin_url ), esc_html( $error ) ),
						'error'
					);
					pgp_ecp_log( 'Custom admin URL invalid during settings save (' . $error . '): ' . $custom_admin_url );
					$sanitized['pgp_admin_url'] = '';
				}
			}
		}

		// Validate Turnstile keys only if enabled.
		if ( $sanitized['pgp_enable_turnstile'] ) {
			if ( ! empty( $sanitized['pgp_turnstile_site_key'] ) && ! preg_match( '/^[0-9a-zA-Z._-]+$/', $sanitized['pgp_turnstile_site_key'] ) ) {
				add_settings_error( 'pgp_ecp_options', 'pgp_turnstile_site_key_invalid', __( 'Invalid Cloudflare Turnstile site key. It should contain only alphanumeric characters, dots, hyphens, or underscores.', 'pgp-encryption-for-contact-page' ), 'error' );
				$sanitized['pgp_turnstile_site_key'] = '';
			}

			if ( ! empty( $sanitized['pgp_turnstile_secret_key'] ) && ! preg_match( '/^[0-9a-zA-Z._-]+$/', $sanitized['pgp_turnstile_secret_key'] ) ) {
				add_settings_error( 'pgp_ecp_options', 'pgp_turnstile_secret_key_invalid', __( 'Invalid Cloudflare Turnstile secret key. It should contain only alphanumeric characters, dots, hyphens, or underscores.', 'pgp-encryption-for-contact-page' ), 'error' );
				$sanitized['pgp_turnstile_secret_key'] = '';
			}
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		pgp_ecp_log( 'render_settings_page called' );
		?>
		<div class="wrap pgp-contact-settings-wrap">
			<div class="pgp-ecp-header">
				<h1><?php esc_html_e( 'PGP Encryption for Contact Page Settings', 'pgp-encryption-for-contact-page' ); ?></h1>
				<?php $this->render_bmc_button(); ?>
			</div>
			<?php $this->render_delete_log_notice(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'pgp_ecp_options_group' );
				do_settings_sections( 'pgp-ecp-settings' );
				submit_button();
				?>
			</form>
			<?php $this->render_debug_log_box(); ?>
		</div>
		<?php
	}

	/**
	 * Buy Me a Coffee page of the developer (plain hyperlink; no remote script or font is loaded).
	 */
	const BMC_URL = 'https://buymeacoffee.com/stanghellini';

	/**
	 * Render a local HTML/CSS "Buy me a coffee" button (replacement for the remote BMC widget script).
	 *
	 * @return void
	 */
	private function render_bmc_button() {
		$svg = '<svg class="pgp-ecp-bmc-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">'
			. '<path d="M8 2.5c-.6.8-.6 1.6 0 2.4M11.5 2c-.6.9-.6 1.8 0 2.7M15 2.5c-.6.8-.6 1.6 0 2.4" fill="none" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round"/>'
			. '<path d="M4 7h14v6.5A6.5 6.5 0 0 1 11.5 20h-1A6.5 6.5 0 0 1 4 13.5Z" fill="#ffffff" stroke="#000000" stroke-width="1.2" stroke-linejoin="round"/>'
			. '<path d="M18 9h1.3a2.7 2.7 0 0 1 0 5.4H17.6" fill="none" stroke="#000000" stroke-width="1.4"/>'
			. '<path d="M3 21.5h17" stroke="#000000" stroke-width="1.4" stroke-linecap="round"/>'
			. '</svg>';

		$allowed_svg = array(
			'svg'  => array(
				'class'       => true,
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
		);

		printf(
			'<a class="pgp-ecp-bmc-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="pgp-ecp-bmc-text">%3$s</span><span class="screen-reader-text"> %4$s</span></a>',
			esc_url( self::BMC_URL ),
			wp_kses( $svg, $allowed_svg ),
			esc_html__( 'Buy me a coffee', 'pgp-encryption-for-contact-page' ),
			esc_html__( '(opens in a new tab)', 'pgp-encryption-for-contact-page' )
		);
	}

	/**
	 * Get a single option value.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	private function get_option_value( $key ) {
		$options = get_option( 'pgp_ecp_options', array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : '';
	}

	/**
	 * Render a link to the Cloudflare Turnstile dashboard.
	 *
	 * @return string
	 */
	private function dashboard_link() {
		return '<a href="' . esc_url( self::TURNSTILE_DASHBOARD_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Cloudflare Dashboard', 'pgp-encryption-for-contact-page' ) . '</a>';
	}

	/**
	 * Render the public key textarea field.
	 *
	 * @return void
	 */
	public function render_public_key_field() {
		pgp_ecp_log( 'render_public_key_field called' );
		?>
		<textarea name="pgp_ecp_options[pgp_public_key]" rows="14" cols="62" class="large-text" style="width: 100%; max-width: 540px;"><?php echo esc_textarea( $this->get_option_value( 'pgp_public_key' ) ); ?></textarea>
		<p class="description">
			<?php
			echo wp_kses(
				__( 'Paste your armored PGP public key here (e.g., starts with "-----BEGIN PGP PUBLIC KEY BLOCK-----"). Generate one using <code>gpg --gen-key</code> and export with <code>gpg --armor --export your-email@domain.com</code>. Required for form encryption.', 'pgp-encryption-for-contact-page' ),
				array( 'code' => array() )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the recipient email input field.
	 *
	 * @return void
	 */
	public function render_recipient_email_field() {
		pgp_ecp_log( 'render_recipient_email_field called' );
		?>
		<input type="email" name="pgp_ecp_options[pgp_recipient_email]" value="<?php echo esc_attr( $this->get_option_value( 'pgp_recipient_email' ) ); ?>" />
		<p class="description"><?php esc_html_e( 'The email address where encrypted messages will be sent (e.g., your-email@domain.com).', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php
	}

	/**
	 * Render the custom admin URL input field.
	 *
	 * @return void
	 */
	public function render_admin_url_field() {
		pgp_ecp_log( 'render_admin_url_field called' );
		?>
		<input type="url" name="pgp_ecp_options[pgp_admin_url]" value="<?php echo esc_attr( $this->get_option_value( 'pgp_admin_url' ) ); ?>" style="width: 100%; max-width: 400px;" />
		<p class="description"><?php esc_html_e( 'Enter a custom admin URL if your WordPress admin panel is not at /wp-admin (e.g., https://example.com/custom-admin). Leave blank to use the default WordPress admin URL. Invalid URLs (e.g., causing 404 errors) will be cleared automatically on save.', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php
	}

	/**
	 * Render the enable debugging logs checkbox field.
	 *
	 * @return void
	 */
	public function render_enable_debugging_field() {
		pgp_ecp_log( 'render_enable_debugging_field called' );
		?>
		<input type="checkbox" id="pgp_enable_debugging" name="pgp_ecp_options[pgp_enable_debugging]" value="1" <?php checked( ! empty( $this->get_option_value( 'pgp_enable_debugging' ) ) ); ?> />
		<label for="pgp_enable_debugging"><?php esc_html_e( 'Enable Debugging Logs', 'pgp-encryption-for-contact-page' ); ?></label>
		<p class="description"><?php esc_html_e( 'This option only enables the plugin\'s own private debug log in wp-content/uploads/pgp-ecp-logs/. It does NOT enable WordPress\'s built-in debugging (WP_DEBUG in wp-config.php), does not change WP_DEBUG, and does not write to wp-content/debug.log. Email addresses are redacted in the log. Turn this option off when you have finished troubleshooting.', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php
	}

	/**
	 * Render the enable Cloudflare Turnstile checkbox field.
	 * Field toggling is handled by assets/js/admin.js.
	 *
	 * @return void
	 */
	public function render_enable_turnstile_field() {
		pgp_ecp_log( 'render_enable_turnstile_field called' );
		?>
		<input type="checkbox" id="pgp_enable_turnstile" name="pgp_ecp_options[pgp_enable_turnstile]" value="1" <?php checked( ! empty( $this->get_option_value( 'pgp_enable_turnstile' ) ) ); ?> />
		<label for="pgp_enable_turnstile"><?php esc_html_e( 'Enable Cloudflare Turnstile CAPTCHA for the contact form', 'pgp-encryption-for-contact-page' ); ?></label>
		<p class="description"><?php esc_html_e( 'Check to enable Turnstile CAPTCHA. Uncheck to disable CAPTCHA and allow form submissions without verification.', 'pgp-encryption-for-contact-page' ); ?></p>
		<?php
	}

	/**
	 * Render the Cloudflare Turnstile site key input field.
	 *
	 * @return void
	 */
	public function render_turnstile_site_key_field() {
		pgp_ecp_log( 'render_turnstile_site_key_field called' );
		$disabled = empty( $this->get_option_value( 'pgp_enable_turnstile' ) );
		?>
		<input type="text" id="pgp_turnstile_site_key" name="pgp_ecp_options[pgp_turnstile_site_key]" value="<?php echo esc_attr( $this->get_option_value( 'pgp_turnstile_site_key' ) ); ?>" style="width: 100%; max-width: 400px;" <?php disabled( $disabled ); ?> />
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the Cloudflare dashboard. */
				esc_html__( 'Enter your Cloudflare Turnstile site key (obtain from the %s). Typically 40 characters starting with "0x4".', 'pgp-encryption-for-contact-page' ),
				wp_kses_post( $this->dashboard_link() )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the Cloudflare Turnstile secret key input field.
	 *
	 * @return void
	 */
	public function render_turnstile_secret_key_field() {
		pgp_ecp_log( 'render_turnstile_secret_key_field called' );
		$disabled = empty( $this->get_option_value( 'pgp_enable_turnstile' ) );
		?>
		<input type="text" id="pgp_turnstile_secret_key" name="pgp_ecp_options[pgp_turnstile_secret_key]" value="<?php echo esc_attr( $this->get_option_value( 'pgp_turnstile_secret_key' ) ); ?>" style="width: 100%; max-width: 400px;" <?php disabled( $disabled ); ?> />
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the Cloudflare dashboard. */
				esc_html__( 'Enter your Cloudflare Turnstile secret key (obtain from the %s). Typically 40 characters starting with "1x" or "2x". Keep this secure.', 'pgp-encryption-for-contact-page' ),
				wp_kses_post( $this->dashboard_link() )
			);
			?>
		</p>
		<?php
	}
}
