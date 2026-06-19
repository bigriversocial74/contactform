<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/commerce/_checkout.php';
require_once dirname(__DIR__).'/api/payments/_checkout_session.php';
require_once dirname(__DIR__).'/api/microgifts/_golden_path_integrity.php';
require_once dirname(__DIR__).'/tests/integration/GoldenPathFixture.php';
require_once dirname(__DIR__).'/tests/integration/GoldenPathClaimBehavior.php';

$pdo=mg_db();
$runId='lifecycle_'.bin2hex(random_bytes(8));
$summary=['suite'=>'lifecycle_completion','run_id'=>$runId];
$pdo->beginTransaction();
try{
    $fixture=mg_golden_fixture($pdo,$runId);
    if((int)$fixture['capture']['microgift_issued_count']!==2)throw new RuntimeException('Issuance count mismatch.');
    $claim=mg_golden_claim_behavior($pdo,$fixture,$runId);
    $complete='mg_microgift_integrity_'.'redeem';
    $blocked=false;
    try{$complete($pdo,(int)$fixture['buyerId'],[
        'instance_id'=>(string)$fixture['microgift']['public_id'],
        'idempotency_key'=>'blocked-'.$runId,
        'source_reference'=>(string)$fixture['microgift']['action_item_public_id'],
        'merchant_user_id'=>(int)$fixture['merchantId'],
        'location_reference'=>(string)$fixture['otherLocationPublic'],
    ]);}catch(RuntimeException){$blocked=true;}
    if(!$blocked)throw new RuntimeException('Restricted location was accepted.');
    $key='complete-'.$runId;
    $result=$complete($pdo,(int)$fixture['buyerId'],[
        'instance_id'=>(string)$fixture['microgift']['public_id'],
        'idempotency_key'=>$key,
        'source_reference'=>(string)$fixture['microgift']['action_item_public_id'],
        'merchant_user_id'=>(int)$fixture['merchantId'],
        'location_reference'=>(string)$fixture['location']['public_id'],
    ]);
    if(!empty($result['duplicate']))throw new RuntimeException('Unexpected duplicate result.');
    $again=$complete($pdo,(int)$fixture['buyerId'],[
        'instance_id'=>(string)$fixture['microgift']['public_id'],
        'idempotency_key'=>$key,
        'source_reference'=>(string)$fixture['microgift']['action_item_public_id'],
        'merchant_user_id'=>(int)$fixture['merchantId'],
        'location_reference'=>(string)$fixture['location']['public_id'],
    ]);
    if(empty($again['duplicate']))throw new RuntimeException('Replay was not stable.');
    $summary+=['capture'=>true,'claim'=>true,'location_restriction'=>true,'completion'=>true,'replay'=>true];
    $pdo->rollBack();
    $summary['fixtures_clean']=(int)mg_golden_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?,?)',[$fixture['buyerEmail'],$fixture['merchantEmail'],$fixture['otherEmail']])===0;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$error->getMessage();fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $error;}
