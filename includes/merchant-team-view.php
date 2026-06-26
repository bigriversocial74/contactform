<?php
declare(strict_types=1);
?>
<section class="mg-team-access" data-team-access-manager>
  <div class="mg-team-contract-label">Merchant team</div>

  <div class="mg-team-commandbar">
    <nav class="mg-team-tabs" aria-label="Team access sections">
      <a class="is-active" href="#team-overview">Overview</a>
      <a href="#team-list-panel">Active Staff</a>
      <a href="#team-invite-panel">Invites</a>
      <a href="#team-readiness">Roles</a>
      <a href="#team-readiness">Permissions</a>
      <a href="#team-list-panel">Redemption Staff</a>
      <a href="#team-list-panel">Activity Log</a>
      <a href="#team-list-panel">Archived</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#team-invite-panel">Invite Team Member</a>
  </div>

  <section class="mg-team-kpis" id="team-overview" aria-label="Team access metrics">
    <article><span>Active team</span><strong data-team-kpi-active>—</strong><small>Workspace users</small></article>
    <article><span>Pending invites</span><strong data-team-kpi-pending>—</strong><small>Awaiting acceptance</small></article>
    <article><span>Redemption staff</span><strong data-team-kpi-redemption>—</strong><small>Claims access</small></article>
    <article><span>Admins</span><strong data-team-kpi-admin>—</strong><small>Full access</small></article>
    <article><span>Permission gaps</span><strong data-team-kpi-gaps>—</strong><small>Review needed</small></article>
  </section>

  <div class="mg-team-layout">
    <section class="mg-app-panel mg-team-panel" id="team-list-panel">
      <div class="mg-app-panel-head mg-team-panel-head">
        <div>
          <span class="mg-eyebrow">Staff Operations</span>
          <h2>Team access manager</h2>
          <p>Review staff, roles, redemption access, product/media permissions, and workspace invitation status.</p>
        </div>
        <a class="mg-btn mg-btn-soft" href="#team-invite-panel">Invite member</a>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-team-list-shell">
          <div class="mg-team-list-head"><strong>Staff and invitations</strong><span>Role, status, and access scope</span></div>
          <div class="mg-team-list" data-team-list></div>
        </div>
      </div>
    </section>

    <aside class="mg-team-side" id="team-readiness">
      <section class="mg-app-panel mg-team-panel mg-team-readiness-card">
        <div class="mg-app-panel-head mg-team-panel-head is-compact"><div><h2>Access Readiness</h2><p>Staff controls before delegating claims, products, campaigns, or media operations.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-team-readiness-score"><span>Access signal</span><strong>Live</strong></div>
          <div class="mg-team-readiness-list">
            <p><b></b><span>Claims and redemption staff should use least-privilege roles.</span></p>
            <p><b></b><span>Pending invites should be reviewed before launch or campaign pushes.</span></p>
            <p><b></b><span>Admins can manage products, media, campaigns, locations, and staff.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-team-panel mg-team-actions-card">
        <div class="mg-app-panel-head mg-team-panel-head is-compact"><div><h2>Quick actions</h2><p>Staff operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="#team-invite-panel">Invite staff</a>
          <a href="/merchant-locations.php">Assign locations</a>
          <a href="/merchant-claims.php">Claims operations</a>
          <a href="/merchant-settings.php">Business settings</a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-team-panel" id="team-invite-panel">
    <div class="mg-app-panel-head mg-team-panel-head">
      <div>
        <span class="mg-eyebrow">Invite</span>
        <h2>Invite member</h2>
        <p>Record an invitation and assign the role that controls what this staff member can manage.</p>
      </div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-team-form" data-team-form autocomplete="off">
        <div class="mg-grid-2">
          <label>Email<input name="email" type="email" required></label>
          <label>Display name<input name="display_name"></label>
        </div>
        <label>Role
          <select name="role_key">
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="location_staff">Location staff</option>
            <option value="claims_staff">Claims staff</option>
            <option value="analyst">Analyst</option>
            <option value="viewer">Viewer</option>
          </select>
        </label>
        <div class="mg-team-role-note">
          <strong>Role guidance</strong>
          <p>Use location staff or claims staff for redemption workflows. Use manager or admin only for users who should edit products, media, campaigns, locations, or settings.</p>
        </div>
        <div class="mg-form-status" data-team-status></div>
        <button class="mg-btn mg-btn-primary" type="submit">Record invitation</button>
      </form>
    </div>
  </section>
</section>