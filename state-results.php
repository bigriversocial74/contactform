<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

function mg_state_results_states(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'Washington DC',
    ];
}

function mg_state_results_normalize_state(mixed $value): ?string
{
    $raw = strtoupper(trim((string)$value));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    if ($raw === '') {
        return null;
    }

    $states = mg_state_results_states();
    if (isset($states[$raw])) {
        return $raw;
    }

    foreach ($states as $code => $name) {
        $normalizedName = strtoupper(preg_replace('/\s+/u', ' ', trim($name)) ?? '');
        if ($raw === $normalizedName) {
            return $code;
        }
    }

    $rawWords = strtoupper(trim(preg_replace('/[^A-Z0-9]+/', ' ', $raw) ?? ''));
    if (in_array($rawWords, ['WASHINGTON DC', 'WASHINGTON D C', 'DISTRICT OF COLUMBIA'], true)) {
        return 'DC';
    }

    return null;
}

function mg_state_results_asset_url(mixed $publicId): ?string
{
    $publicId = trim((string)$publicId);
    return $publicId !== '' ? '/api/public/media.php?asset=' . rawurlencode($publicId) : null;
}

function mg_state_results_public_url(mixed $value): ?string
{
    $value = trim((string)$value);
    return $value !== '' && preg_match('#^(?:https?://|/)#i', $value) === 1 ? $value : null;
}

function mg_state_results_excerpt(mixed $value, int $max = 145): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if ($text === '' || mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

function mg_state_results_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = mb_substr((string)($parts[0] ?? 'M'), 0, 1);
    $last = count($parts) > 1 ? mb_substr((string)end($parts), 0, 1) : '';
    return strtoupper($first . $last);
}

function mg_state_results_location_clause_alias(string $alias): string
{
    return "UPPER(TRIM({$alias}.country_code)) = 'US'
        AND {$alias}.status = 'active'
        AND NULLIF(TRIM(COALESCE({$alias}.region,'')), '') IS NOT NULL
        AND UPPER(TRIM({$alias}.region)) IN (?, ?)";
}

function mg_state_results_counts(PDO $pdo, array $states): array
{
    $counts = array_fill_keys(array_keys($states), 0);
    $lookup = [];
    foreach ($states as $code => $name) {
        $lookup[strtoupper($code)] = $code;
        $lookup[strtoupper($name)] = $code;
    }
    $lookup['DISTRICT OF COLUMBIA'] = 'DC';
    $lookup['WASHINGTON D C'] = 'DC';

    $sql = "SELECT UPPER(TRIM(ml.region)) AS region_key, COUNT(DISTINCT mw.id) AS merchant_count
        FROM merchant_locations ml
        INNER JOIN merchant_workspaces mw ON mw.id = ml.workspace_id AND mw.status NOT IN ('suspended','archived')
        WHERE ml.status = 'active'
          AND UPPER(TRIM(ml.country_code)) = 'US'
          AND NULLIF(TRIM(COALESCE(ml.region,'')), '') IS NOT NULL
        GROUP BY UPPER(TRIM(ml.region))";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $region = strtoupper(trim((string)($row['region_key'] ?? '')));
        $region = preg_replace('/\s+/u', ' ', $region) ?? '';
        $code = $lookup[$region] ?? null;
        if ($code === null) {
            continue;
        }
        $counts[$code] = ($counts[$code] ?? 0) + (int)($row['merchant_count'] ?? 0);
    }

    return $counts;
}

