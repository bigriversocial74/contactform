<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Discover profiles | Microgifter';
$page_section = 'discover';
$header_mode = 'public';

$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/profile-discovery.css',
];

$page_scripts = [
    '/assets/js/profile-discovery.js',
];

$page_manifest = [
    'id' => 'discover',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-discovery-page',
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'show_cart' => false,
        'cart' => false,
        'links' => [
            [
                'label' => 'Feed',
                'href' => '/feed.php',
            ],
            [
                'label' => 'Discover',
                'href' => '/discover.php',
            ],
            [
                'label' => 'Book A Demo',
                'href' => '/learn-more.php',
            ],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'discover',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
:root{
  --discover-dark:#071225;
  --discover-muted:#64748b;
  --discover-border:#dbe5f1;
  --discover-purple:#7c3aed;
  --discover-teal:#20bfd2;
}

/* Remove legacy cart controls on this public page. */
body.mg-discovery-page .mg-cart-trigger,
body.mg-discovery-page .mg-header-cart,
body.mg-discovery-page [data-mg-cart-trigger],
body.mg-discovery-page [data-header-cart],
body.mg-discovery-page .mg-cart-control{
  display:none !important;
}

.mg-discovery-shell{
  background:#fff;
}

.mg-discovery-hero{
  position:relative;
  overflow:hidden;
  padding:100px 0 88px;
  border-bottom:1px solid var(--discover-border);
  background:
    radial-gradient(circle at 18% 18%,rgba(237,233,254,.72),transparent 30%),
    radial-gradient(circle at 84% 22%,rgba(220,252,231,.42),transparent 28%),
    linear-gradient(180deg,#fff,#f8fafc 68%,#eef2f7);
}

.mg-discovery-hero::before,
.mg-discovery-content::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.48;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.mg-discovery-hero .mg-container{
  position:relative;
  z-index:1;
}

.mg-discovery-hero h1{
  max-width:820px;
  margin:18px 0 0;
  color:var(--discover-dark);
  font-size:clamp(46px,5.8vw,76px);
  line-height:.95;
  letter-spacing:-.07em;
}

.mg-discovery-hero p{
  max-width:720px;
  margin:22px 0 0;
  color:var(--discover-muted);
  font-size:18px;
  line-height:1.6;
}

.mg-discovery-search{
  margin-top:34px;
}

.mg-discovery-content{
  position:relative;
  overflow:hidden;
  padding-top:96px;
  padding-bottom:120px;
  background:
    radial-gradient(circle at 84% 8%,rgba(237,233,254,.5),transparent 28%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.mg-discovery-content > *{
  position:relative;
  z-index:1;
}

/*
 * Only the recent featured section is visible.
 * The discovery script can continue using its existing data hooks.
 */
[data-featured-section],
[data-storefront-section],
[data-discovery-pagination],
.mg-discovery-section[aria-labelledby="discovery-results-title"]{
  display:none !important;
}

[data-recent-section]{
  display:block !important;
}

[data-recent-section] .mg-discovery-heading{
  margin-bottom:26px;
}

[data-recent-section] .mg-discovery-heading h2{
  margin:7px 0 0;
  color:var(--discover-dark);
  font-size:clamp(34px,4vw,52px);
  line-height:1;
  letter-spacing:-.055em;
}

/* Ensure generated profile cards can display both cover and avatar images. */
.mg-discovery-card{
  overflow:hidden;
  border:1px solid var(--discover-border);
  border-radius:24px;
  background:#fff;
  box-shadow:0 20px 54px rgba(15,23,42,.08);
}

.mg-discovery-card-header,
.mg-profile-card-header,
[data-profile-cover]{
  position:relative;
  width:100%;
  min-height:150px;
  overflow:hidden;
  background:
    radial-gradient(circle at 30% 20%,rgba(196,181,253,.8),transparent 34%),
    linear-gradient(135deg,#ede9fe,#cffafe);
}

.mg-discovery-card-header img,
.mg-profile-card-header img,
[data-profile-cover] img,
.mg-discovery-cover,
.mg-profile-cover{
  width:100%;
  height:150px;
  display:block;
  object-fit:cover;
}

.mg-discovery-avatar,
.mg-profile-avatar,
[data-profile-avatar]{
  width:76px;
  height:76px;
  overflow:hidden;
  border:4px solid #fff;
  border-radius:999px;
  background:#fff;
  box-shadow:0 10px 26px rgba(15,23,42,.16);
}

.mg-discovery-avatar img,
.mg-profile-avatar img,
[data-profile-avatar] img{
  width:100%;
  height:100%;
  display:block;
  object-fit:cover;
}

/* Wrapper-safe four-column footer */
#discover-public-footer{
  position:relative;
  z-index:2;
  width:100%;
  padding:84px 0 34px;
  border-top:1px solid #e2e8f0;
  background:#fff;
  color:#071225;
  box-sizing:border-box;
}

#discover-public-footer *{
  box-sizing:border-box;
}

.discover-public-footer__inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.discover-public-footer__grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:48px;
  align-items:start;
}

.discover-public-footer__logo{
  display:inline-flex;
  align-items:center;
  gap:11px;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.discover-public-footer__mark{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 26px rgba(124,58,237,.18);
}

.discover-public-footer__brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.discover-public-footer__socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.discover-public-footer__socials a{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border:1px solid #dbe5f1;
  border-radius:12px;
  background:#f8fafc;
  color:#475569;
  text-decoration:none;
  font-size:13px;
  font-weight:950;
}

.discover-public-footer__column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.discover-public-footer__column nav{
  display:grid;
  gap:13px;
}

.discover-public-footer__column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
}

.discover-public-footer__bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.discover-public-footer__bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.discover-public-footer__bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:820px){
  .discover-public-footer__grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}

@media(max-width:680px){
  .mg-discovery-hero{
    padding:72px 0 64px;
  }

  .mg-discovery-content{
    padding-top:72px;
    padding-bottom:88px;
  }

  .discover-public-footer__grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .discover-public-footer__bottom{
    display:grid;
  }
}
</style>

<main class="mg-discovery-shell" data-profile-discovery>
  <section class="mg-discovery-hero">
    <div class="mg-container">
      <span class="mg-kicker">Profile discovery</span>
      <h1>Discover the newest featured local profiles.</h1>
      <p>
        Explore the most recently featured creators, merchants, people,
        and local gifting profiles published through Microgifter.
      </p>

      <form class="mg-discovery-search" data-discovery-form role="search">
        <label class="mg-discovery-query">
          Search profiles
          <input
            type="search"
            name="q"
            maxlength="100"
            autocomplete="off"
            placeholder="Name, profile, headline, or location"
          >
        </label>

        <label>
          Profile type
          <select name="type">
            <option value="">All profile types</option>
            <option value="customer">Customer</option>
            <option value="creator">Creator</option>
            <option value="merchant">Merchant</option>
            <option value="marketing_affiliate">Marketing affiliate</option>
          </select>
        </label>

        <label>
          Location
          <input
            type="search"
            name="location"
            maxlength="100"
            placeholder="City or region"
          >
        </label>

        <label>
          Category
          <input
            type="search"
            name="category"
            maxlength="60"
            placeholder="Product category"
          >
        </label>

        <button class="mg-btn mg-btn-primary" type="submit">Search</button>
        <button
          class="mg-btn mg-btn-ghost"
          type="reset"
          data-discovery-reset
        >
          Reset
        </button>
      </form>
    </div>
  </section>

  <div class="mg-container mg-discovery-content">
    <div
      class="mg-discovery-status"
      data-discovery-status
      role="status"
      aria-live="polite"
    ></div>

    <section
      class="mg-discovery-state"
      data-discovery-loading
      aria-busy="true"
    >
      <div class="mg-discovery-card-grid">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <article
            class="mg-discovery-card is-skeleton"
            aria-hidden="true"
          ></article>
        <?php endfor; ?>
      </div>
    </section>

    <section
      class="mg-discovery-state mg-hidden"
      data-discovery-error
      role="alert"
    >
      <div class="mg-panel mg-discovery-message">
        <h2>Search is temporarily unavailable.</h2>
        <p data-discovery-error-message>
          We could not load profile discovery.
        </p>
        <button
          class="mg-btn mg-btn-primary"
          type="button"
          data-discovery-retry
        >
          Try again
        </button>
      </div>
    </section>

    <section
      class="mg-discovery-state mg-hidden"
      data-discovery-empty
    >
      <div class="mg-panel mg-discovery-message">
        <h2>No featured profiles are available yet.</h2>
        <p>
          Recently featured profiles will appear here when they are published.
        </p>
      </div>
    </section>

    <section
      class="mg-discovery-state mg-hidden"
      data-discovery-no-results
    >
      <div class="mg-panel mg-discovery-message">
        <h2>No matching profiles.</h2>
        <p>
          Try a broader search term, location, category, or profile type.
        </p>
      </div>
    </section>

    <div class="mg-hidden" data-discovery-content>
      <section
        class="mg-discovery-section mg-hidden"
        data-featured-section
      >
        <div class="mg-discovery-card-grid" data-featured-grid></div>
      </section>

      <section
        class="mg-discovery-section mg-hidden"
        data-storefront-section
      >
        <div class="mg-discovery-card-grid" data-storefront-grid></div>
      </section>

      <section
        class="mg-discovery-section"
        data-recent-section
      >
        <div class="mg-discovery-heading">
          <div>
            <span class="mg-kicker">Recently featured</span>
            <h2>Newest profiles</h2>
          </div>
          <p>
            The latest public profiles, including profile photos,
            header images, locations, and published storefront details.
          </p>
        </div>

        <div
          class="mg-discovery-card-grid"
          data-recent-grid
        ></div>
      </section>

      <section
        class="mg-discovery-section mg-hidden"
        aria-labelledby="discovery-results-title"
      >
        <div class="mg-discovery-heading">
          <div>
            <span class="mg-kicker">Organic results</span>
            <h2 id="discovery-results-title">Profiles</h2>
          </div>
          <p data-results-summary></p>
        </div>

        <div
          class="mg-discovery-card-grid"
          data-results-grid
        ></div>

        <div
          class="mg-discovery-more mg-hidden"
          data-discovery-pagination
        >
          <button
            class="mg-btn mg-btn-soft"
            type="button"
            data-discovery-more
          >
            Load more profiles
          </button>
        </div>
      </section>
    </div>
  </div>
</main>

<footer id="discover-public-footer">
  <div class="discover-public-footer__inner">
    <div class="discover-public-footer__grid">
      <div class="discover-public-footer__brand">
        <a class="discover-public-footer__logo" href="/">
          <span class="discover-public-footer__mark">M</span>
          <span>Microgifter</span>
        </a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div
          class="discover-public-footer__socials"
          aria-label="Social links"
        >
          <a href="#" aria-label="Facebook">f</a>
          <a href="#" aria-label="Instagram">ig</a>
          <a href="#" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="discover-public-footer__column">
        <h3>Product</h3>
        <nav aria-label="Product links">
          <a href="/feed.php">Gift Feed</a>
          <a href="/discover.php">Discover</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
        </nav>
      </div>

      <div class="discover-public-footer__column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="discover-public-footer__column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="discover-public-footer__bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="discover-public-footer__bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const customFooter = document.getElementById('discover-public-footer');

  if (!customFooter) {
    return;
  }

  document.body.appendChild(customFooter);

  document.querySelectorAll('body > footer').forEach(function (footer) {
    if (footer !== customFooter) {
      footer.remove();
    }
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
