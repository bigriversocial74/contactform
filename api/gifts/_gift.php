<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

if (!function_exists('mg_public_uuid')) {
    function mg_public_uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

function mg_gift_public_id(): string
{
    return 'GFT-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

function mg_gift_request_id(array $input = []): string
{
    $id = trim((string) ($input['id'] ?? $_GET['id'] ?? ''));
    if ($id === '' || strlen($id) > 32 || !preg_match('/^GFT-[A-Z0-9-]+$/', $id)) mg_fail('Invalid gift identifier.', 422);
    return $id;
}

function mg_gift_format_money(int $cents, string $currency = 'USD'): string
{
    $symbol = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' ';
    return $symbol . number_format($cents / 100, 2);
}

function mg_gift_format_time(?string $value): string
{
    if (!$value) return 'Just now';
    $timestamp = strtotime($value);
    if (!$timestamp) return $value;
    return gmdate('M j, Y g:i A', $timestamp) . ' UTC';
}

function mg_gift_access_clause(string $alias = 'g'): string
{
    return "({$alias}.sender_user_id = ? OR {$alias}.recipient_user_id = ?)";
}

function mg_gift_find_accessible(int $userId, string $publicId): ?array
{
    $stmt = mg_db()->prepare(
        'SELECT g.*, sender.display_name AS sender_name, sender.full_name AS sender_full_name,
                recipient.display_name AS recipient_display_name, recipient.full_name AS recipient_full_name
         FROM gifts g
         INNER JOIN users sender ON sender.id = g.sender_user_id
         LEFT JOIN users recipient ON recipient.id = g.recipient_user_id
         WHERE g.public_id = ? AND ' . mg_gift_access_clause('g') . ' LIMIT 1'
    );
    $stmt->execute([$publicId, $userId, $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function mg_gift_require_accessible(int $userId, string $publicId): array
{
    $gift = mg_gift_find_accessible($userId, $publicId);
    if (!$gift) mg_fail('Gift not found.', 404);
    return $gift;
}

function mg_gift_row_to_public(array $row, int $viewerUserId): array
{
    $senderName = trim((string) ($row['sender_name'] ?? $row['sender_full_name'] ?? 'Microgifter user'));
    $recipientName = trim((string) ($row['recipient_display_name'] ?? $row['recipient_full_name'] ?? $row['recipient_name'] ?? 'Recipient'));
    $activityTime = $row['claimed_at'] ?? $row['delivered_at'] ?? $row['sent_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? null;
    $metadata = [];
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string) $row['metadata_json'], true);
        if (is_array($decoded)) $metadata = $decoded;
    }
    return [
        'id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'description'=>(string)($row['description'] ?? ''),
        'sent_from'=>$senderName !== '' ? $senderName : 'Microgifter user','recipient'=>$recipientName !== '' ? $recipientName : 'Recipient',
        'timestamp'=>$activityTime,'time_label'=>mg_gift_format_time($activityTime),'gift_type'=>(string)$row['gift_type'],
        'value_cents'=>(int)$row['value_cents'],'currency'=>(string)$row['currency'],'value'=>mg_gift_format_money((int)$row['value_cents'], (string)$row['currency']),
        'status'=>(string)$row['status'],'direction'=>(int)$row['sender_user_id'] === $viewerUserId ? 'sent' : 'received',
        'avatar'=>(string)($metadata['avatar_url'] ?? '/assets/images/default-avatar.svg'),'card'=>is_array($metadata['card'] ?? null) ? $metadata['card'] : [],
        'metadata'=>$metadata,'created_at'=>$row['created_at'] ?? null,'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function mg_gift_box_where(string $box): array
{
    return match ($box) {
        'inbox' => ["g.recipient_user_id = ? AND g.status IN ('sent','delivered')", 'recipient'],
        'sent' => ["g.sender_user_id = ? AND g.status IN ('sent','delivered','claimed','expired','cancelled')", 'sender'],
        'claimed' => ["(g.sender_user_id = ? OR g.recipient_user_id = ?) AND g.status = 'claimed'", 'both'],
        default => mg_fail('Invalid gift activity box.', 422),
    };
}

function mg_gift_event(PDO $pdo, int $giftId, ?int $actorUserId, string $eventType, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO gift_events (gift_id, actor_user_id, event_type, metadata_json, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$giftId,$actorUserId,$eventType,$metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null]);
    if ($eventType !== 'sent') return;

    $giftStmt = $pdo->prepare('SELECT public_id,recipient_user_id,sender_user_id,title FROM gifts WHERE id=? LIMIT 1');
    $giftStmt->execute([$giftId]);
    $gift = $giftStmt->fetch(PDO::FETCH_ASSOC);
    $recipientId = (int)($gift['recipient_user_id'] ?? 0);
    if (!$gift || $recipientId < 1 || $recipientId === (int)$actorUserId) return;

    $senderId = (int)($gift['sender_user_id'] ?? $actorUserId ?? 0);
    $sender = $senderId > 0 ? mg_notification_user_label($pdo,$senderId) : 'A Microgifter member';
    $giftTitle = trim((string)($gift['title'] ?? '')) ?: 'a gift';
    $giftPublicId = (string)$gift['public_id'];
    mg_create_notification(
        $pdo,
        $recipientId,
        'gift',
        'You received a gift',
        $sender . ' sent you ' . $giftTitle . '.',
        '/inbox.php?item=' . rawurlencode($giftPublicId),
        [
            'actor_user_id'=>$senderId > 0 ? $senderId : null,
            'event_key'=>'gift.sent.' . strtolower($giftPublicId),
            'gift_id'=>$giftId,
            'gift_public_id'=>$giftPublicId,
        ]
    );
}

function mg_message_validate_body(mixed $value): string
{
    $body = trim((string) $value);
    if ($body === '' || mb_strlen($body) > 4000) mg_fail('Message must be between 1 and 4000 characters.', 422, ['body' => 'Enter a valid message.']);
    return $body;
}
