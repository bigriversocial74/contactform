<?php
declare(strict_types=1);

function mg_personal_agent_credit_update_usage_event(PDO $pdo, int $userId, int $providerId, int $modelId, int $inputTokens, int $outputTokens, string $responseId): void
{
    try {
        $stmt = $pdo->prepare("UPDATE ai_usage_events
            SET request_units=0,input_tokens=?,output_tokens=?,
                metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),'$.credit_ledger',true,'$.anthropic_response_id',?)
            WHERE user_id=? AND provider_id=? AND model_id=? AND request_status='completed'
            ORDER BY id DESC LIMIT 1");
        $stmt->execute([$inputTokens,$outputTokens,$responseId,$userId,$providerId,$modelId]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning','user_agent.ai_usage_token_update_failed','Unable to add token usage to the Personal Agent completion event.',['exception_type'=>$error::class],$userId);
        }
    }
}

function mg_personal_agent_chat_with_credit_response(PDO $pdo, int $userId, array $input): array
{
    $message = mg_personal_agent_text($input['message'] ?? '', 2000);
    $model = null;
    $creditBefore = mg_ai_credit_snapshot($pdo,$userId,'anthropic');
    if ($message !== '' && !mg_personal_agent_message_has_secret_request($message)) {
        $model = mg_personal_agent_model($pdo,$userId,mg_personal_agent_text($input['model_id'] ?? '',80));
        if ($model) {
            $reserve = max(700,min(2200,(int)($model['max_output_tokens'] ?? 1600)));
            $creditBefore = mg_ai_credit_preflight($pdo,$userId,'anthropic',$reserve,'personal_gifting_agent');
        }
    }

    unset($GLOBALS['mg_last_anthropic_response']);
    $result = mg_personal_agent_chat_with_opportunity_attribution($pdo,$userId,$input);
    $creditAfter = $creditBefore;
    $tokensUsed = ['input'=>0,'output'=>0,'total'=>0];

    if ($model && !empty($result['used_ai'])) {
        $raw = function_exists('mg_anthropic_last_response') ? mg_anthropic_last_response() : [];
        $usage = is_array($raw['usage'] ?? null) ? $raw['usage'] : [];
        $inputTokens = max(0,(int)($usage['input_tokens'] ?? 0));
        $outputTokens = max(0,(int)($usage['output_tokens'] ?? 0));
        $responseId = trim((string)($raw['id'] ?? $result['assistant_message']['id'] ?? ''));
        $tokensUsed = ['input'=>$inputTokens,'output'=>$outputTokens,'total'=>$inputTokens+$outputTokens];
        try {
            $creditAfter = mg_ai_credit_consume(
                $pdo,$userId,(int)$model['provider_id'],(int)$model['id'],'anthropic',
                $inputTokens,$outputTokens,'personal_gifting_agent',$responseId,
                ['thread_id'=>(string)($result['thread']['id'] ?? ''),'model_key'=>(string)$model['model_key']]
            );
            mg_personal_agent_credit_update_usage_event($pdo,$userId,(int)$model['provider_id'],(int)$model['id'],$inputTokens,$outputTokens,$responseId);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('error','user_agent.ai_credit_debit_failed','Personal Agent completed but its token debit could not be recorded.',['exception_type'=>$error::class,'response_id'=>$responseId],$userId);
            }
            $result['ai_credit_accounting_error'] = true;
            $creditAfter = mg_ai_credit_snapshot($pdo,$userId,'anthropic');
        }
    } else {
        $creditAfter = mg_ai_credit_snapshot($pdo,$userId,'anthropic');
    }

    $result['ai_credits'] = $creditAfter;
    $result['ai_tokens_used'] = $tokensUsed;
    return $result;
}
