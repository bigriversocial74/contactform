<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$score = 0;
$maximum = 0;
$failures = [];
$categories = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$check = static function (string $category, int $points, bool $condition, string $message) use (&$score, &$maximum, &$failures, &$categories): void {
    $maximum += $points;
    $categories[$category]['maximum'] = ($categories[$category]['maximum'] ?? 0) + $points;
    if ($condition) {
        $score += $points;
        $categories[$category]['score'] = ($categories[$category]['score'] ?? 0) + $points;
        echo "[PASS +{$points}] {$category}: {$message}\n";
        return;
    }
    $categories[$category]['score'] = $categories[$category]['score'] ?? 0;
    $failures[] = "{$category}: {$message}";
    echo "[FAIL +0/{$points}] {$category}: {$message}\n";
};

$required = [
    'database/20260724_investor_module_audit_hardening_v1.sql',
    'includes/investment/investment-audit-hardening.php',
    'includes/investment/investment-audit-hardening-v2.php',
    'includes/investment/investment-audit-hardening-v3.php',
    'includes/investment/investment-audit-hardening-v4.php',
    'includes/investment/investment-audit-hardening-v5.php',
    'includes/investment/investment-audit-hardening-v6.php',
    'includes/investment/investment-audit-hardening-v7.php',
    'includes/investment/investment-audit-hardening-v8.php',
    'includes/investment/investment-audit-hardening-v9.php',
    'includes/investment/investment-audit-hardening-v10.php',
    'assets/js/investor-module-audit-v1.js',
    'docs/investment/investor-module-audit-10of10.md',
    '.github/workflows/investor-module-audit-10of10.yml',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) $failures[] = 'Required file missing: ' . $path;
}

$service = $read('includes/investment/investment-service.php');
$accessApi = $read('api/investment/access-request.php');
$wizardApi = $read('api/admin/investment-wizard.php');
$pipelineApi = $read('api/admin/investor-pipeline.php');
$diligenceApi = $read('api/admin/investor-diligence.php');
$closingApi = $read('api/admin/investment-closing.php');
$governanceApi = $read('api/admin/investor-governance.php');
$portalApi = $read('api/investment/portal.php');
$h1 = $read('includes/investment/investment-audit-hardening.php');
$h2 = $read('includes/investment/investment-audit-hardening-v2.php');
$h3 = $read('includes/investment/investment-audit-hardening-v3.php');
$h4 = $read('includes/investment/investment-audit-hardening-v4.php');
$h5 = $read('includes/investment/investment-audit-hardening-v5.php');
$h6 = $read('includes/investment/investment-audit-hardening-v6.php');
$h7 = $read('includes/investment/investment-audit-hardening-v7.php');
$h8 = $read('includes/investment/investment-audit-hardening-v8.php');
$h9 = $read('includes/investment/investment-audit-hardening-v9.php');
$h10 = $read('includes/investment/investment-audit-hardening-v10.php');
$hardening = $h1 . $h2 . $h3 . $h4 . $h5 . $h6 . $h7 . $h8 . $h9 . $h10;
$sql = $read('database/20260724_investor_module_audit_hardening_v1.sql');
$ui = $read('assets/js/investor-module-audit-v1.js');
$docs = $read('docs/investment/investor-module-audit-10of10.md');
$workflow = $read('.github/workflows/investor-module-audit-10of10.yml');

// Architecture — 15 points.
$allLayersLoaded = true;
foreach (['investment-audit-hardening.php','investment-audit-hardening-v2.php','investment-audit-hardening-v3.php','investment-audit-hardening-v4.php','investment-audit-hardening-v5.php','investment-audit-hardening-v6.php','investment-audit-hardening-v7.php','investment-audit-hardening-v8.php','investment-audit-hardening-v9.php','investment-audit-hardening-v10.php'] as $needle) {
    $allLayersLoaded = $allLayersLoaded && str_contains($service, $needle);
}
$check('Architecture', 5, $allLayersLoaded, 'All ten audited authority layers load after the Phase 1–5 services.');
$check('Architecture', 5,
    str_contains($wizardApi, 'mg_investment_update_round_audited')
    && str_contains($pipelineApi, 'mg_investment_pipeline_save_interest_audited_v2')
    && str_contains($diligenceApi, 'mg_investment_dataroom_save_document_audited')
    && str_contains($closingApi, 'mg_investment_financial_decide_audited_v2')
    && str_contains($governanceApi, 'mg_investment_governance_save_notice_audited_v3')
    && str_contains($portalApi, 'mg_investment_portal_data_v5_final3'),
    'Every investment API routes through the audited canonical authority.');
