/**
 * Area 51 Reunion — Subscribe Form Handler
 * Submits email via AJAX; handles success/error DOM state.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form    = document.getElementById('area51-subscribe-form');
        var input   = document.getElementById('area51-email-input');
        var btn     = document.getElementById('area51-subscribe-btn');
        var success = document.getElementById('area51-subscribe-success');
        var error   = document.getElementById('area51-subscribe-error');

        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Clear previous state
            if (error) { error.textContent = ''; error.style.display = 'none'; }

            var email = input ? input.value.trim() : '';

            // Client-side validation
            if (!email || !email.includes('@')) {
                if (error) {
                    error.textContent = 'Enter a valid email address.';
                    error.style.display = 'block';
                }
                return;
            }

            // Disable button during request
            if (btn) btn.disabled = true;

            var body = new URLSearchParams();
            body.append('action', 'area51_subscribe');
            body.append('nonce',  area51Subscribe.nonce);
            body.append('email',  email);

            fetch(area51Subscribe.ajaxurl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    body.toString(),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    if (form)    form.style.display    = 'none';
                    if (success) success.style.display = 'block';
                } else {
                    var msg = (data.data && data.data.message) ? data.data.message : 'Subscription failed — please try again.';
                    if (error) { error.textContent = msg; error.style.display = 'block'; }
                    if (btn) btn.disabled = false;
                }
            })
            .catch(function () {
                if (error) { error.textContent = 'Network error — please try again.'; error.style.display = 'block'; }
                if (btn) btn.disabled = false;
            });
        });
    });
}());
