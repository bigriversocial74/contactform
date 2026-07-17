<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-chat-memory.php';
require_once __DIR__ . '/merchant-agent-crm-contact-context.php';
require_once __DIR__ . '/merchant-agent-contact-action-center.php';

function mg_merchant_agent_crm_contact_system_prompt(): string
{
    return mg_ai_chat_memory_system_prompt() . "\n\nExplicit CRM contact rules:\n- selected_crm_contacts contains only the exact contact selected for this Merchant Agent thread or explicitly referenced by an exact @username in the current prompt.\n- You may use customer-level fields only from selected_crm_contacts and only to answer the current merchant request.\n- Never infer or invent a contact when a mention appears in unresolved_crm_mentions. Ask the merchant to choose a valid autocomplete result.\n- Do not expose email addresses, phone numbers, internal IDs, or unrelated customer records in the response.\n- For recent activity, summarize the selected contact's real event and campaign history in chronological merchant-facing language.\n- For follow-up copy, create a draft only. Never claim a message was sent.\n- For reward advice, recommend an appropriate next step from real reward, purchase, claim, redemption, campaign, and engagement history. Never issue a reward.\n- For campaign invitations and follow-up tasks, prepare review-ready drafts only.\n- Keep every action approval-first and use existing CRM, message, reward, campaign, follow-up, or review links only.\n";
}

