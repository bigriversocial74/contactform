<?php
declare(strict_types=1);

require_once __DIR__ . '/account-erasure.php';

function mg_privacy_assert_account_restriction_safe(PDO $pdo, int $userId): void
{
    if (!mg_privacy_table_exists($pdo,'user_roles') || !mg_privacy_table_exists($pdo,'roles')) return;
    $role = $pdo->prepare('SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug="super_admin" LIMIT 1');
    $role->execute([$userId]);
    if (!$role->fetchColumn()) return;

    $others = $pdo->prepare(
        'SELECT COUNT(DISTINCT u.id)
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id=u.id
         INNER JOIN roles r ON r.id=ur.role_id
         WHERE r.slug="super_admin" AND u.status="active" AND u.id<>?'
    );
    $others->execute([$userId]);
    if ((int) $others->fetchColumn() < 1) {
        throw new RuntimeException('The last active super-administrator cannot be disabled. Assign another active super-administrator first.');
    }
}

function mg_privacy_create_operational_handoffs(PDO $pdo, int $requestId, int $userId, string $dueAt): int
{
    $isMerchant = false;
    if (mg_privacy_table_exists($pdo,'merchant_profiles')) {
        $stmt = $pdo->prepare('SELECT 1 FROM merchant_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $isMerchant = (bool) $stmt->fetchColumn();
    }
    if (!$isMerchant && mg_privacy_table_exists($pdo,'user_roles') && mg_privacy_table_exists($pdo,'roles')) {
        $stmt = $pdo->prepare('SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug="merchant" LIMIT 1');
        $stmt->execute([$userId]);
        $isMerchant = (bool) $stmt->fetchColumn();
    }
    if (!$isMerchant) return 0;

    $notes = 'Merchant ownership, balances, subscriptions, active campaigns, customer obligations, and workspace continuity require transfer or closure review before final erasure.';
    $insert = $pdo->prepare('INSERT INTO privacy_merchant_handoffs (request_id,merchant_user_id,status,due_at,notes,created_at,updated_at) VALUES (?,? ,"pending",?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE notes=VALUES(notes),due_at=VALUES(due_at),updated_at=NOW()');
    $insert->execute([$requestId,$userId,$dueAt,$notes]);
    mg_privacy_action($pdo,$requestId,'merchant_account_ownership_review','merchant_profiles','notify','pending',0,'Merchant account continuity must be resolved before final erasure.',['merchant_user_id'=>$userId]);
    return 1;
}

function mg_privacy_pending_handoff_count(PDO $pdo, int $requestId): int
{
    if (!mg_privacy_table_exists($pdo,'privacy_merchant_handoffs')) return 0;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM privacy_merchant_handoffs WHERE request_id=? AND status NOT IN ("completed","not_applicable")');
    $stmt->execute([$requestId]);
    return (int) $stmt->fetchColumn();
}

function mg_privacy_post_finalize_cleanup(PDO $pdo, int $requestId, int $userId): array
{
    $deleted = 0;
    $anonymized = 0;

    if (mg_privacy_table_exists($pdo,'public_profiles')) {
        $profile = $pdo->prepare('SELECT id FROM public_profiles WHERE user_id=? LIMIT 1');
        $profile->execute([$userId]);
        $profileId = (int) ($profile->fetchColumn() ?: 0);
        if ($profileId > 0) {
            foreach (['public_profile_links','public_profile_sections'] as $table) {
                if (!mg_privacy_table_exists($pdo,$table) || !mg_privacy_column_exists($pdo,$table,'profile_id')) continue;
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE profile_id=?");
                $stmt->execute([$profileId]);
                $deleted += $stmt->rowCount();
            }
            $sets = [];
            if (mg_privacy_column_exists($pdo,'public_profiles','slug')) $sets[] = 'slug=?';
            if (mg_privacy_column_exists($pdo,'public_profiles','display_name')) $sets[] = 'display_name="Deleted User"';
            if ($sets) {
                $params = [];
                if (str_contains(implode(',',$sets),'slug=?')) $params[] = 'deleted-'.$userId.'-'.substr(hash('sha256',$requestId.'|'.$userId),0,10);
                $params[] = $userId;
                $stmt = $pdo->prepare('UPDATE public_profiles SET '.implode(',',$sets).' WHERE user_id=?');
                $stmt->execute($params);
                $anonymized += $stmt->rowCount();
            }
        }
    }

    foreach (['user_model_events','user_model_assignments','user_roles'] as $table) {
        if (!mg_privacy_table_exists($pdo,$table) || !mg_privacy_column_exists($pdo,$table,'user_id')) continue;
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE user_id=?");
        $stmt->execute([$userId]);
        $deleted += $stmt->rowCount();
    }

    $profileRules = [
        'creator_profiles' => ['display_name="Deleted Creator"','slug=NULL','bio=NULL','status="disabled"'],
        'moderator_profiles' => ['notes=NULL','status="disabled"'],
        'vendor_manager_profiles' => ['territory=NULL','status="disabled"'],
        'marketing_affiliate_profiles' => ['affiliate_code=NULL','status="disabled"'],
        'trader_profiles' => ['status="disabled"','risk_status="restricted"'],
    ];
    foreach ($profileRules as $table => $candidateSets) {
        if (!mg_privacy_table_exists($pdo,$table) || !mg_privacy_column_exists($pdo,$table,'user_id')) continue;
        $sets = [];
        foreach ($candidateSets as $candidate) {
            $column = trim(strtok($candidate,'='),'` ');
            if (mg_privacy_column_exists($pdo,$table,$column)) $sets[] = $candidate;
        }
        if (!$sets) continue;
        $stmt = $pdo->prepare("UPDATE `{$table}` SET ".implode(',',$sets).' WHERE user_id=?');
        $stmt->execute([$userId]);
        $anonymized += $stmt->rowCount();
    }

    if (mg_privacy_table_exists($pdo,'merchant_profiles') && mg_privacy_column_exists($pdo,'merchant_profiles','user_id')) {
        $sets = [];
        if (mg_privacy_column_exists($pdo,'merchant_profiles','onboarding_status')) $sets[] = 'onboarding_status="disabled"';
        if (mg_privacy_column_exists($pdo,'merchant_profiles','verification_status')) $sets[] = 'verification_status="unverified"';
        if (mg_privacy_column_exists($pdo,'merchant_profiles','metadata_json')) $sets[] = 'metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),"$.privacy_owner_erased",true,"$.privacy_request_id",?)';
        if ($sets) {
            $params = [];
            if (str_contains(implode(',',$sets),'privacy_request_id')) $params[] = $requestId;
            $params[] = $userId;
            $stmt = $pdo->prepare('UPDATE merchant_profiles SET '.implode(',',$sets).' WHERE user_id=?');
            $stmt->execute($params);
            $anonymized += $stmt->rowCount();
        }
    }

    mg_privacy_action($pdo,$requestId,'identity_relationship_cleanup',null,'delete','completed',$deleted,'Remove access roles, model assignments, private public-profile children, and obsolete identity relationships.',['anonymized_rows'=>$anonymized]);
    mg_privacy_event($pdo,$requestId,'identity_relationships_cleaned',['deleted_rows'=>$deleted,'anonymized_rows'=>$anonymized]);
    return ['deleted_rows'=>$deleted,'anonymized_rows'=>$anonymized];
}

function mg_privacy_finalize_with_operations(PDO $pdo, int $requestId, ?int $actorUserId = null, bool $force = false): array
{
    $request = mg_privacy_request_by_id($pdo,$requestId,true);
    if (!$request) throw new RuntimeException('Privacy request not found.');
    $userId = (int) ($request['user_id'] ?? 0);
    if ($userId < 1) return mg_privacy_finalize_request($pdo,$requestId,$actorUserId,$force);

    $pendingHandoffs = mg_privacy_pending_handoff_count($pdo,$requestId);
    if ($pendingHandoffs > 0) {
        $pdo->prepare('UPDATE privacy_requests SET status="partially_completed",updated_at=NOW() WHERE id=? AND status NOT IN ("completed","denied","cancelled")')->execute([$requestId]);
        mg_privacy_action($pdo,$requestId,'pending_controller_handoffs','privacy_merchant_handoffs','notify','pending',0,'Final erasure waits for merchant-controller and operational handoffs.',['pending_handoffs'=>$pendingHandoffs]);
        return ['status'=>'partially_completed','pending_handoffs'=>$pendingHandoffs];
    }

    $result = mg_privacy_finalize_request($pdo,$requestId,$actorUserId,$force);
    if (($result['status'] ?? '') === 'completed') {
        $cleanup = mg_privacy_post_finalize_cleanup($pdo,$requestId,$userId);
        $result['post_cleanup'] = $cleanup;
    }
    return $result;
}
