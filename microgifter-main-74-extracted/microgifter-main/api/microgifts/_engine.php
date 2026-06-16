<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_microgift_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_microgift_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
    if ($slug === '') $slug = 'microgift-' . substr(bin2hex(random_bytes(6)), 0, 12);
    return substr($slug, 0, 190);
}

function mg_microgift_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_microgift_event(PDO $pdo, string $eventType, ?int $instanceId, ?int $templateId, ?int $actorUserId, ?string $sourceType, ?string $sourceReference, array $payload = []): void
{
    $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,template_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_microgift_uuid(),$instanceId,$templateId,$eventType,$actorUserId,$sourceType,$sourceReference,mg_microgift_json($payload)]);
}

function mg_microgift_claim_pepper(): string
{
    $config = require dirname(__DIR__) . '/config.php';
    $pepper = trim((string)($config['security']['claim_code_pepper'] ?? ''));
    if ($pepper === '') throw new RuntimeException('Microgift credential security is not configured.');
    return $pepper;
}

function mg_microgift_normalize_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code))));
}

function mg_microgift_generate_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = random_bytes(16);
    $characters = '';
    for ($i = 0; $i < 16; $i++) $characters .= $alphabet[ord($raw[$i]) % strlen($alphabet)];
    return substr($characters,0,4).'-'.substr($characters,4,4).'-'.substr($characters,8,4).'-'.substr($characters,12,4);
}

function mg_microgift_code_hash(string $normalizedCode): string
{
    return hash_hmac('sha256', $normalizedCode, mg_microgift_claim_pepper());
}

function mg_microgift_create_credential(PDO $pdo, int $instanceId, string $purpose, int $actorUserId, ?string $expiresAt = null): array
{
    if (!in_array($purpose, ['claim','redeem'], true)) throw new InvalidArgumentException('Invalid credential purpose.');
    $rawCode = mg_microgift_generate_code();
    $normalized = mg_microgift_normalize_code($rawCode);
    $publicId = mg_microgift_uuid();
    $prefix = substr($normalized,0,6);
    $last4 = substr($normalized,-4);
    $pdo->prepare("INSERT INTO microgift_credentials (public_id,instance_id,purpose,status,code_hash,code_prefix,code_last4,failed_attempts,max_attempts,expires_at,created_by_user_id,created_at,updated_at) VALUES (?,?,?,'active',?,?,?,?,5,?,?,NOW(),NOW())")
        ->execute([$publicId,$instanceId,$purpose,mg_microgift_code_hash($normalized),$prefix,$last4,0,$expiresAt,$actorUserId]);
    return ['credential_id'=>$publicId,'code'=>$rawCode,'code_last4'=>$last4,'expires_at'=>$expiresAt];
}

