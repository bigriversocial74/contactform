<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/creator-campaign-onboarding.php';

$user = mg_require_auth();
$pdo = mg_db();
$notice = '';
$errorMessage = '';
$workspacePublicId = trim((string)($_REQUEST['workspace'] ?? ''));

try {
    $workspace = mg_creator_campaign_pilot_workspace($pdo, (int)$user['id'], $workspacePublicId);
} catch (MgCreatorCampaignPilotException $error) {
    http_response_code($error->httpStatus());
    exit(mg_e($error->getMessage()));
}

$pilotSchemaReady = mg_creator_campaign_pilot_schema_ready($pdo);
$onboardingSchemaReady = mg_creator_campaign_onboarding_schema_ready($pdo);
$pilot = null;
$onboarding = null;
if ($pilotSchemaReady) {
    try {
        $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
        if ($onboardingSchemaReady) {
            $onboarding = mg_creator_campaign_onboarding_ensure($pdo, $user, $workspace, $pilot);
        }
    } catch (MgCreatorCampaignPilotException | MgCreatorCampaignOnboardingException $error) {
        $errorMessage = $error->getMessage();
    }
} else {
    $errorMessage = 'Import the Phase 14 SQL before using Phase 15 merchant onboarding.';
}
if ($pilotSchemaReady && !$onboardingSchemaReady) {
    $errorMessage = 'Import the Phase 15 SQL before using Creator Campaign merchant onboarding.';
}

if ($pilot && $onboarding && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgCreatorCampaignOnboardingException(
                'Your session expired. Refresh the page and try again.',
                419,
                'CREATOR_CAMPAIGN_ONBOARDING_CSRF_FAILED'
            );
        }
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        $redirectStep = max(1, min(9, (int)($_POST['return_step'] ?? 1)));
        if ($action === 'save_enrollment') {
            mg_creator_campaign_onboarding_save_enrollment($pdo, $user, $workspace, $_POST);
            $notice = 'Pilot enrollment saved.';
            $redirectStep = 2;
        } elseif ($action === 'save_business') {
            mg_creator_campaign_onboarding_save_business($pdo, $user, $workspace, $_POST);
            $notice = 'Business and campaign defaults saved.';
            $redirectStep = 3;
        } elseif ($action === 'save_products') {
            mg_creator_campaign_onboarding_save_products($pdo, $user, $workspace, $_POST);
            $notice = 'Product readiness selection saved.';
            $redirectStep = 4;
        } elseif ($action === 'save_financials') {
            mg_creator_campaign_onboarding_save_financials($pdo, $user, $workspace, $_POST);
            $notice = 'Compensation and budget guardrails saved.';
            $redirectStep = 5;
        } elseif ($action === 'save_eligibility') {
            mg_creator_campaign_onboarding_save_eligibility($pdo, $user, $workspace, $_POST);
            $notice = 'Creator eligibility preferences saved.';
            $redirectStep = 6;
        } elseif ($action === 'save_roles') {
            mg_creator_campaign_onboarding_save_roles($pdo, $user, $workspace, $_POST);
            $notice = 'Operator and approval roles saved.';
            $redirectStep = 7;
        } elseif ($action === 'create_first_campaign') {
            mg_creator_campaign_onboarding_create_first_campaign($pdo, $user, $workspace, $onboarding, $_POST);
            $notice = 'First Creator Campaign draft created. Complete its operational workspaces before the smoke test.';
            $redirectStep = 7;
        } elseif ($action === 'select_first_campaign') {
            mg_creator_campaign_onboarding_select_first_campaign($pdo, $user, $workspace, (string)($_POST['campaign_id'] ?? ''));
            $notice = 'First Creator Campaign selected for onboarding.';
            $redirectStep = 7;
        } elseif ($action === 'run_smoke_test') {
            $result = mg_creator_campaign_onboarding_run_smoke_test($pdo, $user, $workspace, $pilot, $onboarding);
            $notice = !empty($result['passed'])
                ? 'Production smoke test passed. Merchant onboarding can now be activated.'
                : 'Production smoke test completed with blockers. Review the failed checks below.';
            $redirectStep = 8;
        } elseif ($action === 'activate_onboarding') {
            mg_creator_campaign_onboarding_activate($pdo, $user, $workspace, $pilot, $onboarding);
            $notice = 'Creator Campaign merchant onboarding activated. Campaign publication remains a separate owner action.';
            $redirectStep = 9;
        } elseif ($action === 'complete_onboarding') {
            mg_creator_campaign_onboarding_complete($pdo, $user, $workspace);
            $notice = 'Creator Campaign merchant onboarding marked complete.';
            $redirectStep = 9;
        } else {
            throw new MgCreatorCampaignOnboardingException('Unknown merchant onboarding action.');
        }
        header(
            'Location: /account-creator-campaign-onboarding.php?notice=' . rawurlencode($notice) . '#onboarding-step-' . $redirectStep,
            true,
            303
        );
        exit;
    } catch (MgCreatorCampaignOnboardingException | MgCreatorCampaignPilotException | InvalidArgumentException | DomainException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log('error', 'creator_campaign.onboarding.owner_action_failed', 'Creator Campaign merchant onboarding action failed.', [
            'action'=>(string)($_POST['action'] ?? ''),
            'exception_class'=>$error::class,
            'exception_message'=>mb_substr($error->getMessage(), 0, 800),
        ], (int)$user['id']);
        $errorMessage = 'The merchant onboarding action could not be completed.';
    }
}

if (isset($_GET['notice'])) $notice = mb_substr(trim((string)$_GET['notice']), 0, 500);

$products = $campaigns = $team = $events = $receipts = [];
$readiness = [
    'score'=>0,'completed'=>0,'total'=>9,'setup_ready'=>false,'launch_ready'=>false,
    'next_step'=>1,'steps'=>[],'selected_products'=>[],'campaign'=>[],'financial_exposure'=>[],
];
if ($pilot && $onboarding) {
    try {
        $products = mg_creator_campaign_onboarding_products($pdo, (int)$workspace['merchant_user_id']);
        $campaigns = mg_creator_campaign_onboarding_campaigns($pdo, (int)$workspace['id']);
        $team = mg_creator_campaign_onboarding_team($pdo, (int)$workspace['id'], (int)$user['id']);
        $events = mg_creator_campaign_onboarding_events($pdo, (int)$onboarding['id']);
        $receipts = mg_creator_campaign_onboarding_receipts($pdo, (int)$onboarding['id']);
        $refresh = mg_creator_campaign_onboarding_refresh_snapshot(
            $pdo,$user,$workspace,$pilot,$onboarding,$products,$campaigns,$receipts
        );
        $onboarding = $refresh['onboarding'];
        $readiness = $refresh['readiness'];
        $receipts = mg_creator_campaign_onboarding_receipts($pdo, (int)$onboarding['id']);
    } catch (Throwable $error) {
        if ($errorMessage === '') {
            $errorMessage = 'Onboarding readiness could not be loaded: ' . mb_substr($error->getMessage(), 0, 300);
        }
    }
}

$page_title = 'Creator Campaign Merchant Onboarding | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'creator-campaign-onboarding';
$can_merchant_nav = true;
$page_body_class = 'mg-creator-campaign-onboarding-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/creator-campaign-onboarding.css?v=20260723-phase15',
];
$page_scripts = ['/assets/js/creator-campaign-onboarding.js?v=20260723-phase15'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/creator-campaign-onboarding/page-view.php';
require __DIR__ . '/includes/footer.php';
