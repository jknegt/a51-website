/**
 * Area 51 Reunion — Admin DECLASSIFY button JS
 * Layer 03: Handles DECLASSIFY button clicks on the missing_person list screen.
 * Localized via area51DeclassifyAdmin object (ajaxurl + nonce).
 * [Prevention: PATTERN-042] — Uses area51DeclassifyAdmin nonce (not first nonce on page).
 */

document.addEventListener( 'DOMContentLoaded', function () {
    document.addEventListener( 'click', function ( e ) {
        var button = e.target.closest( '.area51-declassify-btn' );
        if ( ! button ) return;

        var postId = button.dataset.postId;
        if ( ! postId ) return;

        // Disable button and show loading state
        button.disabled = true;
        button.textContent = 'DECLASSIFYING...';

        // Remove any previous error message
        var prevError = button.parentElement.querySelector( '.area51-inline-error' );
        if ( prevError ) prevError.remove();

        var params = new URLSearchParams( {
            action:  'area51_declassify_card',
            nonce:   area51DeclassifyAdmin.nonce,
            post_id: postId,
        } );

        fetch( area51DeclassifyAdmin.ajaxurl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params.toString(),
        } )
        .then( function ( response ) { return response.json(); } )
        .then( function ( data ) {
            if ( data.success ) {
                // Replace cell content with DECLASSIFIED indicator
                button.parentElement.innerHTML = '<span style="color:#0a0">&#x2713; DECLASSIFIED</span>';
            } else {
                // Restore button and show error
                button.disabled = false;
                button.textContent = 'DECLASSIFY';
                var message = ( data.data && data.data.message ) ? data.data.message : 'An error occurred. Please try again.';
                var errorSpan = document.createElement( 'span' );
                errorSpan.className = 'area51-inline-error';
                errorSpan.style.color = 'red';
                errorSpan.style.display = 'block';
                errorSpan.style.marginTop = '4px';
                errorSpan.textContent = message;
                button.parentElement.appendChild( errorSpan );
            }
        } )
        .catch( function () {
            button.disabled = false;
            button.textContent = 'DECLASSIFY';
            var errorSpan = document.createElement( 'span' );
            errorSpan.className = 'area51-inline-error';
            errorSpan.style.color = 'red';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '4px';
            errorSpan.textContent = 'Network error. Please try again.';
            button.parentElement.appendChild( errorSpan );
        } );
    } );
} );
