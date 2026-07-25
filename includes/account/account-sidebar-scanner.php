<?php
declare(strict_types=1);

if (empty($can_merchant_nav)) {
    return;
}
?>
<style>
.mg-scanner-mobile-primary{display:none!important}
.mg-account-sidebar-scanner-button{display:none!important}
.mg-scanner-confirm-card{display:grid!important;gap:8px!important;margin:10px 0!important;padding:12px!important;border:1px solid #dbeafe!important;border-radius:16px!important;background:#f8fbff!important}
.mg-scanner-confirm-row{display:flex!important;justify-content:space-between!important;gap:12px!important;font-size:12px!important;color:#334155!important}
.mg-scanner-confirm-row strong{color:#0f172a!important;text-align:right!important}
.mg-scanner-receipt-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;margin-top:10px!important;padding:9px 12px!important;border-radius:999px!important;background:#0f172a!important;color:#fff!important;text-decoration:none!important;font-weight:900!important;font-size:12px!important}
.mg-scanner-manual-hidden{display:none!important}
html body.mg-app-page .mg-scanner-modal .mg-scanner-settings{gap:14px!important}
html body.mg-app-page .mg-scanner-modal .mg-scanner-location-note{margin:0!important}
html body.mg-app-page .mg-scanner-modal .mg-scanner-actions{position:static!important;bottom:auto!important;display:grid!important;grid-template-columns:1fr!important;padding:0!important;background:transparent!important;box-shadow:none!important}
html body.mg-app-page .mg-scanner-modal .mg-scanner-actions button{width:100%!important;min-height:54px!important;border-radius:16px!important;font-size:14px!important;font-weight:950!important}
html body.mg-app-page .mg-scanner-modal .mg-scanner-actions button:disabled{opacity:.55!important;cursor:not-allowed!important;background:#e5e7eb!important;color:#64748b!important;border-color:#cbd5e1!important}
@media(max-width:980px){
  html body.mg-app-page.mg-section-account .mg-account-sidebar-scanner-button{display:flex!important;width:100%!important;min-height:52px!important;align-items:center!important;gap:10px!important;margin-top:10px!important;padding:10px 12px!important;border:1px solid #bfdbfe!important;border-radius:14px!important;background:#eff6ff!important;color:#1455d9!important;text-align:left!important}
  html body.mg-app-page.mg-section-account .mg-account-sidebar-scanner-button span{display:grid!important;width:28px!important;height:28px!important;place-items:center!important;border-radius:9px!important;background:#dbeafe!important;font-size:18px!important}
  html body.mg-app-page.mg-section-account .mg-account-sidebar-scanner-button strong{font-size:13px!important;font-weight:950!important;color:#0f3ea8!important}
}
</style>
<section class="mg-agent-tool-modal mg-scanner-modal" data-scanner-modal data-scanner-api="/api/merchant/scanner-claim-trust.php" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mg-account-scanner-title">
  <div class="mg-agent-tool-backdrop" data-scanner-close></div>
  <div class="mg-agent-tool-dialog mg-scanner-dialog">
    <header>
      <div>
        <span>Merchant scanner</span>
        <h2 id="mg-account-scanner-title">Scan and redeem voucher</h2>
      </div>
      <button type="button" data-scanner-close aria-label="Close scanner">x</button>
    </header>
    <div class="mg-scanner-body">
      <div class="mg-scanner-viewfinder" data-scanner-camera data-camera-facing="user">
        <video data-scanner-video muted playsinline></video>
        <div class="mg-scanner-frame" aria-hidden="true"></div>
        <p data-scanner-status>Select a scanner location. Camera starts after permission is approved.</p>
      </div>
      <div class="mg-scanner-settings">
        <label>Merchant location<select data-scanner-location><option value="">Loading scanner locations…</option></select></label>
        <div class="mg-scanner-location-note" data-scanner-location-note>Choose a location with an active claim code.</div>
        <input class="mg-scanner-manual-hidden" data-scanner-scan-value type="text" autocomplete="off" tabindex="-1" aria-hidden="true">
        <input class="mg-scanner-manual-hidden" type="checkbox" data-scanner-auto-claim checked tabindex="-1" aria-hidden="true">
        <input class="mg-scanner-manual-hidden" type="checkbox" data-scanner-two-step checked tabindex="-1" aria-hidden="true">
        <div class="mg-scanner-result" data-scanner-result hidden></div>
        <div class="mg-scanner-confirm" data-scanner-confirm hidden>
          <strong>Confirm redemption</strong>
          <span data-scanner-confirm-copy>Gift verified. Confirm to permanently redeem it.</span>
          <div data-scanner-confirm-details></div>
          <div>
            <button type="button" data-scanner-cancel-confirm>Cancel</button>
            <button class="is-primary" type="button" data-scanner-confirm-claim>Redeem</button>
          </div>
        </div>
        <div class="mg-scanner-actions">
          <button class="is-primary" type="button" data-scanner-start disabled>Scan now</button>
        </div>
      </div>
    </div>
  </div>
</section>
<script src="/assets/js/merchant-scanner-cleanup.js"></script>
<script>
(function(document){
  'use strict';

  var observer = null;

  function removeCanvasShortcut(){
    document.querySelectorAll('[data-scanner-mobile-primary]').forEach(function(button){
      button.remove();
    });
  }

  function mountScannerModal(){
    var modal = document.querySelector('[data-scanner-modal]');
    if (modal && document.body && modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }
  }

  function installSidebarTrigger(){
    var nav = document.querySelector('[data-personal-agent-chat-sidebar] .mg-personal-chat-actions');
    if (!nav || nav.querySelector('[data-account-sidebar-scanner]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-personal-chat-action mg-account-sidebar-scanner-button';
    button.setAttribute('data-account-sidebar-scanner', '');
    button.setAttribute('data-scanner-trigger', '');
    button.setAttribute('aria-label', 'Open merchant QR scanner');
    button.innerHTML = '<span aria-hidden="true">⌗</span><strong>Scan QR Code</strong>';
    nav.appendChild(button);
  }

  function install(){
    mountScannerModal();
    installSidebarTrigger();
    removeCanvasShortcut();
    window.setTimeout(removeCanvasShortcut, 0);
    window.setTimeout(removeCanvasShortcut, 250);
    if (document.body && !observer) {
      observer = new MutationObserver(function(){
        installSidebarTrigger();
        removeCanvasShortcut();
      });
      observer.observe(document.body, {childList:true, subtree:true});
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, {once:true});
  } else {
    install();
  }
})(document);
</script>
