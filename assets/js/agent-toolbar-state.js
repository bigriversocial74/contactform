document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var button = document.querySelector('[data-save-agent]');
  if (!button) return;

  var SAVED_KEY = 'mg_saved_agents_v1';
  var STATE_KEY = 'mg_agent_runtime_v1';

  function read(key, fallback) {
    try {
      return JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
    } catch (error) {
      return fallback;
    }
  }

  function write(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  function currentAgentId() {
    return new URLSearchParams(window.location.search).get('agent');
  }

  function isSavedAgent(id) {
    if (!id) return false;
    return read(SAVED_KEY, []).some(function (item) {
      return item.id === id;
    });
  }

  function runtimeFor(id) {
    var states = read(STATE_KEY, {});
    return states[id] || {
      status: 'paused',
      updated_at: new Date().toISOString()
    };
  }

  function updateSidebarCard(id, state) {
    var row = document.querySelector('[data-saved-agent-dynamic="' + CSS.escape(id) + '"]');
    if (!row) return;

    var isRunning = state.status === 'running';
    row.classList.toggle('is-running', isRunning);

    var status = row.querySelector('.mg-agent-runtime-status');
    if (status) {
      status.textContent = isRunning ? 'Running' : 'Paused';
      status.classList.toggle('is-running', isRunning);
    }

    var time = row.querySelector('.mg-agent-runtime-meta time');
    if (time) {
      var date = new Date(state.updated_at);
      time.dateTime = state.updated_at;
      time.textContent = 'Updated ' + date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
      });
    }

    var toggle = row.querySelector('[data-agent-runtime-toggle]');
    if (toggle) {
      toggle.dataset.nextStatus = isRunning ? 'paused' : 'running';
      toggle.classList.toggle('is-pause', isRunning);
      toggle.classList.toggle('is-start', !isRunning);
      toggle.textContent = isRunning ? 'Pause agent' : 'Start agent';
    }
  }

  function render() {
    var id = currentAgentId();
    var saved = isSavedAgent(id);

    button.dataset.agentToolbarMode = saved ? 'runtime' : 'save';

    if (!saved) {
      button.textContent = 'Save agent';
      button.classList.remove('is-running');
      return;
    }

    var state = runtimeFor(id);
    var isRunning = state.status === 'running';
    button.textContent = isRunning ? 'Pause agent' : 'Start agent';
    button.classList.toggle('is-running', isRunning);
    button.setAttribute('aria-label', isRunning ? 'Pause this saved agent' : 'Start this saved agent');
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-save-agent]');
    if (!target) return;

    var id = currentAgentId();
    if (!isSavedAgent(id)) return;

    event.preventDefault();
    event.stopImmediatePropagation();

    var states = read(STATE_KEY, {});
    var current = states[id] || { status: 'paused' };
    var nextStatus = current.status === 'running' ? 'paused' : 'running';
    var nextState = {
      status: nextStatus,
      updated_at: new Date().toISOString()
    };

    states[id] = nextState;
    write(STATE_KEY, states);

    var canvasStatus = document.querySelector('[data-agent-canvas-status]');
    if (canvasStatus) {
      canvasStatus.textContent = nextStatus === 'running' ? 'Agent running' : 'Agent paused';
    }

    updateSidebarCard(id, nextState);
    render();
  }, true);

  window.addEventListener('storage', function (event) {
    if (event.key === STATE_KEY || event.key === SAVED_KEY) render();
  });

  render();
});