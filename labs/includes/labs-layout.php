<?php
/**
 * Training Lab Stage 1 shared layout helpers.
 *
 * Stage 1 is a static UI shell only. Do not add database writes, real auth,
 * payment processing, real uploads, or reward issuing here.
 */

if (!function_exists('labs_asset')) {
    function labs_asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('labs_is_active')) {
    function labs_is_active(string $current, string $target): string
    {
        return $current === $target ? ' is-active' : '';
    }
}

if (!function_exists('labs_nav_link')) {
    function labs_nav_link(string $active, string $key, string $href, string $label, string $class = ''): void
    {
        $classes = trim($class . labs_is_active($active, $key));
        echo '<a class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
}

if (!function_exists('labs_page_start')) {
    function labs_page_start(array $page = []): void
    {
        $title = $page['title'] ?? 'Training Lab by Microgifter';
        $section = $page['section'] ?? 'public';
        $active = $page['active'] ?? '';
        $bodyClass = 'labs-shell labs-section-' . preg_replace('/[^a-z0-9\-]/i', '', $section);
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="<?php echo labs_asset('css/labs.css'); ?>">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="labs-page">
    <header class="labs-topbar">
      <a class="labs-brand" href="/">
        <span class="labs-brand-mark">MG</span>
        <span>
          <strong>Training Lab</strong>
          <small>by Microgifter</small>
        </span>
      </a>
      <nav class="labs-public-nav" aria-label="Main navigation">
        <?php labs_nav_link($active, 'home', '/', 'Home'); ?>
        <?php labs_nav_link($active, 'how-it-works', '/how-it-works.php', 'How It Works'); ?>
        <?php labs_nav_link($active, 'pricing', '/pricing.php', 'Pricing'); ?>
        <?php labs_nav_link($active, 'blog', '/blog.php', 'Blog'); ?>
        <?php labs_nav_link($active, 'signin', '/signin.php', 'Sign In'); ?>
        <?php labs_nav_link($active, 'signup', '/signup.php', 'Get Started', 'labs-nav-cta'); ?>
      </nav>
    </header>
        <?php
        if ($section === 'app' || $section === 'admin') {
            labs_workspace_start($section, $active);
        } else {
            echo '<main class="labs-main">';
        }
    }
}

if (!function_exists('labs_workspace_start')) {
    function labs_workspace_start(string $section, string $active): void
    {
        $isAdmin = $section === 'admin';
        $items = $isAdmin
            ? [
                'admin-overview' => ['/admin/index.php', 'Overview'],
                'admin-campaigns' => ['/admin/campaigns.php', 'Campaigns'],
                'admin-review' => ['/admin/review-queue.php', 'Review Queue'],
            ]
            : [
                'app-dashboard' => ['/app/index.php', 'Dashboard'],
                'app-campaigns' => ['/app/campaigns.php', 'Campaigns'],
                'app-tasks' => ['/app/sequence-tasks.php', 'Tasks'],
                'app-rewards' => ['/app/rewards.php', 'Rewards'],
                'app-wallet' => ['/app/wallet.php', 'Wallet'],
            ];
        ?>
    <div class="labs-workspace">
      <aside class="labs-sidebar">
        <div class="labs-sidebar-label"><?php echo $isAdmin ? 'Admin Backend' : 'Participant App'; ?></div>
        <nav aria-label="Workspace navigation">
          <?php foreach ($items as $key => [$href, $label]): ?>
            <a class="<?php echo labs_is_active($active, $key); ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
          <?php endforeach; ?>
        </nav>
      </aside>
      <main class="labs-main labs-workspace-main">
        <?php
    }
}

if (!function_exists('labs_page_end')) {
    function labs_page_end(array $page = []): void
    {
        $section = $page['section'] ?? 'public';
        if ($section === 'app' || $section === 'admin') {
            echo "      </main>\n    </div>\n";
        } else {
            echo "    </main>\n";
        }
        ?>
    <footer class="labs-footer">
      <span>Training Lab by Microgifter</span>
      <nav aria-label="Footer navigation">
        <a href="/about.php">About</a>
        <a href="/team.php">Team</a>
        <a href="/contact.php">Contact</a>
      </nav>
      <span>Stage 1 UI shell only</span>
    </footer>
  </div>
  <script src="<?php echo labs_asset('js/labs.js'); ?>"></script>
</body>
</html>
        <?php
    }
}
