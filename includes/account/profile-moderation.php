<?php
declare(strict_types=1);
?>
<section class="mg-profile-moderation" data-profile-moderation data-can-manage="<?= $canManageProfileModeration ? '1' : '0' ?>">
  <header class="mg-profile-moderation-header">
    <div>
      <span class="mg-kicker">Trust and safety</span>
      <h1>Profile moderation</h1>
      <p>Review public identity cases, apply profile-level restrictions, resolve appeals, and retain a durable action history.</p>
    </div>
    <div class="mg-profile-moderation-header-actions">
      <button class="mg-btn mg-btn-ghost" type="button" data-moderation-refresh>Refresh</button>
      <?php if ($canManageProfileModeration): ?>
        <button class="mg-btn mg-btn-primary" type="button" data-moderation-open-case>Open case</button>
      <?php endif; ?>
    </div>
  </header>

  <div class="mg-moderation-state" data-moderation-state role="status" aria-live="polite">
    <strong>Loading moderation queue</strong>
    <span>Preparing profile cases and permissions.</span>
  </div>

  <div class="mg-moderation-content mg-hidden" data-moderation-content>
    <section class="mg-moderation-metrics" aria-label="Moderation queue summary">
      <article><span>Open</span><strong data-moderation-metric="open">0</strong></article>
      <article><span>In review</span><strong data-moderation-metric="in_review">0</strong></article>
      <article><span>Appealed</span><strong data-moderation-metric="appealed">0</strong></article>
      <article><span>Urgent</span><strong data-moderation-metric="urgent">0</strong></article>
      <article><span>Unassigned</span><strong data-moderation-metric="unassigned">0</strong></article>
    </section>

    <form class="mg-moderation-filters" data-moderation-filters>
      <label class="is-search">Search
        <input type="search" name="q" placeholder="Profile, slug, profile ID, or case ID">
      </label>
      <label>Status
        <select name="status">
          <option value="active">Active queue</option>
          <option value="open">Open</option>
          <option value="in_review">In review</option>
          <option value="actioned">Actioned</option>
          <option value="appealed">Appealed</option>
          <option value="resolved">Resolved</option>
          <option value="dismissed">Dismissed</option>
          <option value="all">All statuses</option>
        </select>
      </label>
      <label>Priority
        <select name="priority">
          <option value="all">All priorities</option>
          <option value="urgent">Urgent</option>
          <option value="high">High</option>
          <option value="normal">Normal</option>
          <option value="low">Low</option>
        </select>
      </label>
      <label>Category
        <select name="category">
          <option value="all">All categories</option>
          <option value="impersonation">Impersonation</option>
          <option value="harassment">Harassment</option>
          <option value="spam">Spam</option>
          <option value="fraud">Fraud</option>
          <option value="unsafe_content">Unsafe content</option>
          <option value="copyright">Copyright</option>
          <option value="privacy">Privacy</option>
          <option value="policy">Policy</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label>Assignment
        <select name="assignee">
          <option value="all">All cases</option>
          <option value="me">Assigned to me</option>
          <option value="unassigned">Unassigned</option>
        </select>
      </label>
      <button class="mg-btn mg-btn-soft" type="submit">Apply filters</button>
    </form>

    <div class="mg-moderation-workspace">
      <aside class="mg-moderation-queue-panel" aria-label="Moderation cases">
        <div class="mg-moderation-panel-head">
          <div><h2>Review queue</h2><p><span data-moderation-total>0</span> matching cases</p></div>
        </div>
        <div class="mg-moderation-case-list" data-moderation-case-list></div>
        <div class="mg-moderation-empty mg-hidden" data-moderation-queue-empty>
          <strong>No matching cases</strong>
          <span>Adjust the filters or open a new profile case.</span>
        </div>
        <div class="mg-moderation-pagination">
          <button type="button" class="mg-btn mg-btn-ghost" data-moderation-page="previous">Previous</button>
          <span data-moderation-page-label>Page 1 of 1</span>
          <button type="button" class="mg-btn mg-btn-ghost" data-moderation-page="next">Next</button>
        </div>
      </aside>

      <main class="mg-moderation-review-panel">
        <div class="mg-moderation-select-state" data-moderation-select-state>
          <span class="mg-badge">Case review</span>
          <h2>Select a moderation case</h2>
          <p>Choose a case from the queue to inspect the profile, content footprint, appeal, and action history.</p>
        </div>

        <div class="mg-moderation-case-detail mg-hidden" data-moderation-case-detail>
          <header class="mg-moderation-case-header">
            <div>
              <div class="mg-moderation-badge-row" data-case-badges></div>
              <h2 data-case-summary>Moderation case</h2>
              <p data-case-details></p>
            </div>
            <div class="mg-moderation-case-meta">
              <span>Case <strong data-case-id>—</strong></span>
              <span>Opened <strong data-case-opened>—</strong></span>
              <span>Assigned <strong data-case-assignee>Unassigned</strong></span>
            </div>
          </header>

          <section class="mg-moderation-profile-card" aria-labelledby="mg-moderation-profile-title">
            <div class="mg-moderation-cover" data-case-cover></div>
            <div class="mg-moderation-profile-body">
              <div class="mg-moderation-avatar" data-case-avatar><span>M</span></div>
              <div class="mg-moderation-profile-copy">
                <span class="mg-kicker">Profile under review</span>
                <h3 id="mg-moderation-profile-title" data-profile-name>Profile</h3>
                <p class="mg-moderation-headline" data-profile-headline></p>
                <p data-profile-biography></p>
                <div class="mg-moderation-profile-meta" data-profile-meta></div>
              </div>
              <div class="mg-moderation-profile-links">
                <a class="mg-btn mg-btn-ghost" data-profile-public-link target="_blank" rel="noopener">Public page</a>
                <a class="mg-btn mg-btn-soft" data-profile-preview-link target="_blank" rel="noopener">Owner preview</a>
              </div>
            </div>
          </section>

          <section class="mg-moderation-review-grid">
            <article>
              <header><h3>Identity and reach</h3><p>Current public profile attributes and content footprint.</p></header>
              <dl class="mg-moderation-facts" data-profile-facts></dl>
            </article>
            <article>
              <header><h3>Profile links</h3><p>Active and inactive external destinations.</p></header>
              <div class="mg-moderation-content-list" data-profile-links></div>
            </article>
            <article class="is-wide">
              <header><h3>Custom sections</h3><p>Owner-authored profile content in display order.</p></header>
              <div class="mg-moderation-section-list" data-profile-sections></div>
            </article>
            <article class="is-wide mg-hidden" data-case-evidence-card>
              <header><h3>Case evidence</h3><p>Structured evidence attached when the case was opened.</p></header>
              <div class="mg-moderation-evidence" data-case-evidence></div>
            </article>
          </section>

          <section class="mg-moderation-appeals mg-hidden" data-moderation-appeals-section>
            <header><div><h3>Owner appeal</h3><p>Review the owner statement and record a final decision.</p></div></header>
            <div data-moderation-appeals></div>
          </section>

          <section class="mg-moderation-history">
            <header><div><h3>Moderation history</h3><p>Durable case actions in reverse chronological order.</p></div></header>
            <ol data-moderation-history></ol>
          </section>
        </div>
      </main>

      <aside class="mg-moderation-action-panel" aria-label="Moderation actions">
        <div class="mg-moderation-panel-head"><div><h2>Case actions</h2><p>Changes are transactional and audit logged.</p></div></div>
        <?php if ($canManageProfileModeration): ?>
          <div class="mg-moderation-action-empty" data-action-empty>Select a case to enable actions.</div>
          <form class="mg-moderation-action-form mg-hidden" data-moderation-action-form>
            <input type="hidden" name="case_id">
            <label>Action
              <select name="action" required>
                <option value="claim">Claim case</option>
                <option value="note">Add internal note</option>
                <option value="warn">Record warning</option>
                <option value="hide">Hide profile</option>
                <option value="suspend">Suspend profile</option>
                <option value="restore">Restore profile</option>
                <option value="escalate">Escalate priority</option>
                <option value="dismiss">Dismiss case</option>
                <option value="appeal_accept">Accept appeal</option>
                <option value="appeal_deny">Deny appeal</option>
              </select>
            </label>
            <label>Reason code
              <select name="reason_code">
                <option value="other">Other</option>
                <option value="impersonation">Impersonation</option>
                <option value="harassment">Harassment</option>
                <option value="spam">Spam</option>
                <option value="fraud">Fraud</option>
                <option value="unsafe_content">Unsafe content</option>
                <option value="copyright">Copyright</option>
                <option value="privacy">Privacy</option>
                <option value="policy_violation">Policy violation</option>
                <option value="insufficient_evidence">Insufficient evidence</option>
                <option value="owner_remediated">Owner remediated</option>
                <option value="appeal_upheld">Appeal upheld</option>
                <option value="appeal_denied">Appeal denied</option>
              </select>
            </label>
            <label class="mg-hidden" data-restore-status-field>Restore as
              <select name="restore_status">
                <option value="">Previous safe status</option>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="hidden">Hidden</option>
              </select>
            </label>
            <label class="mg-hidden" data-priority-field>Priority
              <select name="priority">
                <option value="">Next priority level</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
              </select>
            </label>
            <label>Reason or note
              <textarea name="reason" rows="6" maxlength="5000" placeholder="Document the evidence and decision."></textarea>
            </label>
            <div class="mg-moderation-action-warning" data-action-warning></div>
            <button class="mg-btn mg-btn-primary" type="submit" data-action-submit>Apply action</button>
            <div class="mg-profile-action-status" data-action-status role="status" aria-live="polite"></div>
          </form>
        <?php else: ?>
          <div class="mg-moderation-readonly"><strong>Read-only access</strong><span>This session can inspect cases but cannot apply moderation actions.</span></div>
        <?php endif; ?>
      </aside>
    </div>
  </div>

  <?php if ($canManageProfileModeration): ?>
    <dialog class="mg-moderation-dialog" data-moderation-open-dialog>
      <form method="dialog" class="mg-moderation-dialog-close"><button type="submit" aria-label="Close">×</button></form>
      <form class="mg-moderation-open-form" data-moderation-open-form>
        <span class="mg-kicker">New review</span>
        <h2>Open profile case</h2>
        <p>Use a profile slug or public profile ID. Duplicate active cases in the same category are rejected.</p>
        <label>Profile reference<input name="profile_ref" required placeholder="profile-slug or pp_..."></label>
        <div class="mg-grid-2">
          <label>Category
            <select name="category" required>
              <option value="impersonation">Impersonation</option><option value="harassment">Harassment</option><option value="spam">Spam</option><option value="fraud">Fraud</option><option value="unsafe_content">Unsafe content</option><option value="copyright">Copyright</option><option value="privacy">Privacy</option><option value="policy">Policy</option><option value="other" selected>Other</option>
            </select>
          </label>
          <label>Priority
            <select name="priority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select>
          </label>
        </div>
        <label>Summary<input name="summary" maxlength="220" required placeholder="Concise reason for review"></label>
        <label>Details<textarea name="details" rows="7" maxlength="10000" placeholder="Policy context, observations, and relevant evidence."></textarea></label>
        <div class="mg-profile-action-status" data-open-case-status role="status" aria-live="polite"></div>
        <div class="mg-action-row"><button class="mg-btn mg-btn-primary" type="submit">Create case</button><button class="mg-btn mg-btn-ghost" type="button" data-moderation-dialog-cancel>Cancel</button></div>
      </form>
    </dialog>
  <?php endif; ?>
</section>
