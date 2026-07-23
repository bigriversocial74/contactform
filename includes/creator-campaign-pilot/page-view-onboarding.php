<?php
declare(strict_types=1);

$phase15Summary = null;
$phase15SchemaReady = false;
try {
    require_once dirname(__DIR__) . '/creator-campaign-onboarding.php';
    $phase15SchemaReady = mg_creator_campaign_onboarding_schema_ready($pdo);
    if ($phase15SchemaReady) {
        $phase15Summary = mg_creator_campaign_onboarding_summary(
            $pdo,
            (int)$user['id'],
            (int)$workspace['id']
        );
    }
} catch (Throwable) {
    $phase15Summary = null;
}
$phase15Score = (int)($phase15Summary['readiness']['score'] ?? 0);
$phase15Completed = (int)($phase15Summary['readiness']['completed'] ?? 0);
?>
<link rel="stylesheet" href="/assets/css/creator-campaign-onboarding.css?v=20260723-phase15">
<article class="mg-pilot-onboarding-card">
  <div>
    <span>Phase 15 · Native merchant launch</span>
    <strong><?= $phase15Summary ? mg_e((string)$phase15Summary['status_label']) . ' · ' . $phase15Score . '%' : 'Merchant onboarding' ?></strong>
    <small><?= $phase15Summary
      ? $phase15Completed . ' of 9 launch steps complete. MCP authority remains separate.'
      : ($phase15SchemaReady ? 'Open the onboarding workspace to enroll this merchant.' : 'Import the Phase 15 onboarding SQL to begin.') ?></small>
  </div>
  <a href="/account-creator-campaign-onboarding.php"><?= $phase15Summary ? 'Continue onboarding' : 'Open onboarding' ?></a>
</article>
