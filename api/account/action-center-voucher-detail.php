<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center_contract.php';

function mg_ac_voucher_detail_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    if (!is_string($raw) || trim($raw) === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_ac_voucher_detail_text(mixed $value, int $limit = 4000): string
{
    if (is_scalar($value)) return mb_substr(trim((string) $value), 0, $limit);
    if (!is_array($value)) return '';
    foreach (['note', 'text', 'terms', 'description', 'label', 'instructions', 'policy'] as $key) {
        $candidate = $value[$key] ?? null;
        if (is_scalar($candidate) && trim((string) $candidate) !== '') {
            return mb_substr(trim((string) $candidate), 0, $limit);
        }
    }
    $parts = [];
    foreach ($value as $candidate) {
        if (is_scalar($candidate) && trim((string) $candidate) !== '') $parts[] = trim((string) $candidate);
    }
    return mb_substr(implode(' · ', array_unique($parts)), 0, $limit);
}

function mg_ac_voucher_detail_timeline_add(array &$timeline, string $type, string $label, mixed $occurredAt, ?string $actor = null, ?string $recipient = null, ?string $detail = null): void
{
    $occurredAt = trim((string) $occurredAt);
    if ($occurredAt === '') return;
    $timeline[] = [
        'type' => mb_substr(trim($type), 0, 50),
        'label' => mb_substr(trim($label), 0, 160),
        'occurred_at' => $occurredAt,
        'actor' => $actor !== null && trim($actor) !== '' ? mb_substr(trim($actor), 0, 190) : null,
        'recipient' => $recipient !== null && trim($recipient) !== '' ? mb_substr(trim($recipient), 0, 190) : null,
        'detail' => $detail !== null && trim($detail) !== '' ? mb_substr(trim($detail), 0, 500) : null,
    ];
}

function mg_ac_voucher_detail_sort_timeline(array $timeline): array
{
    usort($timeline, static function (array $a, array $b): int {
        return strcmp((string) ($a['occurred_at'] ?? ''), (string) ($b['occurred_at'] ?? ''));
    });
    $seen = [];
    $clean = [];
    foreach ($timeline as $event) {
        $key = implode('|', [(string) ($event['type'] ?? ''), (string) ($event['occurred_at'] ?? ''), (string) ($event['actor'] ?? ''), (string) ($event['recipient'] ?? '')]);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $clean[] = $event;
    }
    return $clean;
}

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int) ($user['id'] ?? 0);
$actionItemId = trim((string) ($_GET['id'] ?? $_GET['action_item_id'] ?? ''));
if ($actionItemId === '' || mb_strlen($actionItemId) > 190) mg_fail('Action Center item id is required.', 422);

$pdo = mg_db();
$walletId = mg_ac_wallet_action_id($actionItemId);
if ($walletId !== null) {
    $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, mg_ac_wallet_user_email($user), false);
    if (!$wallet) mg_fail('Action Center wallet voucher not found.', 404);
    $metadata = mg_ac_wallet_reward_metadata($wallet);
    $terms = mg_ac_voucher_detail_text(
        $wallet['redemption_instructions'] ?? $metadata['terms'] ?? $metadata['terms_text'] ?? $metadata['instructions'] ?? ''
    );
    $timeline = [];
    mg_ac_voucher_detail_timeline_add($timeline, 'issued', 'Reward issued', $wallet['issued_at'] ?? $wallet['created_at'] ?? null, trim((string) ($wallet['merchant_label'] ?? $wallet['merchant_full_name'] ?? 'Participating merchant')));
    mg_ac_voucher_detail_timeline_add($timeline, 'claimed', 'Reward claimed', $wallet['claimed_at'] ?? null, trim((string) ($wallet['contact_name'] ?? $wallet['contact_email'] ?? 'Reward recipient')));
    mg_ac_voucher_detail_timeline_add($timeline, 'redeemed', 'Reward redeemed', $wallet['redeemed_at'] ?? null, trim((string) ($wallet['merchant_label'] ?? $wallet['merchant_full_name'] ?? 'Participating merchant')));
    if (mg_ac_wallet_expired($wallet)) {
        mg_ac_voucher_detail_timeline_add($timeline, 'expired', 'Reward expired', $wallet['expires_at'] ?? null);
    }
    mg_ok([
        'detail_version' => 1,
        'action_item_id' => $actionItemId,
        'kind' => 'wallet_reward',
        'terms' => $terms !== '' ? $terms : 'Present this reward to the participating merchant. Merchant redemption rules apply.',
        'expiration_policy' => trim((string) ($wallet['expires_at'] ?? '')) !== '' ? 'Expires on the date shown on this reward.' : 'No expiration policy is listed.',
        'timeline' => mg_ac_voucher_detail_sort_timeline($timeline),
        'claim_qr_supported' => !mg_ac_wallet_expired($wallet) && (string) ($wallet['status'] ?? '') !== 'redeemed',
        'redemption' => [
            'status' => mg_ac_wallet_state($wallet),
            'redeemed_at' => $wallet['redeemed_at'] ?? null,
            'location_name' => 'Participating merchant',
        ],
    ]);
}

