<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_microgift_issue_signal(PDO $pdo, array $row, int $actor): void
{
    $recipientId = (int)($row['recipient_user_id'] ?? 0);
    if ($recipientId < 1 || $recipientId === $actor) return;

    $publicId = strtolower(trim((string)($row['public_id'] ?? '')));
    if ($publicId === '') return;
    $title = trim((string)($row['title_snapshot'] ?? '')) ?: 'a Microgift';
    $url = '/inbox.php?item=' . rawurlencode($publicId);
    $body = mg_notification_user_label($pdo, $actor) . ' sent you ' . $title . '.';

    mg_create_notification(
        $pdo,
        $recipientId,
        'gift',
        'New Microgift',
        $body,
        $url,
        [
            'actor_user_id'=>$actor,
            'event_key'=>'microgift.issued.' . $publicId,
            'microgift_instance_id'=>(int)($row['id'] ?? 0),
            'microgift_public_id'=>$publicId,
        ]
    );
}
