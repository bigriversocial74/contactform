<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/account/_action_center_reconciliation.php';

$options=getopt('', ['repair','after::','limit::','all']);
$repair=array_key_exists('repair',$options);
$after=max(0,(int)($options['after']??0));
$limit=max(1,min((int)($options['limit']??100),500));
$all=array_key_exists('all',$options);
$totals=['scanned'=>0,'drifted'=>0,'repaired'=>0,'issues'=>0];

do{
    $result=mg_action_center_reconcile_batch(mg_db(),$after,$limit,$repair);
    foreach($totals as $key=>$value)$totals[$key]+=(int)$result[$key];
    $after=(int)$result['next_after_id'];
    fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
}while($all&&$result['has_more']);

fwrite(STDOUT,json_encode(['mode'=>$repair?'repair':'audit','totals'=>$totals,'next_after_id'=>$after],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
