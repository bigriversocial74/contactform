<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = mg_require_auth('/signin.php', '/merchant-canvas.php');
$pdo = mg_db();
$page_title = 'Merchant Store Canvas | Microgifter';
$page_section = 'account-commerce';
$header_mode = 'account';
$page_styles = ['/assets/css/account-commerce.css','/assets/css/merchant-canvas.css','/assets/css/merchant-canvas-rewards.css'];
$page_scripts = ['/assets/js/account-sidebar.js','/assets/js/merchant-canvas.js','/assets/js/merchant-canvas-rewards.js'];
$accountView = 'store-canvas';
if (!mg_user_has_merchant_access($user, $pdo)) {
    http_response_code(403);
    require __DIR__ . '/includes/header.php'; ?>
    <section class="mg-account-page mg-merchant-canvas-page"><div class="mg-account-layout"><?php require __DIR__ . '/includes/account-sidebar.php'; ?><section class="mg-account-shell"><article class="mg-canvas-empty-card"><span class="mg-canvas-eyebrow">Merchant access required</span><h1>Store Canvas is for merchant accounts.</h1><p>Upgrade or sign into a merchant account to view customer avatars, campaign agents, store-session messages, and Store Canvas rewards.</p><a class="mg-btn mg-btn-primary" href="/pricing.php">View merchant packages</a></article></section></div></section>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}
$page_manifest = ['id'=>'merchant-canvas','title'=>$page_title,'section'=>$page_section,'header_mode'=>$header_mode,'styles'=>$page_styles,'scripts'=>$page_scripts,'body_class'=>'mg-merchant-canvas-page','onboarding'=>['enabled'=>false,'page'=>'merchant-canvas','sections'=>[]]];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page mg-store-canvas" data-merchant-canvas>
  <div class="mg-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <section class="mg-account-shell mg-canvas-shell">
      <header class="mg-canvas-hero"><div><span class="mg-canvas-eyebrow">Agent Store Canvas</span><h1>Merchant Store Canvas</h1><p>See customer avatars currently inside your merchant location, open CRM context, send messages, and issue campaign rewards into the customer IN/OUT Box.</p></div><div class="mg-canvas-hero-actions"><a class="mg-btn mg-btn-soft" href="/feed.php">Open Feed</a><button class="mg-btn mg-btn-primary" type="button" data-canvas-refresh>Refresh Canvas</button></div></header>
      <section class="mg-canvas-schema-panel" data-store-schema-panel aria-live="polite"><div class="mg-canvas-schema-summary"><span class="mg-canvas-schema-dot"></span><div><strong data-store-schema-title>Checking Store Canvas schema…</strong><p data-store-schema-message>Verifying Stage 20 tables before loading active customer sessions.</p></div><button class="mg-btn mg-btn-soft" type="button" data-store-schema-refresh>Check schema</button></div><div class="mg-canvas-schema-details" data-store-schema-details hidden></div></section>
      <div class="mg-canvas-grid mg-canvas-grid-full"><section class="mg-canvas-stage" aria-label="Live store canvas"><div class="mg-canvas-stage-head"><div><span class="mg-canvas-eyebrow">Live store</span><h2>Customer avatars</h2></div><span class="mg-canvas-live-pill" data-canvas-live-pill>Polling every few seconds</span></div><div class="mg-canvas-map" data-canvas-map><div class="mg-canvas-agent-node mg-canvas-merchant-node"><span class="mg-canvas-agent-icon">MG</span><strong>Merchant Agent</strong><small>Campaigns · rewards · CRM</small></div><div class="mg-canvas-avatar-layer" data-canvas-customers></div><article class="mg-canvas-empty-state" data-canvas-empty><span>No avatars inside yet</span><p>Add Enter Store buttons to feed posts and customers will appear here when they enter your merchant location.</p></article></div></section></div>
    </section>
  </div>
  <aside class="mg-canvas-crm-drawer" data-canvas-drawer aria-hidden="true"><div class="mg-canvas-drawer-head"><div><span class="mg-canvas-eyebrow">Customer CRM</span><h2 data-drawer-name>Select an avatar</h2></div><button type="button" data-drawer-close aria-label="Close customer CRM drawer">×</button></div><div class="mg-canvas-drawer-body" data-drawer-body><p>Click a customer avatar on the Store Canvas to load CRM details.</p></div><form class="mg-canvas-message-form" data-message-form><label for="mg-canvas-message">Direct message</label><textarea id="mg-canvas-message" name="message" rows="4" maxlength="1000" placeholder="Send a message to this customer’s Messages center…" required disabled></textarea><button class="mg-btn mg-btn-primary" type="submit" disabled data-message-submit>Send Message</button><p class="mg-canvas-form-status" data-message-status role="status"></p></form></aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>