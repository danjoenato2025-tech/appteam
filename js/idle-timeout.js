/**
 * idle-timeout.js
 * Auto-logout after 10 minutes of inactivity.
 * Shows a 60-second countdown warning before logging out.
 *
 * Usage: Include this script near </body> on every page.
 * Set window.IDLE_LOGOUT_URL before including to override the logout path.
 *   e.g. <script>window.IDLE_LOGOUT_URL='../logout.php';</script>
 */
(function () {
    'use strict';

    var IDLE_MINUTES   = 10;          // total idle time before logout
    var WARN_SECONDS   = 60;          // countdown shown before logout
    var IDLE_MS        = IDLE_MINUTES * 60 * 1000;
    var WARN_MS        = WARN_SECONDS * 1000;
    var logoutUrl      = window.IDLE_LOGOUT_URL || 'logout.php';

    var idleTimer      = null;
    var warnTimer      = null;
    var countdownTimer = null;
    var overlay        = null;
    var countdownEl    = null;
    var remaining      = WARN_SECONDS;
    var warningVisible = false;

    /* ── Build modal overlay (once) ── */
    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'idle-timeout-overlay';
        overlay.innerHTML = [
            '<div id="idle-timeout-box">',
            '  <div id="idle-timeout-icon"><i class="fa-solid fa-clock"></i></div>',
            '  <div id="idle-timeout-title">Session Timeout Warning</div>',
            '  <div id="idle-timeout-msg">You\'ve been away from the keyboard.<br>You will be automatically logged out in</div>',
            '  <div id="idle-timeout-countdown"><span id="idle-countdown-num">60</span><span id="idle-countdown-label">seconds</span></div>',
            '  <button id="idle-timeout-stay">Stay Logged In</button>',
            '</div>'
        ].join('');

        var style = document.createElement('style');
        style.textContent = [
            '#idle-timeout-overlay{display:none;position:fixed;inset:0;background:rgba(20,20,50,.7);',
            'backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;}',
            '#idle-timeout-overlay.visible{display:flex;}',
            '#idle-timeout-box{background:#fff;border-radius:18px;padding:40px 36px 32px;max-width:380px;',
            'width:92%;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.22);',
            'animation:idleBoxIn .35s cubic-bezier(.34,1.56,.64,1);}',
            '@keyframes idleBoxIn{from{transform:scale(.82);opacity:0}to{transform:scale(1);opacity:1}}',
            '#idle-timeout-icon{width:72px;height:72px;border-radius:50%;background:#fff3e0;',
            'display:flex;align-items:center;justify-content:center;margin:0 auto 18px;',
            'border:3px solid #ff9f43;}',
            '#idle-timeout-icon i{font-size:30px;color:#ff9f43;}',
            '#idle-timeout-title{font-size:19px;font-weight:800;color:#2d3a4a;margin-bottom:10px;',
            'font-family:inherit;}',
            '#idle-timeout-msg{font-size:13.5px;color:#6e7a8a;line-height:1.65;margin-bottom:22px;',
            'font-family:inherit;}',
            '#idle-timeout-countdown{display:flex;flex-direction:column;align-items:center;',
            'gap:2px;margin-bottom:28px;}',
            '#idle-countdown-num{font-size:56px;font-weight:800;color:#ff3e1d;line-height:1;',
            'font-family:inherit;transition:color .3s;}',
            '#idle-countdown-num.urgent{color:#c0000a;}',
            '#idle-countdown-label{font-size:12px;font-weight:600;color:#a0a8b5;',
            'text-transform:uppercase;letter-spacing:.6px;}',
            '#idle-timeout-stay{background:linear-gradient(135deg,#696cff,#5457c4);color:#fff;',
            'border:none;border-radius:10px;padding:13px 36px;font-size:15px;font-weight:700;',
            'cursor:pointer;width:100%;transition:opacity .18s;font-family:inherit;}',
            '#idle-timeout-stay:hover{opacity:.88;}'
        ].join('');

        document.head.appendChild(style);
        document.body.appendChild(overlay);
        countdownEl = document.getElementById('idle-countdown-num');

        document.getElementById('idle-timeout-stay').addEventListener('click', resetIdle);
    }

    /* ── Show warning modal ── */
    function showWarning() {
        if (warningVisible) return;
        warningVisible = true;
        remaining = WARN_SECONDS;
        overlay.classList.add('visible');
        updateCountdown();
        countdownTimer = setInterval(function () {
            remaining--;
            updateCountdown();
            if (remaining <= 0) { doLogout(); }
        }, 1000);
    }

    function updateCountdown() {
        if (!countdownEl) return;
        countdownEl.textContent = remaining;
        if (remaining <= 10) {
            countdownEl.classList.add('urgent');
        } else {
            countdownEl.classList.remove('urgent');
        }
    }

    /* ── Hide warning modal ── */
    function hideWarning() {
        warningVisible = false;
        if (overlay) overlay.classList.remove('visible');
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    /* ── Do the actual logout ── */
    function doLogout() {
        clearAll();
        /* Store message for login page to display */
        try { sessionStorage.setItem('idle_logout', '1'); } catch(e){}
        window.location.href = logoutUrl + '?reason=idle';
    }

    /* ── Reset all timers (user is active) ── */
    function resetIdle() {
        hideWarning();
        clearAll();
        idleTimer = setTimeout(showWarning, IDLE_MS - WARN_MS);
        warnTimer  = setTimeout(doLogout,   IDLE_MS);
    }

    function clearAll() {
        if (idleTimer)    { clearTimeout(idleTimer);    idleTimer    = null; }
        if (warnTimer)    { clearTimeout(warnTimer);    warnTimer    = null; }
        if (countdownTimer){ clearInterval(countdownTimer); countdownTimer = null; }
    }

    /* ── Activity events ── */
    var ACTIVITY_EVENTS = ['mousemove','keydown','mousedown','touchstart','scroll','click'];
    var throttleFlag = false;

    function onActivity() {
        if (warningVisible) return; /* don't reset if warning is showing */
        if (throttleFlag)   return;
        throttleFlag = true;
        setTimeout(function(){ throttleFlag = false; }, 500);
        resetIdle();
    }

    /* ── Init ── */
    function init() {
        buildOverlay();
        ACTIVITY_EVENTS.forEach(function(ev){
            document.addEventListener(ev, onActivity, { passive: true });
        });
        resetIdle(); /* start the first timer */
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();