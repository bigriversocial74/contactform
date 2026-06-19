<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Learn More | Microgifter';
$page_section = 'learn';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
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
                'label' => 'Book A Demo',
                'href' => '/learn-more.php',
            ],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'learn-more', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --lm-border:#dbe5f1;
  --lm-muted:#64748b;
  --lm-dark:#071225;
  --lm-purple:#7c3aed;
}

.lm-agent-page{
  background:
    radial-gradient(circle at 18% 10%,rgba(237,233,254,.64),transparent 28%),
    radial-gradient(circle at 84% 18%,rgba(220,252,231,.42),transparent 26%),
    linear-gradient(180deg,#fff,#f8fafc 58%,#eef2f7);
  color:var(--lm-dark);
  overflow:visible;
}

.lm-agent-intro,
.lm-question{
  position:relative;
  min-height:0;
  padding:120px 0;
  border-bottom:1px solid var(--lm-border);
  background:transparent;
  scroll-margin-top:88px;
}

.lm-agent-intro::before,
.lm-question::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.56;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.lm-agent-pin{
  position:relative;
  top:auto;
  height:auto;
  min-height:0;
  display:block;
  overflow:visible;
  padding:0;
}

.lm-agent-grid{
  width:min(1180px,92%);
  margin:auto;
  display:grid;
  grid-template-columns:.86fr 1.14fr;
  gap:54px;
  align-items:start;
  position:relative;
  z-index:2;
}

.lm-copy{
  padding-top:18px;
}

.lm-copy .mg-badge{
  margin-bottom:18px;
}

.lm-copy h1,
.lm-copy h2{
  margin:0;
  font-size:clamp(38px,5.2vw,68px);
  line-height:.96;
  letter-spacing:-.07em;
}

.lm-copy p{
  margin:20px 0 0;
  max-width:620px;
  color:var(--lm-muted);
  font-size:18px;
  line-height:1.58;
}

.lm-progress{
  width:min(360px,100%);
  height:8px;
  margin-top:20px;
  border-radius:999px;
  background:rgba(15,23,42,.09);
  overflow:hidden;
}

.lm-progress span{
  display:block;
  height:100%;
  width:var(--progress,0%);
  border-radius:999px;
  background:linear-gradient(90deg,var(--lm-purple),#20bfd2);
}

.lm-agent-card{
  width:100%;
  margin:0;
  padding:34px;
  border:1px solid var(--lm-border);
  border-radius:28px;
  background:rgba(255,255,255,.96);
  box-shadow:0 26px 72px rgba(15,23,42,.11);
  transform:none;
  opacity:1;
  filter:none;
}

.lm-agent-card h3{
  margin:0;
  font-size:30px;
  line-height:1;
  letter-spacing:-.05em;
}

.lm-agent-card p{
  margin:12px 0 0;
  color:var(--lm-muted);
  line-height:1.5;
}

.lm-field{
  display:grid;
  gap:10px;
  margin-top:22px;
}

.lm-field label{
  font-size:12px;
  font-weight:950;
  text-transform:uppercase;
  letter-spacing:.08em;
  color:#475569;
}

.lm-field input,
.lm-field select,
.lm-field textarea{
  width:100%;
  min-height:58px;
  padding:14px 16px;
  border:1px solid #cbd5e1;
  border-radius:15px;
  background:#fff;
  color:#0f172a;
  font:inherit;
  outline:none;
}

.lm-field textarea{
  min-height:150px;
  resize:vertical;
}

.lm-field input:focus,
.lm-field select:focus,
.lm-field textarea:focus{
  border-color:#7c3aed;
  box-shadow:0 0 0 4px rgba(124,58,237,.1);
}

.lm-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:22px;
}

.lm-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:48px;
  padding:0 19px;
  border-radius:999px;
  font-weight:950;
  cursor:pointer;
  text-decoration:none;
}

.lm-btn-primary{
  border:0;
  background:#071225;
  color:#fff;
}

.lm-btn-secondary{
  border:1px solid #cbd5e1;
  background:#fff;
  color:#071225;
}

.lm-btn-link{
  border:0;
  background:transparent;
  color:#64748b;
}

