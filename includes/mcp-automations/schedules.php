<?php
declare(strict_types=1);

const MG_MCP_AUTOMATION_SCHEDULE_TRIGGER_TYPES = ['fixed_schedule', 'recurring_schedule'];
const MG_MCP_AUTOMATION_SCHEDULE_TIMEZONES = [
    'UTC',
    'America/Phoenix',
    'America/Los_Angeles',
    'America/Denver',
    'America/Chicago',
    'America/New_York',
];
const MG_MCP_AUTOMATION_SCHEDULE_INTERVALS = [3600, 21600, 43200, 86400, 604800];

function mg_mcp_automation_schedule_timezone(mixed $value): string
{
    $timezone = trim((string)$value);
    if (!in_array($timezone, MG_MCP_AUTOMATION_SCHEDULE_TIMEZONES, true)) {
        throw new MgMcpAutomationGrantException('Select a supported schedule timezone.', 422, 'MCP_AUTOMATION_TIMEZONE_INVALID');
    }
    return $timezone;
}

function mg_mcp_automation_schedule_due_utc(mixed $value, string $timezone): string
{
    $local = trim((string)$value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone($timezone));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) {
        throw new MgMcpAutomationGrantException('Enter a valid schedule date and time.', 422, 'MCP_AUTOMATION_SCHEDULE_TIME_INVALID');
    }
    $utc = $date->setTimezone(new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($utc <= $now->modify('+4 minutes')) {
        throw new MgMcpAutomationGrantException('Schedule the first simulation at least five minutes in the future.', 422, 'MCP_AUTOMATION_SCHEDULE_TOO_SOON');
    }
    if ($utc > $now->modify('+1 year')) {
        throw new MgMcpAutomationGrantException('Schedule the first simulation within one year.', 422, 'MCP_AUTOMATION_SCHEDULE_TOO_FAR');
    }
    return $utc->format('Y-m-d H:i:s');
}

