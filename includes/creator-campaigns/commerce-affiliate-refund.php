<?php
declare(strict_types=1);

/** Creator affiliate refund dispute helper. */

function mg_creator_campaign_affiliate_open_refund_dispute(
    PDO $pdo,
    array $earning,
    array $payout,
    string $refundPublicId,
    int $actorUserId
): string {
    $stmt = $pdo->prepare(
        "SELECT public_id FROM creator_campaign_disputes
         WHERE active_source_key=? LIMIT 1 FOR UPDATE"
    );
    $activeKey = 'payout:' . (string) $payout['public_id'];
    $stmt->execute([$activeKey]);
    $existing = (string) ($stmt->fetchColumn() ?: '');
    if ($existing !== '') return $existing;

    $publicId = mg_creator_campaign_public_id('ccdi');
    $reason = 'A successful customer refund requires Creator payout reconciliation. Refund: ' . $refundPublicId;
    $pdo->prepare(
        'INSERT INTO creator_campaign_disputes
         (public_id,campaign_id,participant_id,creator_user_id,source_type,source_public_id,status,reason,
          opened_by_user_id,opened_at)
         VALUES (?,?,?,?,?,?,\'open\',?,?,NOW())'
    )->execute([
        $publicId,
        (int) $earning['campaign_id'],
        (int) $earning['participant_id'],
        (int) $earning['creator_user_id'],
        'payout',
        (string) $payout['public_id'],
        $reason,
        $actorUserId,
    ]);
    $disputeId = (int) $pdo->lastInsertId();
    mg_creator_campaign_dispute_append_event(
        $pdo,
        [
            'id' => $disputeId,
            'public_id' => $publicId,
            'source_type' => 'payout',
            'source_public_id' => (string) $payout['public_id'],
        ],
        'opened',
        null,
        'open',
        $actorUserId,
        'affiliate-refund-dispute:' . $refundPublicId . ':' . (string) $payout['public_id'],
        $reason
    );
    return $publicId;
}
