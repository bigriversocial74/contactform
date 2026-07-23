<?php
declare(strict_types=1);
?>
<div class="mg-cookie-consent" data-mg-cookie-consent-root data-policy-version="2026-07-23.1">
  <section class="mg-cookie-consent__banner" data-mg-cookie-banner hidden aria-labelledby="mg-cookie-banner-title">
    <div class="mg-cookie-consent__banner-copy">
      <p class="mg-cookie-consent__eyebrow">Your privacy choices</p>
      <h2 id="mg-cookie-banner-title">Choose how Microgifter uses cookies.</h2>
      <p>Necessary technologies keep accounts, sessions, carts, claims, security, and saved privacy choices working. Optional technologies are used only with your permission. Microgifter does not sell customer or merchant data.</p>
      <div class="mg-cookie-consent__links">
        <a href="/cookies.php">Cookie Policy</a>
        <a href="/privacy.php">Privacy Policy</a>
      </div>
    </div>
    <div class="mg-cookie-consent__banner-actions" aria-label="Cookie consent actions">
      <button class="mg-cookie-consent__button mg-cookie-consent__button--choice" type="button" data-mg-consent-action="reject">Reject non-essential</button>
      <button class="mg-cookie-consent__button mg-cookie-consent__button--manage" type="button" data-mg-consent-action="settings">Manage preferences</button>
      <button class="mg-cookie-consent__button mg-cookie-consent__button--choice" type="button" data-mg-consent-action="accept">Accept all</button>
    </div>
  </section>

  <div class="mg-cookie-consent__overlay" data-mg-cookie-overlay hidden>
    <section class="mg-cookie-consent__dialog" data-mg-cookie-dialog role="dialog" aria-modal="true" aria-labelledby="mg-cookie-dialog-title" tabindex="-1">
      <header class="mg-cookie-consent__dialog-header">
        <div>
          <p class="mg-cookie-consent__eyebrow">Cookie preferences</p>
          <h2 id="mg-cookie-dialog-title">Control optional technologies.</h2>
          <p>Choose each purpose independently. You can change or withdraw your choices at any time through Cookie Settings in the footer.</p>
        </div>
        <button class="mg-cookie-consent__close" type="button" data-mg-consent-action="close" aria-label="Close cookie preferences">×</button>
      </header>

      <div class="mg-cookie-consent__categories">
        <article class="mg-cookie-consent__category">
          <div>
            <h3>Strictly necessary</h3>
            <p>Required for authentication, security, CSRF protection, carts, claims, transactions, account workflows, and storing your privacy choices.</p>
          </div>
          <label class="mg-cookie-consent__switch mg-cookie-consent__switch--locked">
            <span>Always active</span>
            <input type="checkbox" checked disabled data-mg-consent-category="necessary" aria-label="Strictly necessary cookies are always active">
            <i aria-hidden="true"></i>
          </label>
        </article>

        <article class="mg-cookie-consent__category">
          <div>
            <h3>Functional</h3>
            <p>Remembers optional display, convenience, and personalization choices that are not required for the service you requested.</p>
          </div>
          <label class="mg-cookie-consent__switch">
            <span>Allow functional</span>
            <input type="checkbox" data-mg-consent-category="functional">
            <i aria-hidden="true"></i>
          </label>
        </article>

        <article class="mg-cookie-consent__category">
          <div>
            <h3>Analytics</h3>
            <p>Helps Microgifter understand site and product usage, performance, errors, and engagement so the platform can be improved.</p>
          </div>
          <label class="mg-cookie-consent__switch">
            <span>Allow analytics</span>
            <input type="checkbox" data-mg-consent-category="analytics">
            <i aria-hidden="true"></i>
          </label>
        </article>

        <article class="mg-cookie-consent__category">
          <div>
            <h3>Marketing</h3>
            <p>Supports advertising measurement, campaign attribution, retargeting, and personalized promotion outside essential merchant transaction activity.</p>
          </div>
          <label class="mg-cookie-consent__switch">
            <span>Allow marketing</span>
            <input type="checkbox" data-mg-consent-category="marketing">
            <i aria-hidden="true"></i>
          </label>
        </article>

        <article class="mg-cookie-consent__category">
          <div>
            <h3>External media</h3>
            <p>Allows optional third-party video, audio, maps, social embeds, and widgets that may set their own cookies or similar storage.</p>
          </div>
          <label class="mg-cookie-consent__switch">
            <span>Allow external media</span>
            <input type="checkbox" data-mg-consent-category="external_media">
            <i aria-hidden="true"></i>
          </label>
        </article>
      </div>

      <footer class="mg-cookie-consent__dialog-footer">
        <div class="mg-cookie-consent__dialog-links">
          <a href="/cookies.php">View Cookie Policy</a>
          <a href="/privacy.php">View Privacy Policy</a>
        </div>
        <div class="mg-cookie-consent__dialog-actions">
          <button class="mg-cookie-consent__button mg-cookie-consent__button--choice" type="button" data-mg-consent-action="reject">Reject non-essential</button>
          <button class="mg-cookie-consent__button mg-cookie-consent__button--save" type="button" data-mg-consent-action="save">Save preferences</button>
          <button class="mg-cookie-consent__button mg-cookie-consent__button--choice" type="button" data-mg-consent-action="accept">Accept all</button>
        </div>
      </footer>
    </section>
  </div>

  <div class="mg-cookie-consent__status" data-mg-cookie-status role="status" aria-live="polite" aria-atomic="true"></div>
</div>
