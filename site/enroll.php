<?php
/**
 * POST /enroll.php — CoreWave Credit enrolment handler.
 *
 * Two stages, both posted as JSON by assets/js/enroll.js:
 *
 *   stage = "intake"    the visitor filled in the form and picked a plan.
 *                       Emails you straight away — BEFORE they reach PayPal —
 *                       so you have the lead even if they never pay. Returns a
 *                       reference number the rest of the flow carries around.
 *
 *   stage = "returned"  PayPal sent them back to thank-you.html. Emails you a
 *                       heads-up, clearly marked as NOT proof of payment:
 *                       anyone can open that URL. The email that actually
 *                       confirms money arrived comes from paypal-webhook.php.
 *
 * Settings (inbox, sender, retention) live in cw-config.php.
 */

require __DIR__ . '/cw-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    cw_json(['error' => 'Method not allowed.'], 405);
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

// Honeypot — real visitors never see this field. Pretend success so bots move on.
if (cw_clean($data, 'website') !== '') {
    cw_json(['ok' => true, 'ref' => cw_reference()]);
}

cw_store_prune();

$stage = cw_clean($data, 'stage', 20);
$plans = cw_plans();

/* ---------------------------------------------------------------------------
 * Stage 2a: PayPal's button reported that the subscriber approved.
 *
 * Stronger than "returned" below — this comes from PayPal's own SDK callback
 * and carries the subscription id. Still not the same as money settling, which
 * only paypal-webhook.php can confirm.
 * ------------------------------------------------------------------------- */
if ($stage === 'subscribed') {
    $ref   = strtoupper(cw_clean($data, 'ref', 16));
    $subId = cw_clean($data, 'subscriptionId', 60);
    $plan  = strtolower(cw_clean($data, 'plan', 20));

    if ($subId === '' || !preg_match('/^[A-Za-z0-9\-]{5,60}$/', $subId)) {
        cw_json(['error' => 'Missing subscription id.'], 400);
    }

    $record = cw_store_get($ref);
    if ($record) {
        if (!empty($record['subscribed_at'])) cw_json(['ok' => true]);  // already told you
        $record['subscribed_at']   = gmdate('c');
        $record['subscription_id'] = $subId;
        cw_store_put($ref, $record);
        $name  = trim($record['firstName'] . ' ' . $record['lastName']);
        $email = $record['email'];
        $plan  = $record['plan'];
    } else {
        // Storage unavailable, or an unknown reference. Still worth telling you.
        if (!cw_rate_ok('subscribed', 12)) cw_json(['ok' => true]);
        $name  = 'Unknown (no signup record found)';
        $email = '';
    }

    $chosen = isset($plans[$plan]) ? $plans[$plan] : null;

    $body = "$name approved the PayPal subscription.\n\n"
          . 'Reference: ' . ($ref !== '' ? $ref : '-') . "\n"
          . 'Plan: ' . ($chosen ? $chosen['label'] . " (\${$chosen['price']}/month)" : '-') . "\n"
          . 'Subscription ID: ' . $subId . "\n"
          . ($email !== '' ? "Email: $email\n" : '')
          . ($record && $record['phone'] !== '' ? "Phone: {$record['phone']}\n" : '')
          . 'Approved: ' . gmdate('c') . "\n\n"
          . "PayPal's own confirmation follows as a separate 'PAYMENT VERIFIED'\n"
          . "email once the first payment settles. If that one never arrives,\n"
          . "check the subscription in PayPal before starting work.\n";

    cw_mail(
        'PAYPAL APPROVED - ' . $name . ($chosen ? ' - ' . $chosen['label'] : '') . ' - ' . $subId,
        $body,
        $email,
        $name
    );
    cw_json(['ok' => true]);
}

/* ---------------------------------------------------------------------------
 * Stage 2b: back from PayPal without an approval callback.
 * ------------------------------------------------------------------------- */
