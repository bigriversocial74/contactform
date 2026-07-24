<?php
declare(strict_types=1);

/**
 * Investor Module Audit Hardening v1
 *
 * This layer preserves the Phase 1–5 authorities while adding stricter
 * publication, immutability, privacy and transaction controls discovered by
 * the full investor-module audit.
 */

function mg_investment_audit_transaction(PDO $pdo, callable $callback): mixed
{
    if ($pdo->inTransaction()) return $callback();
    $pdo->beginTransaction();
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_investment_audit_require_publishable_counsel(string $status, string $subject): void
{
    if (!in_array($status, ['approved', 'not_applicable'], true)) {
        throw new MgInvestmentException($subject . ' requires counsel approval or an explicit not-applicable status before publication.', 409);
    }
}

function mg_investment_audit_nullable_text(mixed $value, int $max = 500): ?string
{
    $text = mg_investment_text($value, $max);
    return $text === '' ? null : $text;
}

function mg_investment_audit_nullable_long_text(mixed $value, int $max = 12000): ?string
{
    $text = mg_investment_long_text($value, $max);
    return $text === '' ? null : $text;
}

function mg_investment_governance_dashboard_audited(PDO $pdo, array $filters = []): array
{
    $roundPublicId = trim((string)($filters['round_id'] ?? ''));
    $round = null;
    $roundId = null;
    if ($roundPublicId !== '') {
        $round = mg_investment_governance_round($pdo, $roundPublicId);
        $roundId = (int)$round['id'];
    }

    $rounds = $pdo->query('SELECT public_id,public_name,status,instrument_type,target_raise_cents,signed_cents,funded_cents,counsel_status FROM investment_rounds ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $participants = $pdo->query('SELECT p.*,COUNT(a.id) AS appointment_count,SUM(a.status="active") AS active_appointment_count FROM investment_governance_participants p LEFT JOIN investment_governance_appointments a ON a.participant_id=p.id GROUP BY p.id ORDER BY p.status,p.display_name')->fetchAll(PDO::FETCH_ASSOC);

    $meetingWhere = $roundId !== null ? ' WHERE (m.round_id=? OR m.round_id IS NULL) ' : '';
    $meetingsQ = $pdo->prepare('SELECT m.*,r.public_id AS round_public_id,r.public_name,COUNT(DISTINCT a.id) AS attendee_count,COUNT(DISTINCT g.id) AS agenda_count,COUNT(DISTINCT d.id) AS packet_count,MAX(v.version_number) AS latest_minutes_version FROM investment_board_meetings m LEFT JOIN investment_rounds r ON r.id=m.round_id LEFT JOIN investment_board_meeting_attendees a ON a.meeting_id=m.id LEFT JOIN investment_board_agenda_items g ON g.meeting_id=m.id LEFT JOIN investment_board_packet_documents d ON d.meeting_id=m.id LEFT JOIN investment_board_minute_versions v ON v.meeting_id=m.id' . $meetingWhere . ' GROUP BY m.id ORDER BY m.starts_at DESC');
    $meetingsQ->execute($roundId !== null ? [$roundId] : []);
    $meetings = $meetingsQ->fetchAll(PDO::FETCH_ASSOC);

    $consentWhere = $roundId !== null ? ' WHERE (c.round_id=? OR c.round_id IS NULL) ' : '';
    $consentsQ = $pdo->prepare('SELECT c.*,r.public_id AS round_public_id,r.public_name,b.public_id AS batch_public_id,b.batch_name,COUNT(cp.id) AS participant_count,SUM(cp.response="approved") AS approved_count,SUM(cp.response="declined") AS declined_count,SUM(cp.response="abstained") AS abstained_count,SUM(cp.response="pending") AS pending_count FROM investment_written_consents c LEFT JOIN investment_rounds r ON r.id=c.round_id LEFT JOIN investment_closing_batches b ON b.id=c.closing_batch_id LEFT JOIN investment_consent_participants cp ON cp.consent_id=c.id' . $consentWhere . ' GROUP BY c.id ORDER BY c.created_at DESC');
    $consentsQ->execute($roundId !== null ? [$roundId] : []);
    $consents = $consentsQ->fetchAll(PDO::FETCH_ASSOC);

    $rightsWhere = $roundId !== null ? ' WHERE ir.round_id=? ' : '';
    $rightsQ = $pdo->prepare('SELECT ir.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email FROM investment_investor_rights ir INNER JOIN investment_rounds r ON r.id=ir.round_id INNER JOIN users u ON u.id=ir.investor_user_id' . $rightsWhere . ' ORDER BY ir.updated_at DESC');
    $rightsQ->execute($roundId !== null ? [$roundId] : []);
    $rights = $rightsQ->fetchAll(PDO::FETCH_ASSOC);

    $obligationWhere = $roundId !== null ? ' WHERE o.round_id=? ' : '';
    $obligationsQ = $pdo->prepare('SELECT o.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,au.full_name AS assigned_name,au.display_name AS assigned_display_name FROM investment_reporting_obligations o INNER JOIN investment_rounds r ON r.id=o.round_id LEFT JOIN users u ON u.id=o.investor_user_id LEFT JOIN users au ON au.id=o.assigned_user_id' . $obligationWhere . ' ORDER BY FIELD(o.status,"overdue","counsel_review","internal_review","in_progress","planned","ready","completed","waived","cancelled"),o.due_at');
    $obligationsQ->execute($roundId !== null ? [$roundId] : []);
    $obligations = $obligationsQ->fetchAll(PDO::FETCH_ASSOC);
    $now = time();
    foreach ($obligations as &$obligation) {
        if (
            !empty($obligation['due_at'])
            && strtotime((string)$obligation['due_at']) < $now
            && in_array((string)$obligation['status'], ['planned','in_progress','internal_review','counsel_review','ready'], true)
        ) {
            $obligation['status'] = 'overdue';
        }
    }
    unset($obligation);

    $holdingsWhere = $roundId !== null ? ' WHERE h.round_id=? ' : '';
    $holdingsQ = $pdo->prepare('SELECT h.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email FROM investment_holdings_references h INNER JOIN investment_rounds r ON r.id=h.round_id INNER JOIN users u ON u.id=h.investor_user_id' . $holdingsWhere . ' ORDER BY h.generated_at DESC');
    $holdingsQ->execute($roundId !== null ? [$roundId] : []);
    $holdings = $holdingsQ->fetchAll(PDO::FETCH_ASSOC);

    $taxWhere = $roundId !== null ? ' WHERE td.round_id=? ' : '';
    $taxQ = $pdo->prepare('SELECT td.*,r.public_id AS round_public_id,r.public_name,u.full_name,u.display_name,u.email,v.public_id AS version_public_id,v.external_url,v.external_reference,v.status AS version_status,v.published_at AS version_published_at FROM investment_tax_documents td INNER JOIN investment_rounds r ON r.id=td.round_id INNER JOIN users u ON u.id=td.investor_user_id LEFT JOIN investment_tax_document_versions v ON v.tax_document_id=td.id AND v.version_number=td.current_version_number' . $taxWhere . ' ORDER BY td.reporting_year DESC,td.updated_at DESC');
    $taxQ->execute($roundId !== null ? [$roundId] : []);
    $tax = $taxQ->fetchAll(PDO::FETCH_ASSOC);

    $noticeWhere = $roundId !== null ? ' WHERE (n.round_id=? OR n.round_id IS NULL) ' : '';
    $noticesQ = $pdo->prepare('SELECT n.*,r.public_id AS round_public_id,r.public_name,COUNT(nr.id) AS recipient_count,SUM(nr.status IN ("viewed","acknowledged")) AS viewed_count,SUM(nr.status="acknowledged") AS acknowledged_count FROM investment_material_notices n LEFT JOIN investment_rounds r ON r.id=n.round_id LEFT JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id' . $noticeWhere . ' GROUP BY n.id ORDER BY n.created_at DESC');
    $noticesQ->execute($roundId !== null ? [$roundId] : []);
    $notices = $noticesQ->fetchAll(PDO::FETCH_ASSOC);

    $appointmentWhere = $roundId !== null ? ' WHERE (a.round_id=? OR a.round_id IS NULL) ' : '';
    $appointmentsQ = $pdo->prepare('SELECT a.*,p.public_id AS participant_public_id,p.display_name,p.organization,r.public_id AS round_public_id,r.public_name FROM investment_governance_appointments a INNER JOIN investment_governance_participants p ON p.id=a.participant_id LEFT JOIN investment_rounds r ON r.id=a.round_id' . $appointmentWhere . ' ORDER BY a.status,a.starts_at DESC');
    $appointmentsQ->execute($roundId !== null ? [$roundId] : []);
    $appointments = $appointmentsQ->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'active_participants' => count(array_filter($participants, static fn(array $p): bool => (string)$p['status'] === 'active')),
        'upcoming_meetings' => count(array_filter($meetings, static fn(array $m): bool => strtotime((string)$m['starts_at']) >= time() && !in_array((string)$m['status'], ['cancelled','archived'], true))),
        'open_consents' => count(array_filter($consents, static fn(array $c): bool => in_array((string)$c['status'], ['internal_review','counsel_review','approved_for_execution','collecting'], true))),
        'active_rights' => count(array_filter($rights, static fn(array $r): bool => (string)$r['status'] === 'active')),
        'overdue_obligations' => count(array_filter($obligations, static fn(array $o): bool => (string)$o['status'] === 'overdue')),
        'published_tax_documents' => count(array_filter($tax, static fn(array $t): bool => (string)$t['status'] === 'published' && (string)($t['version_status'] ?? '') === 'published')),
        'published_notices' => count(array_filter($notices, static fn(array $n): bool => (string)$n['status'] === 'published')),
        'holdings_references' => count($holdings),
    ];

    return compact('summary','rounds','round','participants','appointments','meetings','consents','rights','obligations','holdings','tax','notices');
}

function mg_investment_governance_save_meeting_audited(PDO $pdo, array $actor, array $input): array
{
    $summaryStatus = (string)($input['summary_status'] ?? 'draft');
    $counselStatus = (string)($input['counsel_status'] ?? 'not_started');
    if ($summaryStatus === 'published') {
        mg_investment_require_permission($actor, 'admin.investment.governance.publish');
        mg_investment_audit_require_publishable_counsel($counselStatus, 'A funded-investor meeting summary');
        if ((string)($input['confidentiality'] ?? '') !== 'funded_investors_summary') {
            throw new MgInvestmentException('Published meeting summaries must use the funded-investors-summary confidentiality level.', 409);
        }
        if (trim((string)($input['round_id'] ?? '')) === '') {
            throw new MgInvestmentException('A round is required before publishing a funded-investor meeting summary.', 409);
        }
        if (!in_array((string)($input['status'] ?? ''), ['held','minutes_drafted','minutes_approved','archived'], true)) {
            throw new MgInvestmentException('A meeting summary may only be published after the meeting is held.', 409);
        }
        mg_investment_long_text($input['investor_visible_summary'] ?? '', 12000, 20, 'Investor-visible meeting summary');
    }

    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input, $summaryStatus): array {
        $publicId = trim((string)($input['meeting_id'] ?? ''));
        if ($publicId !== '') {
            $q = $pdo->prepare('SELECT * FROM investment_board_meetings WHERE public_id=? LIMIT 1 FOR UPDATE');
            $q->execute([mg_investment_text($publicId, 36, 36, 'Meeting identifier')]);
            $current = $q->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new MgInvestmentException('Board meeting not found.', 404);
            if ($current['summary_published_at'] !== null) {
                if (!in_array($summaryStatus, ['published','archived'], true)) {
                    throw new MgInvestmentException('A published meeting summary is immutable. Archive it instead of rewriting its published history.', 409);
                }
                $round = mg_investment_governance_round($pdo, $input['round_id'] ?? '', false);
                $newPublic = [
                    'round_id' => $round ? (int)$round['id'] : null,
                    'meeting_type' => (string)($input['meeting_type'] ?? 'regular_board'),
                    'title' => mg_investment_text($input['title'] ?? '', 220, 2, 'Meeting title'),
                    'starts_at' => mg_investment_governance_datetime($input['starts_at'] ?? '', true, 'Meeting start'),
                    'ends_at' => mg_investment_governance_datetime($input['ends_at'] ?? '', false, 'Meeting end'),
                    'location' => mg_investment_audit_nullable_text($input['location'] ?? '', 300),
                    'meeting_url' => mg_investment_url($input['meeting_url'] ?? ''),
                    'confidentiality' => (string)($input['confidentiality'] ?? 'board_only'),
                    'investor_visible_summary' => mg_investment_audit_nullable_long_text($input['investor_visible_summary'] ?? '', 12000),
                ];
                foreach ($newPublic as $field => $value) {
                    $existing = $current[$field] ?? null;
                    if ((string)($existing ?? '') !== (string)($value ?? '')) {
                        throw new MgInvestmentException('Published meeting-summary content is immutable. Archive it and create a corrected meeting record.', 409);
                    }
                }
            }
        }
        return mg_investment_governance_save_meeting($pdo, $actor, $input);
    });
}

