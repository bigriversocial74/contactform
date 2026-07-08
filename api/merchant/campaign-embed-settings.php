<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_campaign_embed_settings_method(): string
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function mg_campaign_embed_settings_bool(mixed $value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true) ? 1 : 0;
}

function mg_campaign_embed_settings_layout(mixed $value): string
{
    $layout = strtolower(trim((string)$value));
    return in_array($layout, ['inline', 'button', 'compact'], true) ? $layout : 'inline';
}

function mg_campaign_embed_settings_text(mixed $value, int $max): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;
    return mb_substr(preg_replace('/\s+/u', ' ', $text) ?: $text, 0, $max);
}

function mg_campaign_embed_settings_normalize_domains(mixed $value): array
{
    if (is_string($value)) {
        $items = preg_split('/[\r\n,]+/', $value) ?: [];
    } elseif (is_array($value)) {
        $items = $value;
    } else {
        $items = [];
    }

    $domains = [];
    foreach ($items as $item) {
        $raw = strtolower(trim((string)$item));
        if ($raw === '') continue;
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $host = parse_url($raw, PHP_URL_HOST);
            $raw = is_string($host) ? strtolower($host) : '';
        }
        $raw = preg_replace('/^www\./', '', $raw) ?: $raw;
        $raw = preg_replace('/[^a-z0-9.\-]/', '', $raw) ?: '';
        if ($raw === '' || strlen($raw) > 255 || !str_contains($raw, '.')) continue;
        $domains[$raw] = true;
    }

    return array_slice(array_keys($domains), 0, 20);
}

function mg_campaign_embed_settings_empty_stats(): array
{
    return [
        'loaded' => 0,
        'opened' => 0,
        'submitted' => 0,
        'error' => 0,
        'last_event_at' => null,
        'last_origin_host' => null,
    ];
}

function mg_campaign_embed_settings_row(array $row = []): array
{
    $domains = [];
    $decoded = !empty($row['allowed_domains_json']) ? json_decode((string)$row['allowed_domains_json'], true) : null;
    if (is_array($decoded)) $domains = array_values(array_filter(array_map('strval', $decoded)));

    return [
        'embed_enabled' => array_key_exists('embed_enabled', $row) ? (bool)((int)$row['embed_enabled']) : true,
        'default_layout' => mg_campaign_embed_settings_layout($row['default_layout'] ?? 'inline'),
        'custom_button_text' => $row['custom_button_text'] ?? null,
        'custom_success_message' => $row['custom_success_message'] ?? null,
        'allowed_domains' => $domains,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_campaign_embed_settings_find_campaign(PDO $pdo, int $merchantId, mixed $campaignRef): array
{
    $ref = trim((string)$campaignRef);
    if ($ref === '' || strlen($ref) > 180) mg_fail('Campaign is required.', 422);

    $numericId = ctype_digit($ref) ? (int)$ref : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title, campaign_type, status, merchant_user_id FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantId, $numericId, $numericId, $ref, $ref]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign not found.', 404);
    return $campaign;
}

function mg_campaign_embed_settings_stats(PDO $pdo, int $campaignId): array
{
    try {
        $stats = mg_campaign_embed_settings_empty_stats();
        $stmt = $pdo->prepare("SELECT event_type, COUNT(*) total FROM campaign_embed_events WHERE campaign_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY event_type");
        $stmt->execute([$campaignId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string)$row['event_type'];
            $count = (int)$row['total'];
            if (isset($stats[$type])) $stats[$type] = $count;
        }
        $lastStmt = $pdo->prepare('SELECT created_at, origin_host FROM campaign_embed_events WHERE campaign_id = ? ORDER BY id DESC LIMIT 1');
        $lastStmt->execute([$campaignId]);
        $last = $lastStmt->fetch(PDO::FETCH_ASSOC);
        if ($last) {
            $stats['last_event_at'] = $last['created_at'] ?? null;
            $stats['last_origin_host'] = $last['origin_host'] ?? null;
        }
        return $stats;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_stats.unavailable', 'Campaign embed stats unavailable.', ['exception_class' => $error::class]);
        return mg_campaign_embed_settings_empty_stats();
    }
}

$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
$method = mg_campaign_embed_settings_method();

if (!in_array($method, ['GET', 'POST'], true)) mg_fail('Method not allowed.', 405);

$input = $method === 'POST' ? mg_input() : $_GET;
$campaign = mg_campaign_embed_settings_find_campaign($pdo, $merchantId, $input['campaign_id'] ?? $input['campaign'] ?? '');
$campaignId = (int)$campaign['id'];

if ($method === 'POST') {
    $settings = [
        'embed_enabled' => mg_campaign_embed_settings_bool($input['embed_enabled'] ?? 0),
        'default_layout' => mg_campaign_embed_settings_layout($input['default_layout'] ?? 'inline'),
        'custom_button_text' => mg_campaign_embed_settings_text($input['custom_button_text'] ?? null, 120),
        'custom_success_message' => mg_campaign_embed_settings_text($input['custom_success_message'] ?? null, 255),
        'allowed_domains' => mg_campaign_embed_settings_normalize_domains($input['allowed_domains'] ?? []),
    ];

    try {
        $stmt = $pdo->prepare('INSERT INTO campaign_embed_settings (campaign_id, merchant_user_id, embed_enabled, default_layout, custom_button_text, custom_success_message, allowed_domains_json, settings_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE merchant_user_id=VALUES(merchant_user_id), embed_enabled=VALUES(embed_enabled), default_layout=VALUES(default_layout), custom_button_text=VALUES(custom_button_text), custom_success_message=VALUES(custom_success_message), allowed_domains_json=VALUES(allowed_domains_json), settings_json=VALUES(settings_json), updated_at=NOW()');
        $stmt->execute([
            $campaignId,
            $merchantId,
            $settings['embed_enabled'],
            $settings['default_layout'],
            $settings['custom_button_text'],
            $settings['custom_success_message'],
            json_encode($settings['allowed_domains'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode(['version' => 2, 'updated_by' => $merchantId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $error) {
        mg_security_log('error', 'merchant.campaign_embed_settings.save_failed', 'Unable to save campaign embed settings.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
        mg_fail('Unable to save embed settings. Confirm the Campaign Embed Settings v2 SQL migration has been imported.', 500);
    }
}

try {
    $stmt = $pdo->prepare('SELECT * FROM campaign_embed_settings WHERE campaign_id = ? LIMIT 1');
    $stmt->execute([$campaignId]);
    $settingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $settings = mg_campaign_embed_settings_row($settingsRow);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_embed_settings.unavailable', 'Campaign embed settings unavailable.', ['exception_class' => $error::class], $merchantId);
    $settings = mg_campaign_embed_settings_row([]);
}

mg_ok([
    'campaign' => [
        'id' => (string)$campaign['public_id'],
        'slug' => $campaign['public_slug'] ?? null,
        'title' => (string)$campaign['title'],
        'campaign_type' => (string)$campaign['campaign_type'],
        'status' => (string)$campaign['status'],
    ],
    'settings' => $settings,
    'stats' => mg_campaign_embed_settings_stats($pdo, $campaignId),
], $method === 'POST' ? 'Campaign embed settings saved.' : 'Campaign embed settings loaded.');
