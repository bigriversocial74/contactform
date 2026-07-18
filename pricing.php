<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pricing-packages.php';
require_once __DIR__ . '/includes/pricing-cards.php';

$page_title = 'Pricing | Microgifter for Local Business Growth';
$page_section = 'pricing';
$header_mode = 'public';
$page_body_class = 'mg-pricing-page';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/pricing-local-business-v1.css?v=1.2.0',
];
$page_manifest = [
    'id' => 'pricing',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'How It Works', 'href' => '/index.php#how-it-works'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'pricing', 'sections' => []],
];

$plans = mg_public_pricing_packages();
$summary = mg_pricing_package_summary();

$comparisonRows = [
    ['label' => 'Paid Microgifts', 'key' => 'max_microgifts'],
    ['label' => 'Promotional Rewards', 'key' => 'max_rewards'],
    ['label' => 'Active Campaigns', 'key' => 'max_active_campaigns'],
    ['label' => 'CRM Contacts', 'key' => 'max_crm_contacts'],
    ['label' => 'Monthly Stamps', 'key' => 'monthly_stamps_included'],
    ['label' => 'Monthly AI Tokens', 'key' => 'ai_tokens_monthly_included'],
    ['label' => 'Landing Pages', 'key' => 'max_landing_pages'],
    ['label' => 'Business Locations', 'key' => 'max_locations'],
    ['label' => 'Email Stamps', 'key' => 'email_stamps_enabled', 'boolean' => true],
    ['label' => 'SMS Stamps', 'key' => 'sms_stamps_enabled', 'boolean' => true],
];

$formatLimit = static function (array $plan, array $row): string {
    $key = (string)($row['key'] ?? '');
    $value = $plan['limits'][$key] ?? null;

    if (!empty($row['boolean'])) {
        return $value === true ? 'Included' : '—';
    }

    if ($value === null) {
        return $key === 'max_locations' ? 'Unlimited' : 'Custom';
    }

    return is_numeric($value) ? number_format((int)$value) : (string)$value;
};

require __DIR__ . '/includes/header.php';
?>

