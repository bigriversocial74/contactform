<?php
declare(strict_types=1);

require_once __DIR__ . '/_case.php';

mg_require_method('GET');
$user = mg_profile_moderation_require_view();
$caseId = trim((string)($_GET['case_id'] ?? ''));

try {
    $data = mg_profile_moderation_detail(mg_db(), $user, $caseId);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    error_log('Profile moderation case read failed: ' . $error::class);
    mg_fail('Unable to load moderation case.', 500);
}

mg_ok($data, 'Profile moderation case loaded.');
