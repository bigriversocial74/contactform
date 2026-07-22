<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$actorUserId = (int) ($user['id'] ?? 0);

try {
    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'dashboard')));
        $data = match ($action) {
            'dashboard' => mg_creator_campaign_participation_dashboard_merchant($pdo, $user),
            'applications' => mg_creator_campaign_application_list_merchant($pdo, $user, $_GET),
            'application_detail' => mg_creator_campaign_application_detail_merchant(
                $pdo, $user, (string) ($_GET['application_id'] ?? '')
            ),
            'invitations' => mg_creator_campaign_invitation_list_merchant($pdo, $user, $_GET),
            'participants' => mg_creator_campaign_participant_list_merchant($pdo, $user, $_GET),
            'agreements' => mg_creator_campaign_agreement_list_merchant($pdo, $user, $_GET),
            'agreement_detail' => mg_creator_campaign_agreement_detail_merchant(
                $pdo, $user, (string) ($_GET['agreement_id'] ?? '')
            ),
            'directory' => mg_creator_campaign_creator_directory($pdo, $user, $_GET),
            'timeline' => mg_creator_campaign_participation_timeline_merchant(
                $pdo, $user, (string) ($_GET['campaign_id'] ?? '')
            ),
            default => throw new RuntimeException('Creator participation route not found.'),
        };
        mg_ok($data);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    $data = match ($action) {
        'invite' => mg_creator_campaign_invitation_create_merchant(
            $pdo, $user, (string) ($input['campaign_id'] ?? ''), $input
        ),
        'cancel_invitation' => mg_creator_campaign_invitation_cancel_merchant(
            $pdo, $user, (string) ($input['invitation_id'] ?? ''), $input
        ),
        'review_application' => mg_creator_campaign_application_review_merchant(
            $pdo,
            $user,
            (string) ($input['application_id'] ?? ''),
            (string) ($input['review_action'] ?? ''),
            $input
        ),
        'transition_participant' => mg_creator_campaign_participant_transition_merchant(
            $pdo,
            $user,
            (string) ($input['participant_id'] ?? ''),
            (string) ($input['to_status'] ?? ''),
            $input
        ),
        'offer_agreement' => mg_creator_campaign_agreement_offer_merchant(
            $pdo,
            $user,
            (string) ($input['participant_id'] ?? ''),
            $input
        ),
        default => throw new InvalidArgumentException('Creator participation action is invalid.'),
    };
    mg_ok($data, 'Creator participation updated.');
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
        mg_fail('The participation request conflicts with an existing campaign record.', 409);
    }
    mg_fail_unexpected(
        $error,
        'merchant.creator_participation.database_failure',
        'Unable to update creator participation because of a database error.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'merchant.creator_participation.failure',
        'Unable to process the creator participation request.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
}