if ($stage === 'returned') {
    $ref = strtoupper(cw_clean($data, 'ref', 16));
    if (!preg_match('/^CW-[A-Z0-9]{4,12}$/', $ref)) {
        cw_json(['error' => 'Unknown reference.'], 400);
    }

    $record = cw_store_get($ref);
    if (!$record) {
        // No stored record (storage unavailable, or an old/typed reference).
        // Not worth an email — the intake email already went out.
        cw_json(['ok' => true]);
    }
    // Only ever notify once per reference, however many times the page is opened.
    if (!empty($record['returned_at'])) {
        cw_json(['ok' => true]);
    }

    $record['returned_at'] = gmdate('c');
    cw_store_put($ref, $record);

    $plan  = isset($plans[$record['plan']]) ? $plans[$record['plan']] : null;
    $name  = trim($record['firstName'] . ' ' . $record['lastName']);
    $price = $plan ? '$' . $plan['price'] . '/month' : 'unknown';

    $body = "$name came back from PayPal checkout.\n\n"
          . "Reference: $ref\n"
          . "Plan: " . ($plan ? $plan['label'] : $record['plan']) . " ($price)\n"
          . "Email: {$record['email']}\n"
          . "Phone: " . ($record['phone'] !== '' ? $record['phone'] : '-') . "\n"
          . "Returned: " . gmdate('c') . "\n\n"
          . "--------------------------------------------------------------\n"
          . "THIS IS NOT PROOF OF PAYMENT.\n"
          . "It only means their browser landed on the thank-you page, which\n"
          . "anyone can open. Check your PayPal account, or wait for the\n"
          . "'PAYMENT VERIFIED' email that paypal-webhook.php sends once PayPal\n"
          . "itself confirms the money.\n"
          . "--------------------------------------------------------------\n";

    cw_mail("Returned from PayPal (unconfirmed) - $name - $ref", $body, $record['email'], $name);
    cw_json(['ok' => true]);
}

/* ---------------------------------------------------------------------------
 * Stage 1: the intake form.
 * ------------------------------------------------------------------------- */
if (!cw_rate_ok('intake')) {
    cw_json(['error' => 'Too many signups from this connection. Please try again later, or email us.'], 429);
}

$firstName = cw_clean($data, 'firstName', 80);
$lastName  = cw_clean($data, 'lastName', 80);
$email     = cw_clean($data, 'email', 200);
$phone     = cw_clean($data, 'phone', 40);
$state     = cw_clean($data, 'state', 60);
$plan      = strtolower(cw_clean($data, 'plan', 20));
$hear      = cw_clean($data, 'hear', 120);
$notes     = cw_clean($data, 'notes', 2000);
$consent   = cw_clean($data, 'consent', 10);

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
    cw_json(['error' => 'Please fill in your name, email, and phone number.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    cw_json(['error' => 'That email address does not look right.'], 400);
}
if (preg_match_all('/\d/', $phone) < 10) {
    cw_json(['error' => 'Please enter a full phone number including area code.'], 400);
}
if (!isset($plans[$plan])) {
    cw_json(['error' => 'Please choose a plan.'], 400);
}
if ($consent !== 'yes') {
    cw_json(['error' => 'Please tick the box so we may contact you about your enrolment.'], 400);
}

$ref  = cw_reference();
$name = "$firstName $lastName";

$record = [
    'ref'       => $ref,
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'email'     => $email,
    'phone'     => $phone,
    'state'     => $state,
    'plan'      => $plan,
    'hear'      => $hear,
    'notes'     => $notes,
    'created'   => gmdate('c'),
    'ip'        => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
];
cw_store_put($ref, $record);

$chosen = $plans[$plan];

$body = "New signup from the CoreWave Credit website.\n"
      . "Payment has NOT been confirmed yet - this is the intake form only.\n\n"
      . "Reference: $ref\n"
      . "Plan: {$chosen['label']} (\${$chosen['price']}/month)\n\n"
      . "Name: $name\n"
      . "Email: $email\n"
      . "Phone: $phone\n"
      . "State: " . ($state !== '' ? $state : '-') . "\n"
      . "Heard about us: " . ($hear !== '' ? $hear : '-') . "\n"
      . "Submitted: " . gmdate('c') . "\n";

if ($notes !== '') {
    $body .= "\nWhat they told us:\n$notes\n";
}

$body .= "\nNext: they were shown the PayPal link for this plan. You'll get a\n"
       . "separate 'PAYMENT VERIFIED' email once PayPal confirms the payment.\n"
       . "If that never arrives, follow up - they filled the form but didn't pay.\n";

$sent = cw_mail(
    "NEW SIGNUP (payment pending) - $name - {$chosen['label']} \${$chosen['price']} - $ref",
    $body,
    $email,
    $name
);

if (!$sent) {
    // The customer still needs their reference and the PayPal link, so return
    // it and let the front end fall back to a pre-filled email for the lead.
    cw_json(['error' => 'Could not send right now.', 'ref' => $ref], 502);
}

cw_json(['ok' => true, 'ref' => $ref]);
