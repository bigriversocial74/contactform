<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 3) . '/includes/public-donations-feature.php';

function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
    if ($campaignRef === '') return $input;

    $stmt = $pdo->prepare('SELECT campaign_type,merchant_user_id FROM campaigns WHERE public_id=? OR public_slug=? LIMIT 1');
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) return $input;

    $type = (string)$campaign['campaign_type'];
    if ($type === 'public_donation' && !mg_public_donations_is_enabled_for((int)$campaign['merchant_user_id'], mg_current_user())) {
        mg_fail('Campaign is not available.', 404);
    }
    if (!mg_campaign_type_public_transactional($type)) {
        mg_fail('This campaign is informational and does not accept public requests.', 409);
    }
    return $input;
}

require __DIR__ . '/engage-core.php';
