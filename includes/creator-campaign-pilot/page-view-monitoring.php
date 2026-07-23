<?php
declare(strict_types=1);
?>
      <section class="mg-pilot-grid">
        <section class="mg-pilot-panel mg-pilot-events">
          <header><div><span>Operator evidence</span><h2>Pilot activity</h2></div><p>Owner transitions, emergency actions, run recovery, and handoffs.</p></header>
          <?php if ($operatorEvents === []): ?><div class="mg-pilot-empty"><strong>No operator events yet</strong></div><?php else: ?><div class="mg-pilot-feed"><?php foreach ($operatorEvents as $event): ?><article><i class="is-<?= mg_e((string)$event['severity']) ?>"></i><div><strong><?= mg_e($statusLabel((string)$event['event_type'])) ?></strong><p><?= mg_e((string)($event['note'] ?? 'Operator evidence recorded.')) ?></p><small><?= mg_e($formatDate((string)$event['created_at'])) ?></small></div></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="mg-pilot-panel mg-pilot-security">
          <header><div><span>Security monitoring</span><h2>MCP security feed</h2></div><p>Recent Creator Campaign MCP security events for this workspace.</p></header>
          <?php if ($securityEvents === []): ?><div class="mg-pilot-empty"><strong>No recent Creator Campaign security events</strong></div><?php else: ?><div class="mg-pilot-feed"><?php foreach ($securityEvents as $event): ?><article><i class="is-<?= mg_e((string)$event['severity']) ?>"></i><div><strong><?= mg_e((string)$event['event_type']) ?></strong><p><?= mg_e((string)$event['message']) ?></p><small><?= mg_e($formatDate((string)$event['created_at'])) ?></small></div></article><?php endforeach; ?></div><?php endif; ?>
        </section>
      </section>

      <section class="mg-pilot-panel mg-pilot-guide">
        <header><div><span>Pilot operating guide</span><h2>Campaign → review → request → owner execution</h2></div><p>Every stage has a visible boundary and a separate owner decision.</p></header>
        <ol>
          <li><strong>Connect</strong><span>Authorize a draft-only client to the merchant workspace with exact playbook scopes.</span></li>
          <li><strong>Bound</strong><span>Create an active grant and one fixed manual definition per playbook.</span></li>
          <li><strong>Run</strong><span>The external agent invokes the matching tool; Microgifter revalidates authority and canonical campaign data.</span></li>
          <li><strong>Review</strong><span>The run creates one Agent Drafts artifact and one canonical receipt, with no native mutation.</span></li>
          <li><strong>Request</strong><span>An approved recommendation may create a new Phase 13C request under a separate approval-gated grant.</span></li>
          <li><strong>Approve and execute</strong><span>The merchant reviews the exact request, approves it, then uses a second explicit execution control.</span></li>
        </ol>
        <footer><strong>Never automatic</strong><span>No scheduler, worker, payment provider, bank access, agreement acceptance, publication, or action execution is enabled by the Phase 14 operator experience.</span></footer>
      </section>
