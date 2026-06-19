<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/page.php';

$inferred_page_id = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'public.php'), '.php');
$manifest_overrides = is_array($page_manifest ?? null) ? $page_manifest : [];

$manifest_seed = [
    'id' => $manifest_overrides['id'] ?? $inferred_page_id,
    'title' => $page_title ?? 'Microgifter',
    'section' => $page_section ?? 'public',
    'header_mode' => $header_mode ?? 'public',
    'styles' => is_array($page_styles ?? null) ? $page_styles : [],
    'scripts' => is_array($page_scripts ?? null) ? $page_scripts : [],
];

if (isset($page_body_class) && trim((string) $page_body_class) !== '') {
    $manifest_seed['body_class'] = (string) $page_body_class;
}

if (isset($header_controls) && is_array($header_controls)) {
    $manifest_seed['header_controls'] = $header_controls;
}

$page_manifest = mg_page_manifest(
    array_replace_recursive($manifest_seed, $manifest_overrides)
);

$page_assets_resolved = mg_resolve_page_assets($page_manifest);

$page_title = $page_manifest['title'];
$page_section = $page_manifest['section'];
$header_mode = $page_manifest['header_mode'];
$header_controls = $page_manifest['header_controls'];
$page_styles = $page_assets_resolved['styles'];
$page_scripts = $page_assets_resolved['scripts'];
$page_body_class = trim((string) $page_manifest['body_class']);

$page_onboarding = is_array($page_manifest['onboarding'] ?? null)
    ? $page_manifest['onboarding']
    : mg_onboarding_config($page_manifest['id']);

$agent_tab = $agent_tab ?? '';
$section_css = $section_css ?? null;

$is_app_page = in_array(
    $header_mode,
    ['agent', 'account', 'crm', 'builder'],
    true
);

$user = $is_app_page
    ? mg_require_auth()
    : mg_current_user();

if ($user && in_array((string) $page_manifest['id'], ['home', 'index'], true)) {
    header('Cache-Control: no-store, private');
    header('Location: /inbox.php', true, 302);
    exit;
}

if ($is_app_page) {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}

$display_name = $user
    ? mg_user_display_name()
    : 'Account';

$display_email = $user
    ? (string) ($user['email'] ?? '')
    : 'Guest';

$display_initial = strtoupper(
    substr($display_name !== '' ? $display_name : 'A', 0, 1)
);

$user_permissions = is_array($user['permissions'] ?? null)
    ? $user['permissions']
    : [];

$user_roles = is_array($user['roles'] ?? null)
    ? $user['roles']
    : [];

$can_sales_crm = $user && (
    in_array('sales.leads.view_own', $user_permissions, true)
    || in_array('sales.leads.view_all', $user_permissions, true)
    || in_array('super_admin', $user_roles, true)
);

$admin_navigation_permissions = [
    'admin.users.view',
    'admin.users.manage',
    'admin.audit.view',
    'admin.health.view',
    'security.logs.view',
    'admin.security_logs.view',
    'admin.sessions.view',
    'operational.alerts.view',
    'demand.dashboard.view',
    'intelligence.dashboard.view',
    'merchant.payments.view',
    'subscriptions.admin',
    'microgift.operations.view',
    'tips.reverse',
];

$can_admin_dashboard = $user && (
    in_array('super_admin', $user_roles, true)
    || count(array_intersect(
        $admin_navigation_permissions,
        $user_permissions
    )) > 0
);

$public_header_active = isset($public_header_active)
    ? (string) $public_header_active
    : (string) $page_section;

$public_header_account_href = $user
    ? '/account.php'
    : '/signin.php';

$public_header_account_role = $user
    ? 'Member'
    : 'Guest';

