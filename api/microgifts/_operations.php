<?php
declare(strict_types=1);
require_once __DIR__ . '/_lifecycle.php';

function mg_microgift_account_items(PDO $pdo,int $userId,string $scope,int $limit=100): array
{
    $scope=in_array($scope,['owned','sent','received','claimed','redeemed'],true)?$scope:'owned';
    $where=match($scope){
        'sent'=>'i.issuer_user_id=?',
        'received'=>'i.recipient_user_id=?',
        'claimed'=>'c.claimant_user_id=?',
        'redeemed'=>'r.claimant_user_id=?',
        default=>'i.owner_user_id=?',
    };
    $sql="SELECT DISTINCT i.public_id instance_id,i.status,i.title_snapshot,i.description_snapshot,i.currency,i.face_value_cents,i.recipient_policy,i.issued_at,i.delivered_at,i.claimed_at,i.redeemed_at,i.expires_at,i.updated_at,
                 t.public_id template_id,t.name template_name,v.public_id template_version_id,v.version_number,
                 p.public_id pppm_item_id,p.status pppm_status,
                 (SELECT COUNT(*) FROM entitlements e WHERE e.pppm_item_id=i.pppm_item_id AND e.entitled_user_id=?) entitlement_count,
                 c.public_id claim_id,c.status claim_status,
                 r.public_id redemption_id,r.status redemption_status,r.location_reference
          FROM microgift_instances i
          INNER JOIN microgift_templates t ON t.id=i.template_id
          INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
          LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
          LEFT JOIN microgift_claims c ON c.instance_id=i.id AND c.status='completed'
          LEFT JOIN microgift_redemptions r ON r.instance_id=i.id AND r.status='completed'
          WHERE {$where}
          ORDER BY i.updated_at DESC,i.id DESC LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$userId,$userId]);
    return $stmt->fetchAll();
}

function mg_microgift_merchant_summary(PDO $pdo,int $merchantUserId,int $days=30): array
{
    $days=max(1,min($days,365));
    $summary=$pdo->prepare("SELECT COUNT(*) instance_count,
        SUM(i.status='issued') issued_count,SUM(i.status IN ('claimed','redeemable')) claimed_count,SUM(i.status='redeemed') redeemed_count,
        SUM(i.status='expired') expired_count,SUM(i.status='cancelled') cancelled_count,SUM(i.status='revoked') revoked_count,
        COALESCE(SUM(i.face_value_cents),0) face_value_cents
        FROM microgift_instances i INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE t.owner_user_id=? AND i.created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)");
    $summary->execute([$merchantUserId]);
    $redemptions=$pdo->prepare("SELECT COUNT(*) redemption_count,COALESCE(SUM(r.amount_cents),0) redeemed_value_cents,COUNT(DISTINCT r.claimant_user_id) unique_customers,COUNT(DISTINCT r.location_reference) unique_locations
        FROM microgift_redemptions r INNER JOIN microgift_instances i ON i.id=r.instance_id INNER JOIN microgift_templates t ON t.id=i.template_id
        WHERE t.owner_user_id=? AND r.status='completed' AND r.redeemed_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)");
    $redemptions->execute([$merchantUserId]);
    return ['period_days'=>$days,'instances'=>$summary->fetch(),'redemptions'=>$redemptions->fetch()];
}

function mg_microgift_create_review(PDO $pdo,string $type,string $sourceType,string $sourceReference,string $summary,array $refs=[],array $details=[]): string
{
    $existing=$pdo->prepare('SELECT public_id FROM microgift_review_items WHERE review_type=? AND source_type=? AND source_reference=? LIMIT 1');
    $existing->execute([$type,$sourceType,$sourceReference]);
    if($public=$existing->fetchColumn())return (string)$public;
    $public=mg_microgift_uuid();
    $pdo->prepare("INSERT INTO microgift_review_items (public_id,review_type,status,priority,instance_id,legacy_gift_id,user_id,merchant_user_id,source_type,source_reference,summary,details_json,created_at,updated_at) VALUES (?,?,'open',?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$public,$type,$refs['priority']??'normal',$refs['instance_id']??null,$refs['legacy_gift_id']??null,$refs['user_id']??null,$refs['merchant_user_id']??null,$sourceType,$sourceReference,$summary,mg_microgift_json($details)]);
    return $public;
}
