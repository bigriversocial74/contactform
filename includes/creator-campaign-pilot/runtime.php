<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';

function mg_creator_campaign_pilot_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1'
    );
    $stmt->execute([$table]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function mg_creator_campaign_pilot_schema_ready(PDO $pdo): bool
{
    foreach ([
        'creator_campaign_operator_pilots',
        'creator_campaign_operator_events',
        'creator_campaign_operator_handoffs',
    ] as $table) {
        if (!mg_creator_campaign_pilot_table_exists($pdo, $table)) {
            return false;
        }
    }
    return true;
}

function mg_creator_campaign_pilot_emergency_state(PDO $pdo, int $workspaceId): ?array
{
    if ($workspaceId < 1 || !mg_creator_campaign_pilot_schema_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT public_id,status,emergency_disabled,emergency_reason,emergency_disabled_at
         FROM creator_campaign_operator_pilots
         WHERE workspace_id=? LIMIT 1'
    );
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_creator_campaign_pilot_assert_playbook_enabled(PDO $pdo, int $workspaceId): void
{
    $state = mg_creator_campaign_pilot_emergency_state($pdo, $workspaceId);
    if (!$state || empty($state['emergency_disabled'])) {
        return;
    }
    $message = 'Creator Campaign pilot emergency stop is active. No new bounded playbook run may start.';
    if (class_exists('MgMcpCreatorCampaignPlaybookException')) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $message,
            423,
            'MCP_CREATOR_CAMPAIGN_PILOT_EMERGENCY_DISABLED'
        );
    }
    throw new RuntimeException($message);
}
