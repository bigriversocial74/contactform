<?php
declare(strict_types=1);

function mg_creator_campaign_crm_transaction(PDO $pdo, callable $callback): mixed
{
    static $counter = 0;
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'mg_cc_crm_' . (++$counter);
    if ($ownsTransaction) $pdo->beginTransaction();
    else $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $result = $callback();
        if ($ownsTransaction) $pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return $result;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $error;
    }
}

function mg_creator_campaign_crm_project_source(PDO $pdo, array $source): array
{
    if (!mg_creator_campaign_crm_installed($pdo)) {
        return ['schema_ready'=>false,'skipped'=>true,'reason'=>'schema_not_installed'];
    }

    return mg_creator_campaign_crm_transaction($pdo, static function () use ($pdo, $source): array {
        $reservation = mg_creator_campaign_crm_reserve_projection($pdo, $source);
        $projection = $reservation['projection'];
        if (!empty($reservation['idempotent_replay'])) {
            return [
                'schema_ready'=>true,
                'idempotent_replay'=>true,
                'projection_id'=>(string)$projection['public_id'],
                'projection_status'=>(string)$projection['projection_status'],
            ];
        }

        $identity = is_array($source['identity'] ?? null) ? $source['identity'] : [];
        if (empty($identity['resolved'])) {
            $metadata = (array) ($source['metadata'] ?? []);
            $metadata['skip_reason'] = 'identity_unresolved';
            mg_creator_campaign_crm_complete_projection(
                $pdo,(int)$projection['id'],'skipped',null,null,$metadata,'identity_unresolved','No resolvable customer identity was supplied.'
            );
            return [
                'schema_ready'=>true,'skipped'=>true,'reason'=>'identity_unresolved',
                'projection_id'=>(string)$projection['public_id'],'projection_status'=>'skipped',
            ];
        }

        $metadata = (array) ($source['metadata'] ?? []);
        $metadata['creator_campaign_id'] = $source['campaign_public_id'] ?? null;
        $metadata['creator_campaign_title'] = $source['campaign_title'] ?? null;
        $metadata['creator_campaign_relationship_type'] = $source['relationship_type'] ?? null;
        $metadata['creator_campaign_source_domain'] = $source['source_domain'] ?? null;
        $metadata['creator_campaign_source_event_key'] = $source['source_event_key'] ?? null;
        $metadata['creator_campaign_url'] = !empty($source['campaign_public_id'])
            ? '/merchant-creator-campaign-detail.php?campaign=' . rawurlencode((string)$source['campaign_public_id'])
            : null;

        $record = mg_merchant_crm_record_event($pdo, [
            'merchant_user_id'=>(int)$source['merchant_user_id'],
            'campaign_id'=>null,
            'campaign_type'=>'creator_campaign',
            'event_type'=>(string)$source['generic_event_type'],
            'source_type'=>(string)$source['generic_source_type'],
            'source_public_id'=>$source['source_public_id'] ?? null,
            'user_id'=>$identity['user_id'] ?? null,
            'email'=>$identity['email'] ?? null,
            'phone'=>$identity['phone'] ?? null,
            'name'=>$identity['name'] ?? null,
            'value_cents'=>$source['value_cents'] ?? null,
            'metadata'=>$metadata,
        ]);
        if (empty($record['schema_ready']) || empty($record['contact_id'])) {
            throw new RuntimeException('Canonical Merchant CRM could not record the Creator Campaign lifecycle event.');
        }

        $ids = mg_creator_campaign_crm_resolve_crm_ids(
            $pdo,(int)$source['merchant_user_id'],(string)$record['contact_id'],isset($record['event_id'])?(string)$record['event_id']:null
        );
        if (empty($ids['contact_id'])) throw new RuntimeException('Canonical Merchant CRM contact could not be resolved after projection.');

        mg_creator_campaign_crm_merge_contact_context(
            $pdo,(int)$ids['contact_id'],(string)$source['relationship_type'],$source
        );
        mg_creator_campaign_crm_upsert_relationship(
            $pdo,(int)$source['merchant_user_id'],(int)$ids['contact_id'],(int)$source['creator_campaign_id'],
            (string)$source['relationship_type'],(string)$source['event_type'],(string)$source['occurred_at'],
            !empty($source['relationship_closed']),[
                'campaign_public_id'=>$source['campaign_public_id']??null,
                'campaign_title'=>$source['campaign_title']??null,
                'source_domain'=>$source['source_domain']??null,
                'source_public_id'=>$source['source_public_id']??null,
                'last_status'=>$source['to_status']??null,
            ]
        );
        mg_creator_campaign_crm_complete_projection(
            $pdo,(int)$projection['id'],'completed',(int)$ids['contact_id'],$ids['event_id'] === null ? null : (int)$ids['event_id'],
            $metadata + ['crm_contact_public_id'=>(string)$record['contact_id'],'crm_event_public_id'=>$record['event_id']??null]
        );

        return [
            'schema_ready'=>true,'projected'=>true,'idempotent_replay'=>false,
            'projection_id'=>(string)$projection['public_id'],'projection_status'=>'completed',
            'crm_contact_id'=>(string)$record['contact_id'],'crm_event_id'=>$record['event_id']??null,
            'relationship_type'=>(string)$source['relationship_type'],
        ];
    });
}

