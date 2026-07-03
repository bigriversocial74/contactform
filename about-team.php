<?php
declare(strict_types=1);

$page_title = 'About the Team | Microgifter';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/about-team.css',
];
$page_scripts = [];
$page_manifest = [
    'id' => 'about-team',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Merchant', 'href' => '/merchant.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'about-team',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="mg-team-page" aria-labelledby="mgTeamTitle">
  <div class="mg-team-bg" aria-hidden="true">
    <img src="/images/globe.png" alt="">
  </div>

  <div class="mg-team-shell">
    <header class="mg-team-hero">
      <span class="mg-team-eyebrow">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20a6 6 0 0 1 12 0v.5h-12V20Zm11.3.5h7.7V20a5.5 5.5 0 0 0-8.2-4.8 7.8 7.8 0 0 1 .5 5.3Z"/></svg>
        Our Team
      </span>
      <h1 id="mgTeamTitle">About the Team</h1>
      <p>We are building the future of social gifting, prepaid gifts, and local commerce with simple tools that help people buy now, send later, and support local businesses.</p>
    </header>

    <article class="mg-team-profile" aria-labelledby="mgTeamMemberName">
      <div class="mg-team-photo-panel">
        <img src="/images/dave_main.png" alt="David Evans, Founder of Microgifter" loading="eager" decoding="async">
        <div class="mg-team-quote"><span aria-hidden="true">“</span>Building connections. Empowering local.</div>
      </div>

      <div class="mg-team-content">
        <span class="mg-team-role-pill">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0H5Z"/></svg>
          Founder
        </span>
        <h2 id="mgTeamMemberName">David Evans</h2>
        <strong class="mg-team-title">Founder</strong>
        <p class="mg-team-bio">20+ years of experience in graphic design and web design. Musician with a strong aptitude for software and tech. Building Microgifter to simplify gifting and local commerce.</p>

        <div class="mg-team-chips" aria-label="Founder profile highlights">
          <span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 8 4.5 3.5L12 4l4.5 7.5L21 8l-2 11H5L3 8Z"/></svg>Founder</span>
          <span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-4.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0-2.5a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z"/></svg>Product Vision</span>
          <span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 17.5V21h3.5L18.1 10.4l-3.5-3.5L4 17.5ZM20.7 7.8a1 1 0 0 0 0-1.4l-3.1-3.1a1 1 0 0 0-1.4 0L14.8 4.7l4.5 4.5 1.4-1.4Z"/></svg>Design</span>
          <span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m8.7 16.6-5-4.6 5-4.6 1.3 1.5L6.6 12l3.4 3.1-1.3 1.5Zm6.6 0-1.3-1.5 3.4-3.1-3.4-3.1 1.3-1.5 5 4.6-5 4.6ZM11 19l2-14h2l-2 14h-2Z"/></svg>Technology</span>
          <span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s7-5.4 7-12a7 7 0 1 0-14 0c0 6.6 7 12 7 12Zm0-9a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>Phoenix, AZ</span>
        </div>

        <div class="mg-team-metrics" aria-label="Founder capability overview">
          <section class="mg-team-metric-card">
            <span>Focus</span>
            <h3>Product · Design · Tech</h3>
            <div class="mg-team-donut" aria-label="100 percent focus alignment"><b>100%</b></div>
            <ul>
              <li>Product Vision</li>
              <li>Design</li>
              <li>Technology</li>
            </ul>
          </section>
          <section class="mg-team-metric-card mg-team-experience">
            <span>Experience</span>
            <h3><b>20+</b> Years</h3>
            <p>Design, Web, Software &amp; Technology</p>
          </section>
          <section class="mg-team-metric-card mg-team-impact">
            <span>Impact</span>
            <h3>Building the future of gifting</h3>
            <div class="mg-team-chart" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
            <div class="mg-team-chart-labels"><small>Connect</small><small>Gift</small><small>Support Local</small><small>Grow</small></div>
          </section>
        </div>
      </div>
    </article>

    <div class="mg-team-footer-line">
      <span></span>
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm-6-9h4.1c.1 2.2.6 4.1 1.3 5.3A8 8 0 0 1 6 13Zm0-2a8 8 0 0 1 5.4-5.3c-.7 1.2-1.2 3.1-1.3 5.3H6Zm6 7.7c-.8-1.1-1.7-3.1-1.9-5.7h3.8c-.2 2.6-1.1 4.6-1.9 5.7ZM13.9 11h-3.8c.2-2.6 1.1-4.6 1.9-5.7.8 1.1 1.7 3.1 1.9 5.7Zm-1.3 7.3c.7-1.2 1.2-3.1 1.3-5.3H18a8 8 0 0 1-5.4 5.3ZM13.9 11c-.1-2.2-.6-4.1-1.3-5.3A8 8 0 0 1 18 11h-4.1Z"/></svg>
      <p>Simplifying gifting. Strengthening communities.</p>
      <span></span>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