$stmt = $pdo->prepare(
    "SELECT ac.id action_item_internal_id,ac.public_id action_item_id,ac.folder,ac.state,ac.first_received_at,ac.sent_at,ac.claimed_at,ac.redeemed_at,ac.updated_at,
            i.id instance_internal_id,i.public_id instance_id,i.status instance_status,i.issued_at,i.expires_at,
            COALESCE(coi.product_version_id,i.product_version_id) resolved_product_version_id,
            cpv.title product_title,cpv.terms_json,cpv.expiration_policy_json,
            r.status redemption_status,r.redeemed_at merchant_redeemed_at,
            l.name location_name
     FROM microgift_inbox_items ac
     INNER JOIN microgift_instances i ON i.id=ac.instance_id
     LEFT JOIN commerce_order_items coi ON coi.id=i.commerce_order_item_id
     LEFT JOIN catalog_product_versions cpv ON cpv.id=COALESCE(coi.product_version_id,i.product_version_id)
     LEFT JOIN microgift_redemptions r ON r.id=ac.redemption_id
     LEFT JOIN merchant_locations l ON l.id=ac.location_id
     WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
     LIMIT 1"
);
$stmt->execute([$actionItemId, $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) mg_fail('Action Center voucher not found.', 404);

$timeline = [];
mg_ac_voucher_detail_timeline_add($timeline, 'issued', 'Voucher issued', $row['issued_at'] ?? null);

$delivery = $pdo->prepare(
    "SELECT e.event_type,e.occurred_at,
            COALESCE(sender.display_name,sender.full_name) sender_name,
            COALESCE(recipient.display_name,recipient.full_name) recipient_name
     FROM microgift_delivery_events e
     LEFT JOIN users sender ON sender.id=e.sender_user_id
     LEFT JOIN users recipient ON recipient.id=e.recipient_user_id
     WHERE e.instance_id=?
     ORDER BY e.occurred_at ASC,e.id ASC"
);
$delivery->execute([(int) $row['instance_internal_id']]);
foreach ($delivery->fetchAll(PDO::FETCH_ASSOC) as $event) {
    $type = strtolower(trim((string) ($event['event_type'] ?? 'delivered')));
    $label = match ($type) {
        'sent' => 'Gift sent',
        'resent' => 'Gift regifted',
        'delivered' => 'Gift delivered',
        default => 'Ownership updated',
    };
    mg_ac_voucher_detail_timeline_add(
        $timeline,
        $type,
        $label,
        $event['occurred_at'] ?? null,
        (string) ($event['sender_name'] ?? ''),
        (string) ($event['recipient_name'] ?? '')
    );
}

mg_ac_voucher_detail_timeline_add($timeline, 'received', 'Added to Inbox', $row['first_received_at'] ?? null);
mg_ac_voucher_detail_timeline_add($timeline, 'claimed', 'Voucher claimed', $row['claimed_at'] ?? null);
mg_ac_voucher_detail_timeline_add($timeline, 'redeemed', 'Voucher redeemed', $row['merchant_redeemed_at'] ?? $row['redeemed_at'] ?? null, null, null, (string) ($row['location_name'] ?? ''));

$termsJson = mg_ac_voucher_detail_json($row['terms_json'] ?? null);
$expirationJson = mg_ac_voucher_detail_json($row['expiration_policy_json'] ?? null);
$terms = mg_ac_voucher_detail_text($termsJson);
$expirationPolicy = mg_ac_voucher_detail_text($expirationJson);
$status = strtolower(trim((string) ($row['redemption_status'] ?? $row['state'] ?? $row['instance_status'] ?? '')));

mg_ok([
    'detail_version' => 1,
    'action_item_id' => $actionItemId,
    'kind' => 'catalog_voucher',
    'product' => [
        'title' => trim((string) ($row['product_title'] ?? '')) ?: null,
        'has_exact_version' => !empty($row['resolved_product_version_id']),
    ],
    'terms' => $terms !== '' ? $terms : 'Merchant terms apply. Present the voucher at an eligible merchant location.',
    'expiration_policy' => $expirationPolicy !== '' ? $expirationPolicy : (trim((string) ($row['expires_at'] ?? '')) !== '' ? 'Expires on the voucher expiration date.' : 'No expiration policy is listed.'),
    'timeline' => mg_ac_voucher_detail_sort_timeline($timeline),
    'claim_qr_supported' => in_array((string) ($row['folder'] ?? ''), ['inbox', 'claimed'], true)
        && !in_array($status, ['completed', 'redeemed', 'expired', 'cancelled'], true),
    'redemption' => [
        'status' => $status !== '' ? $status : 'not_redeemed',
        'redeemed_at' => $row['merchant_redeemed_at'] ?? $row['redeemed_at'] ?? null,
        'location_name' => trim((string) ($row['location_name'] ?? '')) ?: null,
    ],
]);
