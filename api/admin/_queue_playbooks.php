<?php
declare(strict_types=1);

function mg_queue_playbook_library(): array
{
    return [
        'billing_issue' => [
            'slug' => 'billing_issue',
            'title' => 'Billing issue',
            'category' => 'billing',
            'recommended_for' => ['billing'],
            'summary' => 'Verify payment/refund context, confirm customer or merchant impact, document the resolution, and close or escalate.',
            'checklist' => ['Review payment/order context','Confirm refund/dispute/subscription state','Add merchant/customer-facing next step','Set follow-up due date if unresolved','Close with resolution reason'],
            'default_template' => 'needs_billing_review',
        ],
        'risk_review' => [
            'slug' => 'risk_review',
            'title' => 'Risk review',
            'category' => 'risk',
            'recommended_for' => ['risk','flagged','review'],
            'summary' => 'Validate account behavior, preserve evidence, mark the review state, and escalate if policy or payment risk exists.',
            'checklist' => ['Review profile/account history','Check payment/security signals','Document evidence','Set review flag state','Escalate or clear flag'],
            'default_template' => 'needs_risk_review',
        ],
        'merchant_onboarding' => [
            'slug' => 'merchant_onboarding',
            'title' => 'Merchant onboarding',
            'category' => 'merchant_onboarding',
            'recommended_for' => ['merchant_onboarding'],
            'summary' => 'Help the merchant complete workspace, location, product, claim, and campaign readiness.',
            'checklist' => ['Verify merchant workspace','Confirm location and claim settings','Check first product status','Confirm payout/payment readiness','Send next onboarding step'],
            'default_template' => 'waiting_on_merchant',
        ],
        'catalog_product_issue' => [
            'slug' => 'catalog_product_issue',
            'title' => 'Catalog/product issue',
            'category' => 'product_catalog',
            'recommended_for' => ['product_catalog'],
            'summary' => 'Review catalog correctness, media, fulfillment, pricing, status, and publish readiness.',
            'checklist' => ['Open catalog/product record','Review media and description','Check pricing/fulfillment fields','Request correction or approve','Document catalog outcome'],
            'default_template' => 'catalog_correction_required',
        ],
        'crm_campaign_issue' => [
            'slug' => 'crm_campaign_issue',
            'title' => 'CRM/campaign issue',
            'category' => 'crm_campaigns',
            'recommended_for' => ['crm_campaigns'],
            'summary' => 'Check campaign setup, audience, reward template, delivery events, suppression, and follow-up state.',
            'checklist' => ['Review campaign settings','Check audience/contact state','Confirm reward/template mapping','Review delivery/suppression events','Set follow-up or resolution'],
            'default_template' => 'waiting_on_merchant',
        ],
        'general_support' => [
            'slug' => 'general_support',
            'title' => 'General support',
            'category' => 'support',
            'recommended_for' => ['support','general'],
            'summary' => 'Triage the request, assign ownership, document next step, and resolve or route to a specialized lane.',
            'checklist' => ['Classify the request','Confirm account/user context','Assign owner or lane','Document next step','Resolve or escalate'],
            'default_template' => 'resolved',
        ],
        'sla_breach' => [
            'slug' => 'sla_breach',
            'title' => 'SLA breach',
            'category' => 'support',
            'recommended_for' => ['breached','overdue','critical'],
            'summary' => 'Prioritize breached work, document why it breached, escalate ownership, and set a short follow-up window.',
            'checklist' => ['Review SLA due time','Identify blocker','Escalate owner/lane','Set immediate follow-up date','Document breach resolution'],
            'default_template' => 'escalated',
        ],
        'escalated_note' => [
            'slug' => 'escalated_note',
            'title' => 'Escalated note',
            'category' => 'support',
            'recommended_for' => ['escalated'],
            'summary' => 'Confirm escalation reason, assign a clear owner, document decision, and drive toward close.',
            'checklist' => ['Read escalation history','Confirm responsible owner','Choose resolution path','Set follow-up date','Close or keep escalated with reason'],
            'default_template' => 'escalated',
        ],
    ];
}