function mg_creator_campaign_crm_project_participation_event(PDO $pdo, string $eventPublicId): array
{
    $event = mg_creator_campaign_crm_participation_source($pdo, $eventPublicId);
    $identity = mg_creator_campaign_crm_identity([
        'user_id'=>$event['creator_user_id']??null,
        'email'=>$event['creator_email']??null,
        'name'=>$event['creator_name']??$event['creator_full_name']??null,
    ]);
    $sourceKey = mg_creator_campaign_crm_source_key('participation', (string)$event['public_id']);
    return mg_creator_campaign_crm_project_source($pdo, [
        'merchant_user_id'=>(int)$event['merchant_user_id'],
        'creator_campaign_id'=>(int)$event['campaign_id'],
        'campaign_public_id'=>(string)$event['campaign_public_id'],
        'campaign_title'=>(string)$event['campaign_title'],
        'relationship_type'=>'creator_partner',
        'source_domain'=>'participation',
        'source_event_key'=>$sourceKey,
        'source_public_id'=>(string)$event['public_id'],
        'event_type'=>(string)$event['event_type'],
        'generic_event_type'=>mg_creator_campaign_crm_generic_event_type('partner',(string)$event['event_type']),
        'generic_source_type'=>'creator_campaign_participation',
        'occurred_at'=>(string)$event['created_at'],
        'identity'=>$identity,
        'relationship_closed'=>mg_creator_campaign_crm_relationship_closed((string)$event['event_type'],$event['to_status']??null),
        'to_status'=>$event['to_status']??null,
        'metadata'=>[
            'participation_event_id'=>(string)$event['public_id'],
            'application_id'=>$event['application_id']??null,
            'invitation_id'=>$event['invitation_id']??null,
            'participant_id'=>$event['participant_id']??null,
            'relationship_source_id'=>$event['relationship_source_public_id']??null,
            'from_status'=>$event['from_status']??null,
            'to_status'=>$event['to_status']??null,
            'reason'=>$event['reason']??null,
            'context'=>$event['context']??[],
        ],
    ]);
}

function mg_creator_campaign_crm_project_tracking_event(PDO $pdo, string $eventPublicId): array
{
    $event = mg_creator_campaign_crm_tracking_source($pdo, $eventPublicId);
    $eventType = strtolower((string)$event['event_type']);
    if ((string)$event['status'] !== 'accepted' || !in_array($eventType, mg_creator_campaign_tracking_conversion_event_types(), true)) {
        return ['schema_ready'=>mg_creator_campaign_crm_installed($pdo),'skipped'=>true,'reason'=>'event_not_accepted_conversion'];
    }
    $relationship = mg_creator_campaign_crm_tracking_relationship($eventType);
    $identity = mg_creator_campaign_crm_tracking_identity((array)$event['metadata']);
    $sourceKey = mg_creator_campaign_crm_source_key('tracking', (string)$event['public_id']);
    $amountMinor = isset($event['metadata']['amount_minor']) ? max(0,(int)$event['metadata']['amount_minor']) : null;
    return mg_creator_campaign_crm_project_source($pdo, [
        'merchant_user_id'=>(int)$event['merchant_user_id'],
        'creator_campaign_id'=>(int)$event['campaign_id'],
        'campaign_public_id'=>(string)$event['campaign_public_id'],
        'campaign_title'=>(string)$event['campaign_title'],
        'relationship_type'=>$relationship,
        'source_domain'=>'tracking',
        'source_event_key'=>$sourceKey,
        'source_public_id'=>(string)$event['public_id'],
        'event_type'=>$eventType,
        'generic_event_type'=>mg_creator_campaign_crm_generic_event_type('tracking',$eventType),
        'generic_source_type'=>'creator_campaign_tracking',
        'occurred_at'=>(string)$event['occurred_at'],
        'identity'=>$identity,
        'value_cents'=>$amountMinor,
        'relationship_closed'=>false,
        'metadata'=>[
            'tracking_event_id'=>(string)$event['public_id'],
            'tracking_source_id'=>$event['tracking_source_public_id']??null,
            'participant_id'=>$event['participant_public_id']??null,
            'creator_name'=>$event['creator_name']??null,
            'event_key'=>$event['event_key']??null,
            'currency'=>$event['metadata']['currency']??null,
            'amount_minor'=>$amountMinor,
        ],
    ]);
}

