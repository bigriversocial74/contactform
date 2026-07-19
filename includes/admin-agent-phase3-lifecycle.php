<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase3.php';

function mg_admin_agent_phase3_reconcile_linked_ops_incidents(PDO $pdo): array
{
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT w.id,w.public_id,w.status workspace_status,o.public_id ops_public_id,o.status ops_status,o.title,o.updated_at FROM admin_agent_incident_workspaces w JOIN admin_ops_incidents o ON o.id=w.ops_incident_id');
    $reopened=0; $resolved=0;
    foreach($rows as $row){
        $opsStatus=(string)$row['ops_status'];
        $workspaceStatus=(string)$row['workspace_status'];
        if($opsStatus!=='resolved' && in_array($workspaceStatus,['resolved','dismissed'],true)){
            $target=in_array($opsStatus,['declared','investigating','mitigating','monitoring'],true)?$opsStatus:'investigating';
            $pdo->prepare('UPDATE admin_agent_incident_workspaces SET status=?,resolved_at=NULL,updated_at=NOW() WHERE id=?')->execute([$target,(int)$row['id']]);
            mg_admin_agent_phase3_timeline($pdo,(int)$row['id'],'workspace_reopened','Linked operations incident remains active','The workspace was reopened because operations incident '.(string)$row['ops_public_id'].' is still '.str_replace('_',' ',$opsStatus).'.','admin_ops_incidents',(string)$row['ops_public_id'],null,['ops_status'=>$opsStatus]);
            $reopened++;
        }elseif($opsStatus==='resolved' && !in_array($workspaceStatus,['resolved','dismissed'],true)){
            $correlationActive=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_incident_workspaces w JOIN admin_agent_correlations c ON c.id=w.correlation_id WHERE w.id=? AND c.status IN ("open","acknowledged","under_review")',[(int)$row['id']])['total']??0);
            if($correlationActive===0){
                $pdo->prepare('UPDATE admin_agent_incident_workspaces SET status="resolved",resolved_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
                mg_admin_agent_phase3_timeline($pdo,(int)$row['id'],'workspace_resolved','Linked operations incident resolved','The linked operations incident and source correlation are both resolved.','admin_ops_incidents',(string)$row['ops_public_id'],null,['ops_status'=>$opsStatus]);
                $resolved++;
            }
        }
    }
    return ['reopened'=>$reopened,'resolved'=>$resolved];
}

function mg_admin_agent_phase3_run_hardened(PDO $pdo,array $options=[]): array
{
    $result=mg_admin_agent_phase3_run($pdo,$options);
    $lifecycle=mg_admin_agent_phase3_reconcile_linked_ops_incidents($pdo);
    if($lifecycle['reopened']>0||$lifecycle['resolved']>0){
        $result['causes']=mg_admin_agent_phase3_analyze_causes($pdo);
        $result['release_gate']=mg_admin_agent_phase3_evaluate_release($pdo,(string)($options['environment_key']??'production'));
    }
    $result['incident_lifecycle']=$lifecycle;
    return $result;
}
