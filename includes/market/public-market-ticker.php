<?php
declare(strict_types=1);

function mg_public_market_ticker_table(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_public_market_ticker_fallback_items(): array
{
    return [
        ['symbol'=>'MGFT','name'=>'Local Market','price'=>'Opening soon','change'=>'LIVE SOON','trend'=>'up','href'=>'/discover.php','is_fallback'=>true],
        ['symbol'=>'MERCH','name'=>'Merchant indexes','price'=>'Building','change'=>'SNAPSHOTS','trend'=>'up','href'=>'/merchant.php','is_fallback'=>true],
        ['symbol'=>'DROP','name'=>'Reward drops','price'=>'Coming online','change'=>'DISCOVER','trend'=>'up','href'=>'/discover.php','is_fallback'=>true],
        ['symbol'=>'SCORE','name'=>'Market scores','price'=>'Calculating','change'=>'BETA','trend'=>'up','href'=>'/learn-more.php','is_fallback'=>true],
    ];
}

function mg_public_market_ticker_profile_href(string $slug): string
{
    return '/profile.php?slug=' . rawurlencode($slug);
}

function mg_public_market_ticker_symbol(?string $symbol, string $name, string $slug): string
{
    $clean = strtoupper((string)preg_replace('/[^a-z0-9]/i', '', (string)$symbol));
    if ($clean !== '') return substr($clean, 0, 8);

    $initials = '';
    $words = preg_split('/[^a-z0-9]+/i', trim($name)) ?: [];
    foreach ($words as $word) {
        if ($word === '') continue;
        $initials .= strtoupper($word[0]);
        if (strlen($initials) >= 5) break;
    }
    if (strlen($initials) >= 2) return $initials;

    $slugSymbol = strtoupper((string)preg_replace('/[^a-z0-9]/i', '', $slug));
    return $slugSymbol !== '' ? substr($slugSymbol, 0, 5) : 'MGFT';
}

function mg_public_market_ticker_money(int $cents): string
{
    if ($cents === 0) return '$0.00';
    $prefix = $cents < 0 ? '-$' : '$';
    return $prefix . number_format(abs($cents) / 100, abs($cents) >= 10000 ? 0 : 2);
}

function mg_public_market_ticker_change(int $currentCents, ?int $previousCents): array
{
    if ($previousCents === null || $previousCents === 0) {
        return ['label' => 'LIVE', 'trend' => 'up', 'delta_cents' => null, 'delta_percent' => null];
    }
    $delta = $currentCents - $previousCents;
    $percent = ($delta / max(1, abs($previousCents))) * 100;
    $trend = $delta < 0 ? 'down' : 'up';
    return [
        'label' => ($delta < 0 ? '▼ ' : '▲ ') . number_format(abs($percent), 1) . '%',
        'trend' => $trend,
        'delta_cents' => $delta,
        'delta_percent' => round($percent, 2),
    ];
}

function mg_public_market_ticker_items(PDO $pdo, int $limit = 12, bool $fallback = false): array
{
    if (!mg_public_market_ticker_table($pdo, 'public_profiles') || !mg_public_market_ticker_table($pdo, 'users')) {
        return $fallback ? mg_public_market_ticker_fallback_items() : [];
    }

    $limit = max(4, min(24, $limit));
    $hasSnapshots = mg_public_market_ticker_table($pdo, 'merchant_market_snapshots');
    $hasStorefronts = mg_public_market_ticker_table($pdo, 'merchant_storefronts');
    $hasProducts = mg_public_market_ticker_table($pdo, 'catalog_products');
    $hasProductVersions = mg_public_market_ticker_table($pdo, 'catalog_product_versions');

    $merchantSignals = ["pp.profile_type='merchant'"];
    if ($hasStorefronts) {
        $merchantSignals[] = "EXISTS(SELECT 1 FROM merchant_storefronts ms WHERE ms.merchant_user_id=pp.user_id AND ms.status='published')";
    }
    if ($hasProducts && $hasProductVersions) {
        $merchantSignals[] = "EXISTS(
            SELECT 1
            FROM catalog_products cp
            INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
            WHERE cp.merchant_user_id=pp.user_id
              AND cp.status='published'
              AND cpv.version_status='published'
        )";
    }
    if ($hasSnapshots) {
        $merchantSignals[] = 'mms.id IS NOT NULL';
    }

    $snapshotSelect = $hasSnapshots
        ? 'mms.merchant_user_id,mms.ticker_symbol,mms.ticker_value_cents,mms.merchant_score,mms.snapshot_date'
        : 'pp.user_id AS merchant_user_id,NULL AS ticker_symbol,NULL AS ticker_value_cents,0 AS merchant_score,NULL AS snapshot_date';

    $snapshotJoin = $hasSnapshots
        ? "LEFT JOIN merchant_market_snapshots mms ON mms.id = (
            SELECT latest.id
            FROM merchant_market_snapshots latest
            WHERE latest.merchant_user_id=pp.user_id
            ORDER BY latest.snapshot_date DESC,latest.id DESC
            LIMIT 1
          )"
        : '';

    $orderBy = $hasSnapshots
        ? 'ORDER BY (mms.id IS NOT NULL) DESC,mms.ticker_value_cents DESC,mms.merchant_score DESC,pp.completion_score DESC,pp.updated_at DESC,pp.display_name ASC'
        : 'ORDER BY pp.completion_score DESC,pp.updated_at DESC,pp.display_name ASC';

    $sql = "SELECT
        pp.user_id AS profile_user_id,
        pp.slug,
        pp.display_name AS profile_display_name,
        pp.profile_type,
        pp.avatar_url,
        pp.location_label,
        pp.completion_score,
        u.display_name AS user_display_name,
        u.full_name AS user_full_name,
        {$snapshotSelect}
      FROM public_profiles pp
      INNER JOIN users u ON u.id=pp.user_id
      {$snapshotJoin}
      WHERE u.status='active'
        AND pp.status='active'
        AND pp.visibility IN ('public','unlisted')
        AND (" . implode(' OR ', $merchantSignals) . ")
      {$orderBy}
      LIMIT {$limit}";

    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    if (!$rows) return $fallback ? mg_public_market_ticker_fallback_items() : [];

    $previousStmt = $hasSnapshots
        ? $pdo->prepare('SELECT ticker_value_cents FROM merchant_market_snapshots WHERE merchant_user_id=? AND snapshot_date < ? ORDER BY snapshot_date DESC,id DESC LIMIT 1')
        : null;

    $items = [];
    foreach ($rows as $row) {
        $merchantId = (int)($row['merchant_user_id'] ?? $row['profile_user_id'] ?? 0);
        $slug = trim((string)($row['slug'] ?? ''));
        if ($merchantId < 1 || $slug === '') continue;

        $name = trim((string)($row['profile_display_name'] ?? ''));
        if ($name === '') $name = trim((string)($row['user_display_name'] ?? ''));
        if ($name === '') $name = trim((string)($row['user_full_name'] ?? ''));
        if ($name === '') $name = $slug;

        $snapshotDate = trim((string)($row['snapshot_date'] ?? ''));
        $hasSnapshot = $hasSnapshots && $snapshotDate !== '' && $row['ticker_value_cents'] !== null;
        $currentCents = $hasSnapshot ? (int)$row['ticker_value_cents'] : null;
        $change = ['label' => 'VIEW PROFILE', 'trend' => 'up', 'delta_cents' => null, 'delta_percent' => null];

        if ($hasSnapshot && $previousStmt instanceof PDOStatement) {
            $previousCents = null;
            $previousStmt->execute([$merchantId, $snapshotDate]);
            $prev = $previousStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev) $previousCents = (int)($prev['ticker_value_cents'] ?? 0);
            $change = mg_public_market_ticker_change((int)$currentCents, $previousCents);
        }

        $profileHref = mg_public_market_ticker_profile_href($slug);
        $items[] = [
            'symbol' => mg_public_market_ticker_symbol($row['ticker_symbol'] ?? null, $name, $slug),
            'name' => $name,
            'price' => $hasSnapshot ? mg_public_market_ticker_money((int)$currentCents) : 'Profile live',
            'change' => $change['label'],
            'trend' => $change['trend'],
            'href' => $profileHref,
            'profile_url' => $profileHref,
            'profile_slug' => $slug,
            'profile_type' => (string)($row['profile_type'] ?? 'merchant'),
            'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null,
            'location_label' => $row['location_label'] !== null ? (string)$row['location_label'] : null,
            'merchant_user_id' => $merchantId,
            'ticker_value_cents' => $currentCents,
            'merchant_score' => (int)($row['merchant_score'] ?? 0),
            'snapshot_date' => $hasSnapshot ? $snapshotDate : null,
            'delta_cents' => $change['delta_cents'],
            'delta_percent' => $change['delta_percent'],
            'is_profile_only' => !$hasSnapshot,
            'is_fallback' => false,
        ];
    }

    if (!$items) return $fallback ? mg_public_market_ticker_fallback_items() : [];
    return $items;
}

function mg_public_market_ticker_has_live_items(array $items): bool
{
    foreach ($items as $item) {
        if (empty($item['is_fallback'])) return true;
    }
    return false;
}
