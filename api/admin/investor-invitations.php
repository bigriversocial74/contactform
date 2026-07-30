<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/investment/investment-service.php';

$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        mg_investment_require_permission($actor, 'admin.investor_access.view');
        mg_rate_limit('admin.investor_invitations.read', 'user:' . $actorId, 240, 60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok([
            'ready' => mg_investment_invitation_tables_ready($pdo),
            'migration' => MG_INVESTOR_INVITATION_MIGRATION,
            'items' => mg_investment_invitation_tables_ready($pdo) ? mg_investment_invitation_admin_list($pdo, $_GET) : [],
            'rounds' => mg_investment_invitation_round_options($pdo),
        ], 'Investor invitations loaded.');
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    mg_investment_require_permission($actor, 'admin.investor_access.manage');
    if (!mg_investment_is_super($actor)) throw new MgInvestmentException('Only a Super Admin can manage Investor invitations.', 403);
    mg_rate_limit('admin.investor_invitations.write', 'user:' . $actorId, 60, 3600);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'create')));
    $result = match ($action) {
        'create' => mg_investment_invitation_create($pdo, $actor, $input),
        'resend' => mg_investment_invitation_resend($pdo, $actor, $input),
        'revoke' => ['invitation' => mg_investment_invitation_revoke($pdo, $actor, $input)],
        default => throw new MgInvestmentException('Invalid Investor invitation action.'),
    };
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($result, 'Investor invitation updated.');
} catch (MgInvestmentException $error) {
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'admin.investor_invitations.failed', 'Unable to manage Investor invitations.', 500, [], $actorId);
}
