<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/app.php';

mg_require_method('POST');
$actor = mg_require_api_user();
$actorId = (int)($actor['id'] ?? 0);
$roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
$permissions = is_array($actor['permissions'] ?? null) ? $actor['permissions'] : [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$canInvestmentTests = $isSuperAdmin || in_array('admin.health.view', $permissions, true) || in_array('demand.dashboard.view', $permissions, true) || in_array('intelligence.dashboard.view', $permissions, true);
if (!$canInvestmentTests) mg_fail('Permission denied.', 403);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? '')));
$pdo = mg_db();

function mg_it_table(PDO $pdo, string $table): bool
{
    if (!in_array($table, ['public_profiles','merchant_market_snapshots'], true)) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
        catch (Throwable) { return false; }
    }
}
function mg_it_slug(?string $value): string
{
    $slug = strtolower(trim((string)$value));
    if ($slug === '' || strlen($slug) > 140 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,138}[a-z0-9])?$/', $slug) !== 1) mg_fail('Invalid merchant slug.', 422);
    return $slug;
}
function mg_it_cents(array $market, string $key): int
{
    $v = $market[$key] ?? 0;
    return is_numeric($v) ? (int)$v : 0;
}
function mg_it_metric_raw(array $payload, string $key): int
{
    $v = $payload['metrics'][$key]['raw'] ?? 0;
    return is_numeric($v) ? (int)$v : 0;
}
function mg_it_money(int $cents): string
{
    return '$' . number_format(max(0, $cents) / 100, $cents > 0 && $cents < 10000 ? 2 : 0);
}
function mg_it_profile(PDO $pdo, string $slug): array
{
    $stmt = $pdo->prepare("SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE slug=? LIMIT 1");
    $stmt->execute([$slug]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$profile) mg_fail('Merchant profile not found.', 404);
    return $profile;
}
function mg_it_save_snapshot(PDO $pdo, array $profile, array $payload, string $snapshotDate): array
{
    if (!mg_it_table($pdo, 'merchant_market_snapshots')) mg_fail('Snapshot table is missing. Apply database/stage_19_merchant_market_snapshots.sql first.', 500);
    if (!isset($payload['merchant_market']) || !is_array($payload['merchant_market'])) mg_fail('Investment payload is missing merchant_market.', 422);
    $market = $payload['merchant_market'];
    $snapshotJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $tickerValue = mg_it_cents($market, 'ticker_value_cents');
    $row = [
        (int)$profile['user_id'],
        (int)$profile['id'],
        (string)$profile['slug'],
        $snapshotDate,
        (string)($market['formula_version'] ?? 'unknown'),
        (string)($market['ticker_symbol'] ?? 'MGFT'),
        (int)($market['merchant_score'] ?? 0),
        $tickerValue,
        mg_it_metric_raw($payload, 'demand_value'),
        mg_it_cents($market, 'campaign_conversion_value_cents'),
        (float)($market['campaign_funnel_quality'] ?? 0),
        mg_it_cents($market, 'funnel_quality_value_cents'),
        mg_it_cents($market, 'distribution_value_cents'),
        mg_it_cents($market, 'stamp_inventory_value_cents'),
        mg_it_cents($market, 'stamp_spend_value_cents'),
        mg_it_cents($market, 'follower_brand_value_cents'),
        mg_it_cents($market, 'risk_adjustment_value_cents'),
        $snapshotJson,
    ];
    $stmt = $pdo->prepare(
        "INSERT INTO merchant_market_snapshots
         (public_id,merchant_user_id,public_profile_id,profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,created_at,updated_at)
         VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE public_profile_id=VALUES(public_profile_id),profile_slug=VALUES(profile_slug),ticker_symbol=VALUES(ticker_symbol),merchant_score=VALUES(merchant_score),ticker_value_cents=VALUES(ticker_value_cents),demand_value_cents=VALUES(demand_value_cents),campaign_conversion_value_cents=VALUES(campaign_conversion_value_cents),funnel_quality_score=VALUES(funnel_quality_score),funnel_quality_value_cents=VALUES(funnel_quality_value_cents),distribution_value_cents=VALUES(distribution_value_cents),stamp_inventory_value_cents=VALUES(stamp_inventory_value_cents),stamp_spend_value_cents=VALUES(stamp_spend_value_cents),follower_brand_value_cents=VALUES(follower_brand_value_cents),risk_adjustment_cents=VALUES(risk_adjustment_cents),snapshot_json=VALUES(snapshot_json),updated_at=NOW()"
    );
    $stmt->execute($row);
    return ['slug'=>(string)$profile['slug'],'date'=>$snapshotDate,'formula_version'=>$row[4],'merchant_score'=>$row[6],'ticker_value'=>mg_it_money($tickerValue),'ticker_value_cents'=>$tickerValue];
}

try {
    $result = match ($action) {
        'list_profiles' => (function () use ($pdo, $input): array {
            $limit = max(1, min(200, (int)($input['limit'] ?? 25)));
            $filter = strtolower(trim((string)($input['filter'] ?? '')));
            $params = [];
            $sql = "SELECT slug,display_name,status,visibility FROM public_profiles WHERE status='active' AND visibility IN ('public','unlisted')";
            if ($filter !== '') { $sql .= " AND slug LIKE ?"; $params[] = '%' . str_replace(['%','_'], ['\\%','\\_'], $filter) . '%'; }
            $sql .= " ORDER BY updated_at DESC,id DESC LIMIT " . $limit;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            return ['profiles'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
        })(),
        'recent_snapshots' => (function () use ($pdo): array {
            if (!mg_it_table($pdo, 'merchant_market_snapshots')) return ['snapshots'=>[], 'missing_table'=>true];
            $stmt = $pdo->query("SELECT profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,risk_adjustment_cents,updated_at FROM merchant_market_snapshots ORDER BY snapshot_date DESC,updated_at DESC LIMIT 15");
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[] = ['slug'=>(string)$row['profile_slug'],'date'=>(string)$row['snapshot_date'],'formula_version'=>(string)$row['formula_version'],'ticker_symbol'=>(string)$row['ticker_symbol'],'merchant_score'=>(int)$row['merchant_score'],'ticker_value'=>mg_it_money((int)$row['ticker_value_cents']),'ticker_value_cents'=>(int)$row['ticker_value_cents'],'risk_adjustment'=>mg_it_money((int)$row['risk_adjustment_cents']),'updated_at'=>(string)$row['updated_at']];
            }
            return ['snapshots'=>$rows, 'missing_table'=>false];
        })(),
        'save_snapshot' => (function () use ($pdo, $input): array {
            $slug = mg_it_slug((string)($input['slug'] ?? ''));
            $date = (string)($input['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) mg_fail('Invalid snapshot date.', 422);
            $payload = $input['payload'] ?? null;
            if (!is_array($payload)) mg_fail('Missing investment payload.', 422);
            return ['snapshot'=>mg_it_save_snapshot($pdo, mg_it_profile($pdo, $slug), $payload, $date)];
        })(),
        default => throw new InvalidArgumentException('Unsupported investment test action.'),
    };
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_fail('Unable to complete investment test action.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($result, 'Investment test action completed.');
