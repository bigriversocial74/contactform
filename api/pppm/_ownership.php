<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';
require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';

function mg_pppm_transfer_owner_canonical(PDO $pdo, string $pppmPublicId, int $newOwnerUserId, string $sourceType, string $sourceReference, ?int $actorUserId = null, array $metadata = []): array
{
    if ($newOwnerUserId < 1 || trim($sourceType) === '' || trim($sourceReference) === '') {
        throw new InvalidArgumentException('Valid PPPM owner transfer context is required.');
    }

    $item = mg_pppm_locked_by_public_id($pdo, $pppmPublicId);
    $oldOwner = (int)($item['owner_user_id'] ?? 0);
    $entitlementKey = 'owner-sync:' . $pppmPublicId . ':' . $newOwnerUserId . ':' . $sourceReference;

    $existing = $pdo->prepare('SELECT * FROM entitlement_transfers WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$entitlementKey]);
    if ($existingRow = $existing->fetch()) {
        if ($oldOwner !== $newOwnerUserId || (int)($item['recipient_user_id'] ?? 0) !== $newOwnerUserId) {
            $pdo->prepare('UPDATE pppm_items SET owner_user_id=?,recipient_user_id=?,version_no=version_no+1,updated_at=NOW() WHERE id=?')
                ->execute([$newOwnerUserId, $newOwnerUserId, (int)$item['id']]);
            $updated = mg_pppm_refresh($pdo, (int)$item['id']);
            mg_pppm_record_event($pdo, $updated, 'owner_transferred', null, (string)$updated['status'], $actorUserId, null, array_merge($metadata, [
                'from_user_id' => $oldOwner ?: null,
                'to_user_id' => $newOwnerUserId,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'duplicate_entitlement_transfer' => true,
            ]));
        }
        return ['transfer_id'=>(string)$existingRow['public_id'],'old_owner_user_id'=>$oldOwner ?: null,'new_owner_user_id'=>$newOwnerUserId,'duplicate'=>true];
    }

    $entitlements = mg_entitlements_sync_pppm_owner($pdo, $pppmPublicId, $newOwnerUserId, $sourceType, $sourceReference, $actorUserId);

    if ($oldOwner !== $newOwnerUserId || (int)($item['recipient_user_id'] ?? 0) !== $newOwnerUserId) {
        $pdo->prepare('UPDATE pppm_items SET owner_user_id=?,recipient_user_id=?,version_no=version_no+1,updated_at=NOW() WHERE id=?')
            ->execute([$newOwnerUserId, $newOwnerUserId, (int)$item['id']]);
        $updated = mg_pppm_refresh($pdo, (int)$item['id']);
        mg_pppm_record_event($pdo, $updated, 'owner_transferred', null, (string)$updated['status'], $actorUserId, null, array_merge($metadata, [
            'from_user_id' => $oldOwner ?: null,
            'to_user_id' => $newOwnerUserId,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'entitlement_transfer_id' => $entitlements['transfer_id'] ?? null,
        ]));
        mg_event('pppm.owner_transferred', ['pppm_item_id'=>$pppmPublicId,'from_user_id'=>$oldOwner ?: null,'to_user_id'=>$newOwnerUserId,'source_type'=>$sourceType,'source_reference'=>$sourceReference], $actorUserId);
    }

    return ['transfer_id'=>$entitlements['transfer_id'] ?? null,'old_owner_user_id'=>$oldOwner ?: null,'new_owner_user_id'=>$newOwnerUserId,'entitlements'=>$entitlements,'duplicate'=>(bool)($entitlements['duplicate'] ?? false)];
}
