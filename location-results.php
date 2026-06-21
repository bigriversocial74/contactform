<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/db.php';

$states = [
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
    'WI' => 'Wisconsin', 'WY' => 'Wyoming',
];

$stateLookup = [];
foreach ($states as $code => $name) {
    $stateLookup[strtoupper($code)] = $code;
    $stateLookup[strtoupper($name)] = $code;
}

function mg_lr_clean_state_input(mixed $value, array $states, array $lookup): ?string
{
    $raw = strtoupper(trim((string) $value));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    if ($raw === '') {
        return null;
    }
    if (isset($states[$raw])) {
        return $raw;
    }
    return $lookup[$raw] ?? null;
}

function mg_lr_asset_url(mixed $publicId): ?string
{
    $publicId = trim((string) $publicId);
    if ($publicId === '') {
        return null;
    }
    return '/api/public/media.php?asset=' . rawurlencode($publicId);
}

function mg_lr_public_url(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('#^(?:https?://|/)#i', $value) !== 1) {
        return null;
    }
    return $value;
}

function mg_lr_excerpt(mixed $value, int $max = 155): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

function mg_lr_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'M';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $first = mb_substr((string) ($parts[0] ?? 'M'), 0, 1);
    $second = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return strtoupper($first . $second);
}

function mg_lr_published_product_exists_sql(): string
{
    return "EXISTS (
        SELECT 1
        FROM merchant_storefront_revision_products rp_exists
        INNER JOIN catalog_products cp_exists ON cp_exists.id = rp_exists.catalog_product_id AND cp_exists.status = 'published'
        INNER JOIN catalog_product_versions cpv_exists ON cpv_exists.id = cp_exists.current_version_id AND cpv_exists.version_status = 'published'
        WHERE rp_exists.storefront_revision_id = msr.id AND rp_exists.visibility = 'visible'
        LIMIT 1
    )";
}

function mg_lr_state_counts(PDO $pdo, array $stateLookup): array
{
    $sql = "SELECT UPPER(TRIM(ml.region)) AS region_key, COUNT(DISTINCT ms.id) AS merchant_count
        FROM merchant_locations ml
        INNER JOIN merchant_workspaces mw ON mw.id = ml.workspace_id AND mw.status NOT IN ('suspended','archived')
        INNER JOIN merchant_storefronts ms ON ms.merchant_user_id = mw.merchant_user_id AND ms.status = 'published'
        INNER JOIN users u ON u.id = ms.merchant_user_id AND u.status = 'active'
        INNER JOIN merchant_storefront_states mss ON mss.storefront_id = ms.id AND mss.published_revision_id IS NOT NULL
        INNER JOIN merchant_storefront_revisions msr ON msr.id = mss.published_revision_id AND msr.revision_status = 'published'
        WHERE ml.status = 'active'
          AND UPPER(TRIM(ml.country_code)) = 'US'
          AND NULLIF(TRIM(COALESCE(ml.address_line1,'')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(ml.city,'')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(ml.region,'')), '') IS NOT NULL
          AND " . mg_lr_published_product_exists_sql() . "
        GROUP BY UPPER(TRIM(ml.region))";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $counts = [];
    foreach ($rows as $row) {
        $key = preg_replace('/\s+/u', ' ', strtoupper(trim((string) ($row['region_key'] ?? '')))) ?? '';
        $code = $stateLookup[$key] ?? null;
        if ($code === null) {
            continue;
        }
        $counts[$code] = ($counts[$code] ?? 0) + (int) ($row['merchant_count'] ?? 0);
    }
    return $counts;
}

