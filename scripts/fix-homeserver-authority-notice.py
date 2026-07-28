#!/usr/bin/env python3
from pathlib import Path

path = Path("assets/js/homeserver-intelligence-authority.js")
text = path.read_text(encoding="utf-8")

old_state = "  var campaignResponse = null;\n  var busy = false;\n"
new_state = "  var campaignResponse = null;\n  var busy = false;\n  var notice = null;\n"
if old_state in text:
    text = text.replace(old_state, new_state, 1)
elif new_state not in text:
    raise SystemExit("authority notice state anchor was not found")

old_message = """  function message(text, kind) {
    var node = authorityRoot.querySelector('[data-homeserver-authority-message]');
    if (!node) return;
    node.hidden = !text;
    node.textContent = text || '';
    node.className = 'mg-homeserver-authority-message is-' + (kind || 'info');
  }
"""
new_message = """  function message(text, kind) {
    notice = text ? { text: String(text), kind: kind || 'info' } : null;
    var node = authorityRoot.querySelector('[data-homeserver-authority-message]');
    if (!node) return;
    node.hidden = !notice;
    node.textContent = notice ? notice.text : '';
    node.className = 'mg-homeserver-authority-message is-' + (notice ? notice.kind : 'info');
  }
"""
if old_message in text:
    text = text.replace(old_message, new_message, 1)
elif new_message not in text:
    raise SystemExit("authority message function anchor was not found")

old_render = "      '<div class=\"mg-homeserver-authority-message\" data-homeserver-authority-message hidden></div>',\n"
new_render = "      '<div class=\"mg-homeserver-authority-message is-' + escapeHtml(notice ? notice.kind : 'info') + '\" data-homeserver-authority-message' + (notice ? '' : ' hidden') + '>' + escapeHtml(notice ? notice.text : '') + '</div>',\n"
if old_render in text:
    text = text.replace(old_render, new_render, 1)
elif new_render not in text:
    raise SystemExit("authority notice render anchor was not found")

path.write_text(text, encoding="utf-8", newline="\n")
print("HomeServer authority notices persist across re-renders.")
