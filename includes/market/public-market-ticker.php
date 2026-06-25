<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-market-engine.php';

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
        ['symbol'=>'MGFT','name'=>'Local Market','price'=>'Opening soon','change'=>'● SOON','trend'=>'flat','href'=>'/discover.php','stats'=>[['label'=>'MARKET','value'=>'BETA']],'is_fallback'=>true],
        ['symbol'=>'MERCH','name'=>'Merchant indexes','price'=>'Building','change'=>'● SNAPSHOTS','trend'=>'flat','href'=>'/discover.php','stats'=>[['label'=>'DATA','value'=>'LOADING']],'is_fallback'=>true],
        ['symbol'=>'DROP','name'=>'Reward drops','price'=>'Coming online','change'=>'● DISCOVER','trend'=>'flat','href'=>'/discover.php','stats'=>[['label'=>'REWARDS','value'=>'SOON']],'is_fallback'=>true],
    ];
}

function mg_public_market_ticker_profile_href(string $slug): string
{
    return '/profile.php?slug=' . rawurlencode($slug);
}

function mg_public_market_ticker_compact_money(int $cents): string
{
    $amount = abs($cents) / 100;
    $prefix = $cents < 0 ? '-$' : '$';
    if ($amount >= 1000000) return $prefix . number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return $prefix . number_format($amount / 1000, 1) . 'K';
    return $prefix . number_format($amount, $amount >= 100 ? 0 : 2);
}

function mg_public_market_ticker_change(int $currentCents, ?int $previousCents, ?float $fallbackPercent = null): array
{
    if ($previousCents !== null && $previousCents > 0) {
        $delta = $currentCents - $previousCents;
        $percent = ($delta / $previousCents) * 100;
        if ($delta > 0) {
            return ['label'=>'▲ ' . number_format(abs($percent), 1) . '%','trend'=>'up','delta_cents'=>$delta,'delta_percent'=>round($percent, 2),'source'=>'ticker_history'];
        }
        if ($delta < 0) {
            return ['label'=>'▼ ' . number_format(abs($percent), 1) . '%','trend'=>'down','delta_cents'=>$delta,'delta_percent'=>round($percent, 2),'source'=>'ticker_history'];
        }
        return ['label'=>'• 0.0%','trend'=>'flat','delta_cents'=>0,'delta_percent'=>0.0,'source'=>'ticker_history'];
    }

    if ($fallbackPercent !== null && is_finite($fallbackPercent)) {
        if ($fallbackPercent > 0) return ['label'=>'▲ ' . number_format(abs($fallbackPercent), 1) . '% 30D','trend'=>'up','delta_cents'=>null,'delta_percent'=>round($fallbackPercent, 2),'source'=>'market_growth_30d'];
        if ($fallbackPercent < 0) return ['label'=>'▼ ' . number_format(abs($fallbackPercent), 1) . '% 30D','trend'=>'down','delta_cents'=>null,'delta_percent'=>round($fallbackPercent, 2),'source'=>'market_growth_30d'];
        return ['label'=>'• 0.0% 30D','trend'=>'flat','delta_cents'=>null,'delta_percent'=>0.0,'source'=>'market_growth_30d'];
    }

    return ['label'=>'●','trend'=>'flat','delta_cents'=>null,'delta_percent'=>null,'source'=>'live'];
}

function mg_public_market_ticker_metric(array $metrics, string $key): array
{
    return is_array($metrics[$key] ?? null) ? $metrics[$key] : [];
}

function mg_public_market_ticker_stats(array $payload): array
{
    $market = is_array($payload['merchant_market'] ?? null) ? $payload['merchant_market'] : [];
    $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
    $stats = [];

    $stats[] = ['label'=>'SCORE','value'=>(string)(int)($market['merchant_score'] ?? 0)];

    $map = [
        'active_drops' => 'DROPS',
        'posts_total' => 'POSTS',
        'post_interactions' => 'ACTIVITY',
        'engagement_rate' => 'ENG',
        'demand_value' => 'DEMAND',
        'volume_30d' => 'VOL 30D',
        'campaign_conversions' => 'CONV',
        'campaign_funnel_quality' => 'FUNNEL',
    ];

    foreach ($map as $key => $label) {
        $metric = mg_public_market_ticker_metric($metrics, $key);
        if ($metric === []) continue;
        $display = trim((string)($metric['display'] ?? ''));
        $raw = $metric['raw'] ?? null;
        $hasData = !empty($metric['has_data']);
        if (!$hasData && (!is_numeric($raw) || (float)$raw === 0.0)) continue;
        if ($display === '' || in_array(strtolower($display), ['no trend','no data','no issue data'], true)) continue;
        $stats[] = ['label'=>$label,'value'=>$display];
    }

    return array_slice($stats, 0, 8);
}