function mg_state_results_merchants(PDO $pdo, string $stateCode, string $stateName): array
{
    $locationClause = mg_state_results_location_clause_alias('ml_scope');
    $sql = "SELECT
            mw.id AS workspace_id,
            mw.public_id AS workspace_public_id,
            mw.display_name AS workspace_name,
            mw.legal_name,
            mw.business_type,
            mw.website_url,
            mw.support_phone,
            mw.support_email,
            mw.status AS workspace_status,
            mw.activated_at,
            mw.created_at AS workspace_created_at,
            ms.slug AS storefront_slug,
            ms.status AS storefront_status,
            COALESCE(ms.display_name, mw.display_name) AS storefront_name,
            ms.headline AS storefront_headline,
            ms.description AS storefront_description,
            pp.slug AS profile_slug,
            pp.display_name AS profile_name,
            pp.headline AS profile_headline,
            pp.bio AS profile_bio,
            pp.avatar_url,
            pp.cover_url AS profile_cover_url,
            logo.public_id AS logo_asset_public_id,
            cover.public_id AS cover_asset_public_id,
            lc.primary_city,
            lc.location_count,
            lc.city_list,
            (
                SELECT COUNT(DISTINCT cp.id)
                FROM catalog_products cp
                WHERE cp.merchant_user_id = mw.merchant_user_id
                  AND cp.status = 'published'
            ) AS product_count
        FROM merchant_workspaces mw
        INNER JOIN users u ON u.id = mw.merchant_user_id AND u.status = 'active'
        INNER JOIN (
            SELECT
                workspace_id,
                SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(TRIM(city), '') ORDER BY is_primary DESC, id ASC SEPARATOR '||'), '||', 1) AS primary_city,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(city), '') ORDER BY NULLIF(TRIM(city), '') ASC SEPARATOR ', ') AS city_list,
                COUNT(DISTINCT id) AS location_count
            FROM merchant_locations ml_scope
            WHERE {$locationClause}
            GROUP BY workspace_id
        ) lc ON lc.workspace_id = mw.id
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id = mw.merchant_user_id AND ms.status <> 'archived'
        LEFT JOIN public_profiles pp ON pp.user_id = mw.merchant_user_id AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
        LEFT JOIN catalog_assets logo ON logo.id = ms.logo_asset_id AND logo.status = 'ready'
        LEFT JOIN catalog_assets cover ON cover.id = ms.cover_asset_id AND cover.status = 'ready'
        WHERE mw.status NOT IN ('suspended','archived')
        ORDER BY
            CASE WHEN ms.status = 'published' THEN 0 ELSE 1 END,
            CASE WHEN mw.status = 'active' THEN 0 ELSE 1 END,
            COALESCE(ms.display_name, pp.display_name, mw.display_name) ASC,
            mw.id ASC
        LIMIT 180";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$stateCode, strtoupper($stateName)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$states = mg_state_results_states();
$stateCode = mg_state_results_normalize_state($_GET['state'] ?? '') ?? 'AZ';
$stateName = $states[$stateCode] ?? 'Arizona';
$stateCounts = array_fill_keys(array_keys($states), 0);
$merchants = [];
$loadError = null;

try {
    $pdo = mg_db();
    $stateCounts = mg_state_results_counts($pdo, $states);
    $merchants = mg_state_results_merchants($pdo, $stateCode, $stateName);
} catch (Throwable $error) {
    $loadError = 'State merchant results are temporarily unavailable.';
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'state.results.load_failed', 'Unable to load state merchant results.', [
            'state' => $stateCode,
            'exception_class' => $error::class,
        ]);
    }
}

$currentCount = (int)($stateCounts[$stateCode] ?? count($merchants));
$activeStateCount = count(array_filter($stateCounts, static fn ($count) => (int)$count > 0));
$registeredLocationTotal = 0;
foreach ($merchants as $merchant) {
    $registeredLocationTotal += max(1, (int)($merchant['location_count'] ?? 1));
}

