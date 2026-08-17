/**
 * Area 51 Reunion — Countdown Timer
 * Target: Thursday, October 29, 2026, 22:00 EDT (UTC-4)
 * TODO: John to confirm event start time — currently set to 22:00 EDT (10pm)
 * Vatican Gift Shop, 587 King St W, Toronto
 */
(function () {
    'use strict';

    // TODO: John to confirm event start time — currently set to 22:00 EDT (10pm)
    var TARGET = new Date('2026-10-29T22:00:00-04:00');

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function tick() {
        var now  = new Date();
        var diff = TARGET - now;

        var el = document.getElementById('area51-countdown-display');
        if (!el) return;

        if (diff <= 0) {
            el.textContent = 'INCIDENT IN PROGRESS';
            return;
        }

        var totalSecs = Math.floor(diff / 1000);
        var days      = Math.floor(totalSecs / 86400);
        var hours     = Math.floor((totalSecs % 86400) / 3600);
        var minutes   = Math.floor((totalSecs % 3600) / 60);
        var seconds   = totalSecs % 60;

        el.textContent = pad(days) + ':' + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
    }

    document.addEventListener('DOMContentLoaded', function () {
        tick();
        setInterval(tick, 1000);
    });
}());
