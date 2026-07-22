<?php
declare(strict_types=1);

function mg_creator_campaign_compensation_campaign(PDO $pdo,string $publicId,int $workspaceId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT cc.* FROM creator_campaigns cc WHERE cc.public_id=? AND cc.workspace_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([trim($publicId),$workspaceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator campaign not found.');
    return $row;
}

function mg_creator_campaign_compensation_rule(PDO $pdo,string $publicId,int $workspaceId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT r.*,cc.public_id campaign_public_id,cc.workspace_id,v.public_id version_public_id,v.version_number,v.version_status,v.currency,v.flat_amount_minor,v.rate_bps,v.minimum_source_amount_minor,v.maximum_earning_minor,v.terms_text,v.content_hash
      FROM creator_campaign_compensation_rules r
      INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id
      LEFT JOIN creator_campaign_compensation_rule_versions v ON v.id=r.current_version_id
      WHERE r.public_id=? AND cc.workspace_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([trim($publicId),$workspaceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator compensation rule not found.');
    return $row;
}

function mg_creator_campaign_compensation_participant(PDO $pdo,string $publicId,?int $workspaceId=null,?int $creatorUserId=null,bool $forUpdate=false): array
{
    return mg_creator_campaign_tracking_participant_by_public_id($pdo,$publicId,$workspaceId,$creatorUserId,$forUpdate);
}

function mg_creator_campaign_earning_event(PDO $pdo,string $publicId,int $workspaceId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT e.*,cc.workspace_id,cc.public_id campaign_public_id,p.public_id participant_public_id,cp.display_name creator_name
      FROM creator_campaign_earning_events e
      INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
      INNER JOIN creator_campaign_participants p ON p.id=e.participant_id
      INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
      WHERE e.public_id=? AND cc.workspace_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([trim($publicId),$workspaceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Creator earning event not found.');
    return $row;
}

function mg_creator_campaign_compensation_active_rule(PDO $pdo,int $campaignId,string $triggerType): ?array
{
    $stmt=$pdo->prepare('SELECT r.*,v.public_id version_public_id,v.version_number,v.currency,v.flat_amount_minor,v.rate_bps,v.minimum_source_amount_minor,v.maximum_earning_minor,v.terms_text,v.content_hash
      FROM creator_campaign_compensation_rules r
      INNER JOIN creator_campaign_compensation_rule_versions v ON v.id=r.current_version_id
      WHERE r.campaign_id=? AND r.trigger_type=? AND r.status=\'active\' AND v.version_status=\'active\'
      ORDER BY r.id ASC LIMIT 1');
    $stmt->execute([$campaignId,$triggerType]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_creator_campaign_compensation_source(PDO $pdo,string $sourceType,string $sourcePublicId): array
{
    if($sourceType==='deliverable'){
        $stmt=$pdo->prepare("SELECT pd.id,pd.public_id,pd.campaign_id,pd.participant_id,pd.creator_user_id,pd.agreement_version_id,pd.status,
          0 source_amount_minor,'deliverable_verified' trigger_type
          FROM creator_campaign_participant_deliverables pd WHERE pd.public_id=? LIMIT 1");
        $stmt->execute([$sourcePublicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['status']!=='verified') throw new DomainException('Only verified deliverables can create earnings.');
        return $row;
    }
    if($sourceType==='attribution'||$sourceType==='conversion'){
        $stmt=$pdo->prepare("SELECT a.id,a.public_id,a.campaign_id,a.participant_id,a.creator_user_id,ag.latest_accepted_version_id agreement_version_id,
          a.status,e.public_id conversion_public_id,e.event_type,e.metadata_json
          FROM creator_campaign_attributions a
          INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id
          INNER JOIN creator_campaign_participants p ON p.id=a.participant_id
          INNER JOIN creator_campaign_agreements ag ON ag.participant_id=p.id
          WHERE a.public_id=? LIMIT 1");
        $stmt->execute([$sourcePublicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row||!in_array($row['status'],['attributed','overridden'],true)) throw new DomainException('Only valid attributed conversions can create earnings.');
        $metadata=mg_creator_campaign_participation_decode_json($row['metadata_json']??null);
        $row['source_amount_minor']=isset($metadata['amount_minor'])?(int)$metadata['amount_minor']:0;
        $row['trigger_type']=match((string)$row['event_type']){
            'purchase'=>'purchase_attributed','claim'=>'claim_attributed','redemption'=>'redemption_attributed',
            default=>throw new DomainException('This attributed event type is not compensation eligible.'),
        };
        return $row;
    }
    throw new InvalidArgumentException('source_type is invalid.');
}
