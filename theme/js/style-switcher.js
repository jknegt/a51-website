// [Layer 11] Style Switcher — vanilla JS, no framework, no build step.
// Companion to theme/inc/style-switcher.php. Responsibilities:
//   (a) DOMContentLoaded: defensively re-apply the persisted skin class
//       (wp_body_open's inline script in style-switcher.php already applied
//       it before paint — this is a no-op confirmation, not the primary
//       anti-flash mechanism)
//   (b) Check the radio input matching the currently active skin
//   (c) Listen for radio `change` events: swap the body class, persist to localStorage
//
// [Layer 11] Decision 3: skin names are NOT hardcoded here. This file discovers
// all valid skins at runtime from whatever input[name="area51-skin"] radios
// style-switcher.php rendered, so Layer 12 can add more skins by editing only
// the PHP skin list — no change needed to this file.
(function () {
    var STORAGE_KEY = 'area51_active_skin';
    var DEFAULT_SKIN = 'skin-classified';

    function getRadios() {
        return document.querySelectorAll('input[name="area51-skin"]');
    }

    function applySkin(skin) {
        var radios = getRadios();
        radios.forEach(function (radio) {
            document.body.classList.remove(radio.value);
        });
        document.body.classList.add(skin);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var activeSkin = localStorage.getItem(STORAGE_KEY) || DEFAULT_SKIN;

        // Defensive no-op — see file header. classList.add is idempotent.
        applySkin(activeSkin);

        var radios = getRadios();
        radios.forEach(function (radio) {
            radio.checked = (radio.value === activeSkin);
            radio.addEventListener('change', function (event) {
                if (!event.target.checked) {
                    return;
                }
                var newSkin = event.target.value;
                applySkin(newSkin);
                localStorage.setItem(STORAGE_KEY, newSkin);
            });
        });
    });
})();
