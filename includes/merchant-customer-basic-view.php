<?php

declare(strict_types=1);

if (!function_exists('mg_mc_basic_h')) {
    function mg_mc_basic_h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mg_mc_basic_date')) {
    function mg_mc_basic_date(mixed $value): string
    {
        $time = strtotime((string)$value);
        return $time > 0 ? date('M j, Y g:ia', $time) : '—';
    }
}

if (!function_exists('mg_mc_basic_label')) {
    function mg_mc_basic_label(mixed $value): string
    {
        $text = trim((string)$value);
        return $text !== '' ? ucwords(str_replace(['_', '-'], ' ', $text)) : '—';
    }
}

$mgMcBasicError = '';
$mgMcBasicContact = null;
$mgMcBasicSource = '';
$mgMcBasicLookup = [
    'campaign_contact_id' => strtolower(trim((string)($_GET['campaign_contact_id'] ?? ''))),
    'contact_id' => strtolower(trim((string)($_GET['contact_id'] ?? $_GET['crm_contact_id'] ?? $_GET['id'] ?? ''))),
    'email' => strtolower(trim((string)($_GET['email'] ?? ''))),
];

try {
    $mgMcBasicPdo = mg_db();
    $mgMcBasicMerchantId = (int)($user['id'] ?? 0);

    if ($mgMcBasicMerchantId <= 0) {
        $mgMcBasicError = 'Merchant session could not be resolved.';
    } elseif ($mgMcBasicLookup['campaign_contact_id'] !== '' && preg_match('/^[0-9a-f-]{36}$/i', $mgMcBasicLookup['campaign_contact_id']) === 1) {
        $mgMcBasicStmt = $mgMcBasicPdo->prepare("SELECT cc.public_id campaign_contact_public_id,cc.email,cc.phone,cc.name,cc.source,cc.opt_in_status,cc.user_id,cc.created_at,cc.updated_at,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1");
        $mgMcBasicStmt->execute([$mgMcBasicLookup['campaign_contact_id'], $mgMcBasicMerchantId]);
        $mgMcBasicContact = $mgMcBasicStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $mgMcBasicSource = 'campaign_contact';
    } elseif ($mgMcBasicLookup['contact_id'] !== '' && preg_match('/^[0-9a-f-]{36}$/i', $mgMcBasicLookup['contact_id']) === 1) {
        $mgMcBasicStmt = $mgMcBasicPdo->prepare('SELECT public_id crm_contact_public_id,primary_email email,primary_phone phone,display_name name,lifecycle_stage source,crm_status opt_in_status,user_id,first_seen_at created_at,last_seen_at updated_at,last_campaign_type campaign_type FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $mgMcBasicStmt->execute([$mgMcBasicLookup['contact_id'], $mgMcBasicMerchantId]);
        $mgMcBasicContact = $mgMcBasicStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $mgMcBasicSource = 'crm_contact';
    } elseif ($mgMcBasicLookup['email'] !== '' && filter_var($mgMcBasicLookup['email'], FILTER_VALIDATE_EMAIL)) {
        $mgMcBasicStmt = $mgMcBasicPdo->prepare('SELECT public_id crm_contact_public_id,primary_email email,primary_phone phone,display_name name,lifecycle_stage source,crm_status opt_in_status,user_id,first_seen_at created_at,last_seen_at updated_at,last_campaign_type campaign_type FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? ORDER BY updated_at DESC,id DESC LIMIT 1');
        $mgMcBasicStmt->execute([$mgMcBasicMerchantId, $mgMcBasicLookup['email']]);
        $mgMcBasicContact = $mgMcBasicStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $mgMcBasicSource = 'crm_contact';

        if (!$mgMcBasicContact) {
            $mgMcBasicStmt = $mgMcBasicPdo->prepare("SELECT cc.public_id campaign_contact_public_id,cc.email,cc.phone,cc.name,cc.source,cc.opt_in_status,cc.user_id,cc.created_at,cc.updated_at,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.merchant_user_id=? AND LOWER(cc.email)=? ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 1");
            $mgMcBasicStmt->execute([$mgMcBasicMerchantId, $mgMcBasicLookup['email']]);
            $mgMcBasicContact = $mgMcBasicStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $mgMcBasicSource = 'campaign_contact';
        }
    }

    if (!$mgMcBasicContact && $mgMcBasicLookup['campaign_contact_id'] . $mgMcBasicLookup['contact_id'] . $mgMcBasicLookup['email'] !== '') {
        $mgMcBasicError = 'No matching customer record was found for this merchant.';
    }
} catch (Throwable $mgMcBasicThrowable) {
    $mgMcBasicError = 'Basic customer profile failed to load.';
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'merchant_customer.basic_view_failed', 'Basic merchant customer view failed.', ['exception_class' => $mgMcBasicThrowable::class, 'message' => $mgMcBasicThrowable->getMessage()], (int)($user['id'] ?? 0));
    }
}

