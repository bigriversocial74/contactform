<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$slugIsValid = $slug !== '' && strlen($slug) <= 120 && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) === 1;
$preview = (string)($_GET['preview'] ?? '') === '1';
if ($preview) header('X-Robots-Tag: noindex, nofollow');

$page_title = 'Merchant profile | Microgifter';
$page_section = 'profile';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-profile.css',
    '/assets/css/public-profile-storefront.css',
    '/assets/css/public-profile-engagement.css',
    '/assets/css/public-profile-investment.css',
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
    '/assets/js/public-profile-investment.js',
];
$page_manifest = [
    'id' => 'profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page mg-investment-profile-page',
    'public_header' => ['presentation' => false],
    'onboarding' => ['enabled' => false, 'page' => 'profile', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-profile-shell mg-invest-profile-shell" data-public-profile-page data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>" data-profile-preview="<?= $preview ? '1' : '0' ?>" aria-busy="true">
  <div class="mg-profile-loading" data-profile-loading><div class="mg-invest-loading-cover"></div><div class="mg-invest-wrap"><div class="mg-invest-skeleton-row"><div class="mg-invest-skeleton-avatar"></div><div class="mg-invest-skeleton-copy"><span></span><span></span><span></span></div></div><div class="mg-invest-skeleton-grid"><span></span><span></span><span></span><span></span></div></div></div>
  <div class="mg-profile-error mg-hidden" data-profile-error role="alert"><div class="mg-invest-wrap"><div class="mg-invest-panel mg-profile-empty"><h1 data-profile-error-title>Profile not found</h1><p data-profile-error-message>This profile may be private, still in draft, suspended, blocked, or using a different address.</p><button class="mg-invest-btn mg-invest-btn-gold" type="button" data-profile-retry>Try again</button></div></div></div>
  <div class="mg-profile-content mg-hidden" data-profile-content>
    <div class="mg-profile-preview-banner mg-hidden" data-profile-preview-banner role="status"><div class="mg-invest-wrap"><strong>Owner preview</strong><span>This profile is visible because you are signed in as its owner.</span><a href="/account.php">Edit profile</a></div></div>
    <div class="mg-invest-wrap mg-invest-browser-frame">
      <section class="mg-invest-hero"><div class="mg-invest-cover" data-profile-cover aria-hidden="true"></div></section>
      <section class="mg-invest-profile-top">
        <div class="mg-invest-profile-left"><div class="mg-invest-avatar"><img class="mg-hidden" data-profile-avatar alt=""><span data-profile-avatar-fallback>M</span></div><div class="mg-invest-identity"><div class="mg-invest-title-row"><h1 data-profile-name>Microgifter merchant</h1><span class="mg-invest-verified">✓</span></div><p class="mg-invest-handle" data-profile-headline></p><p class="mg-profile-biography mg-invest-bio" data-profile-biography></p><div class="mg-invest-meta-row"><div class="mg-profile-meta" data-profile-meta></div><a class="mg-invest-meta-link mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Website</a><div class="mg-public-link-list mg-hidden" data-profile-links-section><div data-profile-links></div></div></div><div class="mg-profile-status-row" data-profile-status-row></div></div></div>
        <div class="mg-invest-action-cluster"><button class="mg-invest-btn mg-invest-btn-gold mg-hidden" type="button" data-profile-follow>+ Follow</button><button class="mg-invest-btn mg-invest-btn-dark" type="button">Message</button><button class="mg-invest-btn mg-invest-btn-dark" type="button">Share</button><a class="mg-invest-btn mg-invest-btn-dark mg-hidden" data-profile-edit href="/account.php">Edit</a></div><div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
      </section>
      <section class="mg-invest-summary-grid"><article class="mg-invest-stats-panel"><dl class="mg-invest-social-stats"><div><dt>Followers</dt><dd data-profile-followers>0</dd></div><div><dt>Supporters</dt><dd data-profile-supporters>0</dd></div><div><dt>Products</dt><dd data-profile-products>0</dd></div><div><dt>Market Value</dt><dd data-invest-field="demand_value">—</dd></div></dl></article><article class="mg-invest-card"><strong data-invest-field="floor_price">—</strong><span> Floor price</span></article></section>
      <nav class="mg-invest-tabbar"><a href="#overview">Overview</a><a href="#storefront">Storefront</a><a href="#products">Products</a><a href="#posts">Posts</a><a href="#support">Support</a></nav>
      <div class="mg-invest-layout-grid"><main class="mg-invest-main-column"><section class="mg-invest-panel" id="overview"><h2>Experience economy profile</h2><p data-invest-overview></p></section><section class="mg-invest-panel mg-hidden" data-profile-storefront-section id="storefront"><h2 data-storefront-name>Storefront</h2><p data-storefront-headline data-storefront-tagline></p><a class="mg-hidden" data-storefront-link>Visit storefront</a><span data-storefront-status>Live</span><img class="mg-hidden" data-storefront-logo alt=""><span data-storefront-logo-fallback>S</span><div class="mg-profile-storefront-cover" data-storefront-cover></div><div class="mg-profile-storefront-description" data-storefront-description></div></section><section class="mg-invest-panel" id="products"><h2>Limited experiences</h2><div class="mg-profile-product-grid" data-profile-products-grid></div><div class="mg-profile-empty-inline mg-hidden" data-profile-products-empty>No published products yet.</div><div class="mg-profile-load-more mg-hidden" data-product-pagination><button type="button" data-products-load-more>Load more products</button></div></section><section class="mg-invest-panel mg-hidden" data-profile-posts-section id="posts"><h2>Latest posts</h2><div class="mg-profile-post-list" data-profile-posts-list></div><div class="mg-profile-empty-inline mg-hidden" data-profile-posts-empty>No visible posts.</div><div class="mg-profile-load-more mg-hidden" data-post-pagination><button type="button" data-posts-load-more>Load more posts</button></div></section><section class="mg-invest-panel mg-hidden" data-profile-support-section id="support"><h2>Support this profile</h2><div class="mg-profile-plan-grid" data-profile-plans-grid></div><div class="mg-profile-empty-inline mg-hidden" data-profile-plans-empty>No active memberships.</div><div class="mg-profile-load-more mg-hidden" data-plan-pagination><button type="button" data-plans-load-more>Load more memberships</button></div><aside class="mg-profile-tip-card mg-hidden" data-profile-tip-card></aside></section><div data-profile-sections></div></main><aside class="mg-invest-sidebar"><article class="mg-invest-card">Powered by Microgifter</article></aside></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
