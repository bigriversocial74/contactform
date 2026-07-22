<?php
declare(strict_types=1);

function mg_creator_campaign_tracking_source_payload(array $row): array
{
    $row['share_path'] = '/creator-campaign-track.php?c=' . rawurlencode((string) $row['tracking_code']);
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? ($row['metadata'] ?? null));
    unset($row['metadata_json']);
    return $row;
}

function mg_creator_campaign_tracking_save_source_common(
    PDO $pdo,
    array $participant,
    int $actorUserId,
    array $input,
    ?int $workspaceId,
    ?int $creatorUserId
): array {
    $label = trim((string) ($input['label'] ?? ''));
    if ($label === '' || mb_strlen($label) > 180) throw new InvalidArgumentException('A source label is required.');
    $channel = strtolower(trim((string) ($input['channel'] ?? 'link')));
    if (!in_array($channel, mg_creator_campaign_tracking_channels(), true)) throw new InvalidArgumentException('channel is invalid.');
    $platform = trim((string) ($input['platform'] ?? ''));
    $platform = $platform === '' ? null : mb_substr($platform,0,80);
    $destination = mg_creator_campaign_tracking_internal_path($input['destination_path'] ?? '');
    $model = strtolower(trim((string) ($input['attribution_model'] ?? 'last_touch')));
    if (!in_array($model, ['first_touch','last_touch','direct'], true)) throw new InvalidArgumentException('attribution_model is invalid.');
    $clickWindow = max(1,min(365,(int)($input['click_window_days']??30)));
    $conversionWindow = max(1,min(365,(int)($input['conversion_window_days']??30)));
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    if (!in_array($status, mg_creator_campaign_tracking_source_statuses(), true)) throw new InvalidArgumentException('status is invalid.');
    $metadata = mg_creator_campaign_tracking_metadata($input['metadata'] ?? []);
    $sourcePublicId = trim((string) ($input['source_id'] ?? ''));

    if ($sourcePublicId !== '') {
        $source = mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,$workspaceId,$creatorUserId,true);
        mg_creator_campaign_participation_require_expected_lock($source,(int)($input['expected_lock_version']??0));
        if ((int)$source['participant_id'] !== (int)$participant['id']) throw new DomainException('Tracking source participant cannot be changed.');
        $stmt=$pdo->prepare(
            "UPDATE creator_campaign_tracking_sources
             SET label=?,channel=?,platform=?,destination_path=?,attribution_model=?,click_window_days=?,
                 conversion_window_days=?,metadata_json=?,status=?,lock_version=lock_version+1,updated_by_user_id=?,updated_at=NOW()
             WHERE id=?"
        );
        $stmt->execute([$label,$channel,$platform,$destination,$model,$clickWindow,$conversionWindow,
            $metadata===[]?null:mg_creator_campaign_json_encode($metadata),$status,$actorUserId,(int)$source['id']]);
        return mg_creator_campaign_tracking_source_payload(
            mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,$workspaceId,$creatorUserId)
        );
    }

    $publicId=mg_creator_campaign_public_id('ccts');
    $code=mg_creator_campaign_tracking_code();
    $stmt=$pdo->prepare(
        "INSERT INTO creator_campaign_tracking_sources
         (public_id,campaign_id,participant_id,creator_user_id,label,channel,platform,destination_path,tracking_code,
          attribution_model,click_window_days,conversion_window_days,metadata_json,status,lock_version,
          created_by_user_id,updated_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())"
    );
    $stmt->execute([$publicId,(int)$participant['campaign_id'],(int)$participant['id'],(int)$participant['creator_user_id'],
        $label,$channel,$platform,$destination,$code,$model,$clickWindow,$conversionWindow,
        $metadata===[]?null:mg_creator_campaign_json_encode($metadata),$status,$actorUserId,$actorUserId]);
    return mg_creator_campaign_tracking_source_payload(
        mg_creator_campaign_tracking_source_by_public_id($pdo,$publicId,$workspaceId,$creatorUserId)
    );
}

