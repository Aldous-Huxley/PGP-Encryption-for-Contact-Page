/* File: admin.js */
/* Path: pgp-encryption-for-contact-page/assets/js/admin.js */

jQuery(document).ready(function() {
    var isDebugEnabled = (typeof pgp_ecp_admin_vars !== 'undefined') && pgp_ecp_admin_vars.debug_enabled;
    function debugLog(msg) {
        if (isDebugEnabled) {
            console.log(msg);
        }
    }
    debugLog('PGP_ECP: Admin JavaScript loaded');
    
    var turnstileCheckbox = jQuery("#pgp_enable_turnstile");
    var siteKeyField = jQuery("#pgp_turnstile_site_key");
    var secretKeyField = jQuery("#pgp_turnstile_secret_key");

    if (turnstileCheckbox.length === 0) {
        debugLog('PGP_ECP: Error - turnstileCheckbox not found');
    }
    if (siteKeyField.length === 0) {
        debugLog('PGP_ECP: Error - siteKeyField not found');
    }
    if (secretKeyField.length === 0) {
        debugLog('PGP_ECP: Error - secretKeyField not found');
    }

    function toggleTurnstileFields() {
        debugLog('PGP_ECP: toggleTurnstileFields called, checkbox checked: ' + turnstileCheckbox.is(":checked"));
        if (turnstileCheckbox.is(":checked")) {
            siteKeyField.prop("disabled", false);
            secretKeyField.prop("disabled", false);
        } else {
            siteKeyField.prop("disabled", true);
            secretKeyField.prop("disabled", true);
        }
    }

    toggleTurnstileFields();
    turnstileCheckbox.on("change", function() {
        debugLog('PGP_ECP: Checkbox changed');
        toggleTurnstileFields();
    });
});