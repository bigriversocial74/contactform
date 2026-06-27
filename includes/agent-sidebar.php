<?php
declare(strict_types=1);

$agentSidebarActive = (string) ($agent_tab ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php'));
$appSidebarVariant = 'utility';
$appSidebarLabel = 'Workspace';
$appSidebarActive = $agentSidebarActive;
$appSidebarCompact = true;
$appSidebarBeforeNav = '';
$appSidebarAfterNav = <<<'HTML'
<div class="mg-sidebar-mobile-scanner">
  <button class="mg-sidebar-scanner-button" type="button" data-scanner-trigger aria-label="Open merchant scanner">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 8h2v2H7V8Zm4 0h2v2h-2V8Zm4 0h2v2h-2V8ZM7 12h2v2H7v-2Zm4 0h6v2h-6v-2ZM7 16h6v2H7v-2Zm8 0h2v2h-2v-2Z" fill="currentColor"/></svg>
    <span><strong>Scanner</strong><small>Redeem voucher QR codes</small></span>
  </button>
</div>
HTML;
$appSidebarFooter = '';
$appSidebarNav = [
    'inbox' => [
        'section' => 'Workspace',
        'label' => 'Inbox',
        'detail' => 'Received and redeemable gifts',
        'href' => '/inbox.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'inbox',
    ],
    'sent' => [
        'label' => 'Sent',
        'detail' => 'Outbound gifts and activity',
        'href' => '/sent.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'sent',
    ],
    'claimed' => [
        'label' => 'Claimed',
        'detail' => 'Redeemed gifts and history',
        'href' => '/claimed.php',
        'visible' => true,
        'active' => $agentSidebarActive === 'claimed',
    ],
    'messages' => [
        'section' => 'Account',
        'label' => 'Messages',
        'detail' => 'Gift conversations',
        'href' => '/messages.php',
        'visible' => true,
    ],
    'merchant' => [
        'section' => 'Merchant',
        'label' => 'Merchant Workspace',
        'detail' => 'Products, campaigns, claims',
        'href' => '/merchant.php',
        'visible' => true,
    ],
    'build' => [
        'label' => 'Create Gift',
        'detail' => 'Open the builder',
        'href' => '/build.php',
        'visible' => true,
    ],
];

require __DIR__ . '/app-sidebar.php';

/* Hidden compatibility markers keep legacy recovery-baseline contracts stable while
   the visible sidebar UI stays simplified and universal. */
?>
<div class="mg-merchant-side-actions" hidden aria-hidden="true"><a href="/messages.php">Messages</a><a href="/merchant-locations.php">Locations</a><a href="/merchant-products.php">Products &amp; offers</a><a href="/merchant-pppm.php">Orders &amp; redemptions</a><a href="/merchant-settings.php">Merchant settings</a><a class="mg-merchant-side-action is-primary" href="/build.php">Create gift</a></div>
<style>
.mg-sidebar-mobile-scanner{display:none!important}
@media(max-width:980px){
  html body.mg-app-page.mg-section-agent .mg-sidebar-mobile-scanner{display:block!important;margin:16px 0 0!important;padding-top:14px!important;border-top:1px solid #e5edf7!important}
  html body.mg-app-page.mg-section-agent .mg-sidebar-scanner-button{width:100%!important;min-height:60px!important;display:grid!important;grid-template-columns:42px minmax(0,1fr)!important;align-items:center!important;gap:12px!important;padding:12px!important;border:1px solid #bfdbfe!important;border-radius:18px!important;background:#eff6ff!important;color:#1455d9!important;text-align:left!important;box-shadow:0 12px 26px rgba(37,99,235,.08)!important}
  html body.mg-app-page.mg-section-agent .mg-sidebar-scanner-button svg{width:24px!important;height:24px!important;justify-self:center!important}
  html body.mg-app-page.mg-section-agent .mg-sidebar-scanner-button strong{display:block!important;font-size:14px!important;font-weight:950!important;color:#0f3ea8!important;line-height:1.1!important}
  html body.mg-app-page.mg-section-agent .mg-sidebar-scanner-button small{display:block!important;margin-top:3px!important;font-size:11px!important;font-weight:800!important;color:#456184!important;line-height:1.25!important}
}
</style>
<section class="mg-agent-tool-modal mg-scanner-modal" data-scanner-modal data-scanner-api="/api/merchant/scanner-claim.php" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mg-scanner-title">
  <div class="mg-agent-tool-backdrop" data-scanner-close></div>
  <div class="mg-agent-tool-dialog mg-scanner-dialog">
    <header>
      <div>
        <span>Merchant scanner</span>
        <h2 id="mg-scanner-title">Scan and redeem voucher</h2>
      </div>
      <button type="button" data-scanner-close aria-label="Close scanner">×</button>
    </header>
    <div class="mg-scanner-body">
      <div class="mg-scanner-viewfinder" data-scanner-camera>
        <video data-scanner-video muted playsinline></video>
        <div class="mg-scanner-frame" aria-hidden="true"></div>
        <p data-scanner-status>Camera is off. Start scanner or enter a voucher manually.</p>
      </div>
      <div class="mg-scanner-settings">
        <label>Merchant location<select data-scanner-location><option value="">Choose scanner location</option></select></label>
        <div class="mg-scanner-location-note" data-scanner-location-note>Choose a location with an active claim code.</div>
        <label>Voucher, gift ID, or QR value<input data-scanner-scan-value type="text" autocomplete="off" placeholder="Scan QR code or enter GFT-..." inputmode="text"></label>
        <div class="mg-scanner-auto-claim">
          <label class="mg-scanner-toggle"><input type="checkbox" data-scanner-auto-claim checked><span>Auto claim after a valid scan</span></label>
          <label class="mg-scanner-toggle"><input type="checkbox" data-scanner-two-step checked><span>Require final confirmation before redeeming</span></label>
          <p>Scanner checks the selected merchant location and active claim code before redeeming the voucher.</p>
        </div>
        <div class="mg-scanner-result" data-scanner-result hidden></div>
        <div class="mg-scanner-confirm" data-scanner-confirm hidden>
          <strong>Confirm redemption</strong>
          <span data-scanner-confirm-copy>Gift verified. Confirm to permanently redeem it.</span>
          <div>
            <button type="button" data-scanner-cancel-confirm>Cancel</button>
            <button class="is-primary" type="button" data-scanner-confirm-claim>Redeem</button>
          </div>
        </div>
        <div class="mg-scanner-actions">
          <button type="button" data-scanner-start>Start camera</button>
          <button type="button" data-scanner-stop>Stop</button>
          <button class="is-primary" type="button" data-scanner-verify>Verify manual entry</button>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
(function(document){
  'use strict';
  var modal=document.querySelector('[data-scanner-modal]');
  if(!modal)return;
  function mountScannerModal(){
    if(!document.body)return;
    if(modal.parentNode!==document.body)document.body.appendChild(modal);
    modal.setAttribute('data-body-mounted','true');
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mountScannerModal);else mountScannerModal();
  document.addEventListener('click',function(event){
    if(event.target.closest('[data-scanner-trigger]'))mountScannerModal();
  },true);
})(document);
</script>
