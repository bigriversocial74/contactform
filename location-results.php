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
.location-results-page,.location-results-page *{box-sizing:border-box;border-radius:0!important}.location-results-page{min-height:100vh;background:#f8fafc;color:#071225}.lr-results{position:relative;overflow:hidden;padding:0 0 82px;background:radial-gradient(circle at 74% 0,rgba(219,234,254,.5),transparent 32%),radial-gradient(circle at 8% 4%,rgba(237,233,254,.5),transparent 28%),linear-gradient(180deg,#fff,#f8fafc 240px,#f8fafc)}.lr-results:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.38;background:linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);background-size:72px 72px}.lr-container{position:relative;z-index:2;width:100vw;max-width:none;margin:0;padding:0}.lr-layout{display:grid;grid-template-columns:20vw 80vw;gap:0;align-items:start}.lr-state-sidebar{position:sticky;top:64px;align-self:start;margin:0;min-height:calc(100vh - 64px);max-height:calc(100vh - 64px);padding:18px 16px;border:0;border-right:1px solid #dbe5f1;border-bottom:1px solid #dbe5f1;background:rgba(255,255,255,.96);box-shadow:none;backdrop-filter:blur(12px);overflow:hidden}.lr-state-sidebar h2{margin:0;color:#071225;font-size:17px;line-height:1;letter-spacing:-.035em}.lr-state-sidebar p{margin:7px 0 14px;color:#64748b;font-size:11px;line-height:1.4;font-weight:700}.lr-state-list{display:grid;gap:6px;max-height:calc(100vh - 151px);overflow:auto;padding-right:2px}.lr-state-link{min-height:36px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 9px;border:1px solid #e2e8f0;background:#f8fafc;color:#071225;text-decoration:none;font-size:12px;font-weight:850}.lr-state-link:hover{background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9}.lr-state-link.is-active{background:#071225;border-color:#071225;color:#fff}.lr-state-label{display:flex;align-items:center;gap:8px;min-width:0}.lr-state-code{width:27px;height:27px;display:grid;place-items:center;background:#fff;color:#475569;font-size:10px;font-weight:900}.lr-state-link.is-active .lr-state-code{background:rgba(255,255,255,.14);color:#fff}.lr-state-name{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.lr-state-count{min-width:22px;height:22px;display:grid;place-items:center;padding:0 6px;background:#e2e8f0;color:#475569;font-size:10px;font-weight:900}.lr-state-link.is-active .lr-state-count{background:rgba(255,255,255,.14);color:#fff}.lr-results-panel{min-width:0;align-self:start;margin:0;padding:0 clamp(22px,3vw,46px) 0 28px}.lr-state-summary{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,300px);gap:24px;align-items:start;margin:0 0 28px;padding:28px;border:1px solid #dbe5f1;background:rgba(255,255,255,.72);box-shadow:none;backdrop-filter:blur(14px)}.lr-kicker{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border:1px solid #dbe5f1;background:#fff;color:#7c3aed;font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.lr-state-summary h1{max-width:560px;margin:12px 0 0;font-size:clamp(28px,3.2vw,44px);line-height:.98;letter-spacing:-.06em;color:#071225}.lr-state-summary p{max-width:610px;margin:14px 0 0;color:#64748b;font-size:13px;line-height:1.5;font-weight:650}.lr-stat-grid{display:grid;gap:10px}.lr-stat-card{padding:18px;border:1px solid #dbe5f1;background:#fff;box-shadow:none}.lr-stat-card strong{display:block;font-size:30px;line-height:1;letter-spacing:-.05em;color:#071225}.lr-stat-card span{display:block;margin-top:7px;color:#64748b;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.lr-results-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:18px}.lr-results-head h2{margin:0;font-size:clamp(23px,2.7vw,34px);line-height:1;letter-spacing:-.045em}.lr-results-head p{margin:7px 0 0;color:#64748b;font-size:12px;line-height:1.45}.lr-map-link{min-height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0 13px;background:#071225;color:#fff;text-decoration:none;font-size:12px;font-weight:900;white-space:nowrap}.lr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(245px,1fr));gap:18px}.lr-card{overflow:hidden;border:1px solid #dbe5f1;background:#fff;box-shadow:none}.lr-cover{height:145px;background:radial-gradient(circle at 22% 12%,rgba(124,58,237,.24),transparent 28%),radial-gradient(circle at 78% 22%,rgba(32,191,210,.24),transparent 30%),linear-gradient(135deg,#eef2ff,#f8fafc);background-size:cover;background-position:center}.lr-card-body{position:relative;padding:0 16px 16px}.lr-avatar{width:60px;height:60px;margin-top:-30px;display:grid;place-items:center;overflow:hidden;border:3px solid #fff;background:#071225;color:#fff;box-shadow:none;font-size:17px;font-weight:900;letter-spacing:-.04em}.lr-avatar img{width:100%;height:100%;object-fit:cover;display:block}.lr-card h3{margin:12px 0 0;color:#071225;font-size:18px;line-height:1.05;letter-spacing:-.035em}.lr-headline{min-height:38px;margin:7px 0 0;color:#64748b;font-size:12px;line-height:1.45;font-weight:650}.lr-meta{display:flex;flex-wrap:wrap;gap:7px;margin:14px 0 0}.lr-meta span{display:inline-flex;align-items:center;min-height:24px;padding:0 8px;background:#f1f5f9;color:#475569;font-size:10px;font-weight:900}.lr-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px}.lr-card-actions a{min-height:38px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:12px;font-weight:900}.lr-store-link{background:#7c3aed;color:#fff}.lr-profile-link{background:#f8fafc;color:#071225;border:1px solid #dbe5f1}.lr-empty,.lr-alert{padding:28px;border:1px solid #dbe5f1;background:#fff;box-shadow:none}.lr-empty h2{margin:0;font-size:24px;letter-spacing:-.04em}.lr-empty p{max-width:620px;margin:11px 0 0;color:#64748b;font-size:13px;line-height:1.55}.lr-empty a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;margin-top:16px;padding:0 14px;background:#071225;color:#fff;text-decoration:none;font-size:12px;font-weight:900}.lr-alert{color:#991b1b;background:#fff1f2;border-color:#fecaca;font-size:13px;font-weight:800}@media(max-width:1100px){.lr-layout{grid-template-columns:240px minmax(0,1fr)}.lr-results-panel{padding-right:22px}.lr-state-summary{grid-template-columns:1fr}.lr-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:860px){.lr-results{padding:0 0 64px}.lr-layout{grid-template-columns:1fr}.lr-state-sidebar{position:relative;top:auto;min-height:0;max-height:none;border-right:0;border-bottom:1px solid #dbe5f1}.lr-state-list{grid-template-columns:repeat(auto-fill,minmax(86px,1fr));max-height:none}.lr-state-name{display:none}.lr-results-panel{padding:18px 14px 0}.lr-state-summary{padding:20px}.lr-map-link{display:none}}@media(max-width:620px){.lr-results-head{display:block}.lr-card-actions{grid-template-columns:1fr}.lr-stat-card strong{font-size:28px}.lr-state-summary h1{font-size:clamp(30px,11vw,42px)}}
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