function mg_investment_governance_save_packet_document_audited(PDO $pdo, array $actor, array $input): array
{
    if ((string)($input['status'] ?? 'draft') === 'published') {
        mg_investment_require_permission($actor, 'admin.investment.governance.publish');
        mg_investment_audit_require_publishable_counsel((string)($input['counsel_status'] ?? 'not_started'), 'A governance packet document');
        mg_investment_url($input['external_url'] ?? '', true);
    }
    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input): array {
        $meeting = mg_investment_governance_meeting($pdo, mg_investment_text($input['meeting_id'] ?? '', 36, 36, 'Meeting identifier'));
        $lock = $pdo->prepare('SELECT id FROM investment_board_meetings WHERE id=? FOR UPDATE');
        $lock->execute([(int)$meeting['id']]);
        return mg_investment_governance_save_packet_document($pdo, $actor, $input);
    });
}

function mg_investment_governance_save_minutes_audited(PDO $pdo, array $actor, array $input): array
{
    if ((string)($input['status'] ?? 'draft') === 'approved') {
        mg_investment_audit_require_publishable_counsel((string)($input['counsel_status'] ?? 'not_started'), 'Approved board minutes');
    }
    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input): array {
        $meeting = mg_investment_governance_meeting($pdo, mg_investment_text($input['meeting_id'] ?? '', 36, 36, 'Meeting identifier'));
        $lock = $pdo->prepare('SELECT id FROM investment_board_meetings WHERE id=? FOR UPDATE');
        $lock->execute([(int)$meeting['id']]);
        return mg_investment_governance_save_minutes($pdo, $actor, $input);
    });
}

