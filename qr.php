<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';

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
    if (!$qr || !in_array((string) $qr['status'], ['active', 'draft'], true)) {
        http_response_code(404);
        echo 'QR code not available.';
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

    $destination = (string) $qr['destination_url'];
    header('Cache-Control: no-store, private');
    header('Location: ' . $destination, true, 302);
    exit;
} catch (Throwable) {
    http_response_code(503);
    echo 'QR code service unavailable.';
    exit;
}
