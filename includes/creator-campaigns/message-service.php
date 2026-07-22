<?php
declare(strict_types=1);

function mg_creator_campaign_message_idempotency_hash(array $participant, int $actorUserId, string $key, string $operation): string
{
    $key = trim($key);
    if ($key === '' || strlen($key) > 190) throw new InvalidArgumentException('A valid idempotency key is required.');
    return hash('sha256', implode(':', ['creator_campaign_message',$operation,(string)$participant['campaign_id'],(string)$participant['id'],(string)$actorUserId,$key]));
}

function mg_creator_campaign_message_send(PDO $pdo, array $participant, int $actorUserId, array $input, string $kind = 'participant'): array
{
    $body = mg_creator_campaign_message_validate_body($input['body'] ?? '');
    $contextType = mg_creator_campaign_message_validate_context_type((string)($input['context_type'] ?? 'campaign'));
    $contextPublicId = mg_creator_campaign_message_validate_reference($input['context_public_id'] ?? null);
    $assets = mg_creator_campaign_message_validate_assets($input['asset_public_ids'] ?? []);
    $systemEventType = $kind === 'system' ? mg_creator_campaign_message_validate_reference($input['system_event_type'] ?? null) : null;
    if ($kind === 'system' && $systemEventType === null) throw new InvalidArgumentException('System event type is required.');
    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    $hash = mg_creator_campaign_message_idempotency_hash($participant,$actorUserId,(string)($input['idempotency_key'] ?? ''),$kind);

    $pdo->beginTransaction();
    try {
        $context = mg_creator_campaign_message_get_or_create_thread($pdo,$participant,$actorUserId);
        if ((string)$context['status'] !== 'open' && $kind !== 'system') throw new DomainException('This Creator Campaign thread is closed.');
        $result = mg_creator_campaign_message_insert($pdo,$context,$actorUserId,$body,$contextType,$contextPublicId,$kind,$systemEventType,$assets,$metadata,$hash);
        $notificationIds = [];
        if (empty($result['duplicate'])) {
            $notificationIds = mg_creator_campaign_message_notify($pdo,$context,$participant,$actorUserId,(string)$result['message_public_id'],$body,$kind);
        }
        $pdo->commit();
        return $result + ['notification_ids'=>$notificationIds];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_creator_campaign_message_open(PDO $pdo, array $participant, int $actorUserId): array
{
    $pdo->beginTransaction();
    try {
        $context = mg_creator_campaign_message_get_or_create_thread($pdo,$participant,$actorUserId);
        $pdo->commit();
        return $context;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_creator_campaign_message_add_internal_note(PDO $pdo, array $participant, int $workspaceId, int $actorUserId, array $input): array
{
    $body = mg_creator_campaign_message_validate_body($input['body'] ?? '', 12000);
    $contextType = mg_creator_campaign_message_validate_context_type((string)($input['context_type'] ?? 'campaign'), true);
    $contextPublicId = mg_creator_campaign_message_validate_reference($input['context_public_id'] ?? null);
    $hash = mg_creator_campaign_message_idempotency_hash($participant,$actorUserId,(string)($input['idempotency_key'] ?? ''),'internal_note');
    $pdo->beginTransaction();
    try {
        $result = mg_creator_campaign_message_add_note($pdo,$participant,$workspaceId,$actorUserId,$contextType,$contextPublicId,$body,$hash);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_creator_campaign_message_change_status(PDO $pdo, array $participant, int $actorUserId, string $status, int $lockVersion): array
{
    $pdo->beginTransaction();
    try {
        $context = mg_creator_campaign_message_get_or_create_thread($pdo,$participant,$actorUserId);
        mg_creator_campaign_message_set_status($pdo,$context,$status,$actorUserId,$lockVersion);
        $pdo->commit();
        return ['thread_public_id'=>(string)$context['thread_public_id'],'status'=>$status,'lock_version'=>$lockVersion+1];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_creator_campaign_message_publish_system_event(PDO $pdo, array $participant, int $actorUserId, string $eventType, string $body, array $context = []): array
{
    return mg_creator_campaign_message_send($pdo,$participant,$actorUserId,[
        'body'=>$body,
        'context_type'=>$context['context_type'] ?? 'campaign',
        'context_public_id'=>$context['context_public_id'] ?? null,
        'system_event_type'=>$eventType,
        'metadata'=>$context['metadata'] ?? [],
        'idempotency_key'=>$context['idempotency_key'] ?? ($eventType . ':' . ($context['context_public_id'] ?? $participant['public_id'])),
    ],'system');
}
