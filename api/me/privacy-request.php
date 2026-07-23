<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/privacy/account-erasure.php';
require_once dirname(__DIR__,2) . '/includes/privacy/finalization-operations.php';

$user = mg_require_api_user();
$userId = (int) $user['id'];
$pdo = mg_db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $request = mg_privacy_request_for_user($pdo,$userId);
    if ($request) {
        unset($request['contact_email']);
        $request['events'] = [];
        $events = $pdo->prepare('SELECT event_type,details_json,created_at FROM privacy_request_events WHERE request_id=? ORDER BY id DESC LIMIT 20');
        $events->execute([(int) $request['id']]);
        foreach ($events->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $event['details'] = json_decode((string) ($event['details_json'] ?? ''),true) ?: [];
            unset($event['details_json']);
            $request['events'][] = $event;
        }
    }
    mg_ok(['request'=>$request,'jurisdictions'=>[
        'eu_eea'=>'European Union / EEA','uk'=>'United Kingdom','california'=>'California','other_us'=>'Other United States','other'=>'Other / not listed',
    ]]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('privacy.request.create','user:'.$userId,3,86400);
if (empty($input['understood'])) {
    mg_fail('Confirm that you understand the account will be disabled and the governed erasure process will begin.',422);
}

try {
    $pdo->beginTransaction();
    mg_privacy_assert_account_restriction_safe($pdo,$userId);
    $request = mg_privacy_create_delete_request($pdo,$user,$input);
    $requestId = (int) ($request['id'] ?? 0);
    if ($requestId > 0) {
        $request = mg_privacy_request_by_id($pdo,$requestId,true) ?? $request;
        $dueAt = (string) ($request['extended_due_at'] ?: $request['response_due_at']);
        mg_privacy_create_operational_handoffs($pdo,$requestId,$userId,$dueAt);
        $request = mg_privacy_request_by_id($pdo,$requestId,true) ?? $request;
    }
    $pdo->commit();
    mg_security_log('info','privacy.request.created','Verified account-erasure request created.',['request_id'=>$requestId],$userId);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
        }
        session_destroy();
    }
    unset($request['contact_email']);
    mg_ok(['request'=>$request,'signed_out'=>true],'Your account is closed and the verified deletion request is now being processed.',202);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning','privacy.request.rejected','Privacy request rejected.',['reason'=>$error->getMessage()],$userId);
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error,'privacy.request.failed','Unable to submit the privacy request.',500,[],$userId);
}
