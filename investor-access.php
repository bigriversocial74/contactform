<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = mg_require_auth('/signin.php', '/investor-access.php');
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$page_title = 'Investor Access | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-investment-page mg-investor-access-page';
$page_styles = ['/assets/css/investment-system-v1.css?v=1.0.0'];
$page_scripts = ['/assets/js/investor-access-v1.js?v=1.0.0'];
$csrfToken = mg_csrf_token();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-investment-shell" data-investor-access data-csrf-token="<?= mg_e($csrfToken) ?>">
  <header class="mg-investment-hero">
    <div>
      <span class="mg-eyebrow">Private capital access</span>
      <h1>Request Investor Access</h1>
      <p>Apply for permission to view Microgifter funding-round information, approved company updates, investor documents, and current planning summaries.</p>
    </div>
    <div class="mg-investment-hero-actions">
      <?php if (in_array('investor', $roles, true)): ?><a class="mg-btn mg-btn-primary" href="/investor-portal.php">Open Investor Portal</a><?php endif; ?>
      <a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a>
    </div>
  </header>

  <div class="mg-investment-grid is-access">
    <aside class="mg-investment-summary-card">
      <span>Authenticated account</span>
      <h2><?= mg_e((string)($user['display_name'] ?? $user['full_name'] ?? 'Microgifter user')) ?></h2>
      <p><?= mg_e((string)($user['email'] ?? '')) ?></p>
      <div class="mg-chip-list"><?php foreach ($roles as $role): ?><span class="mg-chip"><?= mg_e((string)$role) ?></span><?php endforeach; ?></div>
      <div class="mg-investment-status" data-access-status><strong>Loading status…</strong><span>Checking your latest request.</span></div>
    </aside>

    <main class="mg-investment-panel">
      <header><div><span>Investor application</span><h2>Professional information</h2><p>Super Admin reviews every application. Submission does not create an investment commitment, allocation, approval, or securities offer.</p></div></header>
      <form class="mg-investment-form" data-access-form>
        <div class="mg-field-grid">
          <label><span>Firm or organization name</span><input name="firm_name" maxlength="180" required placeholder="Individual investor or firm name"></label>
          <label><span>Job title</span><input name="job_title" maxlength="160" placeholder="Partner, founder, analyst…"></label>
          <label><span>Website URL</span><input name="website_url" inputmode="url" placeholder="https://example.com"></label>
          <label><span>Primary professional social profile</span><input name="primary_social_url" inputmode="url" required placeholder="https://linkedin.com/in/name"></label>
          <label><span>LinkedIn URL</span><input name="linkedin_url" inputmode="url" placeholder="Optional when primary profile is elsewhere"></label>
          <label><span>Additional social profile</span><input name="additional_social_url" inputmode="url" placeholder="Crunchbase, AngelList, X, or other"></label>
          <label><span>Investor type</span><select name="investor_type"><option value="individual">Individual investor</option><option value="angel">Angel investor</option><option value="investment_firm">Investment firm</option><option value="venture_fund">Venture fund</option><option value="family_office">Family office</option><option value="strategic_partner">Strategic partner</option><option value="company_entity">Company or entity</option><option value="other">Other</option></select></label>
          <label><span>Expected investment range</span><select name="expected_investment_range"><option value="undecided">Undecided</option><option value="under_10k">Under $10,000</option><option value="10k_25k">$10,000–$25,000</option><option value="25k_50k">$25,000–$50,000</option><option value="50k_100k">$50,000–$100,000</option><option value="100k_250k">$100,000–$250,000</option><option value="over_250k">Over $250,000</option></select></label>
          <label><span>Referral source</span><input name="referral_source" maxlength="180" placeholder="How did you hear about Microgifter?"></label>
          <label><span>Phone</span><input name="phone" maxlength="60" autocomplete="tel"></label>
        </div>
        <label class="is-wide"><span>Reason for requesting access</span><textarea name="request_reason" rows="6" minlength="20" maxlength="4000" required placeholder="Describe your interest in Microgifter and what you hope to review."></textarea></label>
        <label class="mg-investment-check"><input type="checkbox" name="acknowledgement" value="1" required><span>I understand that this request does not represent an investment commitment, allocation, approval, or offer to purchase securities.</span></label>
        <div class="mg-investment-notice" data-access-notice role="status" aria-live="polite"></div>
        <footer class="mg-investment-actions"><button class="mg-btn mg-btn-ghost" type="button" data-access-withdraw hidden>Withdraw request</button><button class="mg-btn mg-btn-primary" type="submit" data-access-submit>Submit Investor Access Request</button></footer>
      </form>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
