<?php
declare(strict_types=1);

require_once __DIR__ . '/_opportunities.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.opportunity_action', 'user:' . (int)$user['id'], 90, 60);
    $opportunity = mg_world_opportunity_update_status($pdo, $user, $input);
    mg_ok(['opportunity' => $opportunity], 'Opportunity updated.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.opportunity_action_failed', 'World Canvas opportunity action failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to update World Canvas opportunity.', 500);
}
