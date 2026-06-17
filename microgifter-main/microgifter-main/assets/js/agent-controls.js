document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var list = document.querySelector('[data-saved-agent-list]');
  if (!list) return;

  function setCanvasStatus(text) {
    var node = document.querySelector('[data-agent-canvas-status]');
    if (node) node.textContent = text;
  }

  function editName(row, id) {
    var title = row.querySelector('.mg-saved-agent-open strong');
    if (!title || row.querySelector('[data-agent-name-editor]')) return;

    var oldName = title.textContent.trim();
    var editor = document.createElement('form');
    editor.className = 'mg-agent-name-editor';
    editor.dataset.agentNameEditor = id;
    editor.innerHTML = '<input type="text" maxlength="80" aria-label="Agent name"><button type="submit">Save</button><button type="button" data-agent-name-cancel>Cancel</button>';
    editor.querySelector('input').value = oldName;
    row.appendChild(editor);
    row.classList.add('is-editing');
    editor.querySelector('input').focus();
    editor.querySelector('input').select();

    function close() {
      row.classList.remove('is-editing');
      editor.remove();
    }

    editor.addEventListener('submit', async function (event) {
      event.preventDefault();
      var value = editor.querySelector('input').value.trim();
      if (!value) return;
      var submit = editor.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        await Microgifter.patch('/api/agents/item.php', { id: id, name: value });
        await Microgifter.agents.refresh();
        close();
        setCanvasStatus('Agent name saved');
      } catch (error) {
        submit.disabled = false;
        setCanvasStatus(error.message || 'Unable to rename agent');
      }
    });

    editor.querySelector('[data-agent-name-cancel]').addEventListener('click', close);
  }

  list.addEventListener('click', async function (event) {
    var edit = event.target.closest('[data-edit-agent-name]');
    if (edit) {
      event.preventDefault();
      event.stopPropagation();
      var editRow = edit.closest('.mg-saved-agent-row');
      if (editRow) editName(editRow, editRow.dataset.savedAgentDynamic);
      return;
    }

    var toggle = event.target.closest('[data-agent-runtime-toggle]');
    if (toggle) {
      event.preventDefault();
      event.stopPropagation();
      toggle.disabled = true;
      try {
        await Microgifter.post('/api/agents/status.php', {
          id: toggle.dataset.agentRuntimeToggle,
          status: toggle.dataset.nextStatus || 'running'
        });
        await Microgifter.agents.refresh();
      } catch (error) {
        toggle.disabled = false;
        setCanvasStatus(error.message || 'Unable to update agent status');
      }
    }
  });

  document.addEventListener('click', async function (event) {
    var save = event.target.closest('[data-save-agent]');
    if (!save || !window.Microgifter.agents) return;
    var active = Microgifter.agents.getActive();
    if (!active) return;

    event.preventDefault();
    save.disabled = true;
    try {
      var next = active.runtime_status === 'running' ? 'paused' : 'running';
      await Microgifter.post('/api/agents/status.php', { id: active.id, status: next });
      await Microgifter.agents.refresh();
    } catch (error) {
      save.disabled = false;
      setCanvasStatus(error.message || 'Unable to update agent status');
    }
  });
});