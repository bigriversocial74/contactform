<?php
declare(strict_types=1);

function mg_system_sql_diag_safe_name(string $name): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
}

function mg_system_sql_diag_table_exists(PDO $pdo, string $table): bool
{
    $table = mg_system_sql_diag_safe_name($table);
    if ($table === '') return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try {
            $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 0');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

function mg_system_sql_diag_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    $table = mg_system_sql_diag_safe_name($table);
    if ($table === '') return [];
    $key = spl_object_hash($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[(string)$row['COLUMN_NAME']] = $row;
        }
        return $cache[$key] = $columns;
    } catch (Throwable) {
        return $cache[$key] = [];
    }
}

function mg_system_sql_diag_indexes(PDO $pdo, string $table): array
{
    $table = mg_system_sql_diag_safe_name($table);
    if ($table === '') return [];
    try {
        $stmt = $pdo->prepare('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? GROUP BY INDEX_NAME');
        $stmt->execute([$table]);
        $indexes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[(string)$row['INDEX_NAME']] = true;
        }
        return $indexes;
    } catch (Throwable) {
        return [];
    }
}

function mg_system_sql_diag_enum_values(PDO $pdo, string $table, string $column): array
{
    $columns = mg_system_sql_diag_columns($pdo, $table);
    $type = (string)($columns[$column]['COLUMN_TYPE'] ?? '');
    if (stripos($type, 'enum(') !== 0) return [];
    $inner = substr($type, 5, -1);
    $values = str_getcsv($inner, ',', "'");
    return array_values(array_filter(array_map(static fn($value): string => stripcslashes((string)$value), $values), static fn($value): bool => $value !== ''));
}

