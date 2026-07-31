# CoreWave Credit — Launch Checklist

Getting the built site live at **corewavecredit.com** on the Credit Repair Cloud
hosting you already pay for ($19.95/month).

Steps 1–3 put the site online. Steps 4–6 make it able to take clients and money.

**Fastest path to taking money:** the signup flow and both PayPal subscription buttons
are wired. What's left is step 4b — four values in `site/cw-config.php` so payments can
be confirmed rather than assumed.

---

## Where things are

```
corecredit/
├── corewave-site.zip        ← UPLOAD THIS. Files are at the zip root, ready for public_html
├── site/                    ← the same files, unzipped, for editing
│   ├── index.html           Home
│   ├── about.html           About Us
│   ├── services.html        Our Services
│   ├── why.html             Why Choose Us
│   ├── pricing.html         Pricing
│   ├── faq.html             FAQ
│   ├── contact.html         Contact
│   ├── get-started.html     Get Started  ← intake form + plan picker + payment toast
│   ├── thank-you.html       Where PayPal sends people back to
│   ├── privacy.html         Privacy Policy
│   ├── terms.html           Terms of Service
│   ├── disclosure.html      Credit Repair Disclosure + CROA rights
│   ├── 404.html
│   ├── contact.php          Contact form handler (this is the one your host uses)
│   ├── enroll.php           Signup handler — emails you every new enrolment
│   ├── paypal-webhook.php   PayPal calls this; emails you when payment is confirmed
│   ├── cw-config.php        ← inbox address + PayPal credentials live here
│   ├── cw-lib.php           Shared helpers for the two files above
│   ├── .htaccess            HTTPS redirect, security headers, compression, 404 page
│   ├── robots.txt / sitemap.xml
│   ├── _headers             Cloudflare-only; harmless here, blocked by .htaccess
│   └── assets/{css,js,img}  assets/js/enroll.js ← your PayPal links go here
├── functions/api/contact.js Cloudflare-only alternative to contact.php — ignore for now
└── LAUNCH-CHECKLIST.md      this file
```

Signups are also written as small JSON files to a `cw-enrollments` folder **one level
above `public_html`**, so the payment email can quote the person's details. They're
deleted automatically after 90 days and are not reachable from the web.

Preview locally before uploading:

```bash
cd /home/sean/Desktop/corecredit/site && python3 -m http.server 8000
# open http://localhost:8000
```

The contact form won't send from the local server (no PHP) — it falls back to opening
a pre-filled email. That's the intended behaviour, not a bug.

---

## ⚠️ First: you have TWO CRC services pointing at the same domain

Your client area lists both:

1. **"My Company"** — corewavecredit.com — **CRC Sitebuilder**
2. **"Hosting Plan - Website & Hosting ($19.95/month)"** — corewavecredit.com

Only one can actually serve the domain. Sitebuilder is Credit Repair Cloud's
drag-and-drop builder; the Hosting Plan is the one with cPanel and a `public_html`
folder that this site needs.

**Before uploading, make sure the domain is being served by the Hosting Plan, not
Sitebuilder.** If Sitebuilder is currently attached to corewavecredit.com, detach it
or point it at a subdomain — otherwise you'll upload the site and still see the
Sitebuilder page, and spend an hour thinking the upload failed.

Your cPanel confirms the risk is real: there's a **Sitejet Builder** entry in the left
sidebar and a **Weebly** icon under Files. If either has ever published to this account,
it may own `public_html`. When you open File Manager, look at what's already in there
before you upload — if you find builder-generated files, that's your answer.

If you don't need Sitebuilder at all now, that may be a second service worth cancelling.
Ask CRC support which of your two services the domain is bound to — that one question
saves a lot of guessing.

**Confirmed account details** (from your cPanel):

| | |
|---|---|
| Server | `s35.mycreditrepairsite.com` |
| Shared IP | `216.172.171.98` |
| cPanel user | *(shown in cPanel under "Home Directory" — deliberately not recorded here, see note)* |
| Web root | `/home/<your-cpanel-user>/public_html` |

> This repo is public, so the cPanel username is left out on purpose. Paired with the
> server hostname it's half of a login, and cPanel is reachable at port 2083. You'll
> find it in cPanel's right-hand sidebar any time you need it. The IP and hostname
> above are fine to publish — both are already discoverable from public DNS and the
> SSL certificate.

