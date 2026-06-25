<?php
declare(strict_types=1);

$itPdo = mg_db();
$itMessage = null;
$itError = null;
$itModel = null;
$itSeries = [];
$itBatchLog = [];

function mg_it_page_table(PDO $pdo, string $table): bool
{
    if (!in_array($table, ['public_profiles', 'merchant_market_snapshots'], true)) return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
        catch (Throwable) { return false; }
    }
}
function mg_it_page_slug(string $value): string
{
    $slug = strtolower(trim($value));
    if ($slug === '' || strlen($slug) > 140 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,138}[a-z0-9])?$/', $slug) !== 1) {
        throw new InvalidArgumentException('Enter a valid merchant slug.');
    }
    return $slug;
}
function mg_it_page_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}
function mg_it_page_fetch_model(string $slug): array
{
    $url = mg_it_page_base_url() . '/api/public/profile-investment.php?slug=' . rawurlencode($slug) . '&snapshot=1';
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 25, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: MicrogifterInvestmentTests/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false || trim((string)$body) === '') throw new RuntimeException('Unable to load the live investment model.');
    $json = json_decode((string)$body, true);
    if (!is_array($json)) throw new RuntimeException('Investment model response was not valid JSON.');
    $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : $json;
    if (!isset($data['merchant_market']) || !is_array($data['merchant_market'])) throw new RuntimeException('Investment model response did not include merchant_market.');
    return $data;
}
function mg_it_page_money(int $cents): string
{
    return '$' . number_format(max(0, $cents) / 100, $cents > 0 && $cents < 10000 ? 2 : 0);
}
function mg_it_page_market_cents(array $market, string $key): int
{
    $value = $market[$key] ?? 0;
    return is_numeric($value) ? (int)$value : 0;
}
function mg_it_page_metric_raw(array $payload, string $key): int
{
    $value = $payload['metrics'][$key]['raw'] ?? 0;
    return is_numeric($value) ? (int)$value : 0;
}
function mg_it_page_profile(PDO $pdo, string $slug): array
{
    $stmt = $pdo->prepare('SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$profile) throw new RuntimeException('Merchant profile was not found.');
    return $profile;
}
function mg_it_page_save_snapshot(PDO $pdo, array $profile, array $payload, string $snapshotDate): array
{
    if (!mg_it_page_table($pdo, 'merchant_market_snapshots')) throw new RuntimeException('Snapshot table is missing. Apply the Stage 19 migration first.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) throw new InvalidArgumentException('Use a valid snapshot date.');
    $market = $payload['merchant_market'] ?? [];
    if (!is_array($market)) throw new RuntimeException('Investment model missing merchant_market.');
    $snapshotJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $tickerValue = mg_it_page_market_cents($market, 'ticker_value_cents');
    $row = [
        (int)$profile['user_id'],
        (int)$profile['id'],
        (string)$profile['slug'],
        $snapshotDate,
        (string)($market['formula_version'] ?? 'unknown'),
        (string)($market['ticker_symbol'] ?? 'MGFT'),
        (int)($market['merchant_score'] ?? 0),
        $tickerValue,
        mg_it_page_metric_raw($payload, 'demand_value'),
        mg_it_page_market_cents($market, 'campaign_conversion_value_cents'),
        (float)($market['campaign_funnel_quality'] ?? 0),
        mg_it_page_market_cents($market, 'funnel_quality_value_cents'),
        mg_it_page_market_cents($market, 'distribution_value_cents'),
        mg_it_page_market_cents($market, 'stamp_inventory_value_cents'),
        mg_it_page_market_cents($market, 'stamp_spend_value_cents'),
        mg_it_page_market_cents($market, 'follower_brand_value_cents'),
        mg_it_page_market_cents($market, 'risk_adjustment_value_cents'),
        $snapshotJson,
    ];
    $stmt = $pdo->prepare("INSERT INTO merchant_market_snapshots (public_id,merchant_user_id,public_profile_id,profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,created_at,updated_at) VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE public_profile_id=VALUES(public_profile_id),profile_slug=VALUES(profile_slug),ticker_symbol=VALUES(ticker_symbol),merchant_score=VALUES(merchant_score),ticker_value_cents=VALUES(ticker_value_cents),demand_value_cents=VALUES(demand_value_cents),campaign_conversion_value_cents=VALUES(campaign_conversion_value_cents),funnel_quality_score=VALUES(funnel_quality_score),funnel_quality_value_cents=VALUES(funnel_quality_value_cents),distribution_value_cents=VALUES(distribution_value_cents),stamp_inventory_value_cents=VALUES(stamp_inventory_value_cents),stamp_spend_value_cents=VALUES(stamp_spend_value_cents),follower_brand_value_cents=VALUES(follower_brand_value_cents),risk_adjustment_cents=VALUES(risk_adjustment_cents),snapshot_json=VALUES(snapshot_json),updated_at=NOW()");
    $stmt->execute($row);
    return ['slug'=>(string)$profile['slug'],'date'=>$snapshotDate,'formula_version'=>$row[4],'merchant_score'=>$row[6],'ticker_value'=>mg_it_page_money($tickerValue),'ticker_value_cents'=>$tickerValue];
}
function mg_it_page_recent(PDO $pdo): array
{
    if (!mg_it_page_table($pdo, 'merchant_market_snapshots')) return [];
    $stmt = $pdo->query('SELECT profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,risk_adjustment_cents,updated_at FROM merchant_market_snapshots ORDER BY snapshot_date DESC,updated_at DESC LIMIT 15');
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function mg_it_page_series(PDO $pdo, string $slug): array
{
    if (!mg_it_page_table($pdo, 'merchant_market_snapshots')) return [];
    $stmt = $pdo->prepare('SELECT snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,risk_adjustment_cents FROM merchant_market_snapshots WHERE profile_slug=? ORDER BY snapshot_date DESC LIMIT 30');
    $stmt->execute([$slug]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!mg_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Security token expired. Refresh the page and try again.');
        $action = (string)($_POST['investment_action'] ?? '');
        $date = (string)($_POST['snapshot_date'] ?? date('Y-m-d'));
        if ($action === 'load_model' || $action === 'save_snapshot' || $action === 'load_series') {
            $slug = mg_it_page_slug((string)($_POST['slug'] ?? ''));
            if ($action === 'load_series') {
                $itSeries = mg_it_page_series($itPdo, $slug);
                $itMessage = 'Loaded snapshot series for ' . $slug . '.';
            } else {
                $itModel = mg_it_page_fetch_model($slug);
                if ($action === 'save_snapshot') {
                    $saved = mg_it_page_save_snapshot($itPdo, mg_it_page_profile($itPdo, $slug), $itModel, $date);
                    $itMessage = 'Saved snapshot for ' . $saved['slug'] . ' · ' . $saved['date'] . ' · ' . $saved['ticker_value'] . '.';
                    $itSeries = mg_it_page_series($itPdo, $slug);
                } else {
                    $itMessage = 'Loaded live investment model for ' . $slug . '.';
                }
            }
        } elseif ($action === 'run_all') {
            if (!mg_it_page_table($itPdo, 'merchant_market_snapshots')) throw new RuntimeException('Snapshot table is missing. Apply the Stage 19 migration first.');
            $limit = max(1, min(200, (int)($_POST['limit'] ?? 25)));
            $filter = strtolower(trim((string)($_POST['filter'] ?? '')));
            $params = [];
            $sql = "SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE status='active' AND visibility IN ('public','unlisted')";
            if ($filter !== '') { $sql .= ' AND slug LIKE ?'; $params[] = '%' . str_replace(['%','_'], ['\\%','\\_'], $filter) . '%'; }
            $sql .= ' ORDER BY updated_at DESC,id DESC LIMIT ' . $limit;
            $stmt = $itPdo->prepare($sql); $stmt->execute($params);
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $savedCount = 0; $failedCount = 0;
            foreach ($profiles as $profile) {
                try {
                    $model = mg_it_page_fetch_model((string)$profile['slug']);
                    $saved = mg_it_page_save_snapshot($itPdo, $profile, $model, $date);
                    $savedCount++;
                    $itBatchLog[] = 'Saved ' . $saved['slug'] . ' · score ' . $saved['merchant_score'] . ' · ' . $saved['ticker_value'];
                } catch (Throwable $error) {
                    $failedCount++;
                    $itBatchLog[] = 'Failed ' . (string)($profile['slug'] ?? 'unknown') . ' · ' . $error->getMessage();
                }
            }
            $itMessage = 'Batch complete. Saved ' . $savedCount . '. Failed ' . $failedCount . '.';
        }
    } catch (Throwable $error) {
        $itError = $error->getMessage();
    }
}

$itRecent = mg_it_page_recent($itPdo);
$itTableReady = mg_it_page_table($itPdo, 'merchant_market_snapshots');
$itSlugValue = (string)($_POST['slug'] ?? '');
$itDateValue = (string)($_POST['snapshot_date'] ?? date('Y-m-d'));
?>
<section class="mg-app-panel mg-account-pane is-active mg-admin-dashboard mg-investment-tests" data-account-pane="investment_tests">
  <div class="mg-app-panel-head mg-section-head">
    <div>
      <h2>Investment Tests</h2>
      <p>Browser controls for merchant market formulas, ticker snapshots, and profile investment charts. No command line needed.</p>
    </div>
    <div class="mg-admin-toolbar"><a class="mg-btn mg-btn-ghost" href="/account-investment-tests.php">Refresh</a></div>
  </div>

  <div class="mg-app-panel-body">
    <?php if ($itMessage): ?><div class="mg-admin-state is-success"><strong>Done</strong><span><?= mg_e($itMessage) ?></span></div><?php endif; ?>
    <?php if ($itError): ?><div class="mg-admin-state is-error"><strong>Action failed</strong><span><?= mg_e($itError) ?></span></div><?php endif; ?>
    <?php if (!$itTableReady): ?><div class="mg-admin-state is-error"><strong>Migration needed</strong><span>Apply database/stage_19_merchant_market_snapshots.sql before saving snapshots.</span></div><?php endif; ?>

    <div class="mg-admin-section-grid">
      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Single merchant test</h3><p>Load the live investment model, save today’s snapshot, or inspect stored series rows.</p></div></header>
        <div class="mg-admin-section-body">
          <form class="mg-investment-test-form" method="post">
            <?= mg_csrf_field() ?>
            <label><span>Merchant slug</span><input type="text" name="slug" value="<?= mg_e($itSlugValue) ?>" placeholder="merchant-slug" autocomplete="off"></label>
            <label><span>Snapshot date</span><input type="date" name="snapshot_date" value="<?= mg_e($itDateValue) ?>"></label>
            <div class="mg-action-row">
              <button class="mg-btn mg-btn-primary" type="submit" name="investment_action" value="load_model">Load model</button>
              <button class="mg-btn mg-btn-soft" type="submit" name="investment_action" value="save_snapshot">Save snapshot</button>
              <button class="mg-btn mg-btn-ghost" type="submit" name="investment_action" value="load_series">Load series</button>
            </div>
          </form>
          <div class="mg-investment-result-grid">
            <?php if ($itModel): $market = $itModel['merchant_market'] ?? []; $conv = $itModel['campaign_conversions'] ?? []; $risk = $itModel['risk'] ?? []; ?>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($market['formula_version'] ?? 'unknown')) ?></strong><span>Formula</span><small>Current market model version</small></div>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($market['ticker_value'] ?? '$0')) ?></strong><span>Ticker Value</span><small><?= mg_e((string)($market['ticker_symbol'] ?? 'MGFT')) ?></small></div>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($market['merchant_score'] ?? '0')) ?></strong><span>Merchant Score</span><small><?= mg_e((string)($market['rating'] ?? 'No rating')) ?></small></div>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($market['campaign_funnel_quality'] ?? '0')) ?></strong><span>Funnel Quality</span><small>Campaign funnel quality score</small></div>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($conv['total'] ?? '0')) ?></strong><span>Campaign Conversions</span><small>Contacts, events, issues, claims, and redemptions</small></div>
              <div class="mg-investment-test-card"><strong><?= mg_e((string)($market['risk_adjustment_value'] ?? '$0')) ?></strong><span>Risk Adjustment</span><small>Risk score <?= mg_e((string)($risk['score_adjustment'] ?? $market['risk_adjustment'] ?? '0')) ?></small></div>
            <?php else: ?>
              <p class="mg-muted">No merchant loaded yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Run active merchant snapshots</h3><p>Runs from this page. It loads each active merchant’s investment model and writes one snapshot row.</p></div></header>
        <div class="mg-admin-section-body">
          <form class="mg-investment-test-form" method="post">
            <?= mg_csrf_field() ?>
            <input type="hidden" name="investment_action" value="run_all">
            <label><span>Snapshot date</span><input type="date" name="snapshot_date" value="<?= mg_e($itDateValue) ?>"></label>
            <label><span>Limit</span><input type="number" name="limit" min="1" max="200" value="<?= mg_e((string)($_POST['limit'] ?? '25')) ?>"></label>
            <label><span>Filter slug contains</span><input type="text" name="filter" value="<?= mg_e((string)($_POST['filter'] ?? '')) ?>" placeholder="optional"></label>
            <button class="mg-btn mg-btn-primary" type="submit">Run snapshots</button>
          </form>
          <div class="mg-investment-log">
            <?php if ($itBatchLog): ?><?php foreach ($itBatchLog as $line): ?><p><?= mg_e($line) ?></p><?php endforeach; ?><?php else: ?><p class="mg-muted">Batch results will appear here after you run snapshots.</p><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3><?= $itSeries ? 'Loaded snapshot series' : 'Recent market snapshots' ?></h3><p><?= $itSeries ? 'Stored ticker snapshots for the selected merchant.' : 'Most recent stored ticker snapshots from the merchant market snapshot table.' ?></p></div></header>
        <div class="mg-admin-section-body mg-investment-result-grid">
          <?php $rowsToShow = $itSeries ?: $itRecent; ?>
          <?php if ($rowsToShow): ?>
            <?php foreach ($rowsToShow as $row): ?>
              <div class="mg-investment-test-card"><strong><?= mg_e(mg_it_page_money((int)($row['ticker_value_cents'] ?? 0))) ?></strong><span><?= mg_e((string)($row['profile_slug'] ?? $itSlugValue)) ?> · <?= mg_e((string)($row['snapshot_date'] ?? '')) ?></span><small>Score <?= mg_e((string)($row['merchant_score'] ?? '0')) ?> · <?= mg_e((string)($row['formula_version'] ?? 'unknown')) ?></small></div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="mg-muted">No snapshots have been stored yet.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
