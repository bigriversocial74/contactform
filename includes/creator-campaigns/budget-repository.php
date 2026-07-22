<?php
declare(strict_types=1);

function mg_creator_campaign_budget_by_public_id(PDO $pdo,string $publicId,int $workspaceId,bool $forUpdate=false): array
{
    $sql="SELECT b.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id FROM creator_campaign_budgets b INNER JOIN creator_campaigns cc ON cc.id=b.campaign_id WHERE b.public_id=? AND cc.workspace_id=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([trim($publicId),$workspaceId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator campaign budget not found.');
    return $row;
}

function mg_creator_campaign_budget_for_campaign(PDO $pdo,int $campaignId,string $currency,bool $forUpdate=false): ?array
{
    $sql='SELECT * FROM creator_campaign_budgets WHERE campaign_id=? AND currency=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$campaignId,$currency]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_creator_campaign_budget_balances(PDO $pdo,int $budgetId): array
{
    $stmt=$pdo->prepare('SELECT COALESCE(SUM(available_delta_minor),0) available_minor,COALESCE(SUM(reserved_delta_minor),0) reserved_minor,COALESCE(SUM(committed_delta_minor),0) committed_minor FROM creator_campaign_budget_events WHERE budget_id=?');
    $stmt->execute([$budgetId]);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return ['available_minor'=>(int)($row['available_minor']??0),'reserved_minor'=>(int)($row['reserved_minor']??0),'committed_minor'=>(int)($row['committed_minor']??0)];
}

function mg_creator_campaign_budget_reservation(PDO $pdo,string $publicId,int $workspaceId,bool $forUpdate=false): array
{
    $sql="SELECT r.*,b.public_id budget_public_id,cc.workspace_id,e.public_id earning_public_id FROM creator_campaign_budget_reservations r INNER JOIN creator_campaign_budgets b ON b.id=r.budget_id INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id INNER JOIN creator_campaign_earning_events e ON e.id=r.earning_event_id WHERE r.public_id=? AND cc.workspace_id=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([trim($publicId),$workspaceId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator campaign budget reservation not found.');
    return $row;
}

function mg_creator_campaign_budget_earning(PDO $pdo,string $publicId,int $workspaceId): array
{
    $stmt=$pdo->prepare("SELECT e.*,cc.workspace_id,p.public_id participant_public_id FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id INNER JOIN creator_campaign_participants p ON p.id=e.participant_id WHERE e.public_id=? AND cc.workspace_id=? LIMIT 1");
    $stmt->execute([trim($publicId),$workspaceId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator earning event not found.');
    return $row;
}

function mg_creator_campaign_budget_append_event(PDO $pdo,array $budget,string $type,int $availableDelta,int $reservedDelta,int $committedDelta,string $idempotencyKey,int $actorUserId,?int $reservationId=null,?int $earningEventId=null,?string $reason=null): array
{
    $hash=mg_creator_campaign_idempotency_hash($idempotencyKey);$before=mg_creator_campaign_budget_balances($pdo,(int)$budget['id']);
    $after=['available_minor'=>$before['available_minor']+$availableDelta,'reserved_minor'=>$before['reserved_minor']+$reservedDelta,'committed_minor'=>$before['committed_minor']+$committedDelta];
    if($after['reserved_minor']<0||$after['committed_minor']<0) throw new DomainException('Reserved and committed budget balances cannot become negative.');
    if($after['available_minor']<0 && empty($budget['allow_overage'])) throw new DomainException('Campaign budget has insufficient available funds.');
    $publicId=mg_creator_campaign_public_id('ccbe');
    $stmt=$pdo->prepare('INSERT INTO creator_campaign_budget_events(public_id,budget_id,campaign_id,reservation_id,earning_event_id,event_type,available_delta_minor,reserved_delta_minor,committed_delta_minor,idempotency_hash,balance_snapshot_json,reason,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$publicId,(int)$budget['id'],(int)$budget['campaign_id'],$reservationId,$earningEventId,$type,$availableDelta,$reservedDelta,$committedDelta,$hash,mg_creator_campaign_json_encode(['before'=>$before,'after'=>$after]),$reason,$actorUserId]);
    return ['event_id'=>$publicId,'balances'=>$after];
}
