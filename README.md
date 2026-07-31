# CoreWave Credit — Website

Website for CoreWave Credit (credit repair) on the registered domain
**corewavecredit.com**, with a self-contained signup and payment flow.

## Status (2026-07-30)

All 8 pages plus 3 legal pages are **built and deployable**, and the enrolment flow is
wired end to end against the live PayPal subscription plans ($75 and $125/month).
Remaining: the PayPal API secret and webhook id, so payments are confirmed by PayPal
rather than assumed from the browser.

**→ See [LAUNCH-CHECKLIST.md](LAUNCH-CHECKLIST.md) for exactly what's left.**

## The signup flow

```
Get Started ─► intake form ─► choose plan ─► payment toast ─► PayPal ─► thank-you.html
                    │              │          (Subscribe btn)     │
                    └──────────────┴─► enroll.php                 │
                                       "NEW SIGNUP (pending)"     │
                                                 ▲                │
                                    "PAYPAL APPROVED" ◄───────────┤
                                                                  ▼
                                                       paypal-webhook.php
                                                       "PAYMENT VERIFIED"
```

Three notifications, deliberately distinct, in increasing order of certainty:

| Email | Fires when | Means |
|---|---|---|
| `NEW SIGNUP (payment pending)` | they pick a plan, **before** paying | you have the lead |
| `PAYPAL APPROVED` | PayPal's SDK reports the subscriber approved | checkout completed |
| `PAYMENT VERIFIED` | PayPal calls the webhook and it passes signature checks | money moved |

Only the last one is proof. `paypal-webhook.php` checks every notification against
PayPal's verify-webhook-signature API and sends nothing if that fails, so a stranger
who finds the URL can't fake a payment. It also reports refunds and cancellations.

The toast renders PayPal's real **Subscribe** button, loaded on demand, and passes the
`CW-XXXXXX` reference through as `custom_id` so the payment email arrives already
matched to the signup form. Plan ids and the (public) client id live in
`site/assets/js/enroll.js`; the secret, webhook id, and destination inbox live in
`site/cw-config.php`.

## Stack

Plain static HTML/CSS/JS with a few small PHP endpoints — **no WordPress**, no build
step, no framework. The folder opens in a browser as-is.

Hosting is the **Credit Repair Cloud cPanel plan** the client already pays for, so the
live handlers are the PHP ones (`contact.php`, `enroll.php`, `paypal-webhook.php`) and
`.htaccess` carries the security headers. `functions/api/contact.js` and `_headers` are
the Cloudflare Pages equivalents, kept but inert. **Note:** if this ever moves to
Cloudflare Pages, `enroll.php` and `paypal-webhook.php` have no Cloudflare counterpart
yet and would need porting — the contact form does.

## Layout

```
site/                     ← the folder that gets deployed
  index.html              Home
  about.html              About Us
  services.html           Our Services
  why.html                Why Choose Us
  pricing.html            Pricing
  faq.html                FAQ
  contact.html            Contact
  get-started.html        Get Started ← intake form, plan picker, payment toast
  thank-you.html          PayPal return page (noindex)
  privacy.html            Privacy Policy
  terms.html              Terms of Service
  disclosure.html         Credit Repair Disclosure + CROA rights
  404.html
  contact.php             Contact form handler (live)
  enroll.php              Signup handler — emails every enrolment, pending or paid
  paypal-webhook.php      Verifies PayPal notifications, emails confirmed payments
  cw-config.php           Inbox address + PayPal credentials  ← TODO:PAYPAL
  cw-lib.php              Shared helpers for the two handlers above
  .htaccess               HTTPS, security headers, compression (live)
  robots.txt / sitemap.xml
  _headers                Cloudflare equivalent of .htaccess — inert here
  assets/css/site.css     All styles, one file
  assets/js/site.js       Theme toggle, mobile menu, contact form
  assets/js/enroll.js     Enrolment wizard + payment toast  ← TODO:PAYPAL links
  assets/img/             logo.svg, favicon.svg
functions/api/contact.js  Cloudflare-only contact handler — inert here
wrangler.toml             Cloudflare deploy config — unused on cPanel
design-previews/          Original mockups — reference only, superseded by site/
```

Header and footer markup is duplicated across pages (no build step, by design — the
folder opens in a browser as-is). Change one, change all; `grep` makes that painless.

## Preview locally

```bash
cd site && python3 -m http.server 8000   # http://localhost:8000
```

No PHP locally, so neither form sends: both fall back to opening a pre-filled email,
which is the intended behaviour when the backend is unreachable. The wizard, plan
picker, toast, and the PayPal button itself all work — don't complete a checkout unless
you mean to, the plan ids are live.

## Deploy

Upload `corewave-site.zip` (files sit at the zip root) into `public_html` via cPanel
File Manager. Rebuild it after any edit:

```bash
cd site && rm -f ../corewave-site.zip && zip -rq ../corewave-site.zip .
```

The zip must include `.htaccess`, which cPanel's File Manager hides by default —
turn on *Settings → Show Hidden Files* to confirm it arrived.

## Brand

- Logo: "Growth Chart" (direction A) — rising bars + trend line + arrowhead in a metallic ring
- Palette: navy `#060B18`, electric blue `#2F8BFF`, peak cyan `#3FE0FF`, deep blue `#0A4BC4`, brushed steel
- Fonts: Sora (display) + Figtree (body)
- Dark/light toggle, bottom-right, remembers the choice
- Pricing: Individual $75/mo, Couples $125/mo, no setup fee

## Deliberately omitted

No testimonials and no statistics — the design has slots for both, but invented
reviews and made-up success rates on a credit repair site are precisely what the FTC
pursues. They go in when real, attributable ones exist. No analytics or tracking
cookies either.

## Original design previews

Superseded by `site/`, kept for reference:
- Homepage: https://claude.ai/code/artifact/b47035cd-033d-4039-84bb-5914f17b3bd1
- Why Choose CoreWave Credit: https://claude.ai/code/artifact/a69089c5-911d-46b0-aedf-94b932dbfd57
- Logo concepts (A/B/C): https://claude.ai/code/artifact/1fe06636-e455-499b-9f4f-1606836332d7
