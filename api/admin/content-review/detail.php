<?php
declare(strict_types=1);

require_once __DIR__ . '/_subjects.php';

mg_require_method('GET');
$user = mg_content_review_require(false);

try {
    $publicId = mg_content_review_reference($_GET['id'] ?? '');
    $pdo = mg_db();
    $row = mg_content_review_report($pdo, $publicId, false);
    $report = mg_content_review_report_public($row);
    $subject = mg_content_review_subject($pdo, $row);
    $account = mg_content_review_account($pdo, (int)($row['subject_user_id'] ?? 0));
    $history = mg_content_review_history($pdo, (int)$row['id']);

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok([
        'report'=>$report,
        'subject'=>$subject,
        'account'=>$account,
        'history'=>$history,
        'access'=>$user['content_review_access'],
        'generated_at'=>gmdate('c'),
    ], 'Report review loaded.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.content_review.detail_failed', 'Content review detail failed.', [
        'exception_class'=>$error::class,
    ], (int)$user['id']);
    mg_fail('Unable to load report review.', 500);
}
