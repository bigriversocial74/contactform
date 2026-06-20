<?php declare(strict_types=1); ?>
<div class="mg-payment-secret-fields">
  <div class="mg-payment-secret-head">
    <strong>Write-only server credentials</strong>
    <p>Leave either field blank to preserve its current encrypted value. Existing values are never returned to this page.</p>
  </div>
  <label>Stripe secret key
    <input name="secret_key" type="password" autocomplete="new-password" placeholder="sk_test_… or sk_live_…">
  </label>
  <label>Webhook signing secret
    <input name="webhook_secret" type="password" autocomplete="new-password" placeholder="whsec_…">
  </label>
</div>
