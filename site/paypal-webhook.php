<?php
/**
 * POST /paypal-webhook.php — the only thing on this site that can honestly say
 * "payment verified".
 *
 * PayPal calls this URL when money moves. The request is checked against
 * PayPal's own verification API before anything is emailed, so a stranger who
 * finds the URL cannot fake a payment notice.
 *
 * ---------------------------------------------------------------------------
 * SETUP (takes about five minutes, do it once)
 * ---------------------------------------------------------------------------
 *  1. Go to developer.paypal.com -> log in -> Apps & Credentials -> Live.
 *  2. Create an app (any name, e.g. "CoreWave website"). Copy the Client ID
 *     and Secret into cw-config.php.
 *  3. Still in that app, scroll to "Webhooks" -> Add Webhook.
 *       Webhook URL:  https://corewavecredit.com/paypal-webhook.php
 *       Event types:  Payment capture completed
 *                     Payment sale completed
 *                     Billing subscription activated
 *                     Billing subscription cancelled
 *                     Payment capture refunded
 *  4. Save, then copy the Webhook ID it shows into cw-config.php.
 *  5. Use "Simulate webhook" in the PayPal dashboard to check an email arrives.
 *
 * Until steps 2 and 4 are done, this file still emails you when PayPal calls,
 * but the subject line says UNVERIFIED — treat those as a nudge to go and look
 * in your PayPal account, not as confirmation.
 * ---------------------------------------------------------------------------
 */

require __DIR__ . '/cw-lib.php';

$cfg = cw_config();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$raw   = file_get_contents('php://input');
$event = json_decode($raw, true);

if (!is_array($event) || empty($event['event_type'])) {
    http_response_code(400);
    exit;
}

// Bound the damage if someone hammers the endpoint.
if (!cw_rate_ok('webhook', 60)) {
    http_response_code(429);
    exit;
}

/* ---------------------------------------------------------------------------
 * Verify the call really came from PayPal.
 * ------------------------------------------------------------------------- */

/** Read one of PayPal's transmission headers, whatever case the server used. */
function cw_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, $name) === 0) return $v;
        }
    }
    return '';
}

/**
 * POST to PayPal. Returns [http status, decoded body].
 *
 * $authHeader is the complete Authorization header value, e.g. "Bearer x..."
 * or "Basic y...". Uses cURL when available and a stream context when not,
 * because shared hosts vary.
 */
function cw_paypal_post($url, $payload, $authHeader, $contentType = 'application/json') {
    $headers = ["Content-Type: $contentType", "Authorization: $authHeader", 'Accept: application/json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) error_log('CoreWave PayPal call failed: ' . curl_error($ch));
        curl_close($ch);
        return [$status, json_decode((string) $body, true)];
    }

    // No cURL on this host — fall back to a stream context.
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $payload,
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $body   = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return [$status, json_decode((string) $body, true)];
}

$apiBase = ($cfg['paypal_env'] === 'sandbox')
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';

$verified   = false;
$verifyNote = '';

if ($cfg['paypal_client_id'] !== '' && $cfg['paypal_secret'] !== '' && $cfg['paypal_webhook_id'] !== '') {

    list($tokStatus, $tok) = cw_paypal_post(
        $apiBase . '/v1/oauth2/token',
        'grant_type=client_credentials',
        'Basic ' . base64_encode($cfg['paypal_client_id'] . ':' . $cfg['paypal_secret']),
        'application/x-www-form-urlencoded'
    );

    if ($tokStatus === 200 && !empty($tok['access_token'])) {
        $payload = json_encode([
            'transmission_id'   => cw_header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => cw_header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url'          => cw_header('PAYPAL-CERT-URL'),
            'auth_algo'         => cw_header('PAYPAL-AUTH-ALGO'),
            'transmission_sig'  => cw_header('PAYPAL-TRANSMISSION-SIG'),
            'webhook_id'        => $cfg['paypal_webhook_id'],
            'webhook_event'     => $event,
        ]);

        list($vStatus, $vBody) = cw_paypal_post(
            $apiBase . '/v1/notifications/verify-webhook-signature',
            $payload,
            'Bearer ' . $tok['access_token']
        );

        $verified = ($vStatus === 200 && isset($vBody['verification_status']) && $vBody['verification_status'] === 'SUCCESS');

        if (!$verified) {
            // A failed signature check means this was not PayPal. Say nothing,
            // send nothing — that is the whole point of the check.
            error_log('CoreWave: PayPal webhook signature check failed (' . $event['event_type'] . ').');
            http_response_code(400);
            exit;
        }
    } else {
        $verifyNote = "Could not reach PayPal to verify this notification (token request returned HTTP $tokStatus).";
        error_log('CoreWave: PayPal token request failed, HTTP ' . $tokStatus);
    }
} else {
    $verifyNote = "PayPal API credentials are not filled in yet (see cw-config.php), so this notification could not be verified.";
}