.lm-review{
  display:grid;
  gap:10px;
  margin-top:22px;
}

.lm-review-row{
  display:grid;
  grid-template-columns:150px 1fr;
  gap:15px;
  padding:14px 0;
  border-bottom:1px solid #e2e8f0;
}

.lm-review-row span{
  color:#64748b;
  font-size:12px;
  font-weight:900;
  text-transform:uppercase;
}

.lm-review-row strong{
  font-size:14px;
  word-break:break-word;
}

.lm-form-status{
  margin-top:16px;
  font-weight:850;
}

.lm-complete{
  display:none;
  margin-top:18px;
  padding:18px;
  border-radius:18px;
  background:#ecfdf5;
  color:#166534;
  font-weight:850;
}

.lm-complete.is-visible{
  display:block;
}

/* Four-column footer */
.mg-home-footer{
  padding:84px 0 34px;
  background:#fff;
  color:#071225;
}

.mg-home-footer-inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.mg-home-footer-grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:48px;
  align-items:start;
}

.mg-home-footer-brand{
  min-width:0;
}

.mg-home-footer-logo{
  display:inline-flex;
  align-items:center;
  gap:11px;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.mg-home-footer-mark{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 26px rgba(124,58,237,.18);
}

.mg-home-footer-brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.mg-home-socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.mg-home-socials a{
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

.mg-home-footer-column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.mg-home-footer-column nav{
  display:grid;
  gap:13px;
}

.mg-home-footer-column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
}

.mg-home-footer-bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.mg-home-footer-bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.mg-home-footer-bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:900px){
  .lm-agent-intro,
  .lm-question{
    padding:88px 0;
  }

  .lm-agent-grid{
    grid-template-columns:1fr;
    width:min(680px,92%);
  }

  .lm-copy{
    text-align:center;
    padding-top:0;
  }

  .lm-copy p,
  .lm-progress{
    margin-left:auto;
    margin-right:auto;
  }

  .mg-home-footer-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}

