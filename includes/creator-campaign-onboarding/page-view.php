<?php
declare(strict_types=1);

$business = (array)($onboarding['business_defaults'] ?? []);
$productSelection = (array)($onboarding['product_selection'] ?? []);
$financials = (array)($onboarding['compensation_defaults'] ?? []);
$preferences = (array)($onboarding['creator_preferences'] ?? []);
$roles = (array)($onboarding['operator_roles'] ?? []);
$selectedProductIds = array_values(array_filter(array_map(
    static fn(array $row): string => (string)($row['product_public_id'] ?? ''),
    (array)($productSelection['products'] ?? [])
)));
$money = static fn(int $minor, string $currency = 'USD'): string => $currency . ' ' . number_format($minor / 100, 2);
$minorInput = static fn(mixed $minor): string => number_format(((int)$minor) / 100, 2, '.', '');
$listText = static fn(mixed $value): string => implode("\n", array_values(array_filter((array)$value)));
$selectedCampaign = is_array($readiness['campaign']['campaign'] ?? null) ? $readiness['campaign']['campaign'] : null;
$latestSmoke = is_array($readiness['latest_smoke_test'] ?? null) ? $readiness['latest_smoke_test'] : null;
$financialExposure = (array)($readiness['financial_exposure'] ?? []);
$formatDate = static function (?string $value): string {
    if (!$value) return 'Not yet';
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : gmdate('M j, Y · g:i A', $timestamp) . ' UTC';
};
$stepHref = static fn(int $step): string => '#onboarding-step-' . $step;
?>
<section class="mg-app-shell mg-onboarding-shell" data-creator-campaign-onboarding>
  <?php require dirname(__DIR__) . '/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-onboarding-workspace">
    <header class="mg-onboarding-hero">
      <div>
        <span class="mg-onboarding-eyebrow">Creator Campaign · Phase 15</span>
        <h1>Pilot launch and merchant onboarding</h1>
        <p>Prepare an existing merchant workspace for its first production Creator Campaign. This flow uses native campaign, catalog, compensation, budget, tracking, agreement, and operator services. It does not configure MCP or grant automation authority.</p>
        <div class="mg-onboarding-hero-links">
          <a href="/account-creator-campaign-pilot.php">Operator cockpit</a>
          <a href="/merchant-creator-campaigns.php">Creator Campaigns</a>
          <a href="/merchant-products.php">Product catalog</a>
        </div>
      </div>
      <aside class="mg-onboarding-score">
        <span><?= mg_e((string)($workspace['display_name'] ?? 'Merchant workspace')) ?></span>
        <strong><?= (int)($readiness['score'] ?? 0) ?>%</strong>
        <small><?= (int)($readiness['completed'] ?? 0) ?> of <?= (int)($readiness['total'] ?? 9) ?> launch steps complete</small>
        <?php if ($onboarding): ?><em class="is-<?= mg_e((string)$onboarding['status']) ?>"><?= mg_e(mg_creator_campaign_onboarding_status_label((string)$onboarding['status'])) ?></em><?php endif; ?>
      </aside>
    </header>

    <?php if ($notice !== ''): ?><div class="mg-onboarding-alert is-success"><?= mg_e($notice) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="mg-onboarding-alert is-error"><?= mg_e($errorMessage) ?></div><?php endif; ?>

    <?php if (!$pilotSchemaReady || !$onboardingSchemaReady || !$pilot || !$onboarding): ?>
      <section class="mg-onboarding-empty">
        <strong>Phase 15 onboarding schema is unavailable</strong>
        <p>Import <code>database/20260723_creator_campaign_pilot_launch_onboarding_v15_single_install.sql</code> after the Phase 14 migration, then reopen this page.</p>
      </section>
    <?php else: ?>
      <nav class="mg-onboarding-step-nav" aria-label="Merchant onboarding steps">
        <?php foreach (MG_CREATOR_CAMPAIGN_ONBOARDING_STEPS as $number => $step): $state = $readiness['steps'][$number] ?? []; ?>
          <a href="<?= mg_e($stepHref($number)) ?>" class="<?= !empty($state['complete']) ? 'is-complete' : '' ?><?= (int)($readiness['next_step'] ?? 1) === $number ? ' is-current' : '' ?>">
            <i><?= !empty($state['complete']) ? '✓' : $number ?></i>
            <span><strong><?= mg_e((string)$step['label']) ?></strong><small><?= !empty($state['complete']) ? 'Complete' : 'Needs attention' ?></small></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <section class="mg-onboarding-overview">
        <article>
          <span>Next step</span>
          <strong><?= mg_e(mg_creator_campaign_onboarding_step_label((int)($readiness['next_step'] ?? 1))) ?></strong>
          <a href="<?= mg_e($stepHref((int)($readiness['next_step'] ?? 1))) ?>">Continue setup</a>
        </article>
        <article>
          <span>First campaign</span>
          <strong><?= mg_e((string)($selectedCampaign['title'] ?? 'Not selected')) ?></strong>
          <?php if ($selectedCampaign): ?><a href="/merchant-creator-campaign-builder.php?campaign=<?= rawurlencode((string)$selectedCampaign['public_id']) ?>">Open campaign builder</a><?php else: ?><a href="#onboarding-step-7">Create or select</a><?php endif; ?>
        </article>
        <article>
          <span>Exposure ceiling</span>
          <strong><?= mg_e($money((int)($financialExposure['configured_campaign_ceiling_minor'] ?? 0), (string)($financialExposure['currency'] ?? 'USD'))) ?></strong>
          <small>Merchant-configured ceiling; native campaign budget remains authoritative.</small>
        </article>
        <article>
          <span>Latest smoke test</span>
          <strong><?= $latestSmoke ? mg_e(ucfirst((string)$latestSmoke['status'])) . ' · ' . (int)$latestSmoke['score'] . '%' : 'Not run' ?></strong>
          <a href="#onboarding-step-8">Open launch test</a>
        </article>
      </section>

<?php require __DIR__ . '/page-view-foundation.php'; ?>
<?php require __DIR__ . '/page-view-guardrails.php'; ?>
<?php require __DIR__ . '/page-view-launch.php'; ?>
    <?php endif; ?>
  </main>
</section>
