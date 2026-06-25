<?php
declare(strict_types=1);

function mg_public_market_ticker_table(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
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
    if (!mg_public_market_ticker_table($pdo, 'merchant_market_snapshots')) return $fallback ? mg_public_market_ticker_fallback_items() : [];
    $limit = max(4, min(24, $limit));

    $sql = "SELECT pp.slug,pp.display_name,mms.merchant_user_id,mms.ticker_symbol,mms.ticker_value_cents,mms.merchant_score,mms.snapshot_date
      FROM merchant_market_snapshots mms
      INNER JOIN (
        SELECT merchant_user_id,MAX(snapshot_date) AS latest_snapshot_date
        FROM merchant_market_snapshots
        GROUP BY merchant_user_id
      ) latest ON latest.merchant_user_id=mms.merchant_user_id AND latest.latest_snapshot_date=mms.snapshot_date
      INNER JOIN public_profiles pp ON pp.user_id=mms.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
      ORDER BY mms.ticker_value_cents DESC,mms.merchant_score DESC,pp.display_name ASC
      LIMIT {$limit}";
    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    if (!$rows) return $fallback ? mg_public_market_ticker_fallback_items() : [];

    $previousStmt = $pdo->prepare("SELECT ticker_value_cents FROM merchant_market_snapshots WHERE merchant_user_id=? AND snapshot_date < ? ORDER BY snapshot_date DESC LIMIT 1");
    $items = [];
    foreach ($rows as $row) {
        $merchantId = (int)($row['merchant_user_id'] ?? 0);
        $snapshotDate = (string)($row['snapshot_date'] ?? '');
        $currentCents = (int)($row['ticker_value_cents'] ?? 0);
        $previousCents = null;
        if ($merchantId > 0 && $snapshotDate !== '') {
            $previousStmt->execute([$merchantId, $snapshotDate]);
            $prev = $previousStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev) $previousCents = (int)($prev['ticker_value_cents'] ?? 0);
        }
        $change = mg_public_market_ticker_change($currentCents, $previousCents);
        $items[] = [
            'symbol' => (string)($row['ticker_symbol'] ?: 'MGFT'),
            'name' => (string)($row['display_name'] ?: $row['slug']),
            'price' => mg_public_market_ticker_money($currentCents),
            'change' => $change['label'],
            'trend' => $change['trend'],
            'href' => '/profile.php?slug=' . rawurlencode((string)$row['slug']),
            'ticker_value_cents' => $currentCents,
            'merchant_score' => (int)($row['merchant_score'] ?? 0),
            'snapshot_date' => $snapshotDate,
            'delta_cents' => $change['delta_cents'],
            'delta_percent' => $change['delta_percent'],
            'is_fallback' => false,
        ];
    }
    return $items;
}

function mg_public_market_ticker_has_live_items(array $items): bool
{
    foreach ($items as $item) {
        if (empty($item['is_fallback'])) return true;
    }
    return false;
}
