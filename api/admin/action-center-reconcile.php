<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/account/_action_center_reconciliation.php';

mg_require_method('POST');
$user=mg_require_permission('microgift.lifecycle.manage');
$input=mg_input();
mg_require_csrf_for_write($input);

$afterId=max(0,(int)($input['after_id']??0));
$limit=max(1,min((int)($input['limit']??100),500));
$repair=(bool)($input['repair']??false);

try{
    $result=mg_action_center_reconcile_batch(mg_db(),$afterId,$limit,$repair);
    mg_audit('action_center.reconciliation_run','action_center',[
        'mode'=>$result['mode'],
        'scanned'=>$result['scanned'],
        'drifted'=>$result['drifted'],
        'repaired'=>$result['repaired'],
        'issues'=>$result['issues'],
        'next_after_id'=>$result['next_after_id'],
    ],(int)$user['id']);
    mg_ok($result,$repair?'Action Center reconciliation completed.':'Action Center audit completed.');
}catch(Throwable $error){
    mg_security_log('error','action_center.reconciliation_failed','Action Center reconciliation failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to reconcile Action Center projections.',500);
}
