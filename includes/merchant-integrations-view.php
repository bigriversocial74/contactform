<?php declare(strict_types=1); ?>
<section class="mg-integrations" data-merchant-integrations>
  <header class="mg-integrations-hero">
    <div>
      <span class="mg-eyebrow">Third-party app connect</span>
      <h1>Connected Apps</h1>
      <p>Connect the CRM, audience, and web-store systems you already use. Microgifter keeps rewards, claims, campaign engagement, and identity history while importing approved external customer and commerce data.</p>
    </div>
    <div class="mg-integrations-hero-badge" aria-label="App Connect contacts version 1">
      <strong>App Connect</strong>
      <span>Contacts v1</span>
    </div>
  </header>

  <section class="mg-integrations-health" data-integrations-health aria-live="polite">
    <div><span>Connection framework</span><strong data-integrations-schema>Checking…</strong></div>
    <div><span>Credential encryption</span><strong data-integrations-encryption>Checking…</strong></div>
    <div><span>Active connections</span><strong data-integrations-active-count>0</strong></div>
    <button type="button" data-integrations-refresh>Refresh</button>
  </section>

  <section class="mg-integrations-principles" aria-label="Integration data rules">
    <article>
      <span>01</span>
      <div><strong>Provider-neutral records</strong><p>External IDs map to canonical Microgifter CRM contacts instead of using email as the permanent link.</p></div>
    </article>
    <article>
      <span>02</span>
      <div><strong>Consent stays explicit</strong><p>Marketing status is preserved only when a provider supplies verifiable evidence. Imports never silently opt a customer into marketing.</p></div>
    </article>
    <article>
      <span>03</span>
      <div><strong>Addresses excluded</strong><p>Billing and shipping addresses are excluded, and address-derived phone numbers are not imported.</p></div>
    </article>
  </section>

  <div class="mg-integrations-section-title">
    <div><span class="mg-eyebrow">Provider catalog</span><h2>Connect your business systems</h2></div>
    <p>Squarespace, WooCommerce, Shopify, Square, HubSpot, Mailchimp, and Klaviyo use the same secure connection, identity-link, synchronization, review, and audit foundation.</p>
  </div>

  <section class="mg-integrations-grid" data-integrations-grid>
    <div class="mg-integrations-loading"><strong>Loading connected apps</strong><p>Checking provider configuration and merchant connections.</p></div>
  </section>

  <section class="mg-integrations-ownership">
    <header><span class="mg-eyebrow">Source-of-truth policy</span><h2>What each system owns</h2></header>
    <div class="mg-integrations-ownership-grid">
      <article><strong>External provider</strong><p>Website contacts, audience membership, orders, products, inventory, and provider-side marketing preference history. Addresses remain outside this integration.</p></article>
      <article><strong>Microgifter</strong><p>Social gifts, campaign participation, rewards, wallet items, claims, redemptions, follow-ups, messages, and engagement intelligence.</p></article>
      <article><strong>Shared identity layer</strong><p>Provider record links, duplicate review, synchronization history, conflicts, external deletion markers, and consent evidence.</p></article>
    </div>
  </section>
</section>
