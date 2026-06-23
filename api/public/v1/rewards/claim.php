<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';
require_once dirname(__DIR__, 2) . '/distribution/_developer_webhooks.php';
require_once dirname(__DIR__, 2) . '/pppm/_pppm.php';

mg_require_method('POST');
$context = mg_public_context('distribution:rewards.issue');
$pdo = $context['pdo'];
$input = mg_input();

$rewardId = trim((string)($input['reward_id'] ?? ''));
$itemId = trim((string)($input['item_id'] ?? $input['pppm_item_id'] ?? ''));
$linkedAccountId = trim((string)($input['linked_account_id'] ?? ''));
$externalUserId = trim((string)($input['external_user_id'] ?? ''));
$externalClaimId = trim((string)($input['external_claim_id'] ?? ''));
$claimAction = strtolower(trim((string)($input['claim_action'] ?? 'claimed_in_app')));
$metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
$allowedActions = ['viewed_in_app','claimed_in_app','redeem_started','redeem_handoff','claim_cancelled'];

if ($rewardId === '' || $linkedAccountId === '' || $externalClaimId === '' || !in_array($claimAction, $allowedActions, true)) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Missing reward, linked account, external claim id, or claim action.');
    mg_fail('Reward ID, linked account ID, external claim ID, and a valid claim action are required.', 422);
}
if (mb_strlen($externalClaimId) > 180) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'External claim id is too long.');
    mg_fail('External claim ID is too long.', 422);
}

if (str_starts_with($rewardId, 'sandbox_reward_') || str_starts_with($linkedAccountId, 'sandbox_linked_')) {
    $stmt = $pdo->prepare('SELECT * FROM public_api_sandbox_rewards WHERE public_id=? AND merchant_user_id=? AND app_id=? AND linked_account_public_id=? LIMIT 1');
    $stmt->execute([$rewardId, (int)$context['merchant_user_id'], (int)$context['app_id'], $linkedAccountId]);
    $sandbox = $stmt->fetch();
    if (!$sandbox) {
        mg_public_log($pdo, $context, 404, 'not_found');
        mg_fail('Sandbox reward not found.', 404);
    }
    $eventId = mg_dev_webhook_event($pdo, (int)$context['app_id'], (int)$context['merchant_user_id'], 'reward.' . $claimAction, ['sandbox'=>true,'reward_id'=>$rewardId,'linked_account_id'=>$linkedAccountId,'external_claim_id'=>$externalClaimId,'claim_action'=>$claimAction,'metadata'=>$metadata], null, 'reward', $rewardId);
    mg_public_log($pdo, $context, 200, 'sandbox_claim_recorded');
    mg_ok(['sandbox'=>true,'reward_id'=>$rewardId,'item_id'=>$itemId ?: ('sandbox_item_' . substr(hash('sha256', $rewardId . '|item'), 0, 24)),'claim_status'=>$claimAction,'microgifter_event_id'=>$eventId], 'Sandbox reward claim recorded.');
}

$linkStmt = $pdo->prepare("SELECT * FROM developer_app_user_links WHERE public_id=? AND app_id=? AND merchant_user_id=? AND status='active' LIMIT 1");
$linkStmt->execute([$linkedAccountId, (int)$context['app_id'], (int)$context['merchant_user_id']]);
$link = $linkStmt->fetch();
if (!$link) {
    mg_public_log($pdo, $context, 404, 'linked_account_not_found');
    mg_fail('Linked Microgifter account not found.', 404);
}
if ($externalUserId !== '' && !hash_equals((string)$link['external_user_id'], $externalUserId)) {
    mg_public_log($pdo, $context, 403, 'linked_account_mismatch');
    mg_fail('Linked account does not match the external user.', 403);
}

