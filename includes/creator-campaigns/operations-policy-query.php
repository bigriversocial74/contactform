<?php
declare(strict_types=1);

/**
 * Add the canonical merchant workspace name to Creator-visible payout policies.
 *
 * Only the public workspace display name is exposed. Account owner identity,
 * contact details, and other merchant profile fields remain private.
 */
function mg_creator_campaign_operations_creator_policy_views(PDO $pdo,int $creatorUserId): array
{
    $policies=mg_creator_campaign_operations_creator_policies($pdo,$creatorUserId);
    if($policies===[])return [];

    $workspaceIds=array_values(array_unique(array_filter(array_map(
        static fn(array $policy):int=>(int)($policy['workspace_id']??0),
        $policies
    ),static fn(int $workspaceId):bool=>$workspaceId>0)));

    $names=[];
    if($workspaceIds!==[]){
        $marks=implode(',',array_fill(0,count($workspaceIds),'?'));
        $stmt=$pdo->prepare("SELECT id,display_name FROM merchant_workspaces WHERE id IN ({$marks})");
        $stmt->execute($workspaceIds);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){
            $names[(int)$row['id']]=trim((string)$row['display_name']);
        }
    }

    foreach($policies as &$policy){
        $workspaceId=(int)($policy['workspace_id']??0);
        $policy['merchant_name']=$names[$workspaceId]??'Merchant';
    }
    unset($policy);

    return $policies;
}
