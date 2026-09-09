/**
 * session-timeout.js — idle session warning
 *
 * Laravel expires a session after `session.lifetime` minutes with no requests.
 * Mouse movement alone does NOT extend it, so a user who reads a long page for
 * two hours gets bounced to the login screen on their next click, losing whatever
 * they had typed.
 *
 * This tracks idle time in the browser, warns before the session dies, and can
 * extend it by POSTing to the keep-alive route (a real request is the only thing
 * that refreshes the server-side session).
 *
 * Markup and config: resources/views/partials/session_timeout.blade.php
 * Rendered for authenticated users only.
 */
(function () {
    'use strict';

    var root = document.getElementById('arms-idle-warning');
    if (!root) return;

    var LIFETIME = parseInt(root.dataset.lifetimeSeconds, 10) || 7200;
    var WARN_AT  = parseInt(root.dataset.warnSeconds, 10) || 120;
    var KEEPALIVE_URL = root.dataset.keepaliveUrl;

    // Never warn for longer than the session itself lasts.
    if (WARN_AT >= LIFETIME) WARN_AT = Math.max(30, Math.floor(LIFETIME / 4));

    // Re-ping at most this often while the user is actually doing things, so a
    // long working session never dies underneath them.
    var PING_EVERY = Math.max(60, Math.floor((LIFETIME - WARN_AT) / 2));

    // Shared across tabs: activity in one tab must not let another tab log out.
    var STORAGE_KEY = 'arms_last_activity';

    var dialog     = root.querySelector('.arms-idle-dialog');
    var remainingEl = document.getElementById('arms-idle-remaining');
    var stayBtn    = document.getElementById('arms-idle-stay');
    var logoutBtn  = document.getElementById('arms-idle-logout');
    var logoutForm = document.getElementById('arms-idle-logout-form');

    var lastActivity = Date.now();
    var lastPing     = Date.now();
    var warning      = false;
    var finished     = false;

    function readShared() {
        try {
            var v = parseInt(window.localStorage.getItem(STORAGE_KEY), 10);
            return isNaN(v) ? 0 : v;
        } catch (e) {
            return 0;
        }
    }

    function writeShared(ts) {
        try {
            window.localStorage.setItem(STORAGE_KEY, String(ts));
        } catch (e) { /* private mode / storage disabled */ }
    }

    function markActive() {
        lastActivity = Date.now();
        writeShared(lastActivity);
    }

    function idleSeconds() {
        // The most recent activity in ANY tab wins.
        var newest = Math.max(lastActivity, readShared());
        return (Date.now() - newest) / 1000;
    }

    function hideWarning() {
        warning = false;
        root.hidden = true;
    }

    function showWarning() {
        if (warning) return;
        warning = true;
        root.hidden = false;
        if (stayBtn) stayBtn.focus();
    }

    function formatClock(seconds) {
        var s = Math.max(0, Math.round(seconds));
        var m = Math.floor(s / 60);
        var r = s % 60;
        return m + ':' + (r < 10 ? '0' : '') + r;
    }

    function signOut() {
        if (finished) return;
        finished = true;
        writeShared(0);
        if (logoutForm) {
            logoutForm.submit();
        } else {
            window.location.reload();
        }
    }

    /** POST to the keep-alive route; a handled request is what extends the session. */
    function ping(onDone) {
        if (!KEEPALIVE_URL) return;

        var token = document.querySelector('meta[name="csrf-token"]');
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (token && token.content) headers['X-CSRF-TOKEN'] = token.content;

        lastPing = Date.now();

        fetch(KEEPALIVE_URL, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
        }).then(function (res) {
            // 401/419 means the session is already gone — no point pretending.
            if (res.status === 401 || res.status === 419) {
                signOut();
                return;
            }
            if (onDone) onDone();
        }).catch(function () {
            // Offline or server unreachable: leave the timer running rather than
            // claiming the session was extended.
        });
    }

    function tick() {
        if (finished) return;

        var idle = idleSeconds();
        var remaining = LIFETIME - idle;

        if (remaining <= 0) {
            signOut();
            return;
        }

        if (remaining <= WARN_AT) {
            showWarning();
            if (remainingEl) remainingEl.textContent = formatClock(remaining);
            return;
        }

        if (warning) hideWarning();

        // Active user, long gap since the last real request: refresh the session
        // so it cannot expire while they are still working.
        if (idle < PING_EVERY && (Date.now() - lastPing) / 1000 >= PING_EVERY) {
            ping();
        }
    }

    /* ── Activity tracking ──────────────────────────────────────────────── */
    var throttled = false;
    function onActivity() {
        if (warning) return;           // only the buttons may dismiss the warning
        if (throttled) return;
        throttled = true;
        setTimeout(function () { throttled = false; }, 1000);
        markActive();
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (evt) {
        window.addEventListener(evt, onActivity, { passive: true });
    });

    // Coming back to a backgrounded tab should re-evaluate immediately: timers are
    // throttled while hidden, so the countdown may be badly out of date.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) tick();
    });

    if (stayBtn) {
        stayBtn.addEventListener('click', function () {
            markActive();
            lastPing = Date.now();
            hideWarning();
            ping();
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', signOut);
    }

    // Clicking the backdrop must not dismiss it — the choice has to be deliberate.
    if (dialog) {
        dialog.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    markActive();
    setInterval(tick, 1000);
})();
