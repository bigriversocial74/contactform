<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_schema.php';
require_once dirname(__DIR__) . '/merchant/_merchant.php';
require_once dirname(__DIR__) . '/stamps/_stamps.php';

function mg_store_reward_required_tables(PDO $pdo): bool
{
    $required = ['reward_templates','campaigns','wallet_items','campaign_events','mg_store_sessions','mg_store_session_events','mg_customer_store_history'];
    return mg_store_canvas_missing_tables($pdo, $required) === [];
}

function mg_store_reward_require_schema(PDO $pdo): void
{
    $required = ['reward_templates','campaigns','wallet_items','campaign_events','mg_store_sessions','mg_store_session_events','mg_customer_store_history'];
    mg_store_canvas_require_tables($pdo, $required, 'Store Canvas reward delivery');
}

function mg_store_reward_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : mg_merchant_uuid();
}

function mg_store_reward_optional_text(mixed $value, int $max): string
{
    $text = trim((string)$value);
    if ($text !== '' && mb_strlen($text) > $max) throw new InvalidArgumentException('Reward note is too long.');
    return $text;
}

function mg_store_reward_expiry(array $template, mixed $expirationDays = null, mixed $expiresAt = null): ?string
{
    $days = trim((string)$expirationDays);
    if ($days !== '') return gmdate('Y-m-d H:i:s', time() + (max(1, min(365, (int)$days)) * 86400));
    $fixed = trim((string)$expiresAt);
    if ($fixed !== '') {
        $timestamp = strtotime($fixed);
        if ($timestamp === false || $timestamp <= time()) throw new InvalidArgumentException('Expiration must be a future date.');
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($template['expires_at'])) return (string)$template['expires_at'];
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return gmdate('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    return null;
}

function mg_store_reward_template_public(array $row): array
{
    $limit = $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'];
    $issued = (int)($row['issued_count'] ?? 0);
    return [
        'id'=>(string)$row['public_id'],
        'title'=>(string)$row['title'],
        'description'=>(string)($row['description'] ?? ''),
        'reward_type'=>(string)$row['reward_type'],
        'value_type'=>(string)$row['value_type'],
        'value_amount_cents'=>(int)($row['value_amount_cents'] ?? 0),
        'value_percent'=>$row['value_percent'] === null ? null : (float)$row['value_percent'],
        'currency'=>(string)($row['currency'] ?? 'USD'),
        'redemption_instructions'=>(string)($row['redemption_instructions'] ?? ''),
        'expiration_rule'=>(string)($row['expiration_rule'] ?? 'none'),
        'expiration_days'=>$row['expiration_days'] === null ? null : (int)$row['expiration_days'],
        'expires_at'=>$row['expires_at'] ?? null,
        'quantity_limit'=>$limit,
        'issued_count'=>$issued,
        'available'=>$limit === null || $issued < $limit,
    ];
}

function mg_store_reward_campaign_public(array $row): array
{
    $limit = $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'];
    $issued = (int)($row['issued_count'] ?? 0);
    return [
        'id'=>(string)$row['public_id'],
        'title'=>(string)$row['title'],
        'campaign_type'=>(string)$row['campaign_type'],
        'reward_template_id'=>$row['reward_template_public_id'] !== null ? (string)$row['reward_template_public_id'] : null,
        'reward_template_title'=>$row['reward_template_title'] !== null ? (string)$row['reward_template_title'] : null,
        'quantity_limit'=>$limit,
        'issued_count'=>$issued,
        'available'=>$limit === null || $issued < $limit,
        'ends_at'=>$row['ends_at'] ?? null,
    ];
}

function mg_store_reward_options(PDO $pdo, int $merchantUserId): array
{
    mg_store_reward_require_schema($pdo);
    $campaignStmt = $pdo->prepare("SELECT c.*,rt.public_id reward_template_public_id,rt.title reward_template_title FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.merchant_user_id=? AND c.status='active' AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) ORDER BY c.updated_at DESC,c.id DESC LIMIT 100");
    $campaignStmt->execute([$merchantUserId]);
    $templateStmt = $pdo->prepare("SELECT * FROM reward_templates WHERE merchant_user_id=? AND status='active' ORDER BY updated_at DESC,id DESC LIMIT 100");
    $templateStmt->execute([$merchantUserId]);
    $campaigns = array_values(array_filter(array_map('mg_store_reward_campaign_public', $campaignStmt->fetchAll(PDO::FETCH_ASSOC)), static fn(array $row): bool => !empty($row['available'])));
    $templates = array_values(array_filter(array_map('mg_store_reward_template_public', $templateStmt->fetchAll(PDO::FETCH_ASSOC)), static fn(array $row): bool => !empty($row['available'])));
    return ['schema_ready'=>true,'campaigns'=>$campaigns,'templates'=>$templates,'can_send_reward'=>$campaigns !== [] && $templates !== []];
}

function mg_store_reward_load_active_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $stmt = $pdo->prepare("SELECT s.*,cp.display_name customer_name,u.email customer_email FROM mg_store_sessions s INNER JOIN users u ON u.id=s.customer_user_id LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id WHERE s.public_id=? AND s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL LIMIT 1 FOR UPDATE");
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Active customer session is not available.');
    return $session;
}

