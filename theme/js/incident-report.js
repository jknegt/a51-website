/**
 * Area 51 Reunion — Memory Wall: Incident Report Form AJAX handler
 * Layer 06
 *
 * [Prevention: PATTERN-042] — Uses area51IncidentReport localized object (not area51Subscribe).
 * Only enqueued on is_page('memory-wall') — see functions.php.
 */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('incident-report-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn    = document.getElementById('ir-submit-btn');
        const confirmation = document.getElementById('incident-report-confirmation');
        const errorDiv     = document.getElementById('incident-report-error');

        submitBtn.disabled    = true;
        submitBtn.textContent = 'TRANSMITTING...';
        errorDiv.style.display = 'none';

        const formData = new FormData(form);
        formData.append('action', 'area51_submit_incident_report');
        formData.append('nonce', area51IncidentReport.nonce); // [Prevention: PATTERN-042]

        fetch(area51IncidentReport.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                form.style.display         = 'none';
                confirmation.style.display = 'block';
            } else {
                var message = (data.data && data.data.message)
                    ? data.data.message
                    : 'TRANSMISSION ERROR. PLEASE RETRY.';
                errorDiv.textContent    = message;
                errorDiv.style.display  = 'block';
                submitBtn.disabled      = false;
                submitBtn.textContent   = 'FILE INCIDENT REPORT';
            }
        })
        .catch(function () {
            errorDiv.textContent   = 'TRANSMISSION ERROR. PLEASE RETRY.';
            errorDiv.style.display = 'block';
            submitBtn.disabled     = false;
            submitBtn.textContent  = 'FILE INCIDENT REPORT';
        });
    });
});
