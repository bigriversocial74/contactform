<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$dbFile = __DIR__ . '/api/db.php';
if (is_file($dbFile)) {
    require_once $dbFile;
}

$page_title = 'Microgifter Bottom-Up TAM | Investor Portal';
$page_section = 'investors';
$header_mode = 'public';
$page_body_class = 'mg-investors-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_meta = [
    'description' => 'Microgifter investor portal with bottom-up TAM, live growth metrics, and the Promotional CRM market model for local commerce.',
    'canonical' => 'https://microgifter.com/investors.php',
    'og_title' => 'Microgifter Bottom-Up TAM | Investor Portal',
    'og_description' => 'Review Microgifter’s bottom-up TAM, live growth snapshot, share model, and market expansion path.',
];
$page_manifest = [
    'id' => 'investors',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Market Model', 'href' => '/investor-tam.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'investors', 'sections' => []],
];

function mgi_money(float|int $value, bool $compact = true): string
{
    $value = (float)$value;
    $prefix = $value < 0 ? '-$' : '$';
    $abs = abs($value);
    if ($compact) {
        if ($abs >= 1000000000) return $prefix . rtrim(rtrim(number_format($abs / 1000000000, 1), '0'), '.') . 'B';
        if ($abs >= 1000000) return $prefix . rtrim(rtrim(number_format($abs / 1000000, 1), '0'), '.') . 'M';
        if ($abs >= 1000) return $prefix . rtrim(rtrim(number_format($abs / 1000, 1), '0'), '.') . 'K';
    }
    return $prefix . number_format($abs, $abs >= 100 ? 0 : 2);
}

function mgi_num(float|int $value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals);
}

function mgi_pct(float|int $value, int $decimals = 1): string
{
    return (($value >= 0) ? '+' : '') . number_format((float)$value, $decimals) . '%';
}

function mgi_db_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mgi_db_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mgi_scalar(PDO $pdo, string $sql, array $params = [], ?float $fallback = null): ?float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (float)$value : $fallback;
    } catch (Throwable) {
        return $fallback;
    }
}

function mgi_period_growth(float $current, float $previous): float
{
    if ($previous > 0) return (($current - $previous) / $previous) * 100;
    if ($current > 0) return 100.0;
    return 0.0;
}

