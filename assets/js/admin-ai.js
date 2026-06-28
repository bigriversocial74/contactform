document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-admin-ai]');
  if (!root || !window.Microgifter) return;
  var form = root.querySelector('[data-ai-settings-form]');
  var list = root.querySelector('[data-ai-provider-list]');
  var status = root.querySelector('[data-ai-settings-status]');
  var state = { providers: [] };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function msg(text, type) {
    status.textContent = text || '';
    status.classList.toggle('is-error', type === 'error');
    status.classList.toggle('is-success', type === 'success');
  }

  function rateInput(provider, name, label) {
    return '<label>' + label + '<input type="number" min="0" data-rate="' + name + '" value="' + Number(provider[name] || 0) + '"></label>';
  }

  function modelRow(model) {
    return '<div class="mg-ai-model-row" data-model-key="' + esc(model.model_key) + '"><label>Model key<input data-model-field="model_key" value="' + esc(model.model_key) + '"></label><label>Display name<input data-model-field="display_name" value="' + esc(model.display_name) + '"></label><label class="mg-ai-checkbox"><input type="checkbox" data-model-field="enabled" ' + (model.enabled ? 'checked' : '') + '>Enabled</label><label class="mg-ai-checkbox"><input type="checkbox" data-model-field="is_default" ' + (model.is_default ? 'checked' : '') + '>Default</label><label>Sort<input type="number" min="0" data-model-field="sort_order" value="' + Number(model.sort_order || 100) + '"></label></div>';
  }

  function render() {
    list.innerHTML = state.providers.map(function (provider, i) {
      var models = (provider.models || []).filter(function (model) {
        return String(model.model_key || '').trim() !== '';
      }).map(modelRow).join('');
      return '<article class="mg-ai-provider-card" data-provider-index="' + i + '"><header class="mg-ai-provider-head"><div><span>' + esc(provider.provider_key) + '</span><h2>' + esc(provider.display_name) + '</h2><code>' + esc(provider.env_var_name) + '</code></div><div><span class="mg-ai-provider-status ' + (provider.configured ? 'is-ready' : 'is-missing') + '">' + (provider.configured ? 'Configured' : 'Missing env key') + '</span><label class="mg-ai-toggle"><input type="checkbox" data-provider-enabled ' + (provider.enabled ? 'checked' : '') + '> Provider enabled</label></div></header><section><h3>Triple rate limits</h3><div class="mg-ai-rate-grid">' + rateInput(provider, 'rate_limit_per_minute', 'Global / key per minute') + rateInput(provider, 'rate_limit_per_hour', 'Global / key per hour') + rateInput(provider, 'rate_limit_per_day', 'Global / key per day') + rateInput(provider, 'user_rate_limit_per_hour', 'Per user per hour') + rateInput(provider, 'user_rate_limit_per_day', 'Per user per day') + rateInput(provider, 'agent_rate_limit_per_hour', 'Per agent per hour') + rateInput(provider, 'agent_rate_limit_per_day', 'Per agent per day') + '</div></section><section class="mg-ai-models"><h3>Available models</h3>' + models + '<button class="mg-btn mg-btn-soft" type="button" data-add-model>Add model</button></section></article>';
    }).join('');
  }

  function readModel(row) {
    var modelKey = row.querySelector('[data-model-field="model_key"]').value.trim();
    var displayName = row.querySelector('[data-model-field="display_name"]').value.trim();
    if (modelKey === '' && displayName === '') return null;
    return {
      model_key: modelKey,
      display_name: displayName,
      enabled: row.querySelector('[data-model-field="enabled"]').checked,
      is_default: row.querySelector('[data-model-field="is_default"]').checked,
      sort_order: Number(row.querySelector('[data-model-field="sort_order"]').value || 100)
    };
  }

  function readPayload() {
    return {
      providers: Array.prototype.map.call(list.querySelectorAll('[data-provider-index]'), function (card) {
        var provider = state.providers[Number(card.dataset.providerIndex)];
        var models = Array.prototype.map.call(card.querySelectorAll('[data-model-key]'), readModel).filter(Boolean);
        var defaultModel = models.find(function (model) { return model.is_default; });
        var payload = {
          provider_key: provider.provider_key,
          enabled: card.querySelector('[data-provider-enabled]').checked,
          models: models,
          default_model_key: defaultModel ? defaultModel.model_key : ''
        };
        card.querySelectorAll('[data-rate]').forEach(function (input) {
          payload[input.dataset.rate] = Number(input.value || 0);
        });
        return payload;
      })
    };
  }

  async function load() {
    msg('Loading AI providers…');
    try {
      var response = await Microgifter.get('/api/admin/ai-settings.php');
      state.providers = (response.data && response.data.providers) || response.providers || [];
      render();
      msg('');
    } catch (error) {
      msg(error.message || 'Unable to load AI provider settings.', 'error');
    }
  }

  list.addEventListener('click', function (event) {
    var add = event.target.closest('[data-add-model]');
    if (!add) return;
    var wrap = add.parentElement;
    var div = document.createElement('div');
    div.innerHTML = modelRow({ model_key: '', display_name: '', enabled: true, is_default: false, sort_order: 100 });
    wrap.insertBefore(div.firstElementChild, add);
  });

  list.addEventListener('change', function (event) {
    var checkbox = event.target.closest('[data-model-field="is_default"]');
    if (!checkbox || !checkbox.checked) return;
    var card = checkbox.closest('[data-provider-index]');
    card.querySelectorAll('[data-model-field="is_default"]').forEach(function (box) {
      if (box !== checkbox) box.checked = false;
    });
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    msg('Saving AI provider settings…');
    try {
      var response = await Microgifter.post('/api/admin/ai-settings.php', readPayload());
      state.providers = (response.data && response.data.providers) || response.providers || [];
      render();
      msg(response.message || 'AI provider settings saved.', 'success');
    } catch (error) {
      msg(error.message || 'Unable to save AI provider settings.', 'error');
    }
  });

  load();
});