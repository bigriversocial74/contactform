<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

function mg_pppm_activity_time(array $row): ?string
{
    return $row['redeemed_at']
        ?? $row['viewed_at']
        ?? $row['delivered_at']
        ?? $row['sent_at']
        ?? $row['assigned_at']
        ?? $row['updated_at']
        ?? $row['issued_at']
        ?? null;
}

function mg_pppm_activity_format_time(?string $value): string
{
    if (!$value) {
        return 'Just now';
    }
    $timestamp = strtotime($value);
    return $timestamp ? gmdate('M j, Y g:i A', $timestamp) . ' UTC' : $value;
}

function mg_pppm_activity_format_money(int $cents, string $currency): string
{
    $code = strtoupper($currency);
    return ($code === 'USD' ? '$' : $code . ' ') . number_format($cents / 100, 2);
}

function mg_pppm_activity_public(array $row, int $viewerUserId): array
{
    $metadata = !empty($row['metadata_snapshot_json'])
        ? json_decode((string) $row['metadata_snapshot_json'], true)
        : [];
    if (!is_array($metadata)) {
        $metadata = [];
    }

    $senderName = trim((string) ($row['issuer_display_name'] ?? $row['issuer_full_name'] ?? 'Microgifter user'));
    $recipientName = trim((string) ($row['recipient_display_name'] ?? $row['recipient_full_name'] ?? $row['recipient_external_id'] ?? 'Recipient'));
    $activityTime = mg_pppm_activity_time($row);
    $direction = ((int) ($row['issuer_user_id'] ?? 0) === $viewerUserId || (int) ($row['owner_user_id'] ?? 0) === $viewerUserId)
        ? 'sent'
        : 'received';

    return [
        'id' => (string) $row['public_id'],
        'pppm_id' => (string) $row['public_id'],
        'title' => (string) $row['title_snapshot'],
        'description' => (string) ($row['description_snapshot'] ?? ''),
        'sent_from' => $senderName !== '' ? $senderName : 'Microgifter user',
        'recipient' => $recipientName !== '' ? $recipientName : 'Recipient',
        'timestamp' => $activityTime,
        'time_label' => mg_pppm_activity_format_time($activityTime),
        'gift_type' => ucfirst(str_replace('_', ' ', (string) $row['item_type'])),
        'item_type' => (string) $row['item_type'],
        'funding_type' => (string) $row['funding_type'],
        'value_cents' => (int) $row['value_cents_snapshot'],
        'currency' => (string) $row['currency_snapshot'],
        'value' => mg_pppm_activity_format_money((int) $row['value_cents_snapshot'], (string) $row['currency_snapshot']),
        'status' => (string) $row['status'],
        'direction' => $direction,
        'avatar' => (string) ($metadata['avatar_url'] ?? '/assets/images/default-avatar.svg'),
        'card' => is_array($metadata['card'] ?? null) ? $metadata['card'] : [],
        'metadata' => $metadata,
        'source_reference' => $row['source_reference'] ?? null,
        'source_line_reference' => $row['source_line_reference'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_pppm_activity_box(string $box, int $userId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $base =
        'SELECT p.*, issuer.display_name AS issuer_display_name, issuer.full_name AS issuer_full_name,
                recipient.display_name AS recipient_display_name, recipient.full_name AS recipient_full_name
         FROM pppm_items p
         LEFT JOIN users issuer ON issuer.id = p.issuer_user_id
         LEFT JOIN users recipient ON recipient.id = p.recipient_user_id ';

    if ($box === 'inbox') {
        $sql = $base .
            "WHERE p.recipient_user_id = ?
               AND p.status IN ('assigned','scheduled','sent','delivered','viewed','claim_pending','verified')
             ORDER BY COALESCE(p.viewed_at,p.delivered_at,p.sent_at,p.assigned_at,p.updated_at,p.issued_at) DESC, p.id DESC
             LIMIT {$limit}";
        $params = [$userId];
    } elseif ($box === 'sent') {
        $sql = $base .
            "WHERE (p.issuer_user_id = ? OR p.owner_user_id = ?)
               AND p.status IN ('assigned','scheduled','sent','delivered','viewed','claim_pending','verified','redeemed','expired','cancelled','refunded','voided')
             ORDER BY COALESCE(p.redeemed_at,p.viewed_at,p.delivered_at,p.sent_at,p.assigned_at,p.updated_at,p.issued_at) DESC, p.id DESC
             LIMIT {$limit}";
        $params = [$userId, $userId];
    } elseif ($box === 'claimed') {
        $sql = $base .
            "WHERE (p.issuer_user_id = ? OR p.owner_user_id = ? OR p.recipient_user_id = ?)
               AND p.status = 'redeemed'
             ORDER BY COALESCE(p.redeemed_at,p.updated_at) DESC, p.id DESC
             LIMIT {$limit}";
        $params = [$userId, $userId, $userId];
    } else {
        mg_fail('Invalid gift activity box.', 422);
    }

    $stmt = mg_db()->prepare($sql);
    $stmt->execute($params);
    return array_map(
        static fn(array $row): array => mg_pppm_activity_public($row, $userId),
        $stmt->fetchAll()
    );
}

function mg_pppm_activity_find(int $userId, string $publicId): ?array
{
    $stmt = mg_db()->prepare(
        'SELECT p.*, issuer.display_name AS issuer_display_name, issuer.full_name AS issuer_full_name,
                recipient.display_name AS recipient_display_name, recipient.full_name AS recipient_full_name
         FROM pppm_items p
         LEFT JOIN users issuer ON issuer.id = p.issuer_user_id
         LEFT JOIN users recipient ON recipient.id = p.recipient_user_id
         WHERE p.public_id = ?
           AND (p.issuer_user_id = ? OR p.owner_user_id = ? OR p.recipient_user_id = ? OR p.merchant_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$publicId, $userId, $userId, $userId, $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}