function mg_investment_governance_save_consent_audited(PDO $pdo, array $actor, array $input): array
{
    $status = (string)($input['status'] ?? 'draft');
    if ($status === 'executed') {
        mg_investment_audit_require_publishable_counsel((string)($input['counsel_status'] ?? 'not_started'), 'An executed written consent');
        mg_investment_governance_datetime($input['effective_at'] ?? '', true, 'Consent effective date');
        mg_investment_url($input['executed_document_url'] ?? '', true);
        mg_investment_text($input['executed_document_reference'] ?? '', 220, 2, 'Executed document reference');
    }

    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input, $status): array {
        $publicId = trim((string)($input['consent_id'] ?? ''));
        if ($publicId !== '') {
            $q = $pdo->prepare('SELECT * FROM investment_written_consents WHERE public_id=? LIMIT 1 FOR UPDATE');
            $q->execute([mg_investment_text($publicId, 36, 36, 'Consent identifier')]);
            $current = $q->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new MgInvestmentException('Written consent not found.', 404);
            if ((string)$current['status'] === 'executed') {
                if ($status !== 'archived') {
                    throw new MgInvestmentException('An executed consent is immutable. Archive it and create a corrective consent if needed.', 409);
                }
                $round = mg_investment_governance_round($pdo, $input['round_id'] ?? '', false);
                $batchId = null;
                if (!empty($input['batch_id'])) {
                    $b = $pdo->prepare('SELECT id FROM investment_closing_batches WHERE public_id=? LIMIT 1');
                    $b->execute([mg_investment_text($input['batch_id'], 36, 36, 'Batch identifier')]);
                    $batchId = (int)$b->fetchColumn() ?: null;
                }
                $newPublic = [
                    'round_id' => $round ? (int)$round['id'] : null,
                    'closing_batch_id' => $batchId,
                    'consent_type' => (string)($input['consent_type'] ?? 'board'),
                    'title' => mg_investment_text($input['title'] ?? '', 220, 2, 'Consent title'),
                    'resolution_text' => mg_investment_long_text($input['resolution_text'] ?? '', 50000, 20, 'Resolution text'),
                    'approval_group' => mg_investment_text($input['approval_group'] ?? '', 180, 2, 'Approval group'),
                    'approval_threshold' => mg_investment_audit_nullable_text($input['approval_threshold'] ?? '', 180),
                    'effective_at' => mg_investment_governance_datetime($input['effective_at'] ?? ''),
                    'response_due_at' => mg_investment_governance_datetime($input['response_due_at'] ?? ''),
                    'executed_document_reference' => mg_investment_audit_nullable_text($input['executed_document_reference'] ?? '', 220),
                    'executed_document_url' => mg_investment_url($input['executed_document_url'] ?? ''),
                ];
                foreach ($newPublic as $field => $value) {
                    if ((string)($current[$field] ?? '') !== (string)($value ?? '')) {
                        throw new MgInvestmentException('Executed consent content is immutable. Archive it and create a corrective consent.', 409);
                    }
                }
            }
        }
        return mg_investment_governance_save_consent($pdo, $actor, $input);
    });
}

