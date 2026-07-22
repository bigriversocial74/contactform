<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_api_user();
$pdo=mg_db();
$actorUserId=(int)($user['id']??0);
$action='';
try {
    if ($method==='GET') {
        $context=mg_creator_campaign_message_merchant_context($pdo,$user,'merchant.creator_messages.view');
        $data=[
            'summary'=>mg_creator_campaign_message_summary($pdo,'merchant_workspace_id',(int)$context['workspace_id'],$actorUserId),
            'threads'=>mg_creator_campaign_message_list($pdo,'mc.merchant_workspace_id',(int)$context['workspace_id'],$actorUserId),
            'notes'=>[],
        ];
        try {
            mg_creator_campaign_require_permission($pdo,$user,$context['workspace'],'merchant.creator_notes.manage');
            $data['notes']=mg_creator_campaign_message_notes($pdo,(int)$context['workspace_id'],trim((string)($_GET['participant_id']??''))?:null);
        } catch (DomainException) {}
        mg_ok($data);
    }
    if ($method!=='POST') mg_fail('Method not allowed.',405);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));
    $permission=$action==='add_note'?'merchant.creator_notes.manage':'merchant.creator_messages.manage';
    $context=mg_creator_campaign_message_merchant_context($pdo,$user,$permission);
    $participant=mg_creator_campaign_message_participant_for_merchant($pdo,(int)$context['workspace_id'],trim((string)($input['participant_id']??'')),false);
    $data=match($action){
        'open'=>mg_creator_campaign_message_open($pdo,$participant,$actorUserId),
        'send'=>mg_creator_campaign_message_send($pdo,$participant,$actorUserId,$input),
        'add_note'=>mg_creator_campaign_message_add_internal_note($pdo,$participant,(int)$context['workspace_id'],$actorUserId,$input),
        'status'=>mg_creator_campaign_message_change_status($pdo,$participant,$actorUserId,strtolower(trim((string)($input['status']??''))),(int)($input['lock_version']??0)),
        default=>throw new InvalidArgumentException('Creator Campaign message action is invalid.'),
    };
    mg_ok($data,'Creator Campaign communication updated.');
} catch (InvalidArgumentException $e) { mg_fail($e->getMessage(),422);
} catch (DomainException $e) { mg_fail($e->getMessage(),409);
} catch (RuntimeException $e) { $m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
} catch (PDOException $e) { if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing Creator Campaign communication record.',409);mg_fail_unexpected($e,'merchant.creator_messages.database_failure','Unable to update Creator Campaign communications.',500,['action'=>$action],$actorUserId);
} catch (Throwable $e) { mg_fail_unexpected($e,'merchant.creator_messages.failure','Unable to process Creator Campaign communications.',500,['action'=>$action],$actorUserId); }
