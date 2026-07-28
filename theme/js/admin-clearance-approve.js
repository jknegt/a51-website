/**
 * Area 51 Reunion — Admin Clearance Approve button JS
 * Layer 03: Handles Approve Clearance button clicks on the clearance_request list screen.
 * Localized via area51ClearanceApproveAdmin object (ajaxurl + nonce).
 * [Prevention: PATTERN-042] — Uses area51ClearanceApproveAdmin nonce (not first nonce on page).
 */

document.addEventListener( 'DOMContentLoaded', function () {
    document.addEventListener( 'click', function ( e ) {
        var button = e.target.closest( '.area51-clearance-approve-btn' );
        if ( ! button ) return;

        var postId = button.dataset.postId;
        if ( ! postId ) return;

        // Get level from sibling select in same cell
        var select = button.parentElement.querySelector( '.area51-clearance-level-select' );
        var level  = select ? select.value : '1';

        // Disable button and select
        button.disabled = true;
        if ( select ) select.disabled = true;
        button.textContent = 'APPROVING...';

        // Remove any previous error message
        var prevError = button.parentElement.querySelector( '.area51-inline-error' );
        if ( prevError ) prevError.remove();

        var params = new URLSearchParams( {
            action:  'area51_approve_clearance',
            nonce:   area51ClearanceApproveAdmin.nonce,
            post_id: postId,
            level:   level,
        } );

        fetch( area51ClearanceApproveAdmin.ajaxurl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params.toString(),
        } )
        .then( function ( response ) { return response.json(); } )
        .then( function ( data ) {
            if ( data.success ) {
                // Replace cell content with approved state
                var returnedLevel    = data.data && data.data.level    ? data.data.level    : level;
                var returnedCodename = data.data && data.data.codename ? data.data.codename : '';
                button.parentElement.innerHTML = '<span>Level ' + returnedLevel + ' &mdash; ' + returnedCodename + '</span>';
            } else {
                // Re-enable controls and show error
                button.disabled = false;
                if ( select ) select.disabled = false;
                button.textContent = 'Approve Clearance';
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
            if ( select ) select.disabled = false;
            button.textContent = 'Approve Clearance';
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
