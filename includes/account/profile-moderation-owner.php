<?php
declare(strict_types=1);
?>
<section class="mg-profile-moderation-owner mg-hidden" data-profile-moderation-owner aria-live="polite">
  <div class="mg-profile-moderation-owner-icon" aria-hidden="true">!</div>
  <div class="mg-profile-moderation-owner-copy">
    <span class="mg-kicker">Profile moderation</span>
    <h2 data-owner-moderation-title>Your profile has a moderation restriction</h2>
    <p data-owner-moderation-summary></p>
    <div class="mg-profile-moderation-owner-meta" data-owner-moderation-meta></div>
    <div class="mg-profile-moderation-owner-reason mg-hidden" data-owner-moderation-reason></div>
    <div class="mg-profile-moderation-owner-appeal mg-hidden" data-owner-appeal-state></div>
  </div>
  <div class="mg-profile-moderation-owner-actions">
    <button class="mg-btn mg-btn-primary mg-hidden" type="button" data-owner-appeal-open>Submit appeal</button>
  </div>

  <dialog class="mg-profile-moderation-appeal-dialog" data-owner-appeal-dialog>
    <form method="dialog" class="mg-profile-moderation-appeal-close"><button type="submit" aria-label="Close">×</button></form>
    <form data-owner-appeal-form>
      <span class="mg-kicker">Request another review</span>
      <h2>Appeal profile restriction</h2>
      <p>Explain why the profile should be restored or what you changed to address the moderation concern. One appeal is available for each case.</p>
      <input type="hidden" name="case_id">
      <label>Appeal statement
        <textarea name="statement" rows="8" minlength="20" maxlength="5000" required placeholder="Provide context, corrections, or evidence for the review team."></textarea>
      </label>
      <div class="mg-profile-action-status" data-owner-appeal-status role="status"></div>
      <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit">Submit appeal</button><button class="mg-btn mg-btn-ghost" type="button" data-owner-appeal-cancel>Cancel</button></div>
    </form>
  </dialog>
</section>
