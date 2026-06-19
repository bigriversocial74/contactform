<?php
declare(strict_types=1);

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
    'assets' => ['universal-header', 'agent-presentation', 'learn-more-questionnaire'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Home', 'href' => '/index.php'],
            ['label' => 'Create Account', 'href' => '/signup.php'],
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
  --lm-teal:#20bfd2;
}
.lm-agent-page{
  overflow:visible;
  color:var(--lm-dark);
  background:
    radial-gradient(circle at 18% 10%,rgba(237,233,254,.64),transparent 28%),
    radial-gradient(circle at 84% 18%,rgba(220,252,231,.42),transparent 26%),
    linear-gradient(180deg,#fff,#f8fafc 58%,#eef2f7);
}
.lm-agent-intro,
.lm-question{
  position:relative;
  min-height:260vh;
  border-bottom:1px solid var(--lm-border);
  background:transparent;
  scroll-margin-top:72px;
}
.lm-agent-intro::before,
.lm-question::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.55;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}
.lm-agent-pin{
  position:sticky;
  top:72px;
  min-height:calc(100svh - 72px);
  display:grid;
  align-items:center;
  overflow:hidden;
  padding:clamp(56px,8vh,96px) 0;
}
.lm-agent-grid{
  position:relative;
  z-index:2;
  width:min(1180px,92%);
  margin:auto;
  display:grid;
  grid-template-columns:.86fr 1.14fr;
  gap:54px;
  align-items:start;
}
.lm-copy{padding-top:18px}
.lm-copy .mg-badge{margin-bottom:18px}
.lm-copy h1,
.lm-copy h2{
  margin:0;
  font-size:clamp(38px,5.2vw,68px);
  line-height:.96;
  letter-spacing:-.07em;
}
.lm-copy p{
  max-width:620px;
  margin:20px 0 0;
  color:var(--lm-muted);
  font-size:18px;
  line-height:1.58;
}
.lm-progress{
  width:min(360px,100%);
  height:8px;
  margin-top:20px;
  overflow:hidden;
  border-radius:999px;
  background:rgba(15,23,42,.09);
}
.lm-progress span{
  display:block;
  width:var(--progress,0%);
  height:100%;
  border-radius:inherit;
  background:linear-gradient(90deg,var(--lm-purple),var(--lm-teal));
}
.lm-agent-card{
  width:100%;
  padding:34px;
  border:1px solid var(--lm-border);
  border-radius:28px;
  background:rgba(255,255,255,.96);
  box-shadow:0 26px 72px rgba(15,23,42,.11);
}
.lm-agent-card h3{
  margin:0;
  font-size:30px;
  line-height:1;
  letter-spacing:-.05em;
}
.lm-agent-card p{margin:12px 0 0;color:var(--lm-muted);line-height:1.5}
.lm-field{display:grid;gap:10px;margin-top:22px}
.lm-field label{
  color:#475569;
  font-size:12px;
  font-weight:950;
  letter-spacing:.08em;
  text-transform:uppercase;
}
.lm-field input,
.lm-field select,
.lm-field textarea{
  width:100%;
  min-height:58px;
  padding:14px 16px;
  border:1px solid #cbd5e1;
  border-radius:15px;
  outline:none;
  background:#fff;
  color:#0f172a;
  font:inherit;
}
.lm-field textarea{min-height:150px;resize:vertical}
.lm-field input:focus,
.lm-field select:focus,
.lm-field textarea:focus{
  border-color:var(--lm-purple);
  box-shadow:0 0 0 4px rgba(124,58,237,.1);
}
.lm-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}
.lm-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:48px;
  padding:0 19px;
  border-radius:999px;
  font-weight:950;
  text-decoration:none;
  cursor:pointer;
}
.lm-btn-primary{border:0;background:var(--lm-dark);color:#fff}
.lm-btn-secondary{border:1px solid #cbd5e1;background:#fff;color:var(--lm-dark)}
.lm-btn-link{border:0;background:transparent;color:#64748b}
.lm-review{display:grid;gap:10px;margin-top:22px}
.lm-review-row{
  display:grid;
  grid-template-columns:150px 1fr;
  gap:15px;
  padding:14px 0;
  border-bottom:1px solid #e2e8f0;
}
.lm-review-row span{color:#64748b;font-size:12px;font-weight:900;text-transform:uppercase}
.lm-review-row strong{font-size:14px;word-break:break-word}
.lm-form-status{margin-top:16px;font-weight:850}
.lm-complete{
  display:none;
  margin-top:18px;
  padding:18px;
  border-radius:18px;
  background:#ecfdf5;
  color:#166534;
  font-weight:850;
}
.lm-complete.is-visible{display:block}
@media(max-width:900px){
  .lm-agent-intro,
  .lm-question{min-height:auto}
  .lm-agent-pin{position:relative;top:auto;min-height:auto;padding:88px 0}
  .lm-agent-grid{grid-template-columns:1fr;width:min(680px,92%)}
  .lm-copy{text-align:center;padding-top:0}
  .lm-copy p,.lm-progress{margin-left:auto;margin-right:auto}
}
@media(max-width:680px){
  .lm-agent-pin{padding:64px 0}
  .lm-copy{text-align:left}
  .lm-agent-card{padding:22px;border-radius:22px}
  .lm-copy h1,.lm-copy h2{font-size:clamp(34px,12vw,54px)}
  .lm-actions{display:grid}
  .lm-actions>*{width:100%}
  .lm-review-row{grid-template-columns:1fr}
}
</style>
<div class="lm-agent-page" data-learn-more-agent>
  <section class="lm-agent-intro is-active" data-lm-stage="intro">
    <div class="lm-agent-pin">
      <div class="lm-agent-grid">
        <div class="lm-copy">
          <span class="mg-badge"><span class="mg-pulse"></span> Agent-guided discovery</span>
          <h1>Tell us what you want to build.</h1>
          <p>Share your business, goals, and the type of Microgifter experience you want to create.</p>
          <div class="lm-progress" style="--progress:8%"><span></span></div>
        </div>
        <article class="lm-agent-card">
          <h3>Start with the basics.</h3>
          <p>Move through the questions, skip anything optional, and review everything before submitting.</p>
          <div class="lm-actions">
            <button class="lm-btn lm-btn-primary" type="button" data-lm-next>Start questionnaire</button>
            <a class="lm-btn lm-btn-secondary" href="/signup.php">Create account</a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <form id="learn-more-form" data-learn-more-form>
    <input type="hidden" name="source_page" value="learn-more">
    <input type="hidden" name="source_url">
    <input type="hidden" name="timezone_label">
    <input type="hidden" name="utm_source">
    <input type="hidden" name="utm_medium">
    <input type="hidden" name="utm_campaign">
    <input type="hidden" name="utm_term">
    <input type="hidden" name="utm_content">
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
    foreach ($steps as $i => $step):
        $n = $i + 1;
        $progress = (int) (($n + 1) / 11 * 100);
    ?>
      <section class="lm-question" data-lm-stage="question" data-step="<?= $n ?>">
        <div class="lm-agent-pin">
          <div class="lm-agent-grid">
            <div class="lm-copy">
              <span class="mg-badge">Question <?= $n ?> of 9</span>
              <h2><?= htmlspecialchars($step[1], ENT_QUOTES, 'UTF-8') ?></h2>
              <p>Answer this step, then continue. You can go back without losing earlier answers.</p>
              <div class="lm-progress" style="--progress:<?= $progress ?>%"><span></span></div>
            </div>
            <article class="lm-agent-card">
              <h3><?= htmlspecialchars($step[2], ENT_QUOTES, 'UTF-8') ?></h3>
              <div class="lm-field">
                <label for="lm-<?= $step[0] ?>"><?= htmlspecialchars($step[2], ENT_QUOTES, 'UTF-8') ?></label>
                <input id="lm-<?= $step[0] ?>" name="<?= $step[0] ?>" type="<?= $step[3] ?>" placeholder="<?= htmlspecialchars($step[4], ENT_QUOTES, 'UTF-8') ?>" <?= $step[5] ? 'required' : '' ?>>
              </div>
              <div class="lm-actions">
                <button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button>
                <button class="lm-btn lm-btn-primary" type="button" data-lm-next>Next</button>
                <?php if (!$step[5]): ?><button class="lm-btn lm-btn-link" type="button" data-lm-skip>Skip</button><?php endif; ?>
              </div>
            </article>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <section class="lm-question" data-lm-stage="question" data-step="8">
      <div class="lm-agent-pin">
        <div class="lm-agent-grid">
          <div class="lm-copy">
            <span class="mg-badge">Question 8 of 9</span>
            <h2>Which path fits you best?</h2>
            <p>This helps route your request to the right Microgifter workflow.</p>
            <div class="lm-progress" style="--progress:82%"><span></span></div>
          </div>
          <article class="lm-agent-card">
            <h3>Interest type</h3>
            <div class="lm-field">
              <label for="lm-lead-type">Choose one</label>
              <select id="lm-lead-type" name="lead_type">
                <option value="merchant">I want to sell gifts or rewards</option>
                <option value="workplace">I want workplace rewards</option>
                <option value="creator">I want creator access</option>
                <option value="partner">I want to partner</option>
                <option value="general">I want to learn more</option>
              </select>
            </div>
            <div class="lm-actions">
              <button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button>
              <button class="lm-btn lm-btn-primary" type="button" data-lm-next>Next</button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="lm-question" data-lm-stage="question" data-step="9">
      <div class="lm-agent-pin">
        <div class="lm-agent-grid">
          <div class="lm-copy">
            <span class="mg-badge">Question 9 of 9</span>
            <h2>What are you trying to launch, sell, reward, or automate?</h2>
            <p>Give the agent enough context to make the first follow-up useful.</p>
            <div class="lm-progress" style="--progress:91%"><span></span></div>
          </div>
          <article class="lm-agent-card">
            <h3>Your goal</h3>
            <div class="lm-field">
              <label for="lm-message">Message</label>
              <textarea id="lm-message" name="message" placeholder="Describe the outcome you want..."></textarea>
            </div>
            <div class="lm-actions">
              <button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button>
              <button class="lm-btn lm-btn-primary" type="button" data-lm-next>Review answers</button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="lm-question" data-lm-stage="review">
      <div class="lm-agent-pin">
        <div class="lm-agent-grid">
          <div class="lm-copy">
            <span class="mg-badge">Final review</span>
            <h2>Review and submit your request.</h2>
            <p>Check your answers and submit when everything looks right.</p>
            <div class="lm-progress" style="--progress:100%"><span></span></div>
          </div>
          <article class="lm-agent-card">
            <h3>Your request</h3>
            <div class="lm-review" data-lm-review></div>
            <div class="lm-form-status" data-learn-more-status role="status" aria-live="polite"></div>
            <div class="lm-complete" data-lm-complete>Thanks — your request was received.</div>
            <div class="lm-actions">
              <button class="lm-btn lm-btn-secondary" type="button" data-lm-back>Back</button>
              <button class="lm-btn lm-btn-primary" type="submit">Submit request</button>
              <button class="lm-btn lm-btn-link" type="button" data-lm-replay>Start over</button>
            </div>
          </article>
        </div>
      </div>
    </section>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
