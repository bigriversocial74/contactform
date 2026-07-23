<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Privacy Policy | Microgifter';
$page_section = 'legal';
$header_mode = 'public';
$page_body_class = 'mg-legal-public-page';
$page_styles = ['/assets/css/legal-pages.css?v=1.0.0'];
$page_meta = [
    'description' => 'Learn how Microgifter collects, uses, protects, and shares information across social gifting, merchant CRM, campaigns, rewards, claims, commerce, analytics, and agent-enabled services.',
    'canonical' => 'https://microgifter.com/privacy.php',
    'og_title' => 'Microgifter Privacy Policy',
    'og_description' => 'Microgifter does not sell customer or merchant data. Read how information is used to operate, secure, analyze, and improve the platform.',
];
$page_manifest = [
    'id' => 'privacy',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'description' => $page_meta['description'],
    'onboarding' => ['enabled' => false, 'page' => 'privacy', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<article class="mg-legal-page">
  <header class="mg-legal-hero">
    <div class="mg-legal-wrap">
      <p class="mg-legal-eyebrow">Trust, privacy, and responsible data use</p>
      <h1>Privacy Policy</h1>
      <p class="mg-legal-summary">This Privacy Policy explains how Microgifter collects, uses, analyzes, protects, and shares information when customers, merchants, creators, organizations, and authorized agents use Microgifter services.</p>
      <div class="mg-legal-meta"><span>Effective July 23, 2026</span><span>Last updated July 23, 2026</span></div>
      <div class="mg-legal-promise">
        <strong>Microgifter does not sell customer data or merchant data to third parties.</strong>
        <p>We use information to operate, secure, support, personalize, analyze, improve, and develop Microgifter. We may share information with merchants, recipients, authorized integrations, and service providers only as described below and as needed to provide the platform.</p>
      </div>
    </div>
  </header>

  <div class="mg-legal-body">
    <div class="mg-legal-wrap mg-legal-layout">
      <nav class="mg-legal-toc" aria-label="Privacy Policy contents">
        <strong>Contents</strong>
        <a href="#scope">1. Scope</a>
        <a href="#roles">2. Our data roles</a>
        <a href="#collect">3. Information collected</a>
        <a href="#use">4. How information is used</a>
        <a href="#merchant-data">5. Merchant data</a>
        <a href="#share">6. How information is shared</a>
        <a href="#no-sale">7. No sale of data</a>
        <a href="#cookies">8. Cookies and tracking</a>
        <a href="#retention">9. Retention</a>
        <a href="#security">10. Security</a>
        <a href="#rights">11. Privacy rights</a>
        <a href="#children">12. Children</a>
        <a href="#international">13. International use</a>
        <a href="#changes">14. Changes</a>
        <a href="#contact">15. Contact</a>
      </nav>

      <div class="mg-legal-content">
        <section class="mg-legal-section" id="scope">
          <h2>1. Scope</h2>
          <p>This policy applies to Microgifter websites, applications, account workspaces, social gifting, merchant CRM, campaigns, offers, rewards, loyalty tools, claims, redemption, messaging, analytics, automated commerce, creator programs, training tools, Model Context Protocol services, agent-enabled features, and related support services that link to this policy (collectively, the “Services”).</p>
          <p>It does not govern a merchant’s independent website, in-store systems, privacy practices, or other third-party services that are not controlled by Microgifter. Merchants and third parties may provide separate privacy notices that also apply to their activities.</p>
        </section>

        <section class="mg-legal-section" id="roles">
          <h2>2. Our data roles</h2>
          <p>Microgifter may act in different data-protection roles depending on the service and relationship:</p>
          <ul>
            <li><strong>Platform operator.</strong> We determine how account, security, transaction, platform-usage, support, and service-improvement information is processed.</li>
            <li><strong>Merchant service provider.</strong> When a merchant uses Microgifter to manage its customer records, campaigns, claims, rewards, or communications, Microgifter may process information on the merchant’s behalf and according to the merchant’s instructions.</li>
            <li><strong>Independent platform use.</strong> Microgifter may also use information for its own legitimate platform purposes, including security, fraud prevention, service analytics, demand intelligence, recommendations, product development, compliance, and aggregated or deidentified insights.</li>
          </ul>
          <p>Merchants remain responsible for their own customer notices, permissions, lawful instructions, and use of information within their business.</p>
        </section>

        <section class="mg-legal-section" id="collect">
          <h2>3. Information we collect</h2>

          <h3>Account and profile information</h3>
          <p>We may collect names, display names, email addresses, phone numbers, passwords in hashed form, verification status, profile images, addresses, business information, account type, roles, permissions, subscription details, preferences, and authentication or recovery information.</p>

          <h3>Gifting, purchase, claim, and redemption information</h3>
          <p>We collect information needed to create, purchase, deliver, schedule, receive, claim, redeem, refund, regift, or track a Microgift, reward, offer, product, service, experience, or other transaction. This may include buyer and recipient details, gift messages, item details, values, delivery timing, claim codes, QR activity, transaction status, merchant verification, and lifecycle history.</p>

          <h3>Merchant, CRM, campaign, and loyalty information</h3>
          <p>Merchants may provide customer records, product and service catalogs, locations, campaign rules, participant lists, messages, rewards, referrals, contest entries, creator relationships, attributed customers, visits, claims, redemptions, loyalty activity, customer preferences, notes, and other business records used in the Services.</p>

          <h3>Content and communications</h3>
          <p>We may collect content that users or merchants create, upload, publish, submit, or send, including messages, reviews, images, videos, campaign content, creative assets, support requests, forms, comments, attachments, and communication history.</p>

          <h3>Payment and subscription information</h3>
          <p>Payment card details are generally handled by payment processors. Microgifter may receive transaction identifiers, payment status, card brand, limited card details such as the last four digits, billing information, refunds, disputes, subscription status, fraud signals, and settlement or payout records.</p>

          <h3>Device, usage, and location information</h3>
          <p>We may collect IP address, browser and device type, operating system, identifiers, pages viewed, clicks, searches, referral source, session activity, timestamps, diagnostics, error logs, security events, approximate location derived from IP address, merchant location data, and precise device location when a user expressly allows it for a location-based feature.</p>

          <h3>AI, agent, and MCP information</h3>
          <p>When users connect or use AI-assisted, agent, automation, or MCP features, we may collect prompts, instructions, selected tools, permissions, approvals, generated outputs, action receipts, errors, connected-client information, and the business or account context needed to perform authorized actions.</p>

          <h3>Information from others</h3>
          <p>We may receive information from merchants, gift senders or recipients, creators, employers, sponsors, payment processors, identity or security providers, service providers, integrations, publicly available sources, and other users who are authorized to provide it.</p>
        </section>

        <section class="mg-legal-section" id="use">
          <h2>4. How we use information</h2>
          <p>We use information to:</p>
          <ul>
            <li>Create, verify, secure, and manage accounts and sessions.</li>
            <li>Provide social gifting, purchasing, scheduling, delivery, claiming, redemption, refunds, regifting, and transaction history.</li>
            <li>Operate merchant CRM, campaigns, messaging, referrals, contests, creator programs, loyalty, rewards, claims, analytics, and automated commerce.</li>
            <li>Connect buyers, recipients, merchants, creators, sponsors, workplaces, communities, and authorized agents as required by a transaction or program.</li>
            <li>Process subscriptions, commissions, payments, payouts, disputes, and related financial records.</li>
            <li>Personalize discovery, recommendations, offers, customer follow-up, merchant insights, and user experiences.</li>
            <li>Analyze future demand, campaign performance, engagement, sales, claims, loyalty, customer relationships, and platform usage.</li>
            <li>Develop, test, maintain, troubleshoot, improve, and expand Microgifter products and services.</li>
            <li>Prevent fraud, abuse, unauthorized access, spam, manipulation, security incidents, and violations of our Terms.</li>
            <li>Send transactional, security, account, service, support, and permitted marketing communications.</li>
            <li>Comply with law, enforce agreements, resolve disputes, and protect users, merchants, Microgifter, and the public.</li>
          </ul>
          <p>Where required, we rely on consent. In other cases, processing may be necessary to perform a contract, comply with law, protect vital interests, or pursue legitimate business interests that are not overridden by applicable privacy rights.</p>
        </section>

        <section class="mg-legal-section" id="merchant-data">
          <h2>5. Merchant data and customer records</h2>
          <div class="mg-legal-callout">
            <p><strong>Merchant data belongs to the merchant.</strong> Using Microgifter does not transfer ownership of a merchant’s customer lists, CRM records, catalogs, campaign records, content, or other business data to Microgifter.</p>
          </div>
          <p>By placing Merchant Data in the Services, the merchant authorizes Microgifter to host, copy, transmit, organize, combine, process, secure, display, analyze, model, and otherwise use that data as needed to provide, support, personalize, market, protect, improve, and develop Microgifter and its related features.</p>
          <p>This permission includes use for gifting, CRM, campaigns, loyalty, rewards, messaging, claims, redemption, attribution, creator programs, fraud prevention, analytics, forecasting, demand intelligence, recommendations, benchmarking, automated commerce, agent features, MCP services, and other Microgifter functionality.</p>
          <p>Microgifter may create and use aggregated or deidentified information that does not reasonably identify a merchant or individual. We may use such information for lawful business purposes, including research, reporting, benchmarking, forecasting, platform improvement, and new services.</p>
          <p>Merchants must have all rights, notices, consents, and legal bases required to provide Merchant Data to Microgifter and to use the Services for communications, campaigns, rewards, analytics, and customer relationship management.</p>
        </section>

        <section class="mg-legal-section" id="share">
          <h2>6. How we share information</h2>
          <p>We may disclose information in the following circumstances:</p>
          <ul>
            <li><strong>Merchants and transaction participants.</strong> We share information with the applicable merchant, buyer, recipient, sponsor, creator, employer, group organizer, or other participant when needed to complete or manage a transaction, claim, campaign, reward, referral, or program.</li>
            <li><strong>Service providers.</strong> We use providers for hosting, databases, security, communications, email, analytics, customer support, payment processing, storage, content delivery, fraud prevention, and similar operational services. They may process information only for contracted purposes and subject to appropriate obligations.</li>
            <li><strong>Authorized integrations and agents.</strong> We share information with connected tools, MCP clients, agent harnesses, or third-party integrations only when authorized by the user, merchant, account administrator, or applicable permission model.</li>
            <li><strong>Legal and safety reasons.</strong> We may disclose information to comply with law, legal process, regulatory requests, enforceable government demands, investigations, or to protect rights, safety, security, property, and the integrity of the Services.</li>
            <li><strong>Business transactions.</strong> Information may be transferred as part of a merger, acquisition, financing, reorganization, bankruptcy, sale of assets, or similar transaction, subject to applicable law and continued protection.</li>
            <li><strong>With consent or direction.</strong> We may share information when a user or merchant asks us to do so or gives permission.</li>
          </ul>
          <p>Public content, public profiles, reviews, offers, campaigns, merchant listings, and other information intentionally published through a public feature may be visible to others and may be indexed or shared outside Microgifter.</p>
        </section>

        <section class="mg-legal-section" id="no-sale">
          <h2>7. We do not sell customer or merchant data</h2>
          <p>Microgifter does not sell customer data, Merchant Data, or personal information to data brokers, advertisers, or unrelated third parties for their own independent use.</p>
          <p>We also do not share personal information for cross-context behavioral advertising unless we separately disclose that practice and provide any legally required controls. Operating merchant campaigns, measuring attribution, presenting sponsored offers, or using contracted service providers does not give those parties ownership of customer or merchant data.</p>
          <p>If our practices materially change, we will update this policy and provide notices or opt-out mechanisms required by applicable law.</p>
        </section>

        <section class="mg-legal-section" id="cookies">
          <h2>8. Cookies and similar technologies</h2>
          <p>Microgifter may use cookies, local storage, session storage, pixels, logs, and similar technologies to keep users signed in, protect sessions, remember preferences, maintain carts and workflows, measure performance, prevent abuse, understand usage, support campaign attribution, and improve the Services.</p>
          <p>Browser controls may allow users to block or delete cookies. Some Services may not function correctly when essential cookies or storage are disabled.</p>
        </section>

        <section class="mg-legal-section" id="retention">
          <h2>9. Data retention</h2>
          <p>We retain information for as long as reasonably necessary to provide the Services, maintain accounts and transaction records, comply with legal and accounting obligations, prevent fraud, enforce agreements, resolve disputes, support merchants and customers, and protect the platform.</p>
          <p>Retention periods vary by data type and context. Transaction, claim, redemption, payment, audit, security, consent, and dispute records may be retained longer than general account or preference data. Backups and logs may persist for a limited period after deletion. Aggregated or deidentified information may be retained without a fixed time limit when it no longer reasonably identifies an individual or merchant.</p>
        </section>

        <section class="mg-legal-section" id="security">
          <h2>10. Security</h2>
          <p>Microgifter is dedicated to protecting and securing its platforms. We use administrative, technical, and organizational safeguards designed to protect information against unauthorized access, loss, misuse, alteration, and disclosure.</p>
          <p>Safeguards may include encrypted transport, password hashing, restricted access, role and permission controls, session protections, authentication controls, multi-factor authentication support, rate limiting, security logging, monitoring, backups, vendor controls, and incident-response procedures.</p>
          <p>No system can guarantee absolute security. Users and merchants are responsible for protecting account credentials, devices, API credentials, connected agents, recovery codes, and access granted to employees or contractors. Please notify us promptly about suspected unauthorized access.</p>
        </section>

        <section class="mg-legal-section" id="rights">
          <h2>11. Privacy choices and rights</h2>
          <p>Depending on where a person lives and subject to legal exceptions, they may have rights to:</p>
          <ul>
            <li>Request access to personal information.</li>
            <li>Request correction of inaccurate information.</li>
            <li>Request deletion of certain information.</li>
            <li>Receive a portable copy of certain information.</li>
            <li>Object to or restrict certain processing.</li>
            <li>Withdraw consent where processing depends on consent.</li>
            <li>Opt out of certain targeted advertising, sale, sharing, or profiling activities when applicable.</li>
            <li>Appeal a denied privacy request where applicable.</li>
            <li>Complain to an appropriate privacy or data-protection authority.</li>
          </ul>
          <p>Microgifter may need to verify identity, account ownership, authority, or merchant relationship before completing a request. Some information may be retained when required by law or needed for security, fraud prevention, transactions, disputes, contracts, or the rights of others.</p>

          <h3>California and other U.S. state notices</h3>
          <p>During the preceding twelve months, Microgifter may have collected the categories described in Section 3, including identifiers, customer records, commercial information, internet or device activity, approximate or precise geolocation when enabled, professional or business information, user content, inferences, and limited sensitive information such as account credentials or payment-related data handled through service providers.</p>
          <p>We collect these categories from users, merchants, transaction participants, devices, service providers, integrations, and authorized third parties. We use and disclose them for the business purposes described in Sections 4 and 6. We do not sell personal information. We do not use sensitive personal information to infer characteristics except as permitted for providing, securing, or improving the Services.</p>
          <p>We will not unlawfully discriminate against a person for exercising an applicable privacy right.</p>
        </section>

        <section class="mg-legal-section" id="children">
          <h2>12. Children’s privacy</h2>
          <p>The Services are not directed to children under 13, and Microgifter does not knowingly collect personal information directly from children under 13 without legally valid parental consent. If we learn that such information was collected improperly, we will take reasonable steps to delete it.</p>
          <p>A gift recipient may be a minor, but an adult sender, parent, guardian, merchant, employer, or sponsor must provide and use information lawfully. Merchants must comply with laws that apply to minors and their own programs.</p>
        </section>

        <section class="mg-legal-section" id="international">
          <h2>13. International use and transfers</h2>
          <p>Microgifter is operated from the United States. Information may be processed in the United States and other countries where Microgifter or its service providers operate. Those countries may have different data-protection laws.</p>
          <p>Where required, Microgifter will use recognized safeguards for international transfers and honor mandatory local rights.</p>
        </section>

        <section class="mg-legal-section" id="changes">
          <h2>14. Changes to this policy</h2>
          <p>We may update this Privacy Policy to reflect new Services, legal requirements, security practices, or changes in how information is handled. The “Last updated” date will show when revisions were made.</p>
          <p>When changes are material, we may provide additional notice through the Services, account communications, email, or another appropriate method. Continued use after an update is subject to the revised policy, except where consent is legally required.</p>
        </section>

        <section class="mg-legal-section" id="contact">
          <h2>15. Contact us</h2>
          <p>Questions, concerns, security reports, and privacy requests may be submitted using the contact information below.</p>
          <div class="mg-legal-contact">
            <strong>Microgifter Privacy</strong>
            <p>Email: <a href="mailto:admin@microgifter.com">admin@microgifter.com</a></p>
            <p>Online: <a href="/learn-more.php">Contact Microgifter</a></p>
            <p>United States</p>
          </div>
        </section>
      </div>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
