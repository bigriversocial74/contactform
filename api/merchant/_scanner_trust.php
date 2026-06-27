<?php
declare(strict_types=1);

function mg_scanner_trust_mask_email(?string $email): string
{
    $email = trim((string)$email);
    if ($email === '' || !str_contains($email, '@')) return '';
    [$local, $domain] = explode('@', $email, 2);
    return mb_substr($local, 0, 1) . '••••@' . $domain;
}

function mg_scanner_trust_user_summary(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) return null;
    try {
        $stmt = $pdo->prepare('SELECT id,email,full_name,display_name FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $name = trim((string)($row['display_name'] ?: $row['full_name'] ?: ''));
        return ['id' => (int)$row['id'], 'name' => $name !== '' ? $name : 'Microgifter user', 'masked_email' => mg_scanner_trust_mask_email((string)($row['email'] ?? ''))];
    } catch (Throwable) {
        return null;
    }
}

function mg_scanner_trust_confirmation(string $giftId, string $title, int $amountCents, string $currency, array $location, array $claimCode, ?array $customer): array
{
    return [
        'gift_id' => $giftId,
        'title' => $title,
        'value_cents' => $amountCents,
        'currency' => $currency,
        'customer' => $customer,
        'location' => ['id' => (string)($location['public_id'] ?? ''), 'name' => (string)($location['name'] ?? 'Merchant location')],
        'claim_code_last4' => (string)($claimCode['code_last4'] ?? ''),
        'status' => 'ready_to_redeem',
        'copy' => 'Ready to redeem. Confirm only after the customer is present and the offer has been accepted.',
    ];
}

function mg_scanner_trust_severity(int $score): string
{
    if ($score >= 90) return 'critical';
    if ($score >= 65) return 'high';
    if ($score >= 35) return 'medium';
    return 'low';
}

function mg_scanner_trust_event(PDO $pdo, string $eventType, int $score, ?string $giftId, int $merchantUserId, ?array $location, ?array $voucherToken, ?string $receiptId, string $rawScan, array $details = []): void
{
    try {
        $pepper = function_exists('mg_claim_code_pepper') ? mg_claim_code_pepper() : 'scanner-trust';
        $stmt = $pdo->prepare("INSERT INTO scanner_risk_events (public_id,event_type,severity,risk_score,gift_public_id,voucher_token_public_id,receipt_public_id,merchant_user_id,scanner_user_id,scanner_location_id,location_public_id,scan_hash,ip_hash,user_agent_hash,details_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            mg_public_uuid(), $eventType, mg_scanner_trust_severity($score), max(0, min(100, $score)), $giftId, $voucherToken['public_id'] ?? null, $receiptId, $merchantUserId ?: null, $merchantUserId ?: null, isset($location['id']) ? (int)$location['id'] : null, isset($location['public_id']) ? (string)$location['public_id'] : null,
            trim($rawScan) !== '' ? hash_hmac('sha256', trim($rawScan), $pepper) : null,
            hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper),
            hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper),
            json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'scanner.trust_event_failed', 'Unable to write scanner trust event.', ['event_type' => $eventType, 'exception_class' => $error::class], $merchantUserId ?: null);
    }
}

function mg_scanner_trust_receipt(PDO $pdo, string $type, string $giftId, ?string $redemptionId, ?string $claimId, ?int $customerUserId, ?int $senderUserId, int $merchantUserId, array $location, array $claimCode, int $amountCents, string $currency, array $payload): array
{
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare("INSERT INTO scanner_redemption_receipts (public_id,receipt_type,gift_public_id,redemption_public_id,claim_public_id,customer_user_id,sender_user_id,merchant_user_id,scanner_user_id,scanner_location_id,location_public_id,location_name,claim_code_last4,amount_cents,currency,status,receipt_payload_json,redeemed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'completed',?,NOW(),NOW(),NOW())");
    $stmt->execute([$publicId, $type, $giftId, $redemptionId, $claimId, $customerUserId, $senderUserId, $merchantUserId, $merchantUserId, isset($location['id']) ? (int)$location['id'] : null, isset($location['public_id']) ? (string)$location['public_id'] : null, (string)($location['name'] ?? 'Merchant location'), (string)($claimCode['code_last4'] ?? ''), $amountCents, $currency ?: 'USD', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return ['id' => $publicId, 'url' => '/claim-receipt-view.php?id=' . rawurlencode($publicId)];
}

function mg_scanner_claim_notify(PDO $pdo, int $userId, int $actorUserId, string $type, string $title, string $body, string $actionUrl, ?int $giftDbId = null): void
{
    if ($userId < 1) return;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,gift_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([mg_public_uuid(), $userId, $type, $title, $body, $actionUrl, $giftDbId]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'scanner_claim.notification_failed', 'Scanner claim notification failed.', ['recipient_user_id' => $userId, 'type' => $type, 'exception_class' => $error::class], $actorUserId);
    }
}

function mg_scanner_claim_notify_many(PDO $pdo, array $userIds, int $actorUserId, string $type, string $title, string $body, string $actionUrl, ?int $giftDbId = null): void
{
    $sent = [];
    foreach ($userIds as $userId) {
        $id = (int)$userId;
        if ($id < 1 || isset($sent[$id])) continue;
        $sent[$id] = true;
        mg_scanner_claim_notify($pdo, $id, $actorUserId, $type, $title, $body, $actionUrl, $giftDbId);
    }
}
