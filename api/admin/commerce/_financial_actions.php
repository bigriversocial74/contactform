<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';
require_once dirname(__DIR__, 2) . '/tips/_tips.php';
require_once dirname(__DIR__, 2) . '/tips/_notifications.php';

function mg_admin_commerce_reverse_tip(PDO $pdo, int $actorId, string $reference, string $reason, mixed $key): array
{
    if (!mg_admin_commerce_subject_exists($pdo, 'tip', $reference, true)) {
        throw new MgAdminCommerceException('Tip not found.', 404);
    }
    $idempotencyKey = mg_admin_commerce_text($key, 190);
    if ($idempotencyKey === '') {
        $idempotencyKey = 'admin-commerce:' . $reference . ':' . hash('sha256', $reason);
    }
    if (preg_match('/^[A-Za-z0-9._:-]{8,190}$/', $idempotencyKey) !== 1) {
        throw new MgAdminCommerceException('Invalid tip reversal idempotency key.', 422);
    }
    $result = mg_tip_reverse($pdo, $actorId, $reference, $idempotencyKey, $reason);
    mg_tip_notify_reversal($pdo, $result['tip'], $reason);
    return [
        'tip_id'=>$reference,
        'reversal_id'=>(string)($result['reversal']['public_id'] ?? ''),
        'duplicate'=>(bool)($result['duplicate'] ?? false),
    ];
}
