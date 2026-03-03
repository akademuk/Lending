<?php
/**
 * Brewmist — Form Handler
 * TODO: CRM integration (awaiting API from client)
 * Telegram + Email sending disabled per client request
 */

/* ── SECURITY HEADERS ── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* ── CORS — only your domain ── */
$allowed_origins = [
    'https://brewmist.com.ua',
    'https://www.brewmist.com.ua',
    'https://lp.timekairos.com.ua',
    'https://www.lp.timekairos.com.ua',
    'http://localhost',
    'http://127.0.0.1'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header('Access-Control-Allow-Origin: https://lp.timekairos.com.ua');
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
// CRM API endpoint — awaiting credentials from client
// $CRM_API_URL = '';
// $CRM_API_KEY = '';

// Telegram + Email disabled per client request
// $TG_BOT_TOKEN  = '8551171117:AAFEx-KT6aJQOtkPB-td-9t4LcoiJqS7IBo';
// $TG_CHAT_ID    = '2110512187';
// $EMAIL_TO      = 'orendabrewmist@gmail.com';
// $EMAIL_FROM    = 'admin@timekairos.com.ua';

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
    // Escape Markdown v1 special characters
    return str_replace(
        ['_', '*', '`', '['],
        ['\\_', '\\*', '\\`', '\\['],
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

$results = ['crm' => false];
$errors = [];

/* ── CRM INTEGRATION (TODO: awaiting API) ── */
// When CRM API is provided, send lead data here:
// $crmPayload = [
//     'name'    => $name,
//     'phone'   => $phone,
//     'company' => $company,
//     'volume'  => $volume,
//     'source'  => $referer,
//     'ts'      => $ts,
// ];

// For now, log lead to file as backup
$logDir = __DIR__ . '/leads/';
if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
$logEntry = json_encode([
    'name'    => $name,
    'phone'   => $phone,
    'company' => $company,
    'volume'  => $volume,
    'source'  => $referer,
    'ts'      => $ts,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ",\n";
@file_put_contents($logDir . 'leads.json', $logEntry, FILE_APPEND | LOCK_EX);
$results['crm'] = true; // treat as success until CRM is connected

/* ── RESPONSE ── */
$ok = $results['crm'];

// If native form POST (no JS), redirect to thanks page
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

if (!$isAjax) {
    header('Location: ' . ($ok ? 'thanks.html' : 'index.html?error=1'));
    exit;
}

http_response_code($ok ? 200 : 500);
echo json_encode(['ok' => $ok, 'results' => $results, 'errors' => (object)$errors]);
