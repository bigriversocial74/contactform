<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/privacy/account-erasure.php';
require_once dirname(__DIR__,2) . '/includes/privacy/admin-operations.php';
require_once dirname(__DIR__,2) . '/includes/privacy/finalization-operations.php';

$actor = mg_require_api_user();
$actorId = (int) $actor['id'];
$permissions = is_array($actor['permissions'] ?? null) ? $actor['permissions'] : [];
$roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
$isSuper = in_array('super_admin',$roles,true);
$canView = $isSuper || in_array('admin.privacy_requests.view',$permissions,true) || in_array('admin.privacy_requests.manage',$permissions,true);
$canManage = $isSuper || in_array('admin.privacy_requests.manage',$permissions,true);
if (!$canView) mg_fail('Permission denied.',403);
$pdo = mg_db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $requestId = max(0,(int) ($_GET['request_id'] ?? 0));
    if ($requestId > 0) {
        $item = mg_privacy_admin_request_detail($pdo,$requestId);
        if (!$item) mg_fail('Privacy request not found.',404);
        mg_ok(['item'=>$item,'can_manage'=>$canManage]);
    }

    $items = mg_privacy_list_requests($pdo,[
        'status'=>trim((string) ($_GET['status'] ?? '')),
        'jurisdiction'=>trim((string) ($_GET['jurisdiction'] ?? '')),
        'q'=>trim((string) ($_GET['q'] ?? '')),
    ]);
    mg_ok([
        'items'=>$items,
        'can_manage'=>$canManage,
        'statuses'=>['submitted','identity_verified','acknowledged','under_review','approved','restricted','blocked_by_hold','processing','completed','partially_completed','denied','cancelled'],
    ]);
}

mg_require_method('POST');
if (!$canManage) mg_fail('Permission denied.',403);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.privacy_requests.write','user:'.$actorId,120,60);
$action = strtolower(trim((string) ($input['action'] ?? '')));
$requestId = max(0,(int) ($input['request_id'] ?? 0));
$reason = trim((string) ($input['reason'] ?? ''));

