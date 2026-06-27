<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_store_schema_expected_tables(): array
{
    return [
        'mg_agents' => ['public_id','owner_user_id','merchant_user_id','account_type','agent_type','display_name','avatar_url','status','metadata_json','created_at','updated_at'],
        'mg_store_sessions' => ['public_id','customer_user_id','merchant_user_id','customer_agent_id','merchant_agent_id','store_agent_id','source_feed_post_id','source_campaign_id','status','active_key','entered_at','last_active_at','exited_at','exit_reason','metadata_json','created_at','updated_at'],
        'mg_store_session_events' => ['public_id','store_session_id','customer_user_id','merchant_user_id','event_type','event_label','event_data_json','created_at'],
        'mg_customer_store_history' => ['public_id','customer_user_id','merchant_user_id','store_session_id','source_feed_post_id','summary','started_at','ended_at','duration_seconds','messages_received_count','rewards_received_count','rewards_claimed_count','products_viewed_count','gifts_sent_count','metadata_json','created_at','updated_at'],
        'mg_agent_messages' => ['public_id','store_session_id','sender_user_id','recipient_user_id','merchant_user_id','sender_role','message_type','subject','body','status','read_at','metadata_json','created_at','updated_at'],
    ];
}

function mg_store_schema_columns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $stmt->execute([$table]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable) {
        return [];
    }
}

function mg_store_schema_count(PDO $pdo, string $table): ?int
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return null;
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable) {
        return null;
    }
}

function mg_store_schema_migration(PDO $pdo): array
{
    $migration = [
        'table_exists' => mg_store_table_exists($pdo, 'schema_migrations'),
        'key' => 'stage_20_agent_store_canvas',
        'applied' => false,
        'applied_at' => null,
        'description' => '',
    ];
    if (!$migration['table_exists']) return $migration;
    try {
        $stmt = $pdo->prepare('SELECT migration_key,description,applied_at FROM schema_migrations WHERE migration_key=? LIMIT 1');
        $stmt->execute([$migration['key']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $migration['applied'] = true;
            $migration['applied_at'] = $row['applied_at'] ?? null;
            $migration['description'] = (string)($row['description'] ?? '');
        }
    } catch (Throwable) {}
    return $migration;
}

try {
    $expected = mg_store_schema_expected_tables();
    $tables = [];
    $missingTables = [];
    $missingColumns = [];
    foreach ($expected as $table => $requiredColumns) {
        $installed = mg_store_table_exists($pdo, $table);
        $columns = $installed ? mg_store_schema_columns($pdo, $table) : [];
        $missing = $installed ? array_values(array_diff($requiredColumns, $columns)) : $requiredColumns;
        if (!$installed) $missingTables[] = $table;
        if ($missing !== []) $missingColumns[$table] = $missing;
        $tables[] = [
            'name' => $table,
            'installed' => $installed,
            'required_columns' => $requiredColumns,
            'installed_columns' => $columns,
            'missing_columns' => $missing,
            'row_count' => $installed ? mg_store_schema_count($pdo, $table) : null,
        ];
    }

    $ready = $missingTables === [] && $missingColumns === [];
    $migration = mg_store_schema_migration($pdo);
    $readCheck = ['ok' => false, 'message' => 'Not checked'];
    if ($ready) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $readCheck = ['ok' => true, 'message' => 'Store Canvas session read check passed.', 'active_sessions' => (int)$stmt->fetchColumn()];
        } catch (Throwable $error) {
            $readCheck = ['ok' => false, 'message' => 'Store Canvas session read check failed.'];
            $ready = false;
        }
    } else {
        $readCheck = ['ok' => false, 'message' => 'Store Canvas tables or columns are missing.'];
    }

    mg_ok([
        'schema_ready' => $ready,
        'migration' => $migration,
        'migration_file' => 'database/stage_20_agent_store_canvas.sql',
        'install_command' => 'mysql < database/stage_20_agent_store_canvas.sql',
        'guidance' => $ready ? 'Stage 20 Store Canvas schema is ready.' : 'Install or re-run database/stage_20_agent_store_canvas.sql, then refresh this page.',
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
        'tables' => $tables,
        'checks' => ['session_read' => $readCheck],
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.schema_status_failed', 'Store Canvas schema status failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to inspect Store Canvas schema.', 500);
}