function mg_investment_governance_record_consent_response_audited(PDO $pdo, array $actor, array $input): array
{
    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input): array {
        $q = $pdo->prepare('SELECT status FROM investment_written_consents WHERE public_id=? LIMIT 1 FOR UPDATE');
        $q->execute([mg_investment_text($input['consent_id'] ?? '', 36, 36, 'Consent identifier')]);
        $status = (string)$q->fetchColumn();
        if ($status === '') throw new MgInvestmentException('Written consent not found.', 404);
        if (!in_array($status, ['approved_for_execution','collecting'], true)) {
            throw new MgInvestmentException('Consent responses are immutable after execution. Record responses only while external collection is open.', 409);
        }
        return mg_investment_governance_record_consent_response($pdo, $actor, $input);
    });
}

function mg_investment_governance_set_consent_visibility_audited(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investment.governance.publish');
    $visible = mg_investment_bool($input['investor_visible'] ?? false) ? 1 : 0;
    $publicId = mg_investment_text($input['consent_id'] ?? '', 36, 36, 'Consent identifier');
    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input, $visible, $publicId): array {
        $q = $pdo->prepare('SELECT * FROM investment_written_consents WHERE public_id=? LIMIT 1 FOR UPDATE');
        $q->execute([$publicId]);
        $consent = $q->fetch(PDO::FETCH_ASSOC);
        if (!$consent) throw new MgInvestmentException('Written consent not found.', 404);
        if ($visible === 1) {
            if ((string)$consent['status'] !== 'executed') throw new MgInvestmentException('Only executed consents may be shown to investors.', 409);
            mg_investment_audit_require_publishable_counsel((string)$consent['counsel_status'], 'An investor-visible executed consent');
            if (empty($consent['executed_document_url'])) throw new MgInvestmentException('An executed document URL is required before investor publication.', 409);
            if ((int)($consent['round_id'] ?? 0) < 1) throw new MgInvestmentException('A round is required before publishing a consent to investors.', 409);
        }
        $pdo->prepare('UPDATE investment_written_consents SET investor_visible=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$visible, (int)$actor['id'], (int)$consent['id']]);
        mg_audit('investment_written_consent_visibility_changed', 'investment_written_consent', ['consent_id'=>$publicId,'investor_visible'=>(bool)$visible], (int)$actor['id']);
        return mg_investment_governance_dashboard_audited($pdo, $input);
    });
}

