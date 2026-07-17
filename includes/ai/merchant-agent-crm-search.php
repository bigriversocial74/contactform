<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/merchant-crm-search.php';

function mg_merchant_agent_crm_search_is_query(mixed $value): bool
{
    return preg_match('/^@[a-z0-9][a-z0-9._-]{0,119}$/i', trim((string)$value)) === 1;
}

function mg_merchant_agent_crm_search_response(PDO $pdo, array $user, array $input): array
{
    $actorId = (int)($user['id'] ?? 0);
    $merchantId = max(1, (int)($input['_merchant_owner_id'] ?? $actorId));
    $rawMessage = trim((string)($input['message'] ?? ''));
    $query = mg_merchant_crm_search_query($rawMessage);
    if ($query === '') mg_fail('Enter an @username or partial CRM contact name.', 422);

    $thread = mg_agent_thread_by_id($pdo, $actorId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $result = mg_merchant_crm_search($pdo, $merchantId, $query, 100, 0);
    $total = (int)($result['total'] ?? 0);
    $schemaReady = !empty($result['schema_ready']);
    $reply = !$schemaReady
        ? 'Merchant CRM search is unavailable until the current CRM schema is installed.'
        : ($total === 0
            ? 'No Merchant CRM contacts matched @' . $query . '.'
            : 'Found ' . number_format($total) . ' Merchant CRM contact' . ($total === 1 ? '' : 's') . ' matching @' . $query . '.');

    $meta = [
        'scope'=>'crm',
        'mode'=>'lookup',
        'output_type'=>'crm_results',
        'approval_mode'=>'advisory',
        'context_profile'=>'crm_lookup',
        'thread_public_id'=>$threadId,
        'agent_name'=>mg_agent_profile($pdo, $actorId)['agent_name'] ?? 'Merchant Agent',
        'crm_search_query'=>$query,
        'crm_search_total'=>$total,
    ];

    try {
        $pdo->beginTransaction();
        $userMessageId = mg_ai_chat_record_message($pdo, $actorId, 'user', $rawMessage, [], $meta);
        $assistantMessageId = mg_ai_chat_record_message($pdo, $actorId, 'assistant', $reply, [], $meta);
        $pdo->commit();
        if (function_exists('mg_audit')) {
            mg_audit('merchant.agent_crm_search.chat', $actorId, [
                'merchant_user_id'=>$merchantId,
                'query_length'=>mb_strlen($query),
                'result_total'=>$total,
                'thread_id'=>$threadId,
            ]);
        }
        return [
            'user_message'=>[
                'id'=>$userMessageId,'role'=>'user','body'=>$rawMessage,'cards'=>[],'blocks'=>[],
                'scope'=>'crm','mode'=>'lookup','output_type'=>'crm_results','approval_mode'=>'advisory',
                'context_profile'=>'crm_lookup','thread_public_id'=>$threadId,'created_at'=>date('c'),
            ],
            'assistant_message'=>[
                'id'=>$assistantMessageId,'role'=>'assistant','body'=>$reply,'cards'=>[],'blocks'=>[],
                'scope'=>'crm','mode'=>'lookup','output_type'=>'crm_results','approval_mode'=>'advisory',
                'context_profile'=>'crm_lookup','thread_public_id'=>$threadId,'created_at'=>date('c'),
            ],
            'crm_search'=>$result + [
                'search_url'=>'/api/merchant/crm-search.php?q=' . rawurlencode($query),
                'crm_url'=>'/merchant-crm.php?search=' . rawurlencode($query),
            ],
            'state'=>mg_ai_chat_public_state($pdo, $actorId),
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.agent_crm_search.chat_failed', 'Merchant Agent CRM search response could not be recorded.', ['exception_class'=>$error::class], $actorId);
        mg_fail('Unable to return Merchant CRM search results.', 500);
    }
}

function mg_merchant_agent_crm_selected_context(PDO $pdo, int $merchantId, mixed $ids): array
{
    if (!is_array($ids)) return [];
    $contacts = mg_merchant_crm_search_contacts_by_ids($pdo, $merchantId, $ids);
    return array_map(static fn(array $contact): array => [
        'id'=>$contact['id'] ?? '',
        'mention'=>$contact['mention'] ?? '',
        'name'=>$contact['name'] ?? '',
        'lifecycle_stage'=>$contact['stage'] ?? '',
        'crm_status'=>$contact['status'] ?? '',
        'campaign'=>$contact['campaign_title'] ?: ($contact['campaign_type'] ?? ''),
        'source'=>$contact['source'] ?? '',
        'last_activity_at'=>$contact['last_activity_at'] ?? null,
        'engagement_score'=>$contact['score'] ?? 0,
        'engagement_label'=>$contact['score_label'] ?? '',
        'next_best_action'=>$contact['next_best_action'] ?? '',
        'has_account'=>!empty($contact['has_account']),
        'email_verified'=>!empty($contact['email_verified']),
    ], $contacts);
}
