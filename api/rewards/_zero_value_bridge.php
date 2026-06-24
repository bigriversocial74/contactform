<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

/**
 * Stage 12 bridge: campaign/API/agent rewards are zero-dollar PPPM-backed
 * Microgifts. wallet_items remains attribution/reporting; PPPM + Action Center
 * remain the user-facing lifecycle authority.
 */

function mg_zero_reward_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_zero_reward_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return (bool) $cache[$key];
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    $cache[$key] = (bool) $stmt->fetch();
    return (bool) $cache[$key];
}

function mg_zero_reward_safe_reference(string $prefix, string $value): string
{
    $value = trim($value);
    if ($value === '') $value = bin2hex(random_bytes(8));
    return substr($prefix . ':' . preg_replace('/[^a-zA-Z0-9_.:-]/', '-', $value), 0, 190);
}

function mg_zero_reward_pppm_source(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pppm_sources WHERE owner_user_id=? AND source_type='distribution' AND provider='microgifter' AND status='active' LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) return $source;

    $sourceId = mg_pppm_uuid();
    $pdo->prepare("INSERT INTO pppm_sources (public_id,owner_user_id,source_type,provider,name,status,created_at,updated_at) VALUES (?,?, 'distribution', 'microgifter', 'Microgifter Rewards Distribution', 'active', NOW(), NOW())")
        ->execute([$sourceId, $merchantUserId]);
    $stmt = $pdo->prepare('SELECT * FROM pppm_sources WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$sourceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_zero_reward_pppm_source_event(PDO $pdo, array $source, string $externalEventId, string $eventType, array $payload): int
{
    $stmt = $pdo->prepare('SELECT id FROM pppm_source_events WHERE source_id=? AND external_event_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int) $source['id'], $externalEventId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id > 0) return $id;

    $json = mg_zero_reward_json($payload);
    $pdo->prepare("INSERT INTO pppm_source_events (public_id,source_id,external_event_id,event_type,payload_json,payload_hash,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?, 'validated', NOW(), NOW(), NOW())")
        ->execute([mg_pppm_uuid(), (int) $source['id'], $externalEventId, $eventType, $json, hash('sha256', $json)]);
    return (int) $pdo->lastInsertId();
}

function mg_zero_reward_pppm_request(PDO $pdo, array $source, int $sourceEventId, array $input): array
{
    $sourceReference = (string) $input['source_reference'];
    $stmt = $pdo->prepare('SELECT * FROM pppm_issuance_requests WHERE source_id=? AND source_reference=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int) $source['id'], $sourceReference]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($request) return $request;

    $metadata = [
        'zero_value_reward' => true,
        'source_type' => (string) ($input['source_type'] ?? 'campaign_reward'),
        'source_reference' => $sourceReference,
        'campaign_id' => $input['campaign_public_id'] ?? null,
        'reward_template_id' => $input['reward_template_public_id'] ?? null,
        'wallet_item_id' => $input['wallet_item_public_id'] ?? null,
    ];
    $requestId = mg_pppm_uuid();
    $pdo->prepare("INSERT INTO pppm_issuance_requests (public_id,source_id,source_event_id,issuer_user_id,merchant_user_id,source_reference,source_line_reference,item_type,funding_type,quantity,unit_value_cents,currency,recipient_user_id,recipient_external_id,recipient_name,title,description,terms_snapshot_json,metadata_json,status,issued_count,requested_at,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'issued', 1, NOW(), NOW(), NOW(), NOW())")
        ->execute([
            $requestId,
            (int) $source['id'],
            $sourceEventId,
            (int) $input['merchant_user_id'],
            (int) $input['merchant_user_id'],
            $sourceReference,
            (string) ($input['source_line_reference'] ?? $sourceReference),
            'reward',
            'merchant_funded',
            1,
            0,
            (string) ($input['currency'] ?? 'USD'),
            !empty($input['recipient_user_id']) ? (int) $input['recipient_user_id'] : null,
            $input['recipient_external_id'] ?? null,
            $input['recipient_name'] ?? null,
            (string) $input['title'],
            $input['description'] ?? null,
            isset($input['terms']) && is_array($input['terms']) ? mg_zero_reward_json($input['terms']) : null,
            mg_zero_reward_json($metadata),
        ]);
    $stmt = $pdo->prepare('SELECT * FROM pppm_issuance_requests WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$requestId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_zero_reward_template_version(PDO $pdo, int $merchantUserId, array $input): array
{
    $sourceKey = (string) ($input['reward_template_public_id'] ?? $input['source_reference'] ?? bin2hex(random_bytes(6)));
    $slug = substr('zero-reward-' . substr(hash('sha256', $merchantUserId . '|' . $sourceKey), 0, 40), 0, 190);
    $stmt = $pdo->prepare('SELECT t.*,v.public_id AS version_public_id,v.id AS version_id FROM microgift_templates t LEFT JOIN microgift_template_versions v ON v.id=t.active_version_id WHERE t.owner_user_id=? AND t.slug=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$merchantUserId, $slug]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($template && !empty($template['version_id'])) return $template;

    if (!$template) {
        $templatePublicId = mg_microgift_uuid();
        $pdo->prepare("INSERT INTO microgift_templates (public_id,owner_type,owner_user_id,owner_reference_id,name,slug,description,gift_type,status,visibility,default_currency,created_by_user_id,created_at,updated_at) VALUES (?, 'merchant', ?, NULL, ?, ?, ?, 'reward', 'active', 'unlisted', ?, ?, NOW(), NOW())")
            ->execute([
                $templatePublicId,
                $merchantUserId,
                (string) $input['title'],
                $slug,
                $input['description'] ?? null,
                (string) ($input['currency'] ?? 'USD'),
                $merchantUserId,
            ]);
        $templateId = (int) $pdo->lastInsertId();
    } else {
        $templateId = (int) $template['id'];
        $templatePublicId = (string) $template['public_id'];
    }

    $versionPublicId = mg_microgift_uuid();
    $claimPolicy = ['mode' => 'purchaser_owned', 'zero_value_reward' => true];
    $redemptionPolicy = [
        'mode' => 'merchant_presented',
        'zero_value_reward' => true,
        'instructions' => (string) ($input['redemption_instructions'] ?? ''),
    ];
    $expirationPolicy = [];
    if (!empty($input['expires_at'])) $expirationPolicy['expires_at'] = (string) $input['expires_at'];

    $pdo->prepare("INSERT INTO microgift_template_versions (public_id,template_id,version_number,status,title,description,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,future_demand_metadata_json,created_by_user_id,published_at,published_by_user_id,created_at,updated_at) VALUES (?,?,1,'published',?,?,?,?,NULL,NULL,'purchaser',?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())")
        ->execute([
            $versionPublicId,
            $templateId,
            (string) $input['title'],
            $input['description'] ?? null,
            (string) ($input['currency'] ?? 'USD'),
            0,
            mg_zero_reward_json($claimPolicy),
            mg_zero_reward_json($redemptionPolicy),
            mg_zero_reward_json([]),
            mg_zero_reward_json($expirationPolicy),
            mg_zero_reward_json((array) ($input['terms'] ?? [])),
            mg_zero_reward_json(['source' => (string) ($input['source_type'] ?? 'campaign_reward')]),
            $merchantUserId,
            $merchantUserId,
        ]);
    $versionId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE microgift_templates SET active_version_id=?,status='active',updated_at=NOW() WHERE id=?")->execute([$versionId, $templateId]);

    return [
        'id' => $templateId,
        'public_id' => $templatePublicId,
        'version_id' => $versionId,
        'version_public_id' => $versionPublicId,
    ];
}

function mg_zero_reward_attach_wallet(PDO $pdo, int $walletItemDbId, int $pppmItemDbId, array $bridge): void
{
    if ($walletItemDbId < 1) return;
    $stmt = $pdo->prepare('SELECT metadata_json FROM wallet_items WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$walletItemDbId]);
    $raw = (string) ($stmt->fetchColumn() ?: '');
    $metadata = [];
    if ($raw !== '') {
        try { $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); if (is_array($decoded)) $metadata = $decoded; } catch (Throwable) {}
    }
    $metadata['pppm_item_id'] = $bridge['pppm_item_id'] ?? null;
    $metadata['microgift_instance_id'] = $bridge['microgift_instance_id'] ?? null;
    $metadata['action_item_id'] = $bridge['action_item_id'] ?? null;
    $metadata['zero_value_pppm_bridge'] = true;

    if (mg_zero_reward_column_exists($pdo, 'wallet_items', 'pppm_item_id')) {
        $pdo->prepare('UPDATE wallet_items SET pppm_item_id=?, metadata_json=?, updated_at=NOW() WHERE id=?')
            ->execute([$pppmItemDbId, mg_zero_reward_json($metadata), $walletItemDbId]);
    } else {
        $pdo->prepare('UPDATE wallet_items SET metadata_json=?, updated_at=NOW() WHERE id=?')
            ->execute([mg_zero_reward_json($metadata), $walletItemDbId]);
    }
}

function mg_zero_reward_project_pppm_item(PDO $pdo, int $pppmItemDbId, array $input): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$pppmItemDbId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new RuntimeException('PPPM item not found for reward bridge.');

    $recipientUserId = !empty($input['recipient_user_id']) ? (int) $input['recipient_user_id'] : (int) ($item['recipient_user_id'] ?? 0);
    if ($recipientUserId < 1) {
        return ['pppm_item_id' => (string) $item['public_id'], 'microgift_instance_id' => null, 'action_item_id' => null, 'pending_account_link' => true];
    }

    $merchantUserId = (int) ($input['merchant_user_id'] ?? $item['merchant_user_id'] ?? $item['issuer_user_id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant context is required for reward bridge.');

    $template = mg_zero_reward_template_version($pdo, $merchantUserId, [
        'reward_template_public_id' => $input['reward_template_public_id'] ?? null,
        'source_reference' => $input['source_reference'] ?? (string) $item['source_reference'],
        'source_type' => $input['source_type'] ?? 'zero_reward',
        'title' => (string) ($input['title'] ?? $item['title_snapshot'] ?? 'Microgifter reward'),
        'description' => $input['description'] ?? $item['description_snapshot'] ?? null,
        'currency' => (string) ($input['currency'] ?? $item['currency_snapshot'] ?? 'USD'),
        'expires_at' => $input['expires_at'] ?? null,
        'redemption_instructions' => $input['redemption_instructions'] ?? null,
        'terms' => (array) ($input['terms'] ?? []),
    ]);

    $instanceStmt = $pdo->prepare('SELECT * FROM microgift_instances WHERE pppm_item_id=? LIMIT 1 FOR UPDATE');
    $instanceStmt->execute([$pppmItemDbId]);
    $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$instance) {
        $instancePublicId = mg_microgift_uuid();
        $sourceReference = mg_zero_reward_safe_reference('zero-reward', (string) ($input['source_reference'] ?? $item['source_reference'] ?? $item['public_id']));
        $idempotencyKey = substr('zero-reward:' . hash('sha256', $sourceReference . '|' . (string) $item['public_id']), 0, 190);
        $metadata = [
            'zero_value_reward' => true,
            'pppm_item_id' => (string) $item['public_id'],
            'source_type' => (string) ($input['source_type'] ?? 'zero_reward'),
            'source_reference' => $sourceReference,
            'wallet_item_id' => $input['wallet_item_public_id'] ?? null,
            'campaign_id' => $input['campaign_public_id'] ?? null,
            'reward_template_id' => $input['reward_template_public_id'] ?? null,
            'display_value_cents' => (int) ($input['display_value_cents'] ?? 0),
        ];
        $pdo->prepare("INSERT INTO microgift_instances (public_id,template_id,template_version_id,pppm_item_id,status,source_type,source_reference,idempotency_key,issuer_user_id,owner_user_id,recipient_user_id,recipient_reference,commerce_order_item_id,legacy_gift_id,title_snapshot,description_snapshot,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?, 'claim_pending', 'merchant', ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, 0, NULL, NULL, 'purchaser', ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())")
            ->execute([
                $instancePublicId,
                (int) $template['id'],
                (int) $template['version_id'],
                $pppmItemDbId,
                $sourceReference,
                $idempotencyKey,
                $merchantUserId,
                $recipientUserId,
                $recipientUserId,
                $input['recipient_external_id'] ?? $item['recipient_external_id'] ?? null,
                (string) ($input['title'] ?? $item['title_snapshot'] ?? 'Microgifter reward'),
                $input['description'] ?? $item['description_snapshot'] ?? null,
                (string) ($input['currency'] ?? $item['currency_snapshot'] ?? 'USD'),
                mg_zero_reward_json(['mode' => 'purchaser_owned', 'zero_value_reward' => true]),
                mg_zero_reward_json(['mode' => 'merchant_presented', 'zero_value_reward' => true, 'instructions' => (string) ($input['redemption_instructions'] ?? '')]),
                mg_zero_reward_json([]),
                mg_zero_reward_json(!empty($input['expires_at']) ? ['expires_at' => (string) $input['expires_at']] : []),
                mg_zero_reward_json((array) ($input['terms'] ?? [])),
                mg_zero_reward_json($metadata),
                $input['expires_at'] ?? null,
            ]);
        $instanceDbId = (int) $pdo->lastInsertId();
        mg_microgift_event($pdo, 'microgift.zero_reward_issued', $instanceDbId, (int) $template['id'], $merchantUserId, 'zero_reward_bridge', $sourceReference, $metadata);
        $load = $pdo->prepare('SELECT * FROM microgift_instances WHERE id=? LIMIT 1 FOR UPDATE');
        $load->execute([$instanceDbId]);
        $instance = $load->fetch(PDO::FETCH_ASSOC);
    }

    $actionItemId = mg_action_center_receive($pdo, (int) $instance['id'], $recipientUserId, $merchantUserId, [
        'sender_user_id' => $merchantUserId,
        'recipient_user_id' => $recipientUserId,
        'merchant_user_id' => $merchantUserId,
        'received_at' => date('Y-m-d H:i:s'),
        'occurred_at' => date('Y-m-d H:i:s'),
    ]);

    return [
        'pppm_item_id' => (string) $item['public_id'],
        'pppm_item_db_id' => $pppmItemDbId,
        'microgift_instance_id' => (string) $instance['public_id'],
        'microgift_instance_db_id' => (int) $instance['id'],
        'action_item_id' => $actionItemId,
        'pending_account_link' => false,
    ];
}

function mg_zero_reward_issue_from_wallet(PDO $pdo, array $input): array
{
    $merchantUserId = (int) ($input['merchant_user_id'] ?? 0);
    if ($merchantUserId < 1) throw new InvalidArgumentException('Merchant user is required.');
    $sourceType = (string) ($input['source_type'] ?? 'campaign_reward');
    $sourceReference = mg_zero_reward_safe_reference($sourceType, (string) ($input['source_reference'] ?? $input['wallet_item_public_id'] ?? 'reward'));
    $walletItemDbId = (int) ($input['wallet_item_db_id'] ?? 0);

    $existingPppmDbId = 0;
    if ($walletItemDbId > 0 && mg_zero_reward_column_exists($pdo, 'wallet_items', 'pppm_item_id')) {
        $stmt = $pdo->prepare('SELECT pppm_item_id FROM wallet_items WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$walletItemDbId]);
        $existingPppmDbId = (int) ($stmt->fetchColumn() ?: 0);
    }
    if ($existingPppmDbId > 0) {
        $bridge = mg_zero_reward_project_pppm_item($pdo, $existingPppmDbId, $input + ['source_reference' => $sourceReference]);
        mg_zero_reward_attach_wallet($pdo, $walletItemDbId, $existingPppmDbId, $bridge);
        return $bridge + ['duplicate' => true];
    }

    $source = mg_zero_reward_pppm_source($pdo, $merchantUserId);
    $payload = $input + ['zero_value_reward' => true, 'source_reference' => $sourceReference];
    $sourceEventId = mg_zero_reward_pppm_source_event($pdo, $source, $sourceReference, $sourceType . '.issued', $payload);
    $request = mg_zero_reward_pppm_request($pdo, $source, $sourceEventId, $input + ['source_reference' => $sourceReference]);

    $itemPublicId = mg_pppm_item_id();
    $metadata = [
        'zero_value_reward' => true,
        'source_type' => $sourceType,
        'source_reference' => $sourceReference,
        'wallet_item_id' => $input['wallet_item_public_id'] ?? null,
        'campaign_id' => $input['campaign_public_id'] ?? null,
        'reward_template_id' => $input['reward_template_public_id'] ?? null,
    ];
    $recipientUserId = !empty($input['recipient_user_id']) ? (int) $input['recipient_user_id'] : null;
    $pdo->prepare("INSERT INTO pppm_items (public_id,issuance_request_id,source_id,unit_sequence,item_type,funding_type,issuer_user_id,merchant_user_id,owner_user_id,recipient_user_id,recipient_external_id,source_reference,source_line_reference,title_snapshot,description_snapshot,value_cents_snapshot,currency_snapshot,terms_snapshot_json,metadata_snapshot_json,status,version_no,issued_at,assigned_at,delivered_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'delivered', 1, NOW(), NOW(), NOW(), NOW(), NOW())")
        ->execute([
            $itemPublicId,
            (int) $request['id'],
            (int) $source['id'],
            1,
            'reward',
            'merchant_funded',
            $merchantUserId,
            $merchantUserId,
            $recipientUserId ?: $merchantUserId,
            $recipientUserId,
            $input['recipient_external_id'] ?? null,
            $sourceReference,
            (string) ($input['source_line_reference'] ?? $sourceReference),
            (string) ($input['title'] ?? 'Microgifter reward'),
            $input['description'] ?? null,
            0,
            (string) ($input['currency'] ?? 'USD'),
            isset($input['terms']) && is_array($input['terms']) ? mg_zero_reward_json($input['terms']) : null,
            mg_zero_reward_json($metadata),
        ]);
    $pppmItemDbId = (int) $pdo->lastInsertId();
    $item = mg_pppm_refresh($pdo, $pppmItemDbId);
    mg_pppm_record_event($pdo, $item, 'zero_reward_delivered', null, 'delivered', $merchantUserId, $sourceEventId, $metadata);

    if ($recipientUserId !== null) {
        $pdo->prepare("INSERT INTO pppm_assignments (public_id,pppm_item_id,assignment_type,from_user_id,to_user_id,to_external_id,to_name,status,created_by_user_id,accepted_by_user_id,accepted_at,created_at,updated_at) VALUES (?,?,'campaign_reward',?,?,?,NULL,'accepted',?,?,NOW(),NOW(),NOW())")
            ->execute([mg_pppm_uuid(), $pppmItemDbId, $merchantUserId, $recipientUserId, $input['recipient_external_id'] ?? null, $merchantUserId, $recipientUserId]);
        $deliveryId = mg_pppm_uuid();
        $pdo->prepare("INSERT INTO pppm_deliveries (public_id,pppm_item_id,channel,destination,status,provider,queued_at,sent_at,delivered_at,created_at,updated_at) VALUES (?,?,'api',?,'delivered','microgifter_inbox',NOW(),NOW(),NOW(),NOW(),NOW())")
            ->execute([$deliveryId, $pppmItemDbId, 'user:' . $recipientUserId]);
        $deliveryDbId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO pppm_delivery_attempts (public_id,delivery_id,schedule_id,attempt_number,provider,status,attempted_at,completed_at,created_at,updated_at) VALUES (?,?,NULL,1,'microgifter_inbox','delivered',NOW(),NOW(),NOW(),NOW())")
            ->execute([mg_pppm_uuid(), $deliveryDbId]);
    }

    $bridge = mg_zero_reward_project_pppm_item($pdo, $pppmItemDbId, $input + ['source_reference' => $sourceReference]);
    mg_zero_reward_attach_wallet($pdo, $walletItemDbId, $pppmItemDbId, $bridge);
    return $bridge + ['duplicate' => false];
}
