<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/investment/investor-invitations.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$token = strtolower(trim((string)($_GET['token'] ?? '')));
$returnPath = '/investor-invitation.php' . ($token !== '' ? '?token=' . rawurlencode($token) : '');
$user = mg_authenticated_user(true);
if ($user !== null) {
    $user = mg_require_auth('/signin.php', $returnPath);
}

$invitation = null;
$invitationError = null;
try {
    $invitation = mg_investment_invitation_view(mg_db(), $token, $user, true);
} catch (MgInvestmentException $error) {
    $invitationError = $error->getMessage();
    $status = $error->httpStatus();
    http_response_code(in_array($status, [404, 410, 503], true) ? $status : 410);
} catch (Throwable) {
    $invitationError = 'This Investor invitation is unavailable.';
    http_response_code(404);
}

$page_title = 'Investor Invitation | Microgifter';
$page_section = 'core';
$header_mode = $user ? 'account' : 'public';
$page_body_class = 'mg-investor-invitation-page';
$page_styles = ['/assets/css/investment-system-v1.css?v=1.0.0', '/assets/css/investor-invitation-v1.css?v=1.0.0'];
$page_scripts = $invitation && !empty($invitation['actionable']) && !empty($invitation['email_matches'])
    ? ['/assets/js/investor-invitation-v1.js?v=1.0.0']
    : [];
$csrfToken = mg_csrf_token();
require __DIR__ . '/includes/header.php';

