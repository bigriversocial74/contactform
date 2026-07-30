<?php
declare(strict_types=1);

/**
 * Persist one unattended reconciliation scan for a merchant workspace.
 *
 * This does not mutate orders, earnings, budgets, payouts, or disputes. It only
 * records and resolves reconciliation cases from the read-only detectors.
 */
function mg_creator_campaign_operations_scan_workspace(PDO $pdo,int $workspaceId): array
{
    mg_creator_campaign_operations_assert_installed($pdo);
    if($workspaceId<1)throw new InvalidArgumentException('workspace_id is required.');

    $scanToken=bin2hex(random_bytes(16));
    $detected=mg_creator_campaign_operations_detect($pdo,$workspaceId);
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try{
        foreach($detected['candidates'] as $candidate){
            $fingerprint=hash('sha256',$candidate['type'].'|'.$candidate['sourceType'].'|'.$candidate['sourcePublicId']);
            $pdo->prepare("INSERT INTO creator_campaign_reconciliation_cases(public_id,workspace_id,fingerprint,issue_type,severity,source_type,source_public_id,campaign_public_id,status,summary,detail_json,scan_token,first_seen_at,last_seen_at)
              VALUES (?,?,?,?,?,?,?,?,'open',?,?,?,NOW(),NOW())
              ON DUPLICATE KEY UPDATE severity=VALUES(severity),campaign_public_id=VALUES(campaign_public_id),summary=VALUES(summary),detail_json=VALUES(detail_json),scan_token=VALUES(scan_token),last_seen_at=NOW(),resolved_at=IF(status='resolved',NULL,resolved_at),status=IF(status='resolved','open',status)")
                ->execute([
                    mg_creator_campaign_public_id('ccrc'),
                    $workspaceId,
                    $fingerprint,
                    $candidate['type'],
                    $candidate['severity'],
                    $candidate['sourceType'],
                    $candidate['sourcePublicId'],
                    $candidate['campaignPublicId'],
                    $candidate['summary'],
                    mg_creator_campaign_json_encode($candidate['detail']),
                    $scanToken,
                ]);
        }

        if($detected['errors']===[]){
            $pdo->prepare("UPDATE creator_campaign_reconciliation_cases SET status='resolved',resolved_at=NOW(),updated_at=NOW() WHERE workspace_id=? AND status IN('open','acknowledged') AND COALESCE(scan_token,'')<>?")
                ->execute([$workspaceId,$scanToken]);
        }else{
            $fingerprint=hash('sha256','scan_error|workspace|'.$workspaceId);
            $pdo->prepare("INSERT INTO creator_campaign_reconciliation_cases(public_id,workspace_id,fingerprint,issue_type,severity,source_type,source_public_id,status,summary,detail_json,scan_token,first_seen_at,last_seen_at)
              VALUES (?,?,?,?,?,'workspace',?,'open',?,?,?,NOW(),NOW())
              ON DUPLICATE KEY UPDATE severity='critical',summary=VALUES(summary),detail_json=VALUES(detail_json),scan_token=VALUES(scan_token),last_seen_at=NOW(),status='open',resolved_at=NULL")
                ->execute([
                    mg_creator_campaign_public_id('ccrc'),
                    $workspaceId,
                    $fingerprint,
                    'reconciliation_scan_error',
                    'critical',
                    (string)$workspaceId,
                    'One or more affiliate reconciliation checks could not run.',
                    mg_creator_campaign_json_encode($detected['errors']),
                    $scanToken,
                ]);
        }

        $pdo->commit();
        return [
            'workspace_id'=>$workspaceId,
            'detected'=>count($detected['candidates']),
            'errors'=>$detected['errors'],
            'scan_token'=>$scanToken,
        ];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}