function mg_system_sql_diag_catalog(): array
{
    return [
        [
            'key' => 'identity_access',
            'label' => 'Identity, roles, and permissions',
            'migration_hint' => 'Core user and permission migrations',
            'endpoints' => ['/api/admin/dashboard.php','/api/admin/users.php','/api/admin/roles-permissions.php'],
            'tables' => [
                'users' => ['columns' => ['id','email','display_name','full_name','created_at','updated_at']],
                'roles' => ['columns' => ['id','slug','name']],
                'permissions' => ['columns' => ['id','slug','name']],
                'role_permissions' => ['columns' => ['role_id','permission_id']],
            ],
        ],
        [
            'key' => 'admin_dashboard_health',
            'label' => 'Admin dashboard and system health',
            'migration_hint' => 'Stage 18 production hardening and admin ops migrations',
            'endpoints' => ['/api/admin/system-health.php','/api/admin/dashboard.php','/api/admin/admin-ops-readiness.php'],
            'tables' => [
                'schema_migrations' => ['columns' => ['id','migration_key','checksum','applied_at']],
                'security_logs' => ['columns' => ['id','severity','event_type','message','created_at']],
                'operational_alerts' => ['columns' => ['id','severity','status','alert_type','title','body','created_at']],
                'audit_logs' => ['columns' => ['id','action','entity_type','metadata_json','created_at']],
            ],
        ],
        [
            'key' => 'admin_queue',
            'label' => 'Admin queue, SLA, reporting, and automation',
            'migration_hint' => 'Stage 18O/18R/18U/18V plus admin_queue_schema_repair_20260701.sql',
            'endpoints' => ['/api/admin/queue-reporting.php','/api/admin/queue-automation.php','/api/admin/support-queue-sla.php','/api/admin/support-queue.php'],
            'tables' => [
                'admin_user_notes' => ['columns' => [
                    'id','public_id','target_user_id','assigned_admin_user_id','status','priority','category','flag_state','note','reason','due_at','closed_at','resolved_at','updated_at','created_at',
                    'routed_lane','sla_due_at','sla_status','auto_escalated_at','last_routed_at','sla_policy_json','playbook_slug','resolution_template_slug','resolution_outcome','resolution_confidence','followup_required','reopened_after_resolution','notes_incomplete','resolution_reviewed_at'
                ], 'enums' => ['sla_status' => ['compliant','at_risk','breached','paused','resolved']]],
                'admin_queue_notifications' => ['columns' => ['id','public_id','note_id','target_user_id','assigned_admin_user_id','actor_user_id','notification_type','severity','title','message','metadata_json','read_at','created_at'], 'enums' => ['notification_type' => ['assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','automation_summary','automation_failed','quality_review']]],
                'admin_queue_automation_runs' => ['columns' => ['id','public_id','actor_user_id','run_mode','status','processed_count','alerts_created_count','sla_updated_count','auto_routed_count','auto_escalated_count','quality_flags_count','unresolved_aging_count','summary_json','error_message','started_at','completed_at']],
            ],
        ],
        [
            'key' => 'operations_risk_forecast',
            'label' => 'Operations risk forecast',
            'migration_hint' => 'Admin operations command, incident, and queue SLA migrations',
            'endpoints' => ['/api/admin/operations-risk-forecast.php','/api/admin/operations-command.php','/api/admin/operations-incident.php'],
            'tables' => [
                'admin_user_notes' => ['columns' => ['status','sla_status','sla_due_at','due_at','assigned_admin_user_id','created_at']],
                'admin_queue_automation_runs' => ['columns' => ['status','started_at','completed_at']],
                'admin_ops_incidents' => ['columns' => ['id','mode_slug','severity','status','declared_at']],
                'admin_ops_incident_reviews' => ['columns' => ['id','status','followup_due_at']],
            ],
        ],
        [
            'key' => 'merchant_workspace_ai',
            'label' => 'Merchant workspace and AI context',
            'migration_hint' => 'Merchant workspace, memory, campaign, wallet, claim, and payment readiness migrations',
            'endpoints' => ['/api/merchant/overview.php','/api/ai/merchant-agent-chat.php','/api/merchant/agent-memory.php'],
            'tables' => [
                'merchant_workspaces' => ['columns' => ['id','public_id','merchant_user_id','display_name','business_type','status','eligibility_status','onboarding_percent','timezone','default_currency','created_at','updated_at']],
                'merchant_onboarding_steps' => ['columns' => ['id','workspace_id','step_key','step_order','status','created_at','updated_at']],
                'merchant_locations' => ['columns' => ['id','public_id','workspace_id','name','city','region','country_code','status','is_primary','updated_at']],
                'merchant_payment_readiness' => ['columns' => ['id','workspace_id','provider_key','mode','account_connected','identity_verified','charges_enabled','payouts_enabled','tax_setup_complete','test_payment_complete','live_approved','updated_at']],
                'agents' => ['columns' => ['id','public_id','user_id','name','category','runtime_status','lifecycle_status','version_no','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'campaign_rewards_wallet',
            'label' => 'Campaigns, rewards, wallet, and claims',
            'migration_hint' => 'Campaign/reward/wallet/claim migrations',
            'endpoints' => ['/api/merchant/campaigns.php','/api/merchant/reward-templates.php','/api/account/action-center.php'],
            'tables' => [
                'reward_templates' => ['columns' => ['id','public_id','merchant_user_id','title','reward_type','value_type','value_amount_cents','value_percent','currency','expiration_rule','expiration_days','quantity_limit','issued_count','per_user_limit','agent_discoverable','status','updated_at']],
                'campaigns' => ['columns' => ['id','public_id','merchant_user_id','reward_template_id','campaign_type','title','status','starts_at','ends_at','quantity_limit','issued_count','per_user_limit','agent_discoverable','updated_at']],
                'campaign_contacts' => ['columns' => ['id','campaign_id','merchant_user_id','source','opt_in_status','created_at']],
                'campaign_events' => ['columns' => ['id','public_id','campaign_id','merchant_user_id','event_type','created_at']],
                'wallet_items' => ['columns' => ['id','campaign_id','merchant_user_id','status','source_type','value_cents_snapshot','created_at']],
                'microgift_claim_attempts' => ['columns' => ['id','merchant_user_id','result','attempted_at']],
                'microgift_claim_escalations' => ['columns' => ['id','merchant_user_id','status','created_at']],
            ],
        ],
        [
            'key' => 'campaign_ads',
            'label' => 'Campaign Ads Manager',
            'migration_hint' => 'database/microgifter_ads_manager_phase1.sql and follow-up Campaign Ads migrations',
            'endpoints' => ['/api/ads/admin-diagnostics.php','/api/ads/merchant-campaigns.php','/api/ads/placements.php'],
            'tables' => [
                'ad_campaigns' => ['columns' => ['id','public_id','merchant_user_id','objective','status','budget_type','created_at','updated_at']],
                'ad_creatives' => ['columns' => ['id','campaign_id','public_id','headline','body','status','created_at','updated_at']],
                'ad_placements' => ['columns' => ['id','placement_key','status','created_at','updated_at']],
                'ad_campaign_placements' => ['columns' => ['id','campaign_id','placement_id','status','created_at','updated_at']],
                'ad_targeting_rules' => ['columns' => ['id','campaign_id','rule_type','rule_value_json','created_at']],
                'ad_events' => ['columns' => ['id','campaign_id','event_type','created_at']],
                'ad_reviews' => ['columns' => ['id','campaign_id','review_status','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'feed_media_storage',
            'label' => 'Feed, media, and storage references',
            'migration_hint' => 'Persistent media, feed post, and catalog asset migrations',
            'endpoints' => ['/api/feed/posts.php','/api/merchant/design-studio-assets.php','/api/account/action-center.php'],
            'tables' => [
                'catalog_assets' => ['columns' => ['id','public_id','storage_provider','storage_key','byte_size','status','metadata_json','updated_at']],
                'feed_posts' => ['columns' => ['id','public_id','user_id','media_json','status','created_at','updated_at']],
                'feed_post_assets' => ['columns' => ['id','post_id','asset_id','created_at']],
                'catalog_products' => ['columns' => ['id','public_id','merchant_user_id','name','status','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'notifications_pwa',
            'label' => 'Notifications and PWA push',
            'migration_hint' => 'Notification delivery and PWA push migrations',
            'endpoints' => ['/api/notifications/index.php','/api/communications/preferences.php','/api/admin/system-health.php'],
            'tables' => [
                'notifications' => ['columns' => ['id','public_id','user_id','type','title','body','read_at','created_at']],
                'notification_preferences' => ['columns' => ['id','user_id','channel','enabled','updated_at']],
                'notification_delivery_jobs' => ['columns' => ['id','status','attempt_count','next_attempt_at','failed_at','failure_code','failure_message','updated_at']],
                'pwa_push_subscriptions' => ['columns' => ['id','user_id','endpoint_hash','status','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'commerce_payments',
            'label' => 'Commerce, subscriptions, and payments',
            'migration_hint' => 'Commerce operations, Stripe payments, subscriptions, tips, and PPPM migrations',
            'endpoints' => ['/api/admin/commerce-operations.php','/api/merchant/payments.php','/api/account/subscriptions.php'],
            'tables' => [
                'commerce_orders' => ['columns' => ['id','public_id','buyer_user_id','merchant_user_id','status','total_cents','created_at','updated_at']],
                'payment_refunds' => ['columns' => ['id','order_id','status','amount_cents','created_at']],
                'payment_disputes' => ['columns' => ['id','order_id','status','created_at']],
                'subscriptions' => ['columns' => ['id','user_id','status','plan_key','created_at','updated_at']],
                'tips' => ['columns' => ['id','from_user_id','to_user_id','status','amount_cents','created_at']],
                'microgift_instances' => ['columns' => ['id','public_id','sender_user_id','recipient_user_id','merchant_user_id','status','created_at','updated_at']],
                'microgift_claims' => ['columns' => ['id','microgift_instance_id','status','created_at']],
                'microgift_redemptions' => ['columns' => ['id','microgift_instance_id','status','created_at']],
            ],
        ],
    ];
}

function mg_system_sql_diag_column_type(string $table, string $column): ?string
{
    $types = [
        'assigned_admin_user_id' => 'BIGINT UNSIGNED NULL',
        'target_user_id' => 'BIGINT UNSIGNED NULL',
        'actor_user_id' => 'BIGINT UNSIGNED NULL',
        'due_at' => 'DATETIME NULL',
        'closed_at' => 'DATETIME NULL',
        'resolved_at' => 'DATETIME NULL',
        'routed_lane' => "ENUM('support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general') NOT NULL DEFAULT 'general'",
        'sla_due_at' => 'DATETIME NULL',
        'sla_status' => "ENUM('compliant','at_risk','breached','paused','resolved') NOT NULL DEFAULT 'compliant'",
        'auto_escalated_at' => 'DATETIME NULL',
        'last_routed_at' => 'DATETIME NULL',
        'sla_policy_json' => 'JSON NULL',
        'resolution_outcome' => "ENUM('resolved_successfully','escalated_externally','merchant_action_required','customer_action_required','billing_adjustment','risk_restriction','catalog_correction','no_action_needed') NULL",
        'resolution_confidence' => "ENUM('high','medium','low','unknown') NOT NULL DEFAULT 'unknown'",
        'followup_required' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'reopened_after_resolution' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'notes_incomplete' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'resolution_reviewed_at' => 'DATETIME NULL',
        'metadata_json' => 'JSON NULL',
        'read_at' => 'DATETIME NULL',
    ];
    return $types[$column] ?? null;
}

function mg_system_sql_diag_legacy_add_column_sql(string $table, string $column, string $type): string
{
    $tableSql = str_replace("'", "''", $table);
    $columnSql = str_replace("'", "''", $column);
    $alter = str_replace("'", "''", 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $type);
    return "SET @sql := IF(\n" .
        "  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableSql}') = 1\n" .
        "  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableSql}' AND COLUMN_NAME = '{$columnSql}') = 0,\n" .
        "  '{$alter}',\n" .
        "  'SELECT \"{$tableSql}.{$columnSql} exists or table missing\" AS status'\n" .
        ");\nPREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;\n";
}

function mg_system_sql_diag_recent_sql_errors(PDO $pdo, int $limit = 20): array
{
    $items = [];
    $limit = max(1, min(50, $limit));
    if (mg_system_sql_diag_table_exists($pdo, 'security_logs')) {
        $columns = mg_system_sql_diag_columns($pdo, 'security_logs');
        if (isset($columns['event_type'], $columns['message'], $columns['severity'], $columns['created_at'])) {
            try {
                $stmt = $pdo->query("SELECT severity,event_type,message,created_at FROM security_logs WHERE severity IN ('warning','error','critical') AND (event_type LIKE '%failed%' OR message LIKE '%SQLSTATE%' OR message LIKE '%Unknown column%' OR message LIKE '%Base table%' OR message LIKE '%syntax%') ORDER BY created_at DESC,id DESC LIMIT {$limit}");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $items[] = ['source'=>'security_logs','severity'=>(string)$row['severity'],'title'=>(string)$row['event_type'],'message'=>(string)$row['message'],'created_at'=>$row['created_at'] ?? null];
                }
            } catch (Throwable) {}
        }
    }
    if (mg_system_sql_diag_table_exists($pdo, 'operational_alerts')) {
        $columns = mg_system_sql_diag_columns($pdo, 'operational_alerts');
        if (isset($columns['alert_type'], $columns['title'], $columns['body'], $columns['severity'], $columns['created_at'])) {
            try {
                $stmt = $pdo->query("SELECT severity,alert_type,title,body,created_at FROM operational_alerts WHERE status IN ('open','acknowledged') AND (alert_type LIKE '%sql%' OR title LIKE '%SQL%' OR body LIKE '%SQLSTATE%' OR body LIKE '%Unknown column%' OR body LIKE '%Base table%' OR body LIKE '%syntax%') ORDER BY created_at DESC,id DESC LIMIT {$limit}");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $items[] = ['source'=>'operational_alerts','severity'=>(string)$row['severity'],'title'=>(string)($row['title'] ?: $row['alert_type']),'message'=>(string)($row['body'] ?? ''),'created_at'=>$row['created_at'] ?? null];
                }
            } catch (Throwable) {}
        }
    }
    usort($items, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($items, 0, $limit);
}

function mg_system_sql_diag_probe_table(PDO $pdo, string $table): ?array
{
    $table = mg_system_sql_diag_safe_name($table);
    if ($table === '') return null;
    try {
        $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 0');
        return null;
    } catch (Throwable $error) {
        return ['table'=>$table,'sqlstate'=>$error instanceof PDOException ? (string)$error->getCode() : null,'message'=>$error->getMessage(),'exception_class'=>$error::class];
    }
}

function mg_system_sql_diagnostics(PDO $pdo): array
{
    $catalog = mg_system_sql_diag_catalog();
    $modules = [];
    $findings = [];
    $endpointChecks = [];
    $repairSql = "-- Microgifter System SQL Diagnostics Repair Plan\n-- Generated: " . gmdate('c') . "\n-- Review before running. Missing tables usually require their original migration file.\n\n";
    $repairCount = 0;

    foreach ($catalog as $module) {
        $missingTables = [];
        $missingColumns = [];
        $missingEnums = [];
        $missingIndexes = [];
        $probeErrors = [];
        $tablesOut = [];
        foreach (($module['tables'] ?? []) as $table => $requirements) {
            $exists = mg_system_sql_diag_table_exists($pdo, $table);
            $tableOut = ['name'=>$table,'ready'=>$exists,'missing_columns'=>[],'missing_enums'=>[],'missing_indexes'=>[]];
            if (!$exists) {
                $missingTables[] = $table;
                $findings[] = ['severity'=>'critical','module'=>$module['key'],'type'=>'missing_table','table'=>$table,'item'=>$table,'message'=>'Missing table: ' . $table,'repairable'=>false,'migration_hint'=>$module['migration_hint'] ?? null];
                $repairSql .= "-- Missing table {$table}. Run migration: " . ($module['migration_hint'] ?? 'original module migration') . "\n";
                $tablesOut[] = $tableOut;
                continue;
            }
            $probe = mg_system_sql_diag_probe_table($pdo, $table);
            if ($probe !== null) {
                $probeErrors[] = $probe;
                $findings[] = ['severity'=>'critical','module'=>$module['key'],'type'=>'table_probe_failed','table'=>$table,'item'=>$table,'message'=>$probe['message'],'repairable'=>false,'migration_hint'=>$module['migration_hint'] ?? null];
            }
            $columns = mg_system_sql_diag_columns($pdo, $table);
            foreach (($requirements['columns'] ?? []) as $column) {
                if (!isset($columns[$column])) {
                    $missingColumns[] = $table . '.' . $column;
                    $tableOut['missing_columns'][] = $column;
                    $type = mg_system_sql_diag_column_type($table, $column);
                    $findings[] = ['severity'=>'critical','module'=>$module['key'],'type'=>'missing_column','table'=>$table,'column'=>$column,'item'=>$table . '.' . $column,'message'=>'Missing column: ' . $table . '.' . $column,'repairable'=>$type !== null,'migration_hint'=>$module['migration_hint'] ?? null];
                    if ($type !== null) {
                        $repairSql .= mg_system_sql_diag_legacy_add_column_sql($table, $column, $type) . "\n";
                        $repairCount++;
                    } else {
                        $repairSql .= "-- Missing column {$table}.{$column}. Type not auto-generated; run migration: " . ($module['migration_hint'] ?? 'original module migration') . "\n";
                    }
                }
            }
            foreach (($requirements['enums'] ?? []) as $column => $values) {
                if (!isset($columns[$column])) continue;
                $actual = mg_system_sql_diag_enum_values($pdo, $table, $column);
                $missing = array_values(array_diff($values, $actual));
                if ($missing !== []) {
                    $missingEnums[] = $table . '.' . $column . ':' . implode('|', $missing);
                    $tableOut['missing_enums'][] = ['column'=>$column,'missing'=>$missing];
                    $findings[] = ['severity'=>'warning','module'=>$module['key'],'type'=>'missing_enum_values','table'=>$table,'column'=>$column,'item'=>$table . '.' . $column,'message'=>'Missing enum values on ' . $table . '.' . $column . ': ' . implode(', ', $missing),'repairable'=>false,'migration_hint'=>$module['migration_hint'] ?? null];
                }
            }
            $indexes = mg_system_sql_diag_indexes($pdo, $table);
            foreach (($requirements['indexes'] ?? []) as $index) {
                if (empty($indexes[$index])) {
                    $missingIndexes[] = $table . '.' . $index;
                    $tableOut['missing_indexes'][] = $index;
                    $findings[] = ['severity'=>'warning','module'=>$module['key'],'type'=>'missing_index','table'=>$table,'item'=>$table . '.' . $index,'message'=>'Missing index: ' . $table . '.' . $index,'repairable'=>false,'migration_hint'=>$module['migration_hint'] ?? null];
                }
            }
            $tableOut['ready'] = $tableOut['missing_columns'] === [] && $tableOut['missing_enums'] === [] && $tableOut['missing_indexes'] === [] && $probe === null;
            $tablesOut[] = $tableOut;
        }
        $ready = $missingTables === [] && $missingColumns === [] && $probeErrors === [];
        $status = !$ready ? 'critical' : (($missingEnums || $missingIndexes) ? 'warning' : 'healthy');
        foreach (($module['endpoints'] ?? []) as $endpoint) {
            $endpointChecks[] = ['endpoint'=>$endpoint,'module'=>$module['key'],'method'=>'GET/schema-preflight','status'=>$status === 'critical' ? 'blocked' : 'ready','missing_count'=>count($missingTables) + count($missingColumns),'safe_smoke'=>true,'destructive'=>false];
        }
        $modules[] = [
            'key'=>$module['key'],
            'label'=>$module['label'],
            'status'=>$status,
            'ready'=>$status === 'healthy',
            'summary'=>$status === 'healthy' ? 'All required SQL dependencies are present.' : (($status === 'warning') ? 'Required tables and columns are present, but enum/index drift needs review.' : 'One or more required SQL dependencies are missing.'),
            'missing_tables'=>$missingTables,
            'missing_columns'=>$missingColumns,
            'missing_enums'=>$missingEnums,
            'missing_indexes'=>$missingIndexes,
            'probe_errors'=>$probeErrors,
            'tables'=>$tablesOut,
            'migration_hint'=>$module['migration_hint'] ?? null,
        ];
    }

    $recentErrors = mg_system_sql_diag_recent_sql_errors($pdo, 20);
    $critical = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'critical'));
    $warning = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'warning'));
    $status = $critical > 0 ? 'critical' : ($warning > 0 || $recentErrors ? 'warning' : 'healthy');
    return [
        'status'=>$status,
        'summary'=>$status === 'healthy' ? 'No SQL dependency issues were detected in the diagnostics catalog.' : ($critical > 0 ? $critical . ' critical SQL dependency issue(s) need attention.' : 'SQL diagnostics found warnings or recent SQL-related failures.'),
        'counts'=>[
            'modules'=>count($modules),
            'healthy_modules'=>count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'healthy')),
            'warning_modules'=>count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'warning')),
            'critical_modules'=>count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'critical')),
            'findings'=>count($findings),
            'critical_findings'=>$critical,
            'warning_findings'=>$warning,
            'recent_sql_errors'=>count($recentErrors),
            'repairable_findings'=>count(array_filter($findings, static fn(array $f): bool => !empty($f['repairable']))),
        ],
        'modules'=>$modules,
        'findings'=>array_slice($findings, 0, 200),
        'endpoint_checks'=>$endpointChecks,
        'recent_sql_errors'=>$recentErrors,
        'repair_plan'=>[
            'available'=>$repairCount > 0,
            'repairable_count'=>$repairCount,
            'filename'=>'microgifter_system_sql_repair_' . gmdate('Ymd_His') . '.sql',
            'sql'=>$repairSql,
            'sql_bytes'=>strlen($repairSql),
        ],
        'generated_at'=>gmdate('c'),
        'request_id'=>function_exists('mg_request_id') ? mg_request_id() : null,
    ];
}