function mg_investment_governance_save_right_audited(PDO $pdo, array $actor, array $input): array
{
    return mg_investment_audit_transaction($pdo, function () use ($pdo, $actor, $input): array {
        $publicId = trim((string)($input['right_id'] ?? ''));
        if ($publicId !== '') {
            $q = $pdo->prepare('SELECT * FROM investment_investor_rights WHERE public_id=? LIMIT 1 FOR UPDATE');
            $q->execute([mg_investment_text($publicId, 36, 36, 'Right identifier')]);
            $current = $q->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new MgInvestmentException('Investor right not found.', 404);
            if (in_array((string)$current['status'], ['active','suspended','expired','terminated'], true)) {
                $newCore = [
                    'investor_user_id' => (int)($input['investor_user_id'] ?? 0),
                    'right_type' => (string)($input['right_type'] ?? 'information'),
                    'title' => mg_investment_text($input['title'] ?? '', 220, 2, 'Right title'),
                    'description' => mg_investment_audit_nullable_long_text($input['description'] ?? '', 10000),
                    'source_document_reference' => mg_investment_text($input['source_document_reference'] ?? '', 220, 2, 'Source document reference'),
                    'source_document_url' => mg_investment_url($input['source_document_url'] ?? ''),
                    'cadence' => (string)($input['cadence'] ?? 'none'),
                    'custom_cadence' => mg_investment_audit_nullable_text($input['custom_cadence'] ?? '', 180),
                    'starts_at' => mg_investment_date($input['starts_at'] ?? null),
                    'expires_at' => mg_investment_date($input['expires_at'] ?? null),
                ];
                foreach ($newCore as $field => $value) {
                    if ((string)($current[$field] ?? '') !== (string)($value ?? '')) {
                        throw new MgInvestmentException('Activated investor-right terms are immutable. Terminate the current right and create a new counsel-approved record.', 409);
                    }
                }
            }
        }
        return mg_investment_governance_save_right($pdo, $actor, $input);
    });
}

