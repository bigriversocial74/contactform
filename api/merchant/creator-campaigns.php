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
        $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
        if ($action === 'list') {
            mg_ok(mg_creator_campaign_builder_list($pdo, $user, $_GET));
        }
        if ($action === 'options') {
            mg_ok(mg_creator_campaign_builder_options($pdo, $user));
        }
        $campaignPublicId = trim((string) ($_GET['campaign_id'] ?? ''));
        if ($action === 'detail') {
            $resolved = mg_creator_campaign_builder_resolve_campaign($pdo, $user, $campaignPublicId);
            $campaign = mg_creator_campaign_repository_hydrate($pdo, $resolved['campaign']);
            mg_ok(['campaign' => mg_creator_campaign_builder_present($pdo, $campaign, true)]);
        }
        if ($action === 'validate') {
            mg_ok(['validation' => mg_creator_campaign_builder_validate_campaign($pdo, $user, $campaignPublicId)]);
        }
        mg_fail('Unsupported creator campaign request.', 404);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($action === 'create') {
        $created = mg_creator_campaign_create_draft($pdo, $user, $input);
        $campaign = $created['campaign'];
        if (empty($created['idempotent_replay']) && !empty($campaign['public_id'])) {
            $input['expected_lock_version'] = (int) ($campaign['lock_version'] ?? 1);
            $campaign = mg_creator_campaign_builder_save_step(
                $pdo,
                $user,
                (string) $campaign['public_id'],
                1,
                $input
            );
        } else {
            $campaign = mg_creator_campaign_builder_present($pdo, $campaign, true);
        }
        mg_ok([
            'campaign' => $campaign,
            'idempotent_replay' => !empty($created['idempotent_replay']),
        ], 'Creator campaign draft saved.', empty($created['idempotent_replay']) ? 201 : 200);
    }

    $campaignPublicId = trim((string) ($input['campaign_id'] ?? ''));
    if ($action === 'save_step') {
        $step = (int) ($input['step'] ?? 0);
        mg_ok([
            'campaign' => mg_creator_campaign_builder_save_step($pdo, $user, $campaignPublicId, $step, $input),
        ], 'Campaign builder step saved.');
    }
    if ($action === 'duplicate') {
        mg_ok([
            'campaign' => mg_creator_campaign_builder_duplicate(
                $pdo,
                $user,
                $campaignPublicId,
                (string) ($input['idempotency_key'] ?? '')
            ),
        ], 'Campaign duplicated.', 201);
    }
    if ($action === 'transition') {
        $resolved = mg_creator_campaign_builder_resolve_campaign(
            $pdo,
            $user,
            $campaignPublicId,
            'merchant.creator_campaigns.publish'
        );
        $result = mg_creator_campaign_transition_status(
            $pdo,
            $user,
            (int) $resolved['campaign']['id'],
            (string) ($input['to_status'] ?? ''),
            [
                'expected_lock_version' => (int) ($input['expected_lock_version'] ?? 0),
                'idempotency_key' => (string) ($input['idempotency_key'] ?? ''),
                'reason' => (string) ($input['reason'] ?? ''),
                'source' => 'merchant_creator_campaign_builder',
            ]
        );
        $result['campaign'] = mg_creator_campaign_builder_present(
            $pdo,
            mg_creator_campaign_repository_hydrate($pdo, $result['campaign']),
            true
        );
        mg_ok($result, 'Campaign status updated.');
    }

    mg_fail('Unsupported creator campaign action.', 405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (PDOException $error) {
    if ((string) $error->getCode() === '23000') {
        mg_fail('A campaign with this internal reference already exists in the workspace.', 409);
    }
    mg_fail_unexpected(
        $error,
        'merchant.creator_campaigns.database_failure',
        'Unable to save the creator campaign because of a database error.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 409);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    $status = str_contains($message, 'schema is incomplete') ? 503 : (str_contains($message, 'not found') ? 404 : 409);
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'merchant.creator_campaigns.failure',
        'Unable to process the creator campaign request.',
        500,
        ['action' => $action ?? null],
        $actorUserId
    );
}
