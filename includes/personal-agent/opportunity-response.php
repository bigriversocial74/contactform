<?php
declare(strict_types=1);

function mg_personal_agent_opportunity_merchant_id(PDO $pdo, string $entityType, string $entityId, array $card): ?int
{
    $provided = (int)($card['merchant_user_id'] ?? 0);
    if ($provided > 0) return $provided;
    try {
        if ($entityType === 'product') {
            $stmt = $pdo->prepare('SELECT merchant_user_id FROM catalog_products WHERE public_id=? LIMIT 1');
            $stmt->execute([$entityId]);
            $value = (int)$stmt->fetchColumn();
            return $value > 0 ? $value : null;
        }
        if ($entityType === 'campaign') {
            $stmt = $pdo->prepare('SELECT merchant_user_id FROM campaigns WHERE public_id=? LIMIT 1');
            $stmt->execute([$entityId]);
            $value = (int)$stmt->fetchColumn();
            return $value > 0 ? $value : null;
        }
        if ($entityType === 'merchant') {
            $stmt = $pdo->prepare('SELECT user_id FROM public_profiles WHERE public_id=? LIMIT 1');
            $stmt->execute([$entityId]);
            $value = (int)$stmt->fetchColumn();
            return $value > 0 ? $value : null;
        }
    } catch (Throwable) {
        return null;
    }
    return null;
}

function mg_personal_agent_opportunity_actions(array $card, array $opportunity): array
{
    $kind = mg_personal_agent_opportunity_entity_type($card['result_kind'] ?? 'product');
    $token = (string)$opportunity['attribution_token'];
    $primary = (string)($card['url'] ?? '');
    $secondary = (string)($card['secondary_url'] ?? '');
    $actions = [];
    if ($kind === 'product') {
        $actions[] = ['key'=>'buy_self','label'=>'Buy for myself','url'=>mg_personal_agent_opportunity_url($primary,$token,'buy_self'),'primary'=>true];
        $actions[] = ['key'=>'send_gift','label'=>'Send as a gift','url'=>mg_personal_agent_opportunity_url($primary,$token,'send_gift') . '&purchase_intent=gift'];
    } elseif ($kind === 'campaign') {
        $actions[] = ['key'=>'join_campaign','label'=>'Join campaign','url'=>mg_personal_agent_opportunity_url($primary,$token,'join_campaign'),'primary'=>true];
    } else {
        $actions[] = ['key'=>'view_merchant','label'=>'View merchant','url'=>mg_personal_agent_opportunity_url($primary,$token,'view_merchant'),'primary'=>true];
    }
    $actions[] = ['key'=>'save','label'=>'Save','url'=>''];
    if ($secondary !== '' && $kind !== 'merchant') {
        $actions[] = ['key'=>'view_merchant','label'=>'View merchant','url'=>mg_personal_agent_opportunity_url($secondary,$token,'view_merchant')];
    }
    $actions[] = ['key'=>'hide','label'=>'Hide','url'=>''];
    return $actions;
}

function mg_personal_agent_chat_with_opportunity_attribution(PDO $pdo, int $userId, array $input): array
{
    $result = mg_personal_agent_chat_with_merchant_opportunities($pdo,$userId,$input);
    if (!mg_personal_agent_opportunity_schema_ready($pdo)) {
        $result['opportunity_actions_available'] = false;
        return $result;
    }
    $cards = is_array($result['assistant_message']['cards'] ?? null) ? $result['assistant_message']['cards'] : [];
    if ($cards === []) {
        $result['opportunity_actions_available'] = true;
        return $result;
    }
    $threadId = (string)($result['thread']['id'] ?? '');
    $messageId = (string)($result['assistant_message']['id'] ?? '');
    $changed = false;
    foreach ($cards as $index => $card) {
        if (!is_array($card)) continue;
        $type = (string)($card['type'] ?? '');
        if (!str_starts_with($type,'marketplace_')) continue;
        $entityType = mg_personal_agent_opportunity_entity_type($card['result_kind'] ?? substr($type,12));
        $entityId = mg_personal_agent_text($card['id'] ?? '',190);
        $destination = mg_personal_agent_opportunity_internal_url($card['url'] ?? '');
        if ($entityId === '' || $destination === '') continue;
        $merchantId = mg_personal_agent_opportunity_merchant_id($pdo,$entityType,$entityId,$card);
        try {
            $opportunity = mg_personal_agent_opportunity_upsert($pdo,$userId,[
                'thread_id'=>$threadId,
                'assistant_message_id'=>$messageId,
                'merchant_user_id'=>$merchantId,
                'entity_type'=>$entityType,
                'entity_id'=>$entityId,
                'title'=>(string)($card['title'] ?? 'Opportunity'),
                'destination_url'=>$destination,
                'source_context'=>[
                    'card_type'=>$type,
                    'eyebrow'=>$card['eyebrow'] ?? null,
                    'price'=>$card['price'] ?? null,
                    'merchant_name'=>$card['merchant_name'] ?? null,
                    'prompt'=>mg_personal_agent_text($input['message'] ?? '',500),
                ],
            ]);
            $row = mg_personal_agent_opportunity_find($pdo,$userId,(string)$opportunity['id']);
            mg_personal_agent_opportunity_event($pdo,$row,'recommendation_created',[
                'action_type'=>'recommendation','page_path'=>'/agent.php',
            ],'recommendation:' . (string)$opportunity['id']);
            $card['opportunity_id'] = (string)$opportunity['id'];
            $card['attribution_token'] = (string)$opportunity['attribution_token'];
            $card['opportunity_state'] = (string)$opportunity['state'];
            $card['merchant_user_id'] = $merchantId;
            $card['actions'] = mg_personal_agent_opportunity_actions($card,$opportunity);
            $card['url'] = mg_personal_agent_opportunity_url($destination,(string)$opportunity['attribution_token'],$entityType === 'campaign' ? 'join_campaign' : ($entityType === 'merchant' ? 'view_merchant' : 'open_product'));
            $cards[$index] = $card;
            $changed = true;
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning','user_agent.opportunity_card_decorate_failed','A marketplace card could not be connected to opportunity attribution.',['exception_type'=>$error::class,'entity_type'=>$entityType,'entity_id'=>$entityId],$userId);
            }
        }
    }
    if ($changed) {
        $result['assistant_message']['cards'] = $cards;
        if ($messageId !== '') {
            try {
                $pdo->prepare("UPDATE user_agent_messages SET cards_json=? WHERE owner_user_id=? AND public_id=? AND role='assistant'")
                    ->execute([mg_personal_agent_json_encode($cards),$userId,$messageId]);
            } catch (Throwable) {
                // The response still contains the decorated cards even when history persistence is unavailable.
            }
        }
    }
    $result['opportunity_actions_available'] = true;
    return $result;
}
