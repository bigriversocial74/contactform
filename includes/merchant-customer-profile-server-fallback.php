<?php
declare(strict_types=1);

$mgCustomerProfileFallbackContact = null;
$mgCustomerProfileFallbackError = '';
$mgCustomerProfileFallbackId = strtolower(trim((string)($_GET['campaign_contact_id'] ?? '')));

if ($mgCustomerProfileFallbackId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $mgCustomerProfileFallbackId) === 1 && isset($user) && is_array($user)) {
    try {
        $mgCustomerProfilePdo = mg_db();
        $mgCustomerProfileStmt = $mgCustomerProfilePdo->prepare("SELECT cc.public_id,cc.email,cc.phone,cc.name,cc.source,cc.opt_in_status,cc.user_id,cc.created_at,cc.updated_at,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1");
        $mgCustomerProfileStmt->execute([$mgCustomerProfileFallbackId, (int)$user['id']]);
        $mgCustomerProfileFallbackContact = $mgCustomerProfileStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $mgCustomerProfileFallbackThrowable) {
        $mgCustomerProfileFallbackError = 'Customer profile fallback could not read this campaign contact.';
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'merchant_customer.server_fallback_failed', 'Merchant customer server fallback failed.', ['exception_class' => $mgCustomerProfileFallbackThrowable::class], (int)($user['id'] ?? 0));
        }
    }
}

if (!function_exists('mg_customer_profile_fallback_h')) {
    function mg_customer_profile_fallback_h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mg_customer_profile_fallback_date')) {
    function mg_customer_profile_fallback_date(mixed $value): string
    {
        $time = strtotime((string)$value);
        return $time > 0 ? date('M j, Y g:ia', $time) : '—';
    }
}

if ($mgCustomerProfileFallbackContact):
    $mgCustomerProfileFallbackName = trim((string)($mgCustomerProfileFallbackContact['name'] ?? '')) ?: (string)($mgCustomerProfileFallbackContact['email'] ?? 'Customer');
    $mgCustomerProfileFallbackCampaign = trim((string)($mgCustomerProfileFallbackContact['campaign_title'] ?? '')) ?: 'Campaign contact';
    $mgCustomerProfileFallbackSource = ucwords(str_replace('_', ' ', (string)($mgCustomerProfileFallbackContact['source'] ?? $mgCustomerProfileFallbackContact['campaign_type'] ?? 'campaign_contact')));
    $mgCustomerProfileFallbackHasAccount = (int)($mgCustomerProfileFallbackContact['user_id'] ?? 0) > 0;
?>
<section class="mg-cp-server-fallback mg-cp-card" data-cp-server-fallback data-campaign-contact-id="<?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackContact['public_id'] ?? '') ?>">
  <div class="mg-cp-card-head">
    <div>
      <h3>Customer profile loaded</h3>
      <span>Server fallback for this campaign contact. The full command center below can still hydrate deeper wallet, message, and timeline details.</span>
    </div>
    <a class="mg-btn mg-btn-secondary" href="/merchant-crm.php?tab=contacts&amp;campaign_contact_id=<?= rawurlencode((string)$mgCustomerProfileFallbackContact['public_id']) ?>">Open in CRM</a>
  </div>
  <div class="mg-cp-server-grid">
    <div>
      <strong><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackName) ?></strong>
      <p><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackContact['email'] ?? '') ?></p>
      <?php if (!empty($mgCustomerProfileFallbackContact['phone'])): ?><p><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackContact['phone']) ?></p><?php endif; ?>
    </div>
    <div><small>Campaign</small><strong><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackCampaign) ?></strong><p><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackSource) ?></p></div>
    <div><small>Account</small><strong><?= $mgCustomerProfileFallbackHasAccount ? 'Linked account' : 'No account yet' ?></strong><p><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackContact['opt_in_status'] ?? 'unknown') ?></p></div>
    <div><small>Last activity</small><strong><?= mg_customer_profile_fallback_h(mg_customer_profile_fallback_date($mgCustomerProfileFallbackContact['updated_at'] ?? $mgCustomerProfileFallbackContact['created_at'] ?? null)) ?></strong><p>First seen <?= mg_customer_profile_fallback_h(mg_customer_profile_fallback_date($mgCustomerProfileFallbackContact['created_at'] ?? null)) ?></p></div>
  </div>
</section>
<?php elseif ($mgCustomerProfileFallbackError !== ''): ?>
<section class="mg-cp-server-fallback mg-cp-card is-error" data-cp-server-fallback>
  <div class="mg-cp-card-head"><div><h3>Customer profile fallback unavailable</h3><span><?= mg_customer_profile_fallback_h($mgCustomerProfileFallbackError) ?></span></div></div>
</section>
<?php endif; ?>