$page_title = $stateName . ' Merchant Market | Microgifter';
$page_section = 'state-results';
$header_mode = 'public';
$page_body_class = 'mg-token-network-page mg-state-results-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_manifest = [
    'id' => 'state-results',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'state-results', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --mgt-bg:#f7f4ee;
  --mgt-surface:#fffdf9;
  --mgt-card:#ffffff;
  --mgt-ink:#10100e;
  --mgt-muted:#69655e;
  --mgt-line:#e8e1d6;
  --mgt-gold:#d9a83e;
  --mgt-gold-dark:#8d6412;
  --mgt-gold-soft:#fbf1d9;
  --mgt-green:#0d9255;
  --mgt-green-soft:#e8f8ef;
  --mgt-shadow:0 24px 70px rgba(53,43,24,.10);
  --mgt-max:1240px;
}
.mg-state-results-page{background:var(--mgt-bg)!important;color:var(--mgt-ink)}
.mg-state-results-page .mg-main{background:var(--mgt-bg);overflow:hidden}
.mgs-page,.mgs-page *{box-sizing:border-box}
.mgs-page{position:relative;background:radial-gradient(circle at 12% 6%,rgba(217,168,62,.18),transparent 24%),radial-gradient(circle at 84% 10%,rgba(20,150,90,.10),transparent 22%),linear-gradient(180deg,#fbf9f4 0,#f7f4ee 58%,#fbf9f4 100%);color:var(--mgt-ink);font-family:Inter,"Helvetica Neue",Arial,sans-serif}
.mgs-full-hero{position:relative;min-height:calc(100vh - 74px);width:100%;display:flex;align-items:stretch;overflow:hidden;background:linear-gradient(90deg,rgba(247,244,238,.98) 0%,rgba(247,244,238,.74) 48%,rgba(247,244,238,.42) 100%),radial-gradient(circle at 6% 10%,rgba(126,178,209,.30),transparent 24%),radial-gradient(circle at 85% 8%,rgba(217,168,62,.30),transparent 35%),url('/images/header_gradient_bg.png') center/cover no-repeat}
.mgs-full-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.20),rgba(255,255,255,.05) 42%,rgba(247,244,238,.70) 100%),repeating-linear-gradient(90deg,rgba(255,255,255,.18) 0 1px,transparent 1px 96px);pointer-events:none}
.mgs-hero-grid{position:relative;z-index:1;width:100%;display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,390px);gap:28px;align-items:center;padding:clamp(28px,3.4vw,54px)}
.mgs-live{display:inline-flex;align-items:center;gap:7px;min-height:26px;padding:0 10px;border-radius:999px;background:var(--mgt-green-soft);color:#0b7a46;font-size:9px;font-weight:950;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}
.mgs-live:before{content:"";width:7px;height:7px;border-radius:50%;background:#20b56c;box-shadow:0 0 0 4px rgba(32,181,108,.13)}
.mgs-hero-title{max-width:870px;margin:22px 0 0;color:#111;font-size:clamp(42px,7vw,104px);line-height:.86;letter-spacing:-.075em;font-weight:950;text-wrap:balance}
.mgs-hero-copy{max-width:720px;margin:22px 0 0;color:#4f4b44;font-size:clamp(15px,1.45vw,20px);line-height:1.45;font-weight:620}
.mgs-value-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:28px;max-width:880px}
.mgs-value-card{min-height:132px;padding:22px 20px;border:1px solid rgba(232,225,214,.86);border-radius:18px;background:rgba(255,255,255,.72);box-shadow:0 20px 54px rgba(53,43,24,.08);backdrop-filter:blur(14px)}
.mgs-value-card span{display:block;min-height:28px;color:#6d685f;font-size:9px;line-height:1.25;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.mgs-value-card strong{display:block;margin-top:13px;color:#111;font-size:clamp(28px,3.2vw,46px);line-height:.9;font-weight:950;letter-spacing:-.055em}
.mgs-value-card em{display:block;margin-top:9px;color:var(--mgt-green);font-size:10px;line-height:1.25;font-style:normal;font-weight:900}
.mgs-state-card{padding:22px;border:1px solid rgba(232,225,214,.95);border-radius:22px;background:rgba(255,255,255,.86);box-shadow:0 24px 70px rgba(53,43,24,.13);backdrop-filter:blur(18px)}
.mgs-state-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.mgs-state-card h2{max-width:250px;margin:0;color:#111;font-size:18px;line-height:1.08;letter-spacing:-.04em;font-weight:950}
.mgs-state-select{display:grid;gap:8px;margin-top:18px}
.mgs-state-select label{color:#4f4b44;font-size:9px;font-weight:950;letter-spacing:.11em;text-transform:uppercase}
.mgs-state-select select{height:48px;width:100%;border:1px solid var(--mgt-line);border-radius:11px;background:#fff;color:var(--mgt-ink);font:inherit;font-size:13px;font-weight:850;outline:none;padding:0 13px}
.mgs-state-select select:focus{border-color:rgba(217,168,62,.70);box-shadow:0 0 0 4px rgba(217,168,62,.12)}
.mgs-card-summary{display:grid;gap:8px;margin-top:16px;padding:14px;border-radius:14px;background:#f7f3ec}
.mgs-summary-row{display:flex;align-items:center;justify-content:space-between;gap:18px;color:#5b564e;font-size:10px;font-weight:780}
.mgs-summary-row strong{color:#151411;font-size:11px;font-weight:950}
.mgs-primary-btn,.mgs-secondary-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;padding:0 18px;border-radius:12px;text-decoration:none!important;font-size:12px;font-weight:950;transition:.18s ease}
.mgs-primary-btn{border:1px solid #111;background:#111;color:#fff!important;box-shadow:0 14px 30px rgba(17,17,17,.15)}
.mgs-primary-btn:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(17,17,17,.20)}
.mgs-secondary-btn{border:1px solid var(--mgt-line);background:#fff;color:#161512!important}
.mgs-state-actions{display:grid;grid-template-columns:1fr;gap:9px;margin-top:16px}
.mgs-shell{width:min(var(--mgt-max),calc(100% - 48px));margin:0 auto;padding:72px 0 92px}
.mgs-section-head{display:flex;align-items:end;justify-content:space-between;gap:28px;margin-bottom:26px}
.mgs-section-head>div{max-width:760px}
.mgs-kicker{display:block;color:var(--mgt-gold-dark);font-size:10px;font-weight:950;letter-spacing:.13em;text-transform:uppercase}
.mgs-section-title{margin:10px 0 0;color:var(--mgt-ink);font-size:clamp(34px,4vw,56px);line-height:.96;letter-spacing:-.06em;font-weight:950;text-wrap:balance}
.mgs-section-copy{max-width:660px;margin:14px 0 0;color:#5b5750;font-size:15px;line-height:1.5;font-weight:560}
.mgs-filter-strip{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end}
.mgs-filter-strip a{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 11px;border:1px solid var(--mgt-line);border-radius:999px;background:#fff;color:#4f4b44;text-decoration:none;font-size:10px;font-weight:950;letter-spacing:.04em;text-transform:uppercase}
.mgs-filter-strip a.is-active{border-color:rgba(217,168,62,.55);background:var(--mgt-gold-soft);color:#6e500e}
.mgs-results-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.mgs-merchant-card{position:relative;overflow:hidden;min-height:360px;display:flex;flex-direction:column;border:1px solid var(--mgt-line);border-radius:22px;background:rgba(255,255,255,.90);box-shadow:0 18px 48px rgba(53,43,24,.07)}
.mgs-merchant-cover{height:148px;background:radial-gradient(circle at 22% 12%,rgba(217,168,62,.28),transparent 28%),radial-gradient(circle at 78% 22%,rgba(20,150,90,.18),transparent 30%),linear-gradient(135deg,#fff8e8,#f8fafc);background-size:cover;background-position:center}
.mgs-merchant-body{position:relative;display:flex;flex-direction:column;flex:1;padding:0 18px 18px}
.mgs-avatar{width:64px;height:64px;margin-top:-32px;display:grid;place-items:center;overflow:hidden;border:4px solid #fff;border-radius:18px;background:#111;color:#fff;box-shadow:0 14px 28px rgba(17,17,17,.14);font-size:18px;font-weight:950;letter-spacing:-.04em}
.mgs-avatar img{width:100%;height:100%;display:block;object-fit:cover}
.mgs-status-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:13px}
.mgs-status-pill{display:inline-flex;align-items:center;min-height:24px;padding:0 8px;border:1px solid rgba(217,168,62,.25);border-radius:999px;background:var(--mgt-gold-soft);color:#7c5a13;font-size:8.5px;font-weight:950;letter-spacing:.07em;text-transform:uppercase}
.mgs-merchant-card h3{margin:10px 0 0;color:#111;font-size:22px;line-height:1;letter-spacing:-.055em;font-weight:950}
.mgs-headline{min-height:44px;margin:9px 0 0;color:#5b5750;font-size:13px;line-height:1.42;font-weight:620}
.mgs-meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;margin:16px 0 0;border-top:1px solid var(--mgt-line);border-bottom:1px solid var(--mgt-line)}
.mgs-meta{min-height:62px;padding:11px 8px;border-right:1px solid var(--mgt-line)}
.mgs-meta:last-child{border-right:0}
.mgs-meta span{display:block;color:#77736b;font-size:8px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;line-height:1.1}
.mgs-meta strong{display:block;margin-top:6px;color:#111;font-size:17px;line-height:1;font-weight:950;letter-spacing:-.035em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mgs-city-line{margin:13px 0 0;color:#5b5750;font-size:11px;line-height:1.4;font-weight:700}
.mgs-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:auto;padding-top:18px}
.mgs-card-actions a{min-height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:11px;text-decoration:none;font-size:12px;font-weight:950}
.mgs-store-link{background:#111;color:#fff!important;border:1px solid #111}
.mgs-profile-link{background:#fff;color:#111!important;border:1px solid var(--mgt-line)}
.mgs-empty,.mgs-alert{padding:34px;border:1px solid var(--mgt-line);border-radius:22px;background:#fff;box-shadow:0 18px 48px rgba(53,43,24,.06)}
.mgs-empty h2{margin:0;color:#111;font-size:28px;line-height:1;letter-spacing:-.04em}
.mgs-empty p,.mgs-alert p{max-width:680px;margin:12px 0 0;color:#5b5750;font-size:14px;line-height:1.55;font-weight:600}
.mgs-alert{border-color:#fecaca;background:#fff1f2;color:#991b1b}
@media(max-width:1120px){.mgs-hero-grid{grid-template-columns:1fr}.mgs-state-card{max-width:620px}.mgs-results-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mgs-section-head{display:block}.mgs-filter-strip{justify-content:flex-start;margin-top:18px}}
@media(max-width:740px){.mgs-hero-grid{padding:26px 18px 34px}.mgs-hero-title{font-size:46px}.mgs-value-row{grid-template-columns:1fr}.mgs-shell{width:min(100% - 32px,640px);padding:46px 0 70px}.mgs-results-grid{grid-template-columns:1fr}.mgs-card-actions{grid-template-columns:1fr}.mgs-filter-strip{max-height:150px;overflow:auto;align-content:flex-start}}
</style>

<main class="mgs-page">
  <section class="mgs-full-hero" aria-labelledby="mgs-state-title">
    <div class="mgs-hero-grid">
      <div class="mgs-chart-stage">
        <span class="mgs-live">State market active</span>
        <h1 class="mgs-hero-title" id="mgs-state-title"><?= mg_e($stateName) ?> Local Merchant Market</h1>
        <p class="mgs-hero-copy">Browse registered Microgifter merchants with active locations in <?= mg_e($stateName) ?>. This page is the state-level market view that can later expand into deeper local results, offers, scores, and merchant ranking.</p>

        <div class="mgs-value-row" aria-label="<?= mg_e($stateName) ?> merchant stats">
          <div class="mgs-value-card"><span>Registered merchants</span><strong><?= mg_e(number_format($currentCount)) ?></strong><em>Based on active state locations</em></div>
          <div class="mgs-value-card"><span>Registered locations</span><strong><?= mg_e(number_format($registeredLocationTotal)) ?></strong><em>Across visible merchant cards</em></div>
          <div class="mgs-value-card"><span>Active state markets</span><strong><?= mg_e(number_format($activeStateCount)) ?></strong><em>States with merchant presence</em></div>
        </div>
      </div>

      <aside class="mgs-state-card" aria-label="State selector">
        <div class="mgs-state-card-top">
          <h2>Select a state market</h2>
          <span class="mgs-live">Live counts</span>
        </div>
        <form class="mgs-state-select" method="get" action="/state-results.php">
          <label for="mgsStateSelect">State</label>
          <select id="mgsStateSelect" name="state" onchange="this.form.submit()">
            <?php foreach ($states as $code => $name): ?>
              <option value="<?= mg_e($code) ?>"<?= $code === $stateCode ? ' selected' : '' ?>><?= mg_e($name) ?> · <?= mg_e(number_format((int)($stateCounts[$code] ?? 0))) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="mgs-primary-btn" type="submit">Open state</button></noscript>
        </form>
        <div class="mgs-card-summary">
          <div class="mgs-summary-row"><span>Selected state</span><strong><?= mg_e($stateCode) ?></strong></div>
          <div class="mgs-summary-row"><span>Merchant count</span><strong><?= mg_e(number_format($currentCount)) ?></strong></div>
          <div class="mgs-summary-row"><span>Page type</span><strong>State results</strong></div>
        </div>
        <div class="mgs-state-actions">
          <a class="mgs-primary-btn" href="/discover.php">Back to market <span>→</span></a>
          <a class="mgs-secondary-btn" href="/buy-in.php">View token network</a>
        </div>
      </aside>
    </div>
  </section>

  <section class="mgs-shell" aria-labelledby="mgs-results-title">
    <div class="mgs-section-head">
      <div>
        <span class="mgs-kicker">Local merchant cards</span>
        <h2 class="mgs-section-title" id="mgs-results-title"><?= mg_e($stateName) ?> registered merchant results.</h2>
        <p class="mgs-section-copy">These cards are powered by merchant workspaces with active registered locations in the selected state. Storefront and profile links are shown when the merchant has those public surfaces available.</p>
      </div>
      <nav class="mgs-filter-strip" aria-label="Quick state links">
        <?php foreach ($states as $code => $name): ?>
          <?php if ((int)($stateCounts[$code] ?? 0) <= 0 && $code !== $stateCode) { continue; } ?>
          <a class="<?= $code === $stateCode ? 'is-active' : '' ?>" href="/state-results.php?state=<?= rawurlencode($code) ?>" title="<?= mg_e($name) ?> merchants"><?= mg_e($code) ?> · <?= mg_e(number_format((int)($stateCounts[$code] ?? 0))) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if ($loadError): ?>
      <section class="mgs-alert" role="alert"><strong><?= mg_e($loadError) ?></strong><p>Please try again in a moment.</p></section>
    <?php elseif (!$merchants): ?>
      <section class="mgs-empty">
        <h2>No registered merchants found yet.</h2>
        <p>There are no registered merchant locations in <?= mg_e($stateName) ?> yet. Once a merchant adds an active location in this state, their card will appear here automatically.</p>
        <div class="mgs-state-actions" style="max-width:320px"><a class="mgs-primary-btn" href="/discover.php">Back to market</a></div>
      </section>
    <?php else: ?>
      <section class="mgs-results-grid" aria-label="<?= mg_e($stateName) ?> merchant cards">
        <?php foreach ($merchants as $merchant): ?>
          <?php
            $merchantName = trim((string)($merchant['storefront_name'] ?? $merchant['profile_name'] ?? $merchant['workspace_name'] ?? 'Microgifter merchant'));
            $headline = mg_state_results_excerpt($merchant['storefront_headline'] ?? $merchant['profile_headline'] ?? $merchant['storefront_description'] ?? $merchant['profile_bio'] ?? '', 128);
            $coverUrl = mg_state_results_asset_url($merchant['cover_asset_public_id'] ?? null) ?? mg_state_results_public_url($merchant['profile_cover_url'] ?? null);
            $avatarUrl = mg_state_results_public_url($merchant['avatar_url'] ?? null) ?? mg_state_results_asset_url($merchant['logo_asset_public_id'] ?? null);
            $storefrontSlug = trim((string)($merchant['storefront_slug'] ?? ''));
            $profileSlug = trim((string)($merchant['profile_slug'] ?? ''));
            $storeUrl = $storefrontSlug !== '' ? '/store.php?s=' . rawurlencode($storefrontSlug) : '/discover.php?location=' . rawurlencode($stateCode);
            $profileUrl = $profileSlug !== '' ? '/profile.php?slug=' . rawurlencode($profileSlug) : $storeUrl;
            $productCount = max(0, (int)($merchant['product_count'] ?? 0));
            $locationCount = max(1, (int)($merchant['location_count'] ?? 1));
            $primaryCity = trim((string)($merchant['primary_city'] ?? ''));
            $cityList = trim((string)($merchant['city_list'] ?? ''));
            $locationLabel = $primaryCity !== '' ? $primaryCity . ', ' . $stateCode : $stateName;
            $workspaceStatus = trim((string)($merchant['workspace_status'] ?? 'registered'));
            $businessType = trim((string)($merchant['business_type'] ?? 'Local merchant'));
          ?>
          <article class="mgs-merchant-card">
            <div class="mgs-merchant-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(17,17,17,.02),rgba(17,17,17,.28)),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
            <div class="mgs-merchant-body">
              <div class="mgs-avatar"><?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image" loading="lazy"><?php else: ?><?= mg_e(mg_state_results_initials($merchantName)) ?><?php endif; ?></div>
              <div class="mgs-status-row"><span class="mgs-status-pill"><?= mg_e($workspaceStatus) ?></span><span class="mgs-status-pill"><?= mg_e($stateCode) ?></span></div>
              <h3><?= mg_e($merchantName) ?></h3>
              <p class="mgs-headline"><?= mg_e($headline !== '' ? $headline : 'Registered Microgifter merchant location in ' . $stateName . '.') ?></p>
              <div class="mgs-meta-grid">
                <div class="mgs-meta"><span>Locations</span><strong><?= mg_e(number_format($locationCount)) ?></strong></div>
                <div class="mgs-meta"><span>Products</span><strong><?= mg_e(number_format($productCount)) ?></strong></div>
                <div class="mgs-meta"><span>Type</span><strong><?= mg_e($businessType !== '' ? $businessType : 'Merchant') ?></strong></div>
              </div>
              <p class="mgs-city-line"><strong><?= mg_e($locationLabel) ?></strong><?php if ($cityList !== '' && $cityList !== $primaryCity): ?> · <?= mg_e($cityList) ?><?php endif; ?></p>
              <div class="mgs-card-actions"><a class="mgs-store-link" href="<?= mg_e($storeUrl) ?>"><?= $storefrontSlug !== '' ? 'Open Store' : 'View Market' ?></a><a class="mgs-profile-link" href="<?= mg_e($profileUrl) ?>"><?= $profileSlug !== '' ? 'View Profile' : 'Details' ?></a></div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
