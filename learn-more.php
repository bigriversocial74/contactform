<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Learn More | Microgifter';
$page_section = 'learn';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/learn-more-v2.css',
];
$page_scripts = ['/assets/js/learn-more.js'];
$page_manifest = [
    'id' => 'learn-more',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            [
                'label' => 'Contact sales',
                'href' => 'mailto:sales@microgifter.com',
            ],
            [
                'label' => 'Book a demo',
                'href' => '#learn-more-form',
            ],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'learn-more', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<div class="lm-page">
  <div class="lm-layout">
    <aside class="lm-story" aria-labelledby="lm-page-title">
      <div class="lm-story-inner">
        <h1 id="lm-page-title">Learn how<br>Microgifter works</h1>
        <p class="lm-story-lede">Microgifter gives local businesses and organizations one platform for gift certificates, workplace rewards, loyalty, and customer engagement.</p>

        <div class="lm-benefits" aria-label="Microgifter benefits">
          <div class="lm-benefit">
            <span class="lm-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20"/><path d="m24 13 3.2 6.5 7.2 1-5.2 5.1 1.2 7.2-6.4-3.4-6.4 3.4 1.2-7.2-5.2-5.1 7.2-1z"/></svg>
            </span>
            <strong>Delight customers<br>and employees</strong>
          </div>
          <div class="lm-benefit">
            <span class="lm-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 48 48"><path d="M9 38V25h7v13zm12 0V17h7v21zm12 0V10h7v28zM10 17l10-7 8 3 10-8"/><path d="m33 5 5 0 0 5"/></svg>
            </span>
            <strong>Drive loyalty<br>and repeat visits</strong>
          </div>
          <div class="lm-benefit">
            <span class="lm-benefit-icon" aria-hidden="true">
              <svg viewBox="0 0 48 48"><path d="M24 39S8 30 8 18c0-6 4-10 10-10 4 0 6.7 2.1 8 4.5C27.3 10.1 30 8 34 8c6 0 10 4 10 10 0 12-20 21-20 21z"/></svg>
            </span>
            <strong>Support local and<br>build stronger relationships</strong>
          </div>
        </div>

        <figure class="lm-illustration">
          <img src="/assets/images/learn-more-merchant-customer.svg" width="900" height="760" alt="A merchant presenting a fifty dollar Microgifter gift certificate to a customer using a mobile phone">
        </figure>

        <div class="lm-trust-card">
          <span class="lm-trust-icon" aria-hidden="true">
            <svg viewBox="0 0 32 32"><path d="M16 3 27 7v8c0 7-4.7 11.8-11 14C9.7 26.8 5 22 5 15V7z"/><path d="m11 16 3 3 7-8"/></svg>
          </span>
          <div>
            <strong>Everything you need in one easy-to-use platform.</strong>
            <p>Launch programs, track performance, and grow relationships that last.</p>
          </div>
        </div>
      </div>
    </aside>

    <main class="lm-form-panel">
      <div class="lm-form-wrap">
        <div class="lm-form-intro">
          <h2>Find the right Microgifter setup</h2>
          <p>Answer a few questions and we will route your request into the existing Microgifter sales CRM with the context your team needs.</p>
        </div>

        <form id="learn-more-form" data-learn-more-form novalidate>
          <input type="hidden" name="source_page" value="learn-more">
          <input type="hidden" name="source_url">
          <input type="hidden" name="timezone_label">
          <input type="hidden" name="utm_source">
          <input type="hidden" name="utm_medium">
          <input type="hidden" name="utm_campaign">
          <input type="hidden" name="utm_term">
          <input type="hidden" name="utm_content">
          <input type="hidden" name="name">
          <input type="hidden" name="email">
          <input type="hidden" name="business_name">
          <input type="hidden" name="website_url">
          <input type="hidden" name="category">
          <input type="hidden" name="lead_type">
          <input type="hidden" name="message">

          <section class="lm-question" data-lm-required-group="use_cases" aria-labelledby="lm-q1-title">
            <div class="lm-question-head">
              <h3 id="lm-q1-title"><span class="lm-question-number">1.</span> What do you want to use Microgifter for?</h3>
              <p>Select all that apply.</p>
            </div>
            <div class="lm-options">
              <?php
              $useCases = [
                  'gift_certificates' => 'Gift certificates',
                  'customer_engagement' => 'Customer engagement',
                  'workplace_rewards' => 'Workplace rewards',
                  'group_gifting' => 'Group gifting',
                  'loyalty_crm' => 'Loyalty & CRM',
                  'events_community_rewards' => 'Events & community rewards',
              ];
              foreach ($useCases as $value => $label):
              ?>
                <label class="lm-choice">
                  <input type="checkbox" name="use_cases[]" value="<?= htmlspecialchars($value) ?>">
                  <span class="lm-control" aria-hidden="true"></span>
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="lm-group-error" data-lm-group-error="use_cases">Choose at least one Microgifter use case.</p>
          </section>

          <section class="lm-question" data-lm-required-group="audiences" aria-labelledby="lm-q2-title">
            <div class="lm-question-head">
              <h3 id="lm-q2-title"><span class="lm-question-number">2.</span> Who are you trying to reward or reach?</h3>
              <p>Choose the audiences most important to you.</p>
            </div>
            <div class="lm-options">
              <?php
              $audiences = [
                  'customers' => 'Customers',
                  'event_attendees' => 'Event attendees',
                  'employees' => 'Employees',
                  'community_groups' => 'Community groups',
                  'members_supporters' => 'Members or supporters',
              ];
              foreach ($audiences as $value => $label):
              ?>
                <label class="lm-choice">
                  <input type="checkbox" name="audiences[]" value="<?= htmlspecialchars($value) ?>">
                  <span class="lm-control" aria-hidden="true"></span>
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="lm-group-error" data-lm-group-error="audiences">Choose at least one audience.</p>
          </section>

          <section class="lm-question" aria-labelledby="lm-q3-title">
            <div class="lm-question-head">
              <h3 id="lm-q3-title"><span class="lm-question-number">3.</span> What type of organization are you?</h3>
              <p>We will tailor the experience to your goals.</p>
            </div>
            <div class="lm-options">
              <?php
              $organizationTypes = [
                  'restaurant_cafe' => 'Restaurant or café',
                  'wellness_hospitality' => 'Wellness or hospitality',
                  'retail_store' => 'Retail store',
                  'organization_nonprofit' => 'Organization or nonprofit',
                  'service_business' => 'Service business',
                  'other' => 'Other',
              ];
              foreach ($organizationTypes as $value => $label):
              ?>
                <label class="lm-choice is-radio">
                  <input type="radio" name="organization_type" value="<?= htmlspecialchars($value) ?>" required>
                  <span class="lm-control" aria-hidden="true"></span>
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="lm-question" aria-labelledby="lm-q4-title">
            <div class="lm-question-head">
              <h3 id="lm-q4-title"><span class="lm-question-number">4.</span> How many locations do you manage?</h3>
              <p>Microgifter supports single-location and multi-location programs.</p>
            </div>
            <div class="lm-options lm-options-three">
              <?php
              $locationCounts = [
                  '1' => '1 location',
                  '2_5' => '2–5 locations',
                  '6_plus' => '6+ locations',
              ];
              foreach ($locationCounts as $value => $label):
              ?>
                <label class="lm-choice is-radio">
                  <input type="radio" name="location_count" value="<?= htmlspecialchars($value) ?>" required>
                  <span class="lm-control" aria-hidden="true"></span>
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="lm-question" aria-labelledby="lm-q5-title">
            <div class="lm-question-head">
              <h3 id="lm-q5-title"><span class="lm-question-number">5.</span> How do you want to get started?</h3>
              <p>We can recommend the best next step.</p>
            </div>
            <div class="lm-options">
              <?php
              $startPreferences = [
                  'create_account' => 'I want to create an account',
                  'plan_help' => 'I need help choosing a plan',
                  'guided_demo' => 'I want a guided demo',
              ];
              foreach ($startPreferences as $value => $label):
              ?>
                <label class="lm-choice is-radio">
                  <input type="radio" name="start_preference" value="<?= htmlspecialchars($value) ?>" required>
                  <span class="lm-control" aria-hidden="true"></span>
                  <span><?= htmlspecialchars($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="lm-question" aria-labelledby="lm-q6-title">
            <div class="lm-question-head">
              <h3 id="lm-q6-title"><span class="lm-question-number">6.</span> Tell us a little about you.</h3>
              <p>We will use this to recommend the right Microgifter setup.</p>
            </div>
            <div class="lm-fields">
              <label class="lm-field">
                <span>First name</span>
                <input name="first_name" autocomplete="given-name" placeholder="First name" required>
              </label>
              <label class="lm-field">
                <span>Last name</span>
                <input name="last_name" autocomplete="family-name" placeholder="Last name" required>
              </label>
              <label class="lm-field is-full">
                <span>Work email address</span>
                <input name="work_email" type="email" autocomplete="email" placeholder="Work email address" required>
              </label>
              <label class="lm-field is-full">
                <span>Company or organization name</span>
                <input name="company_name" autocomplete="organization" placeholder="Company or organization name" required>
              </label>
              <label class="lm-field">
                <span>Team size</span>
                <select name="team_size" required>
                  <option value="">Team size</option>
                  <option value="1">Just me</option>
                  <option value="2_10">2–10</option>
                  <option value="11_50">11–50</option>
                  <option value="51_200">51–200</option>
                  <option value="201_plus">201+</option>
                </select>
              </label>
              <label class="lm-field">
                <span>Phone number</span>
                <input name="phone" type="tel" autocomplete="tel" placeholder="Phone number">
              </label>
              <label class="lm-field is-full">
                <span>Website</span>
                <input name="website" type="url" inputmode="url" autocomplete="url" placeholder="https://">
              </label>
              <label class="lm-field is-full">
                <span>Goals or notes</span>
                <textarea name="goals" placeholder="Tell us what you want to launch, sell, reward, or automate."></textarea>
              </label>
            </div>
          </section>

          <div class="lm-form-status" data-learn-more-status role="status" aria-live="polite"></div>
          <button class="lm-submit" type="submit">
            <span>Show my Microgifter options</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg>
          </button>
          <div class="lm-success-panel" data-lm-complete>
            <strong>Thanks — your request was received.</strong><br>
            Your qualification details are now available in the existing Microgifter sales CRM for follow-up.
          </div>
          <p class="lm-privacy-note">By submitting, you agree that Microgifter may contact you about the products and programs selected above.</p>
        </form>
      </div>
    </main>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
