from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    text = target.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one exact match, found {count}")
    target.write_text(text.replace(old, new, 1), encoding="utf-8")


def replace_regex_once(path: str, pattern: str, replacement: str) -> None:
    target = ROOT / path
    text = target.read_text(encoding="utf-8")
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f"{path}: expected one regex match, found {count}")
    target.write_text(updated, encoding="utf-8")


# Create Center: use the same canonical campaign registry as Campaign Center.
replace_once(
    "includes/header-templates/create-menu.php",
    "declare(strict_types=1);\n\n",
    "declare(strict_types=1);\n\nrequire_once dirname(__DIR__) . '/campaign-types.php';\nrequire_once dirname(__DIR__) . '/public-donations-feature.php';\n\n",
)
replace_once(
    "includes/header-templates/create-menu.php",
    "$can_create_post = (bool) ($can_create_post ?? mg_is_authenticated());\n\n$mgCreateTools = [];",
    "$can_create_post = (bool) ($can_create_post ?? mg_is_authenticated());\n\n$mgCreateCampaignTypes = [];\nif ($can_create_campaigns) {\n    $mgCreateMenuUser = mg_current_user() ?? [];\n    $mgCreateCampaignTypes = mg_public_donations_campaign_type_options(\n        (int) ($mgCreateMenuUser['id'] ?? 0),\n        $mgCreateMenuUser,\n        true\n    );\n}\n\n$mgCreateTools = [];",
)
replace_once(
    "includes/header-templates/create-menu.php",
    '<div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Campaign</span><h3>Create a campaign</h3><p>Launch a signup, QR drop, contest, referral, birthday, or agent-offer campaign.</p></div><a href="/merchant-campaigns.php#campaign-create">Open campaign studio</a></div>',
    '<div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Campaign</span><h3>Create a campaign</h3><p>Choose from the complete Campaign Center registry and save a campaign draft without leaving this modal.</p></div><a href="/merchant-campaigns.php#campaign-create">Open campaign studio</a></div>',
)
replace_once(
    "includes/header-templates/create-menu.php",
    '<label>Campaign type<select name="campaign_type"><option value="newsletter_signup">Newsletter signup</option><option value="qr_reward_drop">QR reward drop</option><option value="contest_giveaway">Contest / giveaway</option><option value="referral_reward">Referral reward</option><option value="birthday_vip">Birthday / VIP</option><option value="agent_offer">Agent offer</option></select></label>',
    '''<label>Campaign type<select name="campaign_type" data-create-campaign-types><?php foreach ($mgCreateCampaignTypes as $mgCreateCampaignType): ?><option value="<?= mg_e((string) ($mgCreateCampaignType['key'] ?? '')) ?>"><?= mg_e((string) ($mgCreateCampaignType['label'] ?? 'Campaign')) ?></option><?php endforeach; ?></select></label>''',
)

# Create Center runtime: refresh campaign types from the same API payload used by Campaign Center.
new_load_campaign = r'''  async function loadCampaignContext(force) {
    if (loaded.campaign && !force) return;
    var form = formFor('campaign');
    if (!form) return;
    var rewardSelect = form.querySelector('[data-create-campaign-rewards]');
    var typeSelect = form.querySelector('[data-create-campaign-types]');
    try {
      var responses = await Promise.all([
        MG.get('/api/merchant/reward-templates.php?status=active'),
        MG.get('/api/merchant/campaigns.php?status=all')
      ]);
      var templates = (unwrap(responses[0]) || {}).templates || [];
      var campaignTypes = (unwrap(responses[1]) || {}).campaign_types || [];
      if (rewardSelect) {
        rewardSelect.innerHTML = '<option value="">No reward attached</option>';
        templates.forEach(function (template) {
          var option = document.createElement('option');
          option.value = String(template.id || '');
          option.textContent = String(template.title || 'Reward template');
          rewardSelect.appendChild(option);
        });
      }
      if (typeSelect && campaignTypes.length) {
        var selectedType = String(typeSelect.value || '');
        typeSelect.replaceChildren();
        campaignTypes.forEach(function (campaignType) {
          var option = document.createElement('option');
          option.value = String(campaignType.key || '');
          option.textContent = String(campaignType.label || 'Campaign');
          if (campaignType.internal_only) option.dataset.internalOnly = 'true';
          typeSelect.appendChild(option);
        });
        if (selectedType && Array.from(typeSelect.options).some(function (option) { return option.value === selectedType; })) {
          typeSelect.value = selectedType;
        }
      }
      loaded.campaign = true;
      var readiness = campaignTypes.length + ' campaign types · ' + templates.length + ' active rewards';
      setStatus('campaign', 'Campaign form ready · ' + readiness + '.', campaignTypes.length ? 'ready' : 'warning');
    } catch (error) {
      setStatus('campaign', error.message || 'Unable to load campaign types and reward templates.', 'error');
    }
  }

  async function submitCampaign(form) {'''