function mg_merchant_agent_crm_unresolved_response(PDO $pdo, int $actorId, string $message, array $context, string $threadId): array
{
    $mentions = $context['unresolved_mentions'] ?? [];
    $reply = 'I could not match ' . implode(', ', $mentions) . ' to an exact contact in this Merchant CRM. Type the partial @username again and choose a contact from autocomplete before asking for activity, a follow-up draft, campaign invitation, task, or reward advice.';
    $meta = [
        'scope'=>'crm',
        'mode'=>'advisor',
        'output_type'=>'quick_answer',
        'approval_mode'=>'advisory',
        'context_profile'=>'crm_contact',
        'thread_public_id'=>$threadId,
        'crm_contact_mentions'=>$mentions,
        'crm_contact_count'=>0,
    ];
    $pdo->beginTransaction();
    try {
        $userId = mg_ai_chat_record_message($pdo, $actorId, 'user', $message, [], $meta);
        $assistantId = mg_ai_chat_record_message($pdo, $actorId, 'assistant', $reply, [], $meta);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return [
        'user_message'=>['id'=>$userId,'role'=>'user','body'=>$message,'cards'=>[],'blocks'=>[],'scope'=>'crm','mode'=>'advisor','output_type'=>'quick_answer','approval_mode'=>'advisory','context_profile'=>'crm_contact','thread_public_id'=>$threadId,'created_at'=>date('c')],
        'assistant_message'=>['id'=>$assistantId,'role'=>'assistant','body'=>$reply,'cards'=>[],'blocks'=>[],'scope'=>'crm','mode'=>'advisor','output_type'=>'quick_answer','approval_mode'=>'advisory','context_profile'=>'crm_contact','thread_public_id'=>$threadId,'created_at'=>date('c')],
        'state'=>mg_ai_chat_public_state($pdo, $actorId),
        'crm_contact_context'=>['selected_count'=>0,'unresolved_mentions'=>$mentions],
    ];
}

function mg_merchant_agent_crm_contact_cards(array $cards, array $selectedContacts): array
{
    $primary = $selectedContacts[0] ?? [];
    $contactId = (string)($primary['id'] ?? '');
    $mention = (string)($primary['mention'] ?? '');
    $name = (string)($primary['name'] ?? 'CRM contact');
    foreach ($cards as &$card) {
        if (!is_array($card)) continue;
        $payload = mg_ai_chat_json($card['review_payload'] ?? []);
        $payload['source'] = 'merchant_contact_action_center';
        $payload['crm_contact_id'] = $contactId;
        $payload['crm_contact_mention'] = $mention;
        $payload['crm_contact_name'] = $name;
        $payload['approval_required'] = true;
        $card['review_payload'] = $payload;
    }
    unset($card);
    return $cards;
}

function mg_merchant_agent_crm_contact_chat_response(PDO $pdo, array $user, array $input): array
{
    $actorId = (int)($user['id'] ?? 0);
    $merchantOwnerId = max(1, (int)($input['_merchant_owner_id'] ?? $actorId));
    $message = mg_ai_chat_clean($input['message'] ?? '', 2000);
    if ($message === '') mg_fail('Enter a message for the merchant agent.', 422);

    $scope = strtolower(mg_ai_chat_clean($input['scope'] ?? 'crm', 40)) ?: 'crm';
    if (!in_array($scope, mg_ai_chat_allowed_scopes(), true)) $scope = 'crm';
    $days = max(7, min(365, (int)($input['days'] ?? 90)));
    $mode = mg_ai_chat_control_value($input, 'mode', mg_ai_chat_allowed_modes(), 'advisor');
    $outputType = mg_ai_chat_control_value($input, 'output_type', mg_ai_chat_allowed_outputs(), 'action_plan');
    $approvalMode = mg_ai_chat_control_value($input, 'approval_mode', mg_ai_chat_allowed_approval_modes(), 'advisory');
    $thread = mg_agent_thread_by_id($pdo, $actorId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');

    $selectedContact = mg_merchant_contact_action_center_find_contact($pdo, $merchantOwnerId, $actorId, $threadId, $input);
    if ($selectedContact) {
        $input['selected_contact_id'] = (string)($selectedContact['id'] ?? '');
        $input['selected_contact_mention'] = (string)($selectedContact['mention'] ?? '');
    }
    $crmContext = mg_merchant_agent_crm_contact_context($pdo, $merchantOwnerId, $message, $days, $input);
    if (($crmContext['selected_count'] ?? 0) <= 0) {
        $response = mg_merchant_agent_crm_unresolved_response($pdo, $actorId, $message, $crmContext, $threadId);
        $response['state'] = mg_merchant_contact_action_center_attach_state($pdo, $merchantOwnerId, $actorId, $response['state'], $input);
        return $response;
    }

    $primaryContact = $crmContext['selected_contacts'][0];
    if ($threadId !== '') mg_merchant_contact_action_center_record_selection($pdo, $actorId, $threadId, $primaryContact);

    $contextProfile = mg_ai_chat_context_profile($message, $scope, $outputType);
    $deepContext = $contextProfile === 'data_analysis';
    $effectiveDays = $deepContext ? $days : min($days, 90);
    $controlInstructions = mg_ai_chat_output_instruction($mode, $outputType, $approvalMode);
    $recipeCatalog = mg_ai_chat_campaign_recipe_prompt_context();
    $skillKeys = mg_agent_skill_keys($input['skill_keys'] ?? null);
    if ($outputType === 'social_campaign' && !in_array('social_campaign_advisor', $skillKeys, true)) $skillKeys[] = 'social_campaign_advisor';
    $model = mg_ai_chat_select_merchant_model($pdo, mg_ai_chat_clean($input['model_key'] ?? '', 120), $contextProfile, $outputType, $message);
    $modelRoute = is_array($model['_routing'] ?? null) ? $model['_routing'] : mg_ai_chat_model_route($contextProfile, $outputType, $message);
    $provider = mg_ai_merchant_provider($pdo, (int)$model['provider_id']);
    mg_ai_enforce_rate_limits($pdo, $provider, $model, $actorId, null);

    $history = array_slice(mg_ai_chat_recent_messages($pdo, $actorId, $deepContext ? 12 : 6, $threadId), $deepContext ? -12 : -6);
    $operatingContext = mg_ai_merchant_context($pdo, $user, ['scope'=>$scope === 'overview' ? 'all' : $scope,'days'=>$effectiveDays,'merchant_goal'=>$message]);
    $operatingContext = mg_ai_chat_lightweight_context($operatingContext, $contextProfile);
    $memory = mg_agent_memory_prompt_context($pdo, $actorId);
    $memorySources = mg_agent_memory_source_prompt_context($pdo, $actorId);
    $feedPosts = mg_ai_chat_feed_posts_context($pdo, $actorId, $deepContext ? 12 : 8);
    $policy = mg_agent_policy_prompt_context($pdo, $actorId);
    $profile = mg_agent_profile($pdo, $actorId);

    $request = [
        'model'=>(string)$model['model_key'],
        'max_tokens'=>max(512, min($deepContext ? 2600 : 1800, (int)($input['max_tokens'] ?? ($deepContext ? 1600 : 1200)))),
        'temperature'=>$deepContext ? 0.25 : 0.45,
        'system'=>mg_merchant_agent_crm_contact_system_prompt(),
        'messages'=>[[
            'role'=>'user',
            'content'=>[['type'=>'text','text'=>json_encode([
                'merchant_message'=>$message,
                'scope'=>$scope,
                'review_window_days'=>$effectiveDays,
                'agent_mode'=>$mode,
                'output_type'=>$outputType,
                'approval_mode'=>$approvalMode,
                'context_profile'=>$contextProfile,
                'output_controls'=>$controlInstructions,
                'selected_crm_contacts'=>$crmContext['selected_contacts'],
                'unresolved_crm_mentions'=>$crmContext['unresolved_mentions'],
                'crm_contact_boundary'=>$crmContext['boundary'],
                'recent_chat_history'=>$history,
                'merchant_operating_snapshot'=>$operatingContext,
                'merchant_agent_memory'=>$memory,
                'merchant_agent_memory_sources'=>$memorySources,
                'merchant_feed_posts'=>$feedPosts,
                'merchant_agent_policy'=>$policy,
                'campaign_recipe_catalog'=>$recipeCatalog,
                'agent_profile'=>$profile,
                'active_thread'=>$thread,
                'enabled_skills'=>mg_agent_skill_prompt_context($skillKeys),
                'allowed_action_urls'=>mg_ai_chat_allowed_links(),
                'bridge_instruction'=>'Draft and recommend only. Use review_action_key and review_payload for any follow-up, message, reward, campaign invitation, or CRM task. Include the selected CRM contact reference in review_payload. Never execute directly.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
        ]],
    ];

    $selectedIds = array_values(array_filter(array_map(static fn(array $contact): string => (string)($contact['id'] ?? ''), $crmContext['selected_contacts'])));
    $selectedMentions = array_values(array_filter(array_map(static fn(array $contact): string => (string)($contact['mention'] ?? ''), $crmContext['selected_contacts'])));

    try {
        $rawResponse = mg_anthropic_messages($request);
        $text = mg_anthropic_text_from_response($rawResponse);
        try { $decoded = mg_anthropic_extract_json_object($text); } catch (Throwable) { $decoded = ['reply'=>$text,'cards'=>[],'blocks'=>[]]; }
        $reply = mg_ai_chat_clean($decoded['reply'] ?? $text, 6000);
        if ($reply === '') $reply = 'I reviewed the selected CRM contact and prepared the safest next step.';
        $cards = mg_ai_chat_shape_cards(mg_ai_chat_normalize_cards($decoded['cards'] ?? []), $message, $scope, $mode, $outputType, $approvalMode);
        $cards = mg_merchant_agent_crm_contact_cards($cards, $crmContext['selected_contacts']);
        $blocks = mg_agent_chat_normalize_blocks($decoded['blocks'] ?? []);
        if ($blocks === []) $blocks = mg_agent_skill_fallback_blocks($message, $skillKeys, $operatingContext);

        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $actorId, null, 'completed', $rawResponse, [
            'source'=>'merchant_agent_crm_contact_chat','scope'=>$scope,'mode'=>$mode,'output_type'=>$outputType,'approval_mode'=>$approvalMode,
            'context_profile'=>$contextProfile,'model_route_task'=>$modelRoute['task'] ?? '','model_selected_family'=>$modelRoute['selected_family'] ?? ($model['_family'] ?? ''),
            'thread_id'=>$threadId,'crm_contact_count'=>count($selectedIds),'crm_contact_ids'=>$selectedIds,'unresolved_mention_count'=>count($crmContext['unresolved_mentions'] ?? []),
        ]);

        $meta = [
            'scope'=>$scope,'mode'=>$mode,'output_type'=>$outputType,'approval_mode'=>$approvalMode,'context_profile'=>$contextProfile,
            'model_routing'=>$modelRoute,'thread_public_id'=>$threadId,'skills'=>$skillKeys,'agent_name'=>$profile['agent_name'] ?? 'Merchant Agent',
            'crm_contact_ids'=>$selectedIds,'crm_contact_mentions'=>$selectedMentions,'crm_contact_count'=>count($selectedIds),
        ];
        $pdo->beginTransaction();
        $userId = mg_ai_chat_record_message($pdo, $actorId, 'user', $message, [], $meta);
        $assistantId = mg_ai_chat_record_message($pdo, $actorId, 'assistant', $reply, $cards, $meta + ['blocks'=>$blocks,'model'=>(string)$model['model_key']]);
        $pdo->commit();
        if ($approvalMode === 'review_queue') mg_ai_chat_auto_bridge_cards($pdo, $user, $assistantId, $cards);

        $state = mg_merchant_contact_action_center_attach_state($pdo, $merchantOwnerId, $actorId, mg_ai_chat_public_state($pdo, $actorId), $input);
        return [
            'user_message'=>['id'=>$userId,'role'=>'user','body'=>$message,'cards'=>[],'blocks'=>[],'scope'=>$scope,'mode'=>$mode,'output_type'=>$outputType,'approval_mode'=>$approvalMode,'context_profile'=>$contextProfile,'thread_public_id'=>$threadId,'crm_contact_mentions'=>$selectedMentions,'created_at'=>date('c')],
            'assistant_message'=>['id'=>$assistantId,'role'=>'assistant','body'=>$reply,'cards'=>mg_ai_chat_recent_messages($pdo, $actorId, 1, $threadId)[0]['cards'] ?? $cards,'blocks'=>$blocks,'scope'=>$scope,'mode'=>$mode,'output_type'=>$outputType,'approval_mode'=>$approvalMode,'context_profile'=>$contextProfile,'thread_public_id'=>$threadId,'model'=>(string)$model['model_key'],'model_routing'=>$modelRoute,'crm_contact_mentions'=>$selectedMentions,'created_at'=>date('c')],
            'state'=>$state + ['memory'=>mg_agent_memory_summary($pdo, $actorId),'memory_sources'=>mg_agent_memory_sources($pdo, $actorId, 20),'feed_posts'=>$feedPosts,'policy'=>$policy],
            'crm_contact_context'=>['selected_count'=>count($selectedIds),'selected_mentions'=>$selectedMentions,'unresolved_mentions'=>$crmContext['unresolved_mentions']],
            'contact_action_center'=>$state['contact_action_center'] ?? null,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_ai_merchant_record_usage_event($pdo, (int)$provider['id'], (int)$model['id'], $actorId, null, 'failed', [], ['source'=>'merchant_agent_crm_contact_chat','error'=>$error->getMessage(),'crm_contact_count'=>count($selectedIds),'thread_id'=>$threadId]);
        mg_security_log('error', 'merchant.agent_crm_contact_chat.failed', 'Contact-aware Merchant Agent chat failed.', ['exception_class'=>$error::class,'crm_contact_count'=>count($selectedIds)], $actorId);
        mg_fail('Unable to run contact-aware Merchant Agent chat: ' . $error->getMessage(), 500);
    }
}
