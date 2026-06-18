<?php
declare(strict_types=1);
$public_header_config = is_array($page_manifest['public_header'] ?? null) ? $page_manifest['public_header'] : [];
$public_nav_links = is_array($public_header_config['links'] ?? null) ? $public_header_config['links'] : [];
?>
<header class="mg-site-header mg-unified-header nav" data-mg-universal-header data-public-header data-header-variant="<?= $user ? 'logged-in' : 'logged-out' ?>">
  <div class="mg-header-inner nav-inner">
    <div class="mg-header-left">
      <a class="mg-brand brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
      <?php if (!$user && $public_nav_links): ?>
        <nav class="mg-site-nav mg-public-nav" aria-label="Primary navigation">
          <?php foreach ($public_nav_links as $public_header_link): ?>
            <a href="<?= mg_e((string) ($public_header_link['href'] ?? '#')) ?>"><?= mg_e((string) ($public_header_link['label'] ?? 'Learn More')) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <div class="mg-header-actions" data-header-template="<?= $user ? 'logged-in-public' : 'logged-out-public' ?>">
      <div class="mg-account-menu" data-mg-auth-menu>
        <button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false">
          <span class="mg-avatar"><?= mg_e($user ? $display_initial : 'A') ?></span>
          <span class="mg-account-copy">
            <span class="mg-account-name"><?= mg_e($user ? $display_name : 'Account') ?></span>
            <span class="mg-account-role"><?= mg_e($user ? (string) ($user_roles[0] ?? 'member') : 'Guest') ?></span>
          </span>
          <span class="mg-account-caret">⌄</span>
        </button>

        <div class="mg-account-actions">
          <div class="mg-account-menu-head">
            <span class="mg-account-status-light"></span>
            <span class="mg-account-head-copy">
              <span class="mg-account-head-name"><?= mg_e($user ? $display_name : 'Account') ?></span>
              <span class="mg-account-head-email"><?= mg_e($user ? $display_email : 'Guest') ?></span>
            </span>
            <span class="mg-account-session-label">SESSION</span>
          </div>

          <?php if ($user): ?>
            <?php $menuIndex = 1; ?>
            <a class="mg-account-action" href="/account.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Account dashboard</span></a>
            <a class="mg-account-action" href="/account-commerce.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Commerce center</span></a>
            <a class="mg-account-action" href="/agent.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Open live agent</span></a>
            <?php if ($can_sales_crm): ?><a class="mg-account-action" href="/sales-crm.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>CRM dashboard</span></a><?php endif; ?>
            <?php if ($can_admin_dashboard): ?><a class="mg-account-action" href="/account-admin.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Admin dashboard</span></a><?php endif; ?>
            <button class="mg-account-action mg-account-logout" type="button" data-auth-logout><span class="mg-account-index">00</span><span>Sign out</span></button>
          <?php else: ?>
            <a class="mg-account-action" href="/signin.php"><span class="mg-account-index">01</span><span>Sign in</span></a>
            <a class="mg-account-action" href="/signup.php"><span class="mg-account-index">02</span><span>Create account</span></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>