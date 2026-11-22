/* TenderHunter — vanilla JS helpers only (no frameworks). */
(function () {
  'use strict';

  var CSRF = document.querySelector('meta[name="csrf"]') &&
    document.querySelector('meta[name="csrf"]').getAttribute('content');
  window.TH = { csrf: CSRF };

  // attach CSRF header to every HTMX request
  document.body.addEventListener('htmx:configRequest', function (evt) {
    if (CSRF) {
      evt.detail.headers['X-CSRF-Token'] = CSRF;
    }
  });

  /* Toasts --------------------------------------------------------- */
  function toast(type, message) {
    var container = document.getElementById('toasts') || createToasts();
    var el = document.createElement('div');
    el.className = 'notification flash ' + type;
    el.setAttribute('role', 'alert');
    el.innerHTML = '<button class="delete"></button>' + escapeHtml(message);
    container.appendChild(el);
    el.querySelector('.delete').addEventListener('click', function () { el.remove(); });
    setTimeout(function () { el.remove(); }, 5000);
  }

  function createToasts() {
    var d = document.createElement('div');
    d.id = 'toasts';
    document.body.appendChild(d);
    return d;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  window.TH.toast = toast;

  /* Flash messages arriving from server via data attributes or HTMX ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
      toast(el.getAttribute('data-flash-type') || 'is-info', el.getAttribute('data-flash'));
      el.remove();
    });
  });

  document.body.addEventListener('htmx:afterSwap', function (evt) {
    evt.detail.elt.querySelectorAll('[data-flash]').forEach(function (el) {
      toast(el.getAttribute('data-flash-type') || 'is-info', el.getAttribute('data-flash'));
    });
  });

  /* Pricing interval toggle (monthly / yearly) ---------------------- */
  document.addEventListener('click', function (evt) {
    var btn = evt.target.closest('[data-price-interval]');
    if (!btn) { return; }
    var wrap = btn.closest('.pricing-table');
    if (!wrap) { return; }
    var interval = btn.getAttribute('data-price-interval');
    wrap.classList.toggle('is-yearly', interval === 'year');
    wrap.querySelectorAll('[data-price-interval]').forEach(function (b) {
      var on = b.getAttribute('data-price-interval') === interval;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    wrap.querySelectorAll('input[name="interval"]').forEach(function (input) {
      input.value = interval;
    });
  });

  /* Confirm dialogs ------------------------------------------------ */
  document.addEventListener('click', function (evt) {
    var el = evt.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      evt.preventDefault();
      evt.stopPropagation();
    }
  }, true);

  /* relative time --------------------------------------------------- */
  window.TH.relativeTime = function (iso) {
    if (!iso) { return ''; }
    var ts = Date.parse(iso.replace(' ', 'T') + 'Z');
    if (isNaN(ts)) { return ''; }
    var diff = ts - Date.now();
    var abs = Math.abs(diff);
    var d = Math.round(abs / 86400000);
    var h = Math.round(abs / 3600000);
    var label = d >= 1 ? d + ' day' + (d === 1 ? '' : 's') : h + ' hour' + (h === 1 ? '' : 's');
    return diff >= 0 ? 'in ' + label : label + ' ago';
  };

  document.querySelectorAll('[data-deadline]').forEach(function (el) {
    var t = window.TH.relativeTime(el.getAttribute('data-deadline'));
    if (t) { el.textContent = t; }
  });
})();
