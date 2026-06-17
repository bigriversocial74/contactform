document.addEventListener('DOMContentLoaded', function () {
  var grid = document.querySelector('[data-agent-category-grid]');
  var start = document.querySelector('[data-agent-category-start]');
  var workspace = document.querySelector('[data-agent-category-workspace]');
  var form = document.querySelector('[data-agent-dynamic-form]');
  var toolbarTitle = document.querySelector('[data-agent-toolbar-title]');
  var toolbarDescription = document.querySelector('[data-agent-toolbar-description]');
  var changeButton = document.querySelector('[data-change-category]');
  if (!grid || !start || !workspace || !form || !toolbarTitle || !toolbarDescription || !changeButton) return;

  if (!document.querySelector('link[data-agent-category-style]')) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/agent-categories.css';
    link.dataset.agentCategoryStyle = '';
    document.head.appendChild(link);
  }

  var saveTimer = null;
  var datasets = {
    family: { title: 'Family', description: 'Plan personal gifts for birthdays, holidays, care moments, meals, and recurring annual events.', fields: [['recipient_name','Recipient name','text','Who is receiving the gift?'],['relationship','Relationship','select',['Parent','Child','Sibling','Partner','Extended family']],['occasion','Occasion or reason','text','Birthday, holiday, care gift...'],['budget','Budget','number','25'],['location','Recipient location','text','City, ZIP, or neighborhood'],['delivery_date','Delivery date','date',''],['repeat','Repeat behavior','select',['One time','Annually','Monthly','Custom reminder']]] },
    friend: { title: 'Friend', description: 'Build celebration, thank-you, encouragement, milestone, or shared-experience gifts.', fields: [['recipient_name','Friend name','text','Recipient name'],['occasion','Reason for the gift','text','Thank you, celebration, encouragement...'],['gift_style','Gift style','select',['Local experience','Food and drink','Wellness','Entertainment','Open choice']],['budget','Budget','number','25'],['location','Location','text','City or ZIP'],['message','Personal message','textarea','Add a short message']] },
    coworker: { title: 'Co-Worker', description: 'Create recognition, onboarding, birthday, team appreciation, and workplace reward gifts.', fields: [['recipient_name','Employee or co-worker','text','Name'],['workplace','Company or team','text','Organization'],['occasion','Recognition reason','select',['Great work','Birthday','Anniversary','Onboarding','Team milestone','Other']],['budget','Reward budget','number','25'],['location','Work or home location','text','City or ZIP'],['manager_note','Manager note','textarea','Optional internal note']] },
    group: { title: 'Group Gifting', description: 'Coordinate contributors, a collection goal, deadlines, and delivery for one shared gift.', fields: [['recipient_name','Recipient or group','text','Who receives the gift?'],['organizer_name','Organizer','text','Lead organizer'],['goal','Contribution goal','number','250'],['deadline','Contribution deadline','date',''],['participant_limit','Participant limit','number','10'],['gift_goal','Gift plan','textarea','Describe the shared gift'],['visibility','Contributor visibility','select',['Show names and amounts','Show names only','Private contributions']]] },
    contest: { title: 'Contest', description: 'Configure contest prizes, eligibility, winner selection, claim rules, and fulfillment.', fields: [['contest_name','Contest name','text','Campaign or contest title'],['sponsor','Sponsor','text','Business or organization'],['prize_count','Number of prizes','number','3'],['prize_value','Value per prize','number','50'],['eligibility','Eligibility rules','textarea','Who can enter?'],['selection_method','Winner selection','select',['Random drawing','Judged selection','Top performance','Manual selection']],['claim_deadline','Claim deadline','date',''],['location','Eligible region','text','City, ZIP, state, or online']] },
    community: { title: 'Community Prizes', description: 'Create neighborhood, merchant-supported, or public campaign rewards for a local audience.', fields: [['campaign_name','Campaign name','text','Community campaign'],['community','Community or region','text','Neighborhood, city, ZIP'],['merchant_partners','Merchant partners','textarea','List participating businesses'],['prize_pool','Prize pool','number','500'],['recipient_count','Expected recipients','number','10'],['distribution','Distribution method','select',['Random draw','First come','Milestone reward','Manual award']],['claim_window','Claim window','select',['7 days','14 days','30 days','Custom']]] },
    fundraiser: { title: 'Local Fundraiser', description: 'Set campaign goals, supporter rewards, local merchant participation, and fundraising milestones.', fields: [['campaign_name','Fundraiser name','text','Campaign title'],['beneficiary','Beneficiary','text','Person, group, or cause'],['goal','Fundraising goal','number','5000'],['deadline','Campaign deadline','date',''],['reward_type','Supporter reward','select',['Local gift','Merchant voucher','Recognition only','Tiered rewards']],['merchant_partners','Merchant partners','textarea','Optional local partners'],['campaign_story','Campaign story','textarea','Explain the fundraiser and impact']] }
  };

  function currentAgentId() {
    return new URLSearchParams(window.location.search).get('agent');
  }

  function storageKey() {
    return 'mg_agent_category_' + (currentAgentId() || 'default');
  }

  function fieldMarkup(field) {
    var name = field[0], label = field[1], type = field[2], value = field[3];
    if (type === 'select') return '<label>' + label + '<select name="' + name + '">' + value.map(function (item) { return '<option>' + item + '</option>'; }).join('') + '</select></label>';
    if (type === 'textarea') return '<label class="mg-agent-form-wide">' + label + '<textarea name="' + name + '" rows="4" placeholder="' + value + '"></textarea></label>';
    return '<label>' + label + '<input name="' + name + '" type="' + type + '" placeholder="' + value + '"></label>';
  }

  function showPicker() {
    workspace.hidden = true;
    start.hidden = false;
    toolbarTitle.textContent = 'Choose a gifting path';
    toolbarDescription.textContent = '';
    toolbarDescription.hidden = true;
    changeButton.hidden = true;
  }

  function loadCategory(key, restoreValues) {
    var data = datasets[key];
    if (!data) return;
    start.hidden = true;
    workspace.hidden = false;
    toolbarTitle.textContent = data.title;
    toolbarDescription.textContent = data.description;
    toolbarDescription.hidden = false;
    changeButton.hidden = false;
    form.innerHTML = '<div class="mg-agent-form-grid">' + data.fields.map(fieldMarkup).join('') + '</div>';
    form.dataset.category = key;
    if (restoreValues) {
      Object.keys(restoreValues).forEach(function (name) {
        var input = form.elements.namedItem(name);
        if (input) input.value = restoreValues[name];
      });
    }
    grid.querySelectorAll('[data-agent-category]').forEach(function (card) {
      card.classList.toggle('is-selected', card.dataset.agentCategory === key);
    });
  }

  function formValues() {
    var values = {};
    new FormData(form).forEach(function (value, key) { values[key] = value; });
    return values;
  }

  function persistDraft() {
    var draft = { category: form.dataset.category || null, values: formValues() };
    localStorage.setItem(storageKey(), JSON.stringify(draft));
    var active = window.Microgifter.agents && Microgifter.agents.getActive();
    if (!active) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(async function () {
      try {
        await Microgifter.patch('/api/agents/item.php', {
          id: active.id,
          category: draft.category,
          config: draft.values
        });
        var status = document.querySelector('[data-agent-canvas-status]');
        if (status) status.textContent = 'Saved to account';
      } catch (error) {
        var status = document.querySelector('[data-agent-canvas-status]');
        if (status) status.textContent = error.message || 'Unable to save changes';
      }
    }, 500);
  }

  grid.addEventListener('click', function (event) {
    var card = event.target.closest('[data-agent-category]');
    if (!card) return;
    loadCategory(card.dataset.agentCategory);
    localStorage.setItem(storageKey(), JSON.stringify({ category: card.dataset.agentCategory, values: {} }));
    persistDraft();
  });

  changeButton.addEventListener('click', showPicker);
  form.addEventListener('input', persistDraft);
  form.addEventListener('submit', function (event) { event.preventDefault(); });

  document.addEventListener('mg:agents:rendered', function (event) {
    var active = event.detail && event.detail.active;
    if (active && active.category) loadCategory(active.category, active.config || {});
  });

  try {
    var saved = JSON.parse(localStorage.getItem(storageKey()) || 'null');
    if (saved && saved.category) loadCategory(saved.category, saved.values || {});
    else showPicker();
  } catch (error) {
    showPicker();
  }
});