<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';

function mg_qr_redirect_destination_safe(string $destination): bool
{
    if ($destination === '' || preg_match('/[\r\n]/', $destination)) return false;
    if (str_starts_with($destination, '//')) return false;
    if (str_starts_with($destination, '/')) return true;
    if (!filter_var($destination, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($destination);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return false;
    if (strtolower((string) $parts['scheme']) !== 'https') return false;
    if (!empty($parts['user']) || !empty($parts['pass'])) return false;
    $port = isset($parts['port']) ? (int) $parts['port'] : 443;
    return $port === 443;
}

$code = trim((string) ($_GET['c'] ?? ''));
if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{6,80}$/', $code)) {
    http_response_code(404);
    echo 'QR code not found.';
    exit;
}

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT id, public_id, destination_url, status FROM merchant_qr_codes WHERE short_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $qr = $stmt->fetch();
    if (!$qr || (string) $qr['status'] !== 'active') {
        http_response_code(404);
        echo 'QR code not available.';
        exit;
    }

    $destination = (string) $qr['destination_url'];
    if (!mg_qr_redirect_destination_safe($destination)) {
        http_response_code(422);
        echo 'QR destination is not available.';
        exit;
    }

    $secret = trim((string) getenv('MG_QR_SCAN_HASH_SECRET')) ?: trim((string) getenv('MG_DISTRIBUTION_HASH_SECRET')) ?: trim((string) getenv('MG_MEDIA_SIGNING_SECRET'));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ipHash = $secret !== '' && $ip !== '' ? hash_hmac('sha256', $ip, $secret) : null;
    $uaHash = $secret !== '' && $ua !== '' ? hash_hmac('sha256', $ua, $secret) : null;
    $referer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1000) ?: null;

    try {
        $scanId = function_exists('mg_public_uuid') ? mg_public_uuid() : bin2hex(random_bytes(16));
        $scan = $pdo->prepare('INSERT INTO merchant_qr_code_scans (public_id,qr_code_id,ip_hash,user_agent_hash,referer_url,metadata_json,scanned_at) VALUES (?,?,?,?,?,?,NOW())');
        $scan->execute([
            $scanId,
            (int) $qr['id'],
            $ipHash,
            $uaHash,
            $referer,
            json_encode(['source' => 'qr_redirect'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $pdo->prepare('UPDATE merchant_qr_codes SET scan_count=scan_count+1,last_scanned_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $qr['id']]);
    } catch (Throwable) {
        // Do not block the customer redirect if scan analytics fails.
    }

    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $destination, true, 302);
    exit;
} catch (Throwable) {
    http_response_code(503);
    echo 'QR code service unavailable.';
    exit;
}
