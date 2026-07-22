<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_proposal_values(
    PDO $pdo,
    array $context,
    string $kind,
    array $values,
    ?array $campaign
): array {
    $values = mg_mcp_creator_campaign_proposal_json($values, 'proposed values');

    if ($kind === 'draft.create' || $kind === 'draft.update') {
        $allowed = [
            'internal_reference', 'title', 'description', 'objective', 'category', 'campaign_focus',
            'access_mode', 'timezone', 'starts_at', 'ends_at', 'application_deadline_at',
            'creator_product_access', 'creator_landing_url', 'maximum_approved_creators',
            'maximum_applications', 'automatic_acceptance', 'existing_creator_preference',
        ];
        $clean = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $values)) continue;
            $value = $values[$field];
            $clean[$field] = match ($field) {
                'title' => mg_mcp_draft_text($value, 180, 'campaign title'),
                'internal_reference' => mg_mcp_draft_text($value, 100, 'internal reference'),
                'description' => mg_mcp_draft_multiline($value, 10000, 'description'),
                'objective' => mg_mcp_draft_text($value, 180, 'objective'),
                'category' => mg_mcp_draft_text($value, 100, 'category'),
                'campaign_focus' => mg_mcp_creator_campaign_proposal_enum($value, ['merchant_profile','single_product','multiple_products','product_collection','microgift_offer','reward','event','service','experience','general_brand_campaign'], 'campaign focus'),
                'access_mode' => mg_mcp_creator_campaign_proposal_enum($value, ['open','invite_only','approved_creators','selected_creators','hybrid'], 'access mode'),
                'timezone' => mg_mcp_draft_text($value, 80, 'timezone'),
                'starts_at', 'ends_at', 'application_deadline_at' => mg_mcp_draft_datetime($value, $field),
                'creator_product_access' => mg_mcp_creator_campaign_proposal_enum($value, ['none','purchase_required','reimbursed','provided','loaned','digital_access'], 'creator product access'),
                'creator_landing_url' => mg_mcp_draft_text($value, 500, 'creator landing URL', false),
                'maximum_approved_creators', 'maximum_applications' => mg_mcp_draft_integer($value, 1, 1000000, $field),
                'automatic_acceptance' => mg_mcp_creator_campaign_proposal_bool($value),
                'existing_creator_preference' => mg_mcp_creator_campaign_proposal_enum($value, ['none','preferred','required'], 'existing Creator preference'),
                default => $value,
            };
        }
        if ($kind === 'draft.create') {
            foreach (['internal_reference','title','objective','category','campaign_focus','access_mode','timezone'] as $required) {
                if (!isset($clean[$required]) || $clean[$required] === '') {
                    throw new MgMcpDraftException('Missing required campaign proposal field: ' . $required . '.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
                }
            }
        } elseif ($clean === []) {
            throw new MgMcpDraftException('At least one campaign field must be proposed.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        }
        if (!empty($clean['starts_at']) && !empty($clean['ends_at']) && $clean['ends_at'] <= $clean['starts_at']) {
            throw new MgMcpDraftException('Campaign end must be later than start.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        }
        return $clean;
    }

    if ($kind === 'products.propose') {
        $items = $values['products'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 50) throw new MgMcpDraftException('Products proposal must contain 1 to 50 products.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        $clean = [];
        foreach (array_values($items) as $item) {
            if (!is_array($item)) throw new MgMcpDraftException('Invalid product proposal item.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            $clean[] = mg_mcp_creator_campaign_proposal_product($pdo, (int)$context['workspace_id'], $item);
        }
        return ['products' => $clean];
    }

    if ($kind === 'eligibility.propose') {
        $rules = $values['rules'] ?? null;
        if (!is_array($rules) || $rules === [] || count($rules) > 50) throw new MgMcpDraftException('Eligibility proposal must contain 1 to 50 rules.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        $clean = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) throw new MgMcpDraftException('Invalid eligibility rule.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            $clean[] = [
                'rule_type' => mg_mcp_creator_campaign_proposal_enum($rule['rule_type'] ?? '', ['specialty','category','platform','verification','location','audience','existing_relationship'], 'eligibility rule type'),
                'operator' => mg_mcp_creator_campaign_proposal_enum($rule['operator'] ?? 'equals', ['equals','not_equals','contains','in','gte','lte','between','exists'], 'eligibility operator', true, 'equals'),
                'value' => mg_mcp_creator_campaign_proposal_json(is_array($rule['value'] ?? null) ? $rule['value'] : ['value' => $rule['value'] ?? null], 'eligibility value', 10000),
                'required' => mg_mcp_creator_campaign_proposal_bool($rule['required'] ?? true, true),
                'sort_order' => mg_mcp_draft_integer($rule['sort_order'] ?? null, 0, 10000, 'sort order', 0),
            ];
        }
        return ['rules' => $clean];
    }

    if ($kind === 'deliverables.propose') {
        $items = $values['deliverables'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 50) throw new MgMcpDraftException('Deliverables proposal must contain 1 to 50 deliverables.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) throw new MgMcpDraftException('Invalid deliverable.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            $clean[] = array_filter([
                'title' => mg_mcp_draft_text($item['title'] ?? '', 180, 'deliverable title'),
                'description' => mg_mcp_draft_multiline($item['description'] ?? '', 10000, 'deliverable description'),
                'type' => mg_mcp_creator_campaign_proposal_enum($item['type'] ?? '', ['photo','short_video','long_video','story','reel','post','article','audio','livestream','event_appearance','product_review','other'], 'deliverable type'),
                'platform' => mg_mcp_draft_text($item['platform'] ?? '', 80, 'platform', false),
                'format' => mg_mcp_draft_text($item['format'] ?? '', 120, 'format', false),
                'quantity' => mg_mcp_draft_integer($item['quantity'] ?? null, 1, 1000, 'quantity', 1),
                'instructions' => mg_mcp_draft_multiline($item['instructions'] ?? '', 20000, 'instructions'),
                'publication_required' => mg_mcp_creator_campaign_proposal_bool($item['publication_required'] ?? false),
                'proof_required' => mg_mcp_creator_campaign_proposal_bool($item['proof_required'] ?? false),
                'revision_limit' => mg_mcp_draft_integer($item['revision_limit'] ?? null, 0, 100, 'revision limit', 2),
                'due_offset_days' => mg_mcp_draft_integer($item['due_offset_days'] ?? null, 0, 3650, 'due offset'),
            ], static fn(mixed $value): bool => $value !== null && $value !== '');
        }
        return ['deliverables' => $clean];
    }

    if ($kind === 'compensation.propose') {
        $rules = $values['rules'] ?? null;
        if (!is_array($rules) || $rules === [] || count($rules) > 50) throw new MgMcpDraftException('Compensation proposal must contain 1 to 50 rules.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        $clean = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) throw new MgMcpDraftException('Invalid compensation rule.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            $currency = strtoupper(mg_mcp_draft_text($rule['currency'] ?? 'USD', 3, 'currency'));
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) throw new MgMcpDraftException('Invalid currency.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            if (!array_key_exists('amount_minor', $rule) && !array_key_exists('rate_bps', $rule)) throw new MgMcpDraftException('Compensation amount or rate is required.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
            $clean[] = array_filter([
                'trigger_type' => mg_mcp_creator_campaign_proposal_enum($rule['trigger_type'] ?? '', ['verified_deliverable','attributed_conversion','milestone','manual_adjustment'], 'compensation trigger'),
                'currency' => $currency,
                'amount_minor' => mg_mcp_draft_integer($rule['amount_minor'] ?? null, 0, 1000000000, 'amount'),
                'rate_bps' => mg_mcp_draft_integer($rule['rate_bps'] ?? null, 0, 10000, 'rate'),
                'cap_minor' => mg_mcp_draft_integer($rule['cap_minor'] ?? null, 0, 1000000000, 'cap'),
                'conditions' => mg_mcp_creator_campaign_proposal_json(is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [], 'compensation conditions', 10000),
            ], static fn(mixed $value): bool => $value !== null && $value !== '');
        }
        return ['rules' => $clean, 'financial_execution_enabled' => false];
    }

    if ($kind === 'attribution.propose') return [
        'model' => mg_mcp_creator_campaign_proposal_enum($values['model'] ?? 'last_touch', ['first_touch','last_touch','direct'], 'attribution model', true, 'last_touch'),
        'click_window_days' => mg_mcp_draft_integer($values['click_window_days'] ?? null, 1, 365, 'click window', 30),
        'conversion_window_days' => mg_mcp_draft_integer($values['conversion_window_days'] ?? null, 1, 365, 'conversion window', 30),
        'channels' => mg_mcp_creator_campaign_proposal_json(is_array($values['channels'] ?? null) ? array_values($values['channels']) : [], 'attribution channels', 10000),
        'manual_override_enabled' => false,
    ];

    if ($kind === 'budget.propose') {
        $currency = strtoupper(mg_mcp_draft_text($values['currency'] ?? 'USD', 3, 'currency'));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) throw new MgMcpDraftException('Invalid currency.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
        return [
            'currency' => $currency,
            'limit_minor' => mg_mcp_draft_integer($values['limit_minor'] ?? null, 0, 100000000000, 'budget limit'),
            'allow_overage' => mg_mcp_creator_campaign_proposal_bool($values['allow_overage'] ?? false),
            'overage_limit_minor' => mg_mcp_draft_integer($values['overage_limit_minor'] ?? null, 0, 100000000000, 'overage limit', 0),
            'funding_or_reservation_enabled' => false,
        ];
    }

    if ($kind === 'rights.propose') return [
        'license_scope' => mg_mcp_draft_text($values['license_scope'] ?? '', 500, 'license scope'),
        'channels' => mg_mcp_creator_campaign_proposal_json(is_array($values['channels'] ?? null) ? array_values($values['channels']) : [], 'rights channels', 10000),
        'territory' => mg_mcp_draft_text($values['territory'] ?? '', 250, 'territory'),
        'duration_days' => mg_mcp_draft_integer($values['duration_days'] ?? null, 1, 36500, 'rights duration'),
        'exclusive' => mg_mcp_creator_campaign_proposal_bool($values['exclusive'] ?? false),
        'usage_notes' => mg_mcp_draft_multiline($values['usage_notes'] ?? '', 10000, 'usage notes'),
        'agreement_mutation_enabled' => false,
    ];

    if ($kind === 'terms.propose') return [
        'summary' => mg_mcp_draft_text($values['summary'] ?? '', 1000, 'terms summary'),
        'terms_text' => mg_mcp_draft_multiline($values['terms_text'] ?? '', 30000, 'terms text', true),
        'change_summary' => mg_mcp_draft_multiline($values['change_summary'] ?? '', 2000, 'change summary'),
        'requires_reacceptance' => mg_mcp_creator_campaign_proposal_bool($values['requires_reacceptance'] ?? true, true),
        'accepted_agreement_mutation_enabled' => false,
    ];

    if ($kind === 'invitation.draft') {
        mg_mcp_creator_campaign_proposal_creator($pdo, $values['creator_profile_id'] ?? '');
        return array_filter([
            'creator_profile_id' => mg_mcp_creator_campaign_proposal_id($values['creator_profile_id'] ?? '', 'creator_profile_id'),
            'invitation_message' => mg_mcp_draft_multiline($values['invitation_message'] ?? '', 5000, 'invitation message', true),
            'response_deadline_at' => mg_mcp_draft_datetime($values['response_deadline_at'] ?? '', 'response deadline'),
            'internal_note' => mg_mcp_draft_multiline($values['internal_note'] ?? '', 2000, 'internal note'),
            'send_enabled' => false,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    if ($kind === 'message.draft') {
        $participant = mg_mcp_creator_campaign_proposal_participant($pdo, $context, $campaign ?? throw new MgMcpDraftException('Campaign is required.'), $values['participant_id'] ?? '', false);
        return array_filter([
            'participant_id' => $participant['public_id'] ?? null,
            'audience_summary' => mg_mcp_draft_text($values['audience_summary'] ?? '', 500, 'audience summary'),
            'subject' => mg_mcp_draft_text($values['subject'] ?? '', 190, 'subject', false),
            'body' => mg_mcp_draft_multiline($values['body'] ?? '', 10000, 'message body', true),
            'channel' => mg_mcp_creator_campaign_proposal_enum($values['channel'] ?? 'in_app', ['in_app','email','sms'], 'message channel', true, 'in_app'),
            'send_after' => mg_mcp_draft_datetime($values['send_after'] ?? '', 'send after'),
            'send_or_schedule_enabled' => false,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    if ($kind === 'submission_feedback.draft') return [
        'submission_id' => mg_mcp_creator_campaign_proposal_id($values['submission_id'] ?? '', 'submission_id'),
        'recommendation' => mg_mcp_creator_campaign_proposal_enum($values['recommendation'] ?? '', ['approve','request_revision','reject','request_information'], 'feedback recommendation'),
        'feedback' => mg_mcp_draft_multiline($values['feedback'] ?? '', 10000, 'submission feedback', true),
        'required_changes' => mg_mcp_creator_campaign_proposal_json(is_array($values['required_changes'] ?? null) ? array_values($values['required_changes']) : [], 'required changes', 20000),
        'review_decision_enabled' => false,
    ];

    throw new MgMcpDraftException('Unsupported Creator Campaign proposal.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_UNSUPPORTED');
}
