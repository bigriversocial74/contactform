<?php
declare(strict_types=1);

/**
 * Merchant campaign route extension.
 *
 * The Stage 12 controller remains in campaigns-core.php. This route adds the
 * specialized Survey, Check-In, and RSVP rule contract before the response is
 * returned, while preserving the existing campaign create/update controller.
 */

function mg_campaign_specialized_bool(array $input, string $inputKey, array $existing, string $ruleKey, bool $default): bool
{
    if (array_key_exists($inputKey, $input)) return !empty($input[$inputKey]);
    if (array_key_exists($ruleKey, $existing)) return !empty($existing[$ruleKey]);
    return $default;
}

function mg_campaign_specialized_rules(string $campaignType, array $input, array $existing): array
{
    if ($campaignType === 'survey_feedback_reward') {
        $prompt = trim((string)($input['survey_prompt'] ?? $existing['prompt'] ?? $input['form_description'] ?? ''));
        return [
            'mode' => 'survey_feedback',
            'prompt' => mb_substr($prompt !== '' ? $prompt : 'How was your experience?', 0, 500),
            'rating_required' => mg_campaign_specialized_bool($input, 'survey_rating_required', $existing, 'rating_required', true),
            'feedback_required' => mg_campaign_specialized_bool($input, 'survey_feedback_required', $existing, 'feedback_required', true),
            'entry_reward_enabled' => true,
        ];
    }

    if ($campaignType === 'check_in_reward') {
        $radius = (int)($input['check_in_radius_meters'] ?? $existing['radius_meters'] ?? 150);
        $radius = max(25, min(5000, $radius > 0 ? $radius : 150));
        $locationRequired = mg_campaign_specialized_bool($input, 'check_in_location_required', $existing, 'location_required', true);
        return [
            'mode' => 'geo_check_in',
            'browser_location_required' => $locationRequired,
            'merchant_location_match' => $locationRequired,
            'radius_meters' => $radius,
            'location_required' => $locationRequired,
            'entry_reward_enabled' => true,
        ];
    }

    if ($campaignType === 'rsvp_event_reward') {
        $eventName = trim((string)($input['rsvp_event_name'] ?? $existing['event_name'] ?? $existing['rsvp_event_name'] ?? $input['title'] ?? ''));
        $eventDateInput = trim((string)($input['rsvp_event_date'] ?? $existing['event_date'] ?? $existing['rsvp_event_date'] ?? ''));
        $eventDate = $eventDateInput !== '' ? mb_substr($eventDateInput, 0, 80) : null;
        $attendanceCode = mb_substr(strtoupper(trim((string)($input['rsvp_attendance_code'] ?? $existing['attendance_code'] ?? $existing['rsvp_attendance_code'] ?? ''))), 0, 64);
        $safeName = mb_substr($eventName !== '' ? $eventName : 'Merchant event', 0, 160);
        return [
            'mode' => 'rsvp_attendance',
            'event_name' => $safeName,
            'rsvp_event_name' => $safeName,
            'event_date' => $eventDate,
            'rsvp_event_date' => $eventDate,
            'attendance_code' => $attendanceCode,
            'rsvp_attendance_code' => $attendanceCode,
            'attendance_required' => true,
            'entry_reward_enabled' => true,
        ];
    }

    return [];
}

function mg_campaign_specialized_enrich_row(array $campaign): array
{
    $rules = is_array($campaign['rules'] ?? null) ? $campaign['rules'] : [];
    $type = (string)($campaign['campaign_type'] ?? '');

    if ($type === 'survey_feedback_reward') {
        $campaign['survey_prompt'] = (string)($rules['prompt'] ?? 'How was your experience?');
        $campaign['survey_rating_required'] = !array_key_exists('rating_required', $rules) || !empty($rules['rating_required']);
        $campaign['survey_feedback_required'] = !array_key_exists('feedback_required', $rules) || !empty($rules['feedback_required']);
    } elseif ($type === 'check_in_reward') {
        $campaign['check_in_radius_meters'] = (int)($rules['radius_meters'] ?? 150);
        $campaign['check_in_location_required'] = !array_key_exists('location_required', $rules) || !empty($rules['location_required']);
    } elseif ($type === 'rsvp_event_reward') {
        $campaign['rsvp_event_name'] = (string)($rules['event_name'] ?? $rules['rsvp_event_name'] ?? '');
        $campaign['rsvp_event_date'] = (string)($rules['event_date'] ?? $rules['rsvp_event_date'] ?? '');
        $campaign['rsvp_attendance_code'] = (string)($rules['attendance_code'] ?? $rules['rsvp_attendance_code'] ?? '');
    }

    return $campaign;
}

