<?php
declare(strict_types=1);

function mg_creator_campaign_builder_validation(PDO $pdo, array $campaign): array
{
    $campaignId = (int) ($campaign['id'] ?? 0);
    $products = array_key_exists('products', $campaign) ? $campaign['products'] : mg_creator_campaign_repository_products($pdo, $campaignId);
    $rules = array_key_exists('eligibility_rules', $campaign) ? $campaign['eligibility_rules'] : mg_creator_campaign_repository_eligibility_rules($pdo, $campaignId);
    $questions = mg_creator_campaign_builder_table_exists($pdo, 'creator_campaign_application_questions')
        ? mg_creator_campaign_builder_questions($pdo, $campaignId)
        : [];
    $checks = [];
    $add = static function (int $step, string $key, string $label, string $status, string $message) use (&$checks): void {
        $checks[] = compact('step', 'key', 'label', 'status', 'message');
    };

    $add(1, 'title', 'Campaign name', trim((string) ($campaign['title'] ?? '')) !== '' ? 'pass' : 'fail', 'A clear campaign name is required.');
    $add(1, 'objective', 'Objective', trim((string) ($campaign['objective'] ?? '')) !== '' ? 'pass' : 'fail', 'Select the primary campaign objective.');
    $add(1, 'description', 'Description', trim((string) ($campaign['description'] ?? '')) !== '' ? 'pass' : 'fail', 'Add the creator-facing campaign description.');
    $add(1, 'category', 'Category', trim((string) ($campaign['category'] ?? '')) !== '' ? 'pass' : 'fail', 'Choose a category.');
    $datesValid = empty($campaign['starts_at']) || empty($campaign['ends_at']) || (string) $campaign['ends_at'] > (string) $campaign['starts_at'];
    $add(1, 'dates', 'Campaign dates', $datesValid ? 'pass' : 'fail', 'The end time must be later than the start time.');
    $add(1, 'manager', 'Campaign manager', !empty($campaign['campaign_manager_user_id']) ? 'pass' : 'warning', 'Workspace owner will manage the campaign when no manager is selected.');

    $focus = (string) ($campaign['campaign_focus'] ?? 'general_brand_campaign');
    $nonExcluded = array_values(array_filter($products, static fn(array $row): bool => (string) ($row['relationship_type'] ?? '') !== 'excluded'));
    $primary = array_values(array_filter($products, static fn(array $row): bool => (string) ($row['relationship_type'] ?? '') === 'primary'));
    $productRequired = in_array($focus, ['single_product', 'multiple_products', 'product_collection', 'microgift_offer', 'reward'], true);
    $offerReady = count($nonExcluded) > 0 || !empty($campaign['featured_reward_template_id']);
    $add(2, 'focus', 'Campaign focus', in_array($focus, mg_creator_campaign_focuses(), true) ? 'pass' : 'fail', 'Choose what the campaign promotes.');
    $add(2, 'products_or_offer', 'Products or offer', !$productRequired || $offerReady ? 'pass' : 'fail', 'This campaign focus requires at least one product or reward offer.');
    $add(2, 'primary_product', 'Primary product', $focus !== 'single_product' || count($primary) === 1 ? 'pass' : 'fail', 'Single-product campaigns require exactly one primary product.');
    $add(2, 'landing_destination', 'Creator landing destination', !empty($campaign['creator_landing_url']) ? 'pass' : 'warning', 'A destination can be added before creator activation.');

    $accessMode = (string) ($campaign['access_mode'] ?? 'open');
    $add(3, 'access_mode', 'Participation method', in_array($accessMode, mg_creator_campaign_access_modes(), true) ? 'pass' : 'fail', 'Choose how creators enter the campaign.');
    $add(3, 'creator_limit', 'Creator limit', empty($campaign['maximum_approved_creators']) || (int) $campaign['maximum_approved_creators'] > 0 ? 'pass' : 'fail', 'Creator limit must be positive.');
    $add(3, 'approval_rule', 'Merchant approval', empty($campaign['automatic_acceptance']) ? 'pass' : 'warning', 'Automatic acceptance remains inactive until the participation service is installed.');
    $add(3, 'eligibility_rules', 'Eligibility rules', count($rules) > 0 ? 'pass' : 'warning', 'No eligibility filters means every approved Creator may qualify.');
    $add(3, 'application_questions', 'Application questions', count($questions) > 0 ? 'pass' : 'warning', 'Application questions are optional.');

    $dependencies = [
        4 => ['creator_campaign_deliverables', 'Deliverables'],
        5 => ['creator_campaign_compensation_rules', 'Compensation'],
        6 => ['creator_campaign_tracking_sources', 'Attribution'],
        7 => ['creator_campaign_budget_ledger', 'Budget'],
        8 => ['creator_campaign_agreement_versions', 'Content rights'],
        9 => ['creator_campaign_agreement_versions', 'Terms and disclosures'],
    ];
    foreach ($dependencies as $step => [$table, $label]) {
        $installed = mg_creator_campaign_builder_table_exists($pdo, $table);
        $add($step, 'dependency_' . $step, $label, $installed ? 'pass' : 'blocked', $installed ? $label . ' service is installed.' : $label . ' is assigned to a later approved phase.');
    }

    $phase2Relevant = array_values(array_filter($checks, static fn(array $check): bool => (int) $check['step'] <= 3));
    $phase2Failures = array_values(array_filter($phase2Relevant, static fn(array $check): bool => $check['status'] === 'fail'));
    $phase2Passes = count(array_filter($phase2Relevant, static fn(array $check): bool => in_array($check['status'], ['pass', 'warning'], true)));
    $phase2Score = $phase2Relevant === [] ? 0 : (int) round(100 * $phase2Passes / count($phase2Relevant));
    $fullBlockers = array_values(array_filter($checks, static fn(array $check): bool => in_array($check['status'], ['fail', 'blocked'], true)));
    $agreementInstalled = mg_creator_campaign_builder_table_exists($pdo, 'creator_campaign_agreement_versions');

    return [
        'phase2_ready' => $phase2Failures === [],
        'phase2_score' => $phase2Score,
        'publish_ready' => $phase2Failures === [] && $agreementInstalled && $fullBlockers === [],
        'full_system_score' => (int) round(100 * count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'pass')) / max(1, count($checks))),
        'checks' => $checks,
        'blockers' => $fullBlockers,
        'generated_at' => gmdate('c'),
    ];
}

function mg_creator_campaign_builder_validate_campaign(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    bool $persist = false
): array {
    $resolved = mg_creator_campaign_builder_resolve_campaign($pdo, $user, $campaignPublicId);
    $campaign = mg_creator_campaign_repository_hydrate($pdo, $resolved['campaign']);
    $validation = mg_creator_campaign_builder_validation($pdo, $campaign);
    if ($persist) {
        $pdo->prepare('UPDATE creator_campaigns SET builder_validation_json=? WHERE id=?')->execute([
            mg_creator_campaign_json_encode($validation), (int) $campaign['id'],
        ]);
    }
    return $validation;
}