function mg_creator_campaign_tracking_save_source_merchant(PDO $pdo,array $user,string $participantPublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.manage');
    $pdo->beginTransaction();
    try{
        $participant=mg_creator_campaign_tracking_participant_by_public_id($pdo,$participantPublicId,(int)$context['workspace_id'],null,true);
        $result=mg_creator_campaign_tracking_save_source_common($pdo,$participant,(int)$context['actor_user_id'],$input,(int)$context['workspace_id'],null);
        $pdo->commit(); return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_save_source_creator(PDO $pdo,array $user,string $participantPublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_creator_context($pdo,$user,'creator.campaign_tracking.manage_own');
    $pdo->beginTransaction();
    try{
        $participant=mg_creator_campaign_tracking_participant_by_public_id($pdo,$participantPublicId,null,(int)$context['creator_user_id'],true);
        $result=mg_creator_campaign_tracking_save_source_common($pdo,$participant,(int)$context['actor_user_id'],$input,null,(int)$context['creator_user_id']);
        $pdo->commit(); return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_retire_source_merchant(PDO $pdo,array $user,string $sourcePublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.manage');
    $pdo->beginTransaction();
    try{
        $source=mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,(int)$context['workspace_id'],null,true);
        mg_creator_campaign_participation_require_expected_lock($source,(int)($input['expected_lock_version']??0));
        $pdo->prepare("UPDATE creator_campaign_tracking_sources SET status='retired',lock_version=lock_version+1,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([(int)$context['actor_user_id'],(int)$source['id']]);
        $pdo->commit();
        return mg_creator_campaign_tracking_source_payload(mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,(int)$context['workspace_id']));
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_retire_source_creator(PDO $pdo,array $user,string $sourcePublicId,array $input):array
{
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $context=mg_creator_campaign_tracking_creator_context($pdo,$user,'creator.campaign_tracking.manage_own');
    $pdo->beginTransaction();
    try{
        $source=mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,null,(int)$context['creator_user_id'],true);
        mg_creator_campaign_participation_require_expected_lock($source,(int)($input['expected_lock_version']??0));
        $pdo->prepare("UPDATE creator_campaign_tracking_sources SET status='retired',lock_version=lock_version+1,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([(int)$context['actor_user_id'],(int)$source['id']]);
        $pdo->commit();
        return mg_creator_campaign_tracking_source_payload(mg_creator_campaign_tracking_source_by_public_id($pdo,$sourcePublicId,null,(int)$context['creator_user_id']));
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_record_by_code(PDO $pdo,string $code,array $input):array
{
    mg_creator_campaign_tracking_assert_schema($pdo);
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try{
        $source=mg_creator_campaign_tracking_source_by_code($pdo,$code,true);
        $event=mg_creator_campaign_tracking_insert_event($pdo,$source,$input);
        if(in_array((string)$event['event_type'],mg_creator_campaign_tracking_conversion_event_types(),true)){
            $event['attribution']=mg_creator_campaign_attribution_decide($pdo,$event,null,false);
            if(function_exists('mg_creator_campaign_crm_project_tracking_event_safe')){
                $event['crm_projection']=mg_creator_campaign_crm_project_tracking_event_safe($pdo,(string)$event['public_id']);
            }
        }
        $pdo->commit();
        return $event;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_creator_campaign_tracking_record_conversion(PDO $pdo,array $input):array
{
    mg_creator_campaign_tracking_assert_schema($pdo);
    mg_creator_campaign_assert_transaction_boundary($pdo);
    $eventType=strtolower(trim((string)($input['event_type']??'')));
    if(!in_array($eventType,mg_creator_campaign_tracking_conversion_event_types(),true)){
        throw new InvalidArgumentException('A conversion event type is required.');
    }
    $pdo->beginTransaction();
    try{
        $code=trim((string)($input['tracking_code']??''));
        if($code!==''){
            $source=mg_creator_campaign_tracking_source_by_code($pdo,$code,true);
        }else{
            $campaignPublicId=trim((string)($input['campaign_id']??''));
            if($campaignPublicId==='')throw new InvalidArgumentException('tracking_code or campaign_id is required.');
            $stmt=$pdo->prepare("SELECT id,public_id,title FROM creator_campaigns WHERE public_id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$campaignPublicId]);$campaign=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$campaign)throw new RuntimeException('Creator campaign not found.');
            $source=['id'=>null,'campaign_id'=>(int)$campaign['id'],'participant_id'=>null,'creator_user_id'=>null,'destination_path'=>'/'];
        }
        $input['event_type']=$eventType;
        $event=mg_creator_campaign_tracking_insert_event($pdo,$source,$input);
        $event['attribution']=mg_creator_campaign_attribution_decide($pdo,$event,null,false);
        if(function_exists('mg_creator_campaign_crm_project_tracking_event_safe')){
            $event['crm_projection']=mg_creator_campaign_crm_project_tracking_event_safe($pdo,(string)$event['public_id']);
        }
        $pdo->commit();return $event;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