$public_header_links = [
    [
        'key' => 'demo',
        'label' => 'Book A Demo',
        'href' => '/learn-more.php',
        'class' => 'mg-site-header__demo',
    ],
    [
        'key' => 'corporate',
        'label' => 'Corporate Gifting',
        'href' => '/corporate.php',
        'class' => 'mg-site-header__text-link',
    ],
    [
        'key' => 'retail-subscriptions',
        'label' => 'Retail Subscriptions',
        'href' => '/retail.php',
        'class' => 'mg-site-header__text-link',
    ],
    [
        'key' => 'locations',
        'label' => 'Locations',
        'href' => '/locations.php',
        'class' => 'mg-site-header__text-link',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= mg_e(mg_csrf_token()) ?>">
<title><?= mg_e($page_title) ?></title>

<link rel="stylesheet" href="/assets/css/microgifter.css">

<?php if ($is_app_page): ?>
<link rel="stylesheet" href="/assets/css/app-shell.css">
<?php endif; ?>

<?php if ($section_css): ?>
<link rel="stylesheet" href="<?= mg_e($section_css) ?>">
<?php endif; ?>

<?php foreach ($page_styles as $style): ?>
<link rel="stylesheet" href="<?= mg_e($style) ?>">
<?php endforeach; ?>

<?php if ($is_app_page): ?>
<link rel="stylesheet" href="/assets/css/mobile-app.css">
<link rel="stylesheet" href="/assets/css/app-fixes.css">
<?php endif; ?>

<?php if (!$is_app_page): ?>
<style>
:root{
  --mg-site-header-dark:#071225;
  --mg-site-header-muted:#64748b;
  --mg-site-header-border:#dbe5f1;
  --mg-site-header-purple:#7c3aed;
  --mg-site-header-teal:#20bfd2;
}

.mg-site-header,
.mg-site-header *{
  box-sizing:border-box;
}

.mg-site-header{
  position:sticky;
  top:0;
  z-index:900;
  width:100%;
  border-bottom:1px solid rgba(219,229,241,.92);
  background:rgba(255,255,255,.96);
  box-shadow:0 8px 24px rgba(15,23,42,.06);
  backdrop-filter:blur(18px);
  -webkit-backdrop-filter:blur(18px);
}

.mg-site-header__inner{
  width:100%;
  max-width:none;
  min-height:72px;
  margin:0;
  padding:0 2px;
  display:flex;
  align-items:center;
  gap:18px;
}

.mg-site-header__menu-toggle{
  display:none;
  width:34px;
  height:34px;
  flex:0 0 auto;
  padding:0;
  border:0;
  background:transparent;
  color:var(--mg-site-header-dark);
  cursor:pointer;
}

.mg-site-header__menu-toggle span{
  display:block;
  width:21px;
  height:2px;
  margin:4px auto;
  border-radius:999px;
  background:currentColor;
  transition:transform .2s ease,opacity .2s ease;
}

.mg-site-header__menu-toggle[aria-expanded="true"] span:nth-child(1){
  transform:translateY(6px) rotate(45deg);
}

.mg-site-header__menu-toggle[aria-expanded="true"] span:nth-child(2){
  opacity:0;
}

.mg-site-header__menu-toggle[aria-expanded="true"] span:nth-child(3){
  transform:translateY(-6px) rotate(-45deg);
}

.mg-site-header__brand{
  display:inline-flex;
  align-items:center;
  flex:0 0 auto;
  color:var(--mg-site-header-dark);
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.055em;
}

.mg-site-header__search{
  position:relative;
  flex:1 1 420px;
  max-width:520px;
  min-width:220px;
}

.mg-site-header__search::before{
  content:"⌕";
  position:absolute;
  top:50%;
  left:14px;
  transform:translateY(-50%);
  color:#94a3b8;
  font-size:17px;
  pointer-events:none;
}

.mg-site-header__search input{
  width:100%;
  height:40px;
  padding:0 14px 0 40px;
  border:1px solid var(--mg-site-header-border);
  border-radius:12px;
  outline:none;
  background:#f8fafc;
  color:var(--mg-site-header-dark);
  font-size:14px;
}

.mg-site-header__search input:focus{
  border-color:#9ca3af;
  background:#fff;
  box-shadow:0 0 0 3px rgba(103,103,103,.08);
}

.mg-site-header__nav{
  margin-left:auto;
  display:flex;
  align-items:center;
  gap:18px;
}

.mg-site-header__demo-slot{
  margin-left:0;
  display:flex;
  align-items:center;
}

.mg-site-header__nav a{
  white-space:nowrap;
  text-decoration:none;
}

.mg-site-header__demo{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:40px;
  padding:0 18px;
  border:2px solid #676767;
  border-radius:12px;
  color:#3f3f3f;
  background:transparent;
  box-shadow:none;
  font-size:14px;
  font-weight:900;
  transition:background .18s ease,color .18s ease,border-color .18s ease;
}

.mg-site-header__demo:hover{
  color:#fff;
  border-color:#676767;
  background:#676767;
  transform:none;
  box-shadow:none;
}

.mg-site-header__text-link{
  padding:6px 0;
  color:#334155;
  font-size:14px;
  font-weight:780;
}

.mg-site-header__text-link:hover,
.mg-site-header__text-link.is-active{
  color:var(--mg-site-header-purple);
}

.mg-site-header__account{
  position:relative;
  margin-left:0;
}

.mg-site-header__account-trigger{
  display:inline-flex;
  align-items:center;
  gap:10px;
  min-height:44px;
  padding:5px 10px 5px 6px;
  border:1px solid var(--mg-site-header-border);
  border-radius:14px;
  background:#fff;
  color:var(--mg-site-header-dark);
  cursor:pointer;
}

.mg-site-header__avatar{
  width:32px;
  height:32px;
  display:grid;
  place-items:center;
  border-radius:10px;
  color:#475569;
  background:#f1f5f9;
  border:1px solid #dbe5f1;
  font-size:13px;
  font-weight:950;
}

.mg-site-header__account-copy{
  display:grid;
  gap:1px;
  text-align:left;
  line-height:1.1;
}

.mg-site-header__account-name{
  color:var(--mg-site-header-dark);
  font-size:13px;
  font-weight:850;
}

.mg-site-header__account-role{
  color:#94a3b8;
  font-size:9px;
  font-weight:850;
  letter-spacing:.08em;
  text-transform:uppercase;
}

.mg-site-header__caret{
  color:#94a3b8;
  font-size:12px;
}

.mg-site-header__account-menu{
  position:absolute;
  top:calc(100% + 10px);
  right:0;
  width:230px;
  padding:10px;
  border:1px solid var(--mg-site-header-border);
  border-radius:16px;
  background:#fff;
  box-shadow:0 24px 60px rgba(15,23,42,.16);
  opacity:0;
  visibility:hidden;
  transform:translateY(-6px);
  transition:opacity .18s ease,transform .18s ease,visibility .18s ease;
}

.mg-site-header__account.is-open .mg-site-header__account-menu{
  opacity:1;
  visibility:visible;
  transform:translateY(0);
}

.mg-site-header__account-menu a{
  display:flex;
  align-items:center;
  min-height:42px;
  padding:0 12px;
  border-radius:10px;
  color:#334155;
  text-decoration:none;
  font-size:13px;
  font-weight:800;
}

.mg-site-header__account-menu a:hover{
  background:#f8fafc;
  color:var(--mg-site-header-purple);
}

.mg-site-header__overlay{
  position:fixed;
  inset:0;
  z-index:998;
  padding:0;
  border:0;
  background:rgba(7,18,37,.52);
  opacity:0;
  pointer-events:none;
  transition:opacity .22s ease;
}

.mg-site-header__overlay.is-open{
  opacity:1;
  pointer-events:auto;
}

.mg-site-header__drawer{
  position:fixed;
  inset:0 auto 0 0;
  z-index:999;
  width:min(360px,88vw);
  height:100dvh;
  padding:18px;
  overflow-y:auto;
  border-right:1px solid #e2e8f0;
  background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);
  box-shadow:24px 0 70px rgba(15,23,42,.22);
  transform:translateX(-104%);
  transition:transform .26s ease;
}

.mg-site-header__drawer.is-open{
  transform:translateX(0);
}

.mg-site-header__drawer-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  padding:2px 2px 18px;
  border-bottom:1px solid #e2e8f0;
}

