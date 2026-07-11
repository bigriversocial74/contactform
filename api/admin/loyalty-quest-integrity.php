<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_integrity.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$requiredPermission=$method==='GET'?'admin.operations_command.view':'admin.operations_command.manage';
$admin=mg_require_permission($requiredPermission);
$adminId=(int)$admin['id'];
$pdo=mg_db();

if($method==='GET'){
    try{
        $status=strtolower(trim((string)($_GET['status']??'open')));
        $severity=strtolower(trim((string)($_GET['severity']??'all')));
        $campaignRef=strtolower(trim((string)($_GET['campaign_id']??'')));
        $query=trim((string)($_GET['q']??''));
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok([
            'summary'=>mg_lqi_admin_summary($pdo),
            'signals'=>mg_lqi_admin_signals($pdo,$status,$severity,$campaignRef,$query,250),
            'campaigns'=>mg_lqi_admin_campaigns($pdo),
            'authority'=>[
                'can_acknowledge'=>true,
                'can_clear'=>true,
                'can_confirm'=>true,
                'can_approve_evidence'=>false,
                'can_issue_rewards'=>false,
                'can_redeem_pppm'=>false,
            ],
        ],'Loyalty Quest integrity operations loaded.');
    }catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);}catch(Throwable $error){mg_security_log('error','admin.loyalty_quest_integrity_load_failed','Unable to load Loyalty Quest integrity operations.',['exception_class'=>$error::class],$adminId);mg_fail('Unable to load Loyalty Quest integrity operations.',500);}
}

if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$signalRef=strtolower(trim((string)($input['signal_id']??'')));
$resolution=strtolower(trim((string)($input['resolution']??'')));
$reason=mg_lqo_require_reason($input);
if(strlen($signalRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$signalRef)!==1)mg_fail('Invalid integrity signal.',422);
$pdo->beginTransaction();
try{
    $result=mg_lqi_admin_resolve($pdo,$adminId,$signalRef,$resolution,$reason);
    $pdo->commit();
    mg_ok($result,'Loyalty Quest integrity signal updated.');
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),422);}catch(DomainException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),409);}catch(RuntimeException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),404);}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','admin.loyalty_quest_integrity_action_failed','Unable to update Loyalty Quest integrity signal.',['exception_class'=>$error::class,'resolution'=>$resolution],$adminId);mg_fail('Unable to update Loyalty Quest integrity signal.',500);}
