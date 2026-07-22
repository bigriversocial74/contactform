<?php
declare(strict_types=1);
require_once __DIR__ . '/_creator.php';
require_once dirname(__DIR__, 2) . '/includes/creator-campaigns.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$actorUserId = (int) ($user['id'] ?? 0);

try {
    $analytics = mg_creator_campaign_analytics_build($pdo, $user, $_GET, 'creator');
    if (strtolower(trim((string) ($_GET['format'] ?? ''))) === 'csv') {
        $report = strtolower(trim((string) ($_GET['report'] ?? 'campaigns')));
        $export = mg_creator_campaign_analytics_csv($analytics, $report);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
        header('Cache-Control: private, no-store, max-age=0');
        echo $export['content'];
        exit;
    }
    mg_ok($analytics);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (DomainException $error) {
    mg_fail($error->getMessage(), 403);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    mg_fail($error->getMessage(), str_contains($message, 'schema is incomplete') ? 503 : (str_contains($message, 'not found') ? 404 : 409));
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'creator.campaign.analytics.creator_failure', 'Unable to load campaign performance.', 500, [], $actorUserId);
}