$readable = static fn(string $value): string => ucwords(str_replace('_', ' ', $value));
?>
<main class="mg-invite-shell">
  <section class="mg-invite-card" data-investor-invitation<?= $invitation ? ' data-token="' . mg_e($token) . '" data-csrf-token="' . mg_e($csrfToken) . '"' : '' ?>>
    <header class="mg-invite-hero">
      <a class="mg-invite-brand" href="/index.php"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>
      <span class="mg-eyebrow">Private Investor onboarding</span>
      <h1><?= $invitationError ? 'Investor invitation unavailable' : 'You are invited to Investor onboarding' ?></h1>
      <p><?= $invitationError ? mg_e($invitationError) : 'Create or link your Microgifter account, complete professional information and disclosures, and enter the same governed Super Admin approval workflow used by all Investor accounts.' ?></p>
    </header>

    <?php if ($invitationError || !$invitation): ?>
      <section class="mg-invite-state is-error">
        <h2>This link cannot be used.</h2>
        <p>Ask the Microgifter Super Admin who invited you to issue a new invitation.</p>
        <a class="mg-btn mg-btn-soft" href="/index.php">Return to Microgifter</a>
      </section>
    <?php else: ?>
      <section class="mg-invite-summary" aria-label="Investor invitation summary">
        <div><span>Invited recipient</span><strong><?= mg_e((string)$invitation['email_masked']) ?></strong></div>
        <div><span>Invited by</span><strong><?= mg_e((string)$invitation['inviter_name']) ?></strong></div>
        <div><span>Organization</span><strong><?= mg_e((string)($invitation['firm_name'] ?: 'To be completed')) ?></strong></div>
        <div><span>Investor type</span><strong><?= mg_e($readable((string)$invitation['investor_type'])) ?></strong></div>
        <?php if (!empty($invitation['round_name'])): ?><div><span>Round context</span><strong><?= mg_e((string)$invitation['round_name']) ?></strong></div><?php endif; ?>
        <div><span>Invitation expires</span><strong><?= mg_e(date('M j, Y g:i A', strtotime((string)$invitation['expires_at']))) ?></strong></div>
      </section>

      <?php if (!empty($invitation['personal_message'])): ?>
        <section class="mg-invite-message"><span>Message from <?= mg_e((string)$invitation['inviter_name']) ?></span><p><?= nl2br(mg_e((string)$invitation['personal_message'])) ?></p></section>
      <?php endif; ?>

      <?php if (!$invitation['actionable']): ?>
        <section class="mg-invite-state is-<?= mg_e((string)$invitation['status']) ?>">
          <h2><?= mg_e($readable((string)$invitation['status'])) ?></h2>
          <?php if ($invitation['status'] === 'accepted'): ?>
            <p>This invitation has already been converted into an Investor Access request<?= $invitation['request_status'] ? ' with status ' . mg_e($readable((string)$invitation['request_status'])) : '' ?>.</p>
            <?php if ($user && $invitation['email_matches']): ?><a class="mg-btn mg-btn-primary" href="/investor-access.php">View Investor Access status</a><?php endif; ?>
          <?php elseif ($invitation['status'] === 'expired'): ?>
            <p>The invitation expired before onboarding was completed. Request a new invitation from the Super Admin.</p>
          <?php elseif ($invitation['status'] === 'revoked'): ?>
            <p>The Super Admin revoked this invitation. It no longer grants an onboarding path.</p>
          <?php else: ?>
            <p>This invitation is no longer available.</p>
          <?php endif; ?>
        </section>
      <?php elseif (!$user): ?>
        <section class="mg-invite-state">
          <h2>Link the invited email to a Microgifter account.</h2>
          <p>Sign in with the invited email address or create a free account. After authentication, you will return here to complete Investor onboarding.</p>
          <div class="mg-invite-actions">
            <a class="mg-btn mg-btn-primary" href="/signin.php?invite=<?= rawurlencode($token) ?>&amp;return=<?= rawurlencode($returnPath) ?>">Sign in to continue</a>
            <a class="mg-btn mg-btn-soft" href="/signup.php?type=customer&amp;invite=<?= rawurlencode($token) ?>&amp;return=<?= rawurlencode($returnPath) ?>">Create invited account</a>
          </div>
        </section>
      <?php elseif (!$invitation['email_matches']): ?>
        <section class="mg-invite-state is-error">
          <h2>This invitation belongs to another email address.</h2>
          <p>You are signed in as <?= mg_e((string)($user['email'] ?? 'another account')) ?>. Sign out and use the invited email shown above.</p>
        </section>
      <?php else: ?>
        <section class="mg-invite-onboarding">
          <header><span class="mg-eyebrow">Authenticated onboarding</span><h2>Complete your professional Investor profile</h2><p>Submission creates a pending Investor Access request. It does not grant portal, Data Room, round, or securities access until a Super Admin approves it.</p></header>
          <form class="mg-investment-form" data-invitation-form>
            <div class="mg-field-grid">
              <label><span>Firm or organization name</span><input name="firm_name" maxlength="180" required value="<?= mg_e((string)($invitation['firm_name'] ?? '')) ?>"></label>
              <label><span>Job title</span><input name="job_title" maxlength="160" placeholder="Partner, founder, analyst…"></label>
              <label><span>Website URL</span><input name="website_url" inputmode="url" placeholder="https://example.com"></label>
              <label><span>Primary professional social profile</span><input name="primary_social_url" inputmode="url" required placeholder="https://linkedin.com/in/name"></label>
              <label><span>LinkedIn URL</span><input name="linkedin_url" inputmode="url" placeholder="Optional when primary profile is elsewhere"></label>
              <label><span>Additional social profile</span><input name="additional_social_url" inputmode="url" placeholder="Crunchbase, AngelList, X, or other"></label>
              <label><span>Investor type</span><select name="investor_type"><?php foreach (['individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other'] as $type): ?><option value="<?= mg_e($type) ?>"<?= $invitation['investor_type'] === $type ? ' selected' : '' ?>><?= mg_e($readable($type)) ?></option><?php endforeach; ?></select></label>
              <label><span>Expected investment range</span><select name="expected_investment_range"><?php foreach (['undecided'=>'Undecided','under_10k'=>'Under $10,000','10k_25k'=>'$10,000–$25,000','25k_50k'=>'$25,000–$50,000','50k_100k'=>'$50,000–$100,000','100k_250k'=>'$100,000–$250,000','over_250k'=>'Over $250,000'] as $value=>$label): ?><option value="<?= mg_e($value) ?>"<?= $invitation['expected_investment_range'] === $value ? ' selected' : '' ?>><?= mg_e($label) ?></option><?php endforeach; ?></select></label>
              <label><span>Phone</span><input name="phone" maxlength="60" autocomplete="tel"></label>
            </div>
            <label class="is-wide"><span>Reason for requesting Investor access</span><textarea name="request_reason" rows="6" minlength="20" maxlength="4000" required placeholder="Describe your interest in Microgifter and what you hope to review."></textarea></label>
            <label class="mg-investment-check"><input type="checkbox" name="identity_acknowledgement" value="1" required><span>I confirm that the professional and organizational information submitted here is accurate to the best of my knowledge.</span></label>
            <label class="mg-investment-check"><input type="checkbox" name="confidentiality_acknowledgement" value="1" required><span>I understand that approved Investor materials may contain private information and must not be redistributed outside authorized recipients.</span></label>
            <label class="mg-investment-check"><input type="checkbox" name="acknowledgement" value="1" required><span>I understand that this invitation does not create an investment commitment, allocation, approval, securities offer, purchase agreement, or automatic Data Room access. Submission creates only a pending Investor Access request.</span></label>
            <div class="mg-investment-notice" data-invitation-notice role="status" aria-live="polite"></div>
            <footer class="mg-investment-actions"><button class="mg-btn mg-btn-primary" type="submit" data-invitation-submit>Submit for Super Admin review</button></footer>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
