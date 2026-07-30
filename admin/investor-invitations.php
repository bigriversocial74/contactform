<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/investment/investment-service.php';

$user = mg_require_admin_page_permission('admin.investor_access.view');
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$canManage = mg_admin_page_user_has_permission($user, 'admin.investor_access.manage') && in_array('super_admin', $roles, true);
$ready = mg_investment_invitation_tables_ready(mg_db());
$page_title = 'Investor Invitations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-investor-invitations-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0','/assets/css/investor-invitations-v1.css?v=1.0.0'];
$page_scripts = ['/assets/js/admin-investor-invitations-v1.js?v=1.0.0'];
$adminActive = 'investor-invitations';
$csrfToken = mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-invites-admin" data-admin-investor-invitations data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>" data-schema-ready="<?= $ready ? '1' : '0' ?>" data-migration="<?= mg_e(MG_INVESTOR_INVITATION_MIGRATION) ?>">
      <header class="mg-investment-hero is-admin">
        <div><a href="/admin/investor-center.php">← Investor Center</a><span class="mg-eyebrow">Governed outreach and onboarding</span><h1>Investor Invitations</h1><p>Invite a specific recipient, bind onboarding to the invited email, collect professional disclosures, and convert acceptance into the existing Super Admin Investor Access review workflow.</p></div>
        <div class="mg-investment-hero-actions"><a class="mg-btn mg-btn-ghost" href="/admin/investor-access-requests.php">Access requests</a><a class="mg-btn mg-btn-soft" href="/admin/investor-pipeline.php">Investor pipeline</a><button class="mg-btn mg-btn-ghost" type="button" data-invitations-refresh>Refresh</button></div>
      </header>

      <?php if (!$ready): ?>
        <section class="mg-invites-schema-warning"><strong>Database migration required</strong><span>Import <code><?= mg_e(MG_INVESTOR_INVITATION_MIGRATION) ?></code> before creating invitations.</span></section>
      <?php endif; ?>

      <div class="mg-invites-layout">
        <section class="mg-investment-panel mg-invites-compose">
          <header><div><span>Super Admin invitation</span><h2>Create a secure onboarding link</h2><p>The link is single-use, token-hashed in storage, email-bound, expiring, revocable, and does not grant Investor access by itself.</p></div></header>
          <form class="mg-investment-form" data-invitation-create-form>
            <div class="mg-field-grid">
              <label><span>Recipient email</span><input type="email" name="email" autocomplete="email" maxlength="254" required></label>
              <label><span>Recipient name</span><input name="contact_name" autocomplete="name" maxlength="180"></label>
              <label><span>Firm or organization</span><input name="firm_name" autocomplete="organization" maxlength="180"></label>
              <label><span>Investor type</span><select name="investor_type"><option value="individual">Individual investor</option><option value="angel">Angel investor</option><option value="investment_firm">Investment firm</option><option value="venture_fund">Venture fund</option><option value="family_office">Family office</option><option value="strategic_partner">Strategic partner</option><option value="company_entity">Company or entity</option><option value="other">Other</option></select></label>
              <label><span>Expected range</span><select name="expected_investment_range"><option value="undecided">Undecided</option><option value="under_10k">Under $10,000</option><option value="10k_25k">$10,000–$25,000</option><option value="25k_50k">$25,000–$50,000</option><option value="50k_100k">$50,000–$100,000</option><option value="100k_250k">$100,000–$250,000</option><option value="over_250k">Over $250,000</option></select></label>
              <label><span>Round context</span><select name="round_id" data-invitation-round><option value="">General Investor invitation</option></select></label>
              <label><span>Expires after</span><select name="expires_in_days"><option value="7">7 days</option><option value="14" selected>14 days</option><option value="30">30 days</option><option value="60">60 days</option></select></label>
            </div>
            <label class="is-wide"><span>Personal message</span><textarea name="personal_message" rows="5" maxlength="4000" placeholder="Optional context from the founder or Super Admin."></textarea></label>
            <label class="mg-investment-check"><input type="checkbox" name="send_email" value="1" checked><span>Send the invitation email now. The secure link is also returned for manual delivery.</span></label>
            <div class="mg-investment-notice" data-invitation-create-notice role="status" aria-live="polite"></div>
            <footer class="mg-investment-actions"><button class="mg-btn mg-btn-primary" type="submit" data-invitation-create<?= (!$canManage || !$ready) ? ' disabled' : '' ?>>Create Investor invitation</button></footer>
          </form>
          <div class="mg-invite-share" data-invitation-share hidden><div><span>Secure invitation link</span><strong data-invitation-share-status></strong></div><input readonly data-invitation-share-url><button class="mg-btn mg-btn-soft" type="button" data-invitation-copy>Copy link</button></div>
        </section>

        <section class="mg-investment-panel mg-invites-list-panel">
          <header><div><span>Invitation lifecycle</span><h2>Issued invitations</h2><p data-invitations-summary>Loading invitations…</p></div></header>
          <form class="mg-investment-filter" data-invitations-filter><label>Search<input name="q" placeholder="Email, name, firm, or round"></label><label>Status<select name="status"><option value="">All statuses</option><option value="created">Created</option><option value="sent">Sent</option><option value="viewed">Viewed</option><option value="accepted">Accepted</option><option value="expired">Expired</option><option value="revoked">Revoked</option></select></label><button class="mg-btn mg-btn-soft" type="submit">Apply</button></form>
          <div class="mg-investment-notice" data-invitations-notice role="status" aria-live="polite"></div>
          <div class="mg-investment-table-wrap"><table class="mg-investment-table mg-invitations-table"><thead><tr><th>Recipient</th><th>Context</th><th>Status</th><th>Delivery</th><th>Expires</th><th>Access request</th><th></th></tr></thead><tbody data-invitations-list></tbody></table></div>
        </section>
      </div>
    </section>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