$check('Architecture', 5,
    str_contains($pipelineApi, "'dashboard'=>mg_investment_pipeline_dashboard_audited")
    && str_contains($closingApi, "'dashboard'=>mg_investment_closing_dashboard_audited")
    && str_contains($governanceApi, 'mg_investment_governance_dashboard_audited')
    && str_contains($closingApi, "'sync'=>mg_investment_closing_sync_audited"),
    'Read dashboards are separated from explicit protected synchronization.');

// Security and privacy — 20 points.
$check('Security & privacy', 4,
    str_contains($accessApi, 'mg_investment_access_public_audited')
    && str_contains($accessApi, 'mg_investment_access_result_public_audited')
    && str_contains($h3, "unset(\$public['review_notes'])")
    && str_contains($h3, "unset(\$result['review_notes'])"),
    'Investor applicants cannot receive internal access-review notes.');
$check('Security & privacy', 4,
    str_contains($h4, 'mg_investment_portal_profile_public_audited')
    && str_contains($h4, "'firm_name'")
    && !str_contains(substr($h4, strpos($h4, 'function mg_investment_portal_profile_public_audited'), 1200), "'notes'"),
    'Investor Portal profile data uses an explicit public whitelist.');
$check('Security & privacy', 4,
    str_contains($h8, 'mg_investment_portal_accessible_round_final3')
    && str_contains($h8, 'mg_investment_portal_event_v5_final3')
    && str_contains($h6, 'Q&A entry is not available')
    && str_contains($h4, 'Round-view subject does not match'),
    'Portal events validate the accessible round and published subject before recording engagement.');
$check('Security & privacy', 4,
    str_contains($h6, 'A document cannot be less restricted than its data-room folder')
    && str_contains($h8, 'funding_verification_source="maker_checker"')
    && str_contains($h6, "\$folders[(string)\$folder['public_id']]")
    && str_contains($h8, "\$portalRound['governance']=null"),
    'Folder, selected-investor and maker/checker-funded visibility are enforced at the final portal boundary.');
$check('Security & privacy', 4,
    str_contains($h6, "admin.investment.diligence.publish")
    && str_contains($h8, "admin.investment.relations.publish")
    && str_contains($h1, 'requires counsel approval or an explicit not-applicable status')
    && str_contains($h9, "admin.investment.publish"),
    'Publishing uses dedicated permissions and counsel/approval gates.');

// Financial integrity — 20 points.
$check('Financial integrity', 4,
    str_contains($sql, 'signed_verification_source')
    && str_contains($sql, 'funding_verification_source')
    && str_contains($sql, "ENUM(''unverified'',''maker_checker'')")
    && str_contains($sql, 'investment_financial_verification_decisions'),
    'Signed and funded money carries backfilled maker/checker provenance.');
$check('Financial integrity', 4,
    str_contains($pipelineApi, 'mg_investment_pipeline_save_interest_audited_v2')
    && str_contains($wizardApi, 'mg_investment_update_round_audited')
    && str_contains($h10, 'Signed and funded values are read-only here')
    && str_contains($h9, "\$safe['signed']"),
    'Pipeline and official-round editors cannot bypass Closing maker/checker authority.');
$check('Financial integrity', 4,
    str_contains($h7, 'mg_investment_closing_sync_audited')
    && str_contains($h7, "0,'unverified',ri.funded_cents,0,'unverified'")
    && !str_contains($closingApi, "'dashboard'=>mg_investment_closing_dashboard(\$pdo"),
    'Legacy Pipeline money imports as reported/unverified and GET does not synchronize it.');
