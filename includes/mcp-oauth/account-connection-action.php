<?php if ((string)$connection['status'] !== 'revoked'): ?>
<form method="post" class="mg-ai-connection-action">
  <input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>">
  <input type="hidden" name="connection_id" value="<?= mg_e((string)$connection['id']) ?>">
  <button class="mg-oauth-button is-secondary" type="submit">Disconnect</button>
</form>
<?php endif; ?>
