document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-homeserver-release-admin]');
  if (!root) return;

  var endpoint = '/api/admin/homeserver-releases.php';
  var form = root.querySelector('[data-hsr-upload-form]');
  var uploadButton = root.querySelector('[data-hsr-upload]');
  var refreshButton = root.querySelector('[data-hsr-refresh]');
  var status = root.querySelector('[data-hsr-status]');
  var releaseRows = root.querySelector('[data-hsr-release-rows]');
  var downloadRows = root.querySelector('[data-hsr-download-rows]');
  var fileInput = form ? form.querySelector('input[name="file"]') : null;
  var fileName = root.querySelector('[data-hsr-file-name]');
  var current = null;

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function apiJson(response) {
    return response.json().catch(function () { return {}; }).then(function (payload) {
      if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
      return payload.data && typeof payload.data === 'object' ? payload.data : payload;
    });
  }

  function setStatus(kind, title, detail) {
    if (!status) return;
    status.classList.remove('is-loading', 'is-good', 'is-warning', 'is-bad');
    status.classList.add(kind === 'good' ? 'is-good' : kind === 'bad' ? 'is-bad' : 'is-warning');
    var strong = status.querySelector('strong');
    var paragraph = status.querySelector('p');
    if (strong) strong.textContent = title;
    if (paragraph) paragraph.textContent = detail;
  }

  function formatBytes(value) {
    var bytes = Number(value || 0);
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
  }

  function formatDate(value) {
    if (!value) return 'Not published';
    var raw = String(value);
    if (!/[zZ]|[+-]\d\d:\d\d$/.test(raw)) raw = raw.replace(' ', 'T') + 'Z';
    var date = new Date(raw);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }

  function text(selector, value) {
    var node = root.querySelector(selector);
    if (node) node.textContent = value;
  }

  function getData() {
    return window.fetch(endpoint, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    }).then(apiJson);
  }

  function postAction(action, extra) {
    return window.fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify(Object.assign({ action: action, csrf_token: csrf() }, extra || {}))
    }).then(apiJson);
  }

  function statusPill(release) {
    var className = 'mg-hsr-pill';
    var label = release.status || 'draft';
    if (release.is_latest) {
      className += ' is-latest';
      label = 'Latest';
    } else if (release.status === 'retired') {
      className += ' is-retired';
    } else if (release.status === 'draft') {
      className += ' is-draft';
    }
    var pill = document.createElement('span');
    pill.className = className;
    pill.textContent = label;
    return pill;
  }

  function appendText(parent, tag, value, className) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    parent.appendChild(node);
    return node;
  }

  function releaseRow(release) {
    var row = document.createElement('tr');

    var versionCell = document.createElement('td');
    appendText(versionCell, 'strong', 'v' + (release.version || 'Unknown'));
    appendText(versionCell, 'small', (release.platform || 'windows') + ' · ' + (release.architecture || 'x64'));
    versionCell.appendChild(statusPill(release));

    var releaseCell = document.createElement('td');
    appendText(releaseCell, 'strong', (release.channel || 'stable').toUpperCase());
    appendText(releaseCell, 'small', release.mandatory ? 'Mandatory update flag enabled' : 'Standard update');
    if (release.minimum_supported_version) appendText(releaseCell, 'small', 'Minimum: ' + release.minimum_supported_version);

    var fileCell = document.createElement('td');
    appendText(fileCell, 'strong', release.filename || 'HomeServer installer');
    appendText(fileCell, 'small', formatBytes(release.byte_size));
    var checksum = String(release.checksum_sha256 || '');
    if (checksum) {
      var checksumLine = document.createElement('small');
      checksumLine.textContent = 'SHA-256 ' + checksum.slice(0, 12) + '… ';
      var copy = document.createElement('button');
      copy.type = 'button';
      copy.className = 'mg-hsr-copy';
      copy.textContent = 'Copy';
      copy.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(checksum).then(function () {
            copy.textContent = 'Copied';
            window.setTimeout(function () { copy.textContent = 'Copy'; }, 1200);
          });
        }
      });
      checksumLine.appendChild(copy);
      fileCell.appendChild(checksumLine);
    }

    var countCell = document.createElement('td');
    appendText(countCell, 'strong', Number(release.download_count || 0).toLocaleString());
    appendText(countCell, 'small', 'authenticated requests');

    var dateCell = document.createElement('td');
    appendText(dateCell, 'strong', formatDate(release.published_at));
    appendText(dateCell, 'small', 'Uploaded ' + formatDate(release.created_at));

    var actionCell = document.createElement('td');
    var actions = document.createElement('div');
    actions.className = 'mg-hsr-row-actions';
    if (release.status === 'published') {
      var download = document.createElement('a');
      download.href = release.download_url || '#';
      download.textContent = 'Download';
      actions.appendChild(download);
    }
    if (!release.is_latest && release.status !== 'retired') {
      var latest = document.createElement('button');
      latest.type = 'button';
      latest.className = 'is-primary';
      latest.textContent = 'Make latest';
      latest.addEventListener('click', function () { mutate('set_latest', release.release_id, latest); });
      actions.appendChild(latest);
    }
    if (release.status !== 'retired') {
      var retire = document.createElement('button');
      retire.type = 'button';
      retire.className = 'is-danger';
      retire.textContent = 'Retire';
      retire.addEventListener('click', function () {
        if (window.confirm('Retire HomeServer v' + release.version + '? Existing tracking remains, but the installer will no longer be downloadable.')) {
          mutate('retire', release.release_id, retire);
        }
      });
      actions.appendChild(retire);
    }
    actionCell.appendChild(actions);

    row.appendChild(versionCell);
    row.appendChild(releaseCell);
    row.appendChild(fileCell);
    row.appendChild(countCell);
    row.appendChild(dateCell);
    row.appendChild(actionCell);
    return row;
  }

  function renderReleases(releases) {
    if (!releaseRows) return;
    releaseRows.textContent = '';
    if (!Array.isArray(releases) || releases.length === 0) {
      var emptyRow = document.createElement('tr');
      var empty = document.createElement('td');
      empty.colSpan = 6;
      empty.className = 'mg-hsr-empty';
      empty.textContent = 'No HomeServer releases have been uploaded yet.';
      emptyRow.appendChild(empty);
      releaseRows.appendChild(emptyRow);
      return;
    }
    releases.forEach(function (release) { releaseRows.appendChild(releaseRow(release)); });
  }

  function renderDownloads(downloads) {
    if (!downloadRows) return;
    downloadRows.textContent = '';
    if (!Array.isArray(downloads) || downloads.length === 0) {
      var emptyRow = document.createElement('tr');
      var empty = document.createElement('td');
      empty.colSpan = 5;
      empty.className = 'mg-hsr-empty';
      empty.textContent = 'No HomeServer installer download requests have been recorded yet.';
      emptyRow.appendChild(empty);
      downloadRows.appendChild(emptyRow);
      return;
    }
    downloads.forEach(function (download) {
      var row = document.createElement('tr');
      var time = document.createElement('td');
      appendText(time, 'strong', formatDate(download.downloaded_at));
      var user = document.createElement('td');
      appendText(user, 'strong', download.user_email || 'Unknown account');
      var version = document.createElement('td');
      appendText(version, 'strong', 'v' + (download.version || 'Unknown'));
      appendText(version, 'small', download.architecture || 'x64');
      var channel = document.createElement('td');
      channel.appendChild(statusPill({ status: 'published', is_latest: false, channel: download.channel }));
      channel.lastChild.textContent = String(download.channel || 'stable').toUpperCase();
      var client = document.createElement('td');
      client.className = 'mg-hsr-client';
      client.title = download.user_agent || '';
      client.textContent = download.user_agent || 'Client not reported';
      row.appendChild(time);
      row.appendChild(user);
      row.appendChild(version);
      row.appendChild(channel);
      row.appendChild(client);
      downloadRows.appendChild(row);
    });
  }

  function renderReadiness(payload) {
    var cards = root.querySelectorAll('[data-hsr-readiness] article');
    if (!cards.length) return;
    var schemaCard = cards[0];
    var storageCard = cards[1];
    schemaCard.classList.remove('is-good', 'is-bad');
    storageCard.classList.remove('is-good', 'is-bad');
    var schemaStrong = schemaCard.querySelector('strong');
    var storageStrong = storageCard.querySelector('strong');
    var storageParagraph = storageCard.querySelector('p');
    if (payload.schema_ready) {
      schemaCard.classList.add('is-good');
      if (schemaStrong) schemaStrong.textContent = 'Ready';
    } else {
      schemaCard.classList.add('is-bad');
      if (schemaStrong) schemaStrong.textContent = 'Migration required';
    }
    if (payload.storage && payload.storage.ready && payload.storage.persistent && payload.storage.writable) {
      storageCard.classList.add('is-good');
      if (storageStrong) storageStrong.textContent = 'Ready';
    } else {
      storageCard.classList.add('is-bad');
      if (storageStrong) storageStrong.textContent = 'Needs configuration';
    }
    if (storageParagraph && payload.storage && payload.storage.message) storageParagraph.textContent = payload.storage.message;
  }

  function render(payload) {
    current = payload || {};
    var stats = current.stats || {};
    text('[data-hsr-stat="release_count"]', Number(stats.release_count || 0).toLocaleString());
    text('[data-hsr-stat="published_count"]', Number(stats.published_count || 0).toLocaleString());
    text('[data-hsr-stat="download_count"]', Number(stats.download_count || 0).toLocaleString());
    text('[data-hsr-stat="latest_version"]', stats.latest_version ? 'v' + stats.latest_version : '—');
    renderReadiness(current);
    renderReleases(current.releases || []);
    renderDownloads(current.recent_downloads || []);

    var ready = current.schema_ready && current.storage && current.storage.ready && current.storage.persistent && current.storage.writable;
    if (!current.schema_ready) {
      setStatus('bad', 'HomeServer release migration required', 'Import database/20260727_homeserver_release_distribution_v1.sql before uploading the installer.');
    } else if (!current.storage || !current.storage.ready || !current.storage.persistent || !current.storage.writable) {
      setStatus('bad', 'Protected storage is not ready', current.storage && current.storage.message ? current.storage.message : 'Configure persistent storage outside the web root.');
    } else if (!current.stats || !current.stats.latest_version) {
      setStatus('warning', 'Ready for the first HomeServer release', 'Upload the Windows installer and publish it as the latest stable version.');
    } else {
      setStatus('good', 'HomeServer downloads are ready', 'Latest stable version v' + current.stats.latest_version + ' is available with tracked authenticated downloads.');
    }
    if (uploadButton) uploadButton.disabled = !ready;
  }

  function refresh(successMessage) {
    if (refreshButton) {
      refreshButton.disabled = true;
      refreshButton.textContent = 'Refreshing…';
    }
    return getData().then(render).then(function () {
      if (successMessage) setStatus('good', 'Release data refreshed', successMessage);
    }).catch(function (error) {
      setStatus('bad', 'Unable to load HomeServer releases', error.message || 'Try again.');
    }).finally(function () {
      if (refreshButton) {
        refreshButton.disabled = false;
        refreshButton.textContent = 'Refresh';
      }
    });
  }

  function mutate(action, releaseId, button) {
    var original = button ? button.textContent : '';
    if (button) {
      button.disabled = true;
      button.textContent = action === 'set_latest' ? 'Publishing…' : 'Retiring…';
    }
    postAction(action, { release_id: releaseId }).then(render).then(function () {
      setStatus('good', action === 'set_latest' ? 'Latest version updated' : 'Release retired', action === 'set_latest' ? 'The selected installer is now the latest downloadable HomeServer release.' : 'The installer is no longer available for download.');
    }).catch(function (error) {
      setStatus('bad', 'Release update failed', error.message || 'Try again.');
    }).finally(function () {
      if (button && document.body.contains(button)) {
        button.disabled = false;
        button.textContent = original;
      }
    });
  }

  if (fileInput) fileInput.addEventListener('change', function () {
    if (fileName) fileName.textContent = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : 'Choose the latest .exe file';
  });

  if (form) form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
      setStatus('warning', 'Choose the installer', 'Select the current Microgifter HomeServer .exe file before uploading.');
      return;
    }
    var body = new FormData(form);
    body.set('action', 'upload');
    body.set('csrf_token', csrf());
    var publish = form.querySelector('input[name="publish_now"]');
    var mandatory = form.querySelector('input[name="mandatory_update"]');
    body.set('publish_now', publish && publish.checked ? '1' : '0');
    body.set('mandatory_update', mandatory && mandatory.checked ? '1' : '0');
    form.classList.add('is-busy');
    if (uploadButton) {
      uploadButton.disabled = true;
      uploadButton.textContent = 'Uploading and verifying…';
    }
    setStatus('warning', 'Uploading HomeServer installer', 'Keep this page open while the executable is verified, stored, and registered.');
    window.fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() },
      body: body
    }).then(apiJson).then(render).then(function () {
      form.reset();
      if (fileName) fileName.textContent = 'Choose the latest .exe file';
      setStatus('good', 'HomeServer release uploaded', 'The installer and version metadata are now available from the HomeServer status modal.');
    }).catch(function (error) {
      setStatus('bad', 'HomeServer upload failed', error.message || 'The installer could not be uploaded.');
    }).finally(function () {
      form.classList.remove('is-busy');
      if (uploadButton) {
        uploadButton.disabled = current && (!current.schema_ready || !current.storage || !current.storage.ready);
        uploadButton.textContent = 'Upload and publish';
      }
    });
  });

  if (refreshButton) refreshButton.addEventListener('click', function () { refresh('Release and download records are current.'); });
  refresh();
});
