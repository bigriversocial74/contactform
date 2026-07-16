<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/user-contact-lists.php';

$user = mg_require_auth();
$lists = [];
$loadError = '';
try {
    $lists = mg_user_contact_lists(mg_db(), (int) $user['id'], isset($_GET['archived']));
} catch (Throwable $e) {
    $loadError = 'Lists are unavailable until the contact-list SQL migration is imported.';
}

$page_title = 'My Lists | Microgifter';
$page_section = 'agent';
$header_mode = 'account';
$agent_tab = 'lists';
$page_styles = ['/assets/css/user-lists.css','/assets/css/personal-agent-opportunity-actions.css?v=1.0.0','/assets/css/personal-agent-recovery.css?v=1.0.0'];
$page_scripts = [
    '/assets/js/user-lists.js',
    '/assets/js/user-lists-create.js?v=1.1.0',
    '/assets/js/saved-opportunities.js?v=1.1.0',
    '/assets/js/personal-agent-attribution-runtime.js?v=1.0.0',
];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-user-lists-shell" data-user-lists-page>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-user-lists-main">
    <header class="mg-user-lists-hero">
      <div>
        <span class="mg-user-lists-kicker">Personal Gifting Agent</span>
        <h1>My Lists</h1>
        <p>Organize family, friends, coworkers, birthdays, holidays, and private contacts into reusable gifting groups.</p>
      </div>
      <button class="mg-btn mg-btn-primary" type="button" data-user-list-open-create aria-haspopup="dialog" aria-controls="mg-create-menu" aria-expanded="false">Create list</button>
    </header>

    <section class="mg-saved-opportunities" data-saved-opportunities aria-labelledby="saved-opportunities-title">
      <header>
        <div>
          <span class="mg-user-lists-kicker">Agent opportunities</span>
          <h2 id="saved-opportunities-title">Saved Opportunities</h2>
          <p>Products, campaigns, merchants, experiences, and rewards saved from Personal Agent recommendations.</p>
        </div>
        <span data-saved-opportunity-status aria-live="polite"></span>
      </header>
      <div class="mg-saved-opportunity-grid" data-saved-opportunity-grid>
        <div class="mg-saved-opportunities-empty">Loading saved opportunities…</div>
      </div>
    </section>

    <div class="mg-user-lists-toolbar">
      <label class="mg-user-lists-search"><span>Search lists</span><input type="search" placeholder="Family, birthdays, coworkers…" data-user-list-filter></label>
      <a class="mg-user-lists-filter<?= isset($_GET['archived']) ? ' is-active' : '' ?>" href="<?= isset($_GET['archived']) ? '/lists.php' : '/lists.php?archived=1' ?>"><?= isset($_GET['archived']) ? 'Active lists' : 'Include archived' ?></a>
    </div>

    <?php if ($loadError !== ''): ?>
      <section class="mg-user-lists-state is-warning"><strong>Database setup required</strong><p><?= mg_e($loadError) ?></p><code>database/20260714_user_contact_lists_phase1.sql</code></section>
    <?php elseif ($lists === []): ?>
      <section class="mg-user-lists-state"><strong>No lists yet</strong><p>Create a list for people, occasions, or recurring gifting plans.</p><button type="button" class="mg-btn mg-btn-primary" data-user-list-open-create aria-haspopup="dialog" aria-controls="mg-create-menu" aria-expanded="false">Create your first list</button></section>
    <?php else: ?>
      <div class="mg-user-list-grid" data-user-list-grid>
        <?php foreach ($lists as $list): ?>
          <article class="mg-user-list-card" data-user-list-card data-search-text="<?= mg_e(mb_strtolower($list['name'] . ' ' . $list['description'] . ' ' . $list['list_type'])) ?>">
            <div class="mg-user-list-card-icon" aria-hidden="true" data-list-icon="<?= mg_e($list['icon_key']) ?>">✦</div>
            <div class="mg-user-list-card-copy">
              <div class="mg-user-list-card-title"><h2><?= mg_e($list['name']) ?></h2><?php if ($list['is_archived']): ?><span>Archived</span><?php endif; ?></div>
              <p><?= mg_e($list['description'] !== '' ? $list['description'] : ucfirst($list['list_type']) . ' gifting group') ?></p>
              <div class="mg-user-list-card-meta">
                <span><strong><?= (int) $list['member_count'] ?></strong> contacts</span>
                <span><?php if ($list['next_birthday']): ?>Next birthday <?= mg_e(date('M j', strtotime((string) $list['next_birthday']))) ?><?php else: ?>No upcoming birthday saved<?php endif; ?></span>
              </div>
            </div>
            <a class="mg-user-list-card-open" href="/list.php?id=<?= rawurlencode($list['id']) ?>" aria-label="Open <?= mg_e($list['name']) ?>">Open <span aria-hidden="true">→</span></a>
          </article>
        <?php endforeach; ?>
      </div>
      <section class="mg-user-lists-state" data-user-list-no-results hidden><strong>No matching lists</strong><p>Try another search term.</p></section>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
