<?php
declare(strict_types=1);

/**
 * Replace the basic participant summary with hold-aware payout readiness.
 *
 * The canonical payout service remains authoritative and repeats every check
 * under row locks. This query exists only to prevent the merchant interface
 * from offering an action that the service will correctly reject.
 */
function mg_creator_campaign_operations_payout_readiness(
    PDO $pdo,
    int $workspaceId,
    string $currency,
    array $policy
): array {
    $currency=mg_creator_campaign_operations_currency($currency);
    $holdDays=max(0,(int)($policy['hold_days']??0));
    $policyMinimum=max(0,(int)($policy['minimum_payout_minor']??0));
    $policyActive=(string)($policy['status']??'active')==='active';
    $cutoff=gmdate('Y-m-d H:i:s',time()-($holdDays*86400));

    $stmt=$pdo->prepare("SELECT p.public_id participant_public_id,p.status participant_status,p.creator_user_id,
      cc.public_id campaign_public_id,cc.title campaign_title,cp.display_name creator_name,
      COALESCE(pp.status,'incomplete') payout_profile_status,pp.method_label,COALESCE(pp.minimum_payout_minor,0) profile_minimum_payout_minor,
      COALESCE(SUM(CASE WHEN r.status='committed' AND r.currency=? AND pi.id IS NULL THEN r.amount_minor ELSE 0 END),0) committed_minor,
      COALESCE(SUM(CASE WHEN r.status='committed' AND r.currency=? AND pi.id IS NULL AND (?=0 OR r.committed_at<=?) THEN r.amount_minor ELSE 0 END),0) payout_ready_minor,
      MIN(CASE WHEN r.status='committed' AND r.currency=? AND pi.id IS NULL THEN r.committed_at ELSE NULL END) oldest_committed_at,
      MIN(CASE WHEN r.status='committed' AND r.currency=? AND pi.id IS NULL AND ?>0 AND r.committed_at>? THEN r.committed_at ELSE NULL END) next_held_committed_at
      FROM creator_campaign_participants p
      INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
      INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
      LEFT JOIN creator_campaign_payout_profiles pp ON pp.creator_user_id=p.creator_user_id AND pp.currency=?
      LEFT JOIN creator_campaign_budget_reservations r ON r.participant_id=p.id
      LEFT JOIN creator_campaign_payout_items pi ON pi.reservation_id=r.id AND pi.status IN('scheduled','paid')
      WHERE cc.workspace_id=?
      GROUP BY p.id,pp.id
      ORDER BY cc.updated_at DESC,cp.display_name ASC,p.id DESC
      LIMIT 500");
    $stmt->execute([
        $currency,
        $currency,$holdDays,$cutoff,
        $currency,
        $currency,$holdDays,$cutoff,
        $currency,$workspaceId,
    ]);

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($rows as &$row){
        $committed=(int)$row['committed_minor'];
        $ready=(int)$row['payout_ready_minor'];
        $effectiveMinimum=max($policyMinimum,(int)$row['profile_minimum_payout_minor']);
        $row['committed_minor']=$committed;
        $row['payout_ready_minor']=$ready;
        $row['held_minor']=max(0,$committed-$ready);
        $row['effective_minimum_payout_minor']=$effectiveMinimum;
        $row['can_create_payout']=$policyActive
            && (string)$row['participant_status']==='active'
            && (string)$row['payout_profile_status']==='eligible'
            && $ready>0
            && $ready>=$effectiveMinimum;
        $row['next_eligible_at']=null;
        if($holdDays>0&&!empty($row['next_held_committed_at'])){
            try{
                $row['next_eligible_at']=(new DateTimeImmutable((string)$row['next_held_committed_at'],new DateTimeZone('UTC')))
                    ->modify('+'.$holdDays.' days')
                    ->format('Y-m-d H:i:s');
            }catch(Throwable){
                $row['next_eligible_at']=null;
            }
        }
        unset($row['next_held_committed_at']);
    }
    unset($row);

    return $rows;
}

function mg_creator_campaign_operations_dashboard_with_readiness(PDO $pdo,array $user,array $filters=[]): array
{
    $dashboard=mg_creator_campaign_operations_dashboard($pdo,$user,$filters);
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.view');
    $participants=mg_creator_campaign_operations_payout_readiness(
        $pdo,
        (int)$context['workspace_id'],
        (string)($dashboard['currency']??'USD'),
        is_array($dashboard['policy']??null)?$dashboard['policy']:[]
    );
    $dashboard['participants']=$participants;
    $dashboard['metrics']['eligible_creators']=count(array_filter(
        $participants,
        static fn(array $participant):bool=>(string)$participant['payout_profile_status']==='eligible'
    ));
    $dashboard['metrics']['committed_minor']=array_sum(array_map(
        static fn(array $participant):int=>(int)$participant['committed_minor'],
        $participants
    ));
    $dashboard['metrics']['payout_ready_minor']=array_sum(array_map(
        static fn(array $participant):int=>(int)$participant['payout_ready_minor'],
        $participants
    ));
    return $dashboard;
}
