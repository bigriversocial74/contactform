document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function looksLikeAgentJson(text) {
    text = String(text || '').trim();
    return text.charAt(0) === '{' && /"reply"\s*:/.test(text);
  }

  function extractJsonCandidate(text) {
    text = String(text || '').trim();
    var fenced = text.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
    if (fenced && fenced[1]) return fenced[1].trim();
    var first = text.indexOf('{');
    var last = text.lastIndexOf('}');
    if (first >= 0 && last > first) return text.slice(first, last + 1);
    return text;
  }

  function decodeJsonText(text) {
    var candidate = extractJsonCandidate(text);
    try {
      return JSON.parse(candidate);
    } catch (error) {
      var reply = String(text || '').match(/"reply"\s*:\s*"((?:\\.|[^"\\])*)"/);
      if (!reply || !reply[1]) return null;
      try {
        return { reply: JSON.parse('"' + reply[1] + '"'), blocks: [], cards: [], partial: true };
      } catch (inner) {
        return { reply: reply[1], blocks: [], cards: [], partial: true };
      }
    }
  }

  function barBlock(block) {
    var data = Array.isArray(block.data) ? block.data : [];
    if (!data.length) return '';
    var max = data.reduce(function (m, row) { return Math.max(m, Math.abs(Number(row.value) || 0)); }, 0) || 1;
    return '<div class="mg-agent-block mg-agent-chart-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Chart') + '</strong><span>' + esc(block.body || '') + '</span></div><div class="mg-agent-bars">' + data.map(function (row) {
      var value = Number(row.value) || 0;
      var width = Math.max(6, Math.round((Math.abs(value) / max) * 100));
      return '<div class="mg-agent-bar-row"><span>' + esc(row.label || row.name || 'Item') + '</span><div><i style="width:' + width + '%"></i></div><b>' + esc(value.toLocaleString()) + '</b></div>';
    }).join('') + '</div></div>';
  }

  function metricBlock(block) {
    var metrics = Array.isArray(block.metrics) ? block.metrics : [];
    if (!metrics.length) return '';
    return '<div class="mg-agent-block mg-agent-metric-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Metrics') + '</strong><span>' + esc(block.body || '') + '</span></div><div class="mg-agent-metrics">' + metrics.map(function (metric) {
      return '<article><strong>' + esc(metric.value) + '</strong><span>' + esc(metric.label) + '</span></article>';
    }).join('') + '</div></div>';
  }

  function socialBlock(block) {
    var posts = Array.isArray(block.posts) ? block.posts : [];
    return '<div class="mg-agent-block mg-agent-social-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Social campaign') + '</strong><span>' + esc(block.body || '') + '</span></div>' +
      (posts.length ? '<div class="mg-agent-social-posts">' + posts.map(function (post) {
        return '<article><strong>' + esc(post.channel || 'Social') + '</strong><p>' + esc(post.copy || '') + '</p></article>';
      }).join('') + '</div>' : '') + '</div>';
  }

  function simpleBlock(block) {
    return '<div class="mg-agent-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Insight') + '</strong><span>' + esc(block.body || '') + '</span></div></div>';
  }

  function blockHtml(blocks) {
    blocks = Array.isArray(blocks) ? blocks : [];
    if (!blocks.length) return '';
    return '<div class="mg-agent-chat-blocks is-json-fixed">' + blocks.map(function (block) {
      if (!block || typeof block !== 'object') return '';
      if (block.type === 'metric_grid') return metricBlock(block);
      if (block.type === 'chart' || block.type === 'forecast') return barBlock(block);
      if (block.type === 'social_campaign' || block.type === 'social_posts') return socialBlock(block);
      return simpleBlock(block);
    }).join('') + '</div>';
  }

  function cardHtml(cards) {
    cards = Array.isArray(cards) ? cards : [];
    if (!cards.length) return '';
    return '<div class="mg-agent-chat-cards is-json-fixed">' + cards.slice(0, 4).map(function (card) {
      if (!card || typeof card !== 'object') return '';
      var url = typeof card.action_url === 'string' && card.action_url.charAt(0) === '/' ? card.action_url : '';
      var action = url ? '<a class="mg-btn mg-btn-soft" href="' + esc(url) + '">' + esc(card.action_label || 'Open') + '</a>' : '';
      return '<article class="mg-agent-chat-card"><span>' + esc(card.type || 'next_step') + '</span><strong>' + esc(card.title || 'Next step') + '</strong><p>' + esc(card.body || '') + '</p><div class="mg-agent-chat-card-actions">' + action + '<button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Save draft</button><button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Dismiss</button></div></article>';
    }).join('') + '</div>';
  }

  function formatBubble(bubble) {
    if (!bubble || bubble.getAttribute('data-agent-json-formatted') === '1') return;
    var paragraph = bubble.querySelector(':scope > p');
    if (!paragraph) return;
    var raw = paragraph.textContent || '';
    if (!looksLikeAgentJson(raw)) return;
    var decoded = decodeJsonText(raw);
    if (!decoded) return;
    paragraph.textContent = decoded.reply || 'I created a structured agent response.';
    if (!bubble.querySelector('.mg-agent-chat-blocks') && decoded.blocks) {
      paragraph.insertAdjacentHTML('afterend', blockHtml(decoded.blocks));
    }
    if (!bubble.querySelector('.mg-agent-chat-cards') && decoded.cards) {
      bubble.insertAdjacentHTML('beforeend', cardHtml(decoded.cards));
    }
    if (decoded.partial && !bubble.querySelector('.mg-agent-json-note')) {
      paragraph.insertAdjacentHTML('afterend', '<div class="mg-agent-json-note">Response formatting was recovered from a partial agent payload.</div>');
    }
    bubble.setAttribute('data-agent-json-formatted', '1');
  }

  function scan() {
    root.querySelectorAll('.mg-agent-chat-message.is-agent .mg-agent-chat-bubble').forEach(formatBubble);
  }

  scan();
  new MutationObserver(scan).observe(root, { childList: true, subtree: true });
});
