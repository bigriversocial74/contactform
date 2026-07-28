<?php
declare(strict_types=1);
?>
<div class="mg-secret-storage-fields">
  <label data-payment-secret-control="secret">
    Stripe API key
    <div class="mg-secret-saved-row">
      <input type="text" value="Checking database…" readonly data-payment-secret-display>
      <button class="mg-btn mg-btn-ghost" type="button" data-payment-secret-replace>Replace</button>
    </div>
    <input
      name="secret_key"
      type="password"
      form="stripe-payment-form"
      autocomplete="new-password"
      placeholder="Paste sk_live_… or rk_live_…"
      data-payment-secret-editor
      hidden
    >
  </label>

  <label data-payment-secret-control="webhook">
    Webhook signing secret
    <div class="mg-secret-saved-row">
      <input type="text" value="Checking database…" readonly data-payment-secret-display>
      <button class="mg-btn mg-btn-ghost" type="button" data-payment-secret-replace>Replace</button>
    </div>
    <input
      name="webhook_secret"
      type="password"
      form="stripe-payment-form"
      autocomplete="new-password"
      placeholder="Paste whsec_…"
      data-payment-secret-editor
      hidden
    >
  </label>
</div>
