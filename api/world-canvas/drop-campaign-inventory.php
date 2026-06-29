<?php
/**
 * Target Drop campaign/reward inventory lookup for launch preflight.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_campaigns.php';

$user = mg_require_permission('merchant.locations.manage');
$pdo = mg_db();

try {
    $input = mg_input();
    $merchantId = (int)($user['id'] ?? 0);
    $campaignPublicId = trim((string)($input['campaign_public_id'] ?? $input['campaign_id'] ?? ''));
    $dropPublicId = trim((string)($input['target_drop_id'] ?? $input['drop_id'] ?? $input['id'] ?? ''));

    if ($campaignPublicId === '' && $dropPublicId !== '' && mg_world_target_drops_ready($pdo)) {
        $rows = mg_world_canvas_rows($pdo, 'SELECT campaign_public_id FROM merchant_target_drops WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$dropPublicId, $merchantId]);
        $campaignPublicId = trim((string)($rows[0]['campaign_public_id'] ?? ''));
    }

    if ($campaignPublicId === '') {
        mg_ok([
            'campaign_found' => false,
            'message' => 'No campaign is attached to this Target Drop yet.',
        ]);
    }

    $campaign = mg_world_target_drop_campaign_payload($pdo, $merchantId, $campaignPublicId);
    if (!$campaign) {
        mg_ok([
            'campaign_found' => false,
            'campaign_public_id' => $campaignPublicId,
            'message' => 'Attached campaign was not found for this merchant.',
        ]);
    }

    $reward = null;
    if (mg_world_canvas_table($pdo, 'campaigns') && mg_world_canvas_table($pdo, 'reward_templates')) {
        $rows = mg_world_canvas_rows($pdo, 'SELECT rt.public_id, rt.title, rt.status FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.merchant_user_id=? AND c.public_id=? LIMIT 1', [$merchantId, $campaignPublicId]);
        if ($rows && !empty($rows[0]['public_id'])) {
            $reward = [
                'id' => (string)$rows[0]['public_id'],
                'title' => (string)($rows[0]['title'] ?? 'Reward'),
                'status' => (string)($rows[0]['status'] ?? ''),
            ];
        }
    }

    mg_ok([
        'campaign_found' => true,
        'campaign' => $campaign,
        'reward' => $reward,
        'available_quantity' => $campaign['quantity_limit'] === null ? null : (int)$campaign['quantity_limit'],
        'claim_limit_per_user' => (int)($campaign['claim_limit_per_user'] ?? 1),
        'quantity_source' => 'campaign.quantity_limit',
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.drop_campaign_inventory_failed', 'Target Drop campaign inventory lookup failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to check campaign reward quantity.', 500);
}
