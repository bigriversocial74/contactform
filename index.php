<?php
declare(strict_types=1);

/*
 * Microgifter public homepage redesign foundation.
 * The previous merchant-focused homepage now lives at /merchant-landing.php.
 */

$page_title = 'Microgifter | The Future of Gifting Starts Local';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
];
$page_scripts = [];
$page_manifest = [
    'id' => 'index',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Find Gifts', 'href' => '/discover.php'],
            ['label' => 'For Businesses', 'href' => '/merchant-landing.php'],
            ['label' => 'About', 'href' => '/about.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<script>
(function () {
  'use strict';
  var merchantSections = ['#how-it-works', '#rewards', '#businesses'];
  if (merchantSections.indexOf(window.location.hash) !== -1) {
    window.location.replace('/merchant-landing.php' + window.location.hash);
  }
})();
</script>

<main id="top" class="mg-home-redesign-shell" aria-label="Microgifter homepage">
  <div id="homepage-redesign-root" data-homepage-redesign-root></div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>