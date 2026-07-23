<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/creator-campaign-pilot.php';

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

$schemaReady = mg_creator_campaign_pilot_schema_ready($pdo);
$pilot = null;
if ($schemaReady) {
    try {
        $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    } catch (MgCreatorCampaignPilotException $error) {
        $errorMessage = $error->getMessage();
    }
} else {
    $errorMessage = 'Import the Phase 14 SQL before using the Creator Campaign production pilot workspace.';
}

$loadState = static function () use ($pdo, $user, $workspace): array {
    $connections = mg_creator_campaign_pilot_connections($pdo, (int)$user['id'], (int)$workspace['id']);
    $grants = mg_creator_campaign_pilot_grants($pdo, (int)$user['id'], (int)$workspace['id']);
    $definitions = mg_creator_campaign_pilot_definitions($pdo, (int)$user['id'], (int)$workspace['id']);
    $runs = mg_creator_campaign_pilot_runs($pdo, (int)$user['id'], (int)$workspace['id']);
    $artifacts = mg_creator_campaign_pilot_artifacts($pdo, (int)$user['id'], (int)$workspace['id']);
    $actionGrants = mg_creator_campaign_pilot_action_grants($pdo, (int)$user['id'], (int)$workspace['id']);
    return compact('connections','grants','definitions','runs','artifacts','actionGrants');
};

if ($schemaReady && $pilot && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgCreatorCampaignPilotException(
                'Your session expired. Refresh the page and try again.',
                419,
                'CREATOR_CAMPAIGN_PILOT_CSRF_FAILED'
            );
        }
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        if ($action === 'save_profile') {
            mg_creator_campaign_pilot_save_profile($pdo, $user, $workspace, $_POST);
            $notice = 'Pilot checklist and support coverage updated.';
        } elseif ($action === 'transition') {
            $state = $loadState();
            $currentPilot = mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
            $readiness = mg_creator_campaign_pilot_readiness(
                $currentPilot,
                $state['connections'],
                $state['grants'],
                $state['definitions'],
                $state['runs'],
                $state['artifacts'],
                $state['actionGrants']
            );
            $result = mg_creator_campaign_pilot_transition(
                $pdo,
                $user,
                $workspace,
                (string)($_POST['transition'] ?? ''),
                $readiness
            );
            $notice = 'Pilot status updated to ' . (string)$result['status'] . '.';
        } elseif ($action === 'emergency_disable') {
            mg_creator_campaign_pilot_emergency_disable(
                $pdo,
                $user,
                $workspace,
                (string)($_POST['reason'] ?? '')
            );
            $notice = 'Emergency stop activated. New Phase 13D runs are blocked and bounded grants and definitions were paused.';
        } elseif ($action === 'emergency_clear') {
            mg_creator_campaign_pilot_emergency_clear(
                $pdo,
                $user,
                $workspace,
                (string)($_POST['reason'] ?? '')
            );
            $notice = 'Emergency stop cleared. Grants and definitions remain paused for individual owner review.';
        } elseif ($action === 'pause_definition') {
            mg_creator_campaign_pilot_pause_definition(
                $pdo,
                $user,
                $workspace,
                (string)($_POST['automation_id'] ?? ''),
                (string)($_POST['reason'] ?? '')
            );
            $notice = 'Playbook definition paused and active work was cancellation-requested.';
        } elseif ($action === 'acknowledge_run') {
            mg_creator_campaign_pilot_acknowledge_run(
                $pdo,
                $user,
                $workspace,
                (string)($_POST['run_id'] ?? ''),
                (string)($_POST['resolution'] ?? ''),
                (string)($_POST['note'] ?? '')
            );
            $notice = 'Run recovery decision recorded. No automatic retry was started.';
        } elseif ($action === 'prepare_action_request') {
            $currentPilot = mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
            $result = mg_creator_campaign_pilot_prepare_action_request(
                $pdo,
                $user,
                $workspace,
                $currentPilot,
                (string)($_POST['draft_id'] ?? ''),
                (string)($_POST['grant_id'] ?? ''),
                (string)($_POST['tool_name'] ?? ''),
                (string)($_POST['action_input_json'] ?? ''),
                (string)($_POST['requested_reason'] ?? '')
            );
            $notice = 'Phase 13C action request created. It is waiting for separate owner approval and execution.';
            header(
                'Location: /account-creator-campaign-actions.php?notice='
                . rawurlencode($notice)
                . '#action-' . rawurlencode((string)$result['id']),
                true,
                303
            );
            exit;
        } else {
            throw new MgCreatorCampaignPilotException('Unknown pilot operator action.');
        }
        header(
            'Location: /account-creator-campaign-pilot.php?notice=' . rawurlencode($notice),
            true,
            303
        );
        exit;
    } catch (MgCreatorCampaignPilotException | MgMcpCreatorCampaignActionException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        mg_security_log(
            'error',
            'creator_campaign.pilot.owner_action_failed',
            'Creator Campaign pilot owner action failed.',
            [
                'exception_class'=>$error::class,
                'exception_message'=>mb_substr($error->getMessage(),0,500),
                'action'=>(string)($_POST['action'] ?? ''),
            ],
            (int)$user['id']
        );
        $errorMessage = 'The pilot operator action could not be completed.';
    }
}

