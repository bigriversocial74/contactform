<?php
declare(strict_types=1);

function mg_creator_campaign_record_audit(
    string $action,
    array $campaign,
    int $actorUserId,
    array $metadata = []
): void {
    $payload = array_merge([
        'campaign_id' => (int) ($campaign['id'] ?? 0),
        'campaign_public_id' => $campaign['public_id'] ?? null,
        'workspace_id' => (int) ($campaign['workspace_id'] ?? 0),
        'status' => $campaign['status'] ?? null,
        'lock_version' => isset($campaign['lock_version']) ? (int) $campaign['lock_version'] : null,
    ], $metadata);

    try {
        if (function_exists('mg_audit')) {
            mg_audit('creator_campaign.' . $action, 'creator_campaign', $payload, $actorUserId);
        }
        if (function_exists('mg_event')) {
            mg_event('creator_campaign.' . $action, $payload, $actorUserId);
        }
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log(
                'error',
                'creator_campaign.audit_failure',
                'Creator campaign mutation committed but global audit emission failed.',
                [
                    'action' => $action,
                    'campaign_id' => $payload['campaign_id'],
                    'exception_class' => $error::class,
                ],
                $actorUserId
            );
        }
    }
}
