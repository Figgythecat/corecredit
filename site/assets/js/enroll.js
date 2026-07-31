/* CoreWave Credit — enrolment flow.
   Step 1 details -> step 2 plan -> payment toast -> PayPal -> thank-you page.

   Loaded by get-started.html and thank-you.html only. Everything specific to
   your PayPal account is in the CONFIG block directly below; nothing further
   down needs editing. */
(function () {
  'use strict';

  /* =========================================================================
     CONFIG — your PayPal subscription plans
     =========================================================================

     These come from the two "button factory" snippets PayPal generated. The
     client id is the public half of your API credentials — it is meant to be
     visible in page source. The SECRET is not here and must never be: it lives
     server-side in cw-config.php.

     To change a price, create a new plan in PayPal (plans are immutable once
     they have subscribers) and swap its P-… id in below.
     ------------------------------------------------------------------------ */
  var PAYPAL_CLIENT_ID = 'AUA-QAgtZuQufDd_6wJQ-CRoTeX1WTnE9TQok5BnSAz3FKzJ5Fp-GnjpMjNLcu9xxu91U2mukZ5kc7ck';

  var PAYPAL_PLANS = {
    individual: 'P-8EE17580V49700728NJVZINQ',   // $75/month
    couples: 'P-56S49089GP0397414NJVZKDQ'       // $125/month
  };

  /* Where the intake form posts. On Cloudflare Pages this would be an API
     route instead; on this host it is the PHP file next to the pages. */
  var ENDPOINT = '/enroll.php';

  /* Used only if ENDPOINT is unreachable, so a signup is never simply lost. */
  var FALLBACK_EMAIL = 'info@corewavecredit.com';

  /* ===================== end of config ==================================== */

  var PLANS = {
    individual: { label: 'Individual Plan', price: 75, blurb: 'One person · month-to-month' },
    couples: { label: 'Couples Plan', price: 125, blurb: 'Two people, same household · month-to-month' }
  };

  function $(id) { return document.getElementById(id); }
  function qs(name) {
    var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }
  function remember(key, value) { try { sessionStorage.setItem('cw-' + key, value); } catch (e) {} }
  function recall(key) { try { return sessionStorage.getItem('cw-' + key) || ''; } catch (e) { return ''; } }

  /* =========================================================================
     Thank-you page — tell the office they came back from PayPal.
     ========================================================================= */
  var tyRef = $('tyRef');
  if (tyRef) {
    var ref = (qs('ref') || recall('ref')).toUpperCase();
    var planKey = qs('plan') || recall('plan');

    if (/^CW-[A-Z0-9]{4,12}$/.test(ref)) {
      tyRef.textContent = ref;
      var tyPlan = $('tyPlan');
      if (tyPlan && PLANS[planKey]) {
        tyPlan.textContent = PLANS[planKey].label + ' — $' + PLANS[planKey].price + '/month';
      }
      // Fire once per browser session; a refresh shouldn't re-notify.
      if (recall('returned') !== ref) {
        remember('returned', ref);
        try {
          fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ stage: 'returned', ref: ref })
          }).catch(function () {});
        } catch (e) {}
      }
    } else {
      var tyBlock = $('tyRefBlock');
      if (tyBlock) tyBlock.style.display = 'none';
    }
    return;
  }

  /* =========================================================================
     Enrolment wizard
     ========================================================================= */
  var form = $('enrollForm');
  if (!form) return;

  var steps = [$('wzStep1'), $('wzStep2')];
  var track = document.querySelectorAll('#wzTrack li');
  var msg = $('enrollMsg');
  var submitBtn = $('enrollSubmit');
  var submitLabel = submitBtn ? submitBtn.textContent : '';
  var recapName = $('recapName');
  var current = 0;

  function say(kind, html) {
    if (!msg) return;
    msg.className = 'form-msg show ' + kind;
    msg.innerHTML = html;
    msg.setAttribute('role', kind === 'err' ? 'alert' : 'status');
    msg.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  function clearSay() { if (msg) msg.className = 'form-msg'; }

  function paintTrack() {
    for (var i = 0; i < track.length; i++) {
      track[i].className = i < current ? 'done' : (i === current ? 'current' : '');
    }
  }

  function goTo(index) {
    current = index;
    for (var i = 0; i < steps.length; i++) {
      if (steps[i]) steps[i].classList.toggle('active', i === index);
    }
    paintTrack();
    clearSay();

    var target = steps[index];
    if (target) {
      var focusable = target.querySelector('input, select, textarea, button, [tabindex]');
      // Don't yank the page around on first paint, only on a real step change.
      if (focusable && index > 0) {
        target.scrollIntoView({ block: 'start', behavior: 'smooth' });
        focusable.focus({ preventScroll: true });
      }
    }
  }

  /* ---- step 1 -> step 2 ---- */
  var toStep2 = $('toStep2');
  if (toStep2) {
    toStep2.addEventListener('click', function () {
      // Validate only the fields on this step.
      var fields = steps[0].querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        if (!fields[i].checkValidity()) { fields[i].reportValidity(); return; }
      }
      if (recapName) {
        recapName.textContent =
          ($('firstName').value.trim() + ' ' + $('lastName').value.trim()).trim();
      }
      goTo(1);
    });
  }

  var backTo1 = document.querySelectorAll('[data-back]');
  for (var b = 0; b < backTo1.length; b++) {
    backTo1[b].addEventListener('click', function () { goTo(0); });
  }

  /* ---- plan picker ---- */
  var picks = document.querySelectorAll('.pick');
  function paintPicks() {
    for (var i = 0; i < picks.length; i++) {
      var input = picks[i].querySelector('input');
      picks[i].classList.toggle('sel', !!(input && input.checked));
    }
  }
  for (var p = 0; p < picks.length; p++) {
    var input = picks[p].querySelector('input');
    if (input) input.addEventListener('change', paintPicks);
  }

  // ?plan=couples from the pricing page preselects a card.
  var preset = qs('plan').toLowerCase();
  if (PLANS[preset]) {
    var presetInput = form.querySelector('input[name="plan"][value="' + preset + '"]');
    if (presetInput) presetInput.checked = true;
  }
  paintPicks();

  function chosenPlan() {
    var checked = form.querySelector('input[name="plan"]:checked');
    return checked ? checked.value : '';
  }

  /* =========================================================================
     Payment toast
     ========================================================================= */
  var modal = $('payModal');
  var scrim = $('payScrim');
  var lastFocused = null;
  var nativeDialog = modal && typeof modal.showModal === 'function';
  if (modal && !nativeDialog) modal.classList.add('fallback');

  function openToast() {
    lastFocused = document.activeElement;
    current = 2;          // the toast IS step 3
    paintTrack();
    if (nativeDialog) {
      modal.showModal();
    } else {
      modal.setAttribute('open', 'open');
      if (scrim) scrim.classList.add('show');
      document.addEventListener('keydown', escClose);
    }
    document.body.style.overflow = 'hidden';
    var first = modal.querySelector('a.btn, button');
    if (first) first.focus();
  }

  function closeToast() {
    if (nativeDialog) {
      modal.close();
    } else {
      modal.removeAttribute('open');
      if (scrim) scrim.classList.remove('show');
      document.removeEventListener('keydown', escClose);
    }
    document.body.style.overflow = '';
    current = 1;          // back on the plan step, payment not finished
    paintTrack();
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  function escClose(e) { if (e.key === 'Escape') closeToast(); }

  if (modal) {
    var closers = modal.querySelectorAll('[data-close]');
    for (var c = 0; c < closers.length; c++) closers[c].addEventListener('click', closeToast);
    if (scrim) scrim.addEventListener('click', closeToast);
    // Clicking the dark area around a native <dialog> closes it too.
    modal.addEventListener('click', function (e) { if (e.target === modal) closeToast(); });
    modal.addEventListener('close', function () { document.body.style.overflow = ''; });
  }

  function fillToast(planKey, reference) {
    var plan = PLANS[planKey];
    $('payPlan').textContent = plan.label;
    $('payPlanNote').textContent = plan.blurb;
    $('payAmount').textContent = '$' + plan.price + '.00';
    $('payDue').textContent = '$' + plan.price + '.00';

    var refWrap = $('payRefWrap');
    if (reference) {
      $('payRef').textContent = reference;
      refWrap.style.display = '';
    } else {
      refWrap.style.display = 'none';
    }

    mountPayPal(planKey, reference);
  }

  /* -------------------------------------------------------------------------
     PayPal's subscribe button.

     The SDK is fetched the first time the toast opens rather than on page load:
     most visitors to this page never reach payment, and there's no reason to
     hand PayPal a record of them. If it fails to load — blocked, offline, or
     slow — we fall back to a plain link to the same subscription plan, so the
     customer can still pay.
     ------------------------------------------------------------------------- */
  var sdkPromise = null;

  function loadPayPalSdk() {
    if (sdkPromise) return sdkPromise;

    sdkPromise = new Promise(function (resolve, reject) {
      if (window.paypal && window.paypal.Buttons) { resolve(window.paypal); return; }
      if (!PAYPAL_CLIENT_ID) { reject(new Error('no client id')); return; }

      var s = document.createElement('script');
      s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(PAYPAL_CLIENT_ID) +
              '&vault=true&intent=subscription';
      s.setAttribute('data-sdk-integration-source', 'button-factory');

      var timer = setTimeout(function () { reject(new Error('timed out')); }, 12000);
      s.onload = function () {
        clearTimeout(timer);
        if (window.paypal && window.paypal.Buttons) resolve(window.paypal);
        else reject(new Error('sdk loaded but empty'));
      };
      s.onerror = function () { clearTimeout(timer); reject(new Error('blocked')); };
      document.head.appendChild(s);
    });
    return sdkPromise;
  }

  /* Prefills the PayPal checkout with what they already typed. */
  function subscriberDetails() {
    var first = $('firstName'), last = $('lastName'), email = $('email');
    if (!first || !last) return null;
    var out = { name: { given_name: first.value.trim(), surname: last.value.trim() } };
    if (email && email.value.trim()) out.email_address = email.value.trim();
    return out;
  }

  function showLinkFallback(planKey, reference) {
    var planId = PAYPAL_PLANS[planKey];
    var btn = $('payBtn');
    btn.href = 'https://www.paypal.com/webapps/billing/plans/subscribe?plan_id=' +
      encodeURIComponent(planId);
    btn.style.display = '';
    // This route can't tell us when they finish, so give them the way back.
    var done = $('payDone');
    if (done) {
      done.href = 'thank-you.html?ref=' + encodeURIComponent(reference || '') +
        '&plan=' + encodeURIComponent(planKey);
      done.style.display = '';
    }
  }

  function mountPayPal(planKey, reference) {
    var host = $('payButtons');
    var loading = $('payLoading');
    var pending = $('payPending');
    var btn = $('payBtn');
    var done = $('payDone');

    host.innerHTML = '';          // they may have come back and changed plan
    btn.style.display = 'none';
    done.style.display = 'none';
    pending.style.display = 'none';
    loading.style.display = '';

    var planId = PAYPAL_PLANS[planKey];
    if (!planId) {                // no plan configured — say so, don't fake a button
      loading.style.display = 'none';
      pending.style.display = '';
      return;
    }

    loadPayPalSdk().then(function (paypal) {
      loading.style.display = 'none';
      return paypal.Buttons({
        style: { shape: 'rect', color: 'blue', layout: 'vertical', label: 'subscribe' },

        createSubscription: function (data, actions) {
          var payload = { plan_id: planId };
          // Rides along with the subscription and comes back on every webhook,
          // so the payment email arrives already matched to this signup.
          if (reference) payload.custom_id = reference;
          var subscriber = subscriberDetails();
          if (subscriber) payload.subscriber = subscriber;
          return actions.subscription.create(payload);
        },

        onApprove: function (data) {
          onSubscribed(planKey, reference, data.subscriptionID);
        },

        onError: function (err) {
          if (window.console) console.error('PayPal button error:', err);
          loading.style.display = 'none';
          showLinkFallback(planKey, reference);
        }
      }).render(host);
    }).catch(function (err) {
      if (window.console) console.warn('PayPal SDK unavailable:', err && err.message);
      loading.style.display = 'none';
      showLinkFallback(planKey, reference);
    });
  }

  /* PayPal says the subscriber approved it. Tell the office, then move them on. */
  function onSubscribed(planKey, reference, subscriptionId) {
    $('payTitle').textContent = 'Payment approved — one moment…';
    $('payButtons').style.display = 'none';
    $('payBtn').style.display = 'none';

    // Stops thank-you.html sending a second, weaker "they came back" notice.
    if (reference) remember('returned', reference);
    remember('ref', reference || '');
    remember('plan', planKey);

    var go = function () {
      window.location.href = 'thank-you.html?ref=' + encodeURIComponent(reference || '') +
        '&plan=' + encodeURIComponent(planKey) +
        '&sub=' + encodeURIComponent(subscriptionId || '');
    };

    try {
      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          stage: 'subscribed',
          ref: reference,
          plan: planKey,
          subscriptionId: subscriptionId
        })
      }).then(go, go);
    } catch (e) {
      go();
    }
  }

  /* Pre-filled email, so a broken endpoint costs a click and not a customer. */
  function mailtoFallback(body) {
    var plan = PLANS[body.plan] || { label: body.plan, price: '' };
    var subject = 'Signup — ' + body.firstName + ' ' + body.lastName + ' — ' + plan.label;
    var lines = [
      'Name: ' + body.firstName + ' ' + body.lastName,
      'Email: ' + body.email,
      'Phone: ' + body.phone,
      'State: ' + (body.state || ''),
      'Plan: ' + plan.label + ' ($' + plan.price + '/month)',
      'Heard about us: ' + (body.hear || ''),
      '',
      body.notes || ''
    ];
    return 'mailto:' + FALLBACK_EMAIL +
      '?subject=' + encodeURIComponent(subject) +
      '&body=' + encodeURIComponent(lines.join('\n'));
  }

  /* ---- submit ---- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var planKey = chosenPlan();
    if (!PLANS[planKey]) {
      say('err', 'Please choose a plan to continue.');
      return;
    }
    var fields = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) {
      if (!fields[i].checkValidity()) {
        // A required field on step 1 — take them back to it rather than
        // reporting an error they can't see.
        if (steps[0].contains(fields[i])) goTo(0);
        fields[i].reportValidity();
        return;
      }
    }

    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'One moment…'; }

    var body = { stage: 'intake' };
    new FormData(form).forEach(function (value, key) { body[key] = value; });

    function finish() {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitLabel; }
    }

    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { status: res.status, ok: res.ok, data: data || {} };
        });
      })
      .then(function (res) {
        if (res.ok) {
          remember('ref', res.data.ref || '');
          remember('plan', planKey);
          fillToast(planKey, res.data.ref || '');
          clearSay();
          openToast();
          return;
        }
        if (res.status === 400 || res.status === 429) {
          say('err', res.data.error || 'Please check the form and try again.');
          return;
        }
        // Server trouble: show the payment step anyway, and hand them an email
        // route so their details still reach us.
        fillToast(planKey, res.data.ref || '');
        openToast();
        say('err', 'We had trouble saving your details. <a href="' + mailtoFallback(body) +
          '">Send them to us by email</a> — everything is pre-filled, just press send.');
      })
      .catch(function () {
        fillToast(planKey, '');
        openToast();
        say('err', "We couldn't reach our server. <a href=\"" + mailtoFallback(body) +
          '">Send your details by email instead</a> — everything is pre-filled.');
      })
      .then(finish, finish);
  });

  goTo(0);
})();
