<?php
declare(strict_types=1);
require_once __DIR__ . '/_training_lab_reward_pilot_issue.php';
try {
    mg_require_method('POST');
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($contentType, 'application/json')) throw new MgTrainingLabPilotIssueException('This endpoint requires application/json.', 415, 'content_type_invalid');
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody)) throw new MgTrainingLabPilotIssueException('The request body could not be read.', 400, 'request_body_unavailable');
    $authentication = mg_training_lab_pilot_issue_authenticate($rawBody);
    $result = mg_training_lab_pilot_issue_execute((array)$authentication['payload']);
    mg_training_lab_pilot_issue_audit($authentication, $result);
    mg_ok($result, 'Training Lab pilot reward issue completed.');
} catch (MgTrainingLabPilotIssueException $e) {
    mg_security_log('warning', 'training_lab.pilot_reward_issue_rejected', 'Signed Training Lab pilot reward issue was rejected.', ['error_code'=>$e->errorCode(),'http_status'=>$e->httpStatus()]);
    mg_json(['ok'=>false,'message'=>$e->getMessage(),'error_code'=>$e->errorCode()], $e->httpStatus());
} catch (Throwable $e) {
    mg_security_log('error', 'training_lab.pilot_reward_issue_failed', 'Signed Training Lab pilot reward issue failed.', ['exception_type'=>get_class($e)]);
    mg_json(['ok'=>false,'message'=>'The Training Lab pilot reward issue could not be completed.','error_code'=>'pilot_issue_failed'], 500);
}