function mg_queue_resolution_templates(): array
{
    return [
        'resolved' => ['slug' => 'resolved', 'label' => 'Resolved', 'status' => 'resolved', 'flag_state' => null, 'reason' => 'Resolved using admin queue playbook.'],
        'escalated' => ['slug' => 'escalated', 'label' => 'Escalated', 'status' => 'escalated', 'flag_state' => null, 'reason' => 'Escalated using admin queue playbook.'],
        'waiting_on_merchant' => ['slug' => 'waiting_on_merchant', 'label' => 'Waiting on merchant', 'status' => 'waiting_on_merchant', 'flag_state' => null, 'reason' => 'Waiting on merchant response per playbook.'],
        'waiting_on_customer' => ['slug' => 'waiting_on_customer', 'label' => 'Waiting on customer', 'status' => 'waiting_on_customer', 'flag_state' => null, 'reason' => 'Waiting on customer response per playbook.'],
        'needs_billing_review' => ['slug' => 'needs_billing_review', 'label' => 'Needs billing review', 'status' => 'escalated', 'flag_state' => 'review', 'reason' => 'Billing review required per playbook.'],
        'needs_risk_review' => ['slug' => 'needs_risk_review', 'label' => 'Needs risk review', 'status' => 'escalated', 'flag_state' => 'review', 'reason' => 'Risk review required per playbook.'],
        'catalog_correction_required' => ['slug' => 'catalog_correction_required', 'label' => 'Catalog correction required', 'status' => 'waiting_on_merchant', 'flag_state' => 'review', 'reason' => 'Catalog correction required per playbook.'],
    ];
}

function mg_queue_playbook_recommend(array $note): string
{
    $status = (string)($note['status'] ?? '');
    $priority = (string)($note['priority'] ?? '');
    $category = (string)($note['category'] ?? 'general');
    $flag = (string)($note['flag_state'] ?? 'none');
    $sla = (string)($note['sla_status'] ?? '');
    if ($sla === 'breached' || $priority === 'critical') {
        return 'sla_breach';
    }
    if ($status === 'escalated') {
        return 'escalated_note';
    }
    if (in_array($flag, ['flagged','review'], true) || $category === 'risk') {
        return 'risk_review';
    }
    return match ($category) {
        'billing' => 'billing_issue',
        'merchant_onboarding' => 'merchant_onboarding',
        'product_catalog' => 'catalog_product_issue',
        'crm_campaigns' => 'crm_campaign_issue',
        'support', 'general' => 'general_support',
        default => 'general_support',
    };
}

function mg_queue_playbook_payload(?array $note = null): array
{
    $library = mg_queue_playbook_library();
    $templates = mg_queue_resolution_templates();
    return [
        'playbooks' => array_values($library),
        'templates' => array_values($templates),
        'recommended_slug' => $note ? mg_queue_playbook_recommend($note) : null,
        'recommended' => $note ? ($library[mg_queue_playbook_recommend($note)] ?? null) : null,
        'score' => ['section' => 'Admin playbooks', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
    ];
}

function mg_queue_playbook_slug(mixed $value, array $library): string
{
    $slug = strtolower(trim((string)$value));
    if (!isset($library[$slug])) {
        throw new MgAdminAccountException('Invalid playbook.', 422);
    }
    return $slug;
}

function mg_queue_template_slug(mixed $value, array $templates): string
{
    $slug = strtolower(trim((string)$value));
    if (!isset($templates[$slug])) {
        throw new MgAdminAccountException('Invalid resolution template.', 422);
    }
    return $slug;
}

function mg_queue_note_by_public_id(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = 'SELECT * FROM admin_user_notes WHERE public_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        throw new MgAdminAccountException('Queue note not found.', 404);
    }
    return $note;
}

function mg_queue_playbook_event(PDO $pdo, array $note, int $actorId, string $eventType, ?string $playbookSlug, ?string $templateSlug, ?array $checklist, string $reason): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_queue_playbook_events (public_id,note_id,target_user_id,admin_user_id,playbook_slug,template_slug,event_type,checklist_json,reason,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_public_uuid(),
        (int)$note['id'],
        (int)$note['target_user_id'],
        $actorId,
        $playbookSlug,
        $templateSlug,
        $eventType,
        $checklist !== null ? json_encode($checklist, JSON_UNESCAPED_SLASHES) : null,
        $reason,
    ]);
}

