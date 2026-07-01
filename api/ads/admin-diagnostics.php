<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
mg_ads_require_admin_user($user);

function mg_ads_diag_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) return false;
    static $cache = [];
    $key = spl_object_id($pdo) . '|' . strtolower($table) . '.' . strtolower($column);
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $database = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
        if ($database !== '') {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
            $stmt->execute([$database, $table, $column]);
            if ($stmt->fetchColumn()) return $cache[$key] = true;
        }
    } catch (Throwable) {
        // Fall through to DESCRIBE for shared hosts with limited information_schema access.
    }
    try {
        $stmt = $pdo->query('DESCRIBE `' . str_replace('`', '``', $table) . '` `' . str_replace('`', '``', $column) . '`');
        return $cache[$key] = (bool)($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_ads_diag_last_event(array $events, string $type = ''): string
{
    $latest = '';
    foreach ($events as $eventType => $event) {
        if ($type !== '' && $eventType !== $type) continue;
        $candidate = (string)($event['last_at'] ?? '');
        if ($candidate !== '' && ($latest === '' || strtotime($candidate) > strtotime($latest))) $latest = $candidate;
    }
    return $latest;
}

function mg_ads_diag_event_public(array $events): array
{
    $out = [];
    foreach (mg_ads_allowed_events() as $type) {
        $row = $events[$type] ?? ['count' => 0, 'last_at' => null];
        $out[$type] = ['count' => (int)($row['count'] ?? 0), 'last_at' => $row['last_at'] ?? null];
    }
    return $out;
}

function mg_ads_diag_load(PDO $pdo): array
{
    $schema = mg_ads_schema_status($pdo);
    $tableChecks = $schema['tables'];
    $optionalTables = [
        'wallet_items' => mg_ads_table_exists($pdo, 'wallet_items'),
        'campaigns' => mg_ads_table_exists($pdo, 'campaigns'),
        'reward_templates' => mg_ads_table_exists($pdo, 'reward_templates'),
    ];
    $columns = [
        'ad_events.wallet_item_id' => mg_ads_table_exists($pdo, 'ad_events') && mg_ads_diag_column_exists($pdo, 'ad_events', 'wallet_item_id'),
        'wallet_items.metadata_json' => mg_ads_table_exists($pdo, 'wallet_items') && mg_ads_diag_column_exists($pdo, 'wallet_items', 'metadata_json'),
        'wallet_items.value_cents_snapshot' => mg_ads_table_exists($pdo, 'wallet_items') && mg_ads_diag_column_exists($pdo, 'wallet_items', 'value_cents_snapshot'),
    ];

    if (!$schema['ready']) {
        return [
            'schema_ready' => false,
            'summary' => [
                'status' => 'setup_required',
                'placements_total' => count(mg_ads_allowed_placements()),
                'placements_enabled' => 0,
                'placements_returning_ads' => 0,
                'active_assignments' => 0,
                'warnings' => count(array_filter($tableChecks, static fn($ready) => !$ready)),
            ],
            'tables' => $tableChecks,
            'optional_tables' => $optionalTables,
            'columns' => $columns,
            'placements' => [],
            'notes' => ['Campaign Ads Manager migration is required before diagnostics can run.'],
        ];
    }

    mg_ads_seed_placements($pdo);

    $placementsStmt = $pdo->query('SELECT placement_key, placement_name, surface, description, is_active, max_ads, updated_at FROM ad_placements ORDER BY FIELD(placement_key,\'feed_sponsored_card\',\'sidebar_sponsored_card\',\'world_canvas_sponsored_pin\',\'target_zone_sponsored_drop\',\'inbox_recommendation\',\'claim_success_recommendation\',\'campaign_drops_map\',\'wallet_recommendation\'), placement_key ASC');
    $placements = $placementsStmt ? $placementsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $assignSql = "SELECT cp.placement_key, cp.status assignment_status, cp.priority, c.public_id, c.title, c.status campaign_status, c.merchant_id, cr.headline FROM ad_campaign_placements cp INNER JOIN ad_campaigns c ON c.id=cp.ad_campaign_id LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE c.status<>'archived' AND cp.status<>'archived' ORDER BY cp.placement_key ASC, cp.priority ASC, cp.updated_at DESC";
    $assignStmt = $pdo->query($assignSql);
    $assignmentsByPlacement = [];
    foreach (($assignStmt ? $assignStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $key = (string)($row['placement_key'] ?? '');
        if ($key === '') continue;
        $profile = mg_ads_merchant_profile($pdo, (int)($row['merchant_id'] ?? 0));
        $assignmentsByPlacement[$key][] = [
            'campaign_id' => (string)($row['public_id'] ?? ''),
            'title' => (string)($row['title'] ?? 'Sponsored Campaign'),
            'headline' => (string)($row['headline'] ?? ''),
            'campaign_status' => (string)($row['campaign_status'] ?? ''),
            'assignment_status' => (string)($row['assignment_status'] ?? ''),
            'priority' => (int)($row['priority'] ?? 100),
            'merchant_name' => (string)($profile['merchant_name'] ?? 'Microgifter Merchant'),
        ];
    }

    $eventStmt = $pdo->query("SELECT placement_key,event_type,COUNT(*) total,MAX(created_at) last_at FROM ad_events WHERE placement_key IS NOT NULL AND placement_key<>'' GROUP BY placement_key,event_type");
    $eventsByPlacement = [];
    foreach (($eventStmt ? $eventStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $key = (string)($row['placement_key'] ?? '');
        $type = (string)($row['event_type'] ?? '');
        if ($key === '' || $type === '') continue;
        $eventsByPlacement[$key][$type] = ['count' => (int)($row['total'] ?? 0), 'last_at' => $row['last_at'] ?? null];
    }

    $walletLinked = 0;
    if ($columns['ad_events.wallet_item_id']) {
        try {
            $walletLinked = (int)($pdo->query('SELECT COUNT(*) FROM ad_events WHERE wallet_item_id IS NOT NULL')->fetchColumn() ?: 0);
        } catch (Throwable) {
            $walletLinked = 0;
        }
    }

    $diagnostics = [];
    $enabled = 0;
    $returning = 0;
    $activeAssignments = 0;
    $warnings = 0;

    foreach ($placements as $placement) {
        $key = (string)($placement['placement_key'] ?? '');
        $isActive = (int)($placement['is_active'] ?? 0) === 1;
        $maxAds = max(1, min(20, (int)($placement['max_ads'] ?? 1)));
        $assignments = $assignmentsByPlacement[$key] ?? [];
        $activeRows = array_values(array_filter($assignments, static fn($row) => ($row['assignment_status'] ?? '') === 'active' && in_array((string)($row['campaign_status'] ?? ''), ['approved','active'], true)));
        $renderItems = [];
        $renderOk = false;
        $renderMessage = 'Not rendered.';
        if ($isActive) {
            try {
                $renderItems = mg_ads_render_placement($pdo, $key, min(5, $maxAds));
                $renderOk = true;
                $renderMessage = count($renderItems) > 0 ? 'Render API should return ads.' : 'Render API returned no ads.';
            } catch (Throwable $error) {
                $renderMessage = $error->getMessage();
            }
        } else {
            $renderMessage = 'Placement is disabled.';
        }

        $events = mg_ads_diag_event_public($eventsByPlacement[$key] ?? []);
        $lastAny = mg_ads_diag_last_event($events);
        $lastImpression = mg_ads_diag_last_event($events, 'impression');
        $issues = [];
        if (!$isActive) $issues[] = 'Placement disabled';
        if ($maxAds < 1) $issues[] = 'Max ads is below 1';
        if (!$activeRows) $issues[] = 'No approved/active campaign assignments';
        if ($isActive && $activeRows && count($renderItems) < 1) $issues[] = 'Active placement has assignments but render test returned zero ads';
        if ($isActive && count($renderItems) > 0 && $lastImpression === '') $issues[] = 'Ads can render, but no impressions have been tracked yet';
        if ($key === 'wallet_recommendation') $issues[] = 'Wallet placement intentionally not active/user-facing yet';
        if ($key === 'campaign_drops_map') $issues[] = 'Campaign Drops Map is reserved; World Canvas currently owns map surfaces';

        $status = 'ok';
        if (!$isActive) $status = 'inactive';
        elseif ($activeRows && count($renderItems) > 0) $status = 'ready';
        elseif ($activeRows) $status = 'blocked';
        else $status = 'needs_assignment';

        $enabled += $isActive ? 1 : 0;
        $returning += count($renderItems) > 0 ? 1 : 0;
        $activeAssignments += count($activeRows);
        $warnings += count($issues);

        $diagnostics[] = [
            'placement_key' => $key,
            'placement_name' => (string)($placement['placement_name'] ?? $key),
            'surface' => (string)($placement['surface'] ?? ''),
            'description' => (string)($placement['description'] ?? ''),
            'is_active' => $isActive,
            'max_ads' => $maxAds,
            'updated_at' => $placement['updated_at'] ?? null,
            'status' => $status,
            'issues' => $issues,
            'assignments' => $assignments,
            'active_assignment_count' => count($activeRows),
            'render_ok' => $renderOk,
            'render_count' => count($renderItems),
            'render_message' => $renderMessage,
            'render_sample' => array_map(static fn($item) => ['id' => (string)($item['public_id'] ?? ''), 'title' => (string)($item['title'] ?? ''), 'headline' => (string)($item['creative']['headline'] ?? '')], $renderItems),
            'events' => $events,
            'last_event_at' => $lastAny,
            'last_impression_at' => $lastImpression,
        ];
    }

    return [
        'schema_ready' => true,
        'summary' => [
            'status' => $warnings > 0 ? 'needs_attention' : 'ready',
            'placements_total' => count($placements),
            'placements_enabled' => $enabled,
            'placements_returning_ads' => $returning,
            'active_assignments' => $activeAssignments,
            'warnings' => $warnings,
            'wallet_linked_events' => $walletLinked,
        ],
        'tables' => $tableChecks,
        'optional_tables' => $optionalTables,
        'columns' => $columns,
        'placements' => $diagnostics,
        'notes' => [
            'Diagnostics are read-only and use existing Campaign Ads tables.',
            'Render checks call the same placement renderer used by public surfaces.',
            'Wallet recommendation remains excluded from user-facing activation unless a real user-facing wallet surface is created later.',
        ],
    ];
}

try {
    mg_ok(mg_ads_diag_load($pdo), 'Campaign Ads diagnostics loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.admin_diagnostics_failed', 'Campaign Ads diagnostics failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail($error->getMessage(), 422);
}
