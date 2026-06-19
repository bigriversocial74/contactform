<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Sign in | Microgifter';
$page_section = 'signin';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];

$page_manifest = [
    'id' => 'signin',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
           
            [
                'label' => 'Book A Demo',
                'href' => '/learn-more.php',
            ],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'signin',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
.mg-signin-page{
  position:relative;
  overflow:hidden;
  min-height:calc(100svh - 64px);
  padding:96px 0 120px;
  background:
    radial-gradient(circle at 18% 16%,rgba(237,233,254,.72),transparent 30%),
    radial-gradient(circle at 84% 20%,rgba(220,252,231,.4),transparent 28%),
    linear-gradient(180deg,#fff 0%,#f8fafc 62%,#eef2f7 100%);
}

.mg-signin-page::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.5;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.mg-signin-inner{
  position:relative;
  z-index:1;
  width:min(1060px,92%);
  margin:0 auto;
  display:grid;
  grid-template-columns:.9fr 1.1fr;
  gap:54px;
  align-items:center;
}

.mg-signin-copy h1{
  margin:18px 0 0;
  max-width:590px;
  color:#071225;
  font-size:clamp(42px,5vw,66px);
  line-height:.96;
  letter-spacing:-.068em;
}

.mg-signin-copy p{
  margin:22px 0 0;
  max-width:560px;
  color:#64748b;
  font-size:18px;
  line-height:1.6;
}

.mg-signin-card{
  width:100%;
  padding:34px;
  border:1px solid #dbe5f1;
  border-radius:28px;
  background:rgba(255,255,255,.96);
  box-shadow:0 30px 84px rgba(15,23,42,.12);
}

.mg-signin-card h2{
  margin:0 0 22px;
  color:#071225;
  font-size:34px;
  line-height:1;
  letter-spacing:-.05em;
}

.mg-signin-card label{
  display:grid;
  gap:8px;
  margin-top:18px;
  color:#475569;
  font-size:12px;
  font-weight:950;
  text-transform:uppercase;
  letter-spacing:.07em;
}

.mg-signin-card input{
  width:100%;
  min-height:56px;
  padding:14px 15px;
  border:1px solid #cbd5e1;
  border-radius:14px;
  background:#fff;
  color:#0f172a;
  font:inherit;
  outline:none;
}

.mg-signin-card input:focus{
  border-color:#7c3aed;
  box-shadow:0 0 0 4px rgba(124,58,237,.1);
}

.mg-signin-card .mg-btn{
  width:100%;
  min-height:50px;
  margin-top:24px;
  border-radius:14px;
}

.mg-signin-card p{
  margin:16px 0 0;
  color:#64748b;
  font-size:14px;
}

.mg-signin-card a{
  color:#7c3aed;
  font-weight:850;
  text-decoration:none;
}

.mg-signin-card a:hover{
  text-decoration:underline;
}

.mg-form-status{
  margin-bottom:6px;
  font-weight:850;
}

@media(max-width:900px){
  .mg-signin-inner{
    grid-template-columns:1fr;
    width:min(680px,92%);
  }

  .mg-signin-copy{
    text-align:center;
  }

  .mg-signin-copy h1,
  .mg-signin-copy p{
    margin-left:auto;
    margin-right:auto;
  }
}

@media(max-width:680px){
  .mg-signin-page{
    padding:64px 0 84px;
  }

  .mg-signin-copy{
    text-align:left;
  }

  .mg-signin-card{
    padding:22px;
    border-radius:22px;
  }
}

/* Four-column footer, moved to <body> after the shared template renders */
#mg-signin-public-footer{
  position:relative;
  z-index:2;
  width:100%;
  padding:84px 0 34px;
  border-top:1px solid #e2e8f0;
  background:#fff;
  color:#071225;
  box-sizing:border-box;
}

#mg-signin-public-footer *{
  box-sizing:border-box;
}

.mg-signin-public-footer__inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.mg-signin-public-footer__grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:48px;
  align-items:start;
}

.mg-signin-public-footer__brand{
  min-width:0;
}

.mg-signin-public-footer__logo{
  display:inline-flex;
  align-items:center;
  gap:11px;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.mg-signin-public-footer__mark{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 26px rgba(124,58,237,.18);
}

.mg-signin-public-footer__brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.mg-signin-public-footer__socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.mg-signin-public-footer__socials a{
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

.mg-signin-public-footer__column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.mg-signin-public-footer__column nav{
  display:grid;
  gap:13px;
}

.mg-signin-public-footer__column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
}

.mg-signin-public-footer__bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.mg-signin-public-footer__bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.mg-signin-public-footer__bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:820px){
  .mg-signin-public-footer__grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}

@media(max-width:680px){
  #mg-signin-public-footer{
    padding-top:64px;
  }

  .mg-signin-public-footer__grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .mg-signin-public-footer__bottom{
    display:grid;
  }
}

</style>

<main class="mg-signin-page">
  <section class="mg-signin-inner" aria-labelledby="signin-title">
    <aside class="mg-signin-copy">
      <span class="mg-badge">Welcome back</span>

      <h1 id="signin-title">
        Sign in to save gifts and manage your workspace.
      </h1>

      <p>
        Use your Microgifter account to unlock saved drafts, inbox tools,
        and permission-based workspace features.
      </p>
    </aside>

    <form
      class="mg-signin-card"
      method="post"
      action="/api/auth/login.php"
      data-auth-form="signin"
      data-success-redirect="/inbox.php"
    >
      <?= mg_csrf_field() ?>

      <h2>Sign in</h2>

      <div
        class="mg-form-status"
        data-auth-status
        role="status"
        aria-live="polite"
      ></div>

      <label>
        Email
        <input
          type="email"
          name="email"
          autocomplete="email"
          required
        >
      </label>

      <label>
        Password
        <input
          type="password"
          name="password"
          autocomplete="current-password"
          required
        >
      </label>

      <button class="mg-btn mg-btn-primary" type="submit">
        Sign in
      </button>

      <p><a href="/forgot-password.php">Forgot password?</a></p>
      <p>New here? <a href="/signup.php">Create an account</a></p>
    </form>
  </section>
</main>


<footer id="mg-signin-public-footer">
  <div class="mg-signin-public-footer__inner">
    <div class="mg-signin-public-footer__grid">
      <div class="mg-signin-public-footer__brand">
        <a class="mg-signin-public-footer__logo" href="/">
          <span class="mg-signin-public-footer__mark">M</span>
          <span>Microgifter</span>
        </a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div class="mg-signin-public-footer__socials" aria-label="Social links">
           <a href="https://instagram.com/microgifter" aria-label="Instagram">ig</a>
          <a href="https://linkedin.com/microgifter" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="mg-signin-public-footer__column">
        <h3>Product</h3>
        <nav aria-label="Product links">
          <a href="/retail.php">Retail Subscriptions</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/discover.php">Discover</a>
        </nav>
      </div>

      <div class="mg-signin-public-footer__column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="mg-signin-public-footer__column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="mg-signin-public-footer__bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="mg-signin-public-footer__bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signup.php">Create Account</a>
      </div>
    </div>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const customFooter = document.getElementById('mg-signin-public-footer');

  if (!customFooter) {
    return;
  }

  /*
   * The shared header/footer template wraps page content.
   * Moving this footer directly under <body> prevents it from inheriting
   * the sign-in grid or any constrained content-column styles.
   */
  document.body.appendChild(customFooter);

  document.querySelectorAll('body > footer').forEach(function (footer) {
    if (footer !== customFooter) {
      footer.remove();
    }
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