---

## 1. Fill in the placeholders

Everything needing your real details is marked `TODO:`. List them all:

```bash
cd /home/sean/Desktop/corecredit && grep -rn "TODO:" site/
```

| Token | What it needs | Files |
|---|---|---|
| `TODO:PHONE` | Real business phone — change both the `tel:` link and the visible text. Currently the obviously-fake `(555) 012-3456`. | contact.html, get-started.html |
| `TODO:EMAIL` | Inbox for enquiries. Also set `$CONTACT_TO` at the top of **contact.php**. | contact.html, contact.php, privacy.html, terms.html |
| `TODO:HOURS` | Real business hours + time zone | contact.html |
| `TODO:PAYPAL` | PayPal API secret + webhook id, so payments can be confirmed — see step 4b. (The subscription buttons themselves are already done.) | cw-config.php |
| `TODO:PORTAL` | Your CRC client portal URL — appears in every footer as "Client login" | all pages, get-started.html |
| `TODO:STORY` | The real story: when you started, who you are, where you're based | about.html |
| `TODO:LEGAL-NAME` | Registered business name. Left off the public pages at your request — but CROA requires a real business identity in the **client contract**, so this still has to be settled before you take money. | disclosure.html, terms.html |
| `TODO:STATE` | State of organisation, for the governing-law clause | disclosure.html, terms.html |
| `TODO:ADDRESS` | Business mailing address. If your only address is residential, a PO box or registered-agent address is the normal answer. | disclosure.html, privacy.html, terms.html |
| `TODO:DATE` | "Last updated" date on the three legal pages | disclosure.html, privacy.html, terms.html |
| `TODO:REFUNDS` | Your actual refund policy | pricing.html, terms.html |
| `TODO:LEGAL-REVIEW` | See step 6 | disclosure.html, privacy.html, terms.html |
| `TODO:ANALYTICS` | Only if you add tracking — you must then disclose it | privacy.html |

After editing anything in `site/`, rebuild the upload file:

```bash
cd /home/sean/Desktop/corecredit/site && rm -f ../corewave-site.zip && zip -rq ../corewave-site.zip .
```

### ⚠️ If you edit site.css or site.js, bump the version number

`.htaccess` tells browsers to cache CSS and JS for **7 days**. That's good for speed
and bad for updates — without a change to the URL, returning visitors keep the old
file for a week and your fix appears not to have worked.

Pages link the assets as `site.css?v=5`, `site.js?v=2` and `enroll.js?v=1`. After
editing any of them, increment that number across all pages:

```bash
cd /home/sean/Desktop/corecredit/site
perl -i -pe 's/site\.css\?v=\d+/site.css?v=6/g; s/site\.js\?v=\d+/site.js?v=3/g; s/enroll\.js\?v=\d+/enroll.js?v=2/g' *.html
```

**`assets/js/enroll.js` is the file holding your PayPal links** — so this matters most
there. Bump it or returning visitors keep using the old links for a week.

Then re-upload the HTML as well as the changed asset. HTML itself is set to
`max-age=0`, so pages always update immediately — it's only CSS and JS that need this.

---

## 2. ✅ DONE — uploaded to your CRC hosting (2026-07-29)

All 21 files are live in `public_html`, uploaded over FTP and verified
byte-for-byte against the local copies. Confirmed working on the server:

- All 12 pages return 200 · styled 404 page wired up · `robots.txt` and `sitemap.xml` serving
- `.htaccess` active — HTTP redirects to HTTPS, all five security headers set
- gzip on (site.css ships 6.1 KB instead of 24.9 KB)
- directory listings blocked; `.htaccess` and `_headers` return 403
- `contact.php` executing — valid submissions accepted, bad input rejected, honeypot working

**Two things still outstanding for the form:** create the `forms@corewavecredit.com`
mailbox, and fix SPF (step 3) — until both are done, submissions may land in spam.

**Delete the `deploy@corewavecredit.com` FTP account now** — it has done its job.
*cPanel → FTP Accounts → Delete.*

<details>
<summary>Original manual upload instructions (kept for future updates)</summary>

1. In your client area, click **Setup Instructions** (top nav) — that's where the
   cPanel URL, username, and FTP details live. If it doesn't show a cPanel login,
   ask CRC support for it directly.