try {
    $pdo->beginTransaction();

    if ($action === 'create_admin_request') {
        $item = mg_privacy_create_admin_delete_request($pdo,$actorId,$input);
        $requestId = (int) ($item['id'] ?? 0);
        $targetUserId = (int) ($item['user_id'] ?? 0);
        if ($requestId > 0 && $targetUserId > 0) {
            $dueAt = (string) ($item['extended_due_at'] ?: $item['response_due_at']);
            mg_privacy_create_operational_handoffs($pdo,$requestId,$targetUserId,$dueAt);
            $item = mg_privacy_admin_request_detail($pdo,$requestId) ?? $item;
        }
        $pdo->commit();
        mg_audit('admin.privacy.create_admin_request','privacy_request',['request_id'=>$requestId,'target_user_id'=>$targetUserId ?: null,'reason'=>$reason],$actorId);
        mg_security_log('info','admin.privacy.request_created','Administrative privacy request created.',['request_id'=>$requestId],$actorId);
        $message = !empty($item['existing']) ? 'Existing active privacy request loaded.' : 'Administrative privacy request created.';
        mg_ok(['item'=>$item],$message,!empty($item['existing']) ? 200 : 201);
    }

    if ($requestId < 1) throw new RuntimeException('Valid request ID required.');
    if (in_array($action,['deny','extend','add_hold','release_hold'],true) && (mb_strlen($reason) < 8 || mb_strlen($reason) > 500)) {
        throw new RuntimeException('Provide a reason between 8 and 500 characters.');
    }

    $request = mg_privacy_request_by_id($pdo,$requestId,true);
    if (!$request) throw new RuntimeException('Privacy request not found.');
    $userId = (int) ($request['user_id'] ?? 0);
    $result = [];

    switch ($action) {
        case 'acknowledge':
            $pdo->prepare('UPDATE privacy_requests SET status=IF(status IN ("submitted","identity_verified"),"acknowledged",status),acknowledged_at=COALESCE(acknowledged_at,NOW()),assigned_to_user_id=COALESCE(assigned_to_user_id,?),updated_at=NOW() WHERE id=?')->execute([$actorId,$requestId]);
            mg_privacy_event($pdo,$requestId,'request_acknowledged',[],$actorId);
            $result = ['status'=>'acknowledged'];
            break;

        case 'approve':
            if (in_array((string) $request['status'],['completed','denied','cancelled'],true)) {
                throw new RuntimeException('This request can no longer be approved.');
            }
            $approvalReason = $reason !== '' ? $reason : 'Approved after administrative privacy review.';
            $pdo->prepare('UPDATE privacy_requests SET decision="approve",status=IF(status IN ("submitted","identity_verified","acknowledged","under_review","approved"),"approved",status),decision_reason=?,assigned_to_user_id=?,updated_at=NOW() WHERE id=?')->execute([$approvalReason,$actorId,$requestId]);
            if ($userId > 0 && empty($request['restricted_at'])) {
                mg_privacy_assert_account_restriction_safe($pdo,$userId);
                $result = mg_privacy_restrict_account($pdo,$requestId,$userId);
            }
            if ($userId > 0) {
                $due = (string) ($request['grace_ends_at'] ?: $request['response_due_at']);
                $pdo->prepare('UPDATE users SET deletion_due_at=? WHERE id=?')->execute([$due,$userId]);
                mg_privacy_create_operational_handoffs($pdo,$requestId,$userId,(string) ($request['extended_due_at'] ?: $request['response_due_at']));
            }
            if ($userId > 0 && mg_privacy_active_hold($pdo,$requestId,$userId)) {
                $pdo->prepare('UPDATE privacy_requests SET status="blocked_by_hold",updated_at=NOW() WHERE id=?')->execute([$requestId]);
                $result['status'] = 'blocked_by_hold';
            } else {
                $result['status'] = 'restricted';
            }
            mg_privacy_event($pdo,$requestId,'request_approved',['reason'=>$approvalReason],$actorId);
            break;

        case 'deny':
            if (in_array((string) $request['status'],['processing','completed'],true)) {
                throw new RuntimeException('A processing or completed request cannot be denied.');
            }
            $pdo->prepare('UPDATE privacy_requests SET status="denied",decision="deny",decision_reason=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$reason,$requestId]);
            if ($userId > 0 && mg_privacy_column_exists($pdo,'users','privacy_state')) {
                $pdo->prepare('UPDATE users SET status="active",privacy_state="active",deletion_requested_at=NULL,deletion_due_at=NULL,privacy_restricted_at=NULL,updated_at=NOW() WHERE id=? AND privacy_state<>"anonymized"')->execute([$userId]);
            }
            mg_privacy_event($pdo,$requestId,'request_denied',['reason'=>$reason],$actorId);
            $result = ['status'=>'denied'];
            break;

        case 'extend':
            $newDue = trim((string) ($input['new_due_at'] ?? ''));
            $date = DateTimeImmutable::createFromFormat('!Y-m-d',$newDue,new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d') !== $newDue) throw new RuntimeException('Provide a valid extension date.');
            $newDueAt = $date->setTime(23,59,59);
            $originalDue = new DateTimeImmutable((string) $request['response_due_at'],new DateTimeZone('UTC'));
            $max = $originalDue->modify('+2 months');
            if ($newDueAt <= $originalDue) throw new RuntimeException('The extension date must be later than the original response deadline.');
            if ($newDueAt > $max) throw new RuntimeException('The extension exceeds the supported two-month maximum.');
            $dueSql = mg_privacy_dt($newDueAt);
            $pdo->prepare('UPDATE privacy_requests SET extended_due_at=?,grace_ends_at=IF(grace_ends_at IS NULL OR grace_ends_at<?,?,grace_ends_at),extension_reason=?,updated_at=NOW() WHERE id=?')->execute([$dueSql,$dueSql,$dueSql,$reason,$requestId]);
            if ($userId > 0) $pdo->prepare('UPDATE users SET deletion_due_at=? WHERE id=?')->execute([$dueSql,$userId]);
            $pdo->prepare('UPDATE privacy_merchant_handoffs SET due_at=?,updated_at=NOW() WHERE request_id=? AND status NOT IN ("completed","not_applicable")')->execute([$dueSql,$requestId]);
            mg_privacy_event($pdo,$requestId,'deadline_extended',['new_due_at'=>$dueSql,'reason'=>$reason],$actorId);
            $result = ['extended_due_at'=>$dueSql];
            break;

        case 'add_hold':
            $scope = trim((string) ($input['scope'] ?? 'all'));
            $stmt = $pdo->prepare('INSERT INTO privacy_legal_holds (request_id,user_id,status,reason,scope_json,placed_by_user_id,placed_at) VALUES (?,? ,"active",?,?,?,NOW())');
            $stmt->execute([$requestId,$userId ?: null,$reason,json_encode(['scope'=>$scope],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$actorId]);
            $holdId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE privacy_requests SET status="blocked_by_hold",updated_at=NOW() WHERE id=?')->execute([$requestId]);
            mg_privacy_event($pdo,$requestId,'legal_hold_added',['hold_id'=>$holdId,'reason'=>$reason,'scope'=>$scope],$actorId);
            $result = ['hold_id'=>$holdId,'status'=>'blocked_by_hold'];
            break;

        case 'release_hold':
            $holdId = (int) ($input['hold_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE privacy_legal_holds SET status="released",released_by_user_id=?,released_at=NOW(),release_reason=? WHERE id=? AND request_id=? AND status="active"');
            $stmt->execute([$actorId,$reason,$holdId,$requestId]);
            if ($stmt->rowCount() < 1) throw new RuntimeException('Active legal hold not found.');
            if (!mg_privacy_active_hold($pdo,$requestId,$userId ?: null)) {
                $pdo->prepare('UPDATE privacy_requests SET status=IF(restricted_at IS NULL,"under_review","restricted"),updated_at=NOW() WHERE id=?')->execute([$requestId]);
            }
            mg_privacy_event($pdo,$requestId,'legal_hold_released',['hold_id'=>$holdId,'reason'=>$reason],$actorId);
            $result = ['hold_id'=>$holdId,'released'=>true];
            break;

        case 'handoff_complete':
            $handoffId = (int) ($input['handoff_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE privacy_merchant_handoffs SET status="completed",completed_at=NOW(),notes=CONCAT_WS("\n",notes,?),updated_at=NOW() WHERE id=? AND request_id=?');
            $stmt->execute([$reason !== '' ? $reason : 'Controller or operational handoff completed.',$handoffId,$requestId]);
            if ($stmt->rowCount() < 1) throw new RuntimeException('Merchant handoff not found.');
            mg_privacy_event($pdo,$requestId,'merchant_handoff_completed',['handoff_id'=>$handoffId],$actorId);
            $result = ['handoff_id'=>$handoffId,'completed'=>true];
            break;

        case 'finalize':
            $result = mg_privacy_finalize_with_operations($pdo,$requestId,$actorId,!empty($input['force']));
            break;

        default:
            throw new RuntimeException('Unsupported privacy action.');
    }

    $pdo->commit();
    mg_audit('admin.privacy.'.$action,'privacy_request',['request_id'=>$requestId,'reason'=>$reason,'result'=>$result],$actorId);
    mg_security_log('info','admin.privacy.completed','Privacy administration action completed.',['request_id'=>$requestId,'action'=>$action],$actorId);
    mg_ok(['result'=>$result,'item'=>mg_privacy_admin_request_detail($pdo,$requestId)],'Privacy action completed.');
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning','admin.privacy.rejected','Privacy administration action rejected.',['request_id'=>$requestId,'action'=>$action,'reason'=>$error->getMessage()],$actorId);
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error,'admin.privacy.failed','Unable to complete the privacy action.',500,['request_id'=>$requestId,'action'=>$action],$actorId);
}