function mg_store_reward_issue(PDO $pdo, array $merchantUser, string $sessionPublicId, string $campaignPublicId, ?string $templatePublicId, string $note = '', mixed $expirationDays = null, mixed $expiresAt = null, string $idempotencyKey = ''): array
{
    mg_store_reward_require_schema($pdo);
    $merchantId = (int)$merchantUser['id'];
    $campaignPublicId = mg_store_safe_public_id($campaignPublicId, 'Campaign');
    $templatePublicId = $templatePublicId !== null && trim($templatePublicId) !== '' ? mg_store_safe_public_id($templatePublicId, 'Reward template') : null;
    $note = mg_store_reward_optional_text($note, 1000);
    $idempotencyKey = trim($idempotencyKey);
    if ($idempotencyKey === '') $idempotencyKey = substr('store-reward:' . hash('sha256', $merchantId . '|' . $sessionPublicId . '|' . $campaignPublicId . '|' . ($templatePublicId ?? '') . '|' . $note . '|' . gmdate('YmdHi')), 0, 190);
    if (mb_strlen($idempotencyKey) > 190) throw new InvalidArgumentException('Invalid idempotency key.');
    mg_merchant_ensure_workspace($pdo, $merchantUser);

    $pdo->beginTransaction();
    try {
        $session = mg_store_reward_load_active_session($pdo, $merchantId, $sessionPublicId);
        $customerUserId = (int)$session['customer_user_id'];
        $campaignStmt = $pdo->prepare("SELECT c.*,rt.public_id attached_template_public_id FROM campaigns c LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.public_id=? AND c.merchant_user_id=? AND c.status='active' LIMIT 1 FOR UPDATE");
        $campaignStmt->execute([$campaignPublicId, $merchantId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) throw new RuntimeException('Active campaign not found.');
        if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > time()) throw new RuntimeException('Campaign has not started yet.');
        if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < time()) throw new RuntimeException('Campaign has already ended.');
        if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) throw new RuntimeException('Campaign reward limit has been reached.');
        if ($templatePublicId === null) $templatePublicId = (string)($campaign['attached_template_public_id'] ?? '');
        if ($templatePublicId === '') throw new RuntimeException('Select a reward template.');

        $templateStmt = $pdo->prepare("SELECT * FROM reward_templates WHERE public_id=? AND merchant_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $templateStmt->execute([$templatePublicId, $merchantId]);
        $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) throw new RuntimeException('Active reward template not found.');
        if ($template['quantity_limit'] !== null && (int)$template['issued_count'] >= (int)$template['quantity_limit']) throw new RuntimeException('Reward template limit has been reached.');

        $perUserLimit = max(1, (int)($campaign['per_user_limit'] ?? $template['per_user_limit'] ?? 1));
        $dupeStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND user_id=? AND campaign_id=? AND reward_template_id=? AND status IN ('issued','viewed','claimed','redeemed') AND (expires_at IS NULL OR expires_at>NOW())");
        $dupeStmt->execute([$merchantId, $customerUserId, (int)$campaign['id'], (int)$template['id']]);
        if ((int)$dupeStmt->fetchColumn() >= $perUserLimit) throw new RuntimeException('This customer already has the active reward allowed for this campaign.');

        $existingStmt = $pdo->prepare("SELECT public_id FROM wallet_items WHERE merchant_user_id=? AND source_type='manual_send' AND source_id=? AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.store_canvas_idempotency_key'))=? LIMIT 1");
        $existingStmt->execute([$merchantId, $sessionPublicId, $idempotencyKey]);
        $existing = (string)($existingStmt->fetchColumn() ?: '');
        if ($existing !== '') {
            $pdo->commit();
            return ['wallet_item_id'=>$existing,'duplicate'=>true,'source_system'=>'store_canvas'];
        }

        $packageContext = mg_user_package_context($pdo, $merchantUser);
        $stampLimit = mg_package_limit_value($packageContext, 'monthly_stamps_included');
        if ($stampLimit !== null && $stampLimit !== '') {
            $stampUsage = $pdo->prepare("SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND status<>'cancelled' AND issued_at>=?");
            $stampUsage->execute([$merchantId, gmdate('Y-m-01 00:00:00')]);
            if ((int)$stampUsage->fetchColumn() >= max(0, (int)$stampLimit) && !mg_package_limit_value($packageContext, 'stamp_overage_enabled')) throw new RuntimeException('Monthly stamp limit reached. Upgrade your package or enable stamp overage.');
        }

        $expires = mg_store_reward_expiry($template, $expirationDays, $expiresAt);
        $walletPublicId = mg_store_reward_uuid();
        $stampLedger = mg_stamp_debit_send($pdo, $merchantId, $merchantId, 'direct_reward_send', $idempotencyKey, [
            'source_type' => 'store_canvas_reward',
            'source_id' => $walletPublicId,
            'reference' => (string)$campaign['public_id'],
            'reason_code' => 'store_canvas_reward_issue',
            'note' => 'Store Canvas reward sent: ' . (string)$template['title'],
            'metadata' => [
                'campaign_id' => (string)$campaign['public_id'],
                'reward_template_id' => (string)$template['public_id'],
                'store_session_id' => $sessionPublicId,
                'customer_user_id' => $customerUserId,
                'source_channel' => 'merchant_canvas_reward',
            ],
        ]);
        $merchantLabel = 'Merchant';
        try {
            $labelStmt = $pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');
            $labelStmt->execute([$merchantId]);
            $merchantLabel = trim((string)($labelStmt->fetchColumn() ?: 'Merchant')) ?: 'Merchant';
        } catch (Throwable) {}
        $metadata = [
            'source_system' => 'store_canvas',
            'source_channel' => 'merchant_canvas_reward',
            'source_type' => 'campaign_reward',
            'source_label' => 'Store Canvas Reward',
            'source_detail' => (string)$campaign['title'],
            'source_reference' => $sessionPublicId,
            'store_session_id' => $sessionPublicId,
            'customer_user_id' => $customerUserId,
            'merchant_user_id' => $merchantId,
            'campaign_id' => (string)$campaign['public_id'],
            'campaign_title' => (string)$campaign['title'],
            'campaign_type' => (string)$campaign['campaign_type'],
            'reward_template_id' => (string)$template['public_id'],
            'reward_template_title' => (string)$template['title'],
            'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
            'note' => $note,
            'store_canvas_idempotency_key' => $idempotencyKey,
            'posts' => [
                ['type'=>'message','title'=>'Reward from ' . $merchantLabel,'body'=>$note !== '' ? $note : 'A merchant sent you a reward from the Store Canvas.','meta'=>'Store Canvas'],
                ['type'=>'offer','title'=>(string)$template['title'],'body'=>trim((string)($template['redemption_instructions'] ?? '')) ?: 'Present this reward to the merchant when you are ready to redeem.','meta'=>(string)$campaign['title']],
            ],
        ];
        $insert = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,pppm_item_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
        $insert->execute([$walletPublicId,$customerUserId,$merchantId,(int)$template['id'],(int)$campaign['id'],null,'manual_send',$sessionPublicId,'issued',(int)$template['value_amount_cents'],(string)$template['currency'],(string)$template['title'],json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$expires]);
        $walletDbId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$template['id']]);
        $pdo->prepare('UPDATE campaigns SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$campaign['id']]);
        $eventContext = $metadata + ['wallet_item_id'=>$walletPublicId,'wallet_item_db_id'=>$walletDbId];
        $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,NULL,?,?,NOW())');
        $event->execute([mg_store_reward_uuid(), $merchantId, (int)$campaign['id'], $walletDbId, 'store_canvas.reward_issued', json_encode($eventContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        mg_store_log_event($pdo, $session, 'reward_sent', 'Merchant sent reward', ['wallet_item_id'=>$walletPublicId,'campaign_id'=>(string)$campaign['public_id'],'reward_template_id'=>(string)$template['public_id'],'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,'source_system'=>'store_canvas']);
        mg_store_log_event($pdo, $session, 'received_reward', 'Customer received Store Canvas reward', ['wallet_item_id'=>$walletPublicId,'campaign_id'=>(string)$campaign['public_id'],'reward_template_id'=>(string)$template['public_id'],'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,'source_system'=>'store_canvas']);
        $notificationId = mg_create_notification($pdo, $customerUserId, 'campaign_reward', 'You received a reward from ' . $merchantLabel, (string)$template['title'], '/inbox.php?item=' . rawurlencode('wallet-' . $walletPublicId), ['actor_user_id'=>$merchantId,'event_key'=>'reward.store_canvas.' . strtolower($walletPublicId),'merchant_user_id'=>$merchantId,'store_session_id'=>$sessionPublicId,'wallet_item_id'=>$walletPublicId,'campaign_id'=>(string)$campaign['public_id'],'reward_template_id'=>(string)$template['public_id'],'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,'source_system'=>'store_canvas','source_channel'=>'merchant_canvas_reward','source_label'=>'Store Canvas Reward']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    mg_event('store_canvas.reward_sent', ['wallet_item_id'=>$walletPublicId,'session_id'=>$sessionPublicId,'campaign_id'=>(string)$campaign['public_id'],'reward_template_id'=>(string)$template['public_id'],'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null], $merchantId);
    return ['wallet_item_id'=>$walletPublicId,'campaign_id'=>(string)$campaign['public_id'],'reward_template_id'=>(string)$template['public_id'],'title'=>(string)$template['title'],'expires_at'=>$expires,'notification_id'=>$notificationId ?: null,'stamp_ledger'=>$stampLedger,'duplicate'=>false,'source_system'=>'store_canvas','source_channel'=>'merchant_canvas_reward'];
}
