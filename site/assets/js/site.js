/* CoreWave Credit — shared behaviour.
   Theme is applied by a tiny inline script in <head> (before paint) so there is no
   flash of the wrong theme; this file only handles the toggle click and the rest. */
(function () {
  'use strict';

  /* ---- dark / light toggle ---- */
  var root = document.documentElement;
  var toggle = document.getElementById('themeToggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('cw-theme', next); } catch (e) {}
    });
  }

  /* ---- mobile menu ---- */
  var burger = document.querySelector('.burger');
  var mobile = document.getElementById('mm');
  if (burger && mobile) {
    burger.addEventListener('click', function () {
      var open = mobile.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // Close on Escape so keyboard users aren't trapped behind the panel.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && mobile.classList.contains('open')) {
        mobile.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });
  }

  /* ---- current year in the footer ---- */
  var years = document.querySelectorAll('[data-year]');
  for (var i = 0; i < years.length; i++) {
    years[i].textContent = String(new Date().getFullYear());
  }

  /* ---- contact form ---- */
  var form = document.getElementById('contactForm');
  if (form) {
    var msg = document.getElementById('formMsg');
    var btn = form.querySelector('button[type="submit"]');
    var btnLabel = btn ? btn.textContent : '';

    function say(kind, html) {
      if (!msg) return;
      msg.className = 'form-msg show ' + kind;
      msg.innerHTML = html;
      msg.setAttribute('role', kind === 'err' ? 'alert' : 'status');
      msg.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    // If the API is unreachable or not configured yet, hand the visitor a
    // pre-filled email instead of a dead end. Losing a lead is worse than
    // an inelegant fallback.
    function mailtoFallback(body) {
      var to = form.getAttribute('data-fallback-email') || '';
      var subject = 'Website enquiry — ' + (body.firstName || '') + ' ' + (body.lastName || '');
      var lines = [
        'Name: ' + (body.firstName || '') + ' ' + (body.lastName || ''),
        'Email: ' + (body.email || ''),
        'Phone: ' + (body.phone || ''),
        'Interested in: ' + (body.interest || ''),
        '',
        body.message || ''
      ];
      var href = 'mailto:' + to +
        '?subject=' + encodeURIComponent(subject) +
        '&body=' + encodeURIComponent(lines.join('\n'));
      say('err',
        "Our form isn't sending right now. <a href=\"" + href + "\">Click here to send it as an email instead</a> " +
        '— everything you typed is already filled in, just press send. Or call us and we\'ll take it down over the phone.');
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;

      if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

      var body = {};
      new FormData(form).forEach(function (value, key) { body[key] = value; });

      fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
        .then(function (res) {
          if (res.ok) {
            form.reset();
            say('ok', "Thanks — we've got your message and will get back to you within one business day.");
            return;
          }
          // 400 means they can fix it themselves; anything else is our problem.
          if (res.status === 400) {
            return res.json().then(function (data) {
              say('err', (data && data.error) || 'Please check the form and try again.');
            });
          }
          mailtoFallback(body);
        })
        .catch(function () {
          mailtoFallback(body);
        })
        .then(function () {
          if (btn) { btn.disabled = false; btn.textContent = btnLabel; }
        });
    });
  }
})();