function mg_mcp_automation_update_schedule_authority(PDO $pdo, array $user, string $grantPublicId, array $input): array
{
    $userId = (int)($user['id'] ?? 0);
    $reason = mg_mcp_automation_text($input['reason'] ?? '', 5, 255, 'Authority-change reason');
    $types = ['manual'];
    foreach (MG_MCP_AUTOMATION_SCHEDULE_TRIGGER_TYPES as $type) {
        if (!empty($input[$type])) {
            $types[] = $type;
        }
    }

    $pdo->beginTransaction();
    try {
        $grant = mg_mcp_automation_lock_owner_grant($pdo, $userId, $grantPublicId);
        if (in_array((string)$grant['status'], ['expired', 'revoked'], true)) {
            throw new MgMcpAutomationGrantException('Expired or revoked grants cannot receive schedule authority.', 409, 'MCP_AUTOMATION_GRANT_INACTIVE');
        }
        if ((string)$grant['status'] === 'active') {
            mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        }
        $currentTypes = mg_mcp_automation_json_list($grant['allowed_trigger_types_json']);
        sort($currentTypes);
        sort($types);
        if ($currentTypes !== $types) {
            $pdo->prepare(
                'UPDATE mcp_automation_grants SET allowed_trigger_types_json=?,revocation_version=revocation_version+1,updated_at=NOW() WHERE id=?'
            )->execute([json_encode($types, JSON_THROW_ON_ERROR), (int)$grant['id']]);

            $removed = array_values(array_diff(MG_MCP_AUTOMATION_SCHEDULE_TRIGGER_TYPES, $types));
            if ($removed !== []) {
                $placeholders = implode(',', array_fill(0, count($removed), '?'));
                $pdo->prepare(
                    "UPDATE mcp_automation_triggers t
                     INNER JOIN mcp_automations a ON a.id=t.automation_id
                     SET t.status='paused',t.next_due_at=NULL,t.updated_at=NOW(),a.next_run_at=NULL,a.updated_at=NOW()
                     WHERE a.grant_id=? AND t.trigger_type IN ($placeholders)"
                )->execute(array_merge([(int)$grant['id']], $removed));
            }
        }

        $connection = [
            'id' => (int)$grant['connection_id'],
            'client_id' => (int)$grant['client_id'],
            'workspace_type' => $grant['workspace_type'],
            'workspace_id' => $grant['workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_schedule_authority.updated', 'Owner updated MCP scheduled-simulation authority.', [
            'grant_public_id' => $grantPublicId,
            'allowed_trigger_types' => $types,
            'reason' => $reason,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
        ]);
        $pdo->commit();

        $metadata = [
            'grant_public_id' => $grantPublicId,
            'allowed_trigger_types' => $types,
            'reason' => $reason,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_automation_schedule_authority_updated', 'mcp_automation_grant', $metadata, $userId);
        mg_event('mcp.automation_schedule_authority.updated', $metadata, $userId);
        mg_security_log('info', 'mcp.automation_schedule_authority.updated', 'Owner updated MCP scheduled-simulation authority.', $metadata, $userId);
        return $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_mcp_automation_configure_schedule(PDO $pdo, array $user, string $automationPublicId, array $input): array
{
    $userId = (int)($user['id'] ?? 0);
    $triggerType = strtolower(trim((string)($input['trigger_type'] ?? '')));
    if (!in_array($triggerType, MG_MCP_AUTOMATION_SCHEDULE_TRIGGER_TYPES, true)) {
        throw new MgMcpAutomationGrantException('Select a fixed or recurring simulation schedule.', 422, 'MCP_AUTOMATION_TRIGGER_TYPE_INVALID');
    }
    $timezone = mg_mcp_automation_schedule_timezone($input['timezone'] ?? 'UTC');
    $firstDueAt = mg_mcp_automation_schedule_due_utc($input['first_due_at'] ?? '', $timezone);
    $intervalSeconds = null;
    if ($triggerType === 'recurring_schedule') {
        $intervalSeconds = (int)($input['interval_seconds'] ?? 0);
        if (!in_array($intervalSeconds, MG_MCP_AUTOMATION_SCHEDULE_INTERVALS, true)) {
            throw new MgMcpAutomationGrantException('Select a supported recurring interval.', 422, 'MCP_AUTOMATION_INTERVAL_INVALID');
        }
    }

    $pdo->beginTransaction();
    try {
        $automation = mg_mcp_automation_lock_owner_definition($pdo, $userId, $automationPublicId);
        if ((string)$automation['status'] !== 'active' || (string)$automation['grant_status'] !== 'active') {
            throw new MgMcpAutomationGrantException('Activate the definition and its grant before scheduling simulations.', 409, 'MCP_AUTOMATION_DEFINITION_INACTIVE');
        }
        $grant = mg_mcp_automation_definition_grant_projection($automation);
        mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        $allowedTriggerTypes = mg_mcp_automation_json_list($automation['grant_allowed_trigger_types_json'] ?? []);
        if (!in_array($triggerType, $allowedTriggerTypes, true)) {
            throw new MgMcpAutomationGrantException('The parent grant does not authorize this schedule type.', 403, 'MCP_AUTOMATION_TRIGGER_DENIED');
        }
        if ($triggerType === 'recurring_schedule'
            && $automation['grant_minimum_frequency_seconds'] !== null
            && $intervalSeconds < (int)$automation['grant_minimum_frequency_seconds']) {
            throw new MgMcpAutomationGrantException('The recurring interval is faster than the grant frequency limit.', 403, 'MCP_AUTOMATION_FREQUENCY_LIMIT');
        }

        $configuration = [
            'phase' => 'phase4c',
            'mode' => 'scheduled_simulation_only',
            'simulation_only' => true,
            'scheduler_enabled' => false,
            'owner_manual_due_evaluation' => true,
            'timezone' => $timezone,
            'first_due_at_utc' => $firstDueAt,
            'interval_seconds' => $intervalSeconds,
            'execution_requested' => false,
        ];
        $stmt = $pdo->prepare(
            "SELECT id,public_id FROM mcp_automation_triggers
             WHERE automation_id=? AND trigger_type=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([(int)$automation['id'], $triggerType]);
        $trigger = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($trigger) {
            $pdo->prepare(
                "UPDATE mcp_automation_triggers SET status='active',configuration_json=?,next_due_at=?,last_fired_at=NULL,fire_count=0,updated_at=NOW() WHERE id=?"
            )->execute([json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $firstDueAt, (int)$trigger['id']]);
            $triggerPublicId = (string)$trigger['public_id'];
        } else {
            $triggerPublicId = mg_public_uuid();
            $pdo->prepare(
                "INSERT INTO mcp_automation_triggers
                 (public_id,automation_id,trigger_type,status,configuration_json,next_due_at,created_at,updated_at)
                 VALUES (?,?,?,'active',?,?,NOW(),NOW())"
            )->execute([
                $triggerPublicId,
                (int)$automation['id'],
                $triggerType,
                json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $firstDueAt,
            ]);
        }
        $otherType = $triggerType === 'fixed_schedule' ? 'recurring_schedule' : 'fixed_schedule';
        $pdo->prepare("UPDATE mcp_automation_triggers SET status='paused',next_due_at=NULL,updated_at=NOW() WHERE automation_id=? AND trigger_type=?")
            ->execute([(int)$automation['id'], $otherType]);
        $pdo->prepare('UPDATE mcp_automations SET timezone=?,next_run_at=?,current_version=current_version+1,updated_at=NOW() WHERE id=?')
            ->execute([$timezone, $firstDueAt, (int)$automation['id']]);

        $connection = [
            'id' => (int)$automation['connection_id'],
            'client_id' => (int)$automation['client_id'],
            'workspace_type' => $automation['grant_workspace_type'],
            'workspace_id' => $automation['grant_workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_schedule.configured', 'Owner configured an MCP scheduled simulation.', [
            'automation_public_id' => $automationPublicId,
            'trigger_public_id' => $triggerPublicId,
            'trigger_type' => $triggerType,
            'next_due_at' => $firstDueAt,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
        ]);
        $pdo->commit();

        $metadata = [
            'automation_public_id' => $automationPublicId,
            'trigger_public_id' => $triggerPublicId,
            'trigger_type' => $triggerType,
            'next_due_at' => $firstDueAt,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_automation_schedule_configured', 'mcp_automation_trigger', $metadata, $userId);
        mg_event('mcp.automation_schedule.configured', $metadata, $userId);
        mg_security_log('info', 'mcp.automation_schedule.configured', 'Owner configured an MCP scheduled simulation.', $metadata, $userId);
        return $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_mcp_automation_remove_schedule(PDO $pdo, array $user, string $automationPublicId, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $reason = mg_mcp_automation_text($reason, 5, 255, 'Schedule-removal reason');
    $pdo->beginTransaction();
    try {
        $automation = mg_mcp_automation_lock_owner_definition($pdo, $userId, $automationPublicId);
        $pdo->prepare(
            "UPDATE mcp_automation_triggers SET status='paused',next_due_at=NULL,updated_at=NOW()
             WHERE automation_id=? AND trigger_type IN ('fixed_schedule','recurring_schedule')"
        )->execute([(int)$automation['id']]);
        $pdo->prepare('UPDATE mcp_automations SET next_run_at=NULL,current_version=current_version+1,updated_at=NOW() WHERE id=?')
            ->execute([(int)$automation['id']]);
        $connection = [
            'id' => (int)$automation['connection_id'],
            'client_id' => (int)$automation['client_id'],
            'workspace_type' => $automation['grant_workspace_type'],
            'workspace_id' => $automation['grant_workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_schedule.removed', 'Owner removed an MCP scheduled simulation.', [
            'automation_public_id' => $automationPublicId,
            'reason' => $reason,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
        ]);
        $pdo->commit();
        return ['automation_public_id' => $automationPublicId, 'status' => 'paused'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_mcp_automation_owner_schedules(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT t.public_id,t.trigger_type,t.status,t.configuration_json,t.next_due_at,t.last_fired_at,t.fire_count,
                a.public_id AS automation_public_id,a.name AS automation_name,a.status AS automation_status,a.timezone,
                g.public_id AS grant_public_id,g.status AS grant_status
         FROM mcp_automation_triggers t
         INNER JOIN mcp_automations a ON a.id=t.automation_id
         INNER JOIN mcp_automation_grants g ON g.id=a.grant_id
         WHERE a.owner_user_id=? AND g.authorizing_user_id=?
           AND t.trigger_type IN ('fixed_schedule','recurring_schedule')
         ORDER BY FIELD(t.status,'active','paused','expired','revoked'),t.next_due_at IS NULL,t.next_due_at,t.updated_at DESC"
    );
    $stmt->execute([$userId, $userId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['trigger_type'],
        'status' => (string)$row['status'],
        'configuration' => mg_mcp_automation_json_object($row['configuration_json']),
        'next_due_at' => $row['next_due_at'] !== null ? (string)$row['next_due_at'] : null,
        'last_fired_at' => $row['last_fired_at'] !== null ? (string)$row['last_fired_at'] : null,
        'fire_count' => (int)$row['fire_count'],
        'automation' => [
            'id' => (string)$row['automation_public_id'],
            'name' => (string)$row['automation_name'],
            'status' => (string)$row['automation_status'],
            'timezone' => (string)$row['timezone'],
        ],
        'grant' => ['id' => (string)$row['grant_public_id'], 'status' => (string)$row['grant_status']],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_recent_scheduled_simulations(PDO $pdo, int $userId, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT r.public_id,r.status,r.scheduled_at,r.completed_at,r.output_summary_json,
                a.public_id AS automation_public_id,a.name AS automation_name,a.playbook_key,
                t.public_id AS trigger_public_id,t.trigger_type,COUNT(aa.id) AS action_count
         FROM mcp_automation_runs r
         INNER JOIN mcp_automations a ON a.id=r.automation_id
         INNER JOIN mcp_automation_grants g ON g.id=r.grant_id
         LEFT JOIN mcp_automation_triggers t ON t.id=r.trigger_id
         LEFT JOIN mcp_automation_actions aa ON aa.run_id=r.id
         WHERE a.owner_user_id=? AND g.authorizing_user_id=?
           AND JSON_UNQUOTE(JSON_EXTRACT(r.output_summary_json,'$.mode'))='scheduled_simulation_only'
         GROUP BY r.id,a.id,t.id
         ORDER BY r.created_at DESC,r.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([$userId, $userId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'scheduled_at' => $row['scheduled_at'] !== null ? (string)$row['scheduled_at'] : null,
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
        'summary' => mg_mcp_automation_json_object($row['output_summary_json']),
        'automation' => ['id' => (string)$row['automation_public_id'], 'name' => (string)$row['automation_name']],
        'playbook_key' => (string)$row['playbook_key'],
        'trigger' => ['id' => (string)$row['trigger_public_id'], 'type' => (string)$row['trigger_type']],
        'action_count' => (int)$row['action_count'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_evaluate_due_schedules(PDO $pdo, array $user, int $limit = 10): array
{
    $userId = (int)($user['id'] ?? 0);
    $limit = max(1, min(25, $limit));
    $stmt = $pdo->prepare(
        "SELECT t.public_id,a.public_id AS automation_public_id
         FROM mcp_automation_triggers t
         INNER JOIN mcp_automations a ON a.id=t.automation_id
         INNER JOIN mcp_automation_grants g ON g.id=a.grant_id
         WHERE a.owner_user_id=? AND g.authorizing_user_id=?
           AND a.status='active' AND g.status='active' AND t.status='active'
           AND t.trigger_type IN ('fixed_schedule','recurring_schedule')
           AND t.next_due_at IS NOT NULL AND t.next_due_at<=NOW()
         ORDER BY t.next_due_at,t.id
         LIMIT " . $limit
    );
    $stmt->execute([$userId, $userId]);
    $due = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $completed = [];
    $failed = [];
    foreach ($due as $row) {
        try {
            $completed[] = mg_mcp_automation_run_scheduled_simulation(
                $pdo,
                $user,
                (string)$row['automation_public_id'],
                (string)$row['public_id']
            );
        } catch (Throwable $error) {
            $failed[] = [
                'trigger_id' => (string)$row['public_id'],
                'automation_id' => (string)$row['automation_public_id'],
                'error' => mb_substr($error->getMessage(), 0, 300),
            ];
        }
    }
    return ['due' => count($due), 'completed' => $completed, 'failed' => $failed, 'scheduler_enabled' => false];
}
