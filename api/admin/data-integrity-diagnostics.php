<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

function mg_data_integrity_table_exists(PDO $pdo, string $table): bool
{
    return mg_admin_system_health_table_exists($pdo, $table);
}

function mg_data_integrity_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1 || preg_match('/^[a-z0-9_]{1,64}$/', $column) !== 1) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_data_integrity_can_check(PDO $pdo, array $tables, array $columns = []): bool
{
    foreach ($tables as $table) {
        if (!mg_data_integrity_table_exists($pdo, $table)) return false;
    }
    foreach ($columns as $table => $items) {
        foreach ((array)$items as $column) {
            if (!mg_data_integrity_column_exists($pdo, (string)$table, (string)$column)) return false;
        }
    }
    return true;
}

function mg_data_integrity_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mg_data_integrity_sample(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_data_integrity_check(string $key, string $label, string $severity, bool $available, int $count = 0, string $summary = '', array $sample = [], array $details = []): array
{
    $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'warning';
    return [
        'key' => $key,
        'label' => $label,
        'available' => $available,
        'status' => !$available ? 'not_available' : ($count > 0 ? $severity : 'healthy'),
        'severity' => $severity,
        'count' => $available ? max(0, $count) : null,
        'summary' => $summary,
        'sample' => array_slice($sample, 0, 8),
        'details' => $details,
    ];
}

function mg_data_integrity_unavailable(string $key, string $label, array $requires): array
{
    return mg_data_integrity_check($key, $label, 'info', false, 0, 'Required tables or columns are not available for this diagnostic.', [], ['requires' => $requires]);
}

function mg_data_integrity_blog_checks(PDO $pdo): array
{
    $checks = [];

    if (!mg_data_integrity_can_check($pdo, ['blog_posts', 'blog_categories'], ['blog_posts' => ['id', 'title', 'slug', 'category_id', 'status', 'deleted_at'], 'blog_categories' => ['id']])) {
        return [mg_data_integrity_unavailable('blog_posts_orphan_category', 'Blog posts with missing category', ['blog_posts', 'blog_categories'])];
    }

    $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.deleted_at IS NULL AND p.category_id IS NOT NULL AND c.id IS NULL");
    $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT p.id,p.slug,p.title,p.category_id,p.status FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.deleted_at IS NULL AND p.category_id IS NOT NULL AND c.id IS NULL ORDER BY p.updated_at DESC,p.id DESC LIMIT 8") : [];
    $checks[] = mg_data_integrity_check('blog_posts_orphan_category', 'Blog posts with missing category', 'warning', true, $count, 'Published or draft posts should not point at deleted or missing categories.', $sample);

    if (mg_data_integrity_can_check($pdo, ['blog_posts'], ['blog_posts' => ['id', 'title', 'slug', 'status', 'published_at', 'deleted_at']])) {
        $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM blog_posts WHERE deleted_at IS NULL AND status = 'published' AND published_at IS NULL");
        $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT id,slug,title,status,published_at FROM blog_posts WHERE deleted_at IS NULL AND status = 'published' AND published_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT 8") : [];
        $checks[] = mg_data_integrity_check('published_blog_missing_date', 'Published Blog posts missing date', 'warning', true, $count, 'Published posts should have a publish timestamp for ordering, RSS, sitemap, and SEO metadata.', $sample);
    }

    return $checks;
}

function mg_data_integrity_media_checks(PDO $pdo): array
{
    if (!mg_data_integrity_can_check($pdo, ['catalog_assets'], ['catalog_assets' => ['id', 'public_id', 'storage_provider', 'storage_key', 'status', 'created_at']])) {
        return [mg_data_integrity_unavailable('catalog_assets_missing_storage', 'Ready catalog assets missing storage keys', ['catalog_assets'])];
    }

    $checks = [];
    $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM catalog_assets WHERE status = 'ready' AND (storage_provider IS NULL OR storage_provider = '' OR storage_key IS NULL OR storage_key = '')");
    $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT id,public_id,asset_type,storage_provider,storage_key,status,created_at FROM catalog_assets WHERE status = 'ready' AND (storage_provider IS NULL OR storage_provider = '' OR storage_key IS NULL OR storage_key = '') ORDER BY id DESC LIMIT 8") : [];
    $checks[] = mg_data_integrity_check('catalog_assets_missing_storage', 'Ready catalog assets missing storage keys', 'critical', true, $count, 'Ready media assets must resolve to a storage provider and key before public or private rendering.', $sample);

    if (mg_data_integrity_can_check($pdo, ['catalog_assets'], ['catalog_assets' => ['public_id']])) {
        $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM catalog_assets WHERE public_id IS NULL OR public_id = ''");
        $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT id,asset_type,status,created_at FROM catalog_assets WHERE public_id IS NULL OR public_id = '' ORDER BY id DESC LIMIT 8") : [];
        $checks[] = mg_data_integrity_check('catalog_assets_missing_public_id', 'Catalog assets missing public ID', 'critical', true, $count, 'Catalog assets need stable public identifiers for controlled media URLs.', $sample);
    }

    return $checks;
}

function mg_data_integrity_pppm_checks(PDO $pdo): array
{
    $checks = [];

    if (mg_data_integrity_can_check($pdo, ['pppm_items', 'pppm_issuance_requests'], ['pppm_items' => ['id', 'public_id', 'issuance_request_id'], 'pppm_issuance_requests' => ['id']])) {
        $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM pppm_items i LEFT JOIN pppm_issuance_requests r ON r.id = i.issuance_request_id WHERE r.id IS NULL");
        $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT i.id,i.public_id,i.issuance_request_id,i.status,i.created_at FROM pppm_items i LEFT JOIN pppm_issuance_requests r ON r.id = i.issuance_request_id WHERE r.id IS NULL ORDER BY i.id DESC LIMIT 8") : [];
        $checks[] = mg_data_integrity_check('pppm_items_missing_request', 'PPPM items missing issuance request', 'critical', true, $count, 'Every PPPM item should map back to an issuance request.', $sample);
    } else {
        $checks[] = mg_data_integrity_unavailable('pppm_items_missing_request', 'PPPM items missing issuance request', ['pppm_items', 'pppm_issuance_requests']);
    }

    if (mg_data_integrity_can_check($pdo, ['pppm_issuance_requests', 'pppm_items'], ['pppm_issuance_requests' => ['id', 'public_id', 'quantity', 'issued_count'], 'pppm_items' => ['issuance_request_id']])) {
        $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM pppm_issuance_requests r LEFT JOIN (SELECT issuance_request_id, COUNT(*) actual_items FROM pppm_items GROUP BY issuance_request_id) x ON x.issuance_request_id = r.id WHERE r.issued_count <> COALESCE(x.actual_items,0) OR r.issued_count > r.quantity");
        $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT r.id,r.public_id,r.quantity,r.issued_count,COALESCE(x.actual_items,0) actual_items,r.status,r.created_at FROM pppm_issuance_requests r LEFT JOIN (SELECT issuance_request_id, COUNT(*) actual_items FROM pppm_items GROUP BY issuance_request_id) x ON x.issuance_request_id = r.id WHERE r.issued_count <> COALESCE(x.actual_items,0) OR r.issued_count > r.quantity ORDER BY r.id DESC LIMIT 8") : [];
        $checks[] = mg_data_integrity_check('pppm_issued_count_mismatch', 'PPPM issued count mismatches', 'warning', true, $count, 'Issuance request issued_count should match created PPPM items and should not exceed quantity.', $sample);
    } else {
        $checks[] = mg_data_integrity_unavailable('pppm_issued_count_mismatch', 'PPPM issued count mismatches', ['pppm_issuance_requests', 'pppm_items']);
    }

    if (mg_data_integrity_can_check($pdo, ['pppm_items'], ['pppm_items' => ['id', 'public_id', 'owner_user_id', 'recipient_user_id', 'status']])) {
        $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM pppm_items WHERE status IN ('sent','delivered','viewed','claim_pending','verified','redeemed') AND owner_user_id IS NULL AND recipient_user_id IS NULL");
        $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT id,public_id,status,owner_user_id,recipient_user_id,updated_at FROM pppm_items WHERE status IN ('sent','delivered','viewed','claim_pending','verified','redeemed') AND owner_user_id IS NULL AND recipient_user_id IS NULL ORDER BY updated_at DESC,id DESC LIMIT 8") : [];
        $checks[] = mg_data_integrity_check('pppm_active_items_without_owner', 'Active PPPM items without owner or recipient', 'critical', true, $count, 'Active PPPM lifecycle records should not be detached from both owner and recipient.', $sample);
    }

    return $checks;
}

function mg_data_integrity_finance_checks(PDO $pdo): array
{
    $checks = [];

    if (!mg_data_integrity_table_exists($pdo, 'finance_ledger_entries')) {
        return [mg_data_integrity_unavailable('finance_unbalanced_ledger_groups', 'Unbalanced ledger entry groups', ['finance_ledger_entries'])];
    }

    $columns = ['entry_group_id', 'direction', 'amount_cents'];
    foreach ($columns as $column) {
        if (!mg_data_integrity_column_exists($pdo, 'finance_ledger_entries', $column)) {
            return [mg_data_integrity_unavailable('finance_unbalanced_ledger_groups', 'Unbalanced ledger entry groups', ['finance_ledger_entries.' . $column])];
        }
    }

    $count = mg_data_integrity_count($pdo, "SELECT COUNT(*) FROM (SELECT entry_group_id, SUM(CASE WHEN direction = 'debit' THEN amount_cents ELSE -amount_cents END) balance_cents FROM finance_ledger_entries GROUP BY entry_group_id HAVING balance_cents <> 0) x");
    $sample = $count > 0 ? mg_data_integrity_sample($pdo, "SELECT entry_group_id, SUM(CASE WHEN direction = 'debit' THEN amount_cents ELSE -amount_cents END) balance_cents, COUNT(*) entries FROM finance_ledger_entries GROUP BY entry_group_id HAVING balance_cents <> 0 ORDER BY MAX(id) DESC LIMIT 8") : [];
    $checks[] = mg_data_integrity_check('finance_unbalanced_ledger_groups', 'Unbalanced ledger entry groups', 'critical', true, $count, 'Ledger entry groups must balance debits and credits to zero.', $sample);

    return $checks;
}

function mg_data_integrity_run(PDO $pdo): array
{
    $groups = [
        'blog' => ['label' => 'Blog content', 'checks' => mg_data_integrity_blog_checks($pdo)],
        'media' => ['label' => 'Media assets', 'checks' => mg_data_integrity_media_checks($pdo)],
        'pppm' => ['label' => 'PPPM ownership', 'checks' => mg_data_integrity_pppm_checks($pdo)],
        'finance' => ['label' => 'Finance ledger', 'checks' => mg_data_integrity_finance_checks($pdo)],
    ];

    $flat = [];
    foreach ($groups as $key => &$group) {
        $group['key'] = $key;
        $critical = 0;
        $warning = 0;
        $notAvailable = 0;
        foreach ($group['checks'] as $check) {
            $flat[] = ['group' => $key] + $check;
            if (($check['status'] ?? '') === 'critical') $critical++;
            if (($check['status'] ?? '') === 'warning') $warning++;
            if (($check['status'] ?? '') === 'not_available') $notAvailable++;
        }
        $group['status'] = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');
        $group['counts'] = [
            'checks' => count($group['checks']),
            'critical' => $critical,
            'warning' => $warning,
            'not_available' => $notAvailable,
        ];
    }
    unset($group);

    $critical = count(array_filter($flat, static fn(array $item): bool => ($item['status'] ?? '') === 'critical'));
    $warning = count(array_filter($flat, static fn(array $item): bool => ($item['status'] ?? '') === 'warning'));
    $notAvailable = count(array_filter($flat, static fn(array $item): bool => ($item['status'] ?? '') === 'not_available'));
    $status = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');

    return [
        'status' => $status,
        'summary' => $status === 'healthy'
            ? 'No data integrity drift was detected in the current read-only checks.'
            : ($critical > 0 ? $critical . ' critical data integrity issue(s) need review.' : $warning . ' data integrity warning(s) need review.'),
        'generated_at' => gmdate('c'),
        'groups' => array_values($groups),
        'checks' => $flat,
        'counts' => [
            'groups' => count($groups),
            'checks' => count($flat),
            'critical' => $critical,
            'warning' => $warning,
            'not_available' => $notAvailable,
        ],
        'read_only' => true,
        'catalog_version' => '2026-07-05.data-integrity-v1',
    ];
}

try {
    mg_rate_limit('admin.data_integrity_diagnostics.read', 'user:' . (int)$user['id'], 60, 60);
    $pdo = mg_db();
    $data = mg_data_integrity_run($pdo);
    mg_security_log('info', 'admin.data_integrity_diagnostics.viewed', 'Data integrity diagnostics viewed.', [
        'status' => $data['status'],
        'critical' => $data['counts']['critical'] ?? 0,
        'warning' => $data['counts']['warning'] ?? 0,
        'catalog_version' => $data['catalog_version'] ?? null,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.data_integrity_diagnostics.failed', 'Data integrity diagnostics request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run data integrity diagnostics.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'Data integrity diagnostics loaded.');