.mg-site-header__drawer-brand{
  color:var(--mg-site-header-dark);
  text-decoration:none;
  font-size:21px;
  font-weight:950;
  letter-spacing:-.045em;
}

.mg-site-header__drawer-close{
  width:34px;
  height:34px;
  padding:0;
  border:0;
  background:transparent;
  color:var(--mg-site-header-dark);
  font-size:26px;
  line-height:1;
  cursor:pointer;
}

.mg-site-header__drawer-search{
  margin-top:18px;
}

.mg-site-header__drawer-search input{
  width:100%;
  height:46px;
  padding:0 14px;
  border:1px solid #dbe5f1;
  border-radius:12px;
  outline:none;
  background:#fff;
  color:var(--mg-site-header-dark);
  font-size:14px;
}

.mg-site-header__drawer-search input:focus{
  border-color:#9ca3af;
  box-shadow:0 0 0 3px rgba(103,103,103,.08);
}

.mg-site-header__drawer nav{
  display:grid;
  gap:4px;
  margin-top:18px;
}

.mg-site-header__drawer nav a{
  display:flex;
  align-items:center;
  justify-content:space-between;
  min-height:48px;
  padding:0 12px;
  border:0;
  border-radius:12px;
  background:transparent;
  color:var(--mg-site-header-dark);
  text-decoration:none;
  font-size:15px;
  font-weight:850;
}

