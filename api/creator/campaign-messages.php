<?php
declare(strict_types=1);
require_once __DIR__.'/_creator.php';
require_once dirname(__DIR__,2).'/includes/creator-campaigns.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_api_user();
$pdo=mg_db();
$actorUserId=(int)($user['id']??0);
$action='';
try {
    if ($method==='GET') {
        $context=mg_creator_campaign_message_creator_context($pdo,$user,'creator.campaign_messages.view_own');
        mg_ok([
            'summary'=>mg_creator_campaign_message_summary($pdo,'creator_user_id',(int)$context['creator_user_id'],$actorUserId),
            'threads'=>mg_creator_campaign_message_list($pdo,'mc.creator_user_id',(int)$context['creator_user_id'],$actorUserId),
        ]);
    }
    if ($method!=='POST') mg_fail('Method not allowed.',405);
    $input=mg_input();
    mg_require_csrf_for_write($input);
    $action=strtolower(trim((string)($input['action']??'')));
    $context=mg_creator_campaign_message_creator_context($pdo,$user,'creator.campaign_messages.send_own');
    $participant=mg_creator_campaign_message_participant_for_creator($pdo,(int)$context['creator_user_id'],trim((string)($input['participant_id']??'')),false);
    $data=match($action){
        'open'=>mg_creator_campaign_message_open($pdo,$participant,$actorUserId),
        'send'=>mg_creator_campaign_message_send($pdo,$participant,$actorUserId,$input),
        default=>throw new InvalidArgumentException('Creator Campaign message action is invalid.'),
    };
    mg_ok($data,'Creator Campaign message updated.');
} catch (InvalidArgumentException $e) { mg_fail($e->getMessage(),422);
} catch (DomainException $e) { mg_fail($e->getMessage(),409);
} catch (RuntimeException $e) { $m=strtolower($e->getMessage());mg_fail($e->getMessage(),str_contains($m,'schema is incomplete')?503:(str_contains($m,'not found')?404:409));
} catch (PDOException $e) { if((string)$e->getCode()==='23000')mg_fail('The request conflicts with an existing Creator Campaign message.',409);mg_fail_unexpected($e,'creator.campaign_messages.database_failure','Unable to update Creator Campaign messages.',500,['action'=>$action],$actorUserId);
} catch (Throwable $e) { mg_fail_unexpected($e,'creator.campaign_messages.failure','Unable to process Creator Campaign messages.',500,['action'=>$action],$actorUserId); }
