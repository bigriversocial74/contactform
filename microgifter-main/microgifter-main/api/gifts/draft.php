<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';

mg_require_method('POST');
$user = mg_require_permission('gift.create');
$input = mg_input();
mg_require_csrf_for_write($input);

function mg_gift_text(mixed $value, string $field, int $max, bool $required = true): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '' && !$required) {
        return null;
    }
    if ($text === '' || mb_strlen($text) > $max) {
        mg_fail('Invalid gift data.', 422, [$field => "Enter {$field} using {$max} characters or fewer."]);
    }
    return $text;
}

function mg_gift_slug(mixed $value): ?string
{
    $slug = strtolower(trim((string) ($value ?? '')));
    if ($slug === '') {
        return null;
    }
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '' || strlen($slug) > 160) {
        mg_fail('Invalid gift URL slug.', 422, ['slug' => 'Enter a valid URL slug.']);
    }
    return $slug;
}

function mg_gift_value_cents(mixed $value): int
{
    $raw = preg_replace('/[^0-9.]/', '', (string) ($value ?? '0')) ?? '0';
    $amount = round((float) $raw, 2);
    if ($amount < 0 || $amount > 1000000) {
        mg_fail('Invalid gift value.', 422, ['value' => 'Enter a valid gift value.']);
    }
    return (int) round($amount * 100);
}

$id = trim((string) ($input['id'] ?? ''));
$title = mg_gift_text($input['title'] ?? '', 'title', 160);
$description = mg_gift_text($input['description'] ?? '', 'description', 5000, false);
$giftType = mg_gift_text($input['gift_type'] ?? 'Digital gift', 'gift type', 80);
$recipientName = mg_gift_text($input['recipient_name'] ?? 'Recipient', 'recipient name', 120);
$slug = mg_gift_slug($input['slug'] ?? null);
$valueCents = mg_gift_value_cents($input['value'] ?? 0);
$visibility = trim((string) ($input['visibility'] ?? 'draft'));
if (!in_array($visibility, ['draft', 'private'], true)) {
    $visibility = 'draft';
}
$metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
$metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
$pdo = mg_db();

try {
    $pdo->beginTransaction();

    if ($id !== '') {
        $gift = mg_gift_require_accessible((int) $user['id'], mg_gift_request_id(['id' => $id]));
        if ((int) $gift['sender_user_id'] !== (int) $user['id']) {
            mg_fail('Only the gift owner can edit this draft.', 403);
        }
        if (($gift['status'] ?? '') !== 'draft') {
            mg_fail('Published gifts cannot be edited as drafts.', 409);
        }
        $stmt = $pdo->prepare(
            'UPDATE gifts SET slug = ?, recipient_name = ?, title = ?, description = ?, gift_type = ?, value_cents = ?, visibility = ?, metadata_json = ?, updated_at = NOW()
             WHERE id = ? AND sender_user_id = ?'
        );
        $stmt->execute([$slug, $recipientName, $title, $description, $giftType, $valueCents, $visibility, $metadataJson, (int) $gift['id'], (int) $user['id']]);
        $publicId = (string) $gift['public_id'];
        mg_gift_event($pdo, (int) $gift['id'], (int) $user['id'], 'created', ['action' => 'draft_updated']);
        $message = 'Gift draft updated.';
    } else {
        $publicId = mg_gift_public_id();
        $stmt = $pdo->prepare(
            "INSERT INTO gifts (public_id, slug, sender_user_id, recipient_name, title, description, gift_type, value_cents, currency, visibility, status, metadata_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'USD', ?, 'draft', ?, NOW(), NOW())"
        );
        $stmt->execute([$publicId, $slug, (int) $user['id'], $recipientName, $title, $description, $giftType, $valueCents, $visibility, $metadataJson]);
        $giftDbId = (int) $pdo->lastInsertId();
        mg_gift_event($pdo, $giftDbId, (int) $user['id'], 'created', ['action' => 'draft_created']);
        $message = 'Gift draft created.';
    }

    $pdo->commit();
    mg_audit('gift.draft_saved', 'gift', ['gift_id' => $publicId], (int) $user['id']);
    mg_ok(['gift_id' => $publicId], $message, $id === '' ? 201 : 200);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'gift.draft_save_failed', 'Gift draft save failed.', ['exception_type' => get_class($e)], (int) $user['id']);
    mg_fail('Unable to save the gift draft right now.', 500);
}
