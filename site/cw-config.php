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

    // ---- WHERE THE EMAIL LANDS -------------------------------------------
    // This is the one that matters. Every signup and payment email arrives
    // here. Change this if you ever want notifications somewhere else.
    'notify_to'   => 'Webshowmedia@gmail.com',

    // ---- WHO THE EMAIL APPEARS TO BE FROM --------------------------------
    // NOT a destination — nothing is delivered here. It's the From: line.
    //
    // This cannot be a gmail.com address. Your server is not authorised to
    // send as Gmail, so SPF would fail and Gmail would treat the message as
    // forged and bin it. It must stay on corewavecredit.com, which your SPF
    // record does authorise.
    //
    // Replies don't come here either — the code sets Reply-To to the
    // customer's own address, so hitting Reply in Gmail answers them.
    //
    // Mail sent TO this address goes to Namecheap (that's where your MX
    // points), so forward it to your Gmail there — see LAUNCH-CHECKLIST step 5.
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