@media(max-width:680px){
  .lm-agent-intro,
  .lm-question{
    padding:64px 0;
  }

  .lm-copy{
    text-align:left;
  }

  .lm-agent-card{
    padding:22px;
    border-radius:22px;
  }

  .lm-copy h1,
  .lm-copy h2{
    font-size:clamp(34px,12vw,54px);
  }

  .lm-actions{
    display:grid;
  }

  .lm-actions > *{
    width:100%;
  }

  .lm-review-row{
    grid-template-columns:1fr;
  }

  .mg-home-footer-grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .mg-home-footer-bottom{
    display:grid;
  }
}
</style>
<div class="lm-agent-page" data-learn-more-agent>
  <section class="lm-agent-intro is-active" data-lm-stage="intro"><div class="lm-agent-pin"><div class="lm-agent-grid"><div class="lm-copy"><span class="mg-badge"><span class="mg-pulse"></span> Agent-guided discovery</span><h1>Tell us what you want to build.</h1><p>Complete the form below to share your business, goals, and the type of Microgifter experience you want to create.</p><div class="lm-progress" style="--progress:8%"><span></span></div></div><article class="lm-agent-card"><h3>Start with the basics.</h3><p>Scroll through the questions, complete the fields that apply, and submit everything at the end.</p><div class="lm-actions"><a class="lm-btn lm-btn-primary" href="#learn-more-form">Start questionnaire</a><a class="lm-btn lm-btn-secondary" href="/signup.php">Create account</a></div></article></div></div></section>
  <form id="learn-more-form" data-learn-more-form>
    <input type="hidden" name="source_page" value="learn-more"><input type="hidden" name="source_url"><input type="hidden" name="timezone_label"><input type="hidden" name="utm_source"><input type="hidden" name="utm_medium"><input type="hidden" name="utm_campaign"><input type="hidden" name="utm_term"><input type="hidden" name="utm_content">
    <?php
    $steps = [
      ['name','What should the agent call you?','Your name','text','Your name',true],
      ['email','Where should we send the follow-up?','Email address','email','you@example.com',true],
      ['phone','Would a phone follow-up help?','Phone number','tel','Optional',false],
      ['zip_code','Where are you building this?','ZIP / region','text','85001',false],
      ['business_name','What business, team, or project is this for?','Business / organization','text','Business or team name',false],
      ['website_url','Does the project already have a website?','Website','url','https://...',false],
      ['category','What category best describes the opportunity?','Category','text','Coffee, wellness, workplace, restaurants...',false],
    ];
    foreach ($steps as $i => $step): $n=$i+1; ?>
    <section class="lm-question" data-lm-stage="question" data-step="<?= $n ?>"><div class="lm-agent-pin"><div class="lm-agent-grid"><div class="lm-copy"><span class="mg-badge">Question <?= $n ?> of 9</span><h2><?= htmlspecialchars($step[1]) ?></h2><p>Answer this step, then continue. You can go back without losing earlier answers.</p><div class="lm-progress" style="--progress:<?= (int)(($n+1)/11*100) ?>%"><span></span></div></div><article class="lm-agent-card"><h3><?= htmlspecialchars($step[2]) ?></h3><div class="lm-field"><label for="lm-<?= $step[0] ?>"><?= htmlspecialchars($step[2]) ?></label><input id="lm-<?= $step[0] ?>" name="<?= $step[0] ?>" type="<?= $step[3] ?>" placeholder="<?= htmlspecialchars($step[4]) ?>" <?= $step[5]?'required':'' ?>></div><div class="lm-actions"><button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button><button class="lm-btn lm-btn-primary" type="button" data-lm-next>Next</button><?= !$step[5]?'<button class="lm-btn lm-btn-link" type="button" data-lm-skip>Skip</button>':'' ?></div></article></div></div></section>
    <?php endforeach; ?>
    <section class="lm-question" data-lm-stage="question" data-step="8"><div class="lm-agent-pin"><div class="lm-agent-grid"><div class="lm-copy"><span class="mg-badge">Question 8 of 9</span><h2>Which path fits you best?</h2><p>This helps route your request to the right Microgifter workflow.</p><div class="lm-progress" style="--progress:82%"><span></span></div></div><article class="lm-agent-card"><h3>Interest type</h3><div class="lm-field"><label for="lm-lead-type">Choose one</label><select id="lm-lead-type" name="lead_type"><option value="merchant">I want to sell gifts/rewards</option><option value="workplace">I want workplace rewards</option><option value="creator">I want creator access</option><option value="partner">I want to partner</option><option value="general">I want to learn more</option></select></div><div class="lm-actions"><button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button><button class="lm-btn lm-btn-primary" type="button" data-lm-next>Next</button></div></article></div></div></section>
    <section class="lm-question" data-lm-stage="question" data-step="9"><div class="lm-agent-pin"><div class="lm-agent-grid"><div class="lm-copy"><span class="mg-badge">Question 9 of 9</span><h2>What are you trying to launch, sell, reward, or automate?</h2><p>Give the agent enough context to make the first follow-up useful.</p><div class="lm-progress" style="--progress:91%"><span></span></div></div><article class="lm-agent-card"><h3>Your goal</h3><div class="lm-field"><label for="lm-message">Message</label><textarea id="lm-message" name="message" placeholder="Describe the outcome you want..."></textarea></div><div class="lm-actions"><button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button><button class="lm-btn lm-btn-primary" type="button" data-lm-next>Review answers</button></div></article></div></div></section>
    <section class="lm-question" data-lm-stage="review"><div class="lm-agent-pin"><div class="lm-agent-grid"><div class="lm-copy"><span class="mg-badge">Final review</span><h2>Review and submit your request.</h2><p>Check your answers, make any edits above, and submit when everything looks right.</p><div class="lm-progress" style="--progress:100%"><span></span></div></div><article class="lm-agent-card"><h3>Your request</h3><div class="lm-review" data-lm-review></div><div class="lm-form-status" data-learn-more-status></div><div class="lm-complete" data-lm-complete>Thanks — your request was received. The agent has completed this presentation.</div><div class="lm-actions"><button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button><button class="lm-btn lm-btn-primary" type="submit">Submit request</button></div></article></div></div></section>
  </form>
</div>


