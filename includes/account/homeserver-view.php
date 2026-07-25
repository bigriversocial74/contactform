<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-account-pane mg-homeserver-account is-active" data-account-pane="homeserver" data-homeserver-account>
  <div class="mg-app-panel-head mg-homeserver-head">
    <div>
      <span class="mg-homeserver-kicker">Private local Microgifter edge</span>
      <h2>HomeServer Connections</h2>
      <p>Pair a Windows HomeServer, review its signed synchronization scopes, and revoke cloud access from one account-controlled workspace.</p>
    </div>
    <button class="mg-btn mg-btn-primary" type="button" data-homeserver-create-code>Create pairing code</button>
  </div>

  <div class="mg-app-panel-body mg-homeserver-body">
    <div class="mg-homeserver-message" data-homeserver-message hidden></div>

    <section class="mg-homeserver-code-panel" data-homeserver-code-panel hidden aria-live="polite">
      <div>
        <span>One-time pairing code</span>
        <strong class="mg-homeserver-code" data-homeserver-code></strong>
        <small data-homeserver-code-expiry></small>
      </div>
      <div class="mg-homeserver-code-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-homeserver-copy-code>Copy code</button>
        <button class="mg-btn mg-btn-ghost" type="button" data-homeserver-hide-code>Hide</button>
      </div>
      <p>Enter this code in the HomeServer Control Center. The code expires quickly and is permanently consumed by the first successful pairing.</p>
    </section>

    <div class="mg-homeserver-grid">
      <article class="mg-homeserver-card mg-homeserver-onboarding">
        <span class="mg-homeserver-kicker">Secure setup</span>
        <h3>Pair a HomeServer</h3>
        <ol>
          <li>Install and open the Microgifter HomeServer Control Center on Windows.</li>
          <li>Create a one-time code above while signed into the owning Microgifter account.</li>
          <li>Paste the code into the Control Center. A new Ed25519 key is generated locally.</li>
          <li>Confirm the device appears below with only the approved status and synchronization scopes.</li>
        </ol>
        <div class="mg-homeserver-boundary">
          <strong>Cloud authority remains enforced.</strong>
          <p>HomeServer cannot originate payments, purchases, claims, redemption, gift ownership, wallet, or other commerce mutations through this connector.</p>
        </div>
      </article>

      <article class="mg-homeserver-card">
        <span class="mg-homeserver-kicker">Protocol controls</span>
        <h3>What every connection uses</h3>
        <dl class="mg-homeserver-control-list">
          <div><dt>Transport</dt><dd>HTTPS only</dd></div>
          <div><dt>Requests</dt><dd>Ed25519 signed</dd></div>
          <div><dt>Replay defense</dt><dd>Timestamp + unique nonce</dd></div>
          <div><dt>Credentials</dt><dd>Hashed cloud token</dd></div>
          <div><dt>Synchronization</dt><dd>Idempotent receipts</dd></div>
          <div><dt>Revocation</dt><dd>Immediate cloud denial</dd></div>
        </dl>
      </article>
    </div>

    <section class="mg-homeserver-devices-section">
      <div class="mg-homeserver-section-head">
        <div>
          <span class="mg-homeserver-kicker">Account-owned devices</span>
          <h3>Connected HomeServers</h3>
        </div>
        <button class="mg-btn mg-btn-ghost" type="button" data-homeserver-refresh>Refresh</button>
      </div>
      <div class="mg-homeserver-devices" data-homeserver-devices>
        <p class="mg-muted">Loading HomeServer devices…</p>
      </div>
    </section>
  </div>
</section>
