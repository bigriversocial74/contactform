<?php
declare(strict_types=1);
require_once __DIR__ . '/_action_center.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$publicId = trim((string)($input['id'] ?? $input['action_item_id'] ?? ''));
if ($publicId === '') mg_fail('Action Center item id is required', 422);

mg_action_center_archive(mg_db(), (int)$user['id'], $publicId);
mg_ok(['status' => 'archived']);
