/**
 * EU Cookie Banner – eucoookiebanner
 * Handles:
 *   - Body scroll lock while banner is visible
 *   - "I Agree" → sets cookie + dismisses overlay with animation
 *   - "Leave Shop" → navigates to configured URL
 *   - Keyboard trap inside modal (accessibility)
 */
(function () {
  'use strict';

  var COOKIE_NAME    = 'eucookie_accepted';
  var COOKIE_DAYS    = 7;
  var OVERLAY_ID     = 'eucb-overlay';
  var AGREE_BTN_ID   = 'eucb-agree';

  /* ── Cookie helpers ──────────────────────────────────── */

  function setCookie(name, value, days) {
    var expires = '';
    if (days) {
      var d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      expires = '; expires=' + d.toUTCString();
    }
    document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax; Secure';
  }

  function getCookie(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') { c = c.substring(1); }
      if (c.indexOf(nameEQ) === 0) {
        return c.substring(nameEQ.length, c.length);
      }
    }
    return null;
  }

  /* ── Overlay helpers ─────────────────────────────────── */

  function dismissOverlay(overlay) {
    overlay.classList.add('eucb-hiding');
    document.body.classList.remove('eucb-no-scroll');

    overlay.addEventListener('animationend', function handler() {
      overlay.removeEventListener('animationend', handler);
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    });
  }

  /* ── Keyboard trap ───────────────────────────────────── */

  function trapFocus(modal) {
    var focusableSelectors = [
      'button:not([disabled])',
      'a[href]',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    var focusable = modal.querySelectorAll(focusableSelectors);
    var first = focusable[0];
    var last  = focusable[focusable.length - 1];

    modal.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') { return; }

      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });
  }

  /* ── Main init ───────────────────────────────────────── */

  function init() {
    var overlay = document.getElementById(OVERLAY_ID);
    if (!overlay) { return; }

    // Already accepted? Nothing to do.
    if (getCookie(COOKIE_NAME) === '1') {
      return;
    }

    overlay.classList.add('eucb-visible');

    var agreeBtn = document.getElementById(AGREE_BTN_ID);
    var modal    = overlay.querySelector('.eucb-modal');
    var leaveUrl = overlay.getAttribute('data-leave-url') || 'https://www.duckduckgo.com';

    // Lock body scroll
    document.body.classList.add('eucb-no-scroll');

    // Focus the agree button on open (accessibility)
    if (agreeBtn) {
      agreeBtn.focus();
    }

    // Trap keyboard focus inside modal
    if (modal) {
      trapFocus(modal);
    }

    // ── Agree ──
    if (agreeBtn) {
      agreeBtn.addEventListener('click', function () {
        setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
        dismissOverlay(overlay);
      });
    }

    // ── Leave Shop: clicking backdrop also leaves ──
    var backdrop = overlay.querySelector('.eucb-backdrop');
    if (backdrop) {
      backdrop.addEventListener('click', function () {
        window.location.href = leaveUrl;
      });
    }

    // ── ESC key → leave shop ──
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        window.location.href = leaveUrl;
      }
    });
  }

  /* ── Bootstrap ───────────────────────────────────────── */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
