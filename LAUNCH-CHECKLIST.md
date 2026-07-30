# CoreWave Credit — Launch Checklist

Getting the built site live at **corewavecredit.com** on the Credit Repair Cloud
hosting you already pay for ($19.95/month).

Steps 1–3 put the site online. Steps 4–6 make it able to take clients and money.

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
│   ├── get-started.html     Get Started  ← the CRC signup form goes here
│   ├── privacy.html         Privacy Policy
│   ├── terms.html           Terms of Service
│   ├── disclosure.html      Credit Repair Disclosure + CROA rights
│   ├── 404.html
│   ├── contact.php          Contact form handler (this is the one your host uses)
│   ├── .htaccess            HTTPS redirect, security headers, compression, 404 page
│   ├── robots.txt / sitemap.xml
│   ├── _headers             Cloudflare-only; harmless here, blocked by .htaccess
│   └── assets/{css,js,img}
├── functions/api/contact.js Cloudflare-only alternative to contact.php — ignore for now
└── LAUNCH-CHECKLIST.md      this file
```

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
| `TODO:CRC-EMBED` | The signup form — see step 4 | get-started.html |
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

Every page links the assets as `site.css?v=2` and `site.js?v=2`. After editing either
file, increment that number across all pages:

```bash
cd /home/sean/Desktop/corecredit/site
perl -i -pe 's/site\.css\?v=\d+/site.css?v=3/g; s/site\.js\?v=\d+/site.js?v=3/g' *.html
```

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

## 4. Get the Credit Repair Cloud signup form in

**This is the step that turns a website into a business.** Until it's done, the Get
Started page tells visitors to call or email instead.

1. Log in to Credit Repair Cloud at **`app.creditrepaircloud.com`** — not
   `secure.mycreditrepairsite.com`, which is only your own subscription billing.
2. Look under **Settings** for the signup/lead form section. Depending on plan it's
   called **Web Leads**, **Sign Up Forms**, **Web Form**, or **Sign Up Links**.
3. Set the form up with your two plans — Individual $75/mo, Couples $125/mo.
4. Copy either the **embed code** or the **hosted link**.
5. In `site/get-started.html`, find `TODO:CRC-EMBED` and:
   - **Embed code:** replace the whole `<div class="embed-placeholder">…</div>` with it,
     keeping the surrounding `<div class="embed-frame">` so it picks up the card styling.
   - **Hosted link only:** don't paste an iframe — point the Get Started buttons at that
     URL instead. Send me the link and I'll wire it up properly.
6. While you're in there, grab your **client portal URL** for the `TODO:PORTAL` spots.
7. Re-zip, re-upload, then **test by enrolling yourself** and confirming payment lands.

---

## 5. Make the contact form send

Much simpler on PHP hosting than on Cloudflare — no API tokens, no environment variables.

**⚠️ Your cPanel currently shows `Email Accounts: 0`** — so `forms@corewavecredit.com`
does not exist yet. Sending "from" an address with no mailbox behind it is a fast route
to the spam folder, and some hosts reject it outright. Create it first:
*cPanel → Email Accounts → Create* (or make it a forwarder pointing at your Gmail).

1. Open `site/contact.php` and set the two values at the top:
   - `$CONTACT_TO` — where enquiries should land (e.g. `webshowmedia@gmail.com`)
   - `$CONTACT_FROM` — the address you just created, e.g. `forms@corewavecredit.com`
2. Re-zip and re-upload.
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
- **No Content-Security-Policy** — written and commented out in `.htaccess`, because
  enabling it now would silently block the CRC signup iframe. Turn it on after step 4.
- **Fonts load from Google Fonts** — normal and fine; self-hosting would be marginally
  faster and more private if you ever care.
- **`_headers` and `functions/`** are Cloudflare-only leftovers. Harmless — keep them
  in case you ever move off CRC hosting, where they'd save redoing this work.
