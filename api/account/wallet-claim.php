<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_wc_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_wc_event(PDO $pdo, array $item, string $eventType): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_wc_uuid(),
        (int) $item['merchant_user_id'],
        $item['campaign_id'] === null ? null : (int) $item['campaign_id'],
        (int) $item['id'],
        $item['contact_id'] === null ? null : (int) $item['contact_id'],
        $eventType,
        json_encode(['wallet_item_id' => (string) $item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
$pdo = mg_db();
$walletId = strtolower(trim((string) ($input['wallet_item_id'] ?? '')));
$userId = (int) $user['id'];
$userEmail = strtolower(trim((string) ($user['email'] ?? '')));

if ($walletId === '' || strlen($walletId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $walletId)) {
    mg_fail('Invalid wallet item.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT wi.*, cc.email contact_email FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id = wi.contact_id WHERE wi.public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$walletId]);
    $item = $stmt->fetch();
    if (!$item) {
        $pdo->rollBack();
        mg_fail('Wallet item not found.', 404);
    }

    $contactEmail = strtolower(trim((string) ($item['contact_email'] ?? '')));
    $sourceId = strtolower(trim((string) ($item['source_id'] ?? '')));
    $owned = ((int) ($item['user_id'] ?? 0)) === $userId;
    $emailMatch = $userEmail !== '' && ($contactEmail === $userEmail || $sourceId === $userEmail);
    if (!$owned && !$emailMatch) {
        $pdo->rollBack();
        mg_fail('Wallet item is not available for your account.', 403);
    }

    if (!empty($item['expires_at']) && strtotime((string) $item['expires_at']) < time()) {
        $pdo->prepare('UPDATE wallet_items SET status = \'expired\', updated_at = NOW() WHERE id = ?')->execute([(int) $item['id']]);
        mg_wc_event($pdo, $item, 'wallet_item.expired');
        $pdo->commit();
        mg_fail('Wallet item has expired.', 410);
    }

    if (in_array((string) $item['status'], ['redeemed','expired','cancelled'], true)) {
        $pdo->rollBack();
        mg_fail('Wallet item is not claimable.', 409);
    }

    if ($item['status'] !== 'claimed') {
        $pdo->prepare('UPDATE wallet_items SET user_id = ?, status = \'claimed\', viewed_at = COALESCE(viewed_at,NOW()), claimed_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$userId, (int) $item['id']]);
        mg_wc_event($pdo, $item, 'wallet_item.claimed');
    }

    $pdo->commit();
    mg_ok(['wallet_item_id' => $walletId, 'wallet_status' => 'claimed'], 'Wallet item claimed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to claim wallet item.', 500);
}
