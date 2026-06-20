<?php
declare(strict_types=1);
?>
<header class="mg-site-header mg-unified-header" data-mg-universal-header data-header-variant="logged-in">
  <div class="mg-header-left">
    <button class="mg-mobile-menu-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
    <a class="mg-brand mg-header-mobile-brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
    <nav class="mg-site-nav" aria-label="Primary navigation">
      <?php if ($header_mode === 'crm'): ?>
        <div class="mg-header-crm-tools"><input data-crm-search placeholder="Search leads, email, business, ZIP..." aria-label="Search CRM leads"><select data-crm-status-filter aria-label="Filter CRM leads by status"><option value="all">All statuses</option><option value="new">New</option><option value="assigned">Assigned</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="nurture">Nurture</option><option value="converted">Converted</option><option value="closed_lost">Closed lost</option><option value="spam">Spam</option></select></div>
      <?php elseif ($header_mode === 'agent'): ?>
        <div class="mg-header-agent-tools">
          <div class="mg-header-agent-tabs" data-agent-tabs aria-label="Workspace tabs">
            <?php foreach ([['agent','Agent','/agent.php'],['inbox','Inbox','/inbox.php'],['sent','Sent','/sent.php'],['claimed','Claimed','/claimed.php']] as $tab): ?>
              <?php $defaultGiftCount = $tab[0] === 'inbox' ? 1 : 0; $defaultGiftCount = ['inbox' => 3, 'sent' => 2, 'claimed' => 2][$tab[0]] ?? $defaultGiftCount; ?>
              <span class="mg-agent-tab-item mg-agent-tab-item-system" data-system-tab="<?= $tab[0] ?>"><a class="<?= $agent_tab === $tab[0] ? 'is-active' : '' ?>" href="<?= $tab[2] ?>"><span><?= $tab[1] ?></span><?php if (in_array($tab[0], ['inbox','sent','claimed'], true)): ?><b class="mg-agent-tab-badge<?= $defaultGiftCount > 0 ? ' has-unread' : '' ?>" data-gift-nav-count="<?= $tab[0] ?>" data-gift-nav-unread="<?= $tab[0] ?>"><?= $defaultGiftCount ?></b><?php endif; ?></a></span>
            <?php endforeach; ?>
          </div>
          <div class="mg-header-agent-search"><input type="search" placeholder="Search agents, products, gifts, claims…" aria-label="Search agent workspace" data-agent-global-search></div>
          <button class="mg-header-product-create" type="button" data-product-header-create data-create-menu-trigger aria-haspopup="dialog" aria-controls="mg-create-menu" aria-expanded="false" aria-label="Create something new">+</button>
        </div>
      <?php elseif ($header_mode === 'account'): ?>
        <div class="mg-header-agent-tools mg-header-account-tools"><div class="mg-header-agent-search"><input type="search" placeholder="Search account, activity, messages, settings…" aria-label="Search account workspace"></div></div>
      <?php elseif ($header_mode === 'builder'): ?>
        <div class="mg-builder-header-toggle" aria-label="Preview size">
          <div class="mg-builder-device-toggle">
            <button class="is-active" type="button" data-device="desktop" aria-label="Desktop preview">▣</button>
            <button type="button" data-device="mobile" aria-label="Mobile preview">▯</button>
          </div>
        </div>
      <?php endif; ?>
    </nav>
  </div>
  <?php require dirname(__DIR__) . '/header-templates/logged-in.php'; ?>
</header>

<?php if ($header_mode === 'agent'): ?>
<div class="mg-create-menu" id="mg-create-menu" data-create-menu hidden aria-hidden="true">
  <button class="mg-create-menu-backdrop" type="button" data-create-menu-close aria-label="Close create menu"></button>
  <section class="mg-create-menu-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-create-menu-title" tabindex="-1">
    <header class="mg-create-menu-head">
      <div><span>Create</span><h2 id="mg-create-menu-title">What do you want to add?</h2><p>Choose a workspace to start creating.</p></div>
      <button class="mg-create-menu-close" type="button" data-create-menu-close aria-label="Close create menu">×</button>
    </header>
    <div class="mg-create-menu-grid">
      <a href="/build.php" data-create-menu-option="microgift"><span class="mg-create-menu-icon" aria-hidden="true">M</span><strong>Microgift</strong><small>Create a prepaid local gift or offer.</small></a>
      <a href="/feed.php" data-create-menu-option="post"><span class="mg-create-menu-icon" aria-hidden="true">P</span><strong>Post</strong><small>Publish an update to your public feed.</small></a>
      <a href="/account-subscriptions.php" data-create-menu-option="subscription"><span class="mg-create-menu-icon" aria-hidden="true">S</span><strong>Subscription</strong><small>Create or manage a recurring membership.</small></a>
      <a href="/merchant-storefront.php" data-create-menu-option="storefront"><span class="mg-create-menu-icon" aria-hidden="true">F</span><strong>Storefront</strong><small>Configure your public merchant storefront.</small></a>
      <a href="/agent.php" data-create-menu-option="agent"><span class="mg-create-menu-icon" aria-hidden="true">A</span><strong>Agent</strong><small>Create or open an automated gifting agent.</small></a>
    </div>
  </section>