function mg_queue_playbook_notice(PDO $pdo, array $note, int $actorId, string $type, string $title, string $message, array $metadata = []): void
{
    mg_queue_notice_create($pdo, [
        'note_id' => (int)$note['id'],
        'target_user_id' => (int)$note['target_user_id'],
        'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
        'actor_user_id' => $actorId,
        'notification_type' => $type,
        'severity' => $type === 'checklist_completed' ? 'info' : 'warning',
        'title' => $title,
        'message' => $message,
        'metadata' => $metadata + ['note_public_id' => (string)$note['public_id']],
    ]);
}

function mg_queue_apply_playbook(PDO $pdo, array $note, int $actorId, string $playbookSlug, string $reason): array
{
    $library = mg_queue_playbook_library();
    $playbook = $library[$playbookSlug];
    $checklist = array_map(static fn(string $item): array => ['label' => $item, 'done' => false], $playbook['checklist']);
    $stmt = $pdo->prepare('UPDATE admin_user_notes SET playbook_slug = ?, resolution_template_slug = ?, playbook_checklist_json = ?, playbook_applied_at = NOW(), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$playbookSlug, $playbook['default_template'], json_encode($checklist, JSON_UNESCAPED_SLASHES), (int)$note['id']]);
    mg_queue_playbook_event($pdo, $note, $actorId, 'playbook_applied', $playbookSlug, (string)$playbook['default_template'], $checklist, $reason);
    mg_queue_playbook_notice($pdo, $note, $actorId, 'playbook_applied', 'Playbook applied', 'An admin playbook was applied to a follow-up queue item.', ['playbook_slug' => $playbookSlug]);
    return ['playbook' => $playbook, 'checklist' => $checklist];
}

function mg_queue_apply_template(PDO $pdo, array $note, int $actorId, string $templateSlug, string $reason): array
{
    $templates = mg_queue_resolution_templates();
    $template = $templates[$templateSlug];
    $sets = ['resolution_template_slug = ?', 'updated_at = NOW()'];
    $params = [$templateSlug];
    if ($template['status'] !== null) {
        $sets[] = 'status = ?';
        $params[] = $template['status'];
        if ($template['status'] === 'resolved') {
            $sets[] = 'resolved_at = NOW()';
            $sets[] = 'closed_at = NOW()';
            $sets[] = 'sla_status = "resolved"';
        }
    }
    if ($template['flag_state'] !== null) {
        $sets[] = 'flag_state = ?';
        $params[] = $template['flag_state'];
    }
    $params[] = (int)$note['id'];
    $stmt = $pdo->prepare('UPDATE admin_user_notes SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);
    mg_queue_playbook_event($pdo, $note, $actorId, 'template_used', $note['playbook_slug'] !== null ? (string)$note['playbook_slug'] : null, $templateSlug, null, $reason);
    mg_queue_playbook_notice($pdo, $note, $actorId, 'template_used', 'Resolution template used', 'A resolution template was applied to a follow-up queue item.', ['template_slug' => $templateSlug]);
    return $template;
}

function mg_queue_update_checklist(PDO $pdo, array $note, int $actorId, array $checklist, string $reason): array
{
    $normalized = [];
    foreach ($checklist as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '' || strlen($label) > 180) {
            continue;
        }
        $normalized[] = ['label' => $label, 'done' => !empty($item['done'])];
    }
    if (!$normalized) {
        throw new MgAdminAccountException('Checklist cannot be empty.', 422);
    }
    $complete = count(array_filter($normalized, static fn(array $item): bool => (bool)$item['done'])) === count($normalized);
    $stmt = $pdo->prepare('UPDATE admin_user_notes SET playbook_checklist_json = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([json_encode($normalized, JSON_UNESCAPED_SLASHES), (int)$note['id']]);
    mg_queue_playbook_event($pdo, $note, $actorId, $complete ? 'checklist_completed' : 'checklist_updated', $note['playbook_slug'] !== null ? (string)$note['playbook_slug'] : null, $note['resolution_template_slug'] !== null ? (string)$note['resolution_template_slug'] : null, $normalized, $reason);
    if ($complete) {
        mg_queue_playbook_notice($pdo, $note, $actorId, 'checklist_completed', 'Playbook checklist completed', 'A playbook checklist was completed for a follow-up queue item.', []);
    }
    return ['checklist' => $normalized, 'completed' => $complete];
}