$mgMcBasicName = trim((string)($mgMcBasicContact['name'] ?? '')) ?: (string)($mgMcBasicContact['email'] ?? 'Customer');
$mgMcBasicEmail = (string)($mgMcBasicContact['email'] ?? '');
$mgMcBasicCampaignContactId = (string)($mgMcBasicContact['campaign_contact_public_id'] ?? '');
$mgMcBasicCrmContactId = (string)($mgMcBasicContact['crm_contact_public_id'] ?? '');
$mgMcBasicProfileId = $mgMcBasicCampaignContactId !== '' ? $mgMcBasicCampaignContactId : $mgMcBasicCrmContactId;
$mgMcBasicCampaignTitle = trim((string)($mgMcBasicContact['campaign_title'] ?? '')) ?: mg_mc_basic_label($mgMcBasicContact['campaign_type'] ?? $mgMcBasicContact['source'] ?? '');
$mgMcBasicCrmHref = '/merchant-crm.php?tab=contacts';
if ($mgMcBasicCampaignContactId !== '') {
    $mgMcBasicCrmHref .= '&campaign_contact_id=' . rawurlencode($mgMcBasicCampaignContactId);
} elseif ($mgMcBasicCrmContactId !== '') {
    $mgMcBasicCrmHref .= '&contact_id=' . rawurlencode($mgMcBasicCrmContactId);
} elseif ($mgMcBasicEmail !== '') {
    $mgMcBasicCrmHref .= '&email=' . rawurlencode($mgMcBasicEmail);
}
?>
<section class="mg-cp-page mg-cp-basic-page" data-customer-profile-basic-page>
  <header class="mg-cp-header">
    <div>
      <h1>Customer Profile</h1>
      <p>Basic server-rendered profile mode. API-heavy panels are intentionally disabled while this page is rebuilt and tested.</p>
    </div>
    <div class="mg-cp-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-crm.php?tab=contacts">Back to CRM</a>
      <?php if ($mgMcBasicContact): ?>
        <a class="mg-btn mg-btn-primary" href="<?= mg_mc_basic_h($mgMcBasicCrmHref) ?>">Open in CRM</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($mgMcBasicError !== ''): ?>
    <section class="mg-cp-card is-error">
      <div class="mg-cp-card-head"><div><h3>Customer not loaded</h3><span><?= mg_mc_basic_h($mgMcBasicError) ?></span></div></div>
      <div class="mg-cp-card-body">
        <p>Open this page from Merchant CRM with a campaign contact, CRM contact, or email lookup.</p>
      </div>
    </section>
  <?php elseif (!$mgMcBasicContact): ?>
    <section class="mg-cp-card">
      <div class="mg-cp-card-head"><div><h3>No customer selected</h3><span>This stripped-down test view is waiting for a customer reference.</span></div></div>
      <div class="mg-cp-card-body">
        <p>Use Merchant CRM and open a customer profile from the Contacts list.</p>
      </div>
    </section>
  <?php else: ?>
    <section class="mg-cp-grid">
      <aside class="mg-cp-profile-card mg-cp-card">
        <div class="mg-cp-avatar" aria-hidden="true"><span><?= mg_mc_basic_h(strtoupper(substr($mgMcBasicName, 0, 2))) ?></span></div>
        <h2><?= mg_mc_basic_h($mgMcBasicName) ?></h2>
        <p class="mg-cp-muted"><?= mg_mc_basic_h($mgMcBasicEmail ?: 'No email on file') ?></p>
        <p class="mg-cp-muted"><?= mg_mc_basic_h($mgMcBasicContact['phone'] ?? '') ?></p>
        <div class="mg-cp-pills">
          <span class="is-blue"><?= mg_mc_basic_h(mg_mc_basic_label($mgMcBasicSource)) ?></span>
          <span class="is-gold"><?= mg_mc_basic_h(mg_mc_basic_label($mgMcBasicContact['opt_in_status'] ?? 'active')) ?></span>
          <span class="is-green"><?= ((int)($mgMcBasicContact['user_id'] ?? 0) > 0) ? 'Linked account' : 'No account yet' ?></span>
        </div>
      </aside>

      <section class="mg-cp-center">
        <article class="mg-cp-card">
          <div class="mg-cp-card-head"><div><h3>Basic Customer Record</h3><span>No profile APIs are called in this test mode.</span></div></div>
          <dl class="mg-cp-details">
            <div><dt>Profile ID</dt><dd><?= mg_mc_basic_h($mgMcBasicProfileId ?: '—') ?></dd></div>
            <div><dt>Source</dt><dd><?= mg_mc_basic_h(mg_mc_basic_label($mgMcBasicSource)) ?></dd></div>
            <div><dt>Campaign</dt><dd><?= mg_mc_basic_h($mgMcBasicCampaignTitle) ?></dd></div>
            <div><dt>Campaign Type</dt><dd><?= mg_mc_basic_h(mg_mc_basic_label($mgMcBasicContact['campaign_type'] ?? $mgMcBasicContact['source'] ?? '')) ?></dd></div>
            <div><dt>First Seen</dt><dd><?= mg_mc_basic_h(mg_mc_basic_date($mgMcBasicContact['created_at'] ?? null)) ?></dd></div>
            <div><dt>Last Updated</dt><dd><?= mg_mc_basic_h(mg_mc_basic_date($mgMcBasicContact['updated_at'] ?? null)) ?></dd></div>
          </dl>
        </article>

        <article class="mg-cp-card">
          <div class="mg-cp-card-head"><div><h3>Rebuild Checkpoint</h3><span>Use this as the known-good baseline before adding APIs back.</span></div></div>
          <ul data-cp-snapshot>
            <li>Server render only.</li>
            <li>No full customer-profile API call.</li>
            <li>No lite-profile fallback timer.</li>
            <li>No agent timeline request.</li>
            <li>No retention recommendation request.</li>
            <li>No action button mutation loop.</li>
          </ul>
        </article>
      </section>
    </section>
  <?php endif; ?>
</section>