.mg-site-header__drawer nav a:hover{
  background:#f1f5f9;
}

.mg-site-header__drawer nav a::after{
  content:"→";
  color:var(--mg-site-header-purple);
  font-size:16px;
}

.mg-site-header__drawer nav .mg-site-header__drawer-demo{
  min-height:50px;
  margin-bottom:10px;
  padding:0 16px;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#6d5dfc,var(--mg-site-header-purple));
  box-shadow:0 14px 28px rgba(124,58,237,.2);
}

.mg-site-header__drawer nav .mg-site-header__drawer-demo::after{
  color:#fff;
}

.mg-site-header__drawer-account{
  margin-top:18px;
  padding-top:18px;
  border-top:1px solid #e2e8f0;
}

.mg-site-header__drawer-account a{
  display:flex;
  align-items:center;
  gap:10px;
  color:#334155;
  text-decoration:none;
  font-size:14px;
  font-weight:850;
}

body.mg-site-header-menu-open{
  overflow:hidden;
}

@media(max-width:860px){
  .mg-site-header__inner{
    min-height:64px;
    padding:0 14px;
    gap:10px;
  }

  .mg-site-header__menu-toggle{
    display:block;
  }

  .mg-site-header__search,
  .mg-site-header__nav,
  .mg-site-header__demo-slot{
    display:none;
  }

  .mg-site-header__brand{
    font-size:21px;
  }

  .mg-site-header__account{
    margin-left:auto;
  }

  .mg-site-header__account-copy,
  .mg-site-header__caret{
    display:none;
  }

  .mg-site-header__account-trigger{
    min-height:38px;
    padding:3px;
    border-radius:11px;
  }

  .mg-site-header__avatar{
    width:30px;
    height:30px;
  }
}
</style>
<?php endif; ?>
</head>

<body
  class="mg-page mg-section-<?= mg_e($page_section) ?><?= $is_app_page ? ' mg-app-page' : '' ?><?= $page_body_class !== '' ? ' ' . mg_e($page_body_class) : '' ?>"
  data-authenticated="<?= $user ? 'true' : 'false' ?>"
  data-page-id="<?= mg_e((string) $page_manifest['id']) ?>"
>

<?php if ($is_app_page): ?>

  <?php require __DIR__ . '/header-components/app-header.php'; ?>

<?php else: ?>