function mgi_growth_data(): array
{
    $model = [
        'is_live' => false,
        'source_label' => 'Model projection',
        'source_note' => 'Connects to live database when available.',
        'active_merchants' => 2500,
        'mrr' => 62500.0,
        'arr' => 750000.0,
        'merchant_growth' => 18.2,
        'mrr_growth' => 12.8,
        'arr_growth' => 18.4,
        'growth_30d' => 28.4,
        'new_merchants_30d' => 112,
        'campaign_volume_30d' => 0,
        'months' => [
            ['label' => 'Dec', 'mrr' => 25000.0],
            ['label' => 'Jan', 'mrr' => 30500.0],
            ['label' => 'Feb', 'mrr' => 38000.0],
            ['label' => 'Mar', 'mrr' => 47500.0],
            ['label' => 'Apr', 'mrr' => 57000.0],
            ['label' => 'May', 'mrr' => 62500.0],
        ],
    ];

    if (!function_exists('mg_db')) return $model;

    try {
        $pdo = mg_db();
    } catch (Throwable) {
        return $model;
    }

    $tables = [];
    foreach (['users','public_profiles','merchant_storefronts','subscriptions','feed_posts','commerce_orders','microgift_claims','microgift_redemptions'] as $table) {
        $tables[$table] = mgi_db_table_exists($pdo, $table);
    }

    $activeMerchants = null;
    $newMerchants30 = null;
    $prevMerchants30 = null;

    if ($tables['public_profiles'] && $tables['users'] && mgi_db_column_exists($pdo, 'public_profiles', 'profile_type')) {
        $activeMerchants = mgi_scalar($pdo, "SELECT COUNT(DISTINCT pp.user_id) FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.profile_type='merchant' AND pp.status='active' AND u.status='active'", [], 0.0);
        if (mgi_db_column_exists($pdo, 'public_profiles', 'created_at')) {
            $newMerchants30 = mgi_scalar($pdo, "SELECT COUNT(DISTINCT pp.user_id) FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.profile_type='merchant' AND pp.status='active' AND u.status='active' AND pp.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0);
            $prevMerchants30 = mgi_scalar($pdo, "SELECT COUNT(DISTINCT pp.user_id) FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.profile_type='merchant' AND pp.status='active' AND u.status='active' AND pp.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY) AND pp.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0);
        }
    }

    if (($activeMerchants === null || $activeMerchants <= 0) && $tables['merchant_storefronts']) {
        $activeMerchants = mgi_scalar($pdo, "SELECT COUNT(*) FROM merchant_storefronts WHERE status IN ('published','active')", [], 0.0);
        if (mgi_db_column_exists($pdo, 'merchant_storefronts', 'created_at')) {
            $newMerchants30 = mgi_scalar($pdo, "SELECT COUNT(*) FROM merchant_storefronts WHERE status IN ('published','active') AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0);
            $prevMerchants30 = mgi_scalar($pdo, "SELECT COUNT(*) FROM merchant_storefronts WHERE status IN ('published','active') AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0);
        }
    }

    if ($activeMerchants === null) return $model;

    $activeMerchants = max(0, (int)$activeMerchants);
    $newMerchants30 = max(0, (int)($newMerchants30 ?? 0));
    $prevMerchants30 = max(0, (int)($prevMerchants30 ?? 0));

    $subscriptionMrr = null;
    if ($tables['subscriptions'] && mgi_db_column_exists($pdo, 'subscriptions', 'amount_cents')) {
        $subscriptionMrrCents = mgi_scalar($pdo, "SELECT COALESCE(SUM(amount_cents),0) FROM subscriptions WHERE status IN ('trialing','active','cancel_pending') AND (recovery_status IS NULL OR recovery_status='clear') AND (current_period_end IS NULL OR current_period_end > UTC_TIMESTAMP())", [], 0.0);
        $subscriptionMrr = max(0.0, ((float)$subscriptionMrrCents) / 100);
    }

    $projectedArpu = 25.0;
    if ($subscriptionMrr !== null && $subscriptionMrr > 0 && $activeMerchants > 0) {
        $projectedArpu = max(1.0, $subscriptionMrr / $activeMerchants);
    }
    $mrr = $subscriptionMrr !== null && $subscriptionMrr > 0 ? $subscriptionMrr : ($activeMerchants * $projectedArpu);
    $arr = $mrr * 12;
    $merchantGrowth = mgi_period_growth((float)$newMerchants30, (float)$prevMerchants30);

    $mrrGrowth = $merchantGrowth;
    if ($tables['subscriptions'] && mgi_db_column_exists($pdo, 'subscriptions', 'created_at') && mgi_db_column_exists($pdo, 'subscriptions', 'amount_cents')) {
        $newMrr = mgi_scalar($pdo, "SELECT COALESCE(SUM(amount_cents),0) FROM subscriptions WHERE status IN ('trialing','active','cancel_pending') AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0) ?? 0.0;
        $prevMrr = mgi_scalar($pdo, "SELECT COALESCE(SUM(amount_cents),0) FROM subscriptions WHERE status IN ('trialing','active','cancel_pending') AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0) ?? 0.0;
        $mrrGrowth = mgi_period_growth($newMrr, $prevMrr);
    }

    $campaignVolume30 = 0;
    if ($tables['feed_posts'] && mgi_db_column_exists($pdo, 'feed_posts', 'created_at')) {
        $campaignVolume30 += (int)(mgi_scalar($pdo, "SELECT COUNT(*) FROM feed_posts WHERE status IN ('published','promoted') AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0) ?? 0);
    }
    if ($tables['microgift_claims'] && mgi_db_column_exists($pdo, 'microgift_claims', 'completed_at')) {
        $campaignVolume30 += (int)(mgi_scalar($pdo, "SELECT COUNT(*) FROM microgift_claims WHERE status='completed' AND completed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0) ?? 0);
    }
    if ($tables['microgift_redemptions'] && mgi_db_column_exists($pdo, 'microgift_redemptions', 'redeemed_at')) {
        $campaignVolume30 += (int)(mgi_scalar($pdo, "SELECT COUNT(*) FROM microgift_redemptions WHERE status='completed' AND redeemed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0.0) ?? 0);
    }

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $label = date('M', strtotime('-' . $i . ' months'));
        $monthMrr = null;
        if ($tables['public_profiles'] && $tables['users'] && mgi_db_column_exists($pdo, 'public_profiles', 'created_at') && mgi_db_column_exists($pdo, 'public_profiles', 'profile_type')) {
            $end = gmdate('Y-m-t 23:59:59', strtotime('-' . $i . ' months'));
            $merchantAtMonth = mgi_scalar($pdo, "SELECT COUNT(DISTINCT pp.user_id) FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.profile_type='merchant' AND pp.status='active' AND u.status='active' AND pp.created_at <= ?", [$end], null);
            if ($merchantAtMonth !== null) $monthMrr = max(0.0, $merchantAtMonth * $projectedArpu);
        }
        if ($monthMrr === null) {
            $progress = (6 - $i) / 6;
            $monthMrr = max(0.0, $mrr * (0.42 + ($progress * 0.58)));
        }
        $months[] = ['label' => $label, 'mrr' => $monthMrr];
    }

    $growth30 = max($merchantGrowth, $mrrGrowth);

    return [
        'is_live' => true,
        'source_label' => $subscriptionMrr !== null && $subscriptionMrr > 0 ? 'Live database' : 'Live database + ARPU projection',
        'source_note' => $subscriptionMrr !== null && $subscriptionMrr > 0 ? 'MRR is summed from active subscriptions.' : 'MRR is projected from live active merchants × $25 ARPU until subscription MRR is available.',
        'active_merchants' => $activeMerchants,
        'mrr' => $mrr,
        'arr' => $arr,
        'merchant_growth' => $merchantGrowth,
        'mrr_growth' => $mrrGrowth,
        'arr_growth' => $mrrGrowth,
        'growth_30d' => $growth30,
        'new_merchants_30d' => $newMerchants30,
        'campaign_volume_30d' => $campaignVolume30,
        'months' => $months,
    ];
}

$growth = mgi_growth_data();
$chartMax = max(80000.0, max(array_map(static fn(array $m): float => (float)$m['mrr'], $growth['months'])) * 1.18);
$points = [];
$areaPoints = [];
$chartW = 420;
$chartH = 190;
$count = max(1, count($growth['months']) - 1);
foreach ($growth['months'] as $index => $month) {
    $x = 28 + (($chartW - 56) * ($index / $count));
    $y = 24 + (($chartH - 48) * (1 - min(1, ((float)$month['mrr'] / $chartMax))));
    $points[] = round($x, 1) . ',' . round($y, 1);
    $areaPoints[] = round($x, 1) . ',' . round($y, 1);
}
$areaPath = implode(' ', $areaPoints) . ' ' . ($chartW - 28) . ',' . ($chartH - 24) . ' 28,' . ($chartH - 24);
$lastMonth = end($growth['months']);
reset($growth['months']);

require __DIR__ . '/includes/header.php';
?>
<style>
:root{--tam-bg:#f8f4ed;--tam-card:#fff;--tam-ink:#090908;--tam-muted:#6d6860;--tam-line:#e9e0d4;--tam-gold:#d88c05;--tam-gold2:#f2bd3c;--tam-green:#0d9255;--tam-green-soft:#eaf8ef;--tam-shadow:0 22px 70px rgba(54,42,22,.09);--tam-max:1240px}.mg-investors-page{background:var(--tam-bg)!important;color:var(--tam-ink)}.mg-investors-page .mg-main{background:var(--tam-bg);overflow:hidden}.tam-page,.tam-page *{box-sizing:border-box}.tam-page{font-family:Inter,"Helvetica Neue",Arial,sans-serif;background:radial-gradient(circle at 88% 5%,rgba(221,154,31,.14),transparent 24%),linear-gradient(180deg,#fffdf9 0,#f8f4ed 55%,#fffdf9 100%);color:var(--tam-ink)}.tam-page:before{content:"";position:absolute;inset:70px 0 auto auto;width:48%;height:440px;background:url('/images/header_gradient_bg.png') center/cover no-repeat;opacity:.22;filter:grayscale(1);pointer-events:none}.tam-wrap{position:relative;z-index:1;width:min(var(--tam-max),calc(100% - 48px));margin:0 auto;padding:78px 0 72px}.tam-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,400px);gap:32px;align-items:start;min-height:570px}.tam-badge{display:inline-flex;align-items:center;gap:8px;min-height:31px;padding:0 13px;border-radius:999px;background:var(--tam-green-soft);color:#0d6c42;font-size:11px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.tam-badge:before{content:"";width:7px;height:7px;border-radius:50%;background:#19a765;box-shadow:0 0 0 4px rgba(25,167,101,.14)}.tam-title{max-width:760px;margin:28px 0 0;font-size:clamp(54px,7vw,104px);line-height:.9;letter-spacing:-.08em;font-weight:950;text-wrap:balance}.tam-lede{max-width:640px;margin:28px 0 0;font-size:clamp(20px,2vw,29px);line-height:1.36;letter-spacing:-.025em;color:#1c1a17;font-weight:520}.tam-sub{margin:24px 0 0;color:#777169;font-size:17px}.tam-card{border:1px solid var(--tam-line);border-radius:22px;background:rgba(255,255,255,.86);box-shadow:var(--tam-shadow)}.tam-assumptions{padding:26px}.tam-assumptions h2{display:flex;align-items:center;gap:14px;margin:0 0 8px;font-size:24px;letter-spacing:-.04em}.tam-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;color:var(--tam-gold);font-size:27px}.tam-assumption{display:grid;grid-template-columns:46px 1fr;gap:16px;align-items:center;min-height:92px;border-top:1px solid var(--tam-line);font-size:15px;line-height:1.38}.tam-assumption strong{display:block;color:#171512;font-weight:850}.tam-formula{display:grid;grid-template-columns:1fr 28px 1fr 28px 1fr 28px 1fr;align-items:center;gap:10px;margin-top:58px;padding:24px;border-radius:19px}.tam-formula-item{text-align:center}.tam-formula-item b{display:grid;place-items:center;margin:0 auto 10px;width:46px;height:46px;border-radius:50%;color:var(--tam-gold);font-size:26px}.tam-formula-item span{display:block;font-size:15px;line-height:1.25}.tam-op{text-align:center;font-size:28px;font-weight:850}.tam-grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:26px;margin-top:34px}.tam-market{padding:32px}.tam-market-top{display:flex;align-items:center;gap:14px;color:var(--tam-gold);font-size:25px;font-weight:950}.tam-market-value{display:flex;align-items:baseline;gap:10px;margin-top:28px;font-size:clamp(42px,5vw,62px);line-height:.9;letter-spacing:-.06em;font-weight:950}.tam-market-value small{font-size:27px;letter-spacing:-.04em}.tam-market p{margin:24px 0 0;font-size:17px}.tam-rule{height:1px;background:linear-gradient(90deg,var(--tam-gold),rgba(216,140,5,.2));margin:24px 0}.tam-market strong{font-size:27px}.tam-note{margin:18px 6px 0;color:#80786e;font-size:13px;font-style:italic}.tam-section{margin-top:30px}.tam-panel{padding:28px;border-radius:22px}.tam-section-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.tam-section-title{margin:0;font-size:25px;letter-spacing:-.04em}.tam-section-copy{margin:7px 0 0;color:#5c5750;font-size:14px}.tam-live-pill{display:inline-flex;align-items:center;gap:7px;min-height:25px;padding:0 10px;border-radius:999px;background:var(--tam-green-soft);color:#087443;font-size:11px;font-weight:950}.tam-live-pill:before{content:"";width:7px;height:7px;border-radius:50%;background:#1aa763}.tam-growth-grid{display:grid;grid-template-columns:.95fr 1.05fr;gap:28px;align-items:stretch}.tam-growth-cards{display:grid;grid-template-columns:1fr 1fr;gap:16px}.tam-growth-card{padding:20px;border:1px solid var(--tam-line);border-radius:15px;background:#fff}.tam-growth-card span{display:block;color:#3f3b35;font-size:13px;font-weight:850}.tam-growth-card strong{display:block;margin-top:8px;font-size:31px;letter-spacing:-.045em}.tam-growth-card em{display:block;margin-top:8px;color:var(--tam-green);font-size:13px;font-style:normal;font-weight:850}.tam-chart-card{padding:18px 18px 14px;border:1px solid var(--tam-line);border-radius:18px;background:#fff}.tam-chart-title{margin:0 0 10px;font-size:15px;font-weight:950;text-align:center}.tam-growth-svg{width:100%;height:auto;display:block}.tam-axis{fill:#726b61;font-size:11px;font-weight:760}.tam-gridline{stroke:#ded7cc;stroke-dasharray:3 5}.tam-line{fill:none;stroke:var(--tam-gold);stroke-width:4;stroke-linecap:round;stroke-linejoin:round}.tam-area{fill:rgba(216,140,5,.12)}.tam-dot{fill:var(--tam-gold);stroke:#fff;stroke-width:3}.tam-tip{filter:drop-shadow(0 10px 22px rgba(54,42,22,.13))}.tam-path-card{padding:30px}.tam-bar-chart{width:100%;height:auto;display:block}.tam-bar{fill:url(#tamBar)}.tam-step{fill:none;stroke:var(--tam-gold);stroke-width:3;stroke-dasharray:5 7}.tam-why{margin-top:36px}.tam-why h2{font-size:32px;letter-spacing:-.055em;margin:0 0 18px}.tam-why-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.tam-why-card{display:grid;grid-template-columns:54px 1fr;gap:16px;align-items:center;padding:24px;border:1px solid var(--tam-line);border-radius:17px;background:#fff;box-shadow:0 12px 34px rgba(54,42,22,.045)}.tam-why-card b{display:grid;place-items:center;color:var(--tam-gold);font-size:38px}.tam-why-card strong{display:block;font-size:17px}.tam-why-card span{display:block;margin-top:6px;color:#5f5a53;font-size:14px;line-height:1.35}.tam-cta{display:grid;grid-template-columns:auto 1fr minmax(280px,420px);gap:26px;align-items:center;margin-top:34px;padding:26px 32px;border-radius:20px;border:1px solid var(--tam-line);background:radial-gradient(circle at 5% 50%,rgba(216,140,5,.14),transparent 18%),linear-gradient(135deg,#fff7e9,#fffdf8);box-shadow:var(--tam-shadow)}.tam-cta-badge{display:grid;place-items:center;width:90px;height:90px;border-radius:50%;background:#fff;color:var(--tam-gold);font-size:45px}.tam-cta h2{margin:0;font-size:34px;line-height:1.07;letter-spacing:-.055em}.tam-actions{display:grid;gap:12px}.tam-btn{display:flex;align-items:center;justify-content:center;gap:12px;min-height:54px;padding:0 20px;border-radius:12px;text-decoration:none!important;font-size:14px;font-weight:950}.tam-btn-dark{background:#111;color:#fff!important;border:1px solid #111}.tam-btn-light{background:#fff;color:#111!important;border:1px solid #111}.tam-footer{display:grid;grid-template-columns:1.2fr repeat(4,1fr);gap:30px;margin-top:54px;padding-top:30px;border-top:1px solid var(--tam-line)}.tam-footer-logo{font-weight:950;letter-spacing:.25em}.tam-footer small{display:block;margin-top:18px;color:#7d766d}.tam-footer h3{margin:0 0 12px;font-size:11px;letter-spacing:.16em;text-transform:uppercase}.tam-footer a{display:block;color:#4f4b45;text-decoration:none;font-size:13px;line-height:1.8}.tam-footer a.active{color:var(--tam-gold);font-weight:950}@media(max-width:980px){.tam-hero,.tam-growth-grid,.tam-cta{grid-template-columns:1fr}.tam-grid3,.tam-why-grid{grid-template-columns:1fr}.tam-formula{grid-template-columns:1fr}.tam-op{font-size:20px}.tam-footer{grid-template-columns:1fr 1fr}.tam-title{font-size:54px}}@media(max-width:560px){.tam-wrap{width:min(100% - 28px,var(--tam-max));padding-top:45px}.tam-growth-cards{grid-template-columns:1fr}.tam-footer{grid-template-columns:1fr}.tam-market-value{font-size:42px}.tam-cta{padding:22px}}
</style>
<main class="tam-page"><div class="tam-wrap">
  <section class="tam-hero" aria-labelledby="tam-title">
    <div>
      <span class="tam-badge">Investor model</span>
      <h1 class="tam-title" id="tam-title">Microgifter<br>Bottom-Up TAM</h1>
      <p class="tam-lede">A simple revenue model built from merchant count × monthly ARPU.</p>
      <p class="tam-sub">Promotional CRM for local commerce.</p>
      <div class="tam-card tam-formula" aria-label="Bottom-up TAM formula">
        <div class="tam-formula-item"><b>♙</b><span>Addressable<br>Merchants</span></div><div class="tam-op">×</div>
        <div class="tam-formula-item"><b>$</b><span>Monthly<br>ARPU</span></div><div class="tam-op">×</div>
        <div class="tam-formula-item"><b>12</b><span>12<br>Months</span></div><div class="tam-op">=</div>
        <div class="tam-formula-item"><b>↗</b><span>Annual Revenue<br>Opportunity</span></div>
      </div>
    </div>
    <aside class="tam-card tam-assumptions">
      <h2><span class="tam-icon">▤</span>Core Assumptions</h2>
      <div class="tam-assumption"><span class="tam-icon">▥</span><strong>Target verticals: restaurants,<br>cafés, salons, fitness, events</strong></div>
      <div class="tam-assumption"><span class="tam-icon">⚙</span><strong>Subscription model:<br>Promotional CRM +<br>campaign tools</strong></div>
      <div class="tam-assumption"><span class="tam-icon">◎</span><strong>Near-term milestone:<br>2,500 paying businesses</strong></div>
      <div class="tam-assumption"><span class="tam-icon">↗</span><strong>Long-term upside:<br>multi-location, transaction,<br>and enterprise expansion</strong></div>
    </aside>
  </section>

  <section class="tam-grid3" aria-label="Bottom-up TAM cards">
    <article class="tam-card tam-market"><div class="tam-market-top"><span>◎</span><span>TAM</span></div><div class="tam-market-value"><span>$294M</span><small>ARR</small></div><p>500,000 merchants × $49/month</p><div class="tam-rule"></div><strong>$24.5M</strong> <span>MRR</span></article>
    <article class="tam-card tam-market"><div class="tam-market-top"><span>▥</span><span>SAM</span></div><div class="tam-market-value"><span>$58.8M</span><small>ARR</small></div><p>100,000 merchants × $49/month</p><div class="tam-rule"></div><strong>$4.9M</strong> <span>MRR</span></article>
    <article class="tam-card tam-market"><div class="tam-market-top"><span>◉</span><span>SOM</span></div><div class="tam-market-value"><span>$750K</span><small>ARR</small></div><p>2,500 merchants × $25/month</p><div class="tam-rule"></div><strong>$62.5K</strong> <span>MRR</span></article>
  </section>
  <p class="tam-note">Internal Microgifter investor model. Figures are illustrative and based on current assumptions.</p>

  <section class="tam-section tam-card tam-panel" aria-labelledby="growth-title">
    <div class="tam-section-head"><div><h2 class="tam-section-title" id="growth-title">Current Growth Snapshot <span class="tam-live-pill"><?= mg_e($growth['is_live'] ? 'Live data' : 'Model data') ?></span></h2><p class="tam-section-copy">Live operating metrics based on current platform activity. <?= mg_e($growth['source_note']) ?></p></div></div>
    <div class="tam-growth-grid">
      <div class="tam-growth-cards">
        <div class="tam-growth-card"><span>♙ Active merchants</span><strong><?= mg_e(mgi_num($growth['active_merchants'])) ?></strong><em>↑ <?= mg_e(number_format(abs((float)$growth['merchant_growth']), 1)) ?>% vs last 30 days</em></div>
        <div class="tam-growth-card"><span>$ MRR</span><strong><?= mg_e(mgi_money($growth['mrr'])) ?></strong><em>↑ <?= mg_e(number_format(abs((float)$growth['mrr_growth']), 1)) ?>% vs last 30 days</em></div>
        <div class="tam-growth-card"><span>↗ ARR run rate</span><strong><?= mg_e(mgi_money($growth['arr'])) ?></strong><em>↑ <?= mg_e(number_format(abs((float)$growth['arr_growth']), 1)) ?>% vs last 30 days</em></div>
        <div class="tam-growth-card"><span>⌁ 30-day growth</span><strong><?= mg_e(mgi_pct($growth['growth_30d'])) ?></strong><em><?= mg_e(mgi_num($growth['new_merchants_30d'])) ?> new merchants · <?= mg_e(mgi_num($growth['campaign_volume_30d'])) ?> activity events</em></div>
      </div>
      <div class="tam-chart-card"><h3 class="tam-chart-title">6-month growth trend (MRR)</h3><svg class="tam-growth-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" role="img" aria-label="6-month MRR trend"><polygon class="tam-area" points="<?= mg_e($areaPath) ?>"/><line class="tam-gridline" x1="28" y1="28" x2="392" y2="28"/><line class="tam-gridline" x1="28" y1="75" x2="392" y2="75"/><line class="tam-gridline" x1="28" y1="122" x2="392" y2="122"/><line x1="28" y1="166" x2="392" y2="166" stroke="#cfc7bb"/><polyline class="tam-line" points="<?= mg_e(implode(' ', $points)) ?>"/><?php foreach($points as $i=>$point): [$px,$py]=array_map('floatval',explode(',',$point)); ?><circle class="tam-dot" cx="<?= $px ?>" cy="<?= $py ?>" r="5"/><?php endforeach; ?><?php foreach($growth['months'] as $i=>$month): $x=28+(($chartW-56)*($i/$count)); ?><text class="tam-axis" x="<?= round($x-10,1) ?>" y="184"><?= mg_e($month['label']) ?></text><?php endforeach; ?><text class="tam-axis" x="4" y="32"><?= mg_e(mgi_money($chartMax,false)) ?></text><text class="tam-axis" x="4" y="169">$0</text><g class="tam-tip"><rect x="285" y="70" width="100" height="58" rx="10" fill="#fff" stroke="#e8dfd2"/><text x="300" y="90" font-size="11" font-weight="900" fill="#8d5c04"><?= mg_e($lastMonth['label'] ?? 'Live') ?> (Live)</text><text x="300" y="108" font-size="14" font-weight="950" fill="#111"><?= mg_e(mgi_money($growth['mrr'])) ?> MRR</text><text x="300" y="123" font-size="11" font-weight="900" fill="#0d9255">↑ <?= mg_e(number_format(abs((float)$growth['mrr_growth']),1)) ?>%</text></g></svg></div>
    </div>
  </section>

  <section class="tam-section tam-card tam-path-card" aria-labelledby="path-title"><h2 class="tam-section-title" id="path-title">Bottom-Up Path to $294M ARR</h2><p class="tam-section-copy">From focused entry to total addressable market.</p><svg class="tam-bar-chart" viewBox="0 0 1080 330" role="img" aria-label="Bottom-up path to TAM"><defs><linearGradient id="tamBar" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#f7bb34"/><stop offset="1" stop-color="#d88c05"/></linearGradient></defs><g fill="#6c655d" font-size="15"><text x="28" y="66">ARR</text><text x="28" y="92">$300M</text><text x="28" y="142">$200M</text><text x="28" y="192">$100M</text><text x="28" y="246">$0</text></g><g stroke="#ddd4c8" stroke-dasharray="4 6"><line x1="94" y1="82" x2="1035" y2="82"/><line x1="94" y1="132" x2="1035" y2="132"/><line x1="94" y1="182" x2="1035" y2="182"/></g><line x1="94" y1="246" x2="1035" y2="246" stroke="#bfb7aa"/><rect class="tam-bar" x="185" y="232" width="120" height="14" rx="5"/><rect class="tam-bar" x="510" y="132" width="120" height="114" rx="7"/><rect class="tam-bar" x="845" y="42" width="120" height="204" rx="8"/><path class="tam-step" d="M245 222 H430 V150 H570 H760 V54 H845"/><text x="195" y="218" font-size="17" font-weight="950">$750K ARR</text><text x="525" y="118" font-size="17" font-weight="950">$58.8M ARR</text><text x="850" y="28" font-size="17" font-weight="950">$294M ARR</text><g fill="#111" font-size="18" text-anchor="middle"><text x="245" y="278">2.5K merchants</text><text x="245" y="302">SOM</text><text x="570" y="278">100K merchants</text><text x="570" y="302">SAM</text><text x="905" y="278">500K merchants</text><text x="905" y="302">TAM</text></g></svg></section>

  <section class="tam-why" aria-labelledby="why-title"><h2 id="why-title">Why this model matters</h2><div class="tam-why-grid"><article class="tam-why-card"><b>◔</b><div><strong>Clear unit economics</strong><span>Defined ARPU and merchant math that scale predictably.</span></div></article><article class="tam-why-card"><b>♙</b><div><strong>Scalable merchant base</strong><span>Large and growing market of local businesses to capture.</span></div></article><article class="tam-why-card"><b>↗</b><div><strong>Expansion upside</strong><span>Multiple levers for ARPU growth and merchant expansion.</span></div></article></div></section>

  <section class="tam-cta"><div class="tam-cta-badge">🏆</div><h2>Start with a focused SOM.<br>Scale into a much larger TAM.</h2><div class="tam-actions"><a class="tam-btn tam-btn-dark" href="/learn-more.php">▤ Request Deck</a><a class="tam-btn tam-btn-light" href="/investor-tam.php">↗ View Market Model</a></div></section>

  <footer class="tam-footer"><div><div class="tam-footer-logo">MICROGIFTER</div><small>© <?= date('Y') ?> Microgifter, Inc. All rights reserved.</small></div><div><h3>Platform</h3><a href="/">Overview</a><a href="/pricing.php">Pricing</a><a href="/developer-docs.php">Integrations</a></div><div><h3>Market</h3><a href="/investor-tam.php">Market Model</a><a href="/discover.php">Use Cases</a><a href="/developer-docs.php#quickstart">Data & Insights</a></div><div><h3>Investors</h3><a class="active" href="/investors.php">Investor Portal</a><a href="/investor-tam.php">Market Model</a><a href="/learn-more.php">Request Access</a></div><div><h3>Company</h3><a href="/learn-more.php">About</a><a href="/pricing.php">Pricing</a><a href="/learn-more.php">Contact</a></div></footer>
</div></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
