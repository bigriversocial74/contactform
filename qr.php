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

function mg_qr_scan_campaign_event(PDO $pdo, array $qr, string $scanId, array $metadata): void
{
    $campaignRef = strtolower(trim((string)($qr['campaign_ref'] ?? '')));
    if ($campaignRef === '') return;
    $stmt = $pdo->prepare('SELECT id,public_id,campaign_type,merchant_user_id FROM campaigns WHERE merchant_user_id=? AND (public_id=? OR public_slug=?) LIMIT 1');
    $stmt->execute([(int)$qr['merchant_user_id'], $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) return;
    $metadata['qr_id'] = (string)$qr['public_id'];
    $metadata['qr_short_code'] = (string)$qr['short_code'];
    $metadata['scan_id'] = $scanId;
    $metadata['campaign_public_id'] = (string)$campaign['public_id'];
    $metadata['campaign_type'] = (string)$campaign['campaign_type'];
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([mg_public_uuid(), (int)$campaign['merchant_user_id'], (int)$campaign['id'], null, null, 'qr.scanned', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

$code = trim((string) ($_GET['c'] ?? ''));
if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{6,80}$/', $code)) {
    http_response_code(404);
    echo 'QR code not found.';
    exit;
}

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id, public_id, merchant_user_id, short_code, destination_url, status, qr_type, campaign_ref, product_ref, metadata_json FROM merchant_qr_codes WHERE short_code = ? LIMIT 1');
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
        $metadata = ['source' => 'qr_redirect', 'qr_type' => (string)($qr['qr_type'] ?? ''), 'campaign_ref' => $qr['campaign_ref'] ?? null, 'product_ref' => $qr['product_ref'] ?? null];
        $scan = $pdo->prepare('INSERT INTO merchant_qr_code_scans (public_id,qr_code_id,ip_hash,user_agent_hash,referer_url,metadata_json,scanned_at) VALUES (?,?,?,?,?,?,NOW())');
        $scan->execute([$scanId, (int) $qr['id'], $ipHash, $uaHash, $referer, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $pdo->prepare('UPDATE merchant_qr_codes SET scan_count=scan_count+1,last_scanned_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $qr['id']]);
        mg_qr_scan_campaign_event($pdo, $qr, $scanId, $metadata);
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