2. Log in to **cPanel → File Manager**.
3. Open **`public_html`**. If there's a default placeholder page in there
   (`index.html`, `default.html`, a "coming soon" file), delete it — otherwise it
   may win over ours.
4. Click **Upload**, choose **`corewave-site.zip`**, and wait for it to finish.
5. Back in File Manager, right-click the zip → **Extract**. Confirm you now see
   `index.html` and an `assets` folder directly inside `public_html` — **not** inside
   a nested `site/` folder. If it nested, move the contents up one level.
6. Turn on **Settings → Show Hidden Files (dotfiles)** and confirm **`.htaccess`**
   made it across. It's the file that forces HTTPS and sets the security headers, and
   cPanel hides it by default — easy to miss.
7. Delete the zip from the server once extracted.
8. Visit the site. Click through all 11 pages, try the dark/light toggle, and open it
   on your phone.

**Prefer FTP?** Same thing with FileZilla — connect with the FTP details from Setup
Instructions, then drag the *contents* of `site/` into `public_html`. Make sure
FileZilla is showing hidden files so `.htaccess` transfers.

</details>

---

## 3. Point corewavecredit.com at the hosting

Today the domain shows a Namecheap parking page:

- Nameservers: `dns1.registrar-servers.com` / `dns2.registrar-servers.com` (Namecheap)
- Root `A` record → `162.255.119.10` (Namecheap parking)
- `www` → `parkingpage.namecheap.com`
- MX → `eforward1-5.registrar-servers.com` (your Namecheap email forwarding)

You own this domain at Namecheap and control its DNS. Two ways to connect it:

**Option A — keep DNS at Namecheap (least disruptive, keeps your email forwarding).**
✅ Your server IP is **`216.172.171.98`** (confirmed: it's `s35.mycreditrepairsite.com`,
the shared IP shown in your cPanel). At Namecheap: *Domain List → Manage → Advanced DNS*:

| Type | Host | Value | TTL |
|---|---|---|---|
| A | `@` | `216.172.171.98` | Automatic |
| CNAME | `www` | `corewavecredit.com.` | Automatic |
| TXT | `@` | `v=spf1 ip4:216.172.171.98 include:spf.efwd.registrar-servers.com ~all` | Automatic |

**That TXT record replaces your existing SPF record** — don't add a second one, a domain
may only have one SPF record. Your current value is
`v=spf1 include:spf.efwd.registrar-servers.com ~all`, which authorises Namecheap's email
forwarder but *not* the hosting server that sends your contact-form mail. Without the
`ip4:216.172.171.98` part, form submissions fail SPF and Gmail is likely to spam-folder
them. Everything else in the record stays as it is, so your email forwarding keeps working.

Delete the parking `A` record and the `www` parking CNAME. **Leave the MX records
alone** or your email forwarding stops working.

**Option B — use CRC's nameservers.** If Setup Instructions gives you two nameservers
instead of an IP, set those at Namecheap under *Domain → Nameservers → Custom DNS*.
This moves all DNS to CRC, which means **your email forwarding will need recreating
there** — check that before you switch.

