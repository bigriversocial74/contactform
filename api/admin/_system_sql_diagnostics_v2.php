<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_sql_diagnostics.php';

function mg_system_sql_diag_catalog_v2(): array
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
                'admin_user_notes' => ['columns' => ['id','public_id','target_user_id','assigned_admin_user_id','status','priority','category','flag_state','note','reason','due_at','closed_at','resolved_at','updated_at','created_at','routed_lane','sla_due_at','sla_status','auto_escalated_at','last_routed_at','sla_policy_json','playbook_slug','resolution_template_slug','resolution_outcome','resolution_confidence','followup_required','reopened_after_resolution','notes_incomplete','resolution_reviewed_at'], 'enums' => ['sla_status' => ['compliant','at_risk','breached','paused','resolved']]],
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
                'ad_campaigns' => ['columns' => ['id','public_id','merchant_id','campaign_id','target_zone_id','title','objective','status','budget_type','created_at','updated_at']],
                'ad_creatives' => ['columns' => ['id','public_id','ad_campaign_id','headline','description','cta_label','destination_type','destination_id','sponsored_label','metadata_json','created_at','updated_at']],
                'ad_placements' => ['columns' => ['id','placement_key','placement_name','surface','description','is_active','max_ads','created_at','updated_at']],
                'ad_campaign_placements' => ['columns' => ['id','ad_campaign_id','placement_key','priority','status','created_at','updated_at']],
                'ad_targeting_rules' => ['columns' => ['id','ad_campaign_id','rule_type','rule_value_json','created_at']],
                'ad_events' => ['columns' => ['id','public_id','ad_campaign_id','merchant_id','event_type','surface','placement_key','created_at']],
                'ad_reviews' => ['columns' => ['id','ad_campaign_id','review_status','review_notes','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'feed_media_storage',
            'label' => 'Feed, media, and storage references',
            'migration_hint' => 'Stage 4A/4C/18H feed, product asset, and storefront migrations',
            'endpoints' => ['/api/feed/posts.php','/api/merchant/design-studio-assets.php','/api/account/action-center.php'],
            'tables' => [
                'catalog_assets' => ['columns' => ['id','public_id','owner_user_id','asset_type','storage_provider','storage_key','byte_size','status','metadata_json','updated_at']],
                'feed_posts' => ['columns' => ['id','public_id','merchant_user_id','catalog_product_id','current_version_id','post_type','visibility','status','created_by_user_id','created_at','updated_at']],
                'feed_post_assets' => ['columns' => ['id','feed_post_id','asset_id','role','sort_order','created_at','updated_at']],
                'catalog_products' => ['columns' => ['id','public_id','merchant_user_id','product_type','slug','status','current_version_id','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'notifications_pwa',
            'label' => 'Notifications and PWA push',
            'migration_hint' => 'Stage 3, Stage 5H, and Stage 25 notification/PWA migrations',
            'endpoints' => ['/api/notifications/index.php','/api/communications/preferences.php','/api/admin/system-health.php'],
            'tables' => [
                'notifications' => ['columns' => ['id','public_id','user_id','type','title','body','read_at','created_at']],
                'notification_preferences' => ['columns' => ['id','user_id','notification_type','in_app_enabled','email_enabled','sms_enabled','push_enabled','digest_mode','updated_at']],
                'notification_delivery_jobs' => ['columns' => ['id','public_id','notification_id','user_id','channel','status','attempt_count','next_attempt_at','failed_at','failure_code','failure_message','updated_at']],
                'pwa_push_subscriptions' => ['columns' => ['id','public_id','user_id','endpoint_hash','endpoint_url','subscription_json','status','created_at','updated_at']],
            ],
        ],
        [
            'key' => 'commerce_payments',
            'label' => 'Commerce, subscriptions, and payments',
            'migration_hint' => 'Stage 5I payments, Stage 9 microgift lifecycle, subscription, tips, and PPPM migrations',
            'endpoints' => ['/api/admin/commerce-operations.php','/api/merchant/payments.php','/api/account/subscriptions.php'],
            'tables' => [
                'commerce_orders' => ['columns' => ['id','public_id','buyer_user_id','merchant_user_id','payment_status','fulfillment_status','total_cents','created_at','updated_at']],
                'payment_refunds' => ['columns' => ['id','public_id','order_id','status','amount_cents','created_at']],
                'payment_disputes' => ['columns' => ['id','public_id','order_id','status','amount_cents','created_at']],
                'subscriptions' => ['columns' => ['id','user_id','status','plan_key','created_at','updated_at']],
                'tips' => ['columns' => ['id','from_user_id','to_user_id','status','amount_cents','created_at']],
                'microgift_instances' => ['columns' => ['id','public_id','sender_user_id','recipient_user_id','merchant_user_id','status','created_at','updated_at']],
                'microgift_claims' => ['columns' => ['id','public_id','instance_id','status','created_at']],
                'microgift_redemptions' => ['columns' => ['id','public_id','instance_id','status','created_at']],
            ],
        ],
    ];
}

function mg_system_sql_diag_repair_plan_header_v2(array $findings, array $recentErrors): string
{
    $sql = "-- Microgifter System SQL Diagnostics Plan\n";
    $sql .= "-- Generated: " . gmdate('c') . "\n";
    $sql .= "-- Review before running. This file may include comments for manual migration items.\n";
    $sql .= "-- Findings: " . count($findings) . "; recent SQL-related warnings: " . count($recentErrors) . "\n\n";
    foreach (array_slice($findings, 0, 200) as $finding) {
        $sql .= "-- [" . strtoupper((string)($finding['severity'] ?? 'warning')) . "] " . (string)($finding['item'] ?? $finding['type'] ?? 'finding') . ' — ' . str_replace(["\r","\n"], ' ', (string)($finding['message'] ?? 'Review required.')) . "\n";
        if (!empty($finding['migration_hint'])) {
            $sql .= "--     Migration hint: " . str_replace(["\r","\n"], ' ', (string)$finding['migration_hint']) . "\n";
        }
    }
    if ($recentErrors) {
        $sql .= "\n-- Recent SQL-related warnings/errors\n";
        foreach (array_slice($recentErrors, 0, 20) as $error) {
            $sql .= "-- [" . strtoupper((string)($error['severity'] ?? 'warning')) . "] " . (string)($error['title'] ?? 'SQL warning') . ' — ' . str_replace(["\r","\n"], ' ', (string)($error['message'] ?? '')) . "\n";
        }
    }
    $sql .= "\n";
    return $sql;
}

function mg_system_sql_diagnostics_v2(PDO $pdo): array
{
    $catalog = mg_system_sql_diag_catalog_v2();
    $modules = [];
    $findings = [];
    $endpointChecks = [];
    $repairSqlBody = '';
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
            $tableOut = ['name' => $table, 'ready' => $exists, 'missing_columns' => [], 'missing_enums' => [], 'missing_indexes' => []];
            if (!$exists) {
                $missingTables[] = $table;
                $findings[] = ['severity' => 'critical', 'module' => $module['key'], 'type' => 'missing_table', 'table' => $table, 'item' => $table, 'message' => 'Missing table: ' . $table, 'repairable' => false, 'migration_hint' => $module['migration_hint'] ?? null];
                $tablesOut[] = $tableOut;
                continue;
            }

            $probe = mg_system_sql_diag_probe_table($pdo, $table);
            if ($probe !== null) {
                $probeErrors[] = $probe;
                $findings[] = ['severity' => 'critical', 'module' => $module['key'], 'type' => 'table_probe_failed', 'table' => $table, 'item' => $table, 'message' => $probe['message'], 'repairable' => false, 'migration_hint' => $module['migration_hint'] ?? null];
            }

            $columns = mg_system_sql_diag_columns($pdo, $table);
            foreach (($requirements['columns'] ?? []) as $column) {
                if (!isset($columns[$column])) {
                    $missingColumns[] = $table . '.' . $column;
                    $tableOut['missing_columns'][] = $column;
                    $type = mg_system_sql_diag_column_type($table, $column);
                    $repairable = $type !== null && function_exists('mg_system_sql_diag_legacy_add_column_sql');
                    $findings[] = ['severity' => 'critical', 'module' => $module['key'], 'type' => 'missing_column', 'table' => $table, 'column' => $column, 'item' => $table . '.' . $column, 'message' => 'Missing column: ' . $table . '.' . $column, 'repairable' => $repairable, 'migration_hint' => $module['migration_hint'] ?? null];
                    if ($repairable) {
                        $repairSqlBody .= mg_system_sql_diag_legacy_add_column_sql($table, $column, (string)$type) . "\n";
                        $repairCount++;
                    }
                }
            }

            foreach (($requirements['enums'] ?? []) as $column => $values) {
                if (!isset($columns[$column])) continue;
                $actual = mg_system_sql_diag_enum_values($pdo, $table, $column);
                $missing = array_values(array_diff($values, $actual));
                if ($missing !== []) {
                    $missingEnums[] = $table . '.' . $column . ':' . implode('|', $missing);
                    $tableOut['missing_enums'][] = ['column' => $column, 'missing' => $missing];
                    $findings[] = ['severity' => 'warning', 'module' => $module['key'], 'type' => 'missing_enum_values', 'table' => $table, 'column' => $column, 'item' => $table . '.' . $column, 'message' => 'Missing enum values on ' . $table . '.' . $column . ': ' . implode(', ', $missing), 'repairable' => false, 'migration_hint' => $module['migration_hint'] ?? null];
                }
            }

            $indexes = mg_system_sql_diag_indexes($pdo, $table);
            foreach (($requirements['indexes'] ?? []) as $index) {
                if (empty($indexes[$index])) {
                    $missingIndexes[] = $table . '.' . $index;
                    $tableOut['missing_indexes'][] = $index;
                    $findings[] = ['severity' => 'warning', 'module' => $module['key'], 'type' => 'missing_index', 'table' => $table, 'item' => $table . '.' . $index, 'message' => 'Missing index: ' . $table . '.' . $index, 'repairable' => false, 'migration_hint' => $module['migration_hint'] ?? null];
                }
            }
            $tableOut['ready'] = $tableOut['missing_columns'] === [] && $tableOut['missing_enums'] === [] && $tableOut['missing_indexes'] === [] && $probe === null;
            $tablesOut[] = $tableOut;
        }

        $ready = $missingTables === [] && $missingColumns === [] && $probeErrors === [];
        $status = !$ready ? 'critical' : (($missingEnums || $missingIndexes) ? 'warning' : 'healthy');
        foreach (($module['endpoints'] ?? []) as $endpoint) {
            $endpointChecks[] = ['endpoint' => $endpoint, 'module' => $module['key'], 'method' => 'GET/schema-preflight', 'status' => $status === 'critical' ? 'blocked' : 'ready', 'missing_count' => count($missingTables) + count($missingColumns), 'safe_smoke' => true, 'destructive' => false];
        }
        $modules[] = ['key' => $module['key'], 'label' => $module['label'], 'status' => $status, 'ready' => $status === 'healthy', 'summary' => $status === 'healthy' ? 'All required SQL dependencies are present.' : (($status === 'warning') ? 'Required tables and columns are present, but enum/index drift needs review.' : 'One or more required SQL dependencies are missing.'), 'missing_tables' => $missingTables, 'missing_columns' => $missingColumns, 'missing_enums' => $missingEnums, 'missing_indexes' => $missingIndexes, 'probe_errors' => $probeErrors, 'tables' => $tablesOut, 'migration_hint' => $module['migration_hint'] ?? null];
    }

    $recentErrors = mg_system_sql_diag_recent_sql_errors($pdo, 20);
    $critical = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'critical'));
    $warning = count(array_filter($findings, static fn(array $f): bool => ($f['severity'] ?? '') === 'warning'));
    $status = $critical > 0 ? 'critical' : ($warning > 0 || $recentErrors ? 'warning' : 'healthy');
    $repairSql = mg_system_sql_diag_repair_plan_header_v2($findings, $recentErrors) . ($repairSqlBody !== '' ? $repairSqlBody : "-- No automatic ALTER statements were generated. Run the migration files listed above for manual items.\nSELECT 'System SQL diagnostics plan generated' AS status;\n");

    return [
        'status' => $status,
        'summary' => $status === 'healthy' ? 'No SQL dependency issues were detected in the diagnostics catalog.' : ($critical > 0 ? $critical . ' critical SQL dependency issue(s) need attention.' : 'SQL diagnostics found warnings or recent SQL-related failures.'),
        'counts' => [
            'modules' => count($modules),
            'healthy_modules' => count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'healthy')),
            'warning_modules' => count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'warning')),
            'critical_modules' => count(array_filter($modules, static fn(array $m): bool => ($m['status'] ?? '') === 'critical')),
            'findings' => count($findings),
            'critical_findings' => $critical,
            'warning_findings' => $warning,
            'recent_sql_errors' => count($recentErrors),
            'repairable_findings' => count(array_filter($findings, static fn(array $f): bool => !empty($f['repairable']))),
        ],
        'modules' => $modules,
        'findings' => array_slice($findings, 0, 200),
        'endpoint_checks' => $endpointChecks,
        'recent_sql_errors' => $recentErrors,
        'repair_plan' => [
            'available' => count($findings) > 0 || count($recentErrors) > 0 || $repairCount > 0,
            'repairable_count' => $repairCount,
            'filename' => 'microgifter_system_sql_diagnostics_' . gmdate('Ymd_His') . '.sql',
            'sql' => $repairSql,
            'sql_bytes' => strlen($repairSql),
        ],
        'generated_at' => gmdate('c'),
        'request_id' => function_exists('mg_request_id') ? mg_request_id() : null,
        'catalog_version' => '2026-07-01.2',
    ];
}
