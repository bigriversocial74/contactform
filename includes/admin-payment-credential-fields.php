<?php declare(strict_types=1); ?>
<div class="mg-payment-secret-fields">
  <div class="mg-payment-secret-head">
    <strong>Write-only server credentials</strong>
    <p>Leave either field blank to preserve its current encrypted value. Existing values are never returned to this page.</p>
  </div>
  <label>Stripe secret or restricted key
    <input name="secret_key" type="password" autocomplete="new-password" placeholder="sk_live_… or rk_live_…">
    <small>Use a key that matches the selected Test or Live mode. Restricted keys must include the Stripe permissions required by checkout, Connect, refunds, and account reads.</small>
  </label>
  <label>Webhook signing secret
    <input name="webhook_secret" type="password" autocomplete="new-password" placeholder="whsec_…">
  </label>
</div>
