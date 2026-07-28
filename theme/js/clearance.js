(function () {
    'use strict';

    var form = document.getElementById('area51-clearance-form');
    if (!form) return;

    var feedback = document.getElementById('area51-clearance-feedback');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var data = new FormData(form);
        data.append('action', 'area51_clearance_request');
        data.append('nonce', area51Clearance.nonce);

        var submitBtn = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'TRANSMITTING...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', area51Clearance.ajaxurl, true);

        xhr.onload = function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT REQUEST';

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (err) {
                showFeedback('Transmission error. Please try again.', false);
                return;
            }

            if (response.success) {
                showFeedback('REQUEST RECEIVED. Clearance pending. Stand by for further instructions.', true);
                form.reset();
            } else {
                var msg = (response.data && response.data.message)
                    ? response.data.message
                    : 'Submission failed. Please check required fields and try again.';
                showFeedback(msg, false);
            }
        };

        xhr.onerror = function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT REQUEST';
            showFeedback('Network error. Please check your connection and try again.', false);
        };

        xhr.send(data);
    });

    function showFeedback(message, success) {
        feedback.hidden = false;
        feedback.className = 'area51-form-feedback area51-form-feedback--' + (success ? 'success' : 'error');
        feedback.textContent = message;
    }
})();