function mg_lr_state_merchants(PDO $pdo, string $stateCode, string $stateName): array
{
    $stateNameUpper = strtoupper($stateName);
    $productExists = mg_lr_published_product_exists_sql();
    $sql = "SELECT
          ms.public_id AS storefront_public_id,
          ms.slug AS storefront_slug,
          ms.display_name AS storefront_name,
          ms.headline AS storefront_headline,
          ms.description AS storefront_description,
          ms.published_at AS storefront_published_at,
          msr.public_id AS storefront_revision_public_id,
          pp.slug AS profile_slug,
          pp.display_name AS profile_name,
          pp.headline AS profile_headline,
          pp.avatar_url,
          pp.cover_url AS profile_cover_url,
          pp.location_label AS profile_location_label,
          lc.city,
          lc.location_count,
          logo.public_id AS logo_asset_public_id,
          cover.public_id AS cover_asset_public_id,
          (
            SELECT COUNT(DISTINCT cp_count.id)
            FROM merchant_storefront_revision_products rp_count
            INNER JOIN catalog_products cp_count ON cp_count.id = rp_count.catalog_product_id AND cp_count.status = 'published'
            INNER JOIN catalog_product_versions cpv_count ON cpv_count.id = cp_count.current_version_id AND cpv_count.version_status = 'published'
            WHERE rp_count.storefront_revision_id = msr.id AND rp_count.visibility = 'visible'
          ) AS product_count,
          (
            SELECT ca_cover.public_id
            FROM merchant_storefront_revision_products rp_cover
            INNER JOIN catalog_products cp_cover ON cp_cover.id = rp_cover.catalog_product_id AND cp_cover.status = 'published'
            INNER JOIN catalog_product_versions cpv_cover ON cpv_cover.id = cp_cover.current_version_id AND cpv_cover.version_status = 'published'
            INNER JOIN catalog_product_version_assets cpva_cover ON cpva_cover.product_version_id = cpv_cover.id AND cpva_cover.role = 'cover'
            INNER JOIN catalog_assets ca_cover ON ca_cover.id = cpva_cover.asset_id AND ca_cover.status = 'ready'
            WHERE rp_cover.storefront_revision_id = msr.id AND rp_cover.visibility = 'visible'
            ORDER BY rp_cover.is_featured DESC, rp_cover.sort_order ASC, cpva_cover.sort_order ASC, cp_cover.id ASC
            LIMIT 1
          ) AS product_cover_asset_public_id
        FROM merchant_storefronts ms
        INNER JOIN users u ON u.id = ms.merchant_user_id AND u.status = 'active'
        INNER JOIN merchant_workspaces mw ON mw.merchant_user_id = ms.merchant_user_id AND mw.status NOT IN ('suspended','archived')
        INNER JOIN (
            SELECT
              workspace_id,
              SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(TRIM(city), '') ORDER BY is_primary DESC, id ASC SEPARATOR '||'), '||', 1) AS city,
              COUNT(*) AS location_count
            FROM merchant_locations
            WHERE status = 'active'
              AND UPPER(TRIM(country_code)) = 'US'
              AND NULLIF(TRIM(COALESCE(address_line1,'')), '') IS NOT NULL
              AND NULLIF(TRIM(COALESCE(city,'')), '') IS NOT NULL
              AND NULLIF(TRIM(COALESCE(region,'')), '') IS NOT NULL
              AND UPPER(TRIM(region)) IN (?, ?)
            GROUP BY workspace_id
        ) lc ON lc.workspace_id = mw.id
        INNER JOIN merchant_storefront_states mss ON mss.storefront_id = ms.id AND mss.published_revision_id IS NOT NULL
        INNER JOIN merchant_storefront_revisions msr ON msr.id = mss.published_revision_id AND msr.revision_status = 'published'
        LEFT JOIN public_profiles pp ON pp.user_id = ms.merchant_user_id AND pp.status = 'active' AND pp.visibility IN ('public','unlisted')
        LEFT JOIN catalog_assets logo ON logo.id = COALESCE(ms.logo_asset_id, msr.logo_asset_id) AND logo.status = 'ready'
        LEFT JOIN catalog_assets cover ON cover.id = COALESCE(ms.cover_asset_id, msr.cover_asset_id) AND cover.status = 'ready'
        WHERE ms.status = 'published'
          AND {$productExists}
        ORDER BY ms.display_name ASC, ms.id ASC
        LIMIT 120";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$stateCode, $stateNameUpper]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stateCode = mg_lr_clean_state_input($_GET['state'] ?? '', $states, $stateLookup);
