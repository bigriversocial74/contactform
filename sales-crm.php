<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Sales CRM | Microgifter';
$page_section = 'sales';
$header_mode = 'crm';
$page_styles = ['/assets/css/sales-crm.css'];
$page_scripts = ['/assets/js/sales-crm.js','/assets/js/sales-crm-sidebar-search.js'];
$user = mg_current_user();
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$canCrm = $user && (in_array('sales.leads.view_own', $permissions, true) || in_array('sales.leads.view_all', $permissions, true) || in_array('super_admin', $roles, true));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell crm-shell">
  <aside class="mg-app-sidebar crm-sidebar">
    <div class="mg-app-sidebar-brand">
      <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
      <?php if ($canCrm): ?><button class="crm-add-user-button" type="button" data-crm-tab="users" aria-label="Add sales user">+</button><?php endif; ?>
    </div>
    <?php if ($canCrm): ?>
      <div class="mg-sidebar-search crm-sidebar-search">
        <input type="search" placeholder="Search leads, email, business, ZIP..." aria-label="Search CRM leads" data-crm-sidebar-search>
        <select aria-label="Filter CRM leads by status" data-crm-sidebar-status-filter><option value="all">All statuses</option><option value="new">New</option><option value="assigned">Assigned</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="nurture">Nurture</option><option value="converted">Converted</option><option value="closed_lost">Closed lost</option><option value="spam">Spam</option></select>
      </div>
      <nav class="crm-nav mg-app-side-nav" aria-label="CRM navigation">
        <button class="is-active" type="button" data-crm-tab="leads"><strong>Leads</strong><span>Sales pipeline</span></button>
        <button type="button" data-crm-tab="manual"><strong>Add lead</strong><span>Manual entry</span></button>
        <button type="button" data-crm-tab="roster"><strong>Roster</strong><span>Sales team</span></button>
      </nav>
      <div class="crm-mini-stats" data-crm-mini-stats><div><strong>—</strong><span>Today views</span></div><div><strong>—</strong><span>Today leads</span></div></div>
    <?php else: ?>
      <div class="mg-app-sidebar-card"><h2>Sales CRM</h2><p><?= $user ? 'Sales access is not active for this account.' : 'Sign in to view and manage CRM leads.' ?></p></div>
      <nav class="mg-app-side-nav" aria-label="CRM access actions">
        <?php if ($user): ?><a href="/account.php"><strong>Account</strong><span>Review your access</span></a><?php else: ?><a href="/signin.php"><strong>Sign in</strong><span>Continue to the CRM</span></a><a href="/signup.php"><strong>Create account</strong><span>Start a workspace</span></a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </aside>

  <?php if (!$user): ?>
    <main class="crm-workspace crm-workspace-locked"><section class="crm-lock mg-app-panel"><div class="mg-app-panel-head"><div><h1>Sign in to use the CRM.</h1><p>Sales access is required to view, create, and manage CRM leads.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section></main>
  <?php elseif (!$canCrm): ?>
    <main class="crm-workspace crm-workspace-locked"><section class="crm-lock mg-app-panel"><div class="mg-app-panel-head"><div><h1>CRM access is not active.</h1><p>A super admin can enable the sales model and add this user to the sales roster.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div></section></main>
  <?php else: ?>
    <main class="crm-workspace">
      <div class="crm-main-column">
        <section class="crm-panel is-active" data-crm-pane="leads"><div class="crm-stat-row" data-crm-stats></div><div class="crm-lead-list" data-crm-lead-list><p class="mg-muted">Loading leads…</p></div></section>
        <section class="crm-panel" data-crm-pane="manual"><div class="crm-form-card"><h3>Add a manual lead</h3><p class="mg-muted">Use this when a lead comes from a phone call, text, local event, or direct conversation.</p><form data-manual-lead-form><input type="hidden" name="source_page" value="sales-crm-manual"><div class="mg-grid-2"><label>Name<input name="name" required></label><label>Email<input name="email" required type="email"></label></div><div class="mg-grid-2"><label>Phone<input name="phone"></label><label>ZIP / region<input name="zip_code"></label></div><div class="mg-grid-2"><label>Business<input name="business_name"></label><label>Website<input name="website_url" inputmode="url"></label></div><div class="mg-grid-2"><label>Lead type<select name="lead_type"><option value="merchant">Merchant</option><option value="workplace">Workplace</option><option value="creator">Creator</option><option value="partner">Partner</option><option value="general">General</option></select></label><label>Priority<select name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></label></div><label>Category<input name="category"></label><label>Message<textarea name="message" rows="4"></textarea></label><div class="mg-form-status" data-manual-lead-status></div><button class="mg-btn mg-btn-primary" type="submit">Create lead</button></form></div></section>
        <section class="crm-panel" data-crm-pane="users"><div class="crm-form-card"><h3>Add a new user</h3><p class="mg-muted">Create a basic Microgifter customer account from a lead or direct sales conversation.</p><form data-create-user-form><input type="hidden" name="lead_id" data-user-lead-id value=""><div class="mg-grid-2"><label>Full name<input name="full_name" required></label><label>Email<input name="email" required type="email"></label></div><div class="mg-form-status" data-create-user-status></div><button class="mg-btn mg-btn-primary" type="submit">Create user</button></form></div></section>
        <section class="crm-panel" data-crm-pane="roster"><div class="crm-layout-grid roster-grid"><div class="crm-roster-list" data-crm-roster><p class="mg-muted">Loading roster…</p></div><aside class="crm-form-card"><h3>Add / update roster user</h3><p class="mg-muted">Requires sales.roster.manage. Enter an existing user ID to place them into sales routing.</p><form data-roster-form><label>User ID<input name="user_id" required inputmode="numeric"></label><label>Status<select name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></label><label>Territory<input name="territory" placeholder="Phoenix, AZ"></label><label>Region code<input name="region_code" placeholder="85001 or AZ"></label><div class="mg-grid-2"><label>Weight<input name="lead_weight" value="100" inputmode="numeric"></label><label>Max open<input name="max_open_leads" value="50" inputmode="numeric"></label></div><div class="mg-form-status" data-roster-status></div><button class="mg-btn mg-btn-soft" type="submit">Save roster user</button></form></aside></div></section>
      </div>
      <aside class="crm-team-panel"><header class="crm-team-head"><div><span class="crm-team-kicker">Sales team</span><h2>Employee chat</h2></div><span class="crm-presence-dot is-online" aria-label="You are online"></span></header><div class="crm-team-list" data-crm-team-list><p class="mg-muted">Loading sales team…</p></div><section class="crm-chat-panel" data-crm-chat-panel><div class="crm-chat-empty"><strong>Select a sales person</strong><span>Open a conversation or leave an offline note.</span></div></section></aside>
    </main>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>