<script>
(() => {
  const root = document.querySelector('[data-learn-more-agent]');
  const form = document.querySelector('[data-learn-more-form]');

  if (!root || !form) {
    return;
  }

  const stages = [
    root.querySelector('[data-lm-stage="intro"]'),
    ...Array.from(form.querySelectorAll('[data-lm-stage="question"]')),
    form.querySelector('[data-lm-stage="review"]')
  ].filter(Boolean);

  const review = form.querySelector('[data-lm-review]');
  const status = form.querySelector('[data-learn-more-status]');
  const complete = form.querySelector('[data-lm-complete]');

  const fieldLabels = {
    name: 'Name',
    email: 'Email',
    phone: 'Phone',
    zip_code: 'ZIP / region',
    business_name: 'Business / organization',
    website_url: 'Website',
    category: 'Category',
    lead_type: 'Interest type',
    message: 'Goal'
  };

  const scrollToStage = (index) => {
    const target = stages[Math.max(0, Math.min(index, stages.length - 1))];

    if (!target) {
      return;
    }

    target.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  };

  const getStageIndex = (element) => {
    const stage = element.closest('[data-lm-stage]');
    return stages.indexOf(stage);
  };

  const validateCurrentStage = (button) => {
    const stage = button.closest('[data-lm-stage]');

    if (!stage) {
      return true;
    }

    const requiredFields = Array.from(stage.querySelectorAll('[required]'));

    for (const field of requiredFields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }

    return true;
  };

  const populateReview = () => {
    if (!review) {
      return;
    }

    const data = new FormData(form);
    review.innerHTML = '';

    Object.entries(fieldLabels).forEach(([name, label]) => {
      const value = String(data.get(name) || '').trim();

      const row = document.createElement('div');
      row.className = 'lm-review-row';

      const labelNode = document.createElement('span');
      labelNode.textContent = label;

      const valueNode = document.createElement('strong');
      valueNode.textContent = value || 'Not provided';

      row.append(labelNode, valueNode);
      review.appendChild(row);
    });
  };

  root.addEventListener('click', (event) => {
    const nextButton = event.target.closest('[data-lm-next]');
    const backButton = event.target.closest('[data-lm-back]');
    const skipButton = event.target.closest('[data-lm-skip]');

    if (nextButton) {
      event.preventDefault();

      if (!validateCurrentStage(nextButton)) {
        return;
      }

      const index = getStageIndex(nextButton);

      if (index === stages.length - 2) {
        populateReview();
      }

      scrollToStage(index + 1);
      return;
    }

    if (backButton) {
      event.preventDefault();
      scrollToStage(getStageIndex(backButton) - 1);
      return;
    }

    if (skipButton) {
      event.preventDefault();

      const stage = skipButton.closest('[data-lm-stage]');
      const optionalFields = stage ? stage.querySelectorAll('input:not([required]), select:not([required]), textarea:not([required])') : [];

      optionalFields.forEach((field) => {
        field.value = '';
      });

      scrollToStage(getStageIndex(skipButton) + 1);
    }
  });

  const introLink = root.querySelector('a[href="#learn-more-form"]');

  if (introLink) {
    introLink.addEventListener('click', (event) => {
      event.preventDefault();
      scrollToStage(1);
    });
  }

  form.addEventListener('input', () => {
    if (review) {
      populateReview();
    }
  });

  form.addEventListener('submit', () => {
    populateReview();
  });

  populateReview();
})();
</script>

<footer class="mg-home-footer">
  <div class="mg-home-footer-inner">
    <div class="mg-home-footer-grid">
      <div class="mg-home-footer-brand">
        <a class="mg-home-footer-logo" href="/">
          <span class="mg-home-footer-mark">M</span>
          <span>Microgifter</span>
        </a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div class="mg-home-socials" aria-label="Social links">
           <a href="https://instagram.com/microgifter" aria-label="Instagram">ig</a>
          <a href="https://linkedin.com/microgifter" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="mg-home-footer-column">
        <h3>Product</h3>
        <nav aria-label="Product links">
                    <a href="/retail.php">Retail Subscriptions</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/discover.php">Discover</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="mg-home-footer-bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="mg-home-footer-bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>