$stateName = $stateCode !== null ? $states[$stateCode] : null;
$stateCounts = [];
$merchants = [];
$loadError = null;

try {
    $pdo = mg_db();
    $stateCounts = mg_lr_state_counts($pdo, $stateLookup);
    if ($stateCode !== null && $stateName !== null) {
        $merchants = mg_lr_state_merchants($pdo, $stateCode, $stateName);
    }
} catch (Throwable $error) {
    $loadError = 'Location results are temporarily unavailable.';
}

$page_title = $stateName ? $stateName . ' Microgifter Locations | Microgifter' : 'Location Results | Microgifter';
$page_section = 'location-results';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_manifest = [
    'id' => 'location-results',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'location-results', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
.location-results-page,
.location-results-page *{box-sizing:border-box}
.location-results-page{background:#f8fafc;color:#071225;min-height:100vh}
.lr-hero{position:relative;overflow:hidden;padding:84px 0 52px;border-bottom:1px solid #e2e8f0;background:radial-gradient(circle at 18% 10%,rgba(237,233,254,.72),transparent 34%),radial-gradient(circle at 82% 12%,rgba(219,234,254,.72),transparent 30%),linear-gradient(180deg,#fff,#f8fafc)}
.lr-hero:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.58;background:linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);background-size:72px 72px}
.lr-container{position:relative;z-index:2;width:min(1180px,92%);margin:0 auto}
.lr-kicker{display:inline-flex;align-items:center;gap:8px;min-height:30px;padding:0 12px;border:1px solid #dbe5f1;border-radius:999px;background:#fff;color:#7c3aed;font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase}
.lr-hero-grid{display:grid;grid-template-columns:minmax(0,1.06fr) minmax(300px,.56fr);gap:34px;align-items:end;margin-top:18px}
.lr-hero h1{margin:0;font-size:clamp(42px,6vw,78px);line-height:.92;letter-spacing:-.075em;color:#071225}
.lr-hero p{max-width:640px;margin:20px 0 0;color:#64748b;font-size:17px;line-height:1.6;font-weight:650}
.lr-stat-card{padding:24px;border:1px solid #dbe5f1;border-radius:26px;background:rgba(255,255,255,.86);box-shadow:0 24px 70px rgba(15,23,42,.1);backdrop-filter:blur(14px)}
.lr-stat-card strong{display:block;font-size:46px;line-height:1;letter-spacing:-.06em;color:#071225}
.lr-stat-card span{display:block;margin-top:8px;color:#64748b;font-size:13px;font-weight:850;text-transform:uppercase;letter-spacing:.055em}
.lr-state-strip{display:flex;gap:9px;overflow:auto;padding:20px 0 2px;margin-top:28px;scrollbar-width:thin}
.lr-state-pill{display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 12px;border:1px solid #dbe5f1;border-radius:999px;background:#fff;color:#334155;text-decoration:none;font-size:12px;font-weight:950;white-space:nowrap}
.lr-state-pill.is-active{background:#071225;border-color:#071225;color:#fff}
.lr-state-pill em{display:grid;place-items:center;min-width:22px;height:22px;border-radius:999px;background:#f1f5f9;color:#475569;font-style:normal;font-size:11px}
.lr-state-pill.is-active em{background:rgba(255,255,255,.16);color:#fff}
.lr-results{padding:46px 0 88px}
.lr-results-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:22px}
.lr-results-head h2{margin:0;font-size:clamp(28px,3.6vw,46px);line-height:1;letter-spacing:-.055em}
.lr-results-head p{margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.55}
.lr-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.lr-card{overflow:hidden;border:1px solid #dbe5f1;border-radius:28px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.08)}
.lr-cover{height:164px;background:radial-gradient(circle at 22% 12%,rgba(124,58,237,.28),transparent 28%),radial-gradient(circle at 78% 22%,rgba(32,191,210,.28),transparent 30%),linear-gradient(135deg,#eef2ff,#f8fafc);background-size:cover;background-position:center}
.lr-card-body{position:relative;padding:0 20px 20px}
.lr-avatar{width:72px;height:72px;margin-top:-36px;display:grid;place-items:center;overflow:hidden;border:4px solid #fff;border-radius:22px;background:#071225;color:#fff;box-shadow:0 12px 26px rgba(15,23,42,.14);font-size:20px;font-weight:950;letter-spacing:-.05em}
.lr-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.lr-card h3{margin:14px 0 0;color:#071225;font-size:21px;line-height:1.05;letter-spacing:-.045em}
.lr-card .lr-headline{min-height:42px;margin:8px 0 0;color:#64748b;font-size:13px;line-height:1.5;font-weight:650}
.lr-meta{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 0}
.lr-meta span{display:inline-flex;align-items:center;min-height:28px;padding:0 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:950}
.lr-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}
.lr-card-actions a{min-height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;text-decoration:none;font-size:13px;font-weight:950}
.lr-store-link{background:#7c3aed;color:#fff}
.lr-profile-link{background:#f8fafc;color:#071225;border:1px solid #dbe5f1}
.lr-empty{padding:34px;border:1px solid #dbe5f1;border-radius:28px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.07)}
.lr-empty h2{margin:0;font-size:30px;letter-spacing:-.05em}.lr-empty p{max-width:620px;margin:12px 0 0;color:#64748b;line-height:1.6}.lr-empty a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:18px;padding:0 16px;border-radius:14px;background:#071225;color:#fff;text-decoration:none;font-weight:950}
.lr-alert{padding:16px 18px;border:1px solid #fecaca;border-radius:18px;background:#fff1f2;color:#991b1b;font-weight:850}
@media(max-width:980px){.lr-hero-grid{grid-template-columns:1fr}.lr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.lr-hero{padding:58px 0 34px}.lr-results-head{display:block}.lr-grid{grid-template-columns:1fr}.lr-card-actions{grid-template-columns:1fr}.lr-stat-card strong{font-size:38px}}
</style>

<section class="location-results-page">
  <header class="lr-hero">
    <div class="lr-container">
      <span class="lr-kicker">Microgifter locations</span>
      <div class="lr-hero-grid">
        <div>
          <?php if ($stateName): ?>
            <h1><?= mg_e($stateName) ?> local gifts.</h1>
            <p>Browse merchants with active addresses in <?= mg_e($stateName) ?> and at least one published Microgifter product ready to sell, send, or claim.</p>
          <?php else: ?>
            <h1>Choose a state.</h1>
            <p>Select a state to see local merchants with published Microgifter products and public storefronts.</p>
          <?php endif; ?>
        </div>
        <aside class="lr-stat-card" aria-label="Merchant count">
          <strong><?= mg_e((string)($stateCode ? ($stateCounts[$stateCode] ?? count($merchants)) : array_sum($stateCounts))) ?></strong>
          <span><?= $stateCode ? 'Merchants in state' : 'Merchants with locations' ?></span>
        </aside>
      </div>

      <nav class="lr-state-strip" aria-label="Browse by state">
        <?php foreach ($states as $code => $name): ?>
          <?php $count = (int)($stateCounts[$code] ?? 0); ?>
          <a class="lr-state-pill<?= $code === $stateCode ? ' is-active' : '' ?>" href="/location-results.php?state=<?= rawurlencode($code) ?>" title="<?= mg_e($name) ?> merchants">
            <?= mg_e($code) ?><em><?= $count ?></em>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>

  <main class="lr-results">
    <div class="lr-container">
      <?php if ($loadError): ?>
        <div class="lr-alert" role="alert"><?= mg_e($loadError) ?></div>
      <?php elseif (!$stateCode): ?>
        <section class="lr-empty">
          <h2>No state selected.</h2>
          <p>Use the state selector above or return to the locations map to pick a market.</p>
          <a href="/locations.php">Open locations map</a>
        </section>
      <?php elseif (!$merchants): ?>
        <section class="lr-empty">
          <h2>No published merchants found yet.</h2>
          <p>There are no active <?= mg_e($stateName ?? $stateCode) ?> merchant locations with a published storefront product right now. As merchants publish products and add addresses, they will appear here automatically.</p>
          <a href="/locations.php">Choose another state</a>
        </section>
      <?php else: ?>
        <div class="lr-results-head">
          <div>
            <h2><?= count($merchants) ?> merchant<?= count($merchants) === 1 ? '' : 's' ?> found</h2>
            <p>Showing public storefronts with an active address and visible published products.</p>
          </div>
          <a class="lr-state-pill is-active" href="/locations.php"><?= mg_e($stateName ?? $stateCode) ?> map</a>
        </div>

        <section class="lr-grid" aria-label="<?= mg_e($stateName ?? $stateCode) ?> merchant results">
          <?php foreach ($merchants as $merchant): ?>
            <?php
              $merchantName = trim((string)($merchant['storefront_name'] ?? $merchant['profile_name'] ?? 'Microgifter merchant'));
              $headline = mg_lr_excerpt($merchant['storefront_headline'] ?? $merchant['profile_headline'] ?? $merchant['storefront_description'] ?? '', 126);
              $coverUrl = mg_lr_asset_url($merchant['cover_asset_public_id'] ?? null)
                  ?? mg_lr_asset_url($merchant['product_cover_asset_public_id'] ?? null)
                  ?? mg_lr_public_url($merchant['profile_cover_url'] ?? null);
              $avatarUrl = mg_lr_public_url($merchant['avatar_url'] ?? null)
                  ?? mg_lr_asset_url($merchant['logo_asset_public_id'] ?? null);
              $storeUrl = '/store.php?s=' . rawurlencode((string)($merchant['storefront_slug'] ?? ''));
              $profileUrl = trim((string)($merchant['profile_slug'] ?? '')) !== ''
                  ? '/profile.php?slug=' . rawurlencode((string)$merchant['profile_slug'])
                  : null;
              $productCount = (int)($merchant['product_count'] ?? 0);
              $city = trim((string)($merchant['city'] ?? ''));
              $locationCount = max(1, (int)($merchant['location_count'] ?? 1));
              $locationLabel = $city !== '' ? $city . ', ' . $stateCode : ($stateName ?? $stateCode);
            ?>
            <article class="lr-card">
              <div class="lr-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(7,18,37,.05),rgba(7,18,37,.28)),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
              <div class="lr-card-body">
                <div class="lr-avatar">
                  <?php if ($avatarUrl): ?>
                    <img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image" loading="lazy">
                  <?php else: ?>
                    <?= mg_e(mg_lr_initials($merchantName)) ?>
                  <?php endif; ?>
                </div>
                <h3><?= mg_e($merchantName) ?></h3>
                <p class="lr-headline"><?= mg_e($headline !== '' ? $headline : 'Published local Microgifter products available now.') ?></p>
                <div class="lr-meta">
                  <span><?= mg_e($locationLabel) ?></span>
                  <?php if ($locationCount > 1): ?><span><?= $locationCount ?> locations</span><?php endif; ?>
                  <span><?= $productCount ?> product<?= $productCount === 1 ? '' : 's' ?></span>
                </div>
                <div class="lr-card-actions">
                  <a class="lr-store-link" href="<?= mg_e($storeUrl) ?>">Open Store</a>
                  <?php if ($profileUrl): ?>
                    <a class="lr-profile-link" href="<?= mg_e($profileUrl) ?>">View Profile</a>
                  <?php else: ?>
                    <a class="lr-profile-link" href="<?= mg_e($storeUrl) ?>">Store Details</a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </div>
  </main>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
