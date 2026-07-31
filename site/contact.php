<?php
/**
 * Contact form handler for ordinary PHP hosting (cPanel, shared hosting, etc.).
 *
 * Use this INSTEAD of functions/api/contact.js when the site is hosted anywhere
 * other than Cloudflare Pages. To switch the form over, edit contact.html and
 * change the form's action from "/api/contact" to "/contact.php".
 *
 * Set CONTACT_TO below to the inbox that should receive enquiries.
 */

// ---- configuration -------------------------------------------------------
// WHERE THE EMAIL LANDS. The one that matters.
$CONTACT_TO   = 'webshowmedia@gmail.com';

// WHO IT APPEARS TO BE FROM — not a destination, nothing is delivered here.
// This cannot be a gmail.com address: the server isn't authorised to send as
// Gmail, so SPF fails and the message gets binned as forged. Replies go to the
// visitor instead, via the Reply-To header set below.
$CONTACT_FROM = 'forms@corewavecredit.com';
// --------------------------------------------------------------------------

header('Content-Type: application/json');
header('Cache-Control: no-store');

function fail($msg, $code) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    fail('Method not allowed.', 405);
}

// The form posts JSON; fall back to normal form encoding just in case.
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

/** Strip control characters, trim, cap length. */
function clean($data, $key, $max = 200) {
    $v = isset($data[$key]) ? (string) $data[$key] : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr(trim($v), 0, $max);
}

// Honeypot — real visitors never see this field. Pretend success so bots move on.
if (clean($data, 'website') !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

$firstName = clean($data, 'firstName', 80);
$lastName  = clean($data, 'lastName', 80);
$email     = clean($data, 'email', 200);
$phone     = clean($data, 'phone', 40);
$interest  = clean($data, 'interest', 120);
$message   = clean($data, 'message', 4000);
$consent   = clean($data, 'consent', 10);

if ($firstName === '' || $lastName === '' || $email === '' || $message === '') {
    fail('Please fill in your name, email, and message.', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('That email address does not look right.', 400);
}
if ($consent !== 'yes') {
    fail('Please tick the consent box so we may reply to you.', 400);
}

$name = "$firstName $lastName";

// Header injection guard: a newline in a header value can add arbitrary headers.
$safeEmail = preg_replace('/[\r\n]+/', '', $email);
$safeName  = preg_replace('/[\r\n]+/', '', $name);

$body = "New enquiry from the CoreWave Credit website\n\n"
      . "Name: $name\n"
      . "Email: $email\n"
      . "Phone: " . ($phone !== '' ? $phone : '-') . "\n"
      . "Interested in: " . ($interest !== '' ? $interest : '-') . "\n"
      . "Submitted: " . gmdate('c') . "\n\n"
      . "Message:\n$message\n";

$headers  = "From: CoreWave Credit website <$CONTACT_FROM>\r\n";
$headers .= "Reply-To: $safeName <$safeEmail>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "MIME-Version: 1.0\r\n";

$sent = @mail(
    $CONTACT_TO,
    "Website enquiry - $safeName",
    $body,
    $headers,
    "-f $CONTACT_FROM"
);

if (!$sent) {
    error_log('CoreWave contact form: mail() returned false.');
    fail('Could not send right now.', 502);
}

echo json_encode(['ok' => true]);
