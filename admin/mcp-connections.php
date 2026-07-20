<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.mcp_connections');
$page_title = 'MCP Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-mcp-page';
$page_styles = [
    '/assets/css/admin-shell.css',
    '/assets/css/admin-mcp-connections.css?v=20260720-phase1-provisioning',
];
$page_scripts = [
    '/assets/js/admin-mcp-connections.js?v=20260720-phase1-provisioning',
];
$adminActive = 'mcp-connections';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-admin-mcp" data-admin-mcp>
      <header class="mg-admin-mcp-hero">
        <div>
          <a class="mg-admin-mcp-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Platform Phase 5 · MCP Phase 1</span>
          <h1>MCP operations</h1>
          <p>Provision internal MCP clients and user connections, control database-backed scopes, and prepare secure runtime configuration without placing production secrets in the repository.</p>
        </div>
        <div class="mg-admin-mcp-hero-actions">
          <span>Updated <strong data-mcp-updated>—</strong></span>
          <button class="mg-btn mg-btn-ghost" type="button" data-mcp-refresh>Refresh</button>
          <button class="mg-btn mg-btn-primary" type="button" data-mcp-provision-open>Provision connection</button>
        </div>
      </header>

      <section class="mg-admin-mcp-stats" aria-label="MCP summary">
        <article><span>Clients</span><strong data-mcp-stat="clients">—</strong><small>Registered harnesses</small></article>
        <article><span>Connections</span><strong data-mcp-stat="connections">—</strong><small>User authorizations</small></article>
        <article><span>Active</span><strong data-mcp-stat="active_connections">—</strong><small>Currently callable</small></article>
        <article><span>Ready</span><strong data-mcp-stat="ready_connections">—</strong><small>Profile + catalog scopes</small></article>
      </section>

      <div class="mg-admin-mcp-layout">
        <section class="mg-admin-mcp-panel mg-admin-mcp-readiness">
          <header>
            <div><span class="mg-eyebrow">Deployment gate</span><h2>Runtime readiness</h2></div>
            <span class="mg-admin-mcp-status" data-mcp-readiness-label>Checking…</span>
          </header>
          <div class="mg-admin-mcp-readiness-grid" data-mcp-readiness></div>
          <div class="mg-admin-mcp-note">
            <strong>Boundary:</strong> Phase 1 remains internal, read-only, and bridge-mediated. Node receives no production database credentials.
          </div>
        </section>

        <section class="mg-admin-mcp-panel mg-admin-mcp-clients">
          <header>
            <div><span class="mg-eyebrow">Harness registry</span><h2>MCP clients</h2></div>
          </header>
          <div class="mg-admin-mcp-client-list" data-mcp-clients></div>
        </section>
      </div>

      <section class="mg-admin-mcp-panel mg-admin-mcp-connections-panel">
        <header>
          <div>
            <span class="mg-eyebrow">Authorization control</span>
            <h2>Connections</h2>
            <p>Every request is revalidated against this connection, its client, account, workspace, expiration, operation ceiling, and active scopes.</p>
          </div>
          <div class="mg-admin-mcp-filter">
            <label for="mcp-connection-filter">Filter</label>
            <input id="mcp-connection-filter" type="search" maxlength="120" placeholder="Name, email, client, UUID" data-mcp-filter>
          </div>
        </header>

        <div class="mg-admin-mcp-state" data-mcp-loading>
          <strong>Loading MCP control plane</strong>
          <span>Reading clients, connections, scopes, and deployment readiness.</span>
        </div>
        <div class="mg-admin-mcp-state mg-hidden" data-mcp-error role="alert">
          <strong>Unable to load MCP operations</strong>
          <span data-mcp-error-message>The control plane could not be loaded.</span>
          <button class="mg-btn mg-btn-soft" type="button" data-mcp-retry>Try again</button>
        </div>
        <div class="mg-admin-mcp-state mg-hidden" data-mcp-empty>
          <strong>No MCP connections yet</strong>
          <span>Provision the first internal connection to activate the Phase 1 catalog tools.</span>
        </div>
        <div class="mg-admin-mcp-connection-list mg-hidden" data-mcp-connections></div>
      </section>

      <div class="mg-admin-mcp-layer mg-hidden" data-mcp-provision-layer>
        <button class="mg-admin-mcp-backdrop" type="button" data-mcp-provision-close aria-label="Close provisioning dialog"></button>
        <aside class="mg-admin-mcp-dialog" role="dialog" aria-modal="true" aria-labelledby="mcp-provision-title">
          <header>
            <div>
              <span class="mg-eyebrow">Protected admin action</span>
              <h2 id="mcp-provision-title">Provision MCP connection</h2>
              <p>Create or select a read-only MCP client, bind an active user and optional merchant workspace, and grant only active Phase 1 scopes.</p>
            </div>
            <button type="button" class="mg-admin-mcp-close" data-mcp-provision-close aria-label="Close">×</button>
          </header>
          <form data-mcp-provision-form>
            <section>
              <h3>Client</h3>
              <label>Use existing client
                <select name="client_public_id" data-mcp-client-select>
                  <option value="">Create a new client</option>
                </select>
              </label>
              <div class="mg-admin-mcp-form-grid" data-mcp-new-client-fields>
                <label>Client key
                  <input name="client_key" type="text" maxlength="120" placeholder="internal-chatgpt" autocomplete="off">
                </label>
                <label>Display name
                  <input name="client_display_name" type="text" maxlength="180" placeholder="Internal ChatGPT" autocomplete="off">
                </label>
                <label>Client type
                  <select name="client_type">
                    <option value="first_party">First party</option>
                    <option value="chatgpt">ChatGPT</option>
                    <option value="claude">Claude</option>
                    <option value="custom" selected>Custom</option>
                    <option value="enterprise">Enterprise</option>
                  </select>
                </label>
                <label>Initial status
                  <select name="client_status">
                    <option value="development" selected>Development</option>
                    <option value="active">Active</option>
                  </select>
                </label>
              </div>
            </section>

            <section>
              <h3>Authorization</h3>
              <div class="mg-admin-mcp-form-grid">
                <label>User ID or email
                  <input name="user_reference" type="text" maxlength="255" required placeholder="42 or user@example.com" autocomplete="off">
                </label>
                <label>Connection name
                  <input name="connection_display_name" type="text" maxlength="180" required placeholder="David's internal agent" autocomplete="off">
                </label>
                <label>Merchant workspace UUID <small>Optional</small>
                  <input name="workspace_public_id" type="text" maxlength="36" placeholder="xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx" autocomplete="off">
                </label>
                <label>Expires after
                  <select name="expires_days">
                    <option value="30">30 days</option>
                    <option value="90" selected>90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">365 days</option>
                  </select>
                </label>
              </div>
              <fieldset class="mg-admin-mcp-scope-fieldset">
                <legend>Scopes</legend>
                <div data-mcp-scope-options></div>
              </fieldset>
              <label>Required action reason
                <textarea name="reason" rows="3" maxlength="240" required placeholder="Explain why this internal MCP connection is needed."></textarea>
              </label>
            </section>
            <div class="mg-admin-mcp-form-notice" data-mcp-provision-notice role="status" aria-live="polite"></div>
            <footer>
              <button class="mg-btn mg-btn-ghost" type="button" data-mcp-provision-close>Cancel</button>
              <button class="mg-btn mg-btn-primary" type="submit">Provision connection</button>
            </footer>
          </form>
        </aside>
      </div>

      <div class="mg-admin-mcp-layer mg-hidden" data-mcp-credentials-layer>
        <button class="mg-admin-mcp-backdrop" type="button" data-mcp-credentials-close aria-label="Close credentials dialog"></button>
        <aside class="mg-admin-mcp-dialog mg-admin-mcp-credentials-dialog" role="dialog" aria-modal="true" aria-labelledby="mcp-credentials-title">
          <header>
            <div>
              <span class="mg-eyebrow">One-time deployment bundle</span>
              <h2 id="mcp-credentials-title">Runtime credentials</h2>
              <p>Secrets are generated in memory, returned once, and never stored by Microgifter.</p>
            </div>
            <button type="button" class="mg-admin-mcp-close" data-mcp-credentials-close aria-label="Close">×</button>
          </header>
          <form data-mcp-credentials-form>
            <input type="hidden" name="connection_public_id">
            <label>Bridge URL
              <input name="bridge_url" type="url" maxlength="500" value="https://microgifter.com/api/internal/mcp-bridge.php" required>
            </label>
            <label>Required action reason
              <textarea name="reason" rows="3" maxlength="240" required placeholder="Explain why a new runtime credential bundle is being generated."></textarea>
            </label>
            <div class="mg-admin-mcp-form-notice" data-mcp-credentials-notice role="status" aria-live="polite"></div>
            <div class="mg-admin-mcp-secret-output mg-hidden" data-mcp-credentials-output>
              <div class="mg-admin-mcp-secret-warning" data-mcp-credentials-warning></div>
              <label>Bearer token <button type="button" data-copy-target="bearer">Copy</button>
                <textarea readonly rows="2" data-secret="bearer"></textarea>
              </label>
              <label>PHP environment <button type="button" data-copy-target="php">Copy</button>
                <textarea readonly rows="4" data-secret="php"></textarea>
              </label>
              <label>Node environment <button type="button" data-copy-target="node">Copy</button>
                <textarea readonly rows="13" data-secret="node"></textarea>
              </label>
            </div>
            <footer>
              <button class="mg-btn mg-btn-ghost" type="button" data-mcp-credentials-close>Close</button>
              <button class="mg-btn mg-btn-primary" type="submit">Generate once</button>
            </footer>
          </form>
        </aside>
      </div>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
