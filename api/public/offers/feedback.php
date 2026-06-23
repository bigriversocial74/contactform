<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

function mg_feedback_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();
$offerId = strtolower(trim((string) ($input['offer_id'] ?? '')));
$event = strtolower(trim((string) ($input['event'] ?? 'view')));
$allowed = ['view','details','recommendation','add_attempt','add_success','dismiss','save'];
if ($offerId === '' || strlen($offerId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $offerId) || !in_array($event, $allowed, true)) {
    mg_fail('Invalid offer feedback.', 422);
}

try {
    $stmt = $pdo->prepare('SELECT id,merchant_user_id,title FROM reward_templates WHERE public_id = ? AND status = \'active\' AND agent_discoverable = 1 LIMIT 1');
    $stmt->execute([$offerId]);
    $template = $stmt->fetch();
    if (!$template) mg_fail('Offer not found.', 404);

    $user = mg_current_user();
    $context = [
        'offer_id' => $offerId,
        'event' => $event,
        'source' => (string) ($input['source'] ?? 'agent_offer_ui'),
        'query' => (string) ($input['query'] ?? ''),
        'user_id' => $user ? (int) $user['id'] : null,
    ];
    $insert = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $insert->execute([mg_feedback_uuid(), (int) $template['merchant_user_id'], null, null, null, 'agent_offer.feedback.' . $event, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    mg_ok(['logged' => true, 'event' => $event]);
} catch (Throwable $error) {
    mg_security_log('warning', 'public.offers.feedback_unavailable', 'Offer feedback unavailable.', ['exception_class' => $error::class]);
    mg_ok(['logged' => false], 'Offer feedback unavailable until the Stage 12 schema is installed.');
}