replace_regex_once(
    "assets/js/create-center-inline.js",
    r"  async function loadCampaignContext\(force\) \{.*?\n  async function submitCampaign\(form\) \{",
    new_load_campaign,
)
replace_once(
    "assets/js/create-center-inline.js",
    "    if (String(data.status || '') === 'active' && !String(data.reward_template_id || '').trim()) {\n      return setStatus('campaign', 'Choose an active reward template before activating the campaign.', 'error');\n    }\n",
    "",
)

# Inbox: bypass stale authenticated GET responses and normalize contract items synchronously.
new_load_folder = r'''  function normalizeFolderItems(items) {
    var values = Array.isArray(items) ? items : [];
    var contract = window.MicrogifterActionCenterContract;
    if (contract && typeof contract.views === 'function') values = contract.views(values);
    return values.map(normalize);
  }

  async function fetchFolderPayload(folder, attempt) {
    var freshness = Date.now() + '-' + String(attempt || 0);
    var response = await Microgifter.get('/api/account/action-center.php?folder=' + encodeURIComponent(folder) + '&limit=100&_mg_fresh=' + encodeURIComponent(freshness));
    return response.data || response;
  }

  function payloadFolderTotal(data, folder) {
    var source = data && data.counts && data.counts[folder];
    return Number(source && typeof source === 'object' ? source.total : source || 0);
  }

  async function loadFolder(folder, force) {
    if (state.loading) return;
    state.folder = folder;
    state.loading = true;
    updateFolderText();
    list.innerHTML = '<div class="mg-gift-empty-list"><strong>Loading gifts…</strong></div>';
    var loadError = '';
    try {
      if (window.Microgifter && (force || !state.folders[folder].length)) {
        var data = await fetchFolderPayload(folder, 0);
        var items = normalizeFolderItems(data.items || []);
        var total = payloadFolderTotal(data, folder);
        if (total > 0 && !items.length) {
          data = await fetchFolderPayload(folder, 1);
          items = normalizeFolderItems(data.items || []);
          total = payloadFolderTotal(data, folder);
        }
        if (total > 0 && !items.length) {
          throw new Error('The ' + folder + ' count reports ' + total + ' gift' + (total === 1 ? '' : 's') + ', but the gift rows could not be loaded.');
        }
        state.folders[folder] = items;
        setCounts(data.counts || state.counts);
      }
    } catch (error) {
      console.error(error);
      loadError = error && error.message ? error.message : 'Unable to load gifts.';
    }
    if (demoEnabled && !state.folders[folder].length && !loadError) {
      state.folders[folder] = (demos[folder] || []).map(normalize);
      state.counts[folder] = state.folders[folder].length;
      state.unread[folder] = folder === 'inbox' ? state.folders[folder].length : 0;
      setCounts({
        inbox: { total: state.counts.inbox, unread: state.unread.inbox },
        sent: { total: state.counts.sent, unread: state.unread.sent },
        claimed: { total: state.counts.claimed, unread: state.unread.claimed }
      });
    }
    state.loading = false;
    state.selected = null;
    if (loadError && !state.folders[folder].length) {
      updateFolderText();
      list.innerHTML = '<div class="mg-gift-empty-list is-error"><strong>Unable to load ' + esc(folder) + ' gifts</strong><p>' + esc(loadError) + '</p><button type="button" class="mg-btn mg-btn-soft" data-gift-refresh>Try again</button></div>';
      var retry = list.querySelector('[data-gift-refresh]');
      if (retry) retry.addEventListener('click', function () { state.folders[folder] = []; loadFolder(folder, true); });
      return;
    }
    renderList();
  }

  document.querySelectorAll'''
replace_regex_once(
    "assets/js/gift-action-center.js",
    r"  async function loadFolder\(folder, force\) \{.*?\n  document\.querySelectorAll",
    new_load_folder,
)

print("Campaign system source patches applied.")