function mg_creator_campaign_crm_project_participation_event_safe(PDO $pdo, string $eventPublicId): array
{
    try {
        return mg_creator_campaign_crm_project_participation_event($pdo, $eventPublicId);
    } catch (Throwable $error) {
        mg_security_log('warning','creator_campaign.crm.participation_projection_failed','Creator Campaign participation could not be projected into Merchant CRM.',[
            'event_public_id'=>$eventPublicId,'exception_class'=>$error::class,'message'=>$error->getMessage(),
        ]);
        return ['schema_ready'=>mg_creator_campaign_crm_installed($pdo),'failed'=>true,'message'=>$error->getMessage()];
    }
}

function mg_creator_campaign_crm_project_tracking_event_safe(PDO $pdo, string $eventPublicId): array
{
    try {
        return mg_creator_campaign_crm_project_tracking_event($pdo, $eventPublicId);
    } catch (Throwable $error) {
        mg_security_log('warning','creator_campaign.crm.tracking_projection_failed','Creator Campaign conversion could not be projected into Merchant CRM.',[
            'event_public_id'=>$eventPublicId,'exception_class'=>$error::class,'message'=>$error->getMessage(),
        ]);
        return ['schema_ready'=>mg_creator_campaign_crm_installed($pdo),'failed'=>true,'message'=>$error->getMessage()];
    }
}

function mg_creator_campaign_crm_reconcile(PDO $pdo, array $user, ?string $campaignPublicId, int $limit = 250): array
{
    if (!mg_creator_campaign_crm_installed($pdo)) throw new RuntimeException('Creator Campaign CRM schema is incomplete.');
    $context = mg_creator_campaign_actor_context($pdo,$user,'merchant.creator_crm.manage');
    $limit = max(1,min(500,$limit));
    $campaignId = null;
    if ($campaignPublicId !== null && trim($campaignPublicId) !== '') {
        $campaign = mg_creator_campaign_participation_campaign_by_public_id($pdo,trim($campaignPublicId),(int)$context['workspace_id']);
        $campaignId = (int)$campaign['id'];
    }
    $run = mg_creator_campaign_crm_create_run(
        $pdo,(int)$context['workspace_owner_user_id'],$campaignId,(int)$context['actor_user_id'],$campaignId===null?'workspace':'campaign'
    );
    $summary = ['participation_scanned'=>0,'tracking_scanned'=>0,'projected_count'=>0,'replay_count'=>0,'skipped_count'=>0,'failed_count'=>0];
    $errors = [];

    $campaignClause = $campaignId === null ? '' : ' AND pe.campaign_id=' . $campaignId;
    $stmt = $pdo->prepare(
        'SELECT pe.public_id FROM creator_campaign_participation_events pe
         INNER JOIN creator_campaigns cc ON cc.id=pe.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         LEFT JOIN merchant_crm_creator_campaign_events x ON x.merchant_user_id=mw.merchant_user_id AND x.source_event_key=CONCAT(\'participation:\',LOWER(pe.public_id))
         WHERE cc.workspace_id=?' . $campaignClause . " AND (x.id IS NULL OR x.projection_status IN ('failed','pending')) ORDER BY pe.id ASC LIMIT " . $limit
    );
    $stmt->execute([(int)$context['workspace_id']]);
    $participationIds = array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $campaignClause = $campaignId === null ? '' : ' AND te.campaign_id=' . $campaignId;
    $stmt = $pdo->prepare(
        'SELECT te.public_id FROM creator_campaign_tracking_events te
         INNER JOIN creator_campaigns cc ON cc.id=te.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         LEFT JOIN merchant_crm_creator_campaign_events x ON x.merchant_user_id=mw.merchant_user_id AND x.source_event_key=CONCAT(\'tracking:\',LOWER(te.public_id))
         WHERE cc.workspace_id=?' . $campaignClause . " AND te.status='accepted' AND te.event_type IN ('lead','checkout','purchase','claim','redemption')
         AND (x.id IS NULL OR x.projection_status IN ('failed','pending')) ORDER BY te.id ASC LIMIT " . $limit
    );
    $stmt->execute([(int)$context['workspace_id']]);
    $trackingIds = array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $consume = static function (array $result) use (&$summary,&$errors): void {
        if (!empty($result['projected'])) $summary['projected_count']++;
        elseif (!empty($result['idempotent_replay'])) $summary['replay_count']++;
        elseif (!empty($result['skipped'])) $summary['skipped_count']++;
        elseif (!empty($result['failed'])) {
            $summary['failed_count']++;
            $errors[] = ['message'=>$result['message']??'Projection failed.'];
        }
    };
    foreach ($participationIds as $publicId) {
        $summary['participation_scanned']++;
        $consume(mg_creator_campaign_crm_project_participation_event_safe($pdo,$publicId));
    }
    foreach ($trackingIds as $publicId) {
        $summary['tracking_scanned']++;
        $consume(mg_creator_campaign_crm_project_tracking_event_safe($pdo,$publicId));
    }
    mg_creator_campaign_crm_complete_run($pdo,(int)$run['id'],$summary,$errors);
    return $summary + ['run_id'=>$run['public_id'],'campaign_id'=>$campaignPublicId,'batch_limit'=>$limit];
}
