<?php
declare(strict_types=1);

function mg_golden_claim_behavior(PDO $pdo,array $fixture,string $runId): array
{
    $key='golden-claim-'.$runId;
    $result=mg_microgift_integrity_claim($pdo,(int)$fixture['buyerId'],['instance_id'=>(string)$fixture['microgift']['public_id'],'idempotency_key'=>$key]);
    if(!empty($result['duplicate']))throw new RuntimeException('Unexpected replay result.');
    $instance=mg_microgift_load_instance($pdo,(string)$fixture['microgift']['public_id']);
    mg_action_center_project_lifecycle($pdo,$instance);
    if((string)$instance['status']!=='redeemable')throw new RuntimeException('Lifecycle state mismatch.');
    $replay=mg_microgift_integrity_claim($pdo,(int)$fixture['buyerId'],['instance_id'=>(string)$fixture['microgift']['public_id'],'idempotency_key'=>$key]);
    if(empty($replay['duplicate']))throw new RuntimeException('Replay result mismatch.');
    return ['claim'=>$result,'instance'=>$instance];
}
