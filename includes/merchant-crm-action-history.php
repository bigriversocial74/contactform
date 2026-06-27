<?php
declare(strict_types=1);

function mg_crm_action_history_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_crm_action_history_summary_seed(): array
{
    return ['selected' => 0, 'sent' => 0, 'issued' => 0, 'invited' => 0, 'skipped' => 0, 'failed' => 0, 'duplicates' => 0];
}

function mg_crm_action_history_action_label(string $type): string
{
    return match ($type) {
        'message' => 'Bulk message',
        'reward' => 'Bulk reward / invite',
        'followup' => 'Bulk follow-up',
        'export' => 'Contact export',
        default => ucwords(str_replace(['_', '-'], ' ', $type)),
    };
}

function mg_crm_action_history_run_status(array $summary): string
{
    if ((int)($summary['failed'] ?? 0) > 0) return 'needs_review';
    if ((int)($summary['skipped'] ?? 0) > 0 || (int)($summary['duplicates'] ?? 0) > 0) return 'partial';
    return 'complete';
}

function mg_crm_action_history_retryable(array $result): bool
{
    $status = (string)($result['status'] ?? '');
    $reason = (string)($result['reason'] ?? '');
    if ($status === 'failed') return $reason !== 'not_found';
    if ($status !== 'skipped') return false;
    return in_array($reason, ['message_failed', 'reward_failed', 'missing_valid_email'], true);
}

function mg_crm_action_history_record_result(PDO $pdo, array $contact, int $merchantId, string $batchKey, string $actionType, array $result, array $extra = []): bool
{
    $batchKey = substr(trim($batchKey), 0, 190);
    $actionType = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower(trim($actionType))) ?: 'bulk';
    if ($batchKey === '' || empty($contact['id']) || empty($contact['campaign_id'])) return false;
    $context = [
        'bulk_action' => true,
        'bulk_action_history' => true,
        'bulk_action_type' => $actionType,
        'bulk_action_label' => mg_crm_action_history_action_label($actionType),
        'bulk_batch_key' => $batchKey,
        'contact_id' => (string)($contact['public_id'] ?? ($result['contact_id'] ?? '')),
        'contact_name' => (string)($contact['name'] ?? ''),
        'has_account' => (int)($contact['user_id'] ?? 0) > 0,
        'result' => $result,
        'status' => (string)($result['status'] ?? 'unknown'),
        'reason' => (string)($result['reason'] ?? ''),
        'duplicate' => !empty($result['duplicate']),
        'retryable' => mg_crm_action_history_retryable($result),
    ] + $extra;
    try {
        $uuid = function_exists('mg_crm_bulk_uuid') ? mg_crm_bulk_uuid() : (function_exists('mg_merchant_uuid') ? mg_merchant_uuid() : bin2hex(random_bytes(16)));
        $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$uuid, $merchantId, (int)$contact['campaign_id'], (int)$contact['id'], 'crm.bulk_action.result', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        return true;
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'merchant.crm_action_history.result_failed', 'Unable to record CRM bulk action history result.', ['exception_class' => $error::class], $merchantId);
        return false;
    }
}

function mg_crm_action_history_result_summary(array $results): array
{
    $summary = mg_crm_action_history_summary_seed();
    $summary['selected'] = count($results);
    foreach ($results as $result) {
        $status = (string)($result['status'] ?? '');
        if (array_key_exists($status, $summary)) $summary[$status]++;
        if (!empty($result['duplicate'])) $summary['duplicates']++;
    }
    return $summary;
}
