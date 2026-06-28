<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';
require_once dirname(__DIR__) . '/store/_canvas_rewards.php';

function mg_canvas_trigger_zone_table(): string
{
    return 'mg_store_trigger_zones';
}

function mg_canvas_trigger_zone_schema_ready(PDO $pdo): bool
{
    return mg_store_canvas_table_exists($pdo, mg_canvas_trigger_zone_table());
}

function mg_canvas_trigger_zone_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, [mg_canvas_trigger_zone_table()], 'Store Canvas trigger zones');
}

function mg_canvas_trigger_zone_column_exists(PDO $pdo, string $column): bool
{
    static $cache = [];
    $key = $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute([mg_canvas_trigger_zone_table(), $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function mg_canvas_trigger_zone_uuid(): string
{
    return mg_public_uuid();
}

function mg_canvas_trigger_zone_clamp_float(mixed $value, float $min, float $max, float $default): float
{
    if ($value === null || $value === '') return $default;
    $number = (float)$value;
    if (!is_finite($number)) return $default;
    return max($min, min($max, $number));
}

function mg_canvas_trigger_zone_clamp_priority(mixed $value): int
{
    return max(1, min(5, (int)$value));
}

function mg_canvas_trigger_zone_name(mixed $value): string
{
    $name = trim((string)$value);
    if ($name === '') $name = 'IN/OUT Box Trigger';
    if (mb_strlen($name) > 160) $name = mb_substr($name, 0, 160);
    return $name;
}

function mg_canvas_trigger_zone_key(mixed $value): string
{
    $key = trim((string)$value);
    if ($key === '') $key = 'store_canvas_zone';
    $key = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $key) ?: 'store_canvas_zone';
    return mb_substr($key, 0, 120);
}

function mg_canvas_trigger_zone_enum(mixed $value, array $allowed, string $default): string
{
    $value = strtolower(trim((string)$value));
    return in_array($value, $allowed, true) ? $value : $default;
}

function mg_canvas_trigger_zone_nullable_text(mixed $value, int $maxLength): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;
    if (mb_strlen($text) > $maxLength) $text = mb_substr($text, 0, $maxLength);
    return $text;
}

function mg_canvas_trigger_zone_bool(mixed $value, bool $default = true): int
{
    if ($value === null || $value === '') return $default ? 1 : 0;
    if (is_bool($value)) return $value ? 1 : 0;
    $string = strtolower(trim((string)$value));
    if (in_array($string, ['1','true','yes','on'], true)) return 1;
    if (in_array($string, ['0','false','no','off'], true)) return 0;
    return ((int)$value) ? 1 : 0;
}

function mg_canvas_trigger_zone_campaign_id(PDO $pdo, int $merchantUserId, mixed $campaignPublicId): ?string
{
    $campaignPublicId = trim((string)$campaignPublicId);
    if ($campaignPublicId === '') return null;
    $campaignPublicId = mg_store_safe_public_id($campaignPublicId, 'Campaign');
    if (!mg_store_canvas_table_exists($pdo, 'campaigns')) throw new RuntimeException('Campaign schema is not available.');
    $stmt = $pdo->prepare("SELECT public_id FROM campaigns WHERE public_id=? AND merchant_user_id=? AND status='active' AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1");
    $stmt->execute([$campaignPublicId, $merchantUserId]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('Selected trigger campaign is not active or available.');
    return $campaignPublicId;
}

function mg_canvas_trigger_zone_public(array $row): array
{
    return [
        'id'=>(string)($row['public_id'] ?? $row['id'] ?? ''),
        'name'=>(string)($row['name'] ?? 'IN/OUT Box Trigger'),
        'trigger_key'=>(string)($row['trigger_key'] ?? 'store_canvas_zone'),
        'campaign_id'=>($row['campaign_public_id'] ?? $row['campaign_id'] ?? null) !== null ? (string)($row['campaign_public_id'] ?? $row['campaign_id']) : '',
        'campaign_title'=>($row['campaign_title'] ?? null) !== null ? (string)$row['campaign_title'] : '',
        'priority'=>max(1, min(5, (int)($row['priority'] ?? 3))),
        'x'=>max(0.0, min(100.0, (float)($row['x_percent'] ?? $row['x'] ?? 0))),
        'y'=>max(0.0, min(100.0, (float)($row['y_percent'] ?? $row['y'] ?? 0))),
        'width'=>max(1.0, min(100.0, (float)($row['width_percent'] ?? $row['width'] ?? 28))),
        'height'=>max(1.0, min(100.0, (float)($row['height_percent'] ?? $row['height'] ?? 18))),
        'status'=>(string)($row['status'] ?? 'active'),
        'automation_action'=>(string)($row['automation_action'] ?? 'message_and_reward'),
        'cooldown_policy'=>(string)($row['cooldown_policy'] ?? 'fifteen_minutes'),
        'cooldown_seconds'=>max(60, (int)($row['cooldown_seconds'] ?? 900)),
        'auto_message_text'=>(string)($row['auto_message_text'] ?? ''),
        'fallback_action'=>(string)($row['fallback_action'] ?? 'notify_only'),
        'crm_segment_name'=>(string)($row['crm_segment_name'] ?? ''),
        'notify_merchant'=>isset($row['notify_merchant']) ? (int)$row['notify_merchant'] : 1,
        'last_triggered_at'=>$row['last_triggered_at'] ?? null,
        'updated_at'=>(string)($row['updated_at'] ?? ''),
    ];
}

function mg_canvas_trigger_zone_list(PDO $pdo, int $merchantUserId): array
{
    mg_canvas_trigger_zone_require_schema($pdo);
    $campaignJoin = mg_store_canvas_table_exists($pdo, 'campaigns') ? 'LEFT JOIN campaigns c ON c.public_id=z.campaign_public_id AND c.merchant_user_id=z.merchant_user_id' : '';
    $campaignTitle = $campaignJoin !== '' ? 'c.title campaign_title' : 'NULL campaign_title';
    $stmt = $pdo->prepare("SELECT z.*,{$campaignTitle} FROM mg_store_trigger_zones z {$campaignJoin} WHERE z.merchant_user_id=? AND z.status<>'archived' ORDER BY z.priority DESC,z.updated_at DESC,z.id DESC LIMIT 50");
    $stmt->execute([$merchantUserId]);
    return array_map('mg_canvas_trigger_zone_public', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_canvas_trigger_zone_dynamic_fields(PDO $pdo, array $input): array
{
    $fields = [];
    if (mg_canvas_trigger_zone_column_exists($pdo, 'automation_action')) {
        $fields['automation_action'] = mg_canvas_trigger_zone_enum($input['automation_action'] ?? 'message_and_reward', ['message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only'], 'message_and_reward');
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'cooldown_policy')) {
        $fields['cooldown_policy'] = mg_canvas_trigger_zone_enum($input['cooldown_policy'] ?? 'fifteen_minutes', ['five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day'], 'fifteen_minutes');
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'cooldown_seconds')) {
        $fields['cooldown_seconds'] = max(60, min(86400, (int)($input['cooldown_seconds'] ?? 900)));
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'auto_message_text')) {
        $fields['auto_message_text'] = mg_canvas_trigger_zone_nullable_text($input['auto_message_text'] ?? null, 1000);
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'fallback_action')) {
        $fields['fallback_action'] = mg_canvas_trigger_zone_enum($input['fallback_action'] ?? 'notify_only', ['notify_only','analytics_only','skip'], 'notify_only');
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'crm_segment_name')) {
        $fields['crm_segment_name'] = mg_canvas_trigger_zone_nullable_text($input['crm_segment_name'] ?? null, 160);
    }
    if (mg_canvas_trigger_zone_column_exists($pdo, 'notify_merchant')) {
        $fields['notify_merchant'] = mg_canvas_trigger_zone_bool($input['notify_merchant'] ?? 1, true);
    }
    return $fields;
}

