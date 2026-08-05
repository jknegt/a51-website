/**
 * Area 51 Reunion — Admin Contact Send button JS
 * Layer 16: Handles Send Email toggle-reveal + submit on the area51_contact list screen.
 * Localized via area51ContactSendAdmin object (ajaxurl + nonce).
 * [Prevention: PATTERN-042] — Uses area51ContactSendAdmin nonce (not first nonce on page).
 */

document.addEventListener( 'DOMContentLoaded', function () {
    document.addEventListener( 'click', function ( e ) {
        var toggleBtn = e.target.closest( '.area51-send-toggle-btn' );
        if ( toggleBtn ) {
            var form = toggleBtn.parentElement.querySelector( '.area51-send-form' );
            if ( form ) form.style.display = ( 'none' === form.style.display ) ? 'block' : 'none';
            return;
        }

        var cancelBtn = e.target.closest( '.area51-send-cancel-btn' );
        if ( cancelBtn ) {
            var cancelForm = cancelBtn.closest( '.area51-send-form' );
            if ( cancelForm ) cancelForm.style.display = 'none';
            return;
        }

        var submitBtn = e.target.closest( '.area51-send-submit-btn' );
        if ( ! submitBtn ) return;

        var postId  = submitBtn.dataset.postId;
        var form    = submitBtn.closest( '.area51-send-form' );
        var subject = form.querySelector( '.area51-send-subject' ).value;
        var message = form.querySelector( '.area51-send-message' ).value;

        submitBtn.disabled = true;
        submitBtn.textContent = 'SENDING...';

        var prevError = form.querySelector( '.area51-inline-error' );
        if ( prevError ) prevError.remove();

        var params = new URLSearchParams( {
            action:  'area51_send_contact_email',
            nonce:   area51ContactSendAdmin.nonce,
            post_id: postId,
            subject: subject,
            message: message,
        } );

        fetch( area51ContactSendAdmin.ajaxurl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params.toString(),
        } )
        .then( function ( response ) { return response.json(); } )
        .then( function ( data ) {
            if ( data.success ) {
                form.parentElement.innerHTML = '<span style="color:#0a0">&#x2713; Sent</span>';
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send';
                var message2 = ( data.data && data.data.message ) ? data.data.message : 'An error occurred. Please try again.';
                var errorSpan = document.createElement( 'span' );
                errorSpan.className = 'area51-inline-error';
                errorSpan.style.color = 'red';
                errorSpan.style.display = 'block';
                errorSpan.style.marginTop = '4px';
                errorSpan.textContent = message2;
                form.appendChild( errorSpan );
            }
        } )
        .catch( function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send';
            var errorSpan = document.createElement( 'span' );
            errorSpan.className = 'area51-inline-error';
            errorSpan.style.color = 'red';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '4px';
            errorSpan.textContent = 'Network error. Please try again.';
            form.appendChild( errorSpan );
        } );
    } );
} );
