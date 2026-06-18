<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

function mg_action_center_expected_projection_users(array $instance): array
{
    $sender=(int)($instance['issuer_user_id']??0);
    $recipient=(int)($instance['recipient_user_id']??$instance['owner_user_id']??0);
    $expected=[];
    if($sender>0&&$sender!==$recipient)$expected[$sender]='sent';
    if($recipient>0)$expected[$recipient]=mg_action_center_recipient_folder($instance);
    return $expected;
}

function mg_action_center_reconcile_instance(PDO $pdo,array $instance,bool $repair=true): array
{
    $instanceId=(int)$instance['id'];
    $expected=mg_action_center_expected_projection_users($instance);
    $stmt=$pdo->prepare('SELECT id,public_id,user_id,folder,state,archived_at FROM microgift_inbox_items WHERE instance_id=? ORDER BY id ASC FOR UPDATE');
    $stmt->execute([$instanceId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $actual=[];
    foreach($rows as $row)$actual[(int)$row['user_id']]=$row;

    $drift=[];
    foreach($expected as $userId=>$folder){
        $row=$actual[$userId]??null;
        if(!$row){
            $drift[]=['type'=>'missing','user_id'=>$userId,'expected_folder'=>$folder];
            continue;
        }
        if((string)$row['folder']!==$folder||(string)$row['state']!==(string)$instance['status']){
            $drift[]=[
                'type'=>'mismatch','user_id'=>$userId,'item_id'=>$row['public_id'],
                'actual_folder'=>$row['folder'],'expected_folder'=>$folder,
                'actual_state'=>$row['state'],'expected_state'=>$instance['status'],
            ];
        }
    }
    foreach($actual as $userId=>$row){
        if(!isset($expected[$userId])){
            $drift[]=['type'=>'orphan','user_id'=>$userId,'item_id'=>$row['public_id'],'folder'=>$row['folder'],'state'=>$row['state']];
        }
    }

    $repaired=false;
    if($repair&&$drift){
        mg_action_center_project_lifecycle($pdo,$instance);
        foreach($actual as $userId=>$row){
            if(!isset($expected[$userId])){
                $pdo->prepare('UPDATE microgift_inbox_items SET archived_at=COALESCE(archived_at,NOW()),updated_at=NOW() WHERE id=?')
                    ->execute([(int)$row['id']]);
            }
        }
        $repaired=true;
    }

    return [
        'instance_id'=>(string)$instance['public_id'],
        'drift'=>$drift,
        'drift_count'=>count($drift),
        'repaired'=>$repaired,
    ];
}

function mg_action_center_reconcile_batch(PDO $pdo,int $afterId=0,int $limit=100,bool $repair=true): array
{
    $limit=max(1,min($limit,500));
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE id>? ORDER BY id ASC LIMIT '.$limit);
    $stmt->execute([$afterId]);
    $instances=$stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary=['scanned'=>0,'drifted'=>0,'repaired'=>0,'issues'=>0,'next_after_id'=>$afterId,'items'=>[]];
    foreach($instances as $instance){
        $pdo->beginTransaction();
        try{
            $locked=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
            $result=mg_action_center_reconcile_instance($pdo,$locked,$repair);
            $pdo->commit();
        }catch(Throwable $error){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $error;
        }
        $summary['scanned']++;
        $summary['next_after_id']=(int)$instance['id'];
        if($result['drift_count']>0){
            $summary['drifted']++;
            $summary['issues']+=$result['drift_count'];
            if($result['repaired'])$summary['repaired']++;
            $summary['items'][]=$result;
        }
    }
    $summary['has_more']=count($instances)===$limit;
    $summary['mode']=$repair?'repair':'audit';
    return $summary;
}
