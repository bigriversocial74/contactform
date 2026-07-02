<?php
declare(strict_types=1);

require_once __DIR__ . '/_ops_readiness.php';

function mg_admin_ops_installer_final_notification_enum(): string
{
    return "ALTER TABLE admin_queue_notifications\n  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due','repeat_incident_detected','prevention_task_overdue','incident_trend_worsening','risk_forecast_high','forecasted_sla_breach','queue_overload_predicted') NOT NULL DEFAULT 'digest';";
}

function mg_admin_ops_installer_reviews_table_sql(): string
{
    return "CREATE TABLE IF NOT EXISTS admin_ops_incident_reviews (\n  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n  public_id CHAR(36) NOT NULL,\n  incident_id BIGINT UNSIGNED NOT NULL,\n  review_summary TEXT NOT NULL,\n  customer_impact TEXT NOT NULL,\n  merchant_impact TEXT NOT NULL,\n  action_items TEXT NOT NULL,\n  followup_owner_user_id BIGINT UNSIGNED NULL,\n  followup_due_at DATETIME NULL,\n  status ENUM('draft','completed','followup_open','followup_complete') NOT NULL DEFAULT 'draft',\n  completed_by_user_id BIGINT UNSIGNED NULL,\n  completed_at DATETIME NULL,\n  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  PRIMARY KEY (id),\n  UNIQUE KEY uq_admin_ops_incident_reviews_public_id (public_id),\n  UNIQUE KEY uq_admin_ops_incident_reviews_incident (incident_id),\n  KEY idx_admin_ops_incident_reviews_status_due (status,followup_due_at),\n  KEY idx_admin_ops_incident_reviews_owner_due (followup_owner_user_id,followup_due_at),\n  CONSTRAINT fk_admin_ops_incident_reviews_incident FOREIGN KEY (incident_id) REFERENCES admin_ops_incidents(id) ON DELETE CASCADE,\n  CONSTRAINT fk_admin_ops_incident_reviews_owner FOREIGN KEY (followup_owner_user_id) REFERENCES users(id) ON DELETE SET NULL,\n  CONSTRAINT fk_admin_ops_incident_reviews_completed_by FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
}

function mg_admin_ops_installer_permissions_sql(array $missingPermissions): string
{
    $rows = [
        'admin.operations_reviews.view' => "('admin.operations_reviews.view','View admin incident reviews','View incident timelines, structured reviews, action items, and follow-up status.',NOW())",
        'admin.operations_reviews.manage' => "('admin.operations_reviews.manage','Manage admin incident reviews','Create and complete incident reviews and follow-up action items.',NOW())",
        'admin.operations_analytics.view' => "('admin.operations_analytics.view','View admin incident analytics','View incident analytics, repeat issue detection, prevention score, and trend intelligence.',NOW())",
        'admin.operations_forecast.view' => "('admin.operations_forecast.view','View admin predictive operations','View risk forecasts, predictive operations score, likely failure points, and recommended actions.',NOW())",
    ];
    $selected = [];
    foreach ($missingPermissions as $permission) {
        if (isset($rows[$permission])) {
            $selected[] = $rows[$permission];
        }
    }
    if ($selected === []) {
        return '';
    }
    return "INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES\n" . implode(",\n", $selected) . ";\n\nINSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)\nSELECT r.id,p.id,NOW()\nFROM roles r\nJOIN permissions p ON p.slug IN ('" . implode("','", array_map(static fn(string $p): string => str_replace("'", "''", $p), array_keys($rows))) . "')\nWHERE r.slug IN ('admin','super_admin');";
}

function mg_admin_ops_installer_missing_by_section(array $readiness, string $sectionKey): array
{
    foreach ($readiness['sections'] ?? [] as $section) {
        if (($section['key'] ?? '') !== $sectionKey) {
            continue;
        }
        return array_values(array_map(static fn(array $check): string => (string)$check['key'], array_filter($section['checks'] ?? [], static fn(array $check): bool => empty($check['ready']))));
    }
    return [];
}

function mg_admin_ops_installer_plan(PDO $pdo): array
{
    $readiness = mg_admin_ops_readiness_read($pdo);
    $missingTables = mg_admin_ops_installer_missing_by_section($readiness, 'tables');
    $missingEnums = mg_admin_ops_installer_missing_by_section($readiness, 'notification_enum');
    $missingPermissions = mg_admin_ops_installer_missing_by_section($readiness, 'permissions');
    $warnings = [];
    $blocks = [];
    $blocks[] = '-- Microgifter Admin Ops Installer Plan';
    $blocks[] = '-- Generated: ' . gmdate('c');
    $blocks[] = '-- This is a read-only generated plan. Review before importing.';
    $blocks[] = 'SET FOREIGN_KEY_CHECKS = 0;';

    if (in_array('admin_ops_incidents', $missingTables, true) || in_array('admin_ops_incident_updates', $missingTables, true)) {
        $warnings[] = 'Stage 18X incident tables are missing. Run database/stage_18x_admin_ops_incidents.sql before this plan.';
        $blocks[] = '-- WARNING: Stage 18X incident tables are missing. Run database/stage_18x_admin_ops_incidents.sql first.';
    }
    if (in_array('admin_ops_incident_reviews', $missingTables, true)) {
        $blocks[] = mg_admin_ops_installer_reviews_table_sql();
    }
    if ($missingEnums !== [] && mg_admin_system_health_table_exists($pdo, 'admin_queue_notifications')) {
        $blocks[] = mg_admin_ops_installer_final_notification_enum();
    } elseif ($missingEnums !== []) {
        $warnings[] = 'admin_queue_notifications is missing, so notification enum recovery was not generated.';
        $blocks[] = '-- WARNING: admin_queue_notifications is missing. Notification enum recovery was not generated.';
    }
    $permissionSql = mg_admin_ops_installer_permissions_sql($missingPermissions);
    if ($permissionSql !== '') {
        $blocks[] = $permissionSql;
    }
    $blocks[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    if (count($blocks) <= 5 && $warnings === []) {
        $blocks[] = '-- No admin ops SQL changes are required. Readiness is already satisfied.';
    }
    $sql = implode("\n\n", $blocks) . "\n";
    return [
        'ready' => (bool)$readiness['ready'],
        'missing_total' => (int)$readiness['missing_total'],
        'warnings' => $warnings,
        'filename' => 'microgifter_admin_ops_recovery_' . gmdate('Ymd_His') . '.sql',
        'sql' => $sql,
        'sql_bytes' => strlen($sql),
        'readiness' => $readiness,
        'recommended_order' => ['1. Pull latest main to the server.', '2. Import the generated SQL in phpMyAdmin if SQL is present.', '3. Refresh System Health and verify Admin Ops Deployment Readiness.'],
    ];
}