/* ---------------------------------------------------------------------------
 * Pull the useful bits out of the event.
 * ------------------------------------------------------------------------- */

/** Dig a value out of nested arrays: cw_dig($a, 'resource.subscriber.email_address'). */
function cw_dig($arr, $path, $default = '') {
    foreach (explode('.', $path) as $key) {
        if (!is_array($arr) || !isset($arr[$key])) return $default;
        $arr = $arr[$key];
    }
    return is_scalar($arr) ? (string) $arr : $default;
}

/** First non-empty value from a list of paths. */
function cw_first($arr, array $paths) {
    foreach ($paths as $p) {
        $v = cw_dig($arr, $p);
        if ($v !== '') return $v;
    }
    return '';
}

$type = (string) $event['event_type'];

$payerEmail = cw_first($event, [
    'resource.subscriber.email_address',
    'resource.payer.email_address',
    'resource.payer_email',
    'resource.payer_info.email',
    'resource.purchase_units.0.payee.email_address',
]);

$payerFirst = cw_first($event, ['resource.subscriber.name.given_name', 'resource.payer.name.given_name']);
$payerLast  = cw_first($event, ['resource.subscriber.name.surname',    'resource.payer.name.surname']);
$payerName  = trim("$payerFirst $payerLast");

$amount = cw_first($event, [
    'resource.amount.value',
    'resource.amount.total',
    'resource.billing_info.last_payment.amount.value',
    'resource.seller_receivable_breakdown.gross_amount.value',
]);
$currency = cw_first($event, [
    'resource.amount.currency_code',
    'resource.amount.currency',
    'resource.billing_info.last_payment.amount.currency_code',
]);

// If the PayPal button was set up to pass our reference through, it arrives here.
$custom = cw_first($event, ['resource.custom_id', 'resource.custom', 'resource.invoice_id']);

/* ---------------------------------------------------------------------------
 * Match it back to the person who filled in the form.
 * ------------------------------------------------------------------------- */
$record = null;
$matchedBy = '';

if (preg_match('/CW-[A-Z0-9]{4,12}/i', $custom, $m)) {
    $record = cw_store_get(strtoupper($m[0]));
    if ($record) $matchedBy = 'reference passed through PayPal';
}
if (!$record && $payerEmail !== '') {
    $record = cw_store_find_by_email($payerEmail);
    if ($record) $matchedBy = 'PayPal email matched the signup form';
}

/* ---------------------------------------------------------------------------
 * Decide what kind of email this is.
 * ------------------------------------------------------------------------- */
$money = [
    'PAYMENT.CAPTURE.COMPLETED'      => 'Payment received',
    'PAYMENT.SALE.COMPLETED'         => 'Subscription payment received',
    'BILLING.SUBSCRIPTION.ACTIVATED' => 'Subscription started',
];
$alerts = [
    'BILLING.SUBSCRIPTION.CANCELLED' => 'Subscription CANCELLED',
    'BILLING.SUBSCRIPTION.SUSPENDED' => 'Subscription SUSPENDED',
    'PAYMENT.CAPTURE.REFUNDED'       => 'Payment REFUNDED',
    'PAYMENT.CAPTURE.DENIED'         => 'Payment DENIED',
    'PAYMENT.SALE.REFUNDED'          => 'Payment REFUNDED',
];