function mg_public_market_ticker_previous_value(PDO $pdo, int $merchantUserId): ?int
{
    if (!mg_public_market_ticker_table($pdo, 'merchant_market_snapshots')) return null;
    try {
        $stmt = $pdo->prepare('SELECT ticker_value_cents FROM merchant_market_snapshots WHERE merchant_user_id=? AND snapshot_date < CURDATE() ORDER BY snapshot_date DESC,id DESC LIMIT 1');
        $stmt->execute([$merchantUserId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    } catch (Throwable) {
        return null;
    }
}

function mg_public_market_ticker_profile_rows(PDO $pdo, int $limit): array
{
    $signals = ["pp.profile_type='merchant'"];
    if (mg_public_market_ticker_table($pdo, 'merchant_storefronts')) {
        $signals[] = "EXISTS(SELECT 1 FROM merchant_storefronts ms WHERE ms.merchant_user_id=pp.user_id AND ms.status='published')";
    }
    if (mg_public_market_ticker_table($pdo, 'catalog_products') && mg_public_market_ticker_table($pdo, 'catalog_product_versions')) {
        $signals[] = "EXISTS(SELECT 1 FROM catalog_products cp INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id WHERE cp.merchant_user_id=pp.user_id AND cp.status='published' AND cpv.version_status='published')";
    }

    $sql = "SELECT pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.location_label
            FROM public_profiles pp
            INNER JOIN users u ON u.id=pp.user_id
            WHERE u.status='active'
              AND pp.status='active'
              AND pp.visibility IN ('public','unlisted')
              AND (" . implode(' OR ', $signals) . ")
            ORDER BY pp.updated_at DESC,pp.display_name ASC
            LIMIT {$limit}";
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_public_market_ticker_items(PDO $pdo, int $limit = 12, bool $fallback = false): array
{
    if (!mg_public_market_ticker_table($pdo, 'public_profiles') || !mg_public_market_ticker_table($pdo, 'users')) {
        return $fallback ? mg_public_market_ticker_fallback_items() : [];
    }

    $limit = max(1, min(12, $limit));
    $rows = mg_public_market_ticker_profile_rows($pdo, $limit);
    $items = [];

    foreach ($rows as $row) {
        $slug = trim((string)($row['slug'] ?? ''));
        $merchantUserId = (int)($row['user_id'] ?? 0);
        if ($slug === '' || $merchantUserId < 1) continue;

        try {
            $payload = mg_merchant_market_build($pdo, $slug);
        } catch (Throwable) {
            continue;
        }

        $market = is_array($payload['merchant_market'] ?? null) ? $payload['merchant_market'] : [];
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        if (empty($market['has_data'])) continue;

        $currentCents = (int)($market['ticker_value_cents'] ?? 0);
        $symbol = trim((string)($market['ticker_symbol'] ?? 'MGFT'));
        $name = trim((string)($payload['profile']['display_name'] ?? $row['display_name'] ?? $slug));
        $growthMetric = mg_public_market_ticker_metric($metrics, 'market_growth_30d');
        $growthRaw = $growthMetric['raw'] ?? null;
        $fallbackPercent = is_numeric($growthRaw) ? (float)$growthRaw : null;
        $change = mg_public_market_ticker_change($currentCents, mg_public_market_ticker_previous_value($pdo, $merchantUserId), $fallbackPercent);
        $stats = mg_public_market_ticker_stats($payload);
        $profileHref = mg_public_market_ticker_profile_href($slug);

        $items[] = [
            'symbol' => $symbol !== '' ? $symbol : 'MGFT',
            'name' => $name !== '' ? $name : $slug,
            'price' => mg_public_market_ticker_compact_money($currentCents),
            'change' => $change['label'],
            'trend' => $change['trend'],
            'trend_source' => $change['source'],
            'stat' => $stats[0] ?? null,
            'stats' => $stats,
            'href' => $profileHref,
            'profile_url' => $profileHref,
            'profile_slug' => $slug,
            'avatar_url' => $row['avatar_url'] !== null ? (string)$row['avatar_url'] : null,
            'location_label' => $row['location_label'] !== null ? (string)$row['location_label'] : null,
            'merchant_user_id' => $merchantUserId,
            'ticker_value_cents' => $currentCents,
            'merchant_score' => (int)($market['merchant_score'] ?? 0),
            'delta_cents' => $change['delta_cents'],
            'delta_percent' => $change['delta_percent'],
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
