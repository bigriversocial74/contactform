<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';

function mg_zero_reward_bridge_uuid(): string
{
    return mg_microgift_uuid();
}

function mg_zero_reward_bridge_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_zero_reward_require_authority_schema(PDO $pdo): void
{
    $required = [
        'wallet_items','pppm_sources','pppm_source_events','pppm_issuance_requests','pppm_items',
        'pppm_item_events','pppm_item_snapshots','microgift_templates','microgift_template_versions',
        'microgift_instances','microgift_events','microgift_inbox_items',
    ];
    $missing = [];
    foreach ($required as $table) {
        if (!mg_zero_reward_bridge_table_exists($pdo, $table)) $missing[] = $table;
    }
    if ($missing !== []) {
        throw new RuntimeException('Reward delivery schema is incomplete: ' . implode(', ', $missing));
    }
}

function mg_zero_reward_source(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pppm_sources WHERE owner_user_id=? AND source_type='reward' AND provider='wallet_staging' AND status='active' ORDER BY id LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) return $source;

    try {
        $pdo->prepare("INSERT INTO pppm_sources (public_id,owner_user_id,source_type,provider,name,status,created_at,updated_at) VALUES (?,?,'reward','wallet_staging','Microgifter reward staging','active',NOW(),NOW())")
            ->execute([mg_pppm_uuid(), $merchantUserId]);
    } catch (Throwable $error) {
        if (!str_contains(strtolower($error->getMessage()), 'duplicate')) throw $error;
    }
    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) throw new RuntimeException('Unable to create the wallet reward PPPM source.');
    return $source;
}

function mg_zero_reward_template_version(PDO $pdo, array $input): array
{
    $merchantUserId = (int)($input['merchant_user_id'] ?? 0);
    $title = trim((string)($input['title'] ?? 'Microgifter reward')) ?: 'Microgifter reward';
    $currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
    $templateReference = trim((string)($input['reward_template_public_id'] ?? $input['source_type'] ?? $title));
    $slug = 'wallet-reward-' . substr(hash('sha256', $merchantUserId . '|' . $templateReference), 0, 32);

    $stmt = $pdo->prepare("SELECT t.id template_id,t.public_id template_public_id,t.active_version_id,v.id version_id,v.public_id version_public_id
        FROM microgift_templates t
        LEFT JOIN microgift_template_versions v ON v.id=t.active_version_id AND v.status='published'
        WHERE t.owner_type='merchant' AND t.owner_user_id=? AND t.slug=?
        ORDER BY t.id LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId, $slug]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && !empty($existing['version_public_id'])) return $existing;

    if (!$existing) {
        $template = mg_microgift_create_template($pdo, $merchantUserId, [
            'owner_type'=>'merchant',
            'name'=>$title,
            'gift_type'=>'reward',
            'visibility'=>'unlisted',
            'default_currency'=>$currency,
            'slug'=>$slug,
            'description'=>trim((string)($input['description'] ?? 'Earned Microgifter reward.')),
        ]);
        $templatePublicId = (string)$template['template_id'];
    } else {
        $templatePublicId = (string)$existing['template_public_id'];
    }

    $locationIds = array_values(array_filter(array_map('strval', (array)($input['location_ids'] ?? []))));
    $created = mg_microgift_create_version($pdo, $merchantUserId, $templatePublicId, [
        'title'=>$title,
        'description'=>trim((string)($input['description'] ?? 'Earned Microgifter reward.')),
        'currency'=>$currency,
        'face_value_cents'=>max(0, (int)($input['display_value_cents'] ?? 0)),
        'recipient_policy'=>'named_user',
        'claim_policy'=>['mode'=>'assigned_reward','source'=>'wallet_staging'],
        'redemption_policy'=>['mode'=>'merchant_location'],
        'location_policy'=>$locationIds === [] ? ['mode'=>'unrestricted'] : ['mode'=>'selected_locations','location_ids'=>$locationIds],
        'expiration_policy'=>!empty($input['expires_at']) ? ['mode'=>'fixed_date','expires_at'=>(string)$input['expires_at']] : ['mode'=>'none'],
        'terms_snapshot'=>(array)($input['terms'] ?? []),
        'future_demand_metadata'=>[
            'source'=>'wallet_staging',
            'reward_template_public_id'=>$input['reward_template_public_id'] ?? null,
            'campaign_public_id'=>$input['campaign_public_id'] ?? null,
        ],
    ]);
    $published = mg_microgift_publish_version($pdo, $merchantUserId, (string)$created['version_id']);
    $stmt->execute([$merchantUserId, $slug]);
    $resolved = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resolved || empty($resolved['version_public_id'])) throw new RuntimeException('Unable to publish the wallet reward Microgift template.');
    return $resolved;
}