<div class="mg-pricing-v1">
  <section class="mg-price-plans" id="plans" aria-labelledby="plans-title">
    <div class="mg-price-shell">
      <div class="mg-price-section-heading mg-price-section-heading-center">
        <span class="mg-price-kicker">Simple pricing for local growth</span>
        <h1 id="plans-title">Choose the right operating level for your business.</h1>
        <p>Start with the customer tools you need now, then add more campaigns, contacts, locations, AI capacity, and automation as demand grows.</p>
      </div>

      <div class="mg-price-trust" aria-label="Pricing assurances">
        <span><b><?= (int)$summary['published'] ?></b> plans available</span>
        <span><b>Monthly</b> subscriptions</span>
        <span><b>Flexible</b> upgrade path</span>
      </div>

      <?php mg_render_public_pricing_cards(); ?>
    </div>
  </section>

  <section class="mg-price-foundation" aria-labelledby="foundation-title">
    <div class="mg-price-shell">
      <div class="mg-price-foundation-panel">
        <div class="mg-price-foundation-copy">
          <span class="mg-price-kicker mg-price-kicker-light">Included with every plan</span>
          <h2 id="foundation-title">A professional foundation from day one.</h2>
          <p>Every subscription includes the core infrastructure needed to create, distribute, track, and improve customer value.</p>
        </div>
        <div class="mg-price-foundation-grid">
          <article><span>◎</span><div><h3>Secure infrastructure</h3><p>Built for real customer and campaign activity.</p></div></article>
          <article><span>↗</span><div><h3>Live reporting</h3><p>Track campaigns, claims, and customer response.</p></div></article>
          <article><span>⌘</span><div><h3>Connected tools</h3><p>Landing pages, APIs, QR, email, and SMS paths.</p></div></article>
          <article><span>◇</span><div><h3>Product updates</h3><p>Ongoing platform improvements are included.</p></div></article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-price-compare" aria-labelledby="compare-title">
    <div class="mg-price-shell">
      <div class="mg-price-section-heading mg-price-section-heading-center">
        <span class="mg-price-kicker">Compare plan capacity</span>
        <h2 id="compare-title">See how each plan scales.</h2>
        <p>Compare the published package limits that power customer engagement, AI assistance, campaign distribution, and local commerce operations.</p>
      </div>

      <div class="mg-price-table-wrap" role="region" aria-label="Pricing plan comparison" tabindex="0">
        <table class="mg-price-table">
          <thead>
            <tr>
              <th scope="col">Capability</th>
              <?php foreach ($plans as $plan): ?><th scope="col"<?= !empty($plan['featured']) ? ' class="is-featured"' : '' ?>><?= mg_e((string)$plan['name']) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($comparisonRows as $row): ?>
              <tr>
                <th scope="row"><?= mg_e((string)$row['label']) ?></th>
                <?php foreach ($plans as $plan): ?>
                  <?php $displayValue = $formatLimit($plan, $row); ?>
                  <td<?= !empty($plan['featured']) ? ' class="is-featured"' : '' ?>><?php if ($displayValue === 'Included'): ?><span class="mg-price-included">✓ Included</span><?php else: ?><?= mg_e($displayValue) ?><?php endif; ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="mg-price-support" aria-labelledby="support-title">
    <div class="mg-price-shell mg-price-support-grid">
      <div class="mg-price-support-copy">
        <span class="mg-price-kicker">Need help choosing?</span>
        <h2 id="support-title">Match the plan to your growth goals.</h2>
        <p>Tell us how many customers, campaigns, locations, or sponsored programs you want to manage. We will help you choose a practical starting point without overbuilding.</p>
        <a class="mg-price-button mg-price-button-primary" href="/learn-more.php">Contact Sales</a>
      </div>
      <div class="mg-price-support-board" aria-label="Example Microgifter activity">
        <div><span>C</span><p><strong>Coffee for two</strong><small>Customer gift and visit intent</small></p><b>REGIFT</b></div>
        <div><span>D</span><p><strong>Dinner experience</strong><small>Protected local reward delivery</small></p><b>CLAIM</b></div>
        <div><span>R</span><p><strong>Return visit reward</strong><small>Tracked loyalty follow-up</small></p><b>LOAD</b></div>
      </div>
    </div>
  </section>

  <section class="mg-price-faq" aria-labelledby="faq-title">
    <div class="mg-price-shell mg-price-faq-grid">
      <div class="mg-price-faq-intro">
        <span class="mg-price-kicker">Pricing questions</span>
        <h2 id="faq-title">Clear answers before you start.</h2>
        <p>Plans use the same connected Microgifter platform. The main differences are capacity, AI allowance, locations, communication channels, design tools, and automation.</p>
      </div>
      <div class="mg-price-faq-list">
        <details><summary>Can I upgrade as my business grows?</summary><p>Yes. Choose the plan that matches your current operating needs, then move to a higher-capacity plan when you need more campaigns, contacts, locations, communication tools, or AI capacity.</p></details>
        <details><summary>What are monthly Stamps?</summary><p>Stamps represent the included monthly distribution capacity defined by each published plan. Available email and SMS channels vary by plan.</p></details>
        <details><summary>Which plan supports multiple locations?</summary><p>Growth supports up to three locations, Pro supports up to ten, and Enterprise supports unlimited locations.</p></details>
        <details><summary>Do I need a long-term contract?</summary><p>The published plans are presented as monthly subscriptions. Enterprise or custom programs may use a separate agreement based on implementation scope.</p></details>
        <details><summary>Can your team help with a custom rollout?</summary><p>Yes. Contact Sales for volume pricing, multi-location programs, white-label design, platform integrations, and automated commerce workflows.</p></details>
      </div>
    </div>
  </section>

  <section class="mg-price-final" aria-labelledby="price-final-title">
    <div class="mg-price-shell">
      <div class="mg-price-final-panel">
        <div>
          <h2 id="price-final-title">Ready to build stronger local relationships?</h2>
          <p>Create your account to start with Microgifter, or talk with our team about the right plan for your business, organization, or sponsored commerce program.</p>
        </div>
        <div class="mg-price-final-actions">
          <a class="mg-price-button mg-price-button-primary" href="/signup.php?type=merchant">Create Merchant Account</a>
          <a class="mg-price-button mg-price-button-secondary" href="/learn-more.php">Book A Demo</a>
        </div>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
