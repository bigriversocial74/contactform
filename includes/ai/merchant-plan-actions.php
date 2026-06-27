<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-planner.php';
require_once dirname(__DIR__) . '/merchant-automation-controls.php';

function mg_ai_plan_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_ai_plan_short(mixed $value, int $max = 500): string
{
    $text = trim((string) $value);
    return $text === '' ? '' : mb_substr($text, 0, $max);
}

function mg_ai_plan_money_to_cents(mixed $value): int
{
    $raw = trim((string) $value);
    if ($raw === '') return 0;
    if (is_numeric($raw) && (int) $raw === (float) $raw && (int) $raw > 1000) return max(0, (int) $raw);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) return 0;
    return (int) round(((float) $raw) * 100);
}

function mg_ai_plan_csv_json(mixed $value): ?string
{
    if (is_array($value)) {
        $items = array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $value), static fn($v): bool => $v !== ''));
    } else {
        $items = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn($v): bool => $v !== ''));
    }
    return $items === [] ? null : json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mg_ai_plan_slug(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'campaign', 0, 120);
}

function mg_ai_plan_unique_campaign_slug(PDO $pdo, int $merchantId, string $title): string
{
    $base = mg_ai_plan_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id = ? AND public_slug = ?');
    while (true) {
        $stmt->execute([$merchantId, $candidate]);
        if ((int) $stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string) $suffix) - 1)) . '-' . $suffix;
    }
}

