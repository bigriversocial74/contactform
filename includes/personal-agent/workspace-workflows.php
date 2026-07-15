<?php declare(strict_types=1); ?>
<section class="mg-personal-agent-view" data-personal-agent-view="scheduled"<?= $activeView === 'scheduled' ? '' : ' hidden' ?>>
  <section class="mg-personal-agent-section mg-workflow-section">
    <header><div><span>Scheduled gifts</span><h2>Prepare-only review checkpoints</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="schedule">Schedule a plan</button></header>
    <p class="mg-personal-agent-boundary">A schedule prepares a review checkpoint. It never checks out, charges a card, or sends a gift.</p>
    <div class="mg-workflow-list" data-personal-workflow-schedules></div>
  </section>
</section>

<section class="mg-personal-agent-view" data-personal-agent-view="recurring"<?= $activeView === 'recurring' ? '' : ' hidden' ?>>
  <section class="mg-personal-agent-section mg-workflow-section">
    <header><div><span>Recurring programs</span><h2>Generate one approval-first draft per occurrence</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="recurring">New recurring program</button></header>
    <p class="mg-personal-agent-boundary">Programs create draft plans only. Each occurrence is idempotent and still requires review.</p>
    <div class="mg-workflow-list" data-personal-workflow-recurring></div>
  </section>
</section>

<section class="mg-personal-agent-view" data-personal-agent-view="requests"<?= $activeView === 'requests' ? '' : ' hidden' ?>>
  <section class="mg-personal-agent-section mg-workflow-section">
    <header><div><span>Recipient consent</span><h2>Request preferences, birthdays, or an address</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="recipient-request">New request</button></header>
    <p class="mg-personal-agent-boundary">Only mutually connected users may receive requests. Recipients choose fields, may decline, and may revoke later.</p>
    <div class="mg-workflow-columns"><div><h3>Incoming requests</h3><div class="mg-workflow-list" data-personal-workflow-incoming-requests></div></div><div><h3>Sent requests</h3><div class="mg-workflow-list" data-personal-workflow-outgoing-requests></div></div></div>
  </section>
</section>

<section class="mg-personal-agent-view" data-personal-agent-view="bundles"<?= $activeView === 'bundles' ? '' : ' hidden' ?>>
  <section class="mg-personal-agent-section mg-workflow-section">
    <header><div><span>Gift bundles</span><h2>Build a reviewable local gift set</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="bundle">New bundle</button></header>
    <p class="mg-personal-agent-boundary">Bundle items are planning records. Product selection does not create a cart, order, or payment.</p>
    <div class="mg-workflow-list" data-personal-workflow-bundles></div>
  </section>
</section>

<section class="mg-personal-agent-view" data-personal-agent-view="claims"<?= $activeView === 'claims' ? '' : ' hidden' ?>>
  <section class="mg-personal-agent-section mg-workflow-section">
    <header><div><span>Claim and redemption</span><h2>In-app lifecycle reminders</h2></div><button type="button" class="mg-btn mg-btn-primary" data-open-agent-dialog="lifecycle-reminder">New lifecycle reminder</button></header>
    <p class="mg-personal-agent-boundary">Reminders create explicit in-app notifications only. They never claim, redeem, expire, or otherwise change a gift.</p>
    <div class="mg-workflow-list" data-personal-workflow-lifecycle></div>
  </section>
</section>
