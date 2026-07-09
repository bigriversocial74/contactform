<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();

$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$entry = $input['entry'] ?? [];
if (!is_array($entry)) $entry = [];
$ratingRaw = trim((string)($entry['rating'] ?? $entry['score'] ?? ''));
$feedback = trim((string)($entry['feedback'] ?? $entry['response'] ?? $entry['note'] ?? ''));

if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Invalid survey feedback submission.', 422);
}
if ($feedback === '' || mb_strlen($feedback) < 3 || mb_strlen($feedback) > 1200) {
    mg_fail('Please share a short feedback response before claiming the reward.', 422);
}
if ($ratingRaw === '' || !ctype_digit($ratingRaw) || (int)$ratingRaw < 1 || (int)$ratingRaw > 5) {
    mg_fail('Please choose a feedback rating from 1 to 5.', 422);
}

try {
    $stmt = $pdo->prepare("SELECT public_id, public_slug, campaign_type, rules_json FROM campaigns WHERE status = 'active' AND (public_id = ? OR public_slug = ?) LIMIT 1");
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign || (string)$campaign['campaign_type'] !== 'survey_feedback_reward') {
        mg_fail('Survey feedback campaign is not available.', 404);
    }
    $rules = [];
    if (is_string($campaign['rules_json'] ?? null) && trim((string)$campaign['rules_json']) !== '') {
        $decoded = json_decode((string)$campaign['rules_json'], true);
        $rules = is_array($decoded) ? $decoded : [];
    }
    if (!empty($rules['rating_required']) && $ratingRaw === '') {
        mg_fail('Please choose a feedback rating before claiming the reward.', 422);
    }
    if (!empty($rules['feedback_required']) && $feedback === '') {
        mg_fail('Please share a feedback response before claiming the reward.', 422);
    }
} catch (Throwable $error) {
    if ($error instanceof RuntimeException) throw $error;
    if (function_exists('mg_security_log')) mg_security_log('warning', 'public.survey_feedback.validation_failed', 'Survey feedback validation failed.', ['exception_class' => $error::class]);
    mg_fail('Survey feedback campaign is not available.', 404);
}

require __DIR__ . '/engage.php';
