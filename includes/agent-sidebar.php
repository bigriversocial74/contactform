<aside class="mg-app-sidebar mg-agent-side" data-agent-sidebar>
  <div class="mg-app-sidebar-brand mg-agent-sidebar-brand-row">
    <a class="mg-brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
    <div class="mg-agent-sidebar-tools">
      <button type="button" class="mg-agent-tool-button mg-agent-scanner-button" data-scanner-trigger aria-haspopup="dialog" aria-label="Open scanner">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </button>
    </div>
  </div>

  <div class="mg-agent-side-tabs" role="tablist" aria-label="Agent sidebar sections">
    <button type="button" role="tab" aria-selected="false" aria-controls="mg-agent-side-agents" data-agent-side-tab="agents">Agents</button>
    <button class="is-active" type="button" role="tab" aria-selected="true" aria-controls="mg-agent-side-merchant" data-agent-side-tab="merchant">Merchant info</button>
  </div>

  <section class="mg-agent-side-panel" id="mg-agent-side-agents" role="tabpanel" data-agent-side-panel="agents" hidden>
    <div class="mg-agent-side-panel-head">
      <h3>Saved agents</h3>
      <span>Manage active workspaces</span>
    </div>
    <div class="mg-app-side-nav" data-saved-agent-list></div>
  </section>

  <section class="mg-agent-side-panel is-active" id="mg-agent-side-merchant" role="tabpanel" data-agent-side-panel="merchant">
    <div class="mg-agent-side-panel-head">
      <h3>Merchant info</h3>
      <span>Business workspace</span>
    </div>
    <nav class="mg-merchant-side-nav" aria-label="Merchant information">
      <a class="is-active" href="/account.php">Business profile</a>
      <a href="/merchant-locations.php">Locations</a>
      <a href="/merchant-products.php">Products &amp; offers</a>
      <a href="/merchant-pppm.php">Orders &amp; redemptions</a>
      <a href="/merchant-settings.php">Merchant settings</a>
      <div class="mg-merchant-side-actions">
        <a class="mg-merchant-side-action" href="/messages.php">Messages</a>
        <a class="mg-merchant-side-action is-primary" href="/build.php">Create gift</a>
      </div>
    </nav>
  </section>

  <footer class="mg-agent-side-footer">
    <button type="button" class="mg-agent-side-scanner-launch" data-scanner-trigger aria-haspopup="dialog">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span><strong>Scanner</strong><small>Scan QR codes, barcodes, and claim codes</small></span>
    </button>
  </footer>
</aside>

<div class="mg-agent-tool-modal" data-agent-model-modal aria-hidden="true">
  <div class="mg-agent-tool-backdrop" data-agent-tool-close></div>
  <section class="mg-agent-tool-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-model-dialog-title">
    <header><div><span>Agent brain</span><h2 id="mg-model-dialog-title">Choose a model</h2></div><button type="button" data-agent-tool-close aria-label="Close model selector">×</button></header>
    <div class="mg-model-options" data-model-options></div>
    <footer><span>Claude is the platform default.</span><button type="button" class="mg-btn mg-btn-primary" data-model-save>Use selected model</button></footer>
  </section>
</div>

<div class="mg-agent-tool-modal mg-scanner-modal" data-scanner-modal aria-hidden="true">
  <div class="mg-agent-tool-backdrop" data-scanner-close></div>
  <section class="mg-agent-tool-dialog mg-scanner-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-scanner-title">
    <header><div><span>Hardware tools</span><h2 id="mg-scanner-title">Scanner</h2></div><button type="button" data-scanner-close aria-label="Close scanner">×</button></header>
    <div class="mg-scanner-body">
      <div class="mg-scanner-viewfinder" data-scanner-viewfinder>
        <video data-scanner-video playsinline muted></video>
        <div class="mg-scanner-frame" aria-hidden="true"></div>
        <p data-scanner-status>Camera is off.</p>
      </div>
      <div class="mg-scanner-settings">
        <label>Scanner mode<select data-scanner-mode><option value="qr">QR code</option><option value="barcode">Barcode</option><option value="document">Document</option><option value="hardware">Hardware scanner</option></select></label>
        <label>Camera<select data-scanner-camera><option value="environment">Rear camera</option><option value="user">Front camera</option></select></label>
        <label>Hardware integration<select><option>None configured</option><option disabled>USB scanner — coming later</option><option disabled>Bluetooth scanner — coming later</option><option disabled>POS scanner — coming later</option></select></label>
        <div class="mg-scanner-auto-claim">
          <label class="mg-scanner-toggle"><input type="checkbox" data-scanner-auto-claim><span>Auto-claim after a matching scan</span></label>
          <label>Claim code<input type="text" data-scanner-claim-code placeholder="Enter claim code for auto-claim" autocomplete="off"></label>
          <p>This is the interface outline only. Verification and redemption are not connected yet.</p>
        </div>
        <div class="mg-scanner-actions"><button type="button" data-scanner-stop>Stop</button><button type="button" class="is-primary" data-scanner-start>Start scanner</button></div>
      </div>
    </div>
  </section>
</div>