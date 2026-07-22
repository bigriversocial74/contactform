<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$actorUserId = (int) ($user['id'] ?? 0);

try {
    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'dashboard')));
        $data = match ($action) {
            'dashboard' => mg_creator_campaign_deliverable_dashboard_merchant($pdo,$user),
            'deliverables' => mg_creator_campaign_deliverable_list_merchant($pdo,$user,$_GET),
            'assignments' => mg_creator_campaign_assignment_list_merchant($pdo,$user,$_GET),
            'submissions' => mg_creator_campaign_submission_list_merchant($pdo,$user,$_GET),
            'submission_detail' => mg_creator_campaign_submission_detail_merchant($pdo,$user,(string)($_GET['submission_id']??'')),
            default => throw new RuntimeException('Creator deliverables route not found.'),
        };
        mg_ok($data);
    }
    if ($method !== 'POST') mg_fail('Method not allowed.',405);
    $input=mg_input(); mg_require_csrf_for_write($input); $action=strtolower(trim((string)($input['action']??'')));
    $data=match($action){
        'save_deliverable'=>mg_creator_campaign_deliverable_save_merchant($pdo,$user,(string)($input['campaign_id']??''),$input),
        'retire_deliverable'=>mg_creator_campaign_deliverable_retire_merchant($pdo,$user,(string)($input['deliverable_id']??''),$input),
        'sync_assignments'=>mg_creator_campaign_deliverable_sync_merchant($pdo,$user,(string)($input['campaign_id']??'')),
        'assign_deliverable'=>mg_creator_campaign_deliverable_assign_merchant($pdo,$user,(string)($input['participant_id']??''),(string)($input['deliverable_id']??'')),
        'transition_assignment'=>mg_creator_campaign_assignment_transition_merchant($pdo,$user,(string)($input['assignment_id']??''),(string)($input['to_status']??''),$input),
        'review_submission'=>mg_creator_campaign_submission_review_merchant($pdo,$user,(string)($input['submission_id']??''),(string)($input['decision']??''),$input),
        default=>throw new InvalidArgumentException('Creator deliverables action is invalid.'),
    };
    mg_ok($data,'Creator deliverables updated.');
} catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);
} catch(DomainException $error){mg_fail($error->getMessage(),409);
} catch(RuntimeException $error){$message=strtolower($error->getMessage());$status=str_contains($message,'schema is incomplete')?503:(str_contains($message,'not found')?404:409);mg_fail($error->getMessage(),$status);
} catch(PDOException $error){if((string)$error->getCode()==='23000')mg_fail('The deliverables request conflicts with an existing campaign record.',409);mg_fail_unexpected($error,'merchant.creator_deliverables.database_failure','Unable to update creator deliverables because of a database error.',500,['action'=>$action??null],$actorUserId);
} catch(Throwable $error){mg_fail_unexpected($error,'merchant.creator_deliverables.failure','Unable to process the creator deliverables request.',500,['action'=>$action??null],$actorUserId);}
