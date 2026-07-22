<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$actorUserId = (int) ($user['id'] ?? 0);

try {
    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'discover')));
        $data = match ($action) {
            'discover' => mg_creator_campaign_discover_creator($pdo, $user, $_GET),
            'detail' => mg_creator_campaign_detail_creator(
                $pdo, $user, (string) ($_GET['campaign_id'] ?? '')
            ),
            'applications' => mg_creator_campaign_application_list_creator($pdo, $user),
            'invitations' => mg_creator_campaign_invitation_list_creator($pdo, $user),
            'participants' => mg_creator_campaign_participant_list_creator($pdo, $user),
            'active_campaigns' => mg_creator_campaign_active_workspace_creator($pdo, $user),
            'agreements' => mg_creator_campaign_agreement_list_creator($pdo, $user),
            'agreement_detail' => mg_creator_campaign_agreement_detail_creator(
                $pdo, $user, (string) ($_GET['agreement_id'] ?? '')
            ),
            default => throw new RuntimeException('Creator campaign route not found.'),
        };
        mg_ok($data);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    $data = match ($action) {
        'save_application' => mg_creator_campaign_application_save_creator(
            $pdo, $user, (string) ($input['campaign_id'] ?? ''), $input, false
        ),
        'submit_application' => mg_creator_campaign_application_save_creator(
            $pdo, $user, (string) ($input['campaign_id'] ?? ''), $input, true
        ),
        'withdraw_application' => mg_creator_campaign_application_withdraw_creator(
            $pdo, $user, (string) ($input['application_id'] ?? ''), $input
        ),
        'respond_invitation' => mg_creator_campaign_invitation_respond_creator(
            $pdo,
            $user,
            (string) ($input['invitation_id'] ?? ''),
            (string) ($input['response'] ?? ''),
            $input
        ),
        'respond_agreement' => mg_creator_campaign_agreement_respond_creator(
            $pdo,
            $user,
            (string) ($input['agreement_id'] ?? ''),
            (string) ($input['decision'] ?? ''),
            $input
        ),
        default => throw new InvalidArgumentException('Creator campaign action is invalid.'),
    };
    mg_ok($data, 'Creator campaign participation updated.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 409);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    $status = str_contains($message, 'schema is incomplete') ? 503 : (str_contains($message, 'not found') ? 404 : 409);
    mg_fail($error->getMessage(), $status);
} catch (PDOException $error) {
    if ((string) $error->getCode() === '23000') {
        mg_fail('This creator already has a participation record for the campaign.', 409);
    }
    mg_fail_unexpected(
        $error,
        'creator.campaign_participation.database_failure',
        'Unable to update campaign participation because of a database error.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'creator.campaign_participation.failure',
        'Unable to process the creator campaign request.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
}
