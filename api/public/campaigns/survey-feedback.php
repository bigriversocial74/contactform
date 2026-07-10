<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';

function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $entry = $input['entry'] ?? [];
    if (!is_array($entry)) $entry = [];

    if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        mg_fail('Invalid survey feedback submission.', 422);
    }

    try {
        $stmt = $pdo->prepare("SELECT public_id,public_slug,campaign_type,rules_json FROM campaigns WHERE status='active' AND (public_id=? OR public_slug=?) LIMIT 1");
        $stmt->execute([$campaignRef, $campaignRef]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'public.survey_feedback.lookup_failed', 'Survey feedback campaign lookup failed.', ['exception_class' => $error::class]);
        }
        mg_fail('Survey feedback campaign is not available.', 404);
    }

    if (!$campaign || (string)$campaign['campaign_type'] !== 'survey_feedback_reward') {
        mg_fail('Survey feedback campaign is not available.', 404);
    }

    $rules = [];
    $decoded = json_decode((string)($campaign['rules_json'] ?? ''), true);
    if (is_array($decoded)) $rules = $decoded;

    $ratingRequired = !array_key_exists('rating_required', $rules) || !empty($rules['rating_required']);
    $feedbackRequired = !array_key_exists('feedback_required', $rules) || !empty($rules['feedback_required']);
    $ratingRaw = trim((string)($entry['rating'] ?? $entry['score'] ?? ''));
    $feedback = trim((string)($entry['feedback'] ?? $entry['response'] ?? $entry['note'] ?? ''));

    if ($feedbackRequired && $feedback === '') {
        mg_fail('Please share a feedback response before claiming the reward.', 422);
    }
    if ($feedback !== '' && (mb_strlen($feedback) < 3 || mb_strlen($feedback) > 1200)) {
        mg_fail('Feedback must be between 3 and 1200 characters.', 422);
    }
    if ($ratingRequired && $ratingRaw === '') {
        mg_fail('Please choose a feedback rating before claiming the reward.', 422);
    }
    if ($ratingRaw !== '' && (!ctype_digit($ratingRaw) || (int)$ratingRaw < 1 || (int)$ratingRaw > 5)) {
        mg_fail('Please choose a feedback rating from 1 to 5.', 422);
    }

    $entry['feedback'] = $feedback;
    $entry['rating'] = $ratingRaw !== '' ? (int)$ratingRaw : null;
    $entry['survey_prompt'] = trim((string)($rules['prompt'] ?? '')) ?: 'How was your experience?';
    $entry['rating_required'] = $ratingRequired;
    $entry['feedback_required'] = $feedbackRequired;
    $input['entry'] = $entry;
    $input['campaign_type'] = 'survey_feedback_reward';
    return $input;
}

require __DIR__ . '/engage.php';
