<?php
declare(strict_types=1);
?>
<section class="mg-reward-library" data-reward-library-manager>
  <div class="mg-reward-toolbar">
    <nav class="mg-reward-tabs" aria-label="Reward library sections">
      <a class="is-active" href="#reward-overview" data-reward-tab-link data-reward-tab="overview" aria-current="page">Overview</a>
      <a href="#reward-active" data-reward-tab-link data-reward-tab="active">Active Rewards</a>
      <a href="#reward-drafts" data-reward-tab-link data-reward-tab="drafts">Drafts</a>
      <a href="#reward-gift-cards" data-reward-tab-link data-reward-tab="gift_cards">Gift Cards</a>
      <a href="#reward-media-packs" data-reward-tab-link data-reward-tab="media_packs">Media Packs</a>
      <a href="#reward-discounts" data-reward-tab-link data-reward-tab="discounts">Discounts</a>
      <a href="#reward-experiences" data-reward-tab-link data-reward-tab="experiences">Experiences</a>
      <a href="#reward-archived" data-reward-tab-link data-reward-tab="archived">Archived</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-link data-reward-tab="create">Create Reward</a>
  </div>

  <div class="mg-reward-tab-panels">
    <section class="mg-reward-tab-panel is-active" id="reward-overview" data-reward-tab-panel="overview" aria-label="Reward overview">
      <section class="mg-reward-kpis" aria-label="Reward metrics">
        <article><span>Active rewards</span><strong data-reward-kpi-active>—</strong><small>Available for campaigns</small></article>
        <article><span>Draft rewards</span><strong data-reward-kpi-draft>—</strong><small>Needs setup</small></article>
        <article><span>Issued rewards</span><strong data-reward-kpi-issued>—</strong><small>Template inventory</small></article>
        <article><span>Claimed rewards</span><strong data-reward-kpi-claimed>—</strong><small>Customer claims</small></article>
        <article><span>Redeemed rewards</span><strong data-reward-kpi-redeemed>—</strong><small>Completed commerce</small></article>
      </section>

      <div class="mg-reward-layout">
        <section class="mg-app-panel mg-reward-panel" id="reward-list">
          <div class="mg-app-panel-head mg-reward-panel-head">
            <div>
              <span class="mg-eyebrow">Reward Library</span>
              <h2>Offer assets</h2>
              <p>Reusable wallet-ready rewards for campaigns, QR pickups, distribution programs, media packs, and agent discovery.</p>
            </div>
            <div class="mg-heading-actions">
              <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
              <a class="mg-btn mg-btn-soft" href="/merchant-distribution.php">Distribution</a>
            </div>
          </div>
          <div class="mg-app-panel-body">
            <div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="all"></div>
          </div>
        </section>

        <aside class="mg-reward-side">
          <section class="mg-app-panel mg-reward-panel mg-reward-readiness">
            <div class="mg-app-panel-head mg-reward-panel-head is-compact"><div><h2>Reward Readiness</h2><p>What needs attention before scaling campaigns.</p></div></div>
            <div class="mg-app-panel-body">
              <div class="mg-reward-readiness-score"><span>Library score</span><strong data-reward-readiness-score>—</strong></div>
              <div class="mg-reward-readiness-list">
                <p><b></b><span data-reward-ready-primary>Create at least one active reward for campaign distribution.</span></p>
                <p><b></b><span data-reward-ready-secondary>Add redemption instructions so staff know exactly what to honor.</span></p>
                <p><b></b><span data-reward-ready-tertiary>Use expiration and limits to control liability and inventory.</span></p>
              </div>
            </div>
          </section>

          <section class="mg-app-panel mg-reward-panel mg-reward-actions">
            <div class="mg-app-panel-head mg-reward-panel-head is-compact"><div><h2>Quick actions</h2><p>Common reward assets.</p></div></div>
            <div class="mg-app-panel-body">
              <a href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="discount">Create discount</a>
              <a href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="free_item">Create gift reward</a>
              <a href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="audio_pack">Create audio pack</a>
              <a href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="media_pack">Create media pack</a>
              <a href="/merchant-campaigns.php">Attach to campaign</a>
            </div>
          </section>
        </aside>
      </div>
    </section>

    <section class="mg-reward-tab-panel" id="reward-active" data-reward-tab-panel="active" aria-label="Active rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Active Rewards</span><h2>Campaign-ready rewards</h2><p>Active templates that can be attached to campaigns, QR pickups, distribution programs, and agent discovery.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create">Create Reward</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="active"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-drafts" data-reward-tab-panel="drafts" aria-label="Draft rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Drafts</span><h2>Rewards still in setup</h2><p>Draft templates that need status, value, instructions, or liability controls before publishing.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create">Create Reward</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="drafts"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-gift-cards" data-reward-tab-panel="gift_cards" aria-label="Gift card rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Gift Cards</span><h2>Gift and credit templates</h2><p>Dollar credits and item-based rewards that work as reusable gift assets.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="dollar_credit">Create Gift Card</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="gift_cards"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-media-packs" data-reward-tab-panel="media_packs" aria-label="Media pack rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Media Packs</span><h2>Audio and media reward packs</h2><p>Reward templates that carry a cover image plus audio, video, image, or document media that loads from the user inbox.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="audio_pack">Create Audio Pack</a><a class="mg-btn mg-btn-soft" href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="media_pack">Create Media Pack</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="media_packs"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-discounts" data-reward-tab-panel="discounts" aria-label="Discount rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Discounts</span><h2>Discount reward templates</h2><p>Campaign-ready discounts for newsletters, contests, distribution links, and local promotions.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="discount">Create Discount</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="discounts"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-experiences" data-reward-tab-panel="experiences" aria-label="Experience rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Experiences</span><h2>Experience and event rewards</h2><p>Perks, upgrades, event rewards, and custom experiences for high-value engagement campaigns.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create" data-reward-type-preset="event_reward">Create Experience</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="experiences"></div></div></section></section>
    <section class="mg-reward-tab-panel" id="reward-archived" data-reward-tab-panel="archived" aria-label="Archived rewards" hidden><section class="mg-app-panel mg-reward-panel"><div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Archived</span><h2>Retired reward templates</h2><p>Archived rewards stay visible for audit history without showing up in active campaign setup.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-primary" href="#reward-create" data-reward-tab-trigger="create">Create Reward</a></div></div><div class="mg-app-panel-body"><div class="mg-product-list mg-reward-list" data-stage12-template-list data-reward-list-filter="archived"></div></div></section></section>

    <section class="mg-reward-tab-panel" id="reward-create" data-reward-tab-panel="create" aria-label="Create new reward" hidden>
      <section class="mg-app-panel mg-reward-panel mg-reward-builder-panel" id="reward-builder">
        <div class="mg-app-panel-head mg-reward-panel-head"><div><span class="mg-eyebrow">Builder</span><h2>Create new reward</h2><p>Build one wallet-ready reward template. Audio and media packs include a cover image plus files that load from the recipient inbox.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form mg-reward-builder-form" data-stage12-template-builder enctype="multipart/form-data">
            <input type="hidden" name="template_id" value="">
            <input type="hidden" name="media_items_json" value="">
            <div class="mg-grid-2"><label>Reward type<select name="reward_type" data-reward-type-select><option value="dollar_credit">Dollar Credit</option><option value="free_item">Free Item</option><option value="discount">Discount</option><option value="perk_upgrade">Perk / Upgrade</option><option value="event_reward">Event Reward</option><option value="audio_pack">Audio Pack</option><option value="media_pack">Media Pack</option><option value="custom">Custom</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label></div>
            <label>Template title<input name="title" placeholder="Coffee credit" required maxlength="180"></label>
            <label>Description<textarea name="description" placeholder="Explain what the customer receives."></textarea></label>
            <div class="mg-grid-2"><label>Value amount<input name="value_amount" placeholder="10.00"></label><label>Expiration rule<select name="expiration_rule"><option value="none">No expiration</option><option value="after_issue">After issue</option><option value="after_claim">After claim</option><option value="fixed_date">Fixed date</option><option value="event_date">Event date</option></select></label></div>
            <section class="mg-reward-media-fields" data-reward-media-fields hidden>
              <span class="mg-eyebrow">Pack media</span>
              <h3>Cover image and pack files</h3>
              <p>The campaign landing page only shows the cover image. The inbox load button opens the full audio/media pack.</p>
              <label>Cover image URL<input name="cover_image_url" placeholder="https://... or /uploads/..." data-reward-cover-url></label>
              <label>Upload cover image<input name="cover_image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label>
              <label>Media URLs<textarea name="media_item_urls" placeholder="One URL per line. Audio packs should use MP3, M4A, WAV, AAC, or OGG."></textarea></label>
              <label>Upload pack files<input name="media_files[]" type="file" multiple accept="audio/*,video/*,image/*,.pdf"></label>
              <div class="mg-reward-media-preview" data-reward-media-preview></div>
            </section>
            <div class="mg-grid-2"><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label></div>
            <label class="mg-reward-check"><input type="checkbox" name="agent_discoverable" value="1"> <span>Agent-discoverable offer</span></label>
            <label>Agent summary<input name="agent_summary" placeholder="Short recommendation summary"></label>
            <label>Agent categories<input name="agent_categories" placeholder="coffee, lunch, local rewards"></label>
            <label>Redemption instructions<textarea name="redemption_instructions" placeholder="Show this reward to staff."></textarea></label>
            <div class="mg-form-status" data-stage12-template-status>Ready to save a reward template.</div>
            <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="submit" data-stage12-template-save>Save template</button><button class="mg-btn mg-btn-ghost" type="button" data-stage12-template-new>New template</button></div>
          </form>
        </div>
      </section>
    </section>
  </div>
</section>
<script src="/assets/js/stage12-reward-templates.js" defer></script>
