<?php
declare(strict_types=1);

function mg_personal_agent_marketplace_grounded_reply(string $kind, int $count): string
{
    $noun = match ($kind) {
        'merchant' => $count === 1 ? 'merchant' : 'merchants',
        'campaign' => $count === 1 ? 'active campaign' : 'active campaigns',
        default => $count === 1 ? 'published product' : 'published products',
    };
    $detail = match ($kind) {
        'merchant' => 'Open a merchant card to view its public profile, products, and campaigns.',
        'campaign' => 'Open a campaign card to view its public campaign page or the merchant profile.',
        default => 'Open a product card for full details or use the merchant link to view the business.',
    };
    return 'I found ' . $count . ' ' . $noun . ' in the permission-safe Microgifter marketplace results. ' . $detail;
}

function mg_personal_agent_chat_with_marketplace_response(PDO $pdo, int $userId, array $input): array
{
    $result = mg_personal_agent_chat_with_marketplace_cards($pdo,$userId,$input);
    $count = (int)($result['marketplace_result_count'] ?? 0);
    if ($count < 1) return $result;

    $reply = (string)($result['assistant_message']['body'] ?? '');
    $lower = mb_strtolower(str_replace(['’','‘'],"'",$reply));
    $contradictory = false;
    foreach (["don't have access","do not have access","cannot access","can't access",'no access to','unable to access the marketplace','no live product catalog'] as $phrase) {
        if (str_contains($lower,$phrase)) {
            $contradictory = true;
            break;
        }
    }
    if (!$contradictory) return $result;

    $reply = mg_personal_agent_marketplace_grounded_reply((string)($result['marketplace_result_kind'] ?? 'product'),$count);
    $assistantId = (string)($result['assistant_message']['id'] ?? '');
    if ($assistantId !== '') {
        try {
            $stmt = $pdo->prepare("UPDATE user_agent_messages SET body=? WHERE owner_user_id=? AND public_id=? AND role='assistant'");
            $stmt->execute([$reply,$userId,$assistantId]);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning','user_agent.marketplace_reply_persist_failed','Grounded marketplace reply could not be persisted.',['exception_type'=>$error::class],$userId);
            }
        }
    }
    $result['assistant_message']['body'] = $reply;
    return $result;
}