Either way: DNS changes take anywhere from a few minutes to 24 hours. Then in cPanel,
run **AutoSSL** (or Let's Encrypt) to get the HTTPS certificate. Confirm
`https://corewavecredit.com` loads with a padlock before you send the link to anyone —
the `.htaccess` forces HTTPS, so it must be working.

---

## 4. Switch the signup flow on (PayPal)

**This is the step that turns a website into a business.** The flow itself is built:

```
Get Started  →  intake form  →  pick a plan  →  payment toast  →  PayPal
                     ↓                              ↓                ↓
              email to you              email to you        thank-you.html
           "NEW SIGNUP (pending)"      (only once paid)   "PAYMENT VERIFIED"
```

Every signup emails you **before** payment, so you keep the lead even if they never
pay. What's missing is your PayPal links and credentials.

### 4a. ✅ DONE — subscription buttons wired in (2026-07-30)

Your two PayPal subscription plans are live in `site/assets/js/enroll.js`:

| Plan | Price | PayPal plan id |
|---|---|---|
| Individual | $75.00/month | `P-8EE17580V49700728NJVZINQ` |
| Couples | $125.00/month | `P-56S49089GP0397414NJVZKDQ` |

The payment toast renders PayPal's real **Subscribe** button rather than a plain link,
which buys three things a link can't: the subscription carries your `CW-XXXXXX`
reference as `custom_id`, PayPal's checkout is prefilled with the name and email they
just typed, and PayPal tells the page the moment the subscriber approves — which
triggers the "PAYPAL APPROVED" email and sends them to the thank-you page.

The PayPal script is only fetched when the toast opens, so visitors who never enrol are
never handed to PayPal. If it fails to load, the toast falls back to a plain subscribe
link to the same plan.

**The client id in `enroll.js` is public** — it's the half PayPal expects in page source.
The secret is a different value and belongs only in `cw-config.php`, below.

Changing a price means creating a **new** plan in PayPal — plans can't be edited once
they have subscribers — then swapping the `P-…` id in `enroll.js` and bumping
`enroll.js?v=` on `get-started.html`.

### 4b. ← **THIS IS THE REMAINING STEP.** Turn on payment confirmation

Right now you'll hear that someone *approved* a subscription in their browser. You
won't hear from PayPal itself that the money actually settled, and you won't hear about
renewals, failed payments, or cancellations at all.

1. **developer.paypal.com → Apps & Credentials → Live.** Open the app whose client id
   starts `AUA-QAgtZuQufDd…` — that's the one your subscribe buttons already use.
2. Copy the **Client ID** and **Secret** into `site/cw-config.php`.
3. In that same app, **Add Webhook**:
   - URL: `https://corewavecredit.com/paypal-webhook.php`
   - Events: *Payment capture completed*, *Payment sale completed*,
     *Billing subscription activated*, *Billing subscription cancelled*,
     *Payment capture refunded*
4. Copy the **Webhook ID** it gives you into `cw-config.php` as well.
5. Use PayPal's **Simulate webhook** button and confirm an email arrives.

Until steps 2 and 4 are done, `paypal-webhook.php` still emails you but marks the
subject **UNVERIFIED** — because without the credentials it cannot tell PayPal apart
from anyone else who finds the URL. Don't treat those as confirmation of payment.

### 4c. Test it properly

1. Re-zip, re-upload.
2. Enrol yourself and subscribe to the real $75 plan. You should get **three** emails:
   - "NEW SIGNUP (payment pending)" — the instant you pick a plan
   - "PAYPAL APPROVED" — when you finish PayPal's checkout
   - "PAYMENT VERIFIED" — from PayPal itself, once 4b is done
3. Cancel that subscription in PayPal — you should get a "Subscription CANCELLED" email.
4. While you're in Credit Repair Cloud, grab your **client portal URL** for the
   `TODO:PORTAL` spots.

### ⚠️ Two things to check before you rely on this

- **PayPal restricts credit repair.** Their Acceptable Use Policy lists credit repair
  and debt settlement among prohibited or restricted activities, and accounts do get
  frozen — with the balance held. Call PayPal, describe the business honestly, and get
  their answer before you route real money through it. If they say no, Stripe and Square
  have the same restriction; the usual answer is a high-risk merchant account.
- **CROA and advance fees.** Charging monthly before the work is performed is how the
  industry operates, but §1679b(b) restricts payment before services are fully
  performed. This is on the list in step 6 for your attorney — it applies to the payment
  flow you just switched on, not only to the wording on the legal pages.

---

## 5. Make the contact form send

Much simpler on PHP hosting than on Cloudflare — no API tokens, no environment variables.

### Two addresses, and only one of them is a destination

Everything the site sends **lands in `Webshowmedia@gmail.com`**. That's already set in
both `contact.php` and `cw-config.php` and needs no change.

`forms@corewavecredit.com` is the **From:** line — nothing is delivered to it, and it
cannot be changed to your Gmail address. Your server at `216.172.171.98` isn't
authorised to send as `@gmail.com`, so SPF would fail and Gmail treats mail claiming to
be from a Gmail address but arriving from an unrelated server as forged. It goes to
spam, or is refused. Sending as `@corewavecredit.com` passes, because your SPF record
names that server.

Replies aren't affected either way: the code sets `Reply-To` to the visitor's own
address, so hitting **Reply** in Gmail answers the customer.

### Make forms@ land in your Gmail too

**Do not create a cPanel mailbox for it.** Your MX records point at Namecheap
(`eforward1-5.registrar-servers.com`), so mail for the domain never reaches cPanel — a
cPanel mailbox would just sit there empty and confuse things. Add a forwarder where the
mail actually goes:

*Namecheap → Domain List → Manage → **Email Forwarding** → Add Forwarder*

| Alias | Forwards to |
|---|---|
| `forms` | `Webshowmedia@gmail.com` |

That's it — bounces and any stray replies land in the same Gmail, and there's no second
mailbox to check. While you're there, tell cPanel not to try delivering the domain's
mail itself: *cPanel → Email Routing → corewavecredit.com → **Remote Mail Exchanger***.

### ✅ SPF is already fixed (verified 2026-07-30)

`corewavecredit.com` publishes
`v=spf1 ip4:216.172.171.98 include:spf.efwd.registrar-servers.com ~all` — the hosting
server is authorised and your Namecheap forwarding still works. Nothing to do.

There is **no DMARC record**. Not required, and not worth adding until you've confirmed
mail is arriving reliably — a wrong policy silently bins your own leads.

1. Open `site/contact.php` and set the two values at the top:
   - `$CONTACT_TO` — where enquiries should land (e.g. `webshowmedia@gmail.com`)
   - `$CONTACT_FROM` — the address you just created, e.g. `forms@corewavecredit.com`
2. Open `site/cw-config.php` and set the same two values there — that file feeds the
   **enrolment and payment** emails, and it's deliberately separate so a change to one
   never silently breaks the other.
3. Re-zip and re-upload.
3. **Submit the form yourself and confirm the email arrives.** Check spam too.
4. If nothing arrives, look at cPanel → *Errors* / *Track Delivery*. Shared-host
   `mail()` is unreliable on some hosts — if yours is one, the fix is to send through
   SMTP instead. Tell me and I'll switch `contact.php` over.

There's a honeypot field already stopping lazy bots. If real spam gets through, add
Cloudflare Turnstile or reCAPTCHA.

---

## 6. Before you take a single paying client

Two things here are genuinely important, not box-ticking.

**Have a lawyer review the legal pages and your client contract.** You're a credit
repair organization, which puts you under the federal Credit Repair Organizations Act
(15 U.S.C. §1679) *and* your state's credit services statute. The three legal pages
are a careful starting draft — **not** legal advice, and no attorney has seen them.
Worth paying someone to look at:

- **Your fee structure.** CROA restricts charging for credit repair services before
  they're fully performed. Monthly billing is industry-standard, but how you structure
  and describe it matters, and getting it wrong carries real penalties.
- **Your client contract.** CROA requires a written contract with specific content, a
  separate written statement of rights given *before* signing, and a three-business-day
  cancellation right. CRC likely supplies templates — have them reviewed anyway.
- **State registration and bonding.** Several states require credit repair organizations
  to register and post a surety bond before taking clients. Some are strict about it.
- **The statutory notice** on `disclosure.html` — confirm it matches the current text
  of the statute word for word.

**Don't add testimonials or statistics you can't back up.** I left both out on purpose,
even though the design has slots for them, because invented reviews and made-up success
rates on a credit repair site are exactly what the FTC pursues. Send real ones — from
clients who've agreed in writing to be quoted — and I'll add them.

Also worth doing:
- Google Business Profile, so you appear in local search
- Submit `https://corewavecredit.com/sitemap.xml` in Google Search Console
- Read your own FAQ out loud and check every answer is true for how you actually work

---

## Deliberately not done

- **No testimonials or stats** — see above.
- **No analytics** — no tracking cookies are set. If you add any, `privacy.html` must
  be updated to disclose it.
- **No Content-Security-Policy** — written and commented out in `.htaccess`. The
  enrolment flow links out to PayPal rather than embedding it, so the policy as written
  should work; it stays off until someone tests it on the live site.
- **No card details ever touch this site** — payment happens on PayPal's own pages. The
  site stores a name, email, phone, optional state, and the plan chosen. The intake form
  deliberately does *not* ask for a Social Security number; that belongs in the
  encrypted client portal, not in an email to Gmail.
- **Fonts load from Google Fonts** — normal and fine; self-hosting would be marginally
  faster and more private if you ever care.
- **`_headers` and `functions/`** are Cloudflare-only leftovers. Harmless — keep them
  in case you ever move off CRC hosting, where they'd save redoing this work.
