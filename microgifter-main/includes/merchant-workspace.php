<?php
declare(strict_types=1);
$merchantView = $merchantView ?? 'overview';
$merchantNav = [
 'overview'=>['Overview','Workspace health','/merchant.php'],
 'onboarding'=>['Onboarding','Activation steps','/merchant-onboarding.php'],
 'products'=>['Products','Catalog and builder','/merchant-products.php'],
 'storefront'=>['Storefront','Public merchant page','/merchant-storefront.php'],
 'pppm'=>['Orders & PPPM','Items and lifecycle','/merchant-pppm.php'],
 'distribution'=>['Distribution','Programs and inputs','/merchant-distribution.php'],
 'claims'=>['Claims','Verification and redemption','/merchant-claims.php'],
 'media'=>['Media','Assets and processing','/merchant-media.php'],
 'intelligence'=>['Intelligence','Forecasts and analytics','/merchant-intelligence.php'],
 'locations'=>['Locations','Stores and claim scope','/merchant-locations.php'],
 'team'=>['Team','Roles and access','/merchant-team.php'],
 'payments'=>['Payments','Checkout and reconciliation','/merchant-payments.php'],
 'settings'=>['Settings','Business configuration','/merchant-settings.php'],
];
$user = mg_current_user();
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="<?= mg_e($merchantView) ?>">
 <aside class="mg-app-sidebar mg-merchant-sidebar is-text-sidebar" data-app-sidebar data-sidebar-variant="merchant">
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand"><a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a></div>
  <nav class="mg-app-side-nav mg-merchant-nav" aria-label="Merchant workspace"><?php foreach($merchantNav as $key=>$item): ?><a class="<?= $merchantView===$key?'is-active':'' ?>" href="<?= mg_e($item[2]) ?>"><strong><?= mg_e($item[0]) ?></strong><span><?= mg_e($item[1]) ?></span></a><?php endforeach; ?></nav>
 </aside>
 <main class="mg-app-workspace mg-merchant-main">
  <?php if(!$user): ?><section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open your merchant workspace.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
  <?php else: require __DIR__ . '/merchant-view.php'; endif; ?>
 </main>
</section>
