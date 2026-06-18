<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_follow_notification_context(PDO $pdo, int $actorId, string $reference): ?array
{
    $stmt = $pdo->prepare('SELECT user_id FROM public_profiles WHERE (public_id=? OR slug=?) LIMIT 1');
    $stmt->execute([$reference, strtolower($reference)]);
    $recipientId = (int)($stmt->fetchColumn() ?: 0);
    if ($recipientId < 1 || $recipientId === $actorId) return null;

    $follow = $pdo->prepare("SELECT 1 FROM social_follows WHERE follower_user_id=? AND followed_user_id=? AND status='active' LIMIT 1");
    $follow->execute([$actorId, $recipientId]);
    return $follow->fetchColumn() ? null : ['recipient_id'=>$recipientId];
}

function mg_follow_notification_send(PDO $pdo, int $actorId, array $context): void
{
    $recipientId = (int)($context['recipient_id'] ?? 0);
    if ($recipientId < 1 || $recipientId === $actorId) return;

    $follow = $pdo->prepare(
        "SELECT DATE_FORMAT(updated_at,'%Y%m%d%H%i%s%f') event_version
         FROM social_follows
         WHERE follower_user_id=? AND followed_user_id=? AND status='active'
         LIMIT 1"
    );
    $follow->execute([$actorId, $recipientId]);
    $eventVersion = trim((string)($follow->fetchColumn() ?: ''));
    if ($eventVersion === '') return;
    $eventKey = trim((string)($context['event_key'] ?? ''));
    if ($eventKey === '') $eventKey = 'social.follow.' . $actorId . '.' . $recipientId . '.' . strtolower($eventVersion);

    $name = mg_notification_user_label($pdo, $actorId);
    $profile = $pdo->prepare("SELECT slug FROM public_profiles WHERE user_id=? AND status='active' LIMIT 1");
    $profile->execute([$actorId]);
    $slug = (string)($profile->fetchColumn() ?: '');
    $url = $slug !== '' ? '/profile.php?slug=' . rawurlencode($slug) : '/notifications.php';

    mg_create_notification(
        $pdo,
        $recipientId,
        'social',
        'New follower',
        $name . ' followed your profile.',
        $url,
        [
            'actor_user_id'=>$actorId,
            'event_key'=>$eventKey,
            'relationship'=>'follow',
        ]
    );
}
