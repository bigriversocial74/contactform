<?php
declare(strict_types=1);
require_once __DIR__.'/_queue_api.php';

$pdo = mg_db();
$input = $_GET;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($body)) {
        $input = $body + $_POST + $_GET;
    } else {
        $input = $_POST + $_GET;
    }
}
$action = (string) ($input['action'] ?? 'list');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if (in_array($action, ['assign', 'resolve'], true) && $method === 'GET') {
        throw new MgOpsQueueApiException('Method not allowed.', 405);
    }

    mg_require_csrf_for_write($input);

    $user = mg_require_api_user();
    $input['actor_user_id'] = (int) $user['id'];
    mg_json_response(['ok' => true, 'data' => mg_ops_queue_route($pdo, $action, $input)]);
} catch (MgOpsQueueApiException|MgOpsAlertException $e) {
    mg_json_response(['ok' => false, 'error' => $e->getMessage()], $e->httpStatus);
} catch (Throwable) {
    mg_json_response(['ok' => false, 'error' => 'Ops queue request failed.'], 500);
}
