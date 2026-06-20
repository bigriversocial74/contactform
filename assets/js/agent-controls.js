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
        var response = await Microgifter.patch('/api/agents/item.php', { id: id, name: value });
        if (response.data && response.data.agent && Microgifter.agents) {
          Microgifter.agents.applyUpdate(response.data.agent);
        } else {
          await Microgifter.agents.refresh();
        }
        close();
        setCanvasStatus('Agent name saved');
      } catch (error) {
        submit.disabled = false;
        setCanvasStatus(error.message || 'Unable to rename agent');
      }
    });

    editor.querySelector('[data-agent-name-cancel]').addEventListener('click', close);
  }

  async function changeRuntimeStatus(id, nextStatus) {
    if (!window.Microgifter.agents) return;

    var previous = Microgifter.agents.setRuntimeStatus(id, nextStatus);
    setCanvasStatus(nextStatus === 'running' ? 'Starting agent…' : 'Pausing agent…');

    try {
      var response = await Microgifter.post('/api/agents/status.php', {
        id: id,
        status: nextStatus
      });
      if (response.data && response.data.agent) {
        Microgifter.agents.applyUpdate(response.data.agent);
      } else {
        await Microgifter.agents.refresh();
      }
      setCanvasStatus(nextStatus === 'running' ? 'Saved · Running' : 'Saved · Paused');
    } catch (error) {
      if (previous) Microgifter.agents.applyUpdate(previous);
      setCanvasStatus(error.message || 'Unable to update agent status');
    }
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
      var id = toggle.dataset.agentRuntimeToggle;
      var nextStatus = toggle.dataset.nextStatus || 'running';
      await changeRuntimeStatus(id, nextStatus);
    }
  });

  document.addEventListener('click', async function (event) {
    var save = event.target.closest('[data-save-agent]');
    if (!save || !window.Microgifter.agents) return;
    var active = Microgifter.agents.getActive();
    if (!active) return;

    event.preventDefault();
    var next = active.runtime_status === 'running' ? 'paused' : 'running';
    await changeRuntimeStatus(active.id, next);
  });
});