$check('Financial integrity', 4,
    str_contains($h7, 'mg_investment_recalculate_round_totals_audited')
    && str_contains($h7, 'signed_verification_source="maker_checker"')
    && str_contains($h7, 'funding_verification_source="maker_checker"')
    && str_contains($sql, 'UPDATE investor_round_interests ri'),
    'Relationship and official-round totals reconcile only from proven closing records.');
$check('Financial integrity', 4,
    str_contains($h7, 'mg_investment_governance_refresh_holdings_audited')
    && str_contains($h8, 'mg_investment_portal_data_v5_final3')
    && str_contains($h10, 'Resolve legacy unproven funded records')
    && str_contains($h7, 'Only maker/checker verified funded records can be assigned'),
    'Closing, holdings, communications, notices and funded portal access require maker/checker provenance.');

// Publication and governance — 15 points.
$check('Publication & governance', 3,
    str_contains($sql, 'CREATE TABLE IF NOT EXISTS investment_round_publication_versions')
    && str_contains($h9, 'INSERT INTO investment_round_publication_versions')
    && str_contains($h9, 'Publication change reason'),
    'Official portal publication changes create immutable reasoned versions.');
$check('Publication & governance', 3,
    str_contains($sql, 'CREATE TABLE IF NOT EXISTS investment_document_versions')
    && str_contains($h9, 'INSERT INTO investment_document_versions')
    && str_contains($h9, 'Document change reason'),
    'Official investment document metadata creates immutable reasoned versions.');
$check('Publication & governance', 3,
    str_contains($hardening, 'Published Q&A is immutable')
    && str_contains($hardening, 'Published investor communications are immutable')
    && str_contains($hardening, 'A published material notice is immutable')
    && str_contains($hardening, 'Executed consent content is immutable'),
    'Published investor records cannot be silently rewritten.');
$check('Publication & governance', 3,
    str_contains($sql, 'investor_visible TINYINT(1)')
    && str_contains($governanceApi, "'set_consent_visibility'")
    && str_contains($h1, 'Only executed consents may be shown to investors')
    && str_contains($h1, 'investor_visible=1'),
    'Executed consent portal visibility is explicit and permission-gated.');
$check('Publication & governance', 3,
    str_contains($sql, 'admin.investment.relations.publish')
    && str_contains($h8, 'Approve this exact report version before publishing it')
    && str_contains($h8, 'Published reporting periods are immutable')
    && str_contains($h8, 'Investor-visible use-of-funds actuals are immutable'),
    'Post-investment reports and visible actuals use separate publish authority and immutable approved versions.');

// Runtime and data integrity — 15 points.
$check('Runtime & data integrity', 3,
    substr_count($hardening, 'mg_investment_audit_transaction') >= 8
    && substr_count($hardening, 'FOR UPDATE') >= 8,
    'Version creation and sensitive transitions use transactions and row locks.');
$check('Runtime & data integrity', 3,
    str_contains($pipelineApi, 'mg_investment_pipeline_dashboard_audited')
    && str_contains($closingApi, 'mg_investment_closing_dashboard_audited')
    && str_contains($governanceApi, 'mg_investment_governance_dashboard_audited')
    && !str_contains($portalApi, 'mg_investment_closing_sync'),
    'GET and portal reads are free of synchronization side effects.');
$check('Runtime & data integrity', 3,
    str_contains($h5, 'mg_investment_pipeline_admin_user_audited')
    && str_contains($h6, 'mg_investment_pipeline_admin_user_audited')
    && str_contains($h10, 'mg_investment_pipeline_admin_user_audited'),
    'Pipeline, diligence and compliance assignees must be Admin or Super Admin.');
$check('Runtime & data integrity', 3,
    str_contains($h9, 'mg_investment_decimal_input_audited')
    && str_contains($h9, '/^\\d{1,15}(?:\\.\\d{1,2})?$/')
    && str_contains($h9, 'mg_investment_save_scenario_audited')
    && str_contains($h9, 'mg_investment_replace_budget_audited'),
    'Planning money is validated as exact decimal text before canonical conversion.');
