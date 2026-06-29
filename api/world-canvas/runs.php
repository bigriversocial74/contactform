<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery_runs.php';
$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');
try {
  if ($method === 'GET') mg_ok(['schema_ready'=>mg_world_delivery_runs_ready($pdo),'delivery_runs'=>mg_world_delivery_run_list($pdo,$user)]);
  if ($method !== 'POST') mg_fail('Method not allowed.',405);
  $input = mg_input();
  mg_require_csrf_for_write($input);
  mg_rate_limit('world_canvas.runs','user:'.(int)($user['id'] ?? 0),80,60);
  $dropPublicId = trim((string)($input['id'] ?? $input['target_drop_id'] ?? ''));
  if ($dropPublicId === '') throw new RuntimeException('Target Drop is required.');
  $drop = mg_world_delivery_run_target_row($pdo,$dropPublicId,(int)($user['id'] ?? 0));
  if (!$drop) throw new RuntimeException('Target Drop not found.');
  $run = mg_world_delivery_run_create($pdo,$drop,'test');
  if (!$run) throw new RuntimeException('Run table is not installed.');
  mg_ok(['delivery_run'=>$run],'Run started.');
} catch (InvalidArgumentException|RuntimeException $error) {
  mg_fail($error->getMessage(),400);
} catch (Throwable $error) {
  mg_security_log('error','world_canvas.runs_failed','World Canvas run failed.',['exception_class'=>$error::class],(int)($user['id'] ?? 0));
  mg_fail('Unable to start run.',500);
}
