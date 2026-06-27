<?php
declare(strict_types=1);

function mg_scanner_ops_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') return $default;
    return in_array(strtolower((string)$value), ['1','true','yes','on'], true);
}

function mg_scanner_ops_hash(string $value): string
{
    $pepper = function_exists('mg_claim_code_pepper') ? mg_claim_code_pepper() : 'scanner-ops';
    return hash_hmac('sha256', $value, $pepper);
}

function mg_scanner_ops_device_id(array $input): string
{
    $candidate = strtolower(trim((string)($input['scanner_device_id'] ?? '')));
    if (preg_match('/^[0-9a-f-]{36}$/', $candidate) === 1) return $candidate;
    return mg_public_uuid();
}

function mg_scanner_ops_touch_device(PDO $pdo, int $merchantUserId, int $workspaceId, array $location, array $input): array
{
    $publicId = mg_scanner_ops_device_id($input);
    $label = trim((string)($input['scanner_device_label'] ?? 'Merchant scanner'));
    $label = mb_substr($label !== '' ? $label : 'Merchant scanner', 0, 120);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $deviceKeyHash = mg_scanner_ops_hash($publicId . '|' . $merchantUserId);
    $ipHash = mg_scanner_ops_hash($ip);
    $uaHash = mg_scanner_ops_hash($ua);

    $stmt = $pdo->prepare('SELECT * FROM scanner_device_sessions WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, $merchantUserId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($device && (string)$device['status'] === 'disabled') {
        mg_scanner_ops_incident($pdo, 'disabled_device_scan', 'high', 'A disabled scanner device attempted a scan.', $merchantUserId, $merchantUserId, (int)($location['id'] ?? 0), (int)$device['id'], null, null, null, ['device_id' => $publicId]);
        mg_fail('This scanner device is disabled. Use an approved scanner device.', 403);
    }
    if ($device) {
        $pdo->prepare('UPDATE scanner_device_sessions SET workspace_id=?,location_id=?,location_public_id=?,device_label=?,device_key_hash=?,last_scan_at=NOW(),last_ip_hash=?,last_user_agent_hash=?,updated_at=NOW() WHERE id=?')->execute([$workspaceId, (int)$location['id'], (string)$location['public_id'], $label, $deviceKeyHash, $ipHash, $uaHash, (int)$device['id']]);
        $device['workspace_id'] = $workspaceId;
        $device['location_id'] = (int)$location['id'];
        $device['location_public_id'] = (string)$location['public_id'];
        $device['device_label'] = $label;
        $device['last_scan_at'] = date('Y-m-d H:i:s');
        return $device;
    }
    $pdo->prepare("INSERT INTO scanner_device_sessions (public_id,merchant_user_id,workspace_id,location_id,location_public_id,device_label,device_key_hash,last_scan_at,last_ip_hash,last_user_agent_hash,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")->execute([$publicId, $merchantUserId, $workspaceId, (int)$location['id'], (string)$location['public_id'], $label, $deviceKeyHash, date('Y-m-d H:i:s'), $ipHash, $uaHash, json_encode(['first_seen_source' => 'merchant_scanner'], JSON_UNESCAPED_SLASHES)]);
    $id = (int)$pdo->lastInsertId();
    return ['id' => $id, 'public_id' => $publicId, 'merchant_user_id' => $merchantUserId, 'workspace_id' => $workspaceId, 'location_id' => (int)$location['id'], 'location_public_id' => (string)$location['public_id'], 'device_label' => $label, 'trusted_device' => 0, 'status' => 'active'];
}

function mg_scanner_ops_settings(PDO $pdo, int $merchantUserId, int $workspaceId, int $locationId): array
{
    $defaults = ['require_confirmation' => 1, 'lock_scanner_to_location' => 0, 'allow_manual_entry' => 1, 'max_failed_scans_per_hour' => 8, 'require_manager_review_high_risk' => 1, 'high_risk_threshold' => 65];
    try {
        $stmt = $pdo->prepare('SELECT * FROM merchant_scanner_settings WHERE merchant_user_id=? AND (workspace_id IS NULL OR workspace_id=?) AND (location_id IS NULL OR location_id=?) ORDER BY location_id IS NULL ASC, id DESC LIMIT 1');
        $stmt->execute([$merchantUserId, $workspaceId, $locationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $defaults;
        foreach ($defaults as $key => $value) $defaults[$key] = isset($row[$key]) ? (int)$row[$key] : $value;
        $defaults['public_id'] = (string)$row['public_id'];
    } catch (Throwable) {}
    return $defaults;
}

function mg_scanner_ops_apply_settings(PDO $pdo, array $settings, array $input, int $merchantUserId, array $location, array $device, string $rawScan, bool &$requireConfirm): void
{
    if (!empty($settings['require_confirmation'])) $requireConfirm = true;
    $source = strtolower(trim((string)($input['scan_source'] ?? 'camera')));
    if (empty($settings['allow_manual_entry']) && $source === 'manual') {
        mg_scanner_ops_incident($pdo, 'manual_entry_blocked', 'medium', 'Manual scanner entry was blocked by merchant settings.', $merchantUserId, $merchantUserId, (int)$location['id'], (int)$device['id'], null, null, null, ['source' => $source]);
        mg_fail('Manual voucher entry is disabled for this scanner.', 403);
    }
    $max = max(1, (int)($settings['max_failed_scans_per_hour'] ?? 8));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_scanner_incidents WHERE scanner_device_session_id=? AND status IN ('open','reviewing','escalated') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([(int)$device['id']]);
    if ((int)$stmt->fetchColumn() >= $max) {
        mg_scanner_ops_incident($pdo, 'scan_rate_limit', 'high', 'Scanner device exceeded the incident threshold.', $merchantUserId, $merchantUserId, (int)$location['id'], (int)$device['id'], null, null, null, ['max_failed_scans_per_hour' => $max]);
        mg_fail('Scanner is temporarily paused because it has too many scan issues. Ask a manager or admin to review.', 429);
    }
}

function mg_scanner_ops_incident(PDO $pdo, string $type, string $severity, string $summary, ?int $merchantUserId, ?int $scannerUserId, ?int $locationId, ?int $deviceId, ?string $giftId, ?string $receiptId, ?string $voucherTokenId, array $details = []): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_scanner_incidents (public_id,incident_type,severity,status,merchant_user_id,scanner_user_id,scanner_location_id,scanner_device_session_id,gift_public_id,receipt_public_id,voucher_token_public_id,summary,details_json,created_at,updated_at) VALUES (?,?,?,'open',?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([mg_public_uuid(), $type, $severity, $merchantUserId, $scannerUserId, $locationId, $deviceId, $giftId, $receiptId, $voucherTokenId, $summary, json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'scanner.incident_failed', 'Unable to create scanner incident.', ['type' => $type, 'exception_class' => $error::class], $scannerUserId ?: null);
    }
}