if (!isset($money[$type]) && !isset($alerts[$type])) {
    // Subscribed to something we don't act on. Acknowledge so PayPal stops retrying.
    http_response_code(200);
    echo 'ignored';
    exit;
}

$isMoney = isset($money[$type]);
$what    = $isMoney ? $money[$type] : $alerts[$type];

$plans     = cw_plans();
$planLabel = '';
if ($record && isset($plans[$record['plan']])) {
    $planLabel = $plans[$record['plan']]['label'] . ' ($' . $plans[$record['plan']]['price'] . '/month)';
} elseif ($amount !== '') {
    // No record — infer from the amount, which is the whole reason both plans
    // have distinct prices.
    foreach ($plans as $p) {
        if (abs((float) $amount - $p['price']) < 0.01) {
            $planLabel = $p['label'] . ' (matched by amount)';
        }
    }
}

$who = $record
    ? trim($record['firstName'] . ' ' . $record['lastName'])
    : ($payerName !== '' ? $payerName : 'Unknown customer');

$status  = $verified ? 'PAYMENT VERIFIED' : 'UNVERIFIED';
$subject = $isMoney
    ? "$status - $what - $who" . ($amount !== '' ? " - \$$amount" : '')
    : "$what - $who";

$lines = [];
$lines[] = $verified
    ? "PayPal confirmed this notification is genuine."
    : "*** NOT VERIFIED *** $verifyNote";
$lines[] = '';
$lines[] = "Event: $type ($what)";
if ($amount !== '')   $lines[] = "Amount: $amount " . ($currency !== '' ? $currency : '');
if ($planLabel !== '') $lines[] = "Plan: $planLabel";
$lines[] = 'PayPal time: ' . cw_first($event, ['create_time', 'resource.create_time', 'resource.update_time']);
$lines[] = '';

if ($record) {
    $lines[] = '--- From their signup form ---';
    $lines[] = 'Reference: ' . $record['ref'] . '   (' . $matchedBy . ')';
    $lines[] = 'Name: ' . trim($record['firstName'] . ' ' . $record['lastName']);
    $lines[] = 'Email: ' . $record['email'];
    $lines[] = 'Phone: ' . ($record['phone'] !== '' ? $record['phone'] : '-');
    if (!empty($record['state'])) $lines[] = 'State: ' . $record['state'];
    if (!empty($record['hear']))  $lines[] = 'Heard about us: ' . $record['hear'];
    $lines[] = 'Signed up: ' . $record['created'];
    if (!empty($record['notes'])) {
        $lines[] = '';
        $lines[] = 'What they told us:';
        $lines[] = $record['notes'];
    }
} else {
    $lines[] = '--- No matching signup form found ---';
    $lines[] = 'They may have paid from a PayPal link without filling in the form,';
    $lines[] = 'or paid using a different email address than they signed up with.';
    $lines[] = 'PayPal name: ' . ($payerName !== '' ? $payerName : '-');
    $lines[] = 'PayPal email: ' . ($payerEmail !== '' ? $payerEmail : '-');
    if ($custom !== '') $lines[] = 'Reference passed by PayPal: ' . $custom;
}

$lines[] = '';
$lines[] = $isMoney
    ? "Next step: set them up in Credit Repair Cloud and send the written contract\nand statement of rights before starting any work."
    : "Next step: stop work and stop billing for this client if that has not\nalready happened automatically.";

cw_mail($subject, implode("\n", $lines), $record ? $record['email'] : $payerEmail, $who);

// Mark the record so a repeat notification is obvious in the file, and so a
// follow-up glance at the folder shows who actually paid.
if ($record && $isMoney) {
    $record['paid_at']    = gmdate('c');
    $record['paid_event'] = $type;
    $record['paid_amount'] = $amount;
    $record['paid_verified'] = $verified;
    cw_store_put($record['ref'], $record);
}

http_response_code(200);
echo 'ok';
