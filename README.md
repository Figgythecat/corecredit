# CoreWave Credit — Website

Website for CoreWave Credit (credit repair) on the registered domain
**corewavecredit.com**, integrating **Credit Repair Cloud (CRC)** for client signup
and online payment.

## Status (2026-07-29)

All 8 pages plus 3 legal pages are **built and deployable** as a static site.
Not yet deployed, and not yet live on the domain.

**→ See [LAUNCH-CHECKLIST.md](LAUNCH-CHECKLIST.md) for exactly what's left.**

## Stack

Plain static HTML/CSS/JS — **no WordPress**. The original plan was a WP block theme;
we switched because all 8 pages are essentially static and the only dynamic piece is
the CRC signup embed, which works fine on a static page. Result: free hosting, instant
loads, HTTPS included, and no PHP or plugins to keep patched on a site that handles
financial information.

Deployment target is **Cloudflare Pages**. The contact form is handled by a Cloudflare
Pages Function (`functions/api/contact.js`) that sends via Cloudflare Email Sending.

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
  get-started.html        Get Started ← CRC signup form goes here
  privacy.html            Privacy Policy
  terms.html              Terms of Service
  disclosure.html         Credit Repair Disclosure + CROA rights
  404.html
  robots.txt / sitemap.xml
  _headers                Security headers (CSP written but off — see checklist)
  assets/css/site.css     All styles, one file
  assets/js/site.js       Theme toggle, mobile menu, contact form
  assets/img/             logo.svg, favicon.svg
functions/api/contact.js  Contact form handler
wrangler.toml             Deploy config
design-previews/          Original mockups — reference only, superseded by site/
```

Header and footer markup is duplicated across pages (no build step, by design — the
folder opens in a browser as-is). Change one, change all; `grep` makes that painless.

## Preview locally

```bash
cd site && python3 -m http.server 8000   # http://localhost:8000
```

The contact form won't send locally — it falls back to opening a pre-filled email,
which is the intended behaviour when the API isn't reachable.

## Deploy

```bash
npx wrangler pages deploy site
```

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