function mg_zero_reward_source_event(PDO $pdo, array $source, string $walletPublicId, array $input): array
{
    $externalId = 'wallet.reward.' . $walletPublicId;
    $stmt = $pdo->prepare('SELECT * FROM pppm_source_events WHERE source_id=? AND external_event_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$source['id'], $externalId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($event) return $event;

    $payload = [
        'wallet_item_public_id'=>$walletPublicId,
        'campaign_public_id'=>$input['campaign_public_id'] ?? null,
        'reward_template_public_id'=>$input['reward_template_public_id'] ?? null,
        'recipient_user_id'=>(int)($input['recipient_user_id'] ?? 0),
        'source_type'=>(string)($input['source_type'] ?? 'wallet_reward'),
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
        $pdo->prepare("INSERT INTO pppm_source_events (public_id,source_id,external_event_id,event_type,payload_json,payload_hash,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'validated',NOW(),NOW(),NOW())")
            ->execute([mg_pppm_uuid(), (int)$source['id'], $externalId, 'wallet.reward.issued', $payloadJson, hash('sha256', $payloadJson)]);
    } catch (Throwable $error) {
        if (!str_contains(strtolower($error->getMessage()), 'duplicate')) throw $error;
    }
    $stmt->execute([(int)$source['id'], $externalId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new RuntimeException('Unable to create the wallet reward source event.');
    return $event;
}

function mg_zero_reward_pppm_item(PDO $pdo, array $source, array $event, string $walletPublicId, array $input): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE source_id=? AND source_reference=? AND unit_sequence=1 ORDER BY id LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$source['id'], $walletPublicId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) return $item;

    $merchantUserId = (int)$input['merchant_user_id'];
    $recipientUserId = (int)$input['recipient_user_id'];
    $title = trim((string)($input['title'] ?? 'Microgifter reward')) ?: 'Microgifter reward';
    $description = trim((string)($input['description'] ?? '')) ?: null;
    $currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
    $valueCents = max(0, (int)($input['display_value_cents'] ?? 0));
    $lineReference = substr(trim((string)($input['source_line_reference'] ?? $walletPublicId)), 0, 190);
    $termsJson = json_encode((array)($input['terms'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $metadata = [
        'wallet_item_public_id'=>$walletPublicId,
        'wallet_item_db_id'=>$input['wallet_item_db_id'] ?? null,
        'campaign_public_id'=>$input['campaign_public_id'] ?? null,
        'reward_template_public_id'=>$input['reward_template_public_id'] ?? null,
        'source_type'=>$input['source_type'] ?? 'wallet_reward',
    ];
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $requestStmt = $pdo->prepare('SELECT * FROM pppm_issuance_requests WHERE source_id=? AND source_reference=? AND source_line_reference=? ORDER BY id LIMIT 1 FOR UPDATE');
    $requestStmt->execute([(int)$source['id'], $walletPublicId, $lineReference]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $requestPublicId = mg_pppm_uuid();
        $pdo->prepare("INSERT INTO pppm_issuance_requests (public_id,source_id,source_event_id,issuer_user_id,merchant_user_id,source_reference,source_line_reference,item_type,funding_type,quantity,unit_value_cents,currency,recipient_user_id,recipient_external_id,recipient_name,title,description,terms_snapshot_json,metadata_json,status,issued_count,requested_at,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'reward','earned_reward',1,?,?,?,?,?,?,?,?,?,'issued',1,NOW(),NOW(),NOW(),NOW())")
            ->execute([$requestPublicId,(int)$source['id'],(int)$event['id'],$merchantUserId,$merchantUserId,$walletPublicId,$lineReference,$valueCents,$currency,$recipientUserId,substr((string)($input['recipient_external_id'] ?? ''),0,190)?:null,substr(trim((string)($input['recipient_name'] ?? '')),0,160)?:null,substr($title,0,160),$description,$termsJson,$metadataJson]);
        $requestId = (int)$pdo->lastInsertId();
    } else {
        $requestId = (int)$request['id'];
    }

    $itemPublicId = mg_pppm_item_id();
    $expiresAt = !empty($input['expires_at']) ? (string)$input['expires_at'] : null;
    $pdo->prepare("INSERT INTO pppm_items (public_id,issuance_request_id,source_id,unit_sequence,item_type,funding_type,issuer_user_id,merchant_user_id,owner_user_id,recipient_user_id,recipient_external_id,source_reference,source_line_reference,title_snapshot,description_snapshot,value_cents_snapshot,currency_snapshot,terms_snapshot_json,metadata_snapshot_json,status,version_no,issued_at,assigned_at,sent_at,delivered_at,expires_at,created_at,updated_at) VALUES (?,?,?,1,'reward','earned_reward',?,?,?,?,?,?,?,?,?,?,?,?,?,'delivered',1,NOW(),NOW(),NOW(),NOW(),?,NOW(),NOW())")
        ->execute([$itemPublicId,$requestId,(int)$source['id'],$merchantUserId,$merchantUserId,$recipientUserId,$recipientUserId,substr((string)($input['recipient_external_id'] ?? ''),0,190)?:null,$walletPublicId,$lineReference,substr($title,0,160),$description,$valueCents,$currency,$termsJson,$metadataJson,$expiresAt]);
    $itemId = (int)$pdo->lastInsertId();
    $item = mg_pppm_locked_by_id($pdo, $itemId);
    mg_pppm_record_event($pdo, $item, 'issued_from_wallet_reward', null, 'delivered', $merchantUserId, (int)$event['id'], $metadata);
    $pdo->prepare("UPDATE pppm_source_events SET processing_status='processed',processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$event['id']]);
    return $item;
}

function mg_zero_reward_microgift(PDO $pdo, array $template, array $pppmItem, string $walletPublicId, array $input): array
{
    $merchantUserId = (int)$input['merchant_user_id'];
    $recipientUserId = (int)$input['recipient_user_id'];
    $idempotencyKey = substr('wallet-reward:' . $walletPublicId, 0, 190);
    $stmt = $pdo->prepare('SELECT * FROM microgift_instances WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$idempotencyKey]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($instance) {
        $existingPppmId = (int)($instance['pppm_item_id'] ?? 0);
        if ($existingPppmId > 0 && $existingPppmId !== (int)$pppmItem['id']) throw new RuntimeException('Wallet reward Microgift is linked to a different PPPM item.');
        if ($existingPppmId === 0) {
            $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=?,updated_at=NOW() WHERE id=?')->execute([(int)$pppmItem['id'],(int)$instance['id']]);
            $instance['pppm_item_id'] = (int)$pppmItem['id'];
        }
        return $instance;
    }

    $versionStmt = $pdo->prepare("SELECT v.*,t.id resolved_template_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.id=? AND v.status='published' AND t.status='active' LIMIT 1 FOR UPDATE");
    $versionStmt->execute([(int)$template['version_id']]);
    $version = $versionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$version) throw new RuntimeException('Published wallet reward Microgift version not found.');

    $instancePublicId = mg_microgift_uuid();
    $metadata = [
        'wallet_item_public_id'=>$walletPublicId,
        'wallet_item_db_id'=>$input['wallet_item_db_id'] ?? null,
        'campaign_public_id'=>$input['campaign_public_id'] ?? null,
        'reward_template_public_id'=>$input['reward_template_public_id'] ?? null,
        'pppm_item_public_id'=>(string)$pppmItem['public_id'],
    ];
    $pdo->prepare("INSERT INTO microgift_instances (public_id,template_id,template_version_id,status,source_type,source_reference,idempotency_key,issuer_user_id,owner_user_id,recipient_user_id,recipient_reference,commerce_order_item_id,pppm_item_id,legacy_gift_id,title_snapshot,description_snapshot,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,metadata_json,issued_at,delivered_at,expires_at,created_at,updated_at) VALUES (?,?,?,'delivered','wallet_reward',?,?,?,?,?,?,?,NULL,?,NULL,?,?,?,?,?,?,?, ?,?,?,?,?,?,NOW(),NOW(),?,NOW(),NOW())")
        ->execute([$instancePublicId,(int)$version['resolved_template_id'],(int)$version['id'],$walletPublicId,$idempotencyKey,$merchantUserId,$recipientUserId,$recipientUserId,substr((string)($input['recipient_external_id'] ?? ''),0,255)?:null,(int)$pppmItem['id'],(string)$version['title'],$version['description'],(string)$version['currency'],$version['face_value_cents'],$version['product_id'],$version['product_version_id'],(string)$version['recipient_policy'],$version['claim_policy_json'],$version['redemption_policy_json'],$version['location_policy_json'],$version['expiration_policy_json'],$version['terms_snapshot_json'],mg_microgift_json($metadata),!empty($input['expires_at'])?(string)$input['expires_at']:null]);
    $instanceId = (int)$pdo->lastInsertId();
    mg_microgift_event($pdo, 'microgift.instance_delivered', $instanceId, (int)$version['resolved_template_id'], $merchantUserId, 'wallet_reward', $walletPublicId, [
        'pppm_item_id'=>(string)$pppmItem['public_id'],
        'recipient_user_id'=>$recipientUserId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM microgift_instances WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$instance) throw new RuntimeException('Issued wallet reward Microgift was not found.');
    return $instance;
}

/**
 * Internal wallet staging -> canonical Microgift -> PPPM -> Action Center.
 *
 * The wallet record is never the customer ownership surface. For an identified
 * recipient, this function creates one idempotent PPPM reward item, links one
 * canonical Microgift instance, and projects it into the recipient Inbox.
 */
function mg_zero_reward_issue_from_wallet(PDO $pdo, array $input): array
{
    $walletPublicId = trim((string)($input['wallet_item_public_id'] ?? $input['source_reference'] ?? ''));
    $recipientUserId = (int)($input['recipient_user_id'] ?? 0);
    $merchantUserId = (int)($input['merchant_user_id'] ?? 0);
    $sourceType = trim((string)($input['source_type'] ?? 'wallet_reward')) ?: 'wallet_reward';
    $sourceReference = trim((string)($input['source_reference'] ?? $walletPublicId));
    if ($walletPublicId === '') $walletPublicId = mg_zero_reward_bridge_uuid();
    if ($merchantUserId < 1) throw new InvalidArgumentException('Merchant identity is required for reward delivery.');

    if ($recipientUserId < 1) {
        return [
            'schema_ready'=>true,
            'pending_account_link'=>true,
            'wallet_item_id'=>$walletPublicId,
            'source_type'=>$sourceType,
            'source_reference'=>$sourceReference,
        ];
    }

    mg_zero_reward_require_authority_schema($pdo);
    $source = mg_zero_reward_source($pdo, $merchantUserId);
    $event = mg_zero_reward_source_event($pdo, $source, $walletPublicId, $input);
    $pppmItem = mg_zero_reward_pppm_item($pdo, $source, $event, $walletPublicId, $input);
    $template = mg_zero_reward_template_version($pdo, $input);
    $instance = mg_zero_reward_microgift($pdo, $template, $pppmItem, $walletPublicId, $input);
    $projection = mg_action_center_sent($pdo, (int)$instance['id'], $merchantUserId, $recipientUserId, [
        'merchant_user_id'=>$merchantUserId,
        'occurred_at'=>$instance['delivered_at'] ?? $instance['issued_at'] ?? date('Y-m-d H:i:s'),
    ]);

    if (!empty($input['wallet_item_db_id'])) {
        $pdo->prepare('UPDATE wallet_items SET user_id=?,pppm_item_id=?,updated_at=NOW() WHERE id=? AND public_id=?')
            ->execute([$recipientUserId,(int)$pppmItem['id'],(int)$input['wallet_item_db_id'],$walletPublicId]);
    }

    return [
        'schema_ready'=>true,
        'pending_account_link'=>false,
        'wallet_item_id'=>$walletPublicId,
        'recipient_user_id'=>$recipientUserId,
        'source_type'=>$sourceType,
        'source_reference'=>$sourceReference,
        'microgift_instance_id'=>(string)$instance['public_id'],
        'microgift_status'=>(string)$instance['status'],
        'pppm_item_db_id'=>(int)$pppmItem['id'],
        'pppm_item_id'=>(string)$pppmItem['public_id'],
        'pppm_status'=>(string)$pppmItem['status'],
        'action_center'=>$projection,
        'destination'=>'inbox',
    ];
}
