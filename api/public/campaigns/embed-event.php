<?php
declare(strict_types=1);

require_once __DIR__ . '/_embed_cors.php';
mg_public_campaign_embed_cors();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_public_campaign_embed_cors();

function mg_campaign_embed_event_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_campaign_embed_event_url_host(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') return null;
    return mb_substr(strtolower($host), 0, 255);
}

function mg_campaign_embed_event_page_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    return $url;
}

function mg_campaign_embed_event_type(mixed $value): string
{
    $type = strtolower(trim((string)$value));
    return in_array($type, ['loaded', 'opened', 'submitted', 'error', 'invalid'], true) ? $type : 'loaded';
}

mg_require_method('POST');
$input = mg_input();
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$campaignType = trim((string)($input['campaign_type'] ?? ''));
$eventType = mg_campaign_embed_event_type($input['event_type'] ?? 'loaded');

if ($campaignRef === '' || strlen($campaignRef) > 180) mg_fail('Campaign is required.', 422);

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, merchant_user_id, campaign_type, status FROM campaigns WHERE status = \'active\' AND (public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign is not available.', 404);

    $originHost = mg_campaign_embed_event_url_host($input['embed_origin'] ?? $input['origin'] ?? ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $pageUrl = mg_campaign_embed_event_page_url($input['page_url'] ?? $input['url'] ?? null);
    $embedMode = mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['embed_mode'] ?? '')) ?: '', 0, 24) ?: null;
    $metadata = [
        'campaign_type' => $campaignType !== '' ? $campaignType : (string)$campaign['campaign_type'],
        'source' => mb_substr((string)($input['embed_source'] ?? 'website_embed'), 0, 80),
        'debug' => !empty($input['debug']),
    ];

    $insert = $pdo->prepare('INSERT INTO campaign_embed_events (public_id, campaign_id, merchant_user_id, event_type, origin_host, page_url, embed_mode, ip_address, user_agent, metadata_json, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    $insert->execute([
        mg_campaign_embed_event_uuid(),
        (int)$campaign['id'],
        (int)$campaign['merchant_user_id'],
        $eventType,
        $originHost,
        $pageUrl,
        $embedMode,
        mg_client_ip(),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    mg_ok(['recorded' => true, 'event_type' => $eventType], 'Campaign embed event recorded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'public.campaign_embed_event.failed', 'Unable to record campaign embed event.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_ok(['recorded' => false, 'event_type' => $eventType], 'Campaign embed event skipped.');
}