function mg_investment_governance_save_tax_document_audited(PDO $pdo, array $actor, array $input): array
{
    $status = (string)($input['status'] ?? 'not_started');
    if (in_array($status, ['approved','published'], true)) {
        mg_investment_url($input['external_url'] ?? '', true);
    }
    $publicId = trim((string)($input['tax_document_id'] ?? ''));
    if ($publicId !== '') {
        $round = mg_investment_governance_round($pdo, $input['round_id'] ?? '');
        $q = $pdo->prepare('SELECT * FROM investment_tax_documents WHERE public_id=? AND round_id=? LIMIT 1');
        $q->execute([mg_investment_text($publicId, 36, 36, 'Tax document identifier'), (int)$round['id']]);
        $current = $q->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new MgInvestmentException('Tax document not found.', 404);
        if ((int)$current['investor_user_id'] !== (int)($input['investor_user_id'] ?? 0)) {
            throw new MgInvestmentException('Tax-document ownership is immutable. Create a new record for a different investor.', 409);
        }
        if ((int)$current['current_version_number'] > 0) {
            if ((string)$current['document_type'] !== (string)($input['document_type'] ?? 'other') || (int)$current['reporting_year'] !== (int)($input['reporting_year'] ?? date('Y'))) {
                throw new MgInvestmentException('A versioned tax-document type and reporting year are immutable. Create a new document record.', 409);
            }
        }
    }
    return mg_investment_governance_save_tax_document($pdo, $actor, $input);
}

function mg_investment_audit_notice_public_fields(PDO $pdo, array $input): array
{
    $round = mg_investment_governance_round($pdo, $input['round_id'] ?? '', false);
    return [
        'round_id' => $round ? (int)$round['id'] : null,
        'notice_type' => (string)($input['notice_type'] ?? 'other'),
        'title' => mg_investment_text($input['title'] ?? '', 220, 2, 'Notice title'),
        'body' => mg_investment_long_text($input['body'] ?? '', 30000, 20, 'Notice body'),
        'audience' => (string)($input['audience'] ?? 'funded_investors'),
        'effective_at' => mg_investment_governance_datetime($input['effective_at'] ?? ''),
        'expires_at' => mg_investment_governance_datetime($input['expires_at'] ?? ''),
        'related_document_url' => mg_investment_url($input['related_document_url'] ?? ''),
        'related_document_reference' => mg_investment_audit_nullable_text($input['related_document_reference'] ?? '', 220),
    ];
}

