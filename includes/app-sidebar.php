<?php
declare(strict_types=1);

$user = $user ?? mg_current_user();
$appSidebarVariant = trim((string) ($appSidebarVariant ?? 'utility')) ?: 'utility';
$appSidebarLabel = trim((string) ($appSidebarLabel ?? match ($appSidebarVariant) {
    'merchant' => 'Merchant',
    'crm' => 'CRM',
    'admin' => 'Admin',
    default => 'Workspace',
}));
$appSidebarActive = trim((string) ($appSidebarActive ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php')));
$appSidebarNav = is_array($appSidebarNav ?? null) ? $appSidebarNav : [];
$appSidebarBeforeNav = (string) ($appSidebarBeforeNav ?? '');
$appSidebarAfterNav = (string) ($appSidebarAfterNav ?? '');
$appSidebarFooter = (string) ($appSidebarFooter ?? '');
$appSidebarSearchPlaceholder = trim((string) ($appSidebarSearchPlaceholder ?? ''));
$appSidebarSearchLabel = trim((string) ($appSidebarSearchLabel ?? $appSidebarSearchPlaceholder));
$appSidebarSearchName = trim((string) ($appSidebarSearchName ?? 'q'));
$appSidebarSearchDataAttr = trim((string) ($appSidebarSearchDataAttr ?? ''));
$appSidebarSearchSelectHtml = (string) ($appSidebarSearchSelectHtml ?? '');
$appSidebarTools = (string) ($appSidebarTools ?? '');
$appSidebarCompact = (bool) ($appSidebarCompact ?? true);
$appSidebarAgentBadges = $appSidebarVariant === 'merchant';

if (!$appSidebarNav) {
    $appSidebarNav = [
        'account' => ['section' => 'Overview', 'label' => 'Account', 'detail' => 'Profile and access', 'href' => '/account.php', 'visible' => true],
        'wallet' => ['label' => 'Wallet', 'detail' => 'Rewards and balance', 'href' => '/wallet.php', 'visible' => true],
        'merchant' => ['section' => 'Commerce', 'label' => 'Merchant', 'detail' => 'Business workspace', 'href' => '/merchant.php', 'visible' => true],
        'messages' => ['label' => 'Messages', 'detail' => 'Gift conversations', 'href' => '/messages.php', 'visible' => true],
        'feed' => ['section' => 'Community', 'label' => 'Feed', 'detail' => 'Public activity', 'href' => '/feed.php', 'visible' => true],
    ];
    if (($can_sales_crm ?? false) === true) {
        $appSidebarNav['sales-crm'] = ['section' => 'CRM', 'label' => 'Sales CRM', 'detail' => 'Leads and pipeline', 'href' => '/sales-crm.php', 'visible' => true];
    }
    if (($can_admin_dashboard ?? false) === true) {
        $appSidebarNav['admin'] = ['section' => 'Admin', 'label' => 'Admin', 'detail' => 'Platform controls', 'href' => '/account-admin.php', 'visible' => true];
    }
}

$currentPath = '/' . ltrim((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$lastSection = null;
?>
<?php if ($appSidebarAgentBadges): ?>
<link rel="stylesheet" href="/assets/css/merchant-agent-notification-digest.css">
<script src="/assets/js/merchant-agent-notification-digest.js" defer></script>
<?php endif; ?>
<aside class="mg-app-sidebar mg-universal-sidebar mg-<?= mg_e($appSidebarVariant) ?>-sidebar <?= $appSidebarCompact ? 'is-text-sidebar' : '' ?>" data-app-sidebar data-sidebar-variant="<?= mg_e($appSidebarVariant) ?>">
  <div class="mg-app-sidebar-brand mg-universal-sidebar-brand">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    <?php if ($appSidebarTools !== ''): ?>
      <div class="mg-universal-sidebar-tools"><?= $appSidebarTools ?></div>
    <?php elseif ($appSidebarLabel !== ''): ?>
      <span class="mg-universal-sidebar-label"><?= mg_e($appSidebarLabel) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($appSidebarSearchPlaceholder !== ''): ?>
    <div class="mg-sidebar-search mg-universal-sidebar-search">
      <input
        type="search"
        name="<?= mg_e($appSidebarSearchName) ?>"
        placeholder="<?= mg_e($appSidebarSearchPlaceholder) ?>"
        aria-label="<?= mg_e($appSidebarSearchLabel !== '' ? $appSidebarSearchLabel : $appSidebarSearchPlaceholder) ?>"
        <?= $appSidebarSearchDataAttr !== '' ? mg_e($appSidebarSearchDataAttr) : '' ?>
      >
      <?= $appSidebarSearchSelectHtml ?>
    </div>
  <?php endif; ?>

  <?= $appSidebarBeforeNav ?>

  <nav class="mg-app-side-nav mg-universal-side-nav" aria-label="<?= mg_e($appSidebarLabel !== '' ? $appSidebarLabel . ' navigation' : 'Workspace navigation') ?>">
    <?php foreach ($appSidebarNav as $key => $item): ?>
      <?php
        if (isset($item['visible']) && !$item['visible']) {
            continue;
        }
        $section = trim((string) ($item['section'] ?? ''));
        if ($section !== '' && $section !== $lastSection) {
            echo '<span class="mg-side-nav-section">' . mg_e($section) . '</span>';
            $lastSection = $section;
        }
        $href = (string) ($item['href'] ?? '#');
        $isActive = (bool) ($item['active'] ?? false)
            || $appSidebarActive === (string) $key
            || ($href !== '#' && $href === $currentPath);
        $label = (string) ($item['label'] ?? $key);
        $detail = (string) ($item['detail'] ?? '');
        $isButton = (bool) ($item['button'] ?? false);
        $dataTab = trim((string) ($item['data_tab'] ?? ''));
        $badge = $appSidebarAgentBadges ? '<em class="mg-agent-nav-badge" data-agent-nav-badge="' . mg_e((string)$key) . '" hidden>0</em>' : '';
      ?>
      <?php if ($isButton): ?>
        <button class="<?= $isActive ? 'is-active' : '' ?>" type="button"<?= $dataTab !== '' ? ' data-crm-tab="' . mg_e($dataTab) . '"' : '' ?>><strong><?= mg_e($label) ?></strong><?php if ($detail !== ''): ?><span><?= mg_e($detail) ?></span><?php endif; ?><?= $badge ?></button>
      <?php else: ?>
        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= mg_e($href) ?>"><strong><?= mg_e($label) ?></strong><?php if ($detail !== ''): ?><span><?= mg_e($detail) ?></span><?php endif; ?><?= $badge ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <?= $appSidebarAfterNav ?>

  <?php if ($appSidebarFooter !== ''): ?>
    <footer class="mg-universal-sidebar-footer"><?= $appSidebarFooter ?></footer>
  <?php endif; ?>
</aside>