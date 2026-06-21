<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/location-data.php';
require_once __DIR__ . '/api/db.php';

function mg_lr_asset_url(mixed $publicId): ?string
{
    $publicId = trim((string) $publicId);
    return $publicId !== '' ? '/api/public/media.php?asset=' . rawurlencode($publicId) : null;
}

function mg_lr_public_url(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value !== '' && preg_match('#^(?:https?://|/)#i', $value) === 1 ? $value : null;
}

function mg_lr_excerpt(mixed $value, int $max = 145): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    if ($text === '' || mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

function mg_lr_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = mb_substr((string) ($parts[0] ?? 'M'), 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return strtoupper($first . $last);
}

function mg_lr_state_merchants(PDO $pdo, string $stateCode, string $stateName): array
{
    $productExists = mg_location_published_product_exists_sql();
    $sql = "SELECT
          ms.slug AS storefront_slug,
          ms.display_name AS storefront_name,
          ms.headline AS storefront_headline,
          ms.description AS storefront_description,
          pp.slug AS profile_slug,
          pp.display_name AS profile_name,
          pp.headline AS profile_headline,
          pp.avatar_url,
          pp.cover_url AS profile_cover_url,
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
          SELECT workspace_id,
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
    $stmt->execute([$stateCode, strtoupper($stateName)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$states = mg_location_states();
$stateCode = mg_location_normalize_state($_GET['state'] ?? '') ?? 'AZ';
$stateName = $states[$stateCode] ?? 'Arizona';
$stateCounts = array_fill_keys(array_keys($states), 0);
$merchants = [];
$loadError = null;

try {
    $pdo = mg_db();
    $stateCounts = mg_location_merchant_state_counts($pdo);
    $merchants = mg_lr_state_merchants($pdo, $stateCode, $stateName);
} catch (Throwable) {
    $loadError = 'Location results are temporarily unavailable.';
}

$currentCount = (int) ($stateCounts[$stateCode] ?? count($merchants));
$totalPublishedStates = count(array_filter($stateCounts, static fn ($count) => (int) $count > 0));

$page_title = $stateName . ' Microgifter Locations | Microgifter';
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
    'public_header' => ['presentation' => false, 'links' => []],
    'onboarding' => ['enabled' => false, 'page' => 'location-results', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
.location-results-page,.location-results-page *{box-sizing:border-box}.location-results-page{min-height:100vh;background:#f8fafc;color:#071225}.lr-results{position:relative;overflow:hidden;padding:46px 0 90px;background:radial-gradient(circle at 74% 0,rgba(219,234,254,.62),transparent 34%),radial-gradient(circle at 8% 4%,rgba(237,233,254,.66),transparent 30%),linear-gradient(180deg,#fff,#f8fafc 260px,#f8fafc)}.lr-results:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.48;background:linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);background-size:72px 72px}.lr-container{position:relative;z-index:2;width:100%;max-width:none;margin:0;padding:0 clamp(22px,3vw,48px)}.lr-layout{display:grid;grid-template-columns:minmax(250px,25vw) minmax(0,1fr);gap:clamp(26px,3vw,44px);align-items:start}.lr-state-sidebar{position:sticky;top:92px;align-self:start;margin-top:0;max-height:calc(100vh - 112px);padding:18px;border:1px solid #dbe5f1;border-radius:24px;background:rgba(255,255,255,.94);box-shadow:0 22px 60px rgba(15,23,42,.08);backdrop-filter:blur(14px);overflow:hidden}.lr-state-sidebar h2{margin:0;color:#071225;font-size:20px;line-height:1;letter-spacing:-.045em}.lr-state-sidebar p{margin:8px 0 16px;color:#64748b;font-size:12px;line-height:1.45;font-weight:750}.lr-state-list{display:grid;gap:8px;max-height:calc(100vh - 218px);overflow:auto;padding-right:3px}.lr-state-link{min-height:42px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 11px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;color:#071225;text-decoration:none;font-size:13px;font-weight:950}.lr-state-link:hover{background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9}.lr-state-link.is-active{background:#071225;border-color:#071225;color:#fff}.lr-state-label{display:flex;align-items:center;gap:9px;min-width:0}.lr-state-code{width:30px;height:30px;display:grid;place-items:center;border-radius:10px;background:#fff;color:#475569;font-size:11px;font-weight:950}.lr-state-link.is-active .lr-state-code{background:rgba(255,255,255,.14);color:#fff}.lr-state-name{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.lr-state-count{min-width:25px;height:25px;display:grid;place-items:center;padding:0 7px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:11px;font-weight:950}.lr-state-link.is-active .lr-state-count{background:rgba(255,255,255,.14);color:#fff}.lr-results-panel{min-width:0;align-self:start;margin-top:0}.lr-state-summary{display:grid;grid-template-columns:minmax(0,1fr) minmax(245px,320px);gap:28px;align-items:start;margin:0 0 34px;padding:34px;border:1px solid #dbe5f1;border-radius:34px;background:rgba(255,255,255,.76);box-shadow:0 26px 80px rgba(15,23,42,.08);backdrop-filter:blur(16px)}.lr-kicker{display:inline-flex;align-items:center;min-height:30px;padding:0 12px;border:1px solid #dbe5f1;border-radius:999px;background:#fff;color:#7c3aed;font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase}.lr-state-summary h1{max-width:660px;margin:14px 0 0;font-size:clamp(34px,4.2vw,56px);line-height:.96;letter-spacing:-.07em;color:#071225}.lr-state-summary p{max-width:680px;margin:16px 0 0;color:#64748b;font-size:16px;line-height:1.6;font-weight:700}.lr-stat-grid{display:grid;gap:12px}.lr-stat-card{padding:22px;border:1px solid #dbe5f1;border-radius:24px;background:#fff;box-shadow:0 18px 44px rgba(15,23,42,.06)}.lr-stat-card strong{display:block;font-size:40px;line-height:1;letter-spacing:-.06em;color:#071225}.lr-stat-card span{display:block;margin-top:8px;color:#64748b;font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.07em}.lr-results-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:20px}.lr-results-head h2{margin:0;font-size:clamp(28px,3.4vw,44px);line-height:1;letter-spacing:-.055em}.lr-results-head p{margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.55}.lr-map-link{min-height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 16px;border-radius:999px;background:#071225;color:#fff;text-decoration:none;font-size:13px;font-weight:950;white-space:nowrap}.lr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px}.lr-card{overflow:hidden;border:1px solid #dbe5f1;border-radius:28px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.08)}.lr-cover{height:164px;background:radial-gradient(circle at 22% 12%,rgba(124,58,237,.28),transparent 28%),radial-gradient(circle at 78% 22%,rgba(32,191,210,.28),transparent 30%),linear-gradient(135deg,#eef2ff,#f8fafc);background-size:cover;background-position:center}.lr-card-body{position:relative;padding:0 20px 20px}.lr-avatar{width:72px;height:72px;margin-top:-36px;display:grid;place-items:center;overflow:hidden;border:4px solid #fff;border-radius:22px;background:#071225;color:#fff;box-shadow:0 12px 26px rgba(15,23,42,.14);font-size:20px;font-weight:950;letter-spacing:-.05em}.lr-avatar img{width:100%;height:100%;object-fit:cover;display:block}.lr-card h3{margin:14px 0 0;color:#071225;font-size:21px;line-height:1.05;letter-spacing:-.045em}.lr-headline{min-height:42px;margin:8px 0 0;color:#64748b;font-size:13px;line-height:1.5;font-weight:650}.lr-meta{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 0}.lr-meta span{display:inline-flex;align-items:center;min-height:28px;padding:0 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:950}.lr-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.lr-card-actions a{min-height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;text-decoration:none;font-size:13px;font-weight:950}.lr-store-link{background:#7c3aed;color:#fff}.lr-profile-link{background:#f8fafc;color:#071225;border:1px solid #dbe5f1}.lr-empty,.lr-alert{padding:34px;border:1px solid #dbe5f1;border-radius:28px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.07)}.lr-empty h2{margin:0;font-size:30px;letter-spacing:-.05em}.lr-empty p{max-width:620px;margin:12px 0 0;color:#64748b;line-height:1.6}.lr-empty a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:18px;padding:0 16px;border-radius:14px;background:#071225;color:#fff;text-decoration:none;font-weight:950}.lr-alert{color:#991b1b;background:#fff1f2;border-color:#fecaca;font-weight:850}@media(max-width:980px){.lr-results{padding-top:28px}.lr-container{padding:0 clamp(14px,4vw,28px)}.lr-layout{grid-template-columns:1fr}.lr-state-sidebar{position:relative;top:auto;max-height:none}.lr-state-list{grid-template-columns:repeat(auto-fill,minmax(118px,1fr));max-height:none}.lr-state-name{display:none}.lr-state-summary{grid-template-columns:1fr;padding:28px}.lr-map-link{display:none}}@media(max-width:680px){.lr-results-head{display:block}.lr-card-actions{grid-template-columns:1fr}.lr-stat-card strong{font-size:36px}.lr-state-summary h1{font-size:clamp(34px,12vw,50px)}}
</style>

<section class="location-results-page">
  <main class="lr-results">
    <div class="lr-container lr-layout">
      <aside class="lr-state-sidebar" aria-label="Browse states">
        <h2>States</h2>
        <p>Select a state to filter local merchant results.</p>
        <nav class="lr-state-list">
          <?php foreach ($states as $code => $name): ?>
            <?php $count = (int) ($stateCounts[$code] ?? 0); ?>
            <a class="lr-state-link<?= $code === $stateCode ? ' is-active' : '' ?>" href="/location-results.php?state=<?= rawurlencode($code) ?>" title="<?= mg_e($name) ?> merchants">
              <span class="lr-state-label"><span class="lr-state-code"><?= mg_e($code) ?></span><span class="lr-state-name"><?= mg_e($name) ?></span></span>
              <span class="lr-state-count"><?= $count ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </aside>

      <section class="lr-results-panel">
        <header class="lr-state-summary">
          <div>
            <span class="lr-kicker">Microgifter locations</span>
            <h1><?= mg_e($stateName) ?> local gifts.</h1>
            <p>Browse merchants with active addresses in <?= mg_e($stateName) ?> and at least one published Microgifter product ready to sell, send, or claim.</p>
          </div>
          <aside class="lr-stat-grid" aria-label="State location stats">
            <div class="lr-stat-card">
              <strong><?= mg_e((string) $currentCount) ?></strong>
              <span>Merchants in state</span>
            </div>
            <div class="lr-stat-card">
              <strong><?= mg_e((string) $totalPublishedStates) ?></strong>
              <span>Active states</span>
            </div>
          </aside>
        </header>

        <?php if ($loadError): ?>
          <div class="lr-alert" role="alert"><?= mg_e($loadError) ?></div>
        <?php elseif (!$merchants): ?>
          <section class="lr-empty">
            <h2>No published merchants found yet.</h2>
            <p>There are no active <?= mg_e($stateName) ?> merchant locations with a published storefront product right now. As merchants publish products and add addresses, they will appear here automatically.</p>
            <a href="/locations.php">Open locations map</a>
          </section>
        <?php else: ?>
          <div class="lr-results-head">
            <div>
              <h2><?= count($merchants) ?> merchant<?= count($merchants) === 1 ? '' : 's' ?> found</h2>
              <p>Showing public storefronts with an active address and visible published products.</p>
            </div>
            <a class="lr-map-link" href="/locations.php"><?= mg_e($stateName) ?> map</a>
          </div>

          <section class="lr-grid" aria-label="<?= mg_e($stateName) ?> merchant results">
            <?php foreach ($merchants as $merchant): ?>
              <?php
                $merchantName = trim((string)($merchant['storefront_name'] ?? $merchant['profile_name'] ?? 'Microgifter merchant'));
                $headline = mg_lr_excerpt($merchant['storefront_headline'] ?? $merchant['profile_headline'] ?? $merchant['storefront_description'] ?? '', 126);
                $coverUrl = mg_lr_asset_url($merchant['cover_asset_public_id'] ?? null) ?? mg_lr_asset_url($merchant['product_cover_asset_public_id'] ?? null) ?? mg_lr_public_url($merchant['profile_cover_url'] ?? null);
                $avatarUrl = mg_lr_public_url($merchant['avatar_url'] ?? null) ?? mg_lr_asset_url($merchant['logo_asset_public_id'] ?? null);
                $storeUrl = '/store.php?s=' . rawurlencode((string)($merchant['storefront_slug'] ?? ''));
                $profileUrl = trim((string)($merchant['profile_slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$merchant['profile_slug']) : null;
                $productCount = (int)($merchant['product_count'] ?? 0);
                $city = trim((string)($merchant['city'] ?? ''));
                $locationCount = max(1, (int)($merchant['location_count'] ?? 1));
                $locationLabel = $city !== '' ? $city . ', ' . $stateCode : $stateName;
              ?>
              <article class="lr-card">
                <div class="lr-cover"<?= $coverUrl ? ' style="background-image:linear-gradient(180deg,rgba(7,18,37,.05),rgba(7,18,37,.28)),url(' . mg_e($coverUrl) . ')"' : '' ?>></div>
                <div class="lr-card-body">
                  <div class="lr-avatar">
                    <?php if ($avatarUrl): ?><img src="<?= mg_e($avatarUrl) ?>" alt="<?= mg_e($merchantName) ?> profile image" loading="lazy"><?php else: ?><?= mg_e(mg_lr_initials($merchantName)) ?><?php endif; ?>
                  </div>
                  <h3><?= mg_e($merchantName) ?></h3>
                  <p class="lr-headline"><?= mg_e($headline !== '' ? $headline : 'Published local Microgifter products available now.') ?></p>
                  <div class="lr-meta"><span><?= mg_e($locationLabel) ?></span><?php if ($locationCount > 1): ?><span><?= $locationCount ?> locations</span><?php endif; ?><span><?= $productCount ?> product<?= $productCount === 1 ? '' : 's' ?></span></div>
                  <div class="lr-card-actions"><a class="lr-store-link" href="<?= mg_e($storeUrl) ?>">Open Store</a><?php if ($profileUrl): ?><a class="lr-profile-link" href="<?= mg_e($profileUrl) ?>">View Profile</a><?php else: ?><a class="lr-profile-link" href="<?= mg_e($storeUrl) ?>">Store Details</a><?php endif; ?></div>
                </div>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
      </section>
    </div>
  </main>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
