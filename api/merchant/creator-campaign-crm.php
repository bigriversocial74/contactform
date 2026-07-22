<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$user = mg_require_api_user();
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actorUserId = (int)($user['id'] ?? 0);

try {
    if ($method === 'GET') {
        $action = strtolower(trim((string)($_GET['action'] ?? 'list')));
        if ($action === 'list') {
            mg_ok(mg_creator_campaign_crm_list($pdo,$user,$_GET));
        }
        if ($action === 'runs') {
            mg_ok(mg_creator_campaign_crm_runs($pdo,$user,(int)($_GET['limit']??20)));
        }
        mg_fail('Unsupported Creator Campaign CRM request.',404);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.',405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action']??'')));
    if ($action === 'sync') {
        $campaignPublicId = trim((string)($input['campaign_id']??''));
        mg_ok([
            'reconciliation'=>mg_creator_campaign_crm_reconcile(
                $pdo,$user,$campaignPublicId===''?null:$campaignPublicId,(int)($input['limit']??250)
            ),
        ],'Creator Campaign CRM reconciliation completed.');
    }
    mg_fail('Unsupported Creator Campaign CRM action.',405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(),422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(),403);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    mg_fail($error->getMessage(),str_contains($message,'schema is incomplete')?503:(str_contains($message,'not found')?404:409));
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,'creator_campaign.crm.api_failure','Unable to process the Creator Campaign CRM request.',500,
        ['method'=>$method,'action'=>$action??null],$actorUserId
    );
}
