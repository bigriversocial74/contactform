<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-search.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.ai.review');
mg_merchant_require_permission('merchant.campaigns.view');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$actorId = (int)($user['id'] ?? 0);
$merchantId = (int)($workspace['merchant_user_id'] ?? $actorId);
$query = mg_merchant_crm_search_query($_GET['q'] ?? $_GET['query'] ?? '');
$limit = max(1, min(100, (int)($_GET['limit'] ?? 12)));
$offset = max(0, min(10000, (int)($_GET['offset'] ?? 0)));

if ($query === '') mg_ok(['schema_ready'=>true,'query'=>'','contacts'=>[],'total'=>0,'limit'=>$limit,'offset'=>$offset,'has_more'=>false]);
mg_rate_limit('merchant.agent.crm_search', 'user:' . $actorId, 180, 60);

try {
    $result = mg_merchant_crm_search($pdo, $merchantId, $query, $limit, $offset);
    if (function_exists('mg_audit')) {
        try {
            mg_audit('merchant.agent_crm_search.read', 'merchant_crm', [
                'merchant_user_id'=>$merchantId,
                'query_length'=>mb_strlen($query),
                'result_count'=>count($result['contacts'] ?? []),
                'total'=>(int)($result['total'] ?? 0),
            ], $actorId);
        } catch (Throwable) {}
    }
    mg_ok($result);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.agent_crm_search.unavailable', 'Merchant Agent CRM search was unavailable.', [
        'exception_class'=>$error::class,
        'query_length'=>mb_strlen($query),
    ], $actorId);
    mg_fail('Unable to search Merchant CRM contacts.', 500);
}
