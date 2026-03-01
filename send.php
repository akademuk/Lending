<?php
/**
 * Brewmist — Form Handler (Hardened)
 * Sends lead to Telegram + Email
 */

/* ── SECURITY HEADERS ── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* ── CORS — only your domain ── */
$allowed_origins = [
    'https://brewmist.com.ua',
    'https://www.brewmist.com.ua',
    'http://localhost',
    'http://127.0.0.1'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header('Access-Control-Allow-Origin: https://brewmist.com.ua');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── CONFIG ── */
$TG_BOT_TOKEN  = '8551171117:AAFEx-KT6aJQOtkPB-td-9t4LcoiJqS7IBo';
$TG_CHAT_ID    = '2110512187';
$EMAIL_TO      = 'akademuk24@gmail.com';
$EMAIL_FROM    = 'noreply@brewmist.com.ua';

/* ── RATE LIMITING (file-based, per IP) ── */
$rate_dir = sys_get_temp_dir() . '/bm_rate/';
if (!is_dir($rate_dir)) @mkdir($rate_dir, 0700, true);

$client_ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$client_ip  = preg_replace('/[^0-9a-f.:]/i', '', explode(',', $client_ip)[0]);
$rate_file  = $rate_dir . md5($client_ip) . '.json';
$rate_limit = 5;      // max requests
$rate_window = 300;   // per 5 minutes

$now = time();
$rate_data = [];
if (file_exists($rate_file)) {
    $rate_data = json_decode(file_get_contents($rate_file), true) ?: [];
    $rate_data = array_filter($rate_data, fn($t) => ($now - $t) < $rate_window);
}

if (count($rate_data) >= $rate_limit) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests. Try again later.']);
    exit;
}

$rate_data[] = $now;
file_put_contents($rate_file, json_encode(array_values($rate_data)), LOCK_EX);

/* ── GET DATA ── */
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // fallback for native form POST
}

/* ── SANITIZE HELPERS ── */
function clean(string $str, int $maxLen = 200): string {
    $str = trim($str);
    $str = mb_substr($str, 0, $maxLen, 'UTF-8');
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    return $str;
}

function escHtml(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function escTg(string $str): string {
    return str_replace(
        ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
        ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
        $str
    );
}

$name    = clean($input['name']    ?? '', 100);
$phone   = clean($input['phone']   ?? '', 30);
$company = clean($input['company'] ?? '', 200);
$volume  = clean($input['volume']  ?? '', 20);
$ts      = date('d.m.Y H:i');
$referer = clean($_SERVER['HTTP_REFERER'] ?? '', 500);

/* ── VALIDATION ── */
if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name and phone required']);
    exit;
}

// Phone: Ukrainian format
$cleanPhone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
if (!preg_match('/^(38)?0\d{9}$/', $cleanPhone)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid phone number']);
    exit;
}

// Name: at least 2 unicode letters
if (!preg_match('/\pL{2,}/u', $name)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid name']);
    exit;
}

// Honeypot
if (!empty($input['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$results = ['tg' => false, 'email' => false];

/* ── TELEGRAM ── */
$tgText = "☕ *Нова заявка з Brewmist*\n\n"
    . "👤 *Ім'я:* " . escTg($name) . "\n"
    . "📞 *Телефон:* " . escTg($phone) . "\n"
    . ($company ? "🏢 *Компанія:* " . escTg($company) . "\n" : '')
    . "📊 *Обсяг:* " . escTg($volume ?: '—') . "\n\n"
    . "🕐 _{$ts}_";

$ch = curl_init("https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'chat_id'    => $TG_CHAT_ID,
        'text'       => $tgText,
        'parse_mode' => 'Markdown'
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$tgResp = curl_exec($ch);
$tgCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$results['tg'] = ($tgCode === 200);

/* ── EMAIL ── */
$eName    = escHtml($name);
$ePhone   = escHtml($phone);
$eCompany = escHtml($company ?: '—');
$eVolume  = escHtml($volume ?: '—');
$eRef     = escHtml($referer ?: '—');

$subject = "Нова заявка Brewmist — " . mb_substr($name, 0, 50, 'UTF-8');

$body = <<<HTML
<html>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;">
<h2 style="color:#493632;">Нова заявка з сайту Brewmist</h2>
<table style="border-collapse:collapse;width:100%;">
  <tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;">Ім'я</td><td style="padding:8px 12px;border-bottom:1px solid #eee;">{$eName}</td></tr>
  <tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;">Телефон</td><td style="padding:8px 12px;border-bottom:1px solid #eee;"><a href="tel:{$ePhone}">{$ePhone}</a></td></tr>
  <tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;">Компанія</td><td style="padding:8px 12px;border-bottom:1px solid #eee;">{$eCompany}</td></tr>
  <tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;">Обсяг напоїв/день</td><td style="padding:8px 12px;border-bottom:1px solid #eee;">{$eVolume}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:bold;">Час</td><td style="padding:8px 12px;">{$ts}</td></tr>
</table>
<p style="margin-top:16px;font-size:12px;color:#999;">Джерело: {$eRef}</p>
</body>
</html>
HTML;

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Brewmist <{$EMAIL_FROM}>\r\n";
$headers .= "Reply-To: {$EMAIL_FROM}\r\n";

$results['email'] = mail($EMAIL_TO, $subject, $body, $headers);

/* ── RESPONSE ── */
$ok = $results['tg'] || $results['email'];
http_response_code($ok ? 200 : 500);
echo json_encode(['ok' => $ok, 'results' => $results]);