$sourceId = $context['source_connection_id'];
$sql = "SELECT da.id AS allocation_id,da.public_id AS reward_id,da.status AS reward_status,dp.public_id AS program_id,cpt.public_id AS template_id,dse.id AS source_event_db_id,dse.public_id AS event_id,dse.external_event_id,dse.event_type,j.public_id AS job_id,i.* FROM distribution_allocations da INNER JOIN distribution_programs dp ON dp.id=da.program_id INNER JOIN distribution_recipients dr ON dr.id=da.recipient_id INNER JOIN distribution_program_products dpp ON dpp.id=da.program_product_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id LEFT JOIN distribution_source_events dse ON dse.id=da.source_event_id LEFT JOIN distribution_issuance_jobs j ON j.allocation_id=da.id LEFT JOIN pppm_items i ON i.id=j.pppm_item_id WHERE da.public_id=? AND dp.merchant_user_id=? AND dr.user_id=? AND (? IS NULL OR dse.connection_id IS NULL OR dse.connection_id=?)";
$params = [$rewardId, (int)$context['merchant_user_id'], (int)$link['microgifter_user_id'], $sourceId, $sourceId];
if ($itemId !== '') {
    $sql .= ' AND i.public_id=?';
    $params[] = $itemId;
}
$sql .= ' ORDER BY j.item_sequence ASC LIMIT 1 FOR UPDATE';

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row || empty($row['id'])) {
        mg_public_log($pdo, $context, 404, 'reward_item_not_found');
        mg_fail('Reward item not found.', 404);
    }

    $fromStatus = (string)$row['status'];
    $toStatus = $fromStatus;
    $fieldSql = '';
    if ($claimAction === 'viewed_in_app' && $fromStatus === 'delivered') {
        $toStatus = 'viewed';
        $fieldSql = ',viewed_at=COALESCE(viewed_at,NOW())';
    } elseif (in_array($claimAction, ['claimed_in_app','redeem_started','redeem_handoff'], true) && in_array($fromStatus, ['delivered','viewed','claim_pending'], true)) {
        $toStatus = 'claim_pending';
        $fieldSql = ',claimed_at=COALESCE(claimed_at,NOW())';
    }

    if ($toStatus !== $fromStatus || $fieldSql !== '') {
        $pdo->prepare("UPDATE pppm_items SET status=?,version_no=version_no+1" . $fieldSql . ",updated_at=NOW() WHERE id=?")
            ->execute([$toStatus, (int)$row['id']]);
        $refresh = $pdo->prepare('SELECT * FROM pppm_items WHERE id=? LIMIT 1');
        $refresh->execute([(int)$row['id']]);
        $item = $refresh->fetch() ?: $row;
    } else {
        $item = $row;
    }

    mg_pppm_record_event($pdo, $item, 'third_party_' . $claimAction, $fromStatus, $toStatus, (int)$link['microgifter_user_id'], null, ['app_id'=>(string)$context['app_public_id'],'linked_account_id'=>$linkedAccountId,'external_user_id'=>(string)$link['external_user_id'],'external_claim_id'=>$externalClaimId,'reward_id'=>$rewardId,'source_event_id'=>$row['event_id'] ?? null,'metadata'=>$metadata]);
    $eventId = mg_dev_webhook_event($pdo, (int)$context['app_id'], (int)$context['merchant_user_id'], 'reward.' . $claimAction, ['reward_id'=>$rewardId,'item_id'=>(string)$item['public_id'],'linked_account_id'=>$linkedAccountId,'external_user_id'=>(string)$link['external_user_id'],'external_claim_id'=>$externalClaimId,'claim_action'=>$claimAction,'item_status'=>(string)$item['status'],'program_id'=>(string)$row['program_id'],'template_id'=>(string)$row['template_id'],'metadata'=>$metadata], $row['source_event_db_id'] !== null ? (int)$row['source_event_db_id'] : null, 'reward', $rewardId);
    $pdo->commit();

    mg_public_log($pdo, $context, 200, 'claim_recorded');
    mg_ok(['reward_id'=>$rewardId,'item_id'=>(string)$item['public_id'],'claim_status'=>$claimAction,'item_status'=>(string)$item['status'],'microgifter_event_id'=>$eventId], 'Reward claim recorded.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_public_log($pdo, $context, 500, 'claim_failed', 'Reward claim failed.');
    mg_fail('Unable to record reward claim.', 500);
}
