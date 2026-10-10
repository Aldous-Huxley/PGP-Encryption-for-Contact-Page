/* File: frontend.js */
/* Path: pgp-encryption-for-contact-page/assets/js/frontend.js */

jQuery(document).ready(function($) {
    // Check if debugging is enabled (passed from wp_localize_script)
    var isDebugEnabled = pgp_ecp_vars.debug_enabled;

    if (isDebugEnabled) {
        console.log('PGP_ECP: Initializing frontend JavaScript');
    }

    $('#pgp-ecp-contact-form').on('submit', function(e) {
        e.preventDefault();

        if (isDebugEnabled) {
            console.log('PGP_ECP: Form submission started');
        }

        var formData = $(this).serialize();

        $.ajax({
            url: pgp_ecp_vars.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                if (isDebugEnabled) {
                    console.log('PGP_ECP: Sending AJAX request');
                }
            },
            success: function(response) {
                if (response.success) {
                    if (isDebugEnabled) {
                        console.log('PGP_ECP: Form submitted successfully', response.data.message);
                    }
                    alert(response.data.message);
                    $('#pgp-ecp-contact-form')[0].reset();
                } else {
                    if (isDebugEnabled) {
                        console.log('PGP_ECP: Form submission failed', response.data.message);
                    }
                    alert(pgp_ecp_vars.error_prefix + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                if (isDebugEnabled) {
                    console.log('PGP_ECP: AJAX error', error);
                }
                alert(pgp_ecp_vars.ajax_error);
            }
        });
    });
});