<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-account-pane mg-homeserver-account is-active" data-account-pane="homeserver" data-homeserver-account>
  <div class="mg-app-panel-head mg-homeserver-head">
    <div>
      <span class="mg-homeserver-kicker">Independent Microgifter provider connection</span>
      <h2>HomeServer Connections</h2>
      <p>Connect an independently licensed HomeServer to Microgifter, review its signed synchronization scopes, and control exactly which merchant data and campaign actions it may use.</p>
    </div>
    <button id="create-sync-code" class="mg-btn mg-btn-primary" type="button" data-homeserver-create-code>Create Sync Code</button>
  </div>

  <div class="mg-app-panel-body mg-homeserver-body">
    <div class="mg-homeserver-message" data-homeserver-message hidden></div>

    <section class="mg-homeserver-code-panel" data-homeserver-code-panel hidden aria-live="polite">
      <div>
        <span>One-time Microgifter Sync Code</span>
        <strong class="mg-homeserver-code" data-homeserver-code></strong>
        <small data-homeserver-code-expiry></small>
      </div>
      <div class="mg-homeserver-code-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-homeserver-copy-code>Copy code</button>
        <button class="mg-btn mg-btn-ghost" type="button" data-homeserver-hide-code>Hide</button>
      </div>
      <p>Enter this code in the HomeServer Control Center to authorize the Microgifter connection. It does not activate the HomeServer software license and cannot authorize software updates.</p>
    </section>

    <div class="mg-homeserver-grid">
      <article class="mg-homeserver-card mg-homeserver-onboarding">
        <span class="mg-homeserver-kicker">Secure provider setup</span>
        <h3>Connect Microgifter</h3>
        <ol>
          <li>Register, license, and install HomeServer through VP3.</li>
          <li>Open the HomeServer Control Center and choose the Microgifter provider connection.</li>
          <li>Create a one-time Sync Code above while signed into the owning Microgifter account.</li>
          <li>Paste the code into HomeServer, then grant only the merchant data and campaign authority it needs.</li>
        </ol>
        <div class="mg-homeserver-boundary">
          <strong>Microgifter remains authoritative for Microgifter data and actions.</strong>
          <p>Microgifter controls merchant and site assignments, datasets, CRM and campaign permissions, commerce and gifting synchronization, claims, redemptions, delivery, and operational receipts. VP3 separately controls the HomeServer license, registered device, installer, release channel, and software updates.</p>
        </div>
      </article>

      <article class="mg-homeserver-card">
        <span class="mg-homeserver-kicker">Separated authority</span>
        <h3>What this connection controls</h3>
        <dl class="mg-homeserver-control-list">
          <div><dt>HomeServer license</dt><dd>VP3</dd></div>
          <div><dt>Installer and updates</dt><dd>VP3</dd></div>
          <div><dt>Microgifter data</dt><dd>Microgifter grants</dd></div>
          <div><dt>Requests</dt><dd>Ed25519 signed</dd></div>
          <div><dt>Replay defense</dt><dd>Timestamp + unique nonce</dd></div>
          <div><dt>Revocation</dt><dd>Independent per provider</dd></div>
        </dl>
      </article>
    </div>

    <section class="mg-homeserver-devices-section">
      <div class="mg-homeserver-section-head">
        <div>
          <span class="mg-homeserver-kicker">Microgifter-authorized connections</span>
          <h3>Connected HomeServers</h3>
        </div>
        <button class="mg-btn mg-btn-ghost" type="button" data-homeserver-refresh>Refresh</button>
      </div>
      <div class="mg-homeserver-devices" data-homeserver-devices>
        <p class="mg-muted">Loading Microgifter provider connections…</p>
      </div>
    </section>

    <section class="mg-homeserver-authority-shell" data-homeserver-authority aria-live="polite">
      <div class="mg-homeserver-authority-empty">Loading Microgifter data grants and agent campaign policies…</div>
    </section>
  </div>
</section>