function mg_campaign_specialized_prepare_response(array $data): array
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $merchantId = (int)($GLOBALS['merchantId'] ?? 0);
    $actor = is_array($GLOBALS['user'] ?? null) ? $GLOBALS['user'] : null;
    $data['public_donations_feature'] = mg_public_donations_feature_context($merchantId > 0 ? $merchantId : null, $actor);

    if ($method === 'GET' && is_array($data['campaigns'] ?? null)) {
        $data['campaigns'] = array_map('mg_campaign_specialized_enrich_row', $data['campaigns']);
        return $data;
    }
    if ($method !== 'POST' || !is_array($data['campaign'] ?? null)) return $data;

    $input = is_array($GLOBALS['input'] ?? null) ? $GLOBALS['input'] : [];
    $campaignType = (string)($GLOBALS['campaignType'] ?? $data['campaign']['campaign_type'] ?? '');
    $campaignId = (string)($GLOBALS['campaignId'] ?? $data['campaign']['id'] ?? '');
    $merchantId = (int)($GLOBALS['merchantId'] ?? 0);
    $pdo = $GLOBALS['pdo'] ?? null;

    if (!in_array($campaignType, ['survey_feedback_reward', 'check_in_reward', 'rsvp_event_reward'], true)) {
        $data['campaign'] = mg_campaign_specialized_enrich_row($data['campaign']);
        return $data;
    }
    if (!$pdo instanceof PDO || $campaignId === '' || $merchantId <= 0) {
        throw new RuntimeException('Specialized campaign rule context is unavailable.');
    }

    $existing = is_array($GLOBALS['existingRules'] ?? null) ? $GLOBALS['existingRules'] : [];
    $baseRules = is_array($data['campaign']['rules'] ?? null) ? $data['campaign']['rules'] : [];
    $rules = array_replace($baseRules, mg_campaign_specialized_rules($campaignType, $input, $existing));
    $rules['campaign_type'] = $campaignType;
    $rules['version'] = max(2, (int)($rules['version'] ?? 2));
    $rules['registry'] = 'campaign_types_v2_specialized_landing';

    $rulesJson = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($rulesJson)) throw new RuntimeException('Unable to encode specialized campaign rules.');

    $stmt = $pdo->prepare('UPDATE campaigns SET rules_json=?, updated_at=NOW() WHERE public_id=? AND merchant_user_id=?');
    $stmt->execute([$rulesJson, $campaignId, $merchantId]);
    if ($stmt->rowCount() < 1) {
        $verify = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE public_id=? AND merchant_user_id=? AND rules_json=?');
        $verify->execute([$campaignId, $merchantId, $rulesJson]);
        if ((int)$verify->fetchColumn() < 1) throw new RuntimeException('Specialized campaign rules were not persisted.');
    }

    $data['campaign']['rules'] = $rules;
    $data['campaign'] = mg_campaign_specialized_enrich_row($data['campaign']);

    if (function_exists('mg_audit')) {
        mg_audit('merchant.campaign_specialized_rules_saved', 'campaign', [
            'campaign_id' => $campaignId,
            'campaign_type' => $campaignType,
            'rules' => $rules,
        ], $merchantId);
    }

    return $data;
}

if (!function_exists('mg_ok')) {
    function mg_ok(array $data = [], string $message = 'OK', int $status = 200): never
    {
        try {
            $data = mg_campaign_specialized_prepare_response($data);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('error', 'merchant.campaign_specialized_rules_failed', 'Unable to persist specialized campaign rules.', [
                    'exception_class' => $error::class,
                    'message' => $error->getMessage(),
                ], (int)($GLOBALS['merchantId'] ?? 0));
            }
            mg_json(['ok' => false, 'message' => 'Unable to save specialized campaign settings.', 'errors' => []], 500);
        }
        mg_json(['ok' => true, 'message' => $message, 'data' => $data], $status);
    }
}

require __DIR__ . '/campaigns-core.php';