if (isset($_GET['notice'])) {
    $notice = mb_substr(trim((string)$_GET['notice']), 0, 500);
}

$connections = $grants = $definitions = $runs = $artifacts = $actionGrants = [];
$readiness = ['score'=>0,'completed'=>0,'total'=>8,'start_ready'=>false,'pilot_validated'=>false,'steps'=>[],'counts'=>[]];
$issues = $securityEvents = $operatorEvents = $handoffs = [];
$playbookCards = [];
if ($schemaReady && $pilot) {
    try {
        $state = $loadState();
        extract($state, EXTR_OVERWRITE);
        $refresh = mg_creator_campaign_pilot_refresh_snapshot(
            $pdo,
            $user,
            $workspace,
            $pilot,
            $connections,
            $grants,
            $definitions,
            $runs,
            $artifacts,
            $actionGrants
        );
        $pilot = $refresh['pilot'];
        $readiness = $refresh['readiness'];
        $issues = $refresh['issues'];
        $securityEvents = mg_creator_campaign_pilot_security_events($pdo, (int)$user['id'], (int)$workspace['id']);
        $operatorEvents = mg_creator_campaign_pilot_events($pdo, (int)$pilot['id']);
        $handoffs = mg_creator_campaign_pilot_handoffs($pdo, (int)$pilot['id']);

        $catalog = mg_creator_campaign_pilot_playbook_catalog();
        foreach ($catalog as $key => $meta) {
            $matchingDefinitions = array_values(array_filter(
                $definitions,
                static fn(array $definition): bool => (string)$definition['playbook_key'] === $key
            ));
            $matchingRuns = array_values(array_filter(
                $runs,
                static fn(array $run): bool => (string)$run['playbook_key'] === $key
            ));
            $playbookCards[$key] = $meta + [
                'definitions'=>$matchingDefinitions,
                'latest_run'=>$matchingRuns[0] ?? null,
                'active'=>count(array_filter($matchingDefinitions, static fn(array $definition): bool =>
                    (string)$definition['status'] === 'active'
                    && (string)$definition['trigger_status'] === 'active'
                    && (string)$definition['grant_status'] === 'active'
                )) > 0,
            ];
        }
        foreach ($artifacts as &$artifact) {
            $artifact['action_options'] = mg_creator_campaign_pilot_action_options($artifact);
        }
        unset($artifact);
    } catch (Throwable $error) {
        if ($errorMessage === '') {
            $errorMessage = 'Pilot telemetry could not be loaded: ' . mb_substr($error->getMessage(), 0, 300);
        }
    }
}

$page_title = 'Creator Campaign Production Pilot | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'creator-campaign-pilot';
$can_merchant_nav = true;
$page_body_class = 'mg-creator-campaign-pilot-page';
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/creator-campaign-pilot.css?v=20260722-phase14',
    '/assets/css/creator-campaign-pilot-components.css?v=20260722-phase14',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/creator-campaign-pilot/page-view.php';
require __DIR__ . '/includes/footer.php';
