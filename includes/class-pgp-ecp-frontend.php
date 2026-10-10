<?php
/**
 * File: class-pgp-ecp-frontend.php
 * Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-frontend.php
 *
 * @package PGP_Encryption_For_Contact_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Contact form shortcode and AJAX submission handler.
 */
class PGP_ECP_Frontend {

	/**
	 * Cloudflare Turnstile client script. Cloudflare requires this script to be loaded from
	 * challenges.cloudflare.com; it may not be self-hosted. Only loaded when the admin enables Turnstile.
	 * Disclosed in readme.txt under "External services".
	 */
	const TURNSTILE_SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Third-party CAPTCHA service; Cloudflare forbids self-hosting api.js. Opt-in and disclosed in readme.

	/**
	 * Cloudflare Turnstile server-side verification endpoint (server-to-server API call, not an asset).
	 */
	const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Server-side API request (wp_remote_post), not an offloaded asset. Disclosed in readme.

	/**
	 * Encryption instance.
	 *
	 * @var PGP_ECP_Encryption|null
	 */
	private $encryption;

	/**
	 * Constructor.
	 */
	public function __construct() {
		pgp_ecp_log( 'PGP_ECP_Frontend constructor called' );
		add_shortcode( 'pgp_contact_form', array( $this, 'render_contact_form' ) );
		add_shortcode( 'pgp_public_key', array( $this, 'render_public_key' ) );
		add_action( 'wp_ajax_pgp_ecp_submit_form', array( $this, 'handle_form_submission' ) );
		add_action( 'wp_ajax_nopriv_pgp_ecp_submit_form', array( $this, 'handle_form_submission' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		pgp_ecp_log( 'Enqueuing assets for pgp_contact_form shortcode' );
		wp_enqueue_style( 'pgp-ecp-frontend', PGP_ECP_PLUGIN_URL . 'assets/css/frontend.css', array(), PGP_ECP_VERSION );

		$options          = get_option( 'pgp_ecp_options', array() );
		$custom_admin_url = ! empty( $options['pgp_admin_url'] ) ? trailingslashit( $options['pgp_admin_url'] ) . 'admin-ajax.php' : admin_url( 'admin-ajax.php' );
		pgp_ecp_log( 'Attempting custom AJAX URL: ' . $custom_admin_url );

		$response = wp_remote_head( $custom_admin_url, array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$custom_admin_url = admin_url( 'admin-ajax.php' );
			pgp_ecp_log( 'Using default AJAX URL: ' . $custom_admin_url . ' (custom URL empty or invalid: ' . ( isset( $options['pgp_admin_url'] ) ? $options['pgp_admin_url'] : '' ) . ')' );
		}

		wp_enqueue_script( 'pgp-ecp-frontend', PGP_ECP_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), PGP_ECP_VERSION, true );
		wp_localize_script(
			'pgp-ecp-frontend',
			'pgp_ecp_vars',
			array(
				'ajax_url'      => $custom_admin_url,
				'nonce'         => wp_create_nonce( 'pgp_ecp_submit_form' ),
				'debug_enabled' => pgp_ecp_is_debug_enabled(),
				'error_prefix'  => __( 'Error: ', 'pgp-encryption-for-contact-page' ),
				'ajax_error'    => __( 'Error: Failed to submit the form. Please try again.', 'pgp-encryption-for-contact-page' ),
			)
		);

		if ( ! empty( $options['pgp_turnstile_site_key'] ) && ! empty( $options['pgp_enable_turnstile'] ) ) {
			// Version null: Cloudflare forbids cache-busting query strings on api.js.
			// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent, WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Opt-in third-party CAPTCHA (Cloudflare Turnstile); Cloudflare requires loading api.js from its own domain. Disclosed in readme "External services".
			wp_enqueue_script( 'pgp-ecp-cf-turnstile', self::TURNSTILE_SCRIPT_URL, array(), null, true );
			pgp_ecp_log( 'Enqueued cf-turnstile script' );
		}
	}

	/**
	 * Render the contact form shortcode.
	 *
	 * @return string
	 */
	public function render_contact_form() {
		pgp_ecp_log( 'Rendering contact form' );
		$options = get_option( 'pgp_ecp_options', array() );
		if ( empty( $options['pgp_public_key'] ) ) {
			pgp_ecp_log( 'PGP encryption not configured properly' );
			return '<p>' . esc_html__( 'Error: PGP encryption is not configured properly. Please set a valid PGP public key in the admin settings.', 'pgp-encryption-for-contact-page' ) . '</p>';
		}

		$turnstile_site_key = ( ! empty( $options['pgp_enable_turnstile'] ) && ! empty( $options['pgp_turnstile_site_key'] ) ) ? $options['pgp_turnstile_site_key'] : '';
		pgp_ecp_log( 'Turnstile site key: ' . ( $turnstile_site_key ? 'set' : 'empty' ) );

		ob_start();
		?>
		<form id="pgp-ecp-contact-form" method="post" action="">
			<label for="pgp-ecp-name"><?php esc_html_e( 'Name:', 'pgp-encryption-for-contact-page' ); ?></label>
			<input type="text" id="pgp-ecp-name" name="pgp_ecp_name" required>
			<label for="pgp-ecp-email"><?php esc_html_e( 'Email:', 'pgp-encryption-for-contact-page' ); ?></label>
			<input type="email" id="pgp-ecp-email" name="pgp_ecp_email" required>
			<label for="pgp-ecp-subject"><?php esc_html_e( 'Subject:', 'pgp-encryption-for-contact-page' ); ?></label>
			<input type="text" id="pgp-ecp-subject" name="pgp_ecp_subject" required>
			<label for="pgp-ecp-message"><?php esc_html_e( 'Message:', 'pgp-encryption-for-contact-page' ); ?></label>
			<textarea id="pgp-ecp-message" name="pgp_ecp_message" required></textarea>
			<?php if ( ! empty( $turnstile_site_key ) ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
				<?php pgp_ecp_log( 'Turnstile widget included in form' ); ?>
			<?php else : ?>
				<?php pgp_ecp_log( 'Turnstile widget skipped, no site key' ); ?>
			<?php endif; ?>
			<input type="hidden" name="action" value="pgp_ecp_submit_form">
			<?php wp_nonce_field( 'pgp_ecp_submit_form', 'pgp_ecp_nonce' ); ?>
			<button type="submit"><?php esc_html_e( 'Submit', 'pgp-encryption-for-contact-page' ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the [pgp_public_key] shortcode: the configured armored public key (restored from 1.0.0).
	 *
	 * @return string
	 */
	public function render_public_key() {
		$options    = get_option( 'pgp_ecp_options', array() );
		$public_key = isset( $options['pgp_public_key'] ) ? trim( (string) $options['pgp_public_key'] ) : '';

		if ( '' === $public_key ) {
			return '<p class="pgp-ecp-public-key-empty">' . esc_html__( 'No public key available.', 'pgp-encryption-for-contact-page' ) . '</p>';
		}

		return '<pre class="pgp-ecp-public-key">' . esc_html( $public_key ) . '</pre>';
	}

	/**
	 * Handle the AJAX form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		pgp_ecp_log( 'Contact form submission received (AJAX handle_form_submission)' );

		$nonce = isset( $_POST['pgp_ecp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pgp_ecp_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'pgp_ecp_submit_form' ) ) {
			pgp_ecp_log( 'Nonce verification failed.' );
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'pgp-encryption-for-contact-page' ) ) );
		}
		pgp_ecp_log( 'Nonce verified successfully' );

		$options              = get_option( 'pgp_ecp_options', array() );
		$turnstile_enabled    = ! empty( $options['pgp_enable_turnstile'] );
		$turnstile_site_key   = $turnstile_enabled && isset( $options['pgp_turnstile_site_key'] ) ? $options['pgp_turnstile_site_key'] : '';
		$turnstile_secret_key = $turnstile_enabled && isset( $options['pgp_turnstile_secret_key'] ) ? $options['pgp_turnstile_secret_key'] : '';

		// Verify Turnstile token if enabled.
		if ( ! empty( $turnstile_site_key ) && ! empty( $turnstile_secret_key ) ) {
			$turnstile_token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			pgp_ecp_log( 'Turnstile token received: ' . ( $turnstile_token ? 'set' : 'empty' ) );

			if ( empty( $turnstile_token ) ) {
				pgp_ecp_log( 'Cloudflare Turnstile token missing.' );
				wp_send_json_error( array( 'message' => __( 'CAPTCHA verification failed. Please complete the CAPTCHA.', 'pgp-encryption-for-contact-page' ) ) );
			}

			$response = wp_remote_post(
				self::TURNSTILE_VERIFY_URL,
				array(
					'body' => array(
						'secret'   => $turnstile_secret_key,
						'response' => $turnstile_token,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				pgp_ecp_log( 'Turnstile verification failed: ' . $response->get_error_message() );
				wp_send_json_error( array( 'message' => __( 'CAPTCHA verification failed.', 'pgp-encryption-for-contact-page' ) ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['success'] ) ) {
				pgp_ecp_log( 'Turnstile verification failed: ' . implode( ', ', isset( $body['error-codes'] ) ? (array) $body['error-codes'] : array( 'Unknown error' ) ) );
				wp_send_json_error( array( 'message' => __( 'CAPTCHA verification failed.', 'pgp-encryption-for-contact-page' ) ) );
			}

			pgp_ecp_log( 'Turnstile token verified successfully' );
		}

		// Validate form data.
		$name    = isset( $_POST['pgp_ecp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pgp_ecp_name'] ) ) : '';
		$email   = isset( $_POST['pgp_ecp_email'] ) ? sanitize_email( wp_unslash( $_POST['pgp_ecp_email'] ) ) : '';
		$subject = isset( $_POST['pgp_ecp_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['pgp_ecp_subject'] ) ) : '';
		$message = isset( $_POST['pgp_ecp_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pgp_ecp_message'] ) ) : '';

		if ( empty( $name ) || empty( $email ) || empty( $subject ) || empty( $message ) || ! is_email( $email ) ) {
			pgp_ecp_log( 'Invalid form data.' );
			wp_send_json_error( array( 'message' => __( 'Invalid form data.', 'pgp-encryption-for-contact-page' ) ) );
		}
		pgp_ecp_log( 'Form data validated successfully' );

		if ( ! $this->encryption ) {
			$this->encryption = new PGP_ECP_Encryption();
			pgp_ecp_log( 'Encryption instance created' );
		}

		if ( ! $this->encryption->import_public_key() ) {
			pgp_ecp_log( 'Failed to import PGP key.' );
			wp_send_json_error( array( 'message' => __( 'Failed to import PGP key. Check server logs for details.', 'pgp-encryption-for-contact-page' ) ) );
		}

		$full_message      = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message";
		$encrypted_message = $this->encryption->encrypt_message( $full_message );

		if ( ! $encrypted_message ) {
			pgp_ecp_log( 'Message encryption failed.' );
			wp_send_json_error( array( 'message' => __( 'Message encryption failed.', 'pgp-encryption-for-contact-page' ) ) );
		}

		$recipient_email = isset( $options['pgp_recipient_email'] ) ? $options['pgp_recipient_email'] : '';
		if ( empty( $recipient_email ) ) {
			pgp_ecp_log( 'Recipient email not set.' );
			wp_send_json_error( array( 'message' => __( 'Recipient email not configured.', 'pgp-encryption-for-contact-page' ) ) );
		}

		pgp_ecp_log( 'Attempting to send email to ' . pgp_ecp_redact_email( $recipient_email ) );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $recipient_email, __( 'New Encrypted Contact Form Submission', 'pgp-encryption-for-contact-page' ), $encrypted_message, $headers );

		if ( ! $sent ) {
			pgp_ecp_log( 'Email sending failed.' );
			wp_send_json_error( array( 'message' => __( 'Failed to send email.', 'pgp-encryption-for-contact-page' ) ) );
		}

		pgp_ecp_log( 'Email sent successfully (wp_mail returned true) to ' . pgp_ecp_redact_email( $recipient_email ) );
		wp_send_json_success( array( 'message' => __( 'Message sent successfully!', 'pgp-encryption-for-contact-page' ) ) );
	}
}
