<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Cookie Policy | Microgifter';
$page_section = 'legal';
$header_mode = 'public';
$page_body_class = 'mg-legal-public-page';
$page_styles = ['/assets/css/legal-pages.css?v=1.0.0', '/assets/css/cookie-consent.css?v=1.0.0'];
$page_meta = [
    'description' => 'Learn how Microgifter uses cookies and similar technologies, which technologies are necessary, and how to control optional functional, analytics, marketing, and external-media choices.',
    'canonical' => 'https://microgifter.com/cookies.php',
    'og_title' => 'Microgifter Cookie Policy',
    'og_description' => 'Control optional cookies and similar technologies used by Microgifter. Necessary technologies remain active to secure and operate the platform.',
];
$page_manifest = [
    'id' => 'cookies',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'description' => $page_meta['description'],
    'onboarding' => ['enabled' => false, 'page' => 'cookies', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<article class="mg-legal-page">
  <header class="mg-legal-hero">
    <div class="mg-legal-wrap">
      <p class="mg-legal-eyebrow">Transparent technology choices</p>
      <h1>Cookie Policy</h1>
      <p class="mg-legal-summary">This Cookie Policy explains how Microgifter uses cookies, browser storage, pixels, embedded services, and similar technologies across its websites and applications.</p>
      <div class="mg-legal-meta"><span>Effective July 23, 2026</span><span>Last updated July 23, 2026</span></div>
      <div class="mg-legal-promise">
        <strong>Optional technologies remain off until you choose to allow them.</strong>
        <p>Strictly necessary technologies support authentication, security, carts, claims, transactions, requested account features, and saved privacy choices. Microgifter does not sell customer or merchant data to third parties.</p>
      </div>
    </div>
  </header>

  <div class="mg-legal-body">
    <div class="mg-legal-wrap mg-legal-layout">
      <nav class="mg-legal-toc" aria-label="Cookie Policy contents">
        <strong>Contents</strong>
        <a href="#scope">1. Scope</a>
        <a href="#technologies">2. Technologies covered</a>
        <a href="#consent">3. How consent works</a>
        <a href="#categories">4. Categories</a>
        <a href="#inventory">5. Current inventory</a>
        <a href="#third-parties">6. Third-party services</a>
        <a href="#manage">7. Manage or withdraw consent</a>
        <a href="#no-sale">8. No sale of data</a>
        <a href="#changes">9. Changes</a>
        <a href="#contact">10. Contact</a>
      </nav>

      <div class="mg-legal-content">
        <section class="mg-legal-section" id="scope">
          <h2>1. Scope</h2>
          <p>This policy applies to Microgifter websites, account workspaces, social gifting, merchant CRM, campaigns, loyalty, rewards, claims, redemption, commerce, analytics, agent-enabled features, Model Context Protocol services, and related services that link to this policy.</p>
          <p>It should be read together with the <a href="/privacy.php">Privacy Policy</a> and <a href="/terms.php">Terms of Service</a>.</p>
        </section>

        <section class="mg-legal-section" id="technologies">
          <h2>2. Technologies covered</h2>
          <p>Cookies are small text files stored by a browser. Similar technologies include local storage, session storage, pixels, software-development-kit identifiers, embedded content, and other mechanisms that store or access information on a device.</p>
          <p>First-party technologies are controlled by Microgifter. Third-party technologies may be provided by services such as payment processors, analytics providers, video or audio hosts, mapping tools, social platforms, or connected widgets.</p>
        </section>

        <section class="mg-legal-section" id="consent">
          <h2>3. How consent works</h2>
          <p>On the first visit, or when the policy version materially changes, Microgifter presents a choice to accept all optional technologies, reject all non-essential technologies, or manage individual categories.</p>
          <ul>
            <li>Optional categories are not preselected.</li>
            <li>Rejecting non-essential technologies is presented as clearly and directly as accepting them.</li>
            <li>Core services remain available when optional technologies are rejected, although an optional feature may remain unavailable when it depends on a third-party service.</li>
            <li>Choices are stored for up to 180 days, unless browser storage is cleared or a new policy version requires another decision.</li>
            <li>Choices can be changed or withdrawn at any time through Cookie Settings in the footer.</li>
          </ul>
          <button class="mg-btn mg-btn-primary" type="button" data-mg-cookie-settings>Open Cookie Settings</button>
        </section>

        <section class="mg-legal-section" id="categories">
          <h2>4. Technology categories</h2>
          <h3>Strictly necessary</h3>
          <p>Required to operate and secure the requested service. Examples include authentication and session management, CSRF protection, fraud prevention, carts, claim and redemption workflows, account permissions, transaction continuity, load balancing, and storing privacy choices. These technologies cannot be disabled through the preference manager.</p>

          <h3>Functional</h3>
          <p>Optional technologies that remember convenience, display, personalization, or interface choices that are not required to provide the requested service. These remain off until allowed.</p>

          <h3>Analytics</h3>
          <p>Optional technologies used to understand visits, product usage, performance, errors, engagement, and feature effectiveness. These remain off until allowed.</p>

          <h3>Marketing</h3>
          <p>Optional technologies used for advertising measurement, retargeting, cross-site promotion, or marketing attribution outside the essential operation of a requested merchant transaction or campaign. These remain off until allowed.</p>

          <h3>External media</h3>
          <p>Optional third-party video, audio, maps, social embeds, and widgets. The external provider may set its own cookies or similar storage after the visitor allows this category and loads the content.</p>
        </section>

        <section class="mg-legal-section" id="inventory">
          <h2>5. Current first-party inventory</h2>
          <p>The following inventory describes the primary site-wide technologies used by Microgifter as of the effective date. Feature-specific necessary storage may also be used to preserve a requested workflow, draft, cart, claim, security state, or account preference.</p>
          <div class="mg-cookie-policy-table-wrap">
            <table class="mg-cookie-policy-table">
              <thead>
                <tr><th>Name</th><th>Provider</th><th>Purpose</th><th>Category</th><th>Type</th><th>Duration</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>mg_session</code></td>
                  <td>Microgifter</td>
                  <td>Maintains authenticated and anonymous session continuity, security state, account access, cart and workflow context.</td>
                  <td><span class="mg-cookie-policy-badge">Necessary</span></td>
                  <td>First-party cookie</td>
                  <td>Session-based or up to 30 days depending on account role, activity, and security policy.</td>
                </tr>
                <tr>
                  <td><code>mg_cookie_consent_v1</code></td>
                  <td>Microgifter</td>
                  <td>Stores the consent identifier, policy version, decision time, and selected technology categories so the site can respect the visitor’s choice.</td>
                  <td><span class="mg-cookie-policy-badge">Necessary</span></td>
                  <td>First-party cookie</td>
                  <td>Up to 180 days.</td>
                </tr>
                <tr>
                  <td><code>mg_cookie_consent_v1</code></td>
                  <td>Microgifter</td>
                  <td>Maintains a first-party browser-storage copy of the consent record when local storage is available.</td>
                  <td><span class="mg-cookie-policy-badge">Necessary</span></td>
                  <td>Local storage</td>
                  <td>Up to 180 days or until browser storage is cleared.</td>
                </tr>
                <tr>
                  <td>Feature-specific keys</td>
                  <td>Microgifter</td>
                  <td>Preserves requested drafts, interface state, checkout continuity, media progress, or other user-directed workflow information.</td>
                  <td><span class="mg-cookie-policy-badge">Necessary / Functional</span></td>
                  <td>Cookie or browser storage</td>
                  <td>Session, feature-specific period, or until cleared.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mg-legal-callout">
            <p><strong>No site-wide analytics, marketing pixel, or external-media tracker is enabled globally at publication.</strong> When an optional provider is introduced, Microgifter will categorize and block it until the required permission is available and will update this inventory as appropriate.</p>
          </div>
        </section>

        <section class="mg-legal-section" id="third-parties">
          <h2>6. Optional third-party services</h2>
          <p>Some pages may offer third-party content or integrations. Where consent is required, Microgifter uses a default-deny loading pattern: the script, iframe, image, widget, or connection remains inactive until the visitor permits its category.</p>
          <p>After activation, the third party may process information under its own privacy and cookie terms. Microgifter does not control third-party policies, retention periods, or technologies. Declining external media may replace the content with a notice or link rather than loading it directly.</p>
          <p>Payment, authentication, fraud-prevention, and other services strictly required to complete a user-requested transaction may operate as necessary services and are governed by the applicable provider notices.</p>
        </section>

        <section class="mg-legal-section" id="manage">
          <h2>7. Manage or withdraw consent</h2>
          <p>Use the Cookie Settings control below or in the site footer to review and change optional categories. Withdrawing a previously enabled category removes known first-party optional identifiers where technically possible and reloads the page so the new restriction takes effect.</p>
          <p>Browser settings can also block or delete cookies and storage. Blocking strictly necessary technologies may prevent sign-in, account security, carts, claims, checkout, redemption, or other requested services from functioning correctly.</p>
          <button class="mg-btn mg-btn-primary" type="button" data-mg-cookie-settings>Manage Cookie Preferences</button>
        </section>

        <section class="mg-legal-section" id="no-sale">
          <h2>8. Microgifter does not sell customer or merchant data</h2>
          <p>Microgifter does not sell customer data, Merchant Data, or cookie-derived personal information to data brokers, advertisers, or unrelated third parties for their independent use.</p>
          <p>Optional analytics or marketing permission does not transfer ownership of data. Service providers may process limited information only for authorized operational purposes and under applicable contractual restrictions.</p>
        </section>

        <section class="mg-legal-section" id="changes">
          <h2>9. Changes to this policy</h2>
          <p>Microgifter may update this policy when technologies, providers, purposes, laws, or platform services change. A material change to optional categories or purposes may update the consent-policy version and require a new visitor choice.</p>
        </section>

        <section class="mg-legal-section" id="contact">
          <h2>10. Contact</h2>
          <p>Questions about cookies, privacy choices, or this policy may be sent to <a href="mailto:admin@microgifter.com">admin@microgifter.com</a> or through the <a href="/learn-more.php">Microgifter contact page</a>.</p>
        </section>
      </div>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
