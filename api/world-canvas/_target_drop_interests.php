<?php
/** Campaign Drop interest helpers. */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_campaigns.php';

function mg_world_target_drop_interests_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_target_drop_interests');
}

function mg_world_target_drop_interest_public_id(): string
{
    try { return 'tdi_' . bin2hex(random_bytes(16)); }
    catch (Throwable) { return 'tdi_' . str_replace('.', '', uniqid('', true)); }
}

function mg_world_target_drop_public_row(PDO $pdo, string $publicId): ?array
{
    if (!mg_world_target_drops_ready($pdo)) return null;
    $rows = mg_world_canvas_rows($pdo, "SELECT * FROM merchant_target_drops WHERE public_id=? AND status IN ('scheduled','launching','active') AND visibility IN ('public','audience') LIMIT 1", [$publicId]);
    return $rows[0] ?? null;
}

function mg_world_target_drop_clean_email(mixed $value): ?string
{
    $email = strtolower(trim((string)($value ?? '')));
    if ($email === '') return null;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 190) : null;
}

function mg_world_target_drop_clean_phone(mixed $value): ?string
{
    $phone = preg_replace('/[^0-9+().\-\s]/', '', trim((string)($value ?? '')));
    $phone = trim((string)$phone);
    return $phone === '' ? null : substr($phone, 0, 64);
}

function mg_world_target_drop_interest_status(mixed $value): string
{
    $status = trim((string)($value ?? 'interested'));
    return in_array($status, ['interested','joined','claimed','dismissed'], true) ? $status : 'interested';
}

function mg_world_target_drop_interest_stats(PDO $pdo, string $dropPublicId, ?int $viewerUserId = null): array
{
    if (!mg_world_target_drop_interests_ready($pdo) || !mg_world_target_drops_ready($pdo)) return ['interest_count'=>0,'joined_count'=>0,'claimed_count'=>0,'viewer_interest_status'=>null];
    $rows = mg_world_canvas_rows($pdo, "SELECT SUM(i.status='interested') interest_count, SUM(i.status='joined') joined_count, SUM(i.status='claimed') claimed_count FROM merchant_target_drop_interests i JOIN merchant_target_drops d ON d.id=i.target_drop_id WHERE d.public_id=?", [$dropPublicId]);
    $viewerStatus = null;
    if ($viewerUserId !== null && $viewerUserId > 0) {
        $viewer = mg_world_canvas_rows($pdo, "SELECT i.status FROM merchant_target_drop_interests i JOIN merchant_target_drops d ON d.id=i.target_drop_id WHERE d.public_id=? AND i.user_id=? LIMIT 1", [$dropPublicId, $viewerUserId]);
        $viewerStatus = $viewer[0]['status'] ?? null;
    }
    return [
        'interest_count' => (int)($rows[0]['interest_count'] ?? 0),
        'joined_count' => (int)($rows[0]['joined_count'] ?? 0),
        'claimed_count' => (int)($rows[0]['claimed_count'] ?? 0),
        'viewer_interest_status' => $viewerStatus,
    ];
}

function mg_world_target_drop_enrich_interest_stats(PDO $pdo, array $drops, array $user = []): array
{
    $viewerUserId = isset($user['id']) ? (int)$user['id'] : null;
    foreach ($drops as &$drop) {
        $drop = array_merge($drop, mg_world_target_drop_interest_stats($pdo, (string)($drop['id'] ?? ''), $viewerUserId));
    }
    unset($drop);
    return $drops;
}

function mg_world_target_drop_save_interest(PDO $pdo, array $input, ?array $viewerUser = null): array
{
    if (!mg_world_target_drop_interests_ready($pdo)) throw new RuntimeException('Campaign Drop interest table is not installed.');
    $dropPublicId = trim((string)($input['target_drop_id'] ?? $input['drop_id'] ?? $input['id'] ?? ''));
    $drop = mg_world_target_drop_public_row($pdo, $dropPublicId);
    if (!$drop) throw new RuntimeException('Target Drop is not available.');
    $viewerUserId = isset($viewerUser['id']) ? (int)$viewerUser['id'] : null;
    $email = mg_world_target_drop_clean_email($input['email'] ?? $input['guest_email'] ?? null);
    $phone = mg_world_target_drop_clean_phone($input['phone'] ?? $input['guest_phone'] ?? null);
    if (($viewerUserId === null || $viewerUserId <= 0) && $email === null && $phone === null) throw new RuntimeException('Email or phone is required.');
    $status = mg_world_target_drop_interest_status($input['status'] ?? 'interested');
    $source = 'world_canvas';
    $existing = $viewerUserId ? mg_world_canvas_rows($pdo, 'SELECT id FROM merchant_target_drop_interests WHERE target_drop_id=? AND user_id=? LIMIT 1', [(int)$drop['id'], $viewerUserId]) : mg_world_canvas_rows($pdo, 'SELECT id FROM merchant_target_drop_interests WHERE target_drop_id=? AND ((guest_email IS NOT NULL AND guest_email=?) OR (guest_phone IS NOT NULL AND guest_phone=?)) LIMIT 1', [(int)$drop['id'], $email, $phone]);
    if ($existing) {
        $pdo->prepare('UPDATE merchant_target_drop_interests SET status=?, guest_email=COALESCE(?, guest_email), guest_phone=COALESCE(?, guest_phone), updated_at=NOW() WHERE id=?')->execute([$status, $email, $phone, (int)$existing[0]['id']]);
        $interestId = (int)$existing[0]['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO merchant_target_drop_interests (public_id,target_drop_id,merchant_user_id,user_id,guest_email,guest_phone,status,source,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([mg_world_target_drop_interest_public_id(), (int)$drop['id'], (int)$drop['merchant_user_id'], $viewerUserId, $email, $phone, $status, $source]);
        $interestId = (int)$pdo->lastInsertId();
    }
    $rows = mg_world_canvas_rows($pdo, 'SELECT public_id,status,source,created_at,updated_at FROM merchant_target_drop_interests WHERE id=? LIMIT 1', [$interestId]);
    return ['interest'=>$rows[0] ?? [], 'drop_id'=>(string)$drop['public_id'], 'stats'=>mg_world_target_drop_interest_stats($pdo, (string)$drop['public_id'], $viewerUserId)];
}
