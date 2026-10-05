/* ══════════════════════════════════════════════════════════════
   Philippine field formats — address pickers and contact numbers

   Two jobs, shared by every grid that encodes a person: fill the
   Region / City dropdowns from the PSGC register, and hold mobile
   and landline numbers to the same shape the registration form on
   the landing page already enforces.

   The register lives in public/data/ph-locations.json — 17 regions
   and 1,634 cities and municipalities, about 20 KB. It is fetched
   once per page and cached by the browser like any other asset.
   PhLocations.php reads that same file, so the server validates
   against exactly the list these dropdowns offered.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    // The same two patterns RegistrationController applies server-side.
    var MOBILE   = /^(09|\+639)\d{9}$/;
    var LANDLINE = /^\d{10}$/;

    // Derived from this script's own src so a sub-directory install still
    // resolves — the JSON sits beside js/, not at the domain root.
    var SOURCE = (function () {
        var el  = document.currentScript;
        var src = el ? el.src : '';
        var cut = src.indexOf('/js/ph-fields.js');

        return cut === -1 ? '/data/ph-locations.json' : src.slice(0, cut) + '/data/ph-locations.json';
    })();

    var regions = null;      // the register, once it has arrived
    var request = null;      // the in-flight fetch, so it is made only once
    var pairs   = [];        // every region/city select wired up so far

    var cityOptionsCache  = {};
    var regionOptionsHtml = '';

    /* ─── The register ─────────────────────────────────────────── */

    function load() {
        if (request) return request;

        request = fetch(SOURCE, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
            .then(function (data) {
                regions = Array.isArray(data) ? data : [];

                regionOptionsHtml = '';
                cityOptionsCache  = {};

                // Pairs wired before the register arrived are still empty.
                prune();
                pairs.forEach(refresh);

                return regions;
            })
            .catch(function (err) {
                if (window.console) {
                    console.error('Could not load the Philippine region list:', err);
                }

                regions = [];
                pairs.forEach(unavailable);

                return regions;
            });

        return request;
    }

    /**
     * Say so in the control itself when the register cannot be fetched.
     *
     * Two empty dropdowns read as a broken form; this at least names the
     * reason, and reloading the page is the fix.
     */
    function unavailable(pair) {
        var note = '<option value="" disabled selected>Region list unavailable — reload the page</option>';

        pair.region.innerHTML = note;
        pair.city.innerHTML = note;
        pair.city.disabled = true;
    }

    function regionRow(code) {
        if (!regions) return null;

        for (var i = 0; i < regions.length; i++) {
            if (regions[i].code === code) return regions[i];
        }

        return null;
    }

    /* ─── Option markup ────────────────────────────────────────── */

    var ENTITIES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };

    function escapeHtml(text) {
        return String(text).replace(/[&<>"]/g, function (ch) { return ENTITIES[ch]; });
    }

    /**
     * Built as one HTML string and cached per region.
     *
     * A directory can run to hundreds of rows, and creating ~140 option
     * elements each time a region is picked is enough work to be felt.
     */
    function cityOptions(code) {
        if (cityOptionsCache[code] !== undefined) return cityOptionsCache[code];

        var row  = regionRow(code);
        var html = '';

        if (row) {
            html = row.cities.map(function (city) {
                var safe = escapeHtml(city);

                return '<option value="' + safe + '">' + safe + '</option>';
            }).join('');
        }

        return (cityOptionsCache[code] = html);
    }

    function regionOptions() {
        if (regionOptionsHtml || !regions) return regionOptionsHtml;

        return (regionOptionsHtml = regions.map(function (row) {
            var code = escapeHtml(row.code);

            return '<option value="' + code + '">' + code + ' (' + escapeHtml(row.label) + ')</option>';
        }).join(''));
    }

    /* ─── Wiring a region / city pair ──────────────────────────── */

    /**
     * Render a pair from the values parked on it.
     *
     * `data-value` rather than `select.value` is what a pair remembers,
     * because a draft can be restored — or a row cloned — before the register
     * has arrived, and a value set against an empty select is simply dropped.
     */
    function refresh(pair) {
        if (!regions) return;

        var regionSel  = pair.region;
        var citySel    = pair.city;
        var wantRegion = regionSel.getAttribute('data-value') || '';
        var wantCity   = citySel.getAttribute('data-value') || '';

        regionSel.innerHTML = '<option value="" disabled selected>— Select region —</option>' + regionOptions();
        regionSel.value = wantRegion;

        // A region that is no longer in the register leaves the prompt showing.
        if (regionSel.value !== wantRegion) {
            regionSel.setAttribute('data-value', '');
            wantRegion = '';
        }

        var prompt = wantRegion ? '— Select city / municipality —' : '— Select a region first —';

        citySel.innerHTML = '<option value="" disabled selected>' + prompt + '</option>' + cityOptions(wantRegion);
        citySel.disabled  = !wantRegion;
        citySel.value     = wantCity;

        if (citySel.value !== wantCity) citySel.setAttribute('data-value', '');
    }

    /**
     * Keep a City dropdown in step with its Region.
     *
     * Both selects are left empty in the markup; everything they offer comes
     * from here, so the row template stays a template and the blade stays free
     * of 1,634 inlined options.
     */
    function wireAddress(regionSel, citySel) {
        if (!regionSel || !citySel || regionSel.phWired) return;

        var pair = { region: regionSel, city: citySel };

        regionSel.phWired = true;
        regionSel.phPair = pair;
        pairs.push(pair);

        regionSel.addEventListener('change', function () {
            regionSel.setAttribute('data-value', regionSel.value);

            // Whatever city was chosen belonged to the previous region.
            citySel.setAttribute('data-value', '');
            refresh(pair);
        });

        citySel.addEventListener('change', function () {
            citySel.setAttribute('data-value', citySel.value);
        });

        refresh(pair);
        load();
    }

    /**
     * Drop pairs whose selects have left the page.
     *
     * The submission dialog is one modal retargeted per training, and each
     * open empties the grid and builds it again. Without this, every row of
     * every directory opened in the session would stay on the list and be
     * re-rendered whenever the register was refreshed.
     */
    function prune() {
        pairs = pairs.filter(function (pair) { return pair.region.isConnected; });
    }

    /** Point a wired pair at a stored address — a draft, or a row being corrected. */
    function setAddress(regionSel, citySel, region, city) {
        if (!regionSel || !citySel) return;

        regionSel.setAttribute('data-value', region || '');
        citySel.setAttribute('data-value', city || '');

        if (regionSel.phPair) refresh(regionSel.phPair);
    }

    /* ─── Contact numbers ──────────────────────────────────────── */

    /** 09171234567 or +639171234567 — digits and one leading plus, nothing else. */
    function formatMobile(input) {
        var value = input.value.replace(/[^\d+]/g, '');

        value = value.charAt(0) === '+'
            ? '+' + value.slice(1).replace(/\+/g, '')
            : value.replace(/\+/g, '');

        input.value = value.slice(0, 13);
    }

    /** 0281234567 — ten digits, area code included. */
    function formatLandline(input) {
        input.value = input.value.replace(/\D/g, '').slice(0, 10);
    }

    function isMobile(value)   { return MOBILE.test(value); }
    function isLandline(value) { return LANDLINE.test(value); }

    /**
     * Correct a contact field as it is typed, and flag it when it cannot be one.
     *
     * An empty optional field is not wrong — only a value that could never be a
     * real number is — so blank clears the flag rather than raising it.
     */
    function wireContact(input, kind, invalidClass) {
        if (!input || input.phWired) return;

        input.phWired = true;

        var format = kind === 'landline' ? formatLandline : formatMobile;
        var test   = kind === 'landline' ? isLandline : isMobile;
        var flag   = invalidClass || 'is-invalid';

        function check() {
            input.classList.toggle(flag, input.value !== '' && !test(input.value));
        }

        input.addEventListener('input', function () {
            format(input);
            check();
        });

        input.addEventListener('blur', check);
    }

    window.PhFields = {
        load: load,
        prune: prune,
        wireAddress: wireAddress,
        setAddress: setAddress,
        wireContact: wireContact,
        isMobile: isMobile,
        isLandline: isLandline,
        formatMobile: formatMobile,
        formatLandline: formatLandline
    };
})();
