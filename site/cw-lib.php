<?php
/**
 * CoreWave Credit — helpers shared by enroll.php and paypal-webhook.php.
 *
 * Nothing in here needs editing. Settings live in cw-config.php.
 */

/** Load settings, falling back to safe defaults if the file is missing. */
function cw_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $defaults = [
        'notify_to'         => 'Webshowmedia@gmail.com',
        'notify_from'       => 'forms@corewavecredit.com',
        'paypal_env'        => 'live',
        'paypal_client_id'  => '',
        'paypal_secret'     => '',
        'paypal_webhook_id' => '',
        'data_dir'          => null,
        'retain_days'       => 90,
    ];

    $file = __DIR__ . '/cw-config.php';
    $loaded = is_readable($file) ? include $file : null;
    $cfg = is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
    return $cfg;
}

/** The two plans, in one place. Prices are in whole dollars. */
function cw_plans() {
    return [
        'individual' => ['label' => 'Individual Plan', 'price' => 75,  'people' => 1],
        'couples'    => ['label' => 'Couples Plan',    'price' => 125, 'people' => 2],
    ];
}

/** Strip control characters, trim, cap length. */
function cw_clean($data, $key, $max = 200) {
    $v = isset($data[$key]) ? (string) $data[$key] : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr(trim($v), 0, $max);
}

/** JSON response + exit. */
function cw_json($body, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

/**
 * A short, human-readable reference the customer can quote and you can search
 * your inbox for. Example: CW-2R7K4M. Not secret, not a password.
 */
function cw_reference() {
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I — misread over the phone
    $out = '';
    for ($i = 0; $i < 6; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'CW-' . $out;
}

/**
 * Directory for enrolment records. Prefers a folder one level above the web
 * root, which no browser can reach. Falls back to a folder beside this file
 * with a deny-all .htaccess and a dummy index.html.
 *
 * Returns null if nothing is writable — callers must cope, because failing to
 * store a record is never a reason to drop a paying customer.
 */
function cw_data_dir() {
    static $dir = false;
    if ($dir !== false) return $dir;

    $cfg = cw_config();
    $candidates = [];
    if (!empty($cfg['data_dir'])) $candidates[] = rtrim($cfg['data_dir'], '/');
    $candidates[] = dirname(__DIR__) . '/cw-enrollments';  // above public_html
    $candidates[] = __DIR__ . '/cw-enrollments';           // last resort

    foreach ($candidates as $path) {
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) continue;
        if (!is_writable($path)) continue;

        // If it ended up inside the web root, lock it down.
        if (strpos($path, __DIR__) === 0) {
            $ht = $path . '/.htaccess';
            if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
            $ix = $path . '/index.html';
            if (!file_exists($ix)) @file_put_contents($ix, '');
        }
        $dir = $path;
        return $dir;
    }

    error_log('CoreWave: no writable directory for enrolment records.');
    $dir = null;
    return $dir;
}

/** Save (or overwrite) the record for a reference. Returns true on success. */
function cw_store_put($ref, array $record) {
    $dir = cw_data_dir();
    if (!$dir || !preg_match('/^CW-[A-Z0-9]{4,12}$/', $ref)) return false;
    $ok = @file_put_contents($dir . '/' . $ref . '.json', json_encode($record), LOCK_EX);
    if ($ok !== false) @chmod($dir . '/' . $ref . '.json', 0600);
    return $ok !== false;
}

/** Load a record by reference, or null. */
function cw_store_get($ref) {
    $dir = cw_data_dir();
    if (!$dir || !preg_match('/^CW-[A-Z0-9]{4,12}$/', $ref)) return null;
    $file = $dir . '/' . $ref . '.json';
    if (!is_readable($file)) return null;
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/**
 * Find the most recent record matching an email address. Used by the PayPal
 * webhook, which knows the payer's email but not our reference number.
 */
function cw_store_find_by_email($email) {
    $dir = cw_data_dir();
    if (!$dir || $email === '') return null;
    $needle = strtolower($email);
    $best = null;

    foreach ((array) glob($dir . '/CW-*.json') as $file) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || !isset($data['email'])) continue;
        if (strtolower($data['email']) !== $needle) continue;
        if ($best === null || ($data['created'] ?? '') > ($best['created'] ?? '')) $best = $data;
    }
    return $best;
}

/** Delete records past their retention window. Cheap enough to run per request. */
function cw_store_prune() {
    $dir = cw_data_dir();
    if (!$dir) return;
    $cfg = cw_config();
    $cutoff = time() - ((int) $cfg['retain_days'] * 86400);
    foreach ((array) glob($dir . '/CW-*.json') as $file) {
        if (@filemtime($file) < $cutoff) @unlink($file);
    }
}

/**
 * Very small per-IP throttle so a bot can't flood the inbox. Keeps one counter
 * file per IP per hour. Silently allows the request if it can't write.
 */
function cw_rate_ok($bucket, $limit = 6) {
    $dir = cw_data_dir();
    if (!$dir) return true;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $file = $dir . '/rate-' . $bucket . '-' . substr(hash('sha256', $ip), 0, 16) . '-' . gmdate('YmdH') . '.txt';
    $count = is_readable($file) ? (int) file_get_contents($file) : 0;
    if ($count >= $limit) return false;
    @file_put_contents($file, (string) ($count + 1), LOCK_EX);
    // Sweep last hour's counters.
    foreach ((array) glob($dir . '/rate-*.txt') as $old) {
        if (@filemtime($old) < time() - 7200) @unlink($old);
    }
    return true;
}

/**
 * Send a plain-text notification. $replyTo is optional and is scrubbed of
 * newlines — a newline in a header value lets an attacker add their own
 * headers.
 */
function cw_mail($subject, $body, $replyToEmail = '', $replyToName = '') {
    $cfg  = cw_config();
    $from = $cfg['notify_from'];
    $to   = $cfg['notify_to'];

    $subject = preg_replace('/[\r\n]+/', ' ', $subject);

    $headers  = "From: CoreWave Credit website <$from>\r\n";
    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $name  = preg_replace('/[\r\n]+/', '', $replyToName);
        $addr  = preg_replace('/[\r\n]+/', '', $replyToEmail);
        $headers .= "Reply-To: $name <$addr>\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    $sent = @mail($to, $subject, $body, $headers, "-f $from");
    if (!$sent) error_log("CoreWave: mail() failed for subject: $subject");
    return $sent;
}
