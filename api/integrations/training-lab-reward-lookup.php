<?php
declare(strict_types=1);

require_once __DIR__ . '/_training_lab_reward_lookup.php';

try {
    mg_require_method('POST');
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($contentType, 'application/json')) {
        throw new MgTrainingLabRewardLookupException('This endpoint requires application/json.', 415, 'content_type_invalid');
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody)) {
        throw new MgTrainingLabRewardLookupException('The request body could not be read.', 400, 'request_body_unavailable');
    }

    $authentication = mg_training_lab_reward_lookup_authenticate($rawBody);
    $result = mg_training_lab_reward_lookup_result(mg_db(), (array)$authentication['payload']);
    mg_training_lab_reward_lookup_audit($authentication, $result);
    mg_training_lab_reward_lookup_complete($authentication, $result);
    mg_ok($result, 'Training Lab reward lookup completed.');
} catch (MgTrainingLabRewardLookupException $e) {
    mg_security_log('warning', 'training_lab.reward_lookup_rejected', 'Signed Training Lab reward lookup was rejected.', [
        'error_code'=>$e->errorCode(),
        'http_status'=>$e->httpStatus(),
    ]);
    mg_json([
        'ok'=>false,
        'message'=>$e->getMessage(),
        'error_code'=>$e->errorCode(),
    ], $e->httpStatus());
} catch (Throwable $e) {
    mg_security_log('error', 'training_lab.reward_lookup_failed', 'Signed Training Lab reward lookup failed.', [
        'exception_type'=>get_class($e),
    ]);
    mg_json([
        'ok'=>false,
        'message'=>'The Training Lab reward lookup could not be completed.',
        'error_code'=>'lookup_failed',
    ], 500);
}
