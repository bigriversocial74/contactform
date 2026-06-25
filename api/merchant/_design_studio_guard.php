<?php
declare(strict_types=1);

function mg_design_studio_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_design_studio_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_design_studio_missing_tables(PDO $pdo, array $tables): array
{
    $missing = [];
    foreach ($tables as $table) {
        if (!mg_design_studio_table_exists($pdo, (string) $table)) $missing[] = (string) $table;
    }
    return $missing;
}

function mg_design_studio_require_tables(PDO $pdo, array $tables): void
{
    $missing = mg_design_studio_missing_tables($pdo, $tables);
    if ($missing) {
        mg_fail('Design Studio setup is incomplete. Import database/stage_19_design_studio_qr_library.sql before using this endpoint.', 503);
    }
}

function mg_design_studio_core_tables(): array
{
    return [
        'merchant_qr_codes',
        'merchant_qr_code_scans',
        'merchant_brand_kits',
        'merchant_brand_kit_assets',
        'merchant_design_templates',
        'merchant_design_template_reviews',
        'merchant_design_projects',
        'merchant_design_ai_jobs',
        'merchant_design_assets',
        'merchant_design_export_jobs',
        'merchant_design_campaign_links',
        'merchant_design_ai_presets',
    ];
}
