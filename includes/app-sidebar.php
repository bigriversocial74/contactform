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

$currentSidebarScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$useSharedMerchantNavigation = str_starts_with($currentSidebarScript, 'merchant-')
    && ($useSharedMerchantNavigation ?? true) !== false;

if ($useSharedMerchantNavigation) {
    require_once __DIR__ . '/merchant-navigation.php';
    $appSidebarVariant = 'merchant';
    $appSidebarNav = mg_merchant_navigation_sidebar($appSidebarActive);
    $appSidebarActive = mg_merchant_navigation_active_key($appSidebarActive);
    $appSidebarLabel = 'Merchant';
}

$appSidebarAgentBadges = $appSidebarVariant === 'merchant';

if (!$appSidebarNav) {
    $appSidebarNav = [
        'account' => ['section' => 'Overview', 'label' => 'Account', 'detail' => 'Profile and access', 'href' => '/account.php', 'visible' => true],
        'inbox' => ['label' => 'Inbox', 'detail' => 'Gifts, rewards, and ownership', 'href' => '/inbox.php', 'visible' => true],
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

if ($appSidebarVariant === 'utility' && !isset($appSidebarNav['loyalty-cards'])) {
    $loyaltyItem = [
        'section' => 'Customer',
        'label' => 'Loyalty Cards',
        'detail' => 'Saved stamp and visit cards',
        'href' => '/loyalty-cards.php',
        'visible' => true,
        'active' => $appSidebarActive === 'loyalty-cards',
    ];
    $withLoyalty = [];
    $inserted = false;
    foreach ($appSidebarNav as $key => $item) {
        $withLoyalty[$key] = $item;
        if ($key === 'feed-following') {
            $withLoyalty['loyalty-cards'] = $loyaltyItem;
            $inserted = true;
        }
    }
    if (!$inserted) {
        $withLoyalty['loyalty-cards'] = $loyaltyItem;
    }
    $appSidebarNav = $withLoyalty;
}

if ($appSidebarVariant === 'utility' && !isset($appSidebarNav['training-lab'])) {
    $trainingLabItem = [
        'section' => 'Account',
        'label' => 'Training Lab',
        'detail' => 'Proof-based training and rewards',
        'href' => '/training-lab.php',
        'visible' => $user !== null,
        'active' => $appSidebarActive === 'training-lab',
    ];
    $withTrainingLab = [];
    $inserted = false;
    foreach ($appSidebarNav as $key => $item) {
        $withTrainingLab[$key] = $item;
        if ($key === 'messages') {
            $withTrainingLab['training-lab'] = $trainingLabItem;
            $inserted = true;
        }
    }
    if (!$inserted) {
        $withTrainingLab['training-lab'] = $trainingLabItem;
    }
    $appSidebarNav = $withTrainingLab;
}

$currentPath = '/' . ltrim((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$renderSidebarItem = static function (string $key, array $item) use ($appSidebarActive, $currentPath): void {
    $href = (string) ($item['href'] ?? '#');
    $isActive = (bool) ($item['active'] ?? false)
        || $appSidebarActive === $key
        || ($href !== '#' && $href === $currentPath);
    $label = (string) ($item['label'] ?? $key);
    $detail = (string) ($item['detail'] ?? '');
    $isButton = (bool) ($item['button'] ?? false);
    $dataTab = trim((string) ($item['data_tab'] ?? ''));
    $badge = '';
    if ($isButton) {
        echo '<button class="' . ($isActive ? 'is-active' : '') . '" type="button"'
            . ($dataTab !== '' ? ' data-crm-tab="' . mg_e($dataTab) . '"' : '')
            . '><strong>' . mg_e($label) . '</strong>'
            . ($detail !== '' ? '<span>' . mg_e($detail) . '</span>' : '')
            . $badge . '</button>';
        return;
    }
    echo '<a class="' . ($isActive ? 'is-active' : '') . '" href="' . mg_e($href) . '"><strong>'
        . mg_e($label) . '</strong>'
        . ($detail !== '' ? '<span>' . mg_e($detail) . '</span>' : '')
        . $badge . '</a>';
};
?>
<?php if ($appSidebarAgentBadges): ?>
<link rel="stylesheet" href="/assets/css/merchant-agent-notification-digest.css">
<link rel="stylesheet" href="/assets/css/merchant-agent-memory.css">
<link rel="stylesheet" href="/assets/css/merchant-sidebar-accordion.css?v=1.0.0">
<script src="/assets/js/merchant-agent-notification-digest.js" defer></script>
<script src="/assets/js/merchant-agent-memory.js" defer></script>
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
    <?php if ($appSidebarVariant === 'merchant'): ?>
      <?php
        $merchantPrimaryItems = [];
        $merchantSectionItems = [];
        foreach ($appSidebarNav as $key => $item) {
            if (isset($item['visible']) && !$item['visible']) {
                continue;
            }
            $section = trim((string) ($item['section'] ?? ''));
            if ($section === '') {
                $merchantPrimaryItems[$key] = $item;
                continue;
            }
            $merchantSectionItems[$section][$key] = $item;
        }
      ?>
      <div class="mg-merchant-nav-primary" data-merchant-nav-primary>
        <?php foreach ($merchantPrimaryItems as $key => $item): ?>
          <?php $renderSidebarItem((string) $key, $item); ?>
        <?php endforeach; ?>
      </div>
      <div class="mg-merchant-nav-accordions" data-merchant-nav-accordions>
        <?php foreach ($merchantSectionItems as $section => $items): ?>
          <?php
            $sectionHasActiveItem = false;
            foreach ($items as $key => $item) {
                $href = (string) ($item['href'] ?? '#');
                if ((bool) ($item['active'] ?? false) || $appSidebarActive === (string) $key || ($href !== '#' && $href === $currentPath)) {
                    $sectionHasActiveItem = true;
                    break;
                }
            }
          ?>
          <details class="mg-side-nav-accordion" data-merchant-nav-section="<?= mg_e($section) ?>"<?= $sectionHasActiveItem ? ' open' : '' ?>>
            <summary><strong><?= mg_e($section) ?></strong><i aria-hidden="true"></i></summary>
            <div class="mg-side-nav-accordion-items">
              <?php foreach ($items as $key => $item): ?>
                <?php $renderSidebarItem((string) $key, $item); ?>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php $lastSection = null; ?>
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
          $renderSidebarItem((string) $key, $item);
        ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <?= $appSidebarAfterNav ?>

  <?php if ($appSidebarFooter !== ''): ?>
    <footer class="mg-universal-sidebar-footer"><?= $appSidebarFooter ?></footer>
  <?php endif; ?>
</aside>
