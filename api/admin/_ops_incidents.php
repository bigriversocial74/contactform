<?php
declare(strict_types=1);

function mg_ops_incident_modes(): array
{
    return [
        'payment_outage' => ['slug'=>'payment_outage','title'=>'Payment outage','severity'=>'critical','escalation'=>'Payments owner / Stripe admin','impact'=>'Checkout, refunds, subscriptions, or tips may be blocked.','checks'=>['Confirm Stripe dashboard status','Review payment logs and recent failures','Check checkout session creation','Pause risky payment actions if needed'],'owner_checklist'=>['Assign payment owner','Post first status update','Confirm customer impact window','Track failed order count'],'resolution_checklist'=>['Confirm successful test payment','Review failed payment recovery','Post resolution summary','Monitor for recurrence']],
        'fulfillment_backlog' => ['slug'=>'fulfillment_backlog','title'=>'Fulfillment backlog','severity'=>'high','escalation'=>'Commerce operations owner','impact'=>'Customers or merchants may see delayed fulfillment, delivery, or redemption state changes.','checks'=>['Review fulfillment attention queue','Check stuck orders and delivery events','Identify backlog size','Prioritize critical merchants'],'owner_checklist'=>['Assign fulfillment owner','Run automation','Escalate blocked cases','Update merchant/customer impact'],'resolution_checklist'=>['Clear stuck fulfillment records','Confirm recent fulfillment success','Post backlog summary','Monitor aging queue']],
        'claim_redemption_issue' => ['slug'=>'claim_redemption_issue','title'=>'Claim/redemption issue','severity'=>'high','escalation'=>'Lifecycle operations owner','impact'=>'Customers may be unable to claim or redeem Microgifts.','checks'=>['Review claim/redemption errors','Check claim code and QR flows','Review location claim settings','Confirm redemption API health'],'owner_checklist'=>['Assign lifecycle owner','Identify affected claim paths','Document workaround','Escalate merchant location issues'],'resolution_checklist'=>['Verify claim and redemption success','Close affected queue items','Post resolution notes','Monitor redemption metrics']],
        'notification_delivery_issue' => ['slug'=>'notification_delivery_issue','title'=>'Notification delivery issue','severity'=>'medium','escalation'=>'Messaging operations owner','impact'=>'Recipients, merchants, or admins may miss delivery, reminder, or alert messages.','checks'=>['Review notification failure logs','Check provider status','Review queue/digest alerts','Confirm delivery suppression state'],'owner_checklist'=>['Assign messaging owner','Identify failed channels','Post temporary workaround','Notify admin team'],'resolution_checklist'=>['Confirm new delivery success','Retry eligible failed events','Post resolution update','Monitor new failures']],
        'fraud_risk_spike' => ['slug'=>'fraud_risk_spike','title'=>'Fraud/risk spike','severity'=>'critical','escalation'=>'Risk and security owner','impact'=>'Suspicious behavior may require account restrictions, payment review, or merchant/customer protection.','checks'=>['Review risk flags','Check payment disputes/refunds','Review account creation spikes','Preserve evidence'],'owner_checklist'=>['Assign risk owner','Restrict high-risk accounts if needed','Escalate payment risk','Document evidence'],'resolution_checklist'=>['Resolve or maintain restrictions','Confirm risk trend normalized','Post risk summary','Update playbooks if needed']],
        'merchant_onboarding_backlog' => ['slug'=>'merchant_onboarding_backlog','title'=>'Merchant onboarding backlog','severity'=>'medium','escalation'=>'Merchant success owner','impact'=>'Merchants may be delayed in launching products, claim settings, or campaigns.','checks'=>['Review onboarding queue','Identify blocked merchants','Check catalog/location readiness','Confirm payment setup blockers'],'owner_checklist'=>['Assign merchant success owner','Prioritize high-value merchants','Send next steps','Track follow-up dates'],'resolution_checklist'=>['Confirm merchant readiness','Close stale waiting items','Post backlog summary','Monitor new onboarding aging']],
        'catalog_publishing_issue' => ['slug'=>'catalog_publishing_issue','title'=>'Catalog publishing issue','severity'=>'medium','escalation'=>'Catalog operations owner','impact'=>'Products, media, or storefront catalog items may not publish correctly.','checks'=>['Review product/catalog queue','Check media and publish status','Inspect catalog correction flags','Confirm storefront visibility'],'owner_checklist'=>['Assign catalog owner','Prioritize affected merchants','Request corrections','Track publish blockers'],'resolution_checklist'=>['Confirm successful publish','Clear correction flags','Post catalog summary','Monitor product queue']],
    ];
}

function mg_ops_incident_mode(string $slug): array
{
    $modes = mg_ops_incident_modes();
    if (!isset($modes[$slug])) {
        throw new MgAdminAccountException('Invalid incident mode.', 422);
    }
    return $modes[$slug];
}