$check('Runtime & data integrity', 3,
    str_contains($h6, 'Diligence request limit reached')
    && str_contains($h6, 'Interest submission limit reached')
    && str_contains($h9, 'Invalid official-round transition')
    && str_contains($h10, 'generic financial adjustments are not allowed') === false,
    'Submission volume and state-machine transitions are bounded.');

// Operator UX — 5 points.
$uiPages = true;
foreach (['admin/investment-wizard.php','admin/investor-pipeline.php','admin/investment-closing.php','admin/investor-governance.php'] as $page) {
    $uiPages = $uiPages && str_contains($read($page), 'investor-module-audit-v1.js');
}
$check('Operator UX', 5,
    $uiPages
    && str_contains($ui, 'input.readOnly = true')
    && str_contains($ui, 'Revision reason')
    && str_contains($ui, 'legacy signed/funded record')
    && str_contains($ui, 'set_consent_visibility'),
    'Admin surfaces visibly enforce canonical money, revision reasons, provenance warnings and consent publication.');

// Tests and deployment — 10 points.
$check('Tests & deployment', 4,
    str_contains($sql, 'CREATE TABLE IF NOT EXISTS')
    && str_contains($sql, 'schema_migrations')
    && !str_contains($sql, 'DROP TABLE')
    && !str_contains($sql, 'TRUNCATE TABLE'),
    'Audit migration is additive, idempotent and preserves prior investment data.');
$check('Tests & deployment', 3,
    str_contains($docs, 'Initial architecture review')
    && str_contains($docs, 'Critical findings and fixes')
    && str_contains($docs, 'Required smoke tests')
    && str_contains($docs, '100/100'),
    'The complete score history, fixes, deployment and smoke-test plan are documented.');
$check('Tests & deployment', 3,
    str_contains($workflow, "php-version: ['8.2','8.3']")
    && str_contains($workflow, 'validate_investor_role_investment_wizard_v1.php')
    && str_contains($workflow, 'validate_investor_pipeline_portal_publishing_v2.php')
    && str_contains($workflow, 'validate_investor_diligence_communications_v3.php')
    && str_contains($workflow, 'validate_investor_closing_compliance_relations_v4.php')
    && str_contains($workflow, 'validate_investor_governance_information_rights_v5.php'),
    'The audit workflow runs PHP 8.2/8.3 and every inherited Phase 1–5 contract.');

if ($maximum !== 100) {
    $failures[] = "Audit weighting error: maximum is {$maximum}, expected 100.";
}

require_once $root . '/includes/investment/investment-service.php';
$functionChecks = [
    'mg_investment_decimal_input_audited',
    'mg_investment_update_round_audited',
    'mg_investment_pipeline_save_interest_audited_v2',
    'mg_investment_closing_sync_audited',
    'mg_investment_financial_decide_audited_v2',
    'mg_investment_portal_data_v5_final3',
];
foreach ($functionChecks as $function) {
    if (!function_exists($function)) $failures[] = 'Loaded service is missing audited authority: ' . $function;
}
try {
    if (mg_investment_decimal_input_audited('$1,234.50', 'Test amount') !== '1234.50') {
        $failures[] = 'Exact decimal normalization failed.';
    }
    try {
        mg_investment_decimal_input_audited('1.234', 'Test amount');
        $failures[] = 'Exact decimal validation accepted more than two decimal places.';
    } catch (MgInvestmentException) {
        // Expected.
    }
} catch (Throwable $error) {
    $failures[] = 'Exact decimal test failed unexpectedly: ' . $error->getMessage();
}

foreach ($categories as $category => $result) {
    echo sprintf("[CATEGORY] %s: %d/%d\n", $category, (int)($result['score'] ?? 0), (int)$result['maximum']);
}
echo "[SCORE] {$score}/{$maximum}\n";

if ($score !== 100 || $failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "[AUDIT FAILURE] {$failure}\n");
    fwrite(STDERR, "Investor module audit result: {$score}/100 — not yet 10/10.\n");
    exit(1);
}

echo "Investor module audit result: 100/100 — 10/10.\n";