function mg_investment_governance_save_notice_audited(PDO $pdo, array $actor, array $input): array
{
    $safeInput = $input;
    $publicId = trim((string)($safeInput['notice_id'] ?? ''));
    if ($publicId !== '') {
        $q = $pdo->prepare('SELECT * FROM investment_material_notices WHERE public_id=? LIMIT 1');
        $q->execute([mg_investment_text($publicId, 36, 36, 'Notice identifier')]);
        $current = $q->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new MgInvestmentException('Material notice not found.', 404);
        if ($current['published_at'] !== null) {
            if ((string)($safeInput['status'] ?? '') !== 'archived') {
                throw new MgInvestmentException('A published material notice is immutable. Archive it and publish a corrective notice instead.', 409);
            }
            foreach (mg_investment_audit_notice_public_fields($pdo, $safeInput) as $field => $value) {
                if ((string)($current[$field] ?? '') !== (string)($value ?? '')) {
                    throw new MgInvestmentException('Published material-notice content and audience are immutable.', 409);
                }
            }
        }
    }

    $status = (string)($safeInput['status'] ?? 'draft');
    $audience = (string)($safeInput['audience'] ?? 'funded_investors');
    $round = mg_investment_governance_round($pdo, $safeInput['round_id'] ?? '', false);
    if ($status === 'published' && $audience !== 'board') {
        if (!$round) throw new MgInvestmentException('A round is required for investor notice audiences.', 409);
        if (in_array($audience, ['specific_investors','custom'], true)) {
            $raw = $safeInput['investor_user_ids'] ?? [];
            if (is_string($raw)) $raw = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$raw), static fn(int $id): bool => $id > 0)));
            if ($ids === []) throw new MgInvestmentException('Select at least one funded investor recipient.', 409);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare('SELECT DISTINCT investor_user_id FROM investor_closing_records WHERE round_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined") AND investor_user_id IN (' . $marks . ')');
            $q->execute([(int)$round['id'], ...$ids]);
            $eligible = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            if ($eligible === []) throw new MgInvestmentException('The selected recipients do not include a verified funded investor for this round.', 409);
            $safeInput['investor_user_ids'] = $eligible;
        } elseif ($audience === 'selected_investors') {
            $q = $pdo->prepare('SELECT COUNT(DISTINCT a.investor_user_id) FROM investment_round_access a INNER JOIN investor_closing_records c ON c.round_id=a.round_id AND c.investor_user_id=a.investor_user_id AND c.verified_funded_cents>0 AND c.status NOT IN ("withdrawn","declined") WHERE a.round_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW())');
            $q->execute([(int)$round['id']]);
            if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('The selected-investor audience contains no verified funded investors.', 409);
        }
    }

    $result = mg_investment_governance_save_notice($pdo, $actor, $safeInput);
    if ($status === 'published' && $round && $audience !== 'board') {
        $q = $pdo->prepare('SELECT id FROM investment_material_notices WHERE public_id=? LIMIT 1');
        $q->execute([$publicId !== '' ? $publicId : (string)($safeInput['notice_id'] ?? '')]);
        $noticeId = (int)$q->fetchColumn();
        if ($noticeId < 1) {
            $q = $pdo->prepare('SELECT id FROM investment_material_notices WHERE round_id=? AND title=? ORDER BY id DESC LIMIT 1');
            $q->execute([(int)$round['id'], mg_investment_text($safeInput['title'] ?? '', 220, 2, 'Notice title')]);
            $noticeId = (int)$q->fetchColumn();
        }
        if ($noticeId > 0) {
            $cleanup = $pdo->prepare('UPDATE investment_material_notice_recipients nr LEFT JOIN investor_closing_records c ON c.round_id=? AND c.investor_user_id=nr.investor_user_id AND c.verified_funded_cents>0 AND c.status NOT IN ("withdrawn","declined") SET nr.status="revoked",nr.updated_at=NOW() WHERE nr.notice_id=? AND c.id IS NULL');
            $cleanup->execute([(int)$round['id'], $noticeId]);
        }
    }
    return mg_investment_governance_dashboard_audited($pdo, $safeInput);
}

