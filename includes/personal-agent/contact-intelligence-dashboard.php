<?php
declare(strict_types=1);

function mg_personal_agent_dashboard_with_contact_intelligence(PDO $pdo, int $userId): array
{
    $dashboard = mg_personal_agent_dashboard($pdo, $userId);
    $signals = [];
    $schemaReady = function_exists('mg_personal_agent_contact_intelligence_schema_ready')
        && mg_personal_agent_contact_intelligence_schema_ready($pdo);

    if ($schemaReady && function_exists('mg_personal_agent_contact_signals')) {
        try {
            // Dashboard loading must remain read-only and must never take down agent.php.
            $signals = mg_personal_agent_contact_signals($pdo, $userId, false);
        } catch (Throwable $error) {
            $signals = [];
            if (function_exists('mg_security_log')) {
                mg_security_log(
                    'warning',
                    'user_agent.contact_intelligence_dashboard_failed',
                    'Contact intelligence was skipped so the Personal Agent dashboard could continue loading.',
                    ['exception_type' => $error::class],
                    $userId
                );
            }
        }
    }

    $dashboard['signals'] = $signals;
    $dashboard['summary']['signals'] = count($signals);
    $dashboard['capabilities']['contact_intelligence'] = [
        'read' => $schemaReady,
        'write' => $schemaReady && function_exists('mg_personal_agent_contact_actions_allowed')
            ? mg_personal_agent_contact_actions_allowed()
            : false,
        'confirmation_required' => true,
        'private_to_account' => true,
        'deterministic_queries_do_not_use_ai_credits' => true,
        'available' => $schemaReady,
    ];
    return $dashboard;
}

function mg_personal_agent_recent_action_receipts(PDO $pdo, int $userId, int $limit = 20): array
{
    if (!function_exists('mg_personal_agent_contact_intelligence_schema_ready')
        || !mg_personal_agent_contact_intelligence_schema_ready($pdo)) {
        return [];
    }

    try {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT public_id,action_type,entity_type,entity_public_id,summary,result_json,created_at FROM user_agent_action_receipts WHERE owner_user_id=? ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute([$userId]);
        return array_map(static fn(array $row): array => [
            'id' => (string) $row['public_id'],
            'action_type' => (string) $row['action_type'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (string) ($row['entity_public_id'] ?? ''),
            'summary' => (string) $row['summary'],
            'result' => mg_personal_agent_json($row['result_json'] ?? null),
            'created_at' => (string) $row['created_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log(
                'warning',
                'user_agent.contact_intelligence_receipts_failed',
                'Contact action receipts could not be loaded.',
                ['exception_type' => $error::class],
                $userId
            );
        }
        return [];
    }
}