<header class="mg-site-header" data-mg-site-header>
  <div class="mg-site-header__inner">
    <button
      class="mg-site-header__menu-toggle"
      type="button"
      aria-label="Open navigation menu"
      aria-controls="mg-site-header-drawer"
      aria-expanded="false"
      data-mg-site-header-toggle
    >
      <span></span>
      <span></span>
      <span></span>
    </button>

    <a class="mg-site-header__brand" href="/">
      Microgifter
    </a>

    <form class="mg-site-header__search" action="/search.php" method="get" role="search">
      <input
        type="search"
        name="q"
        placeholder="Search Microgifter"
        aria-label="Search Microgifter"
        autocomplete="off"
      >
    </form>

    <nav class="mg-site-header__nav" aria-label="Primary navigation">
      <?php foreach ($public_header_links as $link): ?>
        <?php if ($link['key'] === 'demo') { continue; } ?>
        <a
          class="<?= mg_e($link['class']) ?><?= $public_header_active === $link['key'] ? ' is-active' : '' ?>"
          href="<?= mg_e($link['href']) ?>"
        >
          <?= mg_e($link['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="mg-site-header__demo-slot">
      <a class="mg-site-header__demo" href="/learn-more.php">
        Book A Demo
      </a>
    </div>

    <div class="mg-site-header__account" data-mg-site-account>
      <button
        class="mg-site-header__account-trigger"
        type="button"
        aria-expanded="false"
        data-mg-site-account-trigger
      >
        <span class="mg-site-header__avatar">
          <?= mg_e($display_initial) ?>
        </span>

        <span class="mg-site-header__account-copy">
          <span class="mg-site-header__account-name">
            <?= mg_e($display_name) ?>
          </span>
          <span class="mg-site-header__account-role">
            <?= mg_e($public_header_account_role) ?>
          </span>
        </span>

        <span class="mg-site-header__caret">⌄</span>
      </button>

      <div class="mg-site-header__account-menu">
        <?php if ($user): ?>
          <a href="/account.php">Account</a>
          <a href="/inbox.php">Inbox</a>
          <?php if ($can_sales_crm): ?>
            <a href="/crm.php">Sales CRM</a>
          <?php endif; ?>
          <?php if ($can_admin_dashboard): ?>
            <a href="/admin.php">Admin</a>
          <?php endif; ?>
          <a href="/logout.php">Sign out</a>
        <?php else: ?>
          <a href="/signin.php">Sign in</a>
          <a href="/signup.php">Create account</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<button
  class="mg-site-header__overlay"
  type="button"
  aria-label="Close navigation menu"
  data-mg-site-header-overlay
></button>

<aside
  class="mg-site-header__drawer"
  id="mg-site-header-drawer"
  aria-hidden="true"
  data-mg-site-header-drawer
>
  <div class="mg-site-header__drawer-head">
    <a class="mg-site-header__drawer-brand" href="/">
      Microgifter
    </a>

    <button
      class="mg-site-header__drawer-close"
      type="button"
      aria-label="Close navigation menu"
      data-mg-site-header-close
    >
      ×
    </button>
  </div>

  <form class="mg-site-header__drawer-search" action="/search.php" method="get" role="search">
    <input
      type="search"
      name="q"
      placeholder="Search Microgifter"
      aria-label="Search Microgifter"
      autocomplete="off"
    >
  </form>

  <nav aria-label="Mobile navigation">
    <a class="mg-site-header__drawer-demo" href="/learn-more.php">
      Book A Demo
    </a>
    <a href="/corporate.php">Corporate Gifting</a>
    <a href="/retail.php">Retail Subscriptions</a>
    <a href="/locations.php">Locations</a>
  </nav>

  <div class="mg-site-header__drawer-account">
    <a href="<?= mg_e($public_header_account_href) ?>">
      <span class="mg-site-header__avatar">
        <?= mg_e($display_initial) ?>
      </span>
      <span><?= mg_e($display_name) ?></span>
    </a>
  </div>
</aside>

<script>
(function () {
  const initPublicHeader = function () {
    const toggle = document.querySelector('[data-mg-site-header-toggle]');
    const overlay = document.querySelector('[data-mg-site-header-overlay]');
    const drawer = document.querySelector('[data-mg-site-header-drawer]');
    const closeButton = document.querySelector('[data-mg-site-header-close]');
    const account = document.querySelector('[data-mg-site-account]');
    const accountTrigger = document.querySelector('[data-mg-site-account-trigger]');

    if (toggle && overlay && drawer && closeButton) {
      const openMenu = function () {
        toggle.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.classList.add('mg-site-header-menu-open');
        closeButton.focus();
      };

      const closeMenu = function (restoreFocus = true) {
        toggle.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.classList.remove('mg-site-header-menu-open');

        if (restoreFocus) {
          toggle.focus();
        }
      };

      toggle.addEventListener('click', function () {
        if (toggle.getAttribute('aria-expanded') === 'true') {
          closeMenu();
        } else {
          openMenu();
        }
      });

      overlay.addEventListener('click', function () {
        closeMenu();
      });

      closeButton.addEventListener('click', function () {
        closeMenu();
      });

      drawer.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
          closeMenu(false);
        });
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
          closeMenu();
        }
      });

      window.addEventListener('resize', function () {
        if (window.innerWidth > 860 && drawer.classList.contains('is-open')) {
          closeMenu(false);
        }
      });
    }

    if (account && accountTrigger) {
      accountTrigger.addEventListener('click', function () {
        const isOpen = account.classList.toggle('is-open');
        accountTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      document.addEventListener('click', function (event) {
        if (!account.contains(event.target)) {
          account.classList.remove('is-open');
          accountTrigger.setAttribute('aria-expanded', 'false');
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          account.classList.remove('is-open');
          accountTrigger.setAttribute('aria-expanded', 'false');
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      initPublicHeader,
      {once:true}
    );
  } else {
    initPublicHeader();
  }
})();
</script>

<?php endif; ?>

<script
  type="application/json"
  id="mg-page-manifest"
><?= json_encode(
    $page_manifest,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?></script>

<script
  type="application/json"
  id="mg-page-onboarding"
><?= json_encode(
    $page_onboarding,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) ?></script>

<?php if ($is_app_page): ?>
<div
  class="mg-mobile-sidebar-backdrop"
  data-mobile-sidebar-backdrop
></div>
<?php endif; ?>

<main class="mg-main">
