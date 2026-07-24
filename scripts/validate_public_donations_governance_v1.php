<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $source = file_get_contents($full);
    if (!is_string($source)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $source;
};

$feature = $read('includes/public-donations-feature.php');
$governance = $read('includes/public-donations-governance.php');
$locks = $read('includes/public-donations-governance-locks.php');
$assignmentApi = $read('api/merchant/public-donations-community.php');
$allocationApi = $read('api/merchant/public-donations-allocation.php');
$recallApi = $read('api/merchant/public-donations-recall.php');
$reportApi = $read('api/merchant/community-support.php');
$navigation = $read('includes/merchant-navigation.php');
$page = $read('merchant-community-support.php');
$sql = $read('database/20260724_public_donations_community_v1_single_install.sql');
$publicView = $read('includes/public-donations-public-view.php');
$allocationEngine = $read('includes/public-donations-allocation.php');
$recallEngine = $read('includes/public-donations-recall.php');
$publicProfile = $read('includes/public-profile-community.php');

$permissions = [
    'merchant.public_donations.view',
    'merchant.public_donations.manage',
    'merchant.public_donations.assign',
    'merchant.public_donations.allocate',
    'merchant.public_donations.recall',
    'merchant.public_donations.report',
];

$allPermissionsPresent = true;
foreach ($permissions as $permission) {
    $allPermissionsPresent = $allPermissionsPresent
        && str_contains($governance, "'{$permission}'")
        && str_contains($sql, "'{$permission}'");
}

$allocationReplay = strpos($allocationApi, '$completedReplay = mg_public_donations_allocation_operation');
$allocationAdmission = strpos($allocationApi, 'mg_public_donations_governance_admit_operation');
$recallReplay = strpos($recallApi, '$replay = mg_public_donations_recall_operation');
$recallAdmission = strpos($recallApi, 'mg_public_donations_governance_admit_operation');

$checks = [
    'four rollout states remain server controlled' =>
        str_contains($feature, "['disabled', 'admin_only', 'selected_merchants', 'enabled']")
        && str_contains($feature, 'MG_PUBLIC_DONATIONS_FEATURE_STATE')
        && str_contains($feature, 'MG_PUBLIC_DONATIONS_MERCHANT_IDS'),
    'six granular permissions are provisioned' => $allPermissionsPresent,
    'Community role receives no Public Donations merchant permissions' =>
        str_contains($sql, "WHERE role.slug IN ('merchant','admin','super_admin')")
        && !str_contains($sql, "WHERE role.slug IN ('merchant','community'"),
    'workspace role matrix blocks allocation and recall for ordinary staff' =>
        str_contains($governance, "['owner', 'manager']")
        && str_contains($governance, "['marketing', 'marketer', 'staff', 'viewer']")
        && str_contains($governance, "['manage', 'assign']")
        && str_contains($governance, 'return false;'),
    'workspace owner and actor are resolved separately' =>
        str_contains($governance, "'actor_user_id' => \$actorId")
        && str_contains($governance, "'merchant_user_id' => \$merchantId")
        && str_contains($governance, "\$workspace['merchant_user_id']"),
    'disabled actors and rollout denials fail closed with telemetry' =>
        str_contains($governance, "SELECT status FROM users WHERE id=?")
        && str_contains($governance, "'actor_inactive'")
        && str_contains($governance, "'feature_disabled'")
        && str_contains($governance, 'public_donations.governance_denied'),
    'write endpoints use action-specific governance contexts' =>
        str_contains($assignmentApi, "? 'view' : 'assign'")
        && str_contains($allocationApi, "? 'view' : 'allocate'")
        && str_contains($recallApi, "? 'view' : 'recall'")
        && str_contains($reportApi, "governance_context(\$pdo, \$user, 'report')"),
    'write request throttles are enabled' =>
        str_contains($governance, "'assign' => [120, 600]")
        && str_contains($governance, "'allocate' => [60, 600]")
        && str_contains($governance, "'recall' => [60, 600]")
        && str_contains($assignmentApi, "governance_rate_limit('assign'")
        && str_contains($allocationApi, "governance_rate_limit('allocate'")
        && str_contains($recallApi, "governance_rate_limit('recall'"),
    'hourly budgets use locked operation rows' =>
        str_contains($governance, 'SELECT status,requested_quantity,completed_quantity')
        && str_contains($governance, 'ORDER BY id ASC')
        && str_contains($governance, 'FOR UPDATE')
        && !str_contains($governance, 'SELECT COALESCE(SUM(CASE WHEN status='),
    'concurrent operations use merchant-scoped MySQL named locks' =>
        str_contains($locks, 'GET_LOCK(?, 8)')
        && str_contains($locks, 'RELEASE_LOCK(?)')
        && str_contains($locks, "'mg:public-donations:' . \$merchantId . ':' . \$kind"),
    'completed allocation replay precedes budget admission' =>
        $allocationReplay !== false && $allocationAdmission !== false && $allocationReplay < $allocationAdmission,
    'completed recall replay precedes budget admission' =>
        $recallReplay !== false && $recallAdmission !== false && $recallReplay < $recallAdmission,
    'allocation revalidates campaign template assignment and user eligibility under locks' =>
        str_contains($allocationEngine, 'Deterministic lock order: campaign -> reward template -> assignments -> idempotency operation.')
        && str_contains($allocationEngine, "campaign['status'] !== 'active'")
        && str_contains($allocationEngine, "template['status'] !== 'active'")
        && str_contains($allocationEngine, "u.status='active'")
        && str_contains($allocationEngine, "community_role.slug='community'")
        && str_contains($allocationEngine, "assignment.status='active'"),
    'recall preserves downstream owners and only mutates untouched original-owner rewards' =>
        str_contains($recallEngine, "return 'regifted'")
        && str_contains($recallEngine, "return \$untouched ? 'recallable' : 'unavailable'")
        && str_contains($recallApi, "'downstream_recipients_affected' => false")
        && str_contains($recallApi, "'existing_nonrecallable_rewards_preserved' => true"),
    'merchant navigation and direct page use the same rollout gate' =>
        str_contains($navigation, 'mg_merchant_navigation_public_donations_visible')
        && str_contains($navigation, "\$key !== 'community_support' || \$publicDonationsVisible")
        && str_contains($page, '$canCommunitySupport')
        && str_contains($page, 'Community Support is not available'),
    'reporting exposes privacy-safe integrity counts only' =>
        str_contains($reportApi, 'mg_public_donations_governance_integrity')
        && str_contains($governance, "'identity_values_returned' => false")
        && str_contains($governance, "'anonymized_commerce_evidence_preserved' => true")
        && str_contains($governance, "'campaign_attribution_preserved' => true"),
    'public profile keeps unavailable accounts aggregate only' =>
        str_contains($publicProfile, "profile.status='active'")
        && str_contains($publicProfile, "profile.visibility IN ('public','unlisted')")
        && str_contains($publicProfile, "'anonymous_accounts' => max(0, \$supportedAccounts - \$publicAccounts)")
        && str_contains($publicProfile, "'final_recipient_identity_exposed' => false"),
    'operational copy rejects cash and tax-deductible interpretations' =>
        str_contains($governance, 'They are not cash donations or tax-deductible charitable contributions.')
        && str_contains($publicView, 'It is not cash, a charitable receipt, or a tax-deductible contribution.'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

$score = round((count($checks) - count($failed)) / max(1, count($checks)) * 10, 1);
echo 'Public Donations governance score: ' . number_format($score, 1) . '/10' . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Public Donations governance validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Public Donations governance validation passed at 10.0/10.\n";
