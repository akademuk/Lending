<?php
/**
 * Brewmist — обработчик формы
 * Отправляет заявку в Telegram + на email
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
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
$TG_BOT_TOKEN = '8551171117:AAFEx-KT6aJQOtkPB-td-9t4LcoiJqS7IBo';
$TG_CHAT_ID   = '2110512187';
$EMAIL_TO      = 'akademuk24@gmail.com';
$EMAIL_FROM    = 'noreply@brewmist.com.ua';

/* ── GET DATA ── */
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // fallback to POST form data
    $input = $_POST;
}

$name    = trim($input['name']    ?? '');
$phone   = trim($input['phone']   ?? '');
$company = trim($input['company'] ?? '—');
$volume  = trim($input['volume']  ?? '—');
$ts      = date('d.m.Y H:i');

// Basic validation
if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name and phone required']);
    exit;
}

// Honeypot check
if (!empty($input['website'])) {
    // Bot detected — silently return success
    echo json_encode(['ok' => true]);
    exit;
}

$results = ['tg' => false, 'email' => false];

/* ── TELEGRAM ── */
$tgText = "☕ *Нова заявка з Brewmist*\n\n"
    . "👤 *Ім'я:* {$name}\n"
    . "📞 *Телефон:* {$phone}\n"
    . ($company !== '—' ? "🏢 *Компанія:* {$company}\n" : '')
    . "📊 *Обсяг напоїв/день:* {$volume}\n\n"
    . "🕐 _{$ts}_";

$tgPayload = json_encode([
    'chat_id'    => $TG_CHAT_ID,
    'text'       => $tgText,
    'parse_mode' => 'Markdown'
]);

$ch = curl_init("https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $tgPayload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$tgResp = curl_exec($ch);
$tgCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$results['tg'] = ($tgCode === 200);

/* ── EMAIL ── */
$subject = "☕ Нова заявка Brewmist — {$name}";

$body = "
<html>
<body style='font-family:Arial,sans-serif;color:#333;max-width:600px;'>
<h2 style='color:#493632;'>☕ Нова заявка з сайту Brewmist</h2>
<table style='border-collapse:collapse;width:100%;'>
  <tr><td style='padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;'>Ім'я</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$name}</td></tr>
  <tr><td style='padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;'>Телефон</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'><a href='tel:{$phone}'>{$phone}</a></td></tr>
  <tr><td style='padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;'>Компанія</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$company}</td></tr>
  <tr><td style='padding:8px 12px;border-bottom:1px solid #eee;font-weight:bold;'>Обсяг напоїв/день</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$volume}</td></tr>
  <tr><td style='padding:8px 12px;font-weight:bold;'>Час</td><td style='padding:8px 12px;'>{$ts}</td></tr>
</table>
</body>
</html>
";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Brewmist <{$EMAIL_FROM}>\r\n";
$headers .= "Reply-To: {$EMAIL_FROM}\r\n";

$results['email'] = mail($EMAIL_TO, $subject, $body, $headers);

/* ── RESPONSE ── */
$ok = $results['tg'] || $results['email'];
http_response_code($ok ? 200 : 500);
echo json_encode(['ok' => $ok, 'results' => $results]);