function mg_ai_plan_find_reward_template_id(PDO $pdo, int $merchantId, ?string $publicId): ?int
{
    $publicId = strtolower(trim((string) $publicId));
    if ($publicId === '' || strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) return null;
    $stmt = $pdo->prepare("SELECT id FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_ai_plan_find_campaign(PDO $pdo, int $merchantId, ?string $publicId, bool $forUpdate = false): ?array
{
    $publicId = strtolower(trim((string) $publicId));
    if ($publicId === '' || strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) return null;
    $sql = 'SELECT * FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId, $merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_ai_plan_find_reward_template(PDO $pdo, int $merchantId, ?string $publicId, bool $forUpdate = false): ?array
{
    $publicId = strtolower(trim((string) $publicId));
    if ($publicId === '' || strlen($publicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $publicId)) return null;
    $sql = 'SELECT * FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId, $merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_ai_plan_item_owned(PDO $pdo, int $merchantId, string $itemPublicId, bool $forUpdate = false): array
{
    if (strlen($itemPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $itemPublicId)) {
        mg_fail('Invalid recommendation identifier.', 422);
    }
    $sql = "SELECT i.*, p.public_id plan_public_id, p.status plan_status, p.merchant_user_id, p.scope, p.summary plan_summary
            FROM ai_merchant_plan_items i
            INNER JOIN ai_merchant_plans p ON p.id = i.plan_id
            WHERE i.public_id = ? AND p.merchant_user_id = ?
            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemPublicId, $merchantId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($item)) mg_fail('AI recommendation not found.', 404);
    return $item;
}

function mg_ai_plan_update_parent_status(PDO $pdo, int $planId): void
{
    $stmt = $pdo->prepare('SELECT status,COUNT(*) total FROM ai_merchant_plan_items WHERE plan_id = ? GROUP BY status');
    $stmt->execute([$planId]);
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string) $row['status']] = (int) $row['total'];
    }
    $total = array_sum($counts);
    if ($total <= 0) return;

    $open = ($counts['recommended'] ?? 0) + ($counts['deferred'] ?? 0);
    $positive = ($counts['approved'] ?? 0) + ($counts['edited'] ?? 0) + ($counts['queued'] ?? 0) + ($counts['executed'] ?? 0);
    $rejected = $counts['rejected'] ?? 0;
    $failed = $counts['failed'] ?? 0;

    $status = 'review_ready';
    if ($rejected === $total) $status = 'rejected';
    elseif ($open === 0 && $positive === $total) $status = 'approved';
    elseif ($open === 0 && $positive > 0) $status = 'partially_approved';
    elseif ($failed > 0 && $positive === 0) $status = 'failed';

    $pdo->prepare('UPDATE ai_merchant_plans SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $planId]);
}

function mg_ai_plan_public_item(array $item, array $execution = []): array
{
    $payload = mg_ai_plan_json($item['suggested_payload_json'] ?? null);
    return [
        'id' => (string) $item['public_id'],
        'plan_id' => (string) ($item['plan_public_id'] ?? ''),
        'sequence_no' => (int) $item['sequence_no'],
        'action_key' => (string) $item['action_key'],
        'target_type' => (string) $item['target_type'],
        'target_reference' => $item['target_reference'] !== null ? (string) $item['target_reference'] : null,
        'risk_level' => (string) $item['risk_level'],
        'requires_approval' => (bool) $item['requires_approval'],
        'confidence' => $item['confidence'] !== null ? (float) $item['confidence'] : null,
        'title' => (string) $item['title'],
        'reason' => (string) ($item['reason'] ?? ''),
        'suggested_payload' => $payload,
        'status' => (string) $item['status'],
        'execution' => $execution,
    ];
}

function mg_ai_plan_record_review_event(PDO $pdo, int $merchantId, string $eventType, array $item, array $extra = []): string
{
    return mg_automation_record_event($pdo, $merchantId, $eventType, array_merge([
        'ai_plan_id' => (string) ($item['plan_public_id'] ?? ''),
        'ai_plan_item_id' => (string) $item['public_id'],
        'action_key' => (string) $item['action_key'],
        'title' => (string) $item['title'],
        'target_type' => (string) $item['target_type'],
        'target_reference' => (string) ($item['target_reference'] ?? ''),
        'risk_level' => (string) $item['risk_level'],
        'merchant_approval_required' => true,
        'guardrail_applied' => 'Claude produced a recommendation only. Merchant review is required before any draft, task, report, alert, or status change is created.',
    ], $extra), null, null);
}

function mg_ai_plan_create_campaign_draft(PDO $pdo, int $merchantId, array $item, array $payload): array
{
    $title = mg_ai_plan_short($payload['title'] ?? $item['title'], 180) ?: 'Claude campaign draft';
    $campaignType = (string) ($payload['campaign_type'] ?? 'newsletter_signup');
    if (!in_array($campaignType, ['newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer'], true)) {
        $campaignType = 'newsletter_signup';
    }
    $rewardTemplateId = mg_ai_plan_find_reward_template_id($pdo, $merchantId, (string) ($payload['reward_template_id'] ?? ''));
    $publicId = mg_merchant_uuid();
    $slug = mg_ai_plan_unique_campaign_slug($pdo, $merchantId, $title);
    $qrToken = $campaignType === 'qr_reward_drop' ? bin2hex(random_bytes(16)) : null;

    $stmt = $pdo->prepare('INSERT INTO campaigns
        (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,success_message,status,starts_at,ends_at,quantity_limit,per_user_limit,agent_discoverable,public_slug,qr_code_token,metadata_json,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,\'draft\',?,?,?,?,?,?,?,?,NOW(),NOW())');
    $stmt->execute([
        $publicId,
        $merchantId,
        $rewardTemplateId,
        $campaignType,
        $title,
        mg_ai_plan_short($payload['description'] ?? $item['reason'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['form_headline'] ?? $title, 180) ?: null,
        mg_ai_plan_short($payload['form_description'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['success_message'] ?? 'Thanks — your reward has been prepared for review.', 500) ?: null,
        mg_ai_plan_short($payload['starts_at'] ?? '', 40) ?: null,
        mg_ai_plan_short($payload['ends_at'] ?? '', 40) ?: null,
        isset($payload['quantity_limit']) && (int) $payload['quantity_limit'] > 0 ? (int) $payload['quantity_limit'] : null,
        max(1, (int) ($payload['per_user_limit'] ?? 1)),
        !empty($payload['agent_discoverable']) ? 1 : 0,
        $slug,
        $qrToken,
        json_encode(['source' => 'ai_merchant_plan', 'plan_item_id' => (string) $item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    return ['resource_type' => 'campaign', 'resource_id' => $publicId, 'url' => '/merchant-campaigns.php', 'status' => 'draft_created'];
}

function mg_ai_plan_update_campaign_draft(PDO $pdo, int $merchantId, array $item, array $payload): array
{
    $target = (string) ($item['target_reference'] ?? $payload['campaign_id'] ?? '');
    $campaign = mg_ai_plan_find_campaign($pdo, $merchantId, $target, true);
    if (!$campaign) throw new RuntimeException('Campaign target was not found.');
    if ((string) $campaign['status'] !== 'draft') throw new RuntimeException('Only draft campaigns can be updated by an AI recommendation adapter.');

    $title = mg_ai_plan_short($payload['title'] ?? $campaign['title'], 180) ?: (string) $campaign['title'];
    $slug = mg_ai_plan_unique_campaign_slug($pdo, $merchantId, $title);
    $stmt = $pdo->prepare('UPDATE campaigns SET title=?, description=?, form_headline=?, form_description=?, success_message=?, public_slug=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?');
    $stmt->execute([
        $title,
        mg_ai_plan_short($payload['description'] ?? $campaign['description'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['form_headline'] ?? $campaign['form_headline'] ?? $title, 180) ?: null,
        mg_ai_plan_short($payload['form_description'] ?? $campaign['form_description'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['success_message'] ?? $campaign['success_message'] ?? '', 500) ?: null,
        $slug,
        (int) $campaign['id'],
        $merchantId,
    ]);
    return ['resource_type' => 'campaign', 'resource_id' => (string) $campaign['public_id'], 'url' => '/merchant-campaigns.php', 'status' => 'draft_updated'];
}

function mg_ai_plan_create_reward_template_draft(PDO $pdo, int $merchantId, array $item, array $payload): array
{
    $title = mg_ai_plan_short($payload['title'] ?? $item['title'], 180) ?: 'Claude reward draft';
    $rewardType = (string) ($payload['reward_type'] ?? 'custom');
    if (!in_array($rewardType, ['dollar_credit','free_item','discount','perk_upgrade','event_reward','custom'], true)) $rewardType = 'custom';
    $valueType = (string) ($payload['value_type'] ?? ($rewardType === 'discount' ? 'percent' : ($rewardType === 'free_item' ? 'free_item' : 'fixed_amount')));
    if (!in_array($valueType, ['fixed_amount','percent','free_item','custom'], true)) $valueType = 'custom';
    $valuePercent = $valueType === 'percent' && is_numeric($payload['value_percent'] ?? null) ? max(0.0, min(100.0, (float) $payload['value_percent'])) : null;
    $valueAmountCents = $valueType === 'percent' ? 0 : mg_ai_plan_money_to_cents($payload['value_amount'] ?? $payload['value_amount_cents'] ?? 0);
    $expirationRule = (string) ($payload['expiration_rule'] ?? 'none');
    if (!in_array($expirationRule, ['none','after_issue','after_claim','fixed_date','event_date'], true)) $expirationRule = 'none';
    $publicId = mg_merchant_uuid();

    $stmt = $pdo->prepare('INSERT INTO reward_templates
        (public_id,merchant_user_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,redemption_instructions,expiration_rule,expiration_days,expires_at,quantity_limit,per_user_limit,agent_discoverable,agent_summary,agent_categories_json,agent_use_cases_json,agent_add_to_wallet_allowed,agent_gift_send_allowed,status,metadata_json,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'draft\',?,NOW(),NOW())');
    $stmt->execute([
        $publicId,
        $merchantId,
        $title,
        mg_ai_plan_short($payload['description'] ?? $item['reason'] ?? '', 2000) ?: null,
        $rewardType,
        $valueType,
        $valueAmountCents,
        $valuePercent,
        strtoupper(mg_ai_plan_short($payload['currency'] ?? 'USD', 3)) ?: 'USD',
        mg_ai_plan_short($payload['redemption_instructions'] ?? '', 2000) ?: null,
        $expirationRule,
        isset($payload['expiration_days']) && (int) $payload['expiration_days'] > 0 ? (int) $payload['expiration_days'] : null,
        mg_ai_plan_short($payload['expires_at'] ?? '', 40) ?: null,
        isset($payload['quantity_limit']) && (int) $payload['quantity_limit'] > 0 ? (int) $payload['quantity_limit'] : null,
        max(1, (int) ($payload['per_user_limit'] ?? 1)),
        !empty($payload['agent_discoverable']) ? 1 : 0,
        mg_ai_plan_short($payload['agent_summary'] ?? $item['reason'] ?? '', 500) ?: null,
        mg_ai_plan_csv_json($payload['agent_categories'] ?? ''),
        mg_ai_plan_csv_json($payload['agent_use_cases'] ?? ''),
        !empty($payload['agent_add_to_wallet_allowed']) ? 1 : 0,
        !empty($payload['agent_gift_send_allowed']) ? 1 : 0,
        json_encode(['source' => 'ai_merchant_plan', 'plan_item_id' => (string) $item['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    return ['resource_type' => 'reward_template', 'resource_id' => $publicId, 'url' => '/merchant-reward-templates.php', 'status' => 'draft_created'];
}

function mg_ai_plan_update_reward_template_draft(PDO $pdo, int $merchantId, array $item, array $payload): array
{
    $target = (string) ($item['target_reference'] ?? $payload['reward_template_id'] ?? '');
    $template = mg_ai_plan_find_reward_template($pdo, $merchantId, $target, true);
    if (!$template) throw new RuntimeException('Reward template target was not found.');
    if ((string) $template['status'] !== 'draft') throw new RuntimeException('Only draft reward templates can be updated by an AI recommendation adapter.');

    $title = mg_ai_plan_short($payload['title'] ?? $template['title'], 180) ?: (string) $template['title'];
    $stmt = $pdo->prepare('UPDATE reward_templates SET title=?, description=?, redemption_instructions=?, agent_summary=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?');
    $stmt->execute([
        $title,
        mg_ai_plan_short($payload['description'] ?? $template['description'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['redemption_instructions'] ?? $template['redemption_instructions'] ?? '', 2000) ?: null,
        mg_ai_plan_short($payload['agent_summary'] ?? $item['reason'] ?? '', 500) ?: null,
        (int) $template['id'],
        $merchantId,
    ]);
    return ['resource_type' => 'reward_template', 'resource_id' => (string) $template['public_id'], 'url' => '/merchant-reward-templates.php', 'status' => 'draft_updated'];
}

function mg_ai_plan_change_campaign_status(PDO $pdo, int $merchantId, array $item, array $payload, string $status): array
{
    $target = (string) ($item['target_reference'] ?? $payload['campaign_id'] ?? '');
    $campaign = mg_ai_plan_find_campaign($pdo, $merchantId, $target, true);
    if (!$campaign) throw new RuntimeException('Campaign target was not found.');
    if ((string) $campaign['status'] === 'archived') throw new RuntimeException('Archived campaigns cannot be changed by this adapter.');
    if ($status === 'active' && empty($campaign['reward_template_id'])) throw new RuntimeException('Campaign requires an attached reward template before resume.');
    $pdo->prepare('UPDATE campaigns SET status=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?')->execute([$status, (int) $campaign['id'], $merchantId]);
    return ['resource_type' => 'campaign', 'resource_id' => (string) $campaign['public_id'], 'url' => '/merchant-campaigns.php', 'status' => $status === 'active' ? 'campaign_resumed' : 'campaign_paused'];
}

function mg_ai_plan_create_event_action(PDO $pdo, int $merchantId, array $item, array $payload, string $eventType, string $status, string $url): array
{
    $eventId = mg_ai_plan_record_review_event($pdo, $merchantId, $eventType, $item, [
        'status' => $status,
        'payload' => $payload,
        'recommended_next_action' => mg_ai_plan_short($payload['recommended_next_action'] ?? $item['title'], 500),
    ]);
    return ['resource_type' => 'campaign_event', 'resource_id' => $eventId, 'url' => $url, 'status' => $status];
}

function mg_ai_plan_create_report_snapshot(PDO $pdo, int $merchantId, int $actorId, array $item, array $payload): array
{
    $reportType = (string) ($payload['report_type'] ?? 'overview');
    if (!in_array($reportType, ['overview','campaigns','products','locations','pppm_funnel','engagement','forecast'], true)) $reportType = 'overview';
    $publicId = mg_merchant_uuid();
    $name = mg_ai_plan_short($payload['name'] ?? $item['title'], 180) ?: 'Claude merchant report';
    $dateRange = mg_ai_plan_short($payload['date_range_key'] ?? 'last_30_days', 40) ?: 'last_30_days';
    $filters = ['source' => 'ai_merchant_plan', 'plan_item_id' => (string) $item['public_id'], 'scope' => (string) ($payload['scope'] ?? $item['target_type'] ?? 'overview')];
    $pdo->prepare('INSERT INTO merchant_saved_reports (public_id,merchant_user_id,name,report_type,date_range_key,filters_json,columns_json,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,\'active\',?,NOW(),NOW())')
        ->execute([$publicId, $merchantId, $name, $reportType, $dateRange, json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), null, $actorId]);
    return ['resource_type' => 'saved_report', 'resource_id' => $publicId, 'url' => '/merchant-intelligence.php', 'status' => 'report_saved'];
}

function mg_ai_plan_execute_item(PDO $pdo, int $merchantId, int $actorId, array $item): array
{
    $payload = mg_ai_plan_json($item['suggested_payload_json'] ?? null);
    return match ((string) $item['action_key']) {
        'create_campaign_draft' => mg_ai_plan_create_campaign_draft($pdo, $merchantId, $item, $payload),
        'update_campaign_draft' => mg_ai_plan_update_campaign_draft($pdo, $merchantId, $item, $payload),
        'pause_campaign' => mg_ai_plan_change_campaign_status($pdo, $merchantId, $item, $payload, 'paused'),
        'resume_campaign' => mg_ai_plan_change_campaign_status($pdo, $merchantId, $item, $payload, 'active'),
        'create_reward_template_draft' => mg_ai_plan_create_reward_template_draft($pdo, $merchantId, $item, $payload),
        'update_reward_template_draft' => mg_ai_plan_update_reward_template_draft($pdo, $merchantId, $item, $payload),
        'create_crm_followup_task' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'crm.followup.created', 'followup_created', '/merchant-followups.php'),
        'create_message_draft' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'crm.agent.message.draft.created', 'message_draft_created', '/merchant-agent-messages.php'),
        'create_merchant_alert' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.alert.created', 'alert_created', '/merchant-agent-monitor.php'),
        'create_report_snapshot' => mg_ai_plan_create_report_snapshot($pdo, $merchantId, $actorId, $item, $payload),
        'recommend_package_upgrade' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.package_upgrade', 'recommendation_recorded', '/account-subscriptions.php'),
        'recommend_location_fix' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.location_fix', 'recommendation_recorded', '/merchant-locations.php'),
        'recommend_api_integration' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.api_integration', 'recommendation_recorded', '/merchant-distribution.php?developer_api=1'),
        'recommend_claim_review' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.claim_review', 'recommendation_recorded', '/merchant-claims.php'),
        'recommend_reward_optimization' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.reward_optimization', 'recommendation_recorded', '/merchant-reward-templates.php'),
        'recommend_campaign_optimization' => mg_ai_plan_create_event_action($pdo, $merchantId, $item, $payload, 'merchant.ai.recommendation.campaign_optimization', 'recommendation_recorded', '/merchant-campaigns.php'),
        default => throw new RuntimeException('Unsupported AI recommendation action.'),
    };
}

function mg_ai_plan_review_item(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int) $user['id'];
    $actorId = $merchantId;
    $itemPublicId = strtolower(trim((string) ($input['item_id'] ?? '')));
    $decision = strtolower(trim((string) ($input['decision'] ?? 'approve')));
    if (!in_array($decision, ['approve','defer','reject'], true)) mg_fail('Invalid AI recommendation decision.', 422);
    $note = mg_ai_plan_short($input['note'] ?? '', 1000);

    $pdo->beginTransaction();
    try {
        $item = mg_ai_plan_item_owned($pdo, $merchantId, $itemPublicId, true);
        if (!in_array((string) $item['status'], ['recommended','deferred','failed'], true)) {
            throw new RuntimeException('Recommendation is not available for review.');
        }

        $execution = [];
        if ($decision === 'reject') {
            $pdo->prepare('UPDATE ai_merchant_plan_items SET status=\'rejected\', updated_at=NOW() WHERE id=?')->execute([(int) $item['id']]);
            mg_ai_plan_record_review_event($pdo, $merchantId, 'merchant.ai_plan_item.rejected', $item, ['status' => 'rejected', 'merchant_note' => $note, 'decided_by_user_id' => $actorId]);
        } elseif ($decision === 'defer') {
            $pdo->prepare('UPDATE ai_merchant_plan_items SET status=\'deferred\', updated_at=NOW() WHERE id=?')->execute([(int) $item['id']]);
            mg_ai_plan_record_review_event($pdo, $merchantId, 'merchant.ai_plan_item.deferred', $item, ['status' => 'deferred', 'merchant_note' => $note, 'decided_by_user_id' => $actorId]);
        } else {
            mg_ai_plan_record_review_event($pdo, $merchantId, 'merchant.ai_plan_item.approved', $item, ['status' => 'approved', 'merchant_note' => $note, 'decided_by_user_id' => $actorId]);
            $execution = mg_ai_plan_execute_item($pdo, $merchantId, $actorId, $item);
            $pdo->prepare('UPDATE ai_merchant_plan_items SET status=\'executed\', updated_at=NOW() WHERE id=?')->execute([(int) $item['id']]);
            mg_ai_plan_record_review_event($pdo, $merchantId, 'merchant.ai_plan_item.executed', $item, ['status' => 'executed', 'merchant_note' => $note, 'execution' => $execution, 'decided_by_user_id' => $actorId]);
        }

        mg_ai_plan_update_parent_status($pdo, (int) $item['plan_id']);
        $fresh = mg_ai_plan_item_owned($pdo, $merchantId, $itemPublicId, false);
        mg_audit('merchant.ai_plan_item_' . $decision, 'ai_merchant_plan_item', [
            'plan_id' => (string) ($item['plan_public_id'] ?? ''),
            'item_id' => $itemPublicId,
            'action_key' => (string) $item['action_key'],
            'execution' => $execution,
        ], $merchantId);
        $pdo->commit();
        return mg_ai_plan_public_item($fresh, $execution);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.ai_plan_item_review_failed', 'AI plan item review failed.', [
            'exception_class' => $error::class,
            'decision' => $decision,
            'item_id' => $itemPublicId,
        ], $merchantId);
        mg_fail('Unable to review AI recommendation: ' . $error->getMessage(), 500);
    }
}