function mg_canvas_trigger_zone_save(PDO $pdo, int $merchantUserId, array $input): array
{
    mg_canvas_trigger_zone_require_schema($pdo);
    $publicId = trim((string)($input['id'] ?? ''));
    if ($publicId !== '') $publicId = mg_store_safe_public_id($publicId, 'Trigger zone');
    $name = mg_canvas_trigger_zone_name($input['name'] ?? 'IN/OUT Box Trigger');
    $triggerKey = mg_canvas_trigger_zone_key($input['trigger_key'] ?? 'store_canvas_zone');
    $campaignPublicId = mg_canvas_trigger_zone_campaign_id($pdo, $merchantUserId, $input['campaign_id'] ?? '');
    $priority = mg_canvas_trigger_zone_clamp_priority($input['priority'] ?? 3);
    $x = mg_canvas_trigger_zone_clamp_float($input['x'] ?? null, 0, 100, 8);
    $y = mg_canvas_trigger_zone_clamp_float($input['y'] ?? null, 0, 100, 8);
    $width = mg_canvas_trigger_zone_clamp_float($input['width'] ?? null, 1, 100, 28);
    $height = mg_canvas_trigger_zone_clamp_float($input['height'] ?? null, 1, 100, 18);
    if ($x + $width > 100) $x = max(0, 100 - $width);
    if ($y + $height > 100) $y = max(0, 100 - $height);
    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    if (!in_array($status, ['active','paused'], true)) $status = 'active';

    $values = [
        'name'=>$name,
        'trigger_key'=>$triggerKey,
        'campaign_public_id'=>$campaignPublicId,
        'priority'=>$priority,
        'x_percent'=>$x,
        'y_percent'=>$y,
        'width_percent'=>$width,
        'height_percent'=>$height,
        'status'=>$status,
    ];
    $values = array_merge($values, mg_canvas_trigger_zone_dynamic_fields($pdo, $input));

    if ($publicId === '') {
        $publicId = mg_canvas_trigger_zone_uuid();
        $columns = array_merge(['public_id','merchant_user_id'], array_keys($values), ['metadata_json','created_at','updated_at']);
        $placeholders = rtrim(str_repeat('?,', count($columns)), ',');
        $params = array_merge([$publicId, $merchantUserId], array_values($values), [null, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $stmt = $pdo->prepare('INSERT INTO mg_store_trigger_zones (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
        $stmt->execute($params);
    } else {
        $sets = [];
        $params = [];
        foreach ($values as $column => $value) {
            $sets[] = $column . '=?';
            $params[] = $value;
        }
        $sets[] = 'updated_at=NOW()';
        $params[] = $publicId;
        $params[] = $merchantUserId;
        $stmt = $pdo->prepare('UPDATE mg_store_trigger_zones SET ' . implode(',', $sets) . " WHERE public_id=? AND merchant_user_id=? AND status<>'archived'");
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Trigger zone is not available.');
    }

    $zones = mg_canvas_trigger_zone_list($pdo, $merchantUserId);
    foreach ($zones as $zone) {
        if ((string)$zone['id'] === $publicId) return $zone;
    }
    throw new RuntimeException('Unable to save trigger zone.');
}

function mg_canvas_trigger_zone_archive(PDO $pdo, int $merchantUserId, string $publicId): void
{
    mg_canvas_trigger_zone_require_schema($pdo);
    $publicId = mg_store_safe_public_id($publicId, 'Trigger zone');
    $stmt = $pdo->prepare("UPDATE mg_store_trigger_zones SET status='archived',updated_at=NOW() WHERE public_id=? AND merchant_user_id=?");
    $stmt->execute([$publicId, $merchantUserId]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Trigger zone is not available.');
}

function mg_canvas_trigger_zone_load(PDO $pdo, int $merchantUserId, string $publicId): ?array
{
    if (!mg_canvas_trigger_zone_schema_ready($pdo)) return null;
    $publicId = mg_store_safe_public_id($publicId, 'Trigger zone');
    $campaignJoin = mg_store_canvas_table_exists($pdo, 'campaigns') ? 'LEFT JOIN campaigns c ON c.public_id=z.campaign_public_id AND c.merchant_user_id=z.merchant_user_id' : '';
    $campaignTitle = $campaignJoin !== '' ? 'c.title campaign_title' : 'NULL campaign_title';
    $stmt = $pdo->prepare("SELECT z.*,{$campaignTitle} FROM mg_store_trigger_zones z {$campaignJoin} WHERE z.public_id=? AND z.merchant_user_id=? AND z.status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_canvas_trigger_zone_touch(PDO $pdo, int $merchantUserId, string $publicId): void
{
    if (!mg_canvas_trigger_zone_schema_ready($pdo)) return;
    try {
        $publicId = mg_store_safe_public_id($publicId, 'Trigger zone');
        $stmt = $pdo->prepare('UPDATE mg_store_trigger_zones SET last_triggered_at=NOW(),updated_at=NOW() WHERE public_id=? AND merchant_user_id=?');
        $stmt->execute([$publicId, $merchantUserId]);
    } catch (Throwable) {}
}
