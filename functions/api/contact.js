/**
 * POST /api/contact — CoreWave Credit contact form handler.
 *
 * Runs as a Cloudflare Pages Function. Sends the submission to the business
 * inbox using the Cloudflare Email Sending REST API.
 *
 * Required environment variables (set these in the Cloudflare dashboard under
 * Workers & Pages -> your project -> Settings -> Variables and Secrets):
 *
 *   CF_ACCOUNT_ID    your Cloudflare account ID (plain variable)
 *   CF_EMAIL_TOKEN   an API token with the Email Sending permission (SECRET)
 *   CONTACT_TO       where enquiries should land, e.g. webshowmedia@gmail.com
 *   CONTACT_FROM     a sender on your verified domain, e.g. forms@corewavecredit.com
 *
 * Until those are set the endpoint returns 503, and the form on the site falls
 * back to opening the visitor's email client with their message pre-filled — so
 * a misconfiguration slows a lead down but never silently loses one.
 */

const MAX_MESSAGE = 4000;

// Control characters, minus tab (\t), newline (\n) and carriage return (\r),
// which are legitimate inside a message body.
const CONTROL_CHARS = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g;

/** Strip control characters, trim, and cap length. */
function clean(value, max = 200) {
  return String(value == null ? '' : value)
    .replace(CONTROL_CHARS, '')
    .trim()
    .slice(0, max);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function json(body, status) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
}

async function handlePost(request, env) {
  let payload;
  try {
    payload = await request.json();
  } catch {
    return json({ error: 'Expected a JSON body.' }, 400);
  }

  // Honeypot: a real visitor never sees this field, so anything in it is a bot.
  // Return 200 so the bot believes it succeeded and doesn't retry.
  if (clean(payload.website)) {
    return json({ ok: true }, 200);
  }

  const firstName = clean(payload.firstName, 80);
  const lastName = clean(payload.lastName, 80);
  const email = clean(payload.email, 200);
  const phone = clean(payload.phone, 40);
  const interest = clean(payload.interest, 120);
  const message = clean(payload.message, MAX_MESSAGE);
  const consent = clean(payload.consent, 10);

  if (!firstName || !lastName || !email || !message) {
    return json({ error: 'Please fill in your name, email, and message.' }, 400);
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
    return json({ error: 'That email address does not look right.' }, 400);
  }
  if (consent !== 'yes') {
    return json({ error: 'Please tick the consent box so we may reply to you.' }, 400);
  }

  const { CF_ACCOUNT_ID, CF_EMAIL_TOKEN, CONTACT_TO, CONTACT_FROM } = env;
  if (!CF_ACCOUNT_ID || !CF_EMAIL_TOKEN || !CONTACT_TO || !CONTACT_FROM) {
    console.error('Contact form is not configured — missing environment variables.');
    return json({ error: 'Email delivery is not configured yet.' }, 503);
  }

  const name = `${firstName} ${lastName}`;
  const rows = [
    ['Name', name],
    ['Email', email],
    ['Phone', phone || '—'],
    ['Interested in', interest || '—'],
    ['Submitted', new Date().toISOString()]
  ];

  const text =
    'New enquiry from the CoreWave Credit website\n\n' +
    rows.map(([k, v]) => `${k}: ${v}`).join('\n') +
    `\n\nMessage:\n${message}\n`;

  const html =
    '<h2 style="font-family:sans-serif">New enquiry from the website</h2>' +
    '<table style="font-family:sans-serif;font-size:14px;border-collapse:collapse">' +
    rows
      .map(
        ([k, v]) =>
          `<tr><td style="padding:4px 12px 4px 0;color:#54637c">${escapeHtml(k)}</td>` +
          `<td style="padding:4px 0"><strong>${escapeHtml(v)}</strong></td></tr>`
      )
      .join('') +
    '</table>' +
    '<p style="font-family:sans-serif;font-size:14px;color:#54637c;margin-top:20px">Message:</p>' +
    `<p style="font-family:sans-serif;font-size:15px;white-space:pre-wrap">${escapeHtml(message)}</p>`;

  let res;
  try {
    res = await fetch(
      `https://api.cloudflare.com/client/v4/accounts/${CF_ACCOUNT_ID}/email/sending/send`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${CF_EMAIL_TOKEN}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          to: CONTACT_TO,
          from: { address: CONTACT_FROM, name: 'CoreWave Credit website' },
          reply_to: { address: email, name },
          subject: `Website enquiry — ${name}`,
          text,
          html
        })
      }
    );
  } catch (err) {
    console.error('Email send threw:', err);
    return json({ error: 'Could not send right now.' }, 502);
  }

  if (!res.ok) {
    console.error(`Email send failed: HTTP ${res.status} — ${await res.text()}`);
    return json({ error: 'Could not send right now.' }, 502);
  }

  return json({ ok: true }, 200);
}

export async function onRequest({ request, env }) {
  if (request.method === 'POST') return handlePost(request, env);
  if (request.method === 'OPTIONS') {
    return new Response(null, { status: 204, headers: { Allow: 'POST, OPTIONS' } });
  }
  return new Response('Method not allowed', { status: 405, headers: { Allow: 'POST' } });
}