function mg_investment_portal_data_v5_audited(PDO $pdo, array $user): array
{
    $base = mg_investment_portal_data_v5($pdo, $user);
    $userId = (int)$user['id'];
    foreach ($base['rounds'] as &$portalRound) {
        if (!is_array($portalRound['governance'] ?? null)) continue;
        $q = $pdo->prepare('SELECT id FROM investment_rounds WHERE public_id=? LIMIT 1');
        $q->execute([(string)$portalRound['id']]);
        $roundId = (int)$q->fetchColumn();
        if ($roundId < 1) {
            $portalRound['governance'] = null;
            continue;
        }

        $meetingQ = $pdo->prepare('SELECT public_id,meeting_type,title,starts_at,ends_at,location,meeting_url,status,investor_visible_summary,summary_published_at FROM investment_board_meetings WHERE round_id=? AND summary_status="published" AND confidentiality="funded_investors_summary" AND counsel_status IN ("approved","not_applicable") ORDER BY starts_at DESC');
        $meetingQ->execute([$roundId]);
        $packetQ = $pdo->prepare('SELECT d.public_id,d.document_type,d.title,d.external_url,d.version_number,d.published_at,m.public_id AS meeting_public_id,m.title AS meeting_title,m.starts_at FROM investment_board_packet_documents d INNER JOIN investment_board_meetings m ON m.id=d.meeting_id WHERE m.round_id=? AND d.status="published" AND d.confidentiality="funded_investors" AND d.counsel_status IN ("approved","not_applicable") ORDER BY m.starts_at DESC,d.title');
        $packetQ->execute([$roundId]);
        $rightsQ = $pdo->prepare('SELECT public_id,right_type,title,description,source_document_reference,source_document_url,cadence,custom_cadence,starts_at,expires_at FROM investment_investor_rights WHERE round_id=? AND investor_user_id=? AND status="active" AND investor_visible=1 AND counsel_status IN ("approved","not_applicable") AND (expires_at IS NULL OR expires_at>=CURRENT_DATE()) ORDER BY right_type,title');
        $rightsQ->execute([$roundId, $userId]);
        $noticeQ = $pdo->prepare('SELECT n.public_id,n.notice_type,n.title,n.body,n.effective_at,n.published_at,n.expires_at,n.related_document_url,n.related_document_reference,nr.status AS recipient_status,nr.first_viewed_at,nr.last_viewed_at,nr.acknowledged_at,nr.view_count FROM investment_material_notices n INNER JOIN investment_material_notice_recipients nr ON nr.notice_id=n.id WHERE n.round_id=? AND nr.investor_user_id=? AND n.status="published" AND n.counsel_status IN ("approved","not_applicable") AND nr.status IN ("published","viewed","acknowledged") AND (n.expires_at IS NULL OR n.expires_at>NOW()) ORDER BY n.published_at DESC');
        $noticeQ->execute([$roundId, $userId]);
        $consentQ = $pdo->prepare('SELECT public_id,consent_type,title,effective_at,executed_document_reference,executed_document_url FROM investment_written_consents WHERE round_id=? AND status="executed" AND investor_visible=1 AND counsel_status IN ("approved","not_applicable") ORDER BY effective_at DESC,updated_at DESC');
        $consentQ->execute([$roundId]);

        $portalRound['governance']['meetings'] = $meetingQ->fetchAll(PDO::FETCH_ASSOC);
        $portalRound['governance']['documents'] = $packetQ->fetchAll(PDO::FETCH_ASSOC);
        $portalRound['governance']['rights'] = $rightsQ->fetchAll(PDO::FETCH_ASSOC);
        $portalRound['governance']['notices'] = $noticeQ->fetchAll(PDO::FETCH_ASSOC);
        $portalRound['governance']['executed_consents'] = $consentQ->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($portalRound);
    return $base;
}

function mg_investment_portal_submit_diligence_v5_audited(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_submit_diligence_v4($pdo, $user, $input);
    return mg_investment_portal_data_v5_audited($pdo, $user);
}

function mg_investment_portal_submit_interest_v5_audited(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_submit_interest_v4($pdo, $user, $input);
    return mg_investment_portal_data_v5_audited($pdo, $user);
}

function mg_investment_portal_acknowledge_notice_v5_audited(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_acknowledge_notice_v5($pdo, $user, $input);
    return mg_investment_portal_data_v5_audited($pdo, $user);
}
