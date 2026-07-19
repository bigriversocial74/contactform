<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading mg-bundle-heading">
  <div><span class="mg-eyebrow">Curated local commerce</span><h1>Product &amp; Experience Bundles</h1><p>Combine existing products and experiences into one coordinated gift while each merchant retains independent terms and fulfillment.</p></div>
  <div class="mg-action-row"><a class="mg-btn mg-btn-soft" href="/merchant-bundle-invitations.php">Invitations</a><button class="mg-btn mg-btn-primary" type="button" data-new-bundle>Create bundle</button></div>
</section>
<div class="mg-bundle-status" data-bundle-status aria-live="polite">Loading bundles…</div>
<section class="mg-bundle-metrics" data-bundle-metrics></section>
<section class="mg-bundle-layout">
  <div class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Your bundles</h2><p>Draft, review, published, and participation states.</p></div></div><div class="mg-app-panel-body"><div class="mg-bundle-list" data-bundle-list></div></div></div>
  <aside class="mg-app-panel mg-bundle-builder" data-bundle-builder hidden>
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Bundle Builder</span><h2 data-builder-title>Create bundle</h2></div><button class="mg-btn mg-btn-ghost" type="button" data-close-builder>Close</button></div>
    <div class="mg-app-panel-body">
      <nav class="mg-builder-steps" aria-label="Bundle builder steps"><button class="is-active" data-step="1">1 Identity</button><button data-step="2">2 Components</button><button data-step="3">3 Merchants</button><button data-step="4">4 Options</button><button data-step="5">5 Commission</button><button data-step="6">6 Campaign</button><button data-step="7">7 Publish</button></nav>
      <form data-bundle-form>
        <section data-step-panel="1"><div class="mg-grid-2"><label>Bundle title<input name="title" maxlength="190" required></label><label>Short statement<input name="short_statement" maxlength="255"></label></div><label>Description<textarea name="description"></textarea></label><div class="mg-grid-2"><label>Cover image URL<input name="cover_asset_url" type="url"></label><label>Bundle type<select name="bundle_type"><option value="fixed_single_merchant">Single merchant</option><option value="fixed_multi_merchant">Multi-merchant</option></select></label></div><div class="mg-grid-2"><label>Category<input name="category"></label><label>Occasion<input name="occasion"></label></div><div class="mg-grid-2"><label>Primary location<input name="primary_location"></label><label>Estimated duration<input name="estimated_duration"></label></div></section>
        <section data-step-panel="2" hidden><div data-component-picker><p>Create the draft first, then select published products and configure component value, inventory, claim, reservation, and settlement terms.</p><div data-product-list></div><div data-component-list></div></div></section>
        <section data-step-panel="3" hidden><p>Invite participating merchants with versioned financial, inventory, settlement, claim, and refund terms.</p><div data-invite-form></div><div data-participant-list></div></section>
        <section data-step-panel="4" hidden><div class="mg-grid-2"><label>Sales start<input name="sales_start_at" type="datetime-local"></label><label>Sales end<input name="sales_end_at" type="datetime-local"></label></div><div class="mg-grid-2"><label>Redemption expiration<input name="redemption_expires_at" type="datetime-local"></label><label>Inventory limit<input name="inventory_limit" type="number" min="0"></label></div><label>Visibility<select name="visibility"><option value="private">Private draft</option><option value="unlisted">Unlisted</option><option value="public">Public after publish</option></select></label></section>
        <section data-step-panel="5" hidden><label>Commission mode<select name="commission_mode"><option value="merchant_default">Use each merchant’s default rate</option><option value="bundle_starting_rate">Use one starting rate</option><option value="custom_participant_rates">Custom accepted participant rates</option></select></label><label>Bundle starting rate, basis points<input name="starting_commission_bps" type="number" min="0" max="10000"><small>1500 = 15%. Existing commission authority remains canonical.</small></label><div data-commission-preview></div></section>
        <section data-step-panel="6" hidden><p>Campaign attachment is reserved for the existing campaign platform. Phase 1 stores a compatible foundation without duplicating campaign services.</p></section>
        <section data-step-panel="7" hidden><div class="mg-publish-checks" data-publish-checks></div><button class="mg-btn mg-btn-primary" type="button" data-publish-bundle>Publish bundle</button></section>
        <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit" data-save-bundle>Save draft</button><button class="mg-btn mg-btn-soft" type="button" data-prev-step>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-next-step>Next</button></div>
      </form>
    </div>
  </aside>
</section>