</div>
<style>
.mg-header-product-create{appearance:none;cursor:pointer;font:inherit}.mg-create-menu[hidden]{display:none!important}.mg-create-menu{position:fixed;inset:0;z-index:1400;display:grid;place-items:center;padding:24px}.mg-create-menu-backdrop{position:absolute;inset:0;width:100%;height:100%;border:0;background:rgba(7,18,37,.54);backdrop-filter:blur(8px)}.mg-create-menu-dialog{position:relative;z-index:1;width:min(720px,100%);max-height:calc(100vh - 48px);overflow:auto;padding:26px;border:1px solid #cbd5e1;border-radius:26px;background:#fff;box-shadow:0 32px 90px rgba(15,23,42,.3);outline:none}.mg-create-menu-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}.mg-create-menu-head span{display:block;margin-bottom:7px;color:#2563eb;font-size:12px;font-weight:950;letter-spacing:.1em;text-transform:uppercase}.mg-create-menu-head h2{margin:0;color:#071225;font-size:30px;line-height:1.05;letter-spacing:-.045em}.mg-create-menu-head p{margin:9px 0 0;color:#64748b;font-size:14px;font-weight:650}.mg-create-menu-close{flex:0 0 auto;width:40px;height:40px;display:grid;place-items:center;border:1px solid #dbe5f1;border-radius:12px;background:#f8fafc;color:#334155;font-size:25px;line-height:1;cursor:pointer}.mg-create-menu-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mg-create-menu-grid a{min-height:132px;display:grid;grid-template-columns:48px 1fr;grid-template-rows:auto 1fr;column-gap:14px;padding:18px;border:1px solid #dbe5f1;border-radius:18px;background:linear-gradient(180deg,#fff,#f8fafc);color:#0f172a;text-decoration:none;transition:.18s ease}.mg-create-menu-grid a:hover,.mg-create-menu-grid a:focus-visible{border-color:#93b4ee;box-shadow:0 16px 34px rgba(37,99,235,.12);transform:translateY(-2px);outline:none}.mg-create-menu-icon{grid-row:1/3;width:48px;height:48px;display:grid;place-items:center;border-radius:14px;background:#eef4ff;color:#1d4ed8;font-weight:950}.mg-create-menu-grid strong{align-self:end;font-size:16px}.mg-create-menu-grid small{margin-top:5px;color:#64748b;font-size:12px;line-height:1.45;font-weight:650}body.mg-create-menu-open{overflow:hidden}@media(max-width:640px){.mg-create-menu{align-items:end;padding:12px}.mg-create-menu-dialog{width:100%;max-height:calc(100svh - 24px);padding:22px 18px;border-radius:24px}.mg-create-menu-grid{grid-template-columns:1fr}.mg-create-menu-grid a{min-height:104px}}
</style>
<script>
(function(){var t=document.querySelector('[data-create-menu-trigger]'),m=document.querySelector('[data-create-menu]');if(!t||!m)return;var d=m.querySelector('.mg-create-menu-dialog'),last=null;function open(){last=document.activeElement;m.hidden=false;m.setAttribute('aria-hidden','false');t.setAttribute('aria-expanded','true');document.body.classList.add('mg-create-menu-open');requestAnimationFrame(function(){(m.querySelector('[data-create-menu-option]')||d).focus()})}function close(){m.hidden=true;m.setAttribute('aria-hidden','true');t.setAttribute('aria-expanded','false');document.body.classList.remove('mg-create-menu-open');if(last&&last.focus)last.focus()}t.addEventListener('click',function(){m.hidden?open():close()});m.querySelectorAll('[data-create-menu-close]').forEach(function(n){n.addEventListener('click',close)});document.addEventListener('keydown',function(e){if(!m.hidden&&e.key==='Escape'){e.preventDefault();close()}})})();
</script>
<?php endif; ?>
