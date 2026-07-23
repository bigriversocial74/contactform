<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Terms of Service | Microgifter';
$page_section = 'legal';
$header_mode = 'public';
$page_body_class = 'mg-legal-public-page';
$page_styles = ['/assets/css/legal-pages.css?v=1.0.0'];
$page_meta = [
    'description' => 'Terms governing use of Microgifter social gifting, merchant CRM, campaigns, rewards, claims, subscriptions, automated commerce, agent features, and MCP services.',
    'canonical' => 'https://microgifter.com/terms.php',
    'og_title' => 'Microgifter Terms of Service',
    'og_description' => 'Review the terms for customers, merchants, creators, organizations, and authorized agents using Microgifter.',
];
$page_manifest = [
    'id' => 'terms',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'description' => $page_meta['description'],
    'onboarding' => ['enabled' => false, 'page' => 'terms', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<article class="mg-legal-page">
  <header class="mg-legal-hero">
    <div class="mg-legal-wrap">
      <p class="mg-legal-eyebrow">Rules for using the Microgifter platform</p>
      <h1>Terms of Service</h1>
      <p class="mg-legal-summary">These Terms govern access to and use of Microgifter by customers, merchants, creators, organizations, sponsors, account administrators, developers, connected agents, and other users.</p>
      <div class="mg-legal-meta"><span>Effective July 23, 2026</span><span>Last updated July 23, 2026</span></div>
      <div class="mg-legal-promise">
        <strong>Merchant data remains owned by the merchant.</strong>
        <p>Merchants give Microgifter permission to process and analyze their data across Microgifter services so we can operate gifting, CRM, campaigns, rewards, claims, analytics, demand intelligence, automated commerce, agent features, security, and platform improvement. Microgifter does not sell customer or merchant data to third parties.</p>
      </div>
    </div>
  </header>

  <div class="mg-legal-body">
    <div class="mg-legal-wrap mg-legal-layout">
      <nav class="mg-legal-toc" aria-label="Terms of Service contents">
        <strong>Contents</strong>
        <a href="#acceptance">1. Acceptance</a>
        <a href="#eligibility">2. Eligibility and accounts</a>
        <a href="#services">3. The Services</a>
        <a href="#merchants">4. Merchant responsibilities</a>
        <a href="#purchases">5. Gifts and purchases</a>
        <a href="#payments">6. Fees and subscriptions</a>
        <a href="#claims">7. Claims and redemption</a>
        <a href="#campaigns">8. Campaigns and rewards</a>
        <a href="#data">9. Merchant Data</a>
        <a href="#content">10. Content</a>
        <a href="#agents">11. AI, agents, and MCP</a>
        <a href="#communications">12. Communications</a>
        <a href="#acceptable-use">13. Acceptable use</a>
        <a href="#third-party">14. Third parties</a>
        <a href="#termination">15. Suspension and termination</a>
        <a href="#intellectual-property">16. Intellectual property</a>
        <a href="#disclaimers">17. Disclaimers</a>
        <a href="#liability">18. Liability</a>
        <a href="#indemnity">19. Indemnity</a>
        <a href="#disputes">20. Disputes</a>
        <a href="#changes">21. Changes</a>
        <a href="#contact">22. Contact</a>
      </nav>

      <div class="mg-legal-content">
        <section class="mg-legal-section" id="acceptance">
          <h2>1. Acceptance of these Terms</h2>
          <p>These Terms of Service (“Terms”) form a binding agreement between the person or organization using the Services (“you”) and Microgifter (“Microgifter,” “we,” “us,” or “our”). By creating an account, purchasing or sending a Microgift, claiming or redeeming value, using a merchant or creator workspace, connecting an agent, accessing an MCP service, or otherwise using the Services, you agree to these Terms and our <a href="/privacy.php">Privacy Policy</a>.</p>
          <p>If you use the Services for a company, merchant, organization, employer, sponsor, or other entity, you represent that you have authority to bind that entity. In that case, “you” includes the entity.</p>
          <p>If you do not agree, do not use the Services.</p>
        </section>

        <section class="mg-legal-section" id="eligibility">
          <h2>2. Eligibility and account security</h2>
          <p>You must be at least 13 years old to use a personal customer account. If you are under the age of majority where you live, a parent or legal guardian must approve your use. Merchant owners, organization administrators, subscription purchasers, payout recipients, and persons entering binding commercial commitments must be at least 18 and legally capable of contracting.</p>
          <p>You agree to provide accurate, current, and complete information and to keep it updated. You are responsible for activity under your account, credentials, devices, connected agents, API credentials, recovery codes, team members, and permissions.</p>
          <p>You must promptly notify Microgifter if you suspect unauthorized access. We may require email verification, multi-factor authentication, reauthentication, identity checks, or additional security controls.</p>
        </section>

        <section class="mg-legal-section" id="services">
          <h2>3. The Microgifter Services</h2>
          <p>Microgifter provides a platform for social gifting, pre-sale commerce, customer relationship management, campaigns, offers, rewards, loyalty, referrals, contests, creator programs, claims, redemption, messaging, analytics, recurring programs, workplace and community gifting, automated commerce, AI-assisted features, Model Context Protocol connections, and related tools.</p>
          <p>Features may vary by account type, package, location, merchant, campaign, integration, device, or availability. Some features may be beta, preview, pilot, limited-release, or dependent on third-party services.</p>
          <p>Microgifter may add, remove, modify, suspend, or discontinue features. We will provide notice when reasonably practical if a material change affects a paid subscription.</p>
        </section>

        <section class="mg-legal-section" id="merchants">
          <h2>4. Merchant, creator, and organization responsibilities</h2>
          <p>Merchants, creators, employers, sponsors, and organizations are responsible for their own products, services, experiences, content, offers, campaigns, rewards, fulfillment, taxes, licenses, disclosures, customer service, and legal compliance.</p>
          <p>Merchants agree to:</p>
          <ul>
            <li>Provide accurate descriptions, prices, availability, restrictions, locations, expiration terms, refund terms, and redemption instructions.</li>
            <li>Honor valid purchases, claims, rewards, offers, and redemptions according to the published terms and applicable law.</li>
            <li>Maintain appropriate inventory, staffing, licensing, insurance, health, safety, accessibility, and consumer-protection compliance.</li>
            <li>Use customer records, campaigns, messages, location data, analytics, and automation lawfully and only for authorized business purposes.</li>
            <li>Obtain all notices, permissions, consents, and legal bases required for customer data, marketing, communications, creator relationships, contests, and sponsored programs.</li>
            <li>Protect account access and promptly remove former employees, contractors, creators, or agents who should no longer have access.</li>
            <li>Not misrepresent participation, performance, availability, sponsorship, endorsements, customer results, or Microgifter capabilities.</li>
          </ul>
          <p>Microgifter may review, reject, remove, pause, or require changes to content, offers, campaigns, rewards, products, or accounts that violate these Terms, create risk, or could harm users or the platform.</p>
        </section>

        <section class="mg-legal-section" id="purchases">
          <h2>5. Microgifts, purchases, and recipients</h2>
          <p>A Microgift may represent a product, service, experience, reward, offer, stored value, sponsored benefit, or other merchant commitment. The applicable merchant is generally responsible for the underlying item, description, availability, quality, fulfillment, and legally required remedies unless checkout expressly identifies Microgifter as the seller.</p>
          <p>Users are responsible for entering correct recipient, delivery, scheduling, and contact information. A sender must have permission to provide a recipient’s information and must not use gifting, messaging, or scheduling features for harassment, spam, deception, or unlawful activity.</p>
          <p>Delivery times, notifications, claim availability, and redemption depend on network, device, merchant, recipient, and third-party conditions. Microgifter does not guarantee that a recipient will open, accept, claim, or redeem a gift.</p>
        </section>

        <section class="mg-legal-section" id="payments">
          <h2>6. Payments, subscriptions, commissions, and taxes</h2>
          <p>Prices, package limits, transaction charges, commissions, processing fees, recurring billing terms, and other charges will be shown at checkout, in a package description, merchant agreement, or account workspace.</p>
          <p>You authorize Microgifter and its payment processors to charge the payment method provided for purchases, subscriptions, renewals, commissions, adjustments, taxes, and other authorized amounts. Payment processors may impose their own terms and privacy practices.</p>
          <p>Subscriptions renew according to the billing interval shown at purchase until canceled. Cancellation generally stops future renewal and does not automatically refund a current billing period unless required by law or stated otherwise.</p>
          <p>Merchants are responsible for taxes, reporting, permits, and financial obligations associated with their sales, payouts, rewards, and programs unless Microgifter expressly agrees to handle a specific obligation.</p>
          <p>We may correct pricing or billing errors, reverse duplicate or fraudulent transactions, hold funds where legally permitted, and request additional verification.</p>
        </section>

        <section class="mg-legal-section" id="claims">
          <h2>7. Claims, redemption, refunds, expiration, and regifting</h2>
          <p>Microgifter may track a Microgift through statuses such as inbox, outbox, sent, claimed, redeemed, refunded, canceled, or regifted. A status record does not independently establish that a merchant fulfilled its obligation.</p>
          <p>Claim codes, QR codes, links, and redemption credentials must be protected. Users may not duplicate, alter, sell, guess, scrape, reuse, or fraudulently present a credential.</p>
          <p>Expiration, refund, transfer, cash-redemption, replacement, and unused-balance rules may depend on merchant terms and applicable gift-card, stored-value, promotional, contest, tax, or consumer laws. Where a merchant term conflicts with mandatory law, the law controls.</p>
          <p>Microgifter may pause, reverse, or investigate a claim, transfer, refund, or redemption when fraud, error, duplicate use, unauthorized access, chargeback, campaign abuse, or legal risk is suspected.</p>
        </section>

        <section class="mg-legal-section" id="campaigns">
          <h2>8. Campaigns, rewards, loyalty, referrals, and contests</h2>
          <p>Campaigns and programs may include separate eligibility rules, dates, locations, actions, limits, claim requirements, approval steps, reward conditions, sponsor terms, and merchant terms. Those program-specific rules are incorporated into these Terms.</p>
          <p>Participants must complete actions honestly. Microgifter or the applicable merchant may reject, reverse, withhold, or cancel credit, rewards, entries, referrals, claims, or attributed activity resulting from manipulation, duplicate accounts, bots, false evidence, self-referrals, collusion, abuse, or technical error.</p>
          <p>Merchants and sponsors are responsible for legally required contest rules, disclosures, registrations, winner selection, taxes, prizes, and advertising compliance. Microgifter may provide tools but does not become the legal sponsor unless expressly stated.</p>
        </section>

        <section class="mg-legal-section" id="data">
          <h2>9. Merchant Data, customer records, and analytics</h2>
          <div class="mg-legal-callout">
            <p><strong>Merchant Data remains owned by the merchant.</strong> Microgifter does not acquire ownership of merchant customer lists, CRM records, catalogs, campaigns, creative assets, communications, transaction records, or other business data merely because it is stored or processed through the Services.</p>
          </div>
          <p>“Merchant Data” means data, content, and records submitted, generated, imported, or maintained by or for a merchant through the Services, excluding Microgifter software, system data, platform-wide analytics, and independently collected Microgifter data.</p>
          <p>The merchant grants Microgifter a worldwide, nonexclusive, royalty-free license to host, store, copy, reproduce, transmit, display, organize, combine, process, analyze, model, secure, and otherwise use Merchant Data for the following purposes:</p>
          <ul>
            <li>Providing and supporting all Microgifter services and features used by the merchant, customers, recipients, creators, sponsors, organizations, and authorized users.</li>
            <li>Operating gifting, CRM, campaigns, offers, rewards, loyalty, claims, redemption, messaging, attribution, creator programs, subscriptions, commerce, reporting, and integrations.</li>
            <li>Personalizing experiences, generating recommendations, identifying opportunities, measuring engagement, forecasting demand, and providing merchant or customer insights.</li>
            <li>Operating AI-assisted, automated, agent, and MCP functionality within approved permissions and platform rules.</li>
            <li>Preventing fraud and abuse; securing, auditing, troubleshooting, maintaining, marketing, improving, and developing Microgifter.</li>
            <li>Creating aggregated, statistical, benchmarked, or deidentified information that does not reasonably identify a merchant or individual.</li>
          </ul>
          <p>This license allows Microgifter to use subcontractors and service providers as needed to perform these purposes, subject to appropriate obligations. It does not authorize Microgifter to sell Merchant Data or customer personal information to data brokers or unrelated third parties.</p>
          <p>The license continues while Merchant Data remains in the Services and for a reasonable period afterward for backups, legal obligations, security, disputes, fraud prevention, and transaction integrity. Microgifter may retain and use aggregated or deidentified information after account termination where it no longer reasonably identifies the merchant or an individual.</p>
          <p>Merchants represent that they have all rights and permissions needed to provide Merchant Data and authorize the uses described above. Merchants are responsible for responding to customer requests and complying with privacy, marketing, employment, consumer, and data-protection laws applicable to their own business.</p>
        </section>

        <section class="mg-legal-section" id="content">
          <h2>10. User and merchant content</h2>
          <p>You retain ownership of content you create and submit, subject to rights held by others. You grant Microgifter a worldwide, nonexclusive, royalty-free license to host, store, reproduce, adapt for formatting, transmit, display, distribute, and use that content as needed to operate, promote, secure, and improve the Services and to make content available according to your selected settings.</p>
          <p>Public content may be visible, copied, linked, indexed, or shared by others. You are responsible for obtaining rights to logos, names, music, images, video, trademarks, likenesses, reviews, testimonials, and other material you submit.</p>
          <p>You must not upload content that is unlawful, infringing, deceptive, defamatory, exploitative, malicious, invasive, discriminatory, or that violates another person’s rights.</p>
        </section>

        <section class="mg-legal-section" id="agents">
          <h2>11. AI-assisted features, agents, automation, and MCP</h2>
          <p>Microgifter may provide AI-assisted tools, inline agents, automated workflows, predictive or demand intelligence, generated content, recommendations, and MCP connections to compatible external clients or agent harnesses.</p>
          <p>AI and automated outputs may be incomplete, inaccurate, outdated, or unsuitable. Users must review outputs and remain responsible for decisions, approvals, messages, campaigns, pricing, financial activity, customer treatment, legal compliance, and actions taken through their account.</p>
          <p>Connected agents do not bypass Microgifter permissions, ownership rules, campaign requirements, reward logic, limits, approvals, or action receipts. You are responsible for the agent, client, integration, permissions, scopes, credentials, instructions, and data you authorize.</p>
          <p>You may not use AI or agents to impersonate others, make unauthorized decisions, scrape data, evade limits, manipulate rewards, send unlawful messages, perform discriminatory profiling, or take actions without required human approval.</p>
        </section>

        <section class="mg-legal-section" id="communications">
          <h2>12. Communications and electronic notices</h2>
          <p>You agree that Microgifter may send transactional, account, security, claim, purchase, subscription, support, and service notices electronically. Marketing communications may be sent where permitted and may include unsubscribe or preference controls.</p>
          <p>Merchants are responsible for ensuring that their own email, text, push, direct message, automated communication, and campaign practices comply with consent, disclosure, opt-out, quiet-hour, and other applicable requirements.</p>
        </section>

        <section class="mg-legal-section" id="acceptable-use">
          <h2>13. Acceptable use</h2>
          <p>You may not:</p>
          <ul>
            <li>Violate law, regulations, sanctions, intellectual-property rights, privacy rights, publicity rights, consumer rights, or contractual obligations.</li>
            <li>Use the Services for fraud, money laundering, unauthorized financial activity, deceptive promotions, illegal goods or services, harassment, exploitation, or harmful content.</li>
            <li>Access another account, record, merchant, customer, device, credential, claim, reward, or system without authorization.</li>
            <li>Probe, scan, attack, overload, disrupt, reverse engineer, bypass, disable, or interfere with security, rate limits, permissions, audits, APIs, or platform safeguards.</li>
            <li>Use bots, scripts, fake accounts, automated claims, false evidence, or coordinated behavior to manipulate campaigns, rewards, referrals, engagement, reviews, rankings, attribution, or analytics.</li>
            <li>Scrape, harvest, resell, broker, or commercially exploit personal data, Merchant Data, platform data, content, or access except as expressly authorized.</li>
            <li>Introduce malware, malicious code, unsafe files, or credentials obtained from unauthorized sources.</li>
            <li>Misrepresent identity, authority, affiliation, sponsorship, approval, performance, results, or the source of content.</li>
          </ul>
        </section>

        <section class="mg-legal-section" id="third-party">
          <h2>14. Third-party services and links</h2>
          <p>The Services may depend on or link to payment processors, maps, email providers, hosting providers, social networks, AI model providers, agent clients, merchant systems, and other third parties. Their terms and privacy practices govern their services.</p>
          <p>Microgifter is not responsible for third-party availability, security, content, conduct, products, or decisions. A link or integration does not imply endorsement.</p>
        </section>

        <section class="mg-legal-section" id="termination">
          <h2>15. Suspension and termination</h2>
          <p>You may stop using the Services and may cancel a subscription through available account controls, subject to outstanding transactions, commitments, fees, and retention obligations.</p>
          <p>Microgifter may restrict, suspend, or terminate access when reasonably necessary to address security, fraud, abuse, nonpayment, legal requirements, harmful conduct, repeated complaints, platform risk, or violations of these Terms.</p>
          <p>Termination does not eliminate payment obligations, transaction commitments, merchant fulfillment duties, rights accrued before termination, or provisions that by their nature should survive, including data licenses, intellectual property, disclaimers, liability limits, indemnity, and dispute provisions.</p>
        </section>

        <section class="mg-legal-section" id="intellectual-property">
          <h2>16. Microgifter intellectual property</h2>
          <p>Microgifter and its licensors own the Services, software, interfaces, workflows, databases, designs, documentation, trademarks, logos, platform content, analytics models, and related intellectual property, excluding user and Merchant Data.</p>
          <p>Subject to these Terms, Microgifter grants you a limited, revocable, nonexclusive, nontransferable right to access and use the Services for their intended purposes. No other rights are granted.</p>
          <p>Feedback may be used by Microgifter without restriction or compensation, provided we do not publicly identify you as the source without permission.</p>
        </section>

        <section class="mg-legal-section" id="disclaimers">
          <h2>17. Disclaimers</h2>
          <p>To the maximum extent permitted by law, the Services are provided “as is” and “as available.” Microgifter disclaims implied warranties of merchantability, fitness for a particular purpose, title, noninfringement, uninterrupted availability, accuracy, and results.</p>
          <p>Microgifter does not guarantee sales, engagement, customer retention, redemption, campaign performance, future demand, merchant results, AI output, agent actions, availability of a merchant item, or the conduct of users, merchants, creators, recipients, sponsors, or third parties.</p>
          <p>Nothing in these Terms excludes warranties or rights that cannot legally be excluded.</p>
        </section>

        <section class="mg-legal-section" id="liability">
          <h2>18. Limitation of liability</h2>
          <p>To the maximum extent permitted by law, Microgifter and its officers, directors, employees, contractors, affiliates, and service providers will not be liable for indirect, incidental, special, consequential, exemplary, or punitive damages, lost profits, lost revenue, lost data, lost goodwill, business interruption, or substitute services arising from or related to the Services.</p>
          <p>To the maximum extent permitted by law, Microgifter’s total aggregate liability for claims arising from the Services will not exceed the greater of: (a) the amount you paid directly to Microgifter during the twelve months before the event giving rise to the claim; or (b) one hundred U.S. dollars.</p>
          <p>These limits do not apply where prohibited by law and do not limit liability for fraud, willful misconduct, or other liability that cannot legally be limited.</p>
        </section>

        <section class="mg-legal-section" id="indemnity">
          <h2>19. Indemnity</h2>
          <p>To the extent permitted by law, merchants, creators, organizations, sponsors, and business users agree to defend, indemnify, and hold harmless Microgifter and its officers, directors, employees, contractors, affiliates, and service providers from claims, damages, losses, liabilities, judgments, costs, and reasonable legal fees arising from:</p>
          <ul>
            <li>Their products, services, experiences, fulfillment, content, offers, campaigns, rewards, contests, communications, or customer relationships.</li>
            <li>Merchant Data or other data they provide or instruct Microgifter to process.</li>
            <li>Their violation of law, these Terms, or another person’s rights.</li>
            <li>Unauthorized account, employee, contractor, creator, integration, or agent activity under their control.</li>
          </ul>
        </section>

        <section class="mg-legal-section" id="disputes">
          <h2>20. Governing law and disputes</h2>
          <p>Except where mandatory local law provides otherwise, these Terms are governed by the laws of the State of Arizona, without regard to conflict-of-law principles.</p>
          <p>Before filing a formal claim, you and Microgifter agree to attempt in good faith to resolve the dispute by written notice describing the issue and requested resolution. If the dispute is not resolved, exclusive jurisdiction and venue will lie in the state or federal courts located in Maricopa County, Arizona, unless applicable law requires another forum.</p>
          <p>Nothing in this section prevents either party from seeking urgent injunctive relief for security, intellectual-property, confidentiality, or unauthorized-access matters.</p>
        </section>

        <section class="mg-legal-section" id="changes">
          <h2>21. Changes to these Terms</h2>
          <p>We may update these Terms to reflect changes to the Services, law, risk, security, business practices, or platform functionality. The “Last updated” date will identify revisions.</p>
          <p>For material changes, we may provide notice through the Services, account communications, email, or another reasonable method. Continued use after the effective date constitutes acceptance unless applicable law requires another form of consent.</p>
          <p>If a provision is unenforceable, it will be modified to the minimum extent necessary and the remaining provisions will continue in effect. Failure to enforce a provision is not a waiver. You may not assign these Terms without Microgifter’s consent; Microgifter may assign them as part of a corporate transaction or service reorganization.</p>
        </section>

        <section class="mg-legal-section" id="contact">
          <h2>22. Contact us</h2>
          <p>Questions about these Terms may be submitted using the contact information below.</p>
          <div class="mg-legal-contact">
            <strong>Microgifter Legal</strong>
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
