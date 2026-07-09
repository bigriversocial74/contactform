<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/_limits.php';

function mg_participation_status_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$campaignType = strtolower(trim((string)($input['campaign_type'] ?? '')));
$allowedTypes = ['listen_music_reward', 'watch_video_reward'];

if ($campaignRef === '' || !in_array($campaignType, $allowedTypes, true)) {
    mg_fail('Invalid campaign status check.', 422);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    mg_ok(['known' => false, 'participated' => false, 'needs_email' => true], 'Enter an email to check participation.');
}

$stmt = $pdo->prepare("SELECT id, public_id, per_user_limit, starts_at, ends_at, quantity_limit, issued_count FROM campaigns WHERE status='active' AND campaign_type=? AND (public_id=? OR public_slug=?) LIMIT 1");
$stmt->execute([$campaignType, $campaignRef, $campaignRef]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) {
    mg_fail('Campaign is not available.', 404);
}

$now = time();
if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) {
    mg_ok(['known' => true, 'available' => false, 'participated' => false, 'reason' => 'not_started'], 'Campaign has not started yet.');
}
if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) {
    mg_ok(['known' => true, 'available' => false, 'participated' => false, 'reason' => 'ended'], 'Campaign has ended.');
}
if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
    mg_ok(['known' => true, 'available' => false, 'participated' => false, 'reason' => 'campaign_limit'], 'Campaign reward limit has been reached.');
}

$userId = mg_participation_status_find_user($pdo, $email);
$limit = max(1, (int)($campaign['per_user_limit'] ?? 1));
$count = mg_public_campaign_limit_count($pdo, 'campaign_id', (int)$campaign['id'], $userId, $email);
$participated = $count >= $limit;

mg_ok([
    'known' => true,
    'available' => !$participated,
    'participated' => $participated,
    'participation_count' => $count,
    'per_user_limit' => $limit,
    'campaign_id' => (string)$campaign['public_id'],
], $participated ? 'You have already participated in this campaign.' : 'Campaign participation available.');
