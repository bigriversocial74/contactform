window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function ctx() { return window.MicrogifterServerContext || {}; }
  function user() { return ctx().user || null; }
  function name() {
    var u = user();
    return (u && (u.display_name || u.full_name || u.email)) || 'Account';
  }
  function email() {
    var u = user();
    return (u && u.email) || 'Guest mode';
  }
  function initial() {
    return String(name() || 'M').trim().charAt(0).toUpperCase() || 'M';
  }
  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }
  function esc(value) {
    return String(value || '').replace(/[&<>'"]/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
    });
  }
  function bolt() {
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 10-13h-7l1-7Z" fill="currentColor"/></svg>';
  }
  function accountMenu() {
    var u = user();
    return '<div class="mg-account-menu" data-mg-auth-menu>' +
      '<button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false">' +
      '<span class="mg-avatar">' + esc(initial()) + '</span>' +
      '<span class="mg-account-copy"><strong>' + esc(name()) + '</strong><small>' + (u ? 'Member' : 'Guest') + '</small></span>' +
      '<span class="mg-account-caret">⌄</span></button>' +
      '<div class="mg-account-actions"><div class="mg-account-menu-head"><strong>' + esc(name()) + '</strong><small>' + esc(email()) + '</small></div>' +
      (u ? '<a class="mg-account-action" href="/account.php">Account dashboard</a><a class="mg-account-action" href="/build.php">Create / edit product</a><a class="mg-account-action" href="/agent.php">Open live agent</a><button class="mg-account-action mg-account-logout" type="button" data-auth-logout>Sign out</button>' : '<a class="mg-account-action" href="/signin.php">Sign in</a><a class="mg-account-action" href="/signup.php">Create account</a>') +
      '</div></div>';
  }
  function fullHeader() {
    return '<header class="mg-site-header" data-mg-universal-header><div class="mg-header-left"><a class="mg-brand" href="/index.php"><span class="mg-brand-mark">' + bolt() + '</span><span>Microgifter</span></a><nav class="mg-site-nav"><a href="/index.php">Home</a><a href="/build.php">Build</a><a href="/agent.php">Agent</a></nav></div><div class="mg-header-actions">' + accountMenu() + '</div></header>';
  }
  function fullFooter() {
    return '<footer class="mg-site-footer" data-mg-universal-footer><div class="mg-footer-inner"><div class="mg-footer-brand"><a class="mg-brand mg-footer-logo" href="/index.php"><span class="mg-brand-mark">' + bolt() + '</span><span>Microgifter</span></a><p>Pre-purchase gifts, local rewards, and agent-assisted gifting.</p></div><nav class="mg-footer-nav"><a href="/build.php">Build</a><a href="/agent.php">Agent</a><a href="/account.php">Account</a><a href="/signin.php">Sign in</a></nav></div></footer>';
  }
  function styles() {
    if (document.getElementById('mg-auth-state-styles')) return;
    var s = document.createElement('style');
    s.id = 'mg-auth-state-styles';
    s.textContent = ':root{--mg-header-h:76px;--mg-border:#e2e8f0;--mg-text:#0f172a;--mg-muted:#64748b;--mg-shadow:0 24px 70px rgba(15,23,42,.10);--mg-shadow-soft:0 14px 34px rgba(15,23,42,.06);--mg-danger:#dc2626}.mg-site-header{position:sticky;top:0;z-index:1000;min-height:var(--mg-header-h);width:100%;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:0 clamp(18px,3vw,32px);border-bottom:1px solid rgba(226,232,240,.9);background:rgba(255,255,255,.94);backdrop-filter:blur(16px)}.mg-header-left{display:flex;align-items:center;gap:28px;min-width:0}.mg-brand{display:inline-flex;align-items:center;gap:12px;font-weight:950;letter-spacing:-.045em;font-size:22px;white-space:nowrap;text-decoration:none;color:var(--mg-text)}.mg-brand-mark{width:42px;height:42px;display:grid;place-items:center;border-radius:14px;border:1px solid var(--mg-border);background:#fff;color:#050505;box-shadow:var(--mg-shadow-soft)}.mg-brand-mark svg{width:22px;height:22px}.mg-site-nav{display:flex;align-items:center;gap:22px;color:var(--mg-muted);font-weight:900;font-size:14px;letter-spacing:-.015em;white-space:nowrap}.mg-site-nav a{text-decoration:none;color:inherit}.mg-header-actions{display:flex;align-items:center;gap:10px;margin-left:auto}.mg-account-menu{position:relative;display:inline-flex;align-items:center;z-index:1001}.mg-account-trigger{height:44px;border:1px solid var(--mg-border);background:#fff;border-radius:999px;padding:0 12px 0 8px;display:inline-flex;align-items:center;gap:9px;font:inherit;font-weight:900;color:var(--mg-text);box-shadow:var(--mg-shadow-soft);cursor:pointer}.mg-avatar{width:28px;height:28px;border:2px solid var(--mg-text);border-radius:999px;display:grid;place-items:center;font-size:12px;font-weight:950;background:#fff;color:var(--mg-text)}.mg-account-copy{display:grid;line-height:1.05;text-align:left}.mg-account-copy strong{font-size:13px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mg-account-copy small{color:var(--mg-muted);font-weight:900;font-size:11px}.mg-account-actions{position:absolute;right:0;top:calc(100% + 10px);width:240px;display:none;padding:10px;border:1px solid var(--mg-border);border-radius:18px;background:#fff;box-shadow:var(--mg-shadow);z-index:150}.mg-account-menu.is-open .mg-account-actions,.mg-account-menu:hover .mg-account-actions,.mg-account-menu:focus-within .mg-account-actions{display:grid;gap:4px}.mg-account-menu-head{padding:8px 10px 10px;border-bottom:1px solid var(--mg-border);margin-bottom:4px;display:grid}.mg-account-menu-head small{font-size:11px;color:var(--mg-muted);overflow:hidden;text-overflow:ellipsis}.mg-account-action{width:100%;display:flex;align-items:center;min-height:40px;padding:0 10px;border:0;border-radius:12px;background:transparent;color:var(--mg-text);font:inherit;font-weight:900;text-align:left;text-decoration:none;cursor:pointer}.mg-account-action:hover{background:#f8fafc}.mg-account-logout{color:var(--mg-danger)}.mg-site-footer{padding:38px clamp(18px,3vw,32px);border-top:1px solid var(--mg-border);background:#fff;color:var(--mg-muted)}.mg-footer-inner{width:100%;display:flex;align-items:flex-start;justify-content:space-between;gap:24px}.mg-footer-brand{display:grid;gap:12px;max-width:520px}.mg-footer-brand p{margin:0}.mg-footer-logo{font-size:20px}.mg-footer-nav{display:flex;align-items:center;gap:18px;flex-wrap:wrap;font-size:14px;font-weight:900}.mg-footer-nav a{text-decoration:none;color:inherit}@media(max-width:860px){.mg-site-header{align-items:flex-start;padding:14px 18px}.mg-header-left{gap:14px;align-items:flex-start;flex-direction:column}.mg-site-nav{max-width:100%;overflow-x:auto}.mg-account-copy{display:none}.mg-footer-inner{flex-direction:column}}';
    document.head.appendChild(s);
  }
  function normalize() {
    var map = {'index.html':'/index.php','/index.html':'/index.php','build.html':'/build.php','/build.html':'/build.php','builder.html':'/build.php','/builder.html':'/build.php','agent.html':'/agent.php','/agent.html':'/agent.php','signin.html':'/signin.php','/signin.html':'/signin.php','signup.html':'/signup.php','/signup.html':'/signup.php'};
    document.querySelectorAll('a[href]').forEach(function (a) { var h = a.getAttribute('href'); if (map[h]) a.setAttribute('href', map[h]); });
    document.querySelectorAll('header.nav,.mg-mobile-header').forEach(function (h) { h.outerHTML = fullHeader(); });
    document.querySelectorAll('.mg-user-shell,[data-native-user-menu],.account-menu,.account-dropdown,.mg-header-account,[data-user-menu]').forEach(function (n) { n.outerHTML = accountMenu(); });
    document.querySelectorAll('footer.footer').forEach(function (f) { f.outerHTML = fullFooter(); });
    if (!user()) document.querySelectorAll('header a[href="/build.php"].btn,header a[href="/build.php"].mg-btn,.mg-top-actions>a[href="/build.php"],.nav-actions>a[href="/build.php"]').forEach(function (n) { if (!n.closest('.mg-site-nav')) n.remove(); });
  }
  function bindMenus() {
    document.addEventListener('click', function (event) {
      var t = event.target.closest('[data-mg-auth-trigger]');
      if (t) {
        var m = t.closest('[data-mg-auth-menu],.mg-account-menu');
        var open = !m.classList.contains('is-open');
        document.querySelectorAll('[data-mg-auth-menu].is-open,.mg-account-menu.is-open').forEach(function (x) { if (x !== m) x.classList.remove('is-open'); });
        m.classList.toggle('is-open', open);
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
      }
      if (!event.target.closest('[data-mg-auth-menu],.mg-account-menu')) document.querySelectorAll('[data-mg-auth-menu].is-open,.mg-account-menu.is-open').forEach(function (m) { m.classList.remove('is-open'); });
    });
  }
  function bindLogout() {
    document.addEventListener('click', async function (event) {
      var b = event.target.closest('[data-auth-logout]');
      if (!b) return;
      event.preventDefault();
      b.disabled = true;
      try {
        var r = await fetch('/api/auth/logout.php', {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':csrf()},body:JSON.stringify({csrf_token:csrf()})});
        var d = await r.json().catch(function(){return {};});
        window.location.href = (d.data && d.data.redirect) || '/index.php';
      } catch (e) { alert(e.message || 'Unable to sign out.'); b.disabled = false; }
    });
  }
  document.addEventListener('DOMContentLoaded', function () { styles(); normalize(); bindMenus(); bindLogout(); });
})();