<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_api_user();$pdo=mg_db();$actorUserId=(int)($user['id']??0);
try{
    if($method==='GET'){$action=strtolower(trim((string)($_GET['action']??'assignments')));$data=match($action){
        'assignments'=>mg_creator_campaign_assignment_list_creator($pdo,$user,$_GET),
        'submissions'=>mg_creator_campaign_submission_list_creator($pdo,$user),
        'submission_detail'=>mg_creator_campaign_submission_detail_creator($pdo,$user,(string)($_GET['submission_id']??'')),
        default=>throw new RuntimeException('Creator deliverables route not found.'),
    };mg_ok($data);}
    if($method!=='POST')mg_fail('Method not allowed.',405);$input=mg_input();mg_require_csrf_for_write($input);$action=strtolower(trim((string)($input['action']??'')));$data=match($action){
        'sync_assignments'=>mg_creator_campaign_deliverable_sync_creator($pdo,$user),
        'save_submission'=>mg_creator_campaign_submission_save_creator($pdo,$user,(string)($input['assignment_id']??''),$input,false),
        'submit_submission'=>mg_creator_campaign_submission_save_creator($pdo,$user,(string)($input['assignment_id']??''),$input,true),
        'withdraw_submission'=>mg_creator_campaign_submission_withdraw_creator($pdo,$user,(string)($input['submission_id']??''),$input),
        'submit_publication_proof'=>mg_creator_campaign_submission_publication_proof_creator($pdo,$user,(string)($input['submission_id']??''),$input),
        default=>throw new InvalidArgumentException('Creator deliverables action is invalid.'),
    };mg_ok($data,'Campaign submission updated.');
}catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);
}catch(DomainException $error){mg_fail($error->getMessage(),409);
}catch(RuntimeException $error){$message=strtolower($error->getMessage());$status=str_contains($message,'schema is incomplete')?503:(str_contains($message,'not found')?404:409);mg_fail($error->getMessage(),$status);
}catch(PDOException $error){if((string)$error->getCode()==='23000')mg_fail('This campaign deliverable already has a submission record.',409);mg_fail_unexpected($error,'creator.campaign_deliverables.database_failure','Unable to update the campaign submission because of a database error.',500,['action'=>$action??null],$actorUserId);
}catch(Throwable $error){mg_fail_unexpected($error,'creator.campaign_deliverables.failure','Unable to process the campaign deliverables request.',500,['action'=>$action??null],$actorUserId);}
