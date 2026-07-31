<?php
/**
 * CoreWave Credit — shared settings for the enrolment flow.
 *
 * Used by enroll.php (intake) and paypal-webhook.php (payment confirmation).
 * Edit the values below; nothing else in those two files needs touching.
 *
 * NOTE: contact.php keeps its own copy of the destination address at the top of
 * that file. If you change the inbox here, change it there too.
 */

return [

    // ---- where notifications go ------------------------------------------
    // Every enrolment and payment email lands here.
    'notify_to'   => 'Webshowmedia@gmail.com',

    // The "From" address. It MUST be on a domain your hosting is allowed to
    // send as, or the mail lands in spam (or is rejected outright). On cPanel,
    // create this mailbox first: Email Accounts -> Create.
    'notify_from' => 'forms@corewavecredit.com',

    // ---- PayPal webhook verification -------------------------------------
    // Until these three are filled in, paypal-webhook.php still sends you an
    // email but marks it UNVERIFIED, because without them anyone who finds the
    // URL could fake a "payment received" notice. Get them from
    // developer.paypal.com -> Apps & Credentials (client id / secret), and
    // from the webhook you create there (webhook id).
    'paypal_env'        => 'live',   // 'live' or 'sandbox'
    'paypal_client_id'  => '',       // TODO:PAYPAL
    'paypal_secret'     => '',       // TODO:PAYPAL
    'paypal_webhook_id' => '',       // TODO:PAYPAL

    // ---- where enrolment records are kept --------------------------------
    // Small JSON files, one per signup, so the payment email can quote the
    // person's details. Leave null to auto-pick: one level ABOVE public_html
    // if that is writable (best — not reachable from the web at all), else a
    // locked-down folder next to this file.
    'data_dir' => null,

    // Records older than this are deleted automatically.
    'retain_days' => 90,
];
