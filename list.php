<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/user-contact-lists.php';

$user = mg_require_auth();
$listPublicId = mg_contact_text($_GET['id'] ?? '', 40);
$list = null;
$members = [];
$loadError = '';
try {
    if ($listPublicId === '') {
        throw new InvalidArgumentException('List id is required.');
    }
    $pdo = mg_db();
    $list = mg_user_contact_list_load($pdo, (int) $user['id'], $listPublicId);
    $members = mg_user_contact_list_members($pdo, (int) $user['id'], (int) $list['id_internal']);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$page_title = ($list ? (string) $list['name'] : 'Contact List') . ' | Microgifter';
$page_section = 'agent';
$header_mode = 'account';
$agent_tab = 'lists';
$page_styles = ['/assets/css/user-lists.css'];
$page_scripts = ['/assets/js/user-lists.js'];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-user-lists-shell" data-user-list-page data-list-id="<?= mg_e($listPublicId) ?>">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-user-lists-main">
    <?php if ($loadError !== '' || !$list): ?>
      <section class="mg-user-lists-state is-warning"><strong>List unavailable</strong><p><?= mg_e($loadError !== '' ? $loadError : 'The requested list could not be loaded.') ?></p><a class="mg-btn mg-btn-primary" href="/lists.php">Back to My Lists</a></section>
    <?php else: ?>
      <header class="mg-user-list-detail-hero">
        <div class="mg-user-list-detail-title">
          <a href="/lists.php">← My Lists</a>
          <span class="mg-user-lists-kicker"><?= mg_e(ucfirst((string) $list['list_type'])) ?> list</span>
          <h1><?= mg_e((string) $list['name']) ?></h1>
          <p><?= mg_e((string) ($list['description'] ?: 'A private gifting group managed by your Personal Gifting Agent.')) ?></p>
        </div>
        <div class="mg-user-list-detail-actions">
          <span><strong><?= count($members) ?></strong> contacts</span>
          <button class="mg-btn mg-btn-primary" type="button" data-contact-panel-open>Add contact</button>
        </div>
      </header>

      <section class="mg-user-list-opportunities" aria-label="List opportunities">
        <article><span>Upcoming dates</span><strong><?= count(array_filter($members, static fn(array $m): bool => !empty($m['birthdate']))) ?></strong><small>Contacts with birthdays saved</small></article>
        <article><span>Gift readiness</span><strong><?= count(array_filter($members, static fn(array $m): bool => !empty($m['gift_preferences']))) ?></strong><small>Contacts with gift preferences</small></article>
        <article><span>Agent action</span><strong>Plan</strong><small>Build a list-wide gifting plan</small></article>
      </section>

      <div class="mg-user-lists-toolbar">
        <label class="mg-user-lists-search"><span>Search contacts</span><input type="search" placeholder="Name, relationship, interests…" data-user-contact-filter></label>
        <button class="mg-user-lists-filter" type="button" data-contact-panel-open>Add contact</button>
      </div>

      <?php if ($members === []): ?>
        <section class="mg-user-lists-state" data-user-contact-empty><strong>This list is ready for people</strong><p>Search mutual followers or add a private contact such as a child, family member, or offline friend.</p><button class="mg-btn mg-btn-primary" type="button" data-contact-panel-open>Add first contact</button></section>
      <?php else: ?>
        <div class="mg-user-contact-grid" data-user-contact-grid>
          <?php foreach ($members as $member): ?>
            <article class="mg-user-contact-card" data-user-contact-card data-search-text="<?= mg_e(mb_strtolower($member['display_name'] . ' ' . $member['relationship_label'] . ' ' . $member['interests'] . ' ' . $member['gift_preferences'])) ?>">
              <div class="mg-user-contact-avatar"><?php if ($member['avatar_url'] !== ''): ?><img src="<?= mg_e($member['avatar_url']) ?>" alt=""><?php else: ?><span><?= mg_e(mb_strtoupper(mb_substr($member['display_name'], 0, 1))) ?></span><?php endif; ?></div>
              <div class="mg-user-contact-copy">
                <div class="mg-user-contact-heading"><h2><?= mg_e($member['display_name']) ?></h2><span><?= $member['contact_type'] === 'linked_user' ? 'Microgifter user' : 'Private contact' ?></span></div>
                <p><?= mg_e($member['relationship_label'] !== '' ? $member['relationship_label'] : ($member['relationship_type'] !== '' ? ucfirst($member['relationship_type']) : 'Relationship not set')) ?></p>
                <div class="mg-user-contact-facts">
                  <span><?= $member['birthdate'] ? 'Birthday ' . mg_e(date('M j', strtotime((string) $member['birthdate']))) : 'Birthday missing' ?></span>
                  <span><?= $member['phone_masked'] !== '' ? mg_e($member['phone_masked']) : 'Phone private / not saved' ?></span>
                  <span><?= $member['location'] !== '' ? mg_e($member['location']) : 'Address availability unknown' ?></span>
                </div>
                <?php if ($member['gift_preferences'] !== '' || $member['interests'] !== ''): ?><p class="mg-user-contact-preferences"><?= mg_e($member['gift_preferences'] !== '' ? $member['gift_preferences'] : $member['interests']) ?></p><?php endif; ?>
              </div>
              <div class="mg-user-contact-actions">
                <button type="button" data-agent-contact-prompt="<?= mg_e($member['display_name']) ?>">Find gifts</button>
                <button type="button" class="is-danger" data-remove-membership="<?= mg_e($member['membership_id']) ?>">Remove</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <section class="mg-user-lists-state" data-user-contact-no-results hidden><strong>No matching contacts</strong><p>Try another search term.</p></section>
      <?php endif; ?>

      <aside class="mg-contact-panel" data-contact-panel hidden aria-hidden="true" aria-labelledby="mg-contact-panel-title">
        <button class="mg-contact-panel-backdrop" type="button" data-contact-panel-close aria-label="Close add contact panel"></button>
        <section class="mg-contact-panel-dialog" role="dialog" aria-modal="true">
          <header><div><span>Personal Gifting Agent</span><h2 id="mg-contact-panel-title">Add contact</h2><p>Search Microgifter relationships first, or create a private contact.</p></div><button type="button" data-contact-panel-close aria-label="Close">×</button></header>
          <div class="mg-contact-panel-tabs" role="tablist"><button class="is-active" type="button" data-contact-panel-tab="search">Search people</button><button type="button" data-contact-panel-tab="private">New private contact</button></div>

          <section data-contact-panel-view="search">
            <label class="mg-contact-search-box">Search name or profile<input type="search" minlength="2" maxlength="80" placeholder="Start typing a name…" data-contact-search></label>
            <p class="mg-contact-search-help">Registered users require an active mutual follow. Privacy and block rules are enforced again when saving.</p>
            <div class="mg-contact-search-status" data-contact-search-status role="status" aria-live="polite"></div>
            <div class="mg-contact-search-results" data-contact-search-results></div>
          </section>

          <section data-contact-panel-view="private" hidden>
            <form class="mg-private-contact-form" data-private-contact-form>
              <div class="mg-create-form-grid mg-create-form-grid-2"><label>First name<input name="first_name" maxlength="120"></label><label>Last name<input name="last_name" maxlength="120"></label></div>
              <div class="mg-create-form-grid mg-create-form-grid-2"><label>Display name<input name="display_name" maxlength="180" required></label><label>Relationship<input name="relationship_label" maxlength="120" placeholder="Mom, teammate, client…"></label></div>
              <div class="mg-create-form-grid mg-create-form-grid-2"><label>Email<input name="email" type="email" maxlength="190"></label><label>Phone<input name="phone" inputmode="tel" maxlength="40" placeholder="Stored encrypted and shown masked"></label></div>
              <div class="mg-create-form-grid mg-create-form-grid-2"><label>Birthday<input name="birthdate" type="date"></label><label>Country<input name="country_code" maxlength="2" value="US"></label></div>
              <label>Address<input name="address_line_1" maxlength="190"></label>
              <div class="mg-create-form-grid mg-create-form-grid-3"><label>City<input name="city" maxlength="120"></label><label>State / region<input name="state_region" maxlength="120"></label><label>Postal code<input name="postal_code" maxlength="40"></label></div>
              <label>Interests<textarea name="interests" rows="3" maxlength="10000" placeholder="Music, coffee, hiking…"></textarea></label>
              <label>Gift preferences<textarea name="gift_preferences" rows="3" maxlength="10000" placeholder="Favorite products, experiences, merchants…"></textarea></label>
              <div class="mg-create-form-grid mg-create-form-grid-2"><label>Budget minimum<input name="budget_min" inputmode="decimal" placeholder="25.00"></label><label>Budget maximum<input name="budget_max" inputmode="decimal" placeholder="75.00"></label></div>
              <label>Private notes<textarea name="notes" rows="4" maxlength="10000"></textarea></label>
              <input type="hidden" name="list_ids[]" value="<?= mg_e($listPublicId) ?>">
              <div class="mg-contact-form-status" data-private-contact-status role="status" aria-live="polite"></div>
              <div class="mg-create-inline-actions"><button class="mg-btn mg-btn-primary" type="submit">Create and add</button><button class="mg-btn mg-btn-ghost" type="button" data-contact-panel-close>Cancel</button></div>
            </form>
          </section>
        </section>
      </aside>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
