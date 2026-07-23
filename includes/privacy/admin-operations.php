<?php
declare(strict_types=1);

require_once __DIR__ . '/account-erasure.php';

function mg_privacy_admin_request_detail(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT pr.*,u.email AS current_email,u.display_name,u.full_name,u.status AS user_status,u.privacy_state
         FROM privacy_requests pr
         LEFT JOIN users u ON u.id=pr.user_id
         WHERE pr.id=? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) return null;

    $item['user'] = $item['user_id'] ? [
        'id' => (int) $item['user_id'],
        'email' => $item['current_email'],
        'display_name' => $item['display_name'] ?: $item['full_name'],
        'status' => $item['user_status'],
        'privacy_state' => $item['privacy_state'],
    ] : null;

    $holds = $pdo->prepare(
        'SELECT h.*,placer.display_name AS placed_by_name,releaser.display_name AS released_by_name
         FROM privacy_legal_holds h
         LEFT JOIN users placer ON placer.id=h.placed_by_user_id
         LEFT JOIN users releaser ON releaser.id=h.released_by_user_id
         WHERE h.request_id=? OR (? IS NOT NULL AND h.user_id=?)
         ORDER BY h.id DESC'
    );
    $holds->execute([$requestId,$item['user_id'],$item['user_id']]);
    $item['holds'] = $holds->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $handoffs = $pdo->prepare(
        'SELECT h.*,u.display_name AS merchant_name,u.email AS merchant_email
         FROM privacy_merchant_handoffs h
         LEFT JOIN users u ON u.id=h.merchant_user_id
         WHERE h.request_id=? ORDER BY h.id'
    );
    $handoffs->execute([$requestId]);
    $item['handoffs'] = $handoffs->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $actions = $pdo->prepare('SELECT * FROM privacy_data_actions WHERE request_id=? ORDER BY id');
    $actions->execute([$requestId]);
    $item['actions'] = $actions->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $events = $pdo->prepare(
        'SELECT e.*,u.display_name AS actor_name
         FROM privacy_request_events e
         LEFT JOIN users u ON u.id=e.actor_user_id
         WHERE e.request_id=? ORDER BY e.id DESC LIMIT 200'
    );
    $events->execute([$requestId]);
    $item['events'] = $events->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $item;
}

function mg_privacy_create_admin_delete_request(PDO $pdo, int $actorUserId, array $input): array
{
    $reason = trim((string) ($input['reason'] ?? ''));
    if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
        throw new RuntimeException('Provide an administrative reason between 8 and 500 characters.');
    }

    $jurisdiction = strtolower(trim((string) ($input['jurisdiction'] ?? 'other')));
    if (!in_array($jurisdiction,['eu_eea','uk','california','other_us','other'],true)) {
        throw new RuntimeException('Select a supported jurisdiction.');
    }

    $userId = (int) ($input['user_id'] ?? 0);
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    if ($userId < 1 && !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Provide a valid user ID or account email.');
    }

    if ($userId > 0) {
        $find = $pdo->prepare('SELECT id,email,display_name,full_name,status,privacy_state FROM users WHERE id=? LIMIT 1 FOR UPDATE');
        $find->execute([$userId]);
    } else {
        $find = $pdo->prepare('SELECT id,email,display_name,full_name,status,privacy_state FROM users WHERE email=? LIMIT 1 FOR UPDATE');
        $find->execute([$email]);
    }
    $user = $find->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('The selected account was not found.');
    $userId = (int) $user['id'];
    if ((string) ($user['privacy_state'] ?? 'active') === 'anonymized') {
        throw new RuntimeException('This identity has already been anonymized.');
    }

    $existing = $pdo->prepare('SELECT id FROM privacy_requests WHERE user_id=? AND status NOT IN ("completed","denied","cancelled") ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $existing->execute([$userId]);
    if ($existingId = (int) ($existing->fetchColumn() ?: 0)) {
        $detail = mg_privacy_admin_request_detail($pdo,$existingId);
        if (!$detail) throw new RuntimeException('The existing privacy request could not be loaded.');
        $detail['existing'] = true;
        return $detail;
    }

    $accountEmail = (string) $user['email'];
    $identityHash = mg_privacy_identity_hash($accountEmail);
    $now = new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $deadlines = mg_privacy_deadlines($jurisdiction,$now);
    $grace = $deadlines['grace_ends_at'] > $deadlines['response_due_at']
        ? $deadlines['response_due_at']
        : $deadlines['grace_ends_at'];
    $publicId = mg_privacy_uuid();

    $insert = $pdo->prepare(
        'INSERT INTO privacy_requests
         (public_id,user_id,request_type,jurisdiction,source,status,contact_email,contact_email_hash,verification_method,requested_at,acknowledgement_due_at,acknowledged_at,identity_verified_at,response_due_at,grace_ends_at,decision,decision_reason,created_by_user_id,assigned_to_user_id,metadata_json,created_at,updated_at)
         VALUES (?,? ,"delete",?,"admin","under_review",?,? ,"admin_account_match",NOW(),?,NOW(),NOW(),?,? ,"pending",?,?,?, ?,NOW(),NOW())'
    );
    $insert->execute([
        $publicId,
        $userId,
        $jurisdiction,
        $accountEmail,
        $identityHash,
        mg_privacy_dt($deadlines['acknowledgement_due_at']),
        mg_privacy_dt($deadlines['response_due_at']),
        mg_privacy_dt($grace),
        $reason,
        $actorUserId,
        $actorUserId,
        json_encode(['administrative_request'=>true,'account_status'=>$user['status']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);
    $requestId = (int) $pdo->lastInsertId();
    mg_privacy_event($pdo,$requestId,'admin_request_created',['reason'=>$reason,'jurisdiction'=>$jurisdiction],$actorUserId);
    $handoffs = mg_privacy_generate_merchant_handoffs($pdo,$requestId,$userId,mg_privacy_dt($deadlines['response_due_at']));
    mg_privacy_action($pdo,$requestId,'merchant_controller_review','merchant_crm_contacts','notify',$handoffs ? 'pending' : 'skipped',0,'Merchant-controlled CRM records require controller review.',['handoffs_created'=>$handoffs]);

    $detail = mg_privacy_admin_request_detail($pdo,$requestId);
    if (!$detail) throw new RuntimeException('The administrative privacy request could not be loaded.');
    return $detail;
}