function mg_microgift_create_template(PDO $pdo, int $actorUserId, array $input): array
{
    $ownerType = trim((string)($input['owner_type'] ?? 'merchant'));
    if (!in_array($ownerType,['user','creator','merchant','organization','enterprise'],true)) throw new InvalidArgumentException('Invalid template owner type.');
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') throw new InvalidArgumentException('Template name is required.');
    $giftType = trim((string)($input['gift_type'] ?? 'value'));
    if (!in_array($giftType,['value','product','digital','discount','experience','reward'],true)) throw new InvalidArgumentException('Invalid Microgift type.');
    $visibility = trim((string)($input['visibility'] ?? 'private'));
    if (!in_array($visibility,['private','unlisted','public'],true)) throw new InvalidArgumentException('Invalid template visibility.');
    $currency = strtoupper(trim((string)($input['default_currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/',$currency)) throw new InvalidArgumentException('Invalid template currency.');
    $ownerReferenceId = isset($input['owner_reference_id']) ? (int)$input['owner_reference_id'] : null;
    $slug = mg_microgift_slug((string)($input['slug'] ?? $name));
    $publicId = mg_microgift_uuid();
    $pdo->prepare("INSERT INTO microgift_templates (public_id,owner_type,owner_user_id,owner_reference_id,name,slug,description,gift_type,status,visibility,default_currency,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'draft',?,?,?,NOW(),NOW())")
        ->execute([$publicId,$ownerType,$actorUserId,$ownerReferenceId?:null,$name,$slug,trim((string)($input['description']??''))?:null,$giftType,$visibility,$currency,$actorUserId]);
    $templateId = (int)$pdo->lastInsertId();
    mg_microgift_event($pdo,'microgift.template_created',null,$templateId,$actorUserId,'template_api',$publicId,['owner_type'=>$ownerType]);
    return ['template_id'=>$publicId,'status'=>'draft','slug'=>$slug];
}

function mg_microgift_create_version(PDO $pdo, int $actorUserId, string $templatePublicId, array $input): array
{
    $templateStmt = $pdo->prepare('SELECT * FROM microgift_templates WHERE public_id=? LIMIT 1 FOR UPDATE');
    $templateStmt->execute([$templatePublicId]);
    $template = $templateStmt->fetch();
    if (!$template || (int)$template['owner_user_id'] !== $actorUserId) throw new RuntimeException('Microgift template not found.');
    $recipientPolicy = trim((string)($input['recipient_policy'] ?? 'open_claim'));
    if (!in_array($recipientPolicy,['purchaser','named_user','external_recipient','open_claim','workplace_assigned'],true)) throw new InvalidArgumentException('Invalid recipient policy.');
    $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM microgift_template_versions WHERE template_id=?');
    $versionStmt->execute([(int)$template['id']]);
    $versionNumber = (int)$versionStmt->fetchColumn();
    $publicId = mg_microgift_uuid();
    $currency = strtoupper(trim((string)($input['currency'] ?? $template['default_currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/',$currency)) throw new InvalidArgumentException('Invalid version currency.');
    $faceValue = array_key_exists('face_value_cents',$input) ? max(0,(int)$input['face_value_cents']) : null;
    $pdo->prepare("INSERT INTO microgift_template_versions (public_id,template_id,version_number,status,title,description,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,future_demand_metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,'draft',?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$publicId,(int)$template['id'],$versionNumber,trim((string)($input['title']??$template['name'])),trim((string)($input['description']??$template['description']??''))?:null,$currency,$faceValue,isset($input['product_id'])?(int)$input['product_id']:null,isset($input['product_version_id'])?(int)$input['product_version_id']:null,$recipientPolicy,mg_microgift_json((array)($input['claim_policy']??[])),mg_microgift_json((array)($input['redemption_policy']??[])),mg_microgift_json((array)($input['location_policy']??[])),mg_microgift_json((array)($input['expiration_policy']??[])),mg_microgift_json((array)($input['terms_snapshot']??[])),mg_microgift_json((array)($input['future_demand_metadata']??[])),$actorUserId]);
    mg_microgift_event($pdo,'microgift.template_version_created',null,(int)$template['id'],$actorUserId,'template_api',$publicId,['version_number'=>$versionNumber]);
    return ['template_id'=>$templatePublicId,'version_id'=>$publicId,'version_number'=>$versionNumber,'status'=>'draft'];
}

function mg_microgift_publish_version(PDO $pdo, int $actorUserId, string $versionPublicId): array
{
    $stmt = $pdo->prepare('SELECT v.*,t.public_id AS template_public_id,t.owner_user_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$versionPublicId]);
    $version = $stmt->fetch();
    if (!$version || (int)$version['owner_user_id'] !== $actorUserId) throw new RuntimeException('Microgift template version not found.');
    if ((string)$version['status'] === 'published') return ['template_id'=>$version['template_public_id'],'version_id'=>$versionPublicId,'status'=>'published','duplicate'=>true];
    if ((string)$version['status'] !== 'draft') throw new RuntimeException('Only draft versions may be published.');
    $pdo->prepare("UPDATE microgift_template_versions SET status='published',published_at=NOW(),published_by_user_id=?,updated_at=NOW() WHERE id=? AND status='draft'")->execute([$actorUserId,(int)$version['id']]);
    $pdo->prepare("UPDATE microgift_templates SET active_version_id=?,status='active',updated_at=NOW() WHERE id=?")->execute([(int)$version['id'],(int)$version['template_id']]);
    mg_microgift_event($pdo,'microgift.template_version_published',null,(int)$version['template_id'],$actorUserId,'template_api',$versionPublicId,['version_number'=>(int)$version['version_number']]);
    return ['template_id'=>$version['template_public_id'],'version_id'=>$versionPublicId,'status'=>'published','duplicate'=>false];
}

function mg_microgift_validate_source(PDO $pdo, int $actorUserId, string $sourceType, string $sourceReference): array
{
    if ($sourceType === 'commerce_order_item') {
        $stmt = $pdo->prepare("SELECT oi.id,o.buyer_user_id FROM commerce_order_items oi INNER JOIN commerce_orders o ON o.id=oi.order_id WHERE oi.public_id=? AND oi.merchant_user_id=? AND o.payment_status='paid' LIMIT 1");
        $stmt->execute([$sourceReference,$actorUserId]);
        $source = $stmt->fetch();
        if (!$source) throw new RuntimeException('A verified paid commerce order item is required.');
        return ['commerce_order_item_id'=>(int)$source['id'],'purchaser_user_id'=>(int)$source['buyer_user_id']];
    }
    if (!in_array($sourceType,['merchant','administrator','enterprise','workplace','agent'],true)) throw new InvalidArgumentException('Unsupported Microgift issuance source.');
    return ['commerce_order_item_id'=>null,'purchaser_user_id'=>null];
}

function mg_microgift_existing_issue(PDO $pdo, string $idempotencyKey, int $actorUserId, string $templateVersionPublicId, string $sourceType, string $sourceReference): ?array
{
    $existing = $pdo->prepare(
        'SELECT i.public_id, i.status, i.issuer_user_id, i.source_type, i.source_reference,
                v.public_id AS template_version_public_id
         FROM microgift_instances i
         INNER JOIN microgift_template_versions v ON v.id = i.template_version_id
         WHERE i.idempotency_key = ?
         LIMIT 1'
    );
    $existing->execute([$idempotencyKey]);
    $row = $existing->fetch();
    if (!$row) {
        return null;
    }

    $sameRequest = (int)$row['issuer_user_id'] === $actorUserId
        && hash_equals((string)$row['template_version_public_id'], $templateVersionPublicId)
        && hash_equals((string)$row['source_type'], $sourceType)
        && hash_equals((string)$row['source_reference'], $sourceReference);
    if (!$sameRequest) {
        throw new RuntimeException('Idempotency key is already bound to a different Microgift issuance request.');
    }

    return ['instance_id'=>$row['public_id'],'status'=>$row['status'],'duplicate'=>true];
}

function mg_microgift_issue(PDO $pdo, int $actorUserId, array $input): array
{
    $templateVersionPublicId = trim((string)($input['template_version_id'] ?? ''));
    $sourceType = trim((string)($input['source_type'] ?? ''));
    $sourceReference = trim((string)($input['source_reference'] ?? ''));
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    if ($templateVersionPublicId==='' || $sourceType==='' || $sourceReference==='' || $idempotencyKey==='') throw new InvalidArgumentException('Template version, source, source reference, and idempotency key are required.');
    if (strlen($idempotencyKey)>190 || strlen($sourceReference)>190) throw new InvalidArgumentException('Source reference or idempotency key is too long.');

    $duplicate = mg_microgift_existing_issue($pdo,$idempotencyKey,$actorUserId,$templateVersionPublicId,$sourceType,$sourceReference);
    if ($duplicate !== null) return $duplicate;

    $versionStmt = $pdo->prepare("SELECT v.*,t.owner_user_id,t.id AS resolved_template_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.public_id=? AND v.status='published' AND t.status='active' LIMIT 1 FOR UPDATE");
    $versionStmt->execute([$templateVersionPublicId]);
    $version = $versionStmt->fetch();
    if (!$version || (int)$version['owner_user_id'] !== $actorUserId) throw new RuntimeException('Published Microgift template version not found.');
    $source = mg_microgift_validate_source($pdo,$actorUserId,$sourceType,$sourceReference);
    $instancePublicId = mg_microgift_uuid();
    $ownerUserId = $source['purchaser_user_id'] ?: $actorUserId;
    $recipientUserId = isset($input['recipient_user_id']) ? max(0,(int)$input['recipient_user_id']) : null;
    $expiresAt = isset($input['expires_at']) && trim((string)$input['expires_at'])!=='' ? trim((string)$input['expires_at']) : null;

    try {
        $pdo->prepare("INSERT INTO microgift_instances (public_id,template_id,template_version_id,status,source_type,source_reference,idempotency_key,issuer_user_id,owner_user_id,recipient_user_id,recipient_reference,commerce_order_item_id,legacy_gift_id,title_snapshot,description_snapshot,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,'issued',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())")
            ->execute([$instancePublicId,(int)$version['resolved_template_id'],(int)$version['id'],$sourceType,$sourceReference,$idempotencyKey,$actorUserId,$ownerUserId,$recipientUserId?:null,trim((string)($input['recipient_reference']??''))?:null,$source['commerce_order_item_id'],isset($input['legacy_gift_id'])?(int)$input['legacy_gift_id']:null,(string)$version['title'],$version['description'],(string)$version['currency'],$version['face_value_cents'],$version['product_id'],$version['product_version_id'],(string)$version['recipient_policy'],$version['claim_policy_json'],$version['redemption_policy_json'],$version['location_policy_json'],$version['expiration_policy_json'],$version['terms_snapshot_json'],mg_microgift_json((array)($input['metadata']??[])),$expiresAt]);
    } catch (Throwable $error) {
        if (str_contains($error->getMessage(),'Duplicate')) {
            $duplicate = mg_microgift_existing_issue($pdo,$idempotencyKey,$actorUserId,$templateVersionPublicId,$sourceType,$sourceReference);
            if ($duplicate !== null) return $duplicate;
        }
        throw $error;
    }

    $instanceId = (int)$pdo->lastInsertId();
    $credential = (string)$version['recipient_policy'] !== 'purchaser' ? mg_microgift_create_credential($pdo,$instanceId,'claim',$actorUserId,$expiresAt) : null;
    mg_microgift_event($pdo,'microgift.instance_issued',$instanceId,(int)$version['resolved_template_id'],$actorUserId,$sourceType,$sourceReference,['template_version_id'=>$templateVersionPublicId,'owner_user_id'=>$ownerUserId,'recipient_user_id'=>$recipientUserId]);
    return ['instance_id'=>$instancePublicId,'status'=>'issued','duplicate'=>false,'credential'=>$credential];
}