function mg_ops_incident_public_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid incident identifier.', 422);
    }
    return $id;
}

function mg_ops_incident_text(mixed $value, int $max = 600): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if ($text === '') {
        throw new MgAdminAccountException('Incident message is required.', 422);
    }
    return mb_substr($text, 0, $max);
}

function mg_ops_incident_choice(mixed $value, array $allowed, string $fallback): string
{
    $text = strtolower(trim((string)$value));
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function mg_ops_incident_checklist(array $mode): array
{
    $items = array_merge($mode['checks'], $mode['owner_checklist'], $mode['resolution_checklist']);
    return array_map(static fn(string $label): array => ['label'=>$label,'done'=>false], $items);
}

function mg_ops_incident_row(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = 'SELECT i.*, owner.email owner_email, owner.display_name owner_display_name, declared.email declared_email, declared.display_name declared_display_name FROM admin_ops_incidents i LEFT JOIN users owner ON owner.id = i.owner_user_id INNER JOIN users declared ON declared.id = i.declared_by_user_id WHERE i.public_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgAdminAccountException('Incident not found.', 404);
    }
    return $row;
}

function mg_ops_incident_update(PDO $pdo, int $incidentId, int $actorId, string $type, string $message, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_ops_incident_updates (public_id,incident_id,admin_user_id,update_type,message,metadata_json,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_public_uuid(), $incidentId, $actorId, $type, $message, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
}

function mg_ops_incident_notice(PDO $pdo, int $actorId, string $type, string $severity, string $title, string $message, array $metadata): void
{
    mg_queue_notice_create($pdo, ['note_id'=>null,'target_user_id'=>null,'assigned_admin_user_id'=>null,'actor_user_id'=>$actorId,'notification_type'=>$type,'severity'=>$severity,'title'=>$title,'message'=>$message,'metadata'=>$metadata]);
}

function mg_ops_incident_payload(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT i.public_id, i.mode_slug, i.title, i.severity, i.status, i.impact_summary, i.runbook_checklist_json, i.declared_at, i.updated_at, i.resolved_at, owner.email owner_email, owner.display_name owner_display_name, declared.email declared_email, declared.display_name declared_display_name FROM admin_ops_incidents i LEFT JOIN users owner ON owner.id = i.owner_user_id INNER JOIN users declared ON declared.id = i.declared_by_user_id WHERE i.status <> "resolved" ORDER BY CASE i.severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END, i.declared_at DESC LIMIT 12');
    $active = array_map(static fn(array $r): array => [
        'id'=>(string)$r['public_id'],
        'mode_slug'=>(string)$r['mode_slug'],
        'title'=>(string)$r['title'],
        'severity'=>(string)$r['severity'],
        'status'=>(string)$r['status'],
        'impact_summary'=>(string)$r['impact_summary'],
        'checklist'=>$r['runbook_checklist_json'] ? json_decode((string)$r['runbook_checklist_json'], true) : [],
        'declared_at'=>(string)$r['declared_at'],
        'updated_at'=>(string)$r['updated_at'],
        'resolved_at'=>$r['resolved_at'] !== null ? (string)$r['resolved_at'] : null,
        'owner'=>$r['owner_email'] !== null ? ['email'=>(string)$r['owner_email'], 'display_name'=>(string)($r['owner_display_name'] ?: $r['owner_email'])] : null,
        'declared_by'=>['email'=>(string)$r['declared_email'], 'display_name'=>(string)($r['declared_display_name'] ?: $r['declared_email'])],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    return ['modes'=>array_values(mg_ops_incident_modes()), 'active_incidents'=>$active, 'summary'=>['active_total'=>count($active), 'critical_total'=>count(array_filter($active, static fn(array $i): bool => $i['severity']==='critical')), 'score'=>['section'=>'Incident mode','score'=>10,'max'=>10,'status'=>'cleared']]];
}

function mg_ops_incident_declare(PDO $pdo, int $actorId, array $input): array
{
    $mode = mg_ops_incident_mode(strtolower(trim((string)($input['mode_slug'] ?? ''))));
    $severity = mg_ops_incident_choice($input['severity'] ?? $mode['severity'], ['low','medium','high','critical'], $mode['severity']);
    $title = mb_substr(trim((string)($input['title'] ?? $mode['title'])), 0, 180);
    $impact = mg_ops_incident_text($input['impact_summary'] ?? $mode['impact']);
    $ownerId = isset($input['owner_user_id']) && (int)$input['owner_user_id'] > 0 ? (int)$input['owner_user_id'] : null;
    $checklist = mg_ops_incident_checklist($mode);
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare('INSERT INTO admin_ops_incidents (public_id,mode_slug,title,severity,status,owner_user_id,declared_by_user_id,impact_summary,runbook_checklist_json,declared_at,updated_at) VALUES (?,?,?,?,"declared",?,?,?,?,NOW(),NOW())');
    $stmt->execute([$publicId, $mode['slug'], $title, $severity, $ownerId, $actorId, $impact, json_encode($checklist, JSON_UNESCAPED_SLASHES)]);
    $incidentId = (int)$pdo->lastInsertId();
    mg_ops_incident_update($pdo, $incidentId, $actorId, 'declared', $impact, ['mode_slug'=>$mode['slug'], 'severity'=>$severity]);
    mg_ops_incident_notice($pdo, $actorId, 'incident_declared', $severity === 'critical' ? 'critical' : 'warning', 'Operations incident declared', $title . ': ' . $impact, ['incident_id'=>$publicId, 'mode_slug'=>$mode['slug']]);
    return ['id'=>$publicId,'mode_slug'=>$mode['slug'],'title'=>$title,'severity'=>$severity,'status'=>'declared'];
}

function mg_ops_incident_apply(PDO $pdo, int $actorId, array $input): array
{
    $action = strtolower(trim((string)($input['action'] ?? 'status_update')));
    $incident = mg_ops_incident_row($pdo, mg_ops_incident_public_id($input['incident_id'] ?? null), true);
    $metadata = ['incident_id'=>(string)$incident['public_id'], 'mode_slug'=>(string)$incident['mode_slug']];
    if ($action === 'assign_owner') {
        $ownerId = max(1, (int)($input['owner_user_id'] ?? $actorId));
        $stmt = $pdo->prepare('UPDATE admin_ops_incidents SET owner_user_id = ?, status = IF(status="declared","investigating",status), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ownerId, (int)$incident['id']]);
        mg_ops_incident_update($pdo, (int)$incident['id'], $actorId, 'owner_assigned', 'Incident owner assigned.', $metadata + ['owner_user_id'=>$ownerId]);
        mg_ops_incident_notice($pdo, $actorId, 'incident_updated', 'info', 'Incident owner assigned', (string)$incident['title'], $metadata);
    } elseif ($action === 'update_status') {
        $status = mg_ops_incident_choice($input['status'] ?? 'investigating', ['declared','investigating','mitigating','monitoring','resolved'], 'investigating');
        $message = mg_ops_incident_text($input['message'] ?? 'Incident status updated.');
        $stmt = $pdo->prepare('UPDATE admin_ops_incidents SET status = ?, updated_at = NOW(), resolved_at = IF(?="resolved",NOW(),resolved_at) WHERE id = ?');
        $stmt->execute([$status, $status, (int)$incident['id']]);
        mg_ops_incident_update($pdo, (int)$incident['id'], $actorId, $status === 'resolved' ? 'resolved' : 'status_update', $message, $metadata + ['status'=>$status]);
        mg_ops_incident_notice($pdo, $actorId, $status === 'resolved' ? 'incident_resolved' : 'incident_updated', $status === 'resolved' ? 'info' : 'warning', $status === 'resolved' ? 'Incident resolved' : 'Incident status updated', $message, $metadata);
    } elseif ($action === 'change_severity') {
        $severity = mg_ops_incident_choice($input['severity'] ?? 'medium', ['low','medium','high','critical'], 'medium');
        $message = mg_ops_incident_text($input['message'] ?? 'Incident severity updated.');
        $stmt = $pdo->prepare('UPDATE admin_ops_incidents SET severity = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$severity, (int)$incident['id']]);
        mg_ops_incident_update($pdo, (int)$incident['id'], $actorId, 'severity_changed', $message, $metadata + ['severity'=>$severity]);
        mg_ops_incident_notice($pdo, $actorId, 'incident_updated', $severity === 'critical' ? 'critical' : 'warning', 'Incident severity changed', $message, $metadata);
    } elseif ($action === 'update_runbook') {
        $checklist = is_array($input['checklist'] ?? null) ? $input['checklist'] : [];
        $clean = [];
        foreach ($checklist as $item) {
            if (!is_array($item)) { continue; }
            $label = mb_substr(trim((string)($item['label'] ?? '')), 0, 180);
            if ($label !== '') { $clean[] = ['label'=>$label, 'done'=>!empty($item['done'])]; }
        }
        if (!$clean) { throw new MgAdminAccountException('Runbook checklist cannot be empty.', 422); }
        $stmt = $pdo->prepare('UPDATE admin_ops_incidents SET runbook_checklist_json = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($clean, JSON_UNESCAPED_SLASHES), (int)$incident['id']]);
        mg_ops_incident_update($pdo, (int)$incident['id'], $actorId, 'runbook_updated', 'Incident runbook checklist updated.', $metadata);
    } else {
        throw new MgAdminAccountException('Invalid incident action.', 422);
    }
    return ['id'=>(string)$incident['public_id'], 'action'=>$action, 'updated'=>true];
}
