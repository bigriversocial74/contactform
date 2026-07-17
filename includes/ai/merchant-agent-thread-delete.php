<?php
declare(strict_types=1);

function mg_merchant_agent_thread_exact(PDO $pdo, int $merchantId, string $threadPublicId): array
{
    if ($threadPublicId === '' || preg_match('/^[a-f0-9-]{36}$/i', $threadPublicId) !== 1) {
        mg_fail('Merchant Agent chat was not found.', 404);
    }
    $stmt = $pdo->prepare('SELECT * FROM merchant_agent_threads WHERE merchant_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$merchantId, strtolower($threadPublicId)]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($thread)) mg_fail('Merchant Agent chat was not found.', 404);
    return $thread;
}

function mg_merchant_agent_delete_thread(PDO $pdo, int $merchantId, string $threadPublicId): array
{
    if (!mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
        mg_fail('Run the merchant agent skills SQL migration before deleting chats.', 500);
    }
    $thread = mg_merchant_agent_thread_exact($pdo, $merchantId, $threadPublicId);
    $threadPublicId = (string)$thread['public_id'];
    $wasActive = (string)$thread['status'] === 'active';

    try {
        $pdo->beginTransaction();
        if (mg_agent_table_exists($pdo, 'merchant_agent_insight_snapshots')) {
            $pdo->prepare('DELETE FROM merchant_agent_insight_snapshots WHERE merchant_user_id=? AND thread_public_id=?')->execute([$merchantId, $threadPublicId]);
        }
        $pdo->prepare("DELETE FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant','merchant.agent_chat.contact_selected','merchant.agent_chat.contact_cleared') AND JSON_VALID(event_context_json)=1 AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.thread_public_id'))=?")->execute([$merchantId, $threadPublicId]);
        $pdo->prepare('DELETE FROM merchant_agent_threads WHERE merchant_user_id=? AND public_id=? LIMIT 1')->execute([$merchantId, $threadPublicId]);
        if ($wasActive) mg_agent_create_thread($pdo, $merchantId, ['title'=>'Current chat'], true);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'merchant.agent_thread.delete_failed', 'Merchant Agent chat could not be deleted.', ['exception_class'=>$error::class,'thread_id'=>$threadPublicId], $merchantId);
        mg_fail('Unable to delete this Merchant Agent chat.', 500);
    }

    if (function_exists('mg_audit')) {
        try { mg_audit('merchant.agent_thread.deleted', 'merchant_agent_thread', ['thread_id'=>$threadPublicId,'was_active'=>$wasActive], $merchantId); } catch (Throwable) {}
    }
    return ['deleted_thread_id'=>$threadPublicId,'state'=>mg_ai_chat_public_state($pdo, $merchantId)];
}
