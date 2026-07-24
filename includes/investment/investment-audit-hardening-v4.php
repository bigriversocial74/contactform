<?php
declare(strict_types=1);

function mg_investment_pipeline_save_interest_audited(PDO $pdo, array $actor, array $input): array
{
    mg_investment_require_permission($actor, 'admin.investor_pipeline.manage');
    $record = mg_investment_pipeline_record($pdo, mg_investment_text($input['investor_id'] ?? '', 36, 36, 'Investor identifier'));
    $round = mg_investment_pipeline_round($pdo, mg_investment_text($input['round_id'] ?? '', 36, 36, 'Round identifier'));

    $existingQ = $pdo->prepare('SELECT signed_cents,funded_cents FROM investor_round_interests WHERE round_id=? AND investor_user_id=? LIMIT 1');
    $existingQ->execute([(int)$round['id'], (int)$record['investor_user_id']]);
    $existing = $existingQ->fetch(PDO::FETCH_ASSOC) ?: ['signed_cents'=>0,'funded_cents'=>0];

    $requestedSigned = mg_investment_money($input['signed'] ?? 0);
    $requestedFunded = mg_investment_money($input['funded'] ?? 0);
    if ($requestedSigned !== (int)$existing['signed_cents'] || $requestedFunded !== (int)$existing['funded_cents']) {
        throw new MgInvestmentException('Signed and funded amounts are controlled by Closing Command Center maker/checker verification and cannot be edited from Investor Pipeline.', 409);
    }

    $status = (string)($input['status'] ?? 'invited');
    if ($status === 'signed' && (int)$existing['signed_cents'] < 1) {
        throw new MgInvestmentException('The signed stage requires an approved signed-amount verification.', 409);
    }
    if ($status === 'funded' && (int)$existing['funded_cents'] < 1) {
        throw new MgInvestmentException('The funded stage requires an approved funded-amount verification.', 409);
    }

    return mg_investment_audit_transaction($pdo, static fn(): array => mg_investment_pipeline_save_interest($pdo, $actor, $input));
}

function mg_investment_portal_profile_public_audited(array $profile): array
{
    return [
        'id' => (string)($profile['public_id'] ?? ''),
        'status' => (string)($profile['status'] ?? ''),
        'firm_name' => (string)($profile['firm_name'] ?? ''),
        'job_title' => $profile['job_title'] !== null ? (string)$profile['job_title'] : null,
        'website_url' => $profile['website_url'] !== null ? (string)$profile['website_url'] : null,
        'primary_social_url' => $profile['primary_social_url'] !== null ? (string)$profile['primary_social_url'] : null,
        'investor_type' => (string)($profile['investor_type'] ?? ''),
        'expected_investment_range' => (string)($profile['expected_investment_range'] ?? ''),
        'approved_at' => $profile['approved_at'] !== null ? (string)$profile['approved_at'] : null,
    ];
}

function mg_investment_portal_data_v5_final(PDO $pdo, array $user): array
{
    $data = mg_investment_portal_data_v5_audited($pdo, $user);
    $data['profile'] = mg_investment_portal_profile_public_audited(is_array($data['profile'] ?? null) ? $data['profile'] : []);
    return $data;
}

function mg_investment_portal_submit_diligence_v5_final(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_submit_diligence_v4($pdo, $user, $input);
    return mg_investment_portal_data_v5_final($pdo, $user);
}

function mg_investment_portal_submit_interest_v5_final(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_submit_interest_v4($pdo, $user, $input);
    return mg_investment_portal_data_v5_final($pdo, $user);
}

function mg_investment_portal_acknowledge_notice_v5_final(PDO $pdo, array $user, array $input): array
{
    mg_investment_portal_acknowledge_notice_v5($pdo, $user, $input);
    return mg_investment_portal_data_v5_final($pdo, $user);
}

function mg_investment_portal_accessible_round_audited(PDO $pdo, array $user, string $roundPublicId): array
{
    if (!in_array('investor', is_array($user['roles'] ?? null) ? $user['roles'] : [], true)) {
        throw new MgInvestmentException('Investor access is not active.', 403);
    }
    $userId = (int)$user['id'];
    $profileQ = $pdo->prepare('SELECT COUNT(*) FROM investor_profiles WHERE user_id=? AND status="active"');
    $profileQ->execute([$userId]);
    if ((int)$profileQ->fetchColumn() < 1) throw new MgInvestmentException('Investor profile is not active.', 403);

    $q = $pdo->prepare('SELECT r.* FROM investment_rounds r INNER JOIN investment_round_publication p ON p.round_id=r.id WHERE r.public_id=? AND p.publication_status IN ("private_preview","published") AND r.status IN ("private_preview","open","minimum_reached","closing","closed") AND (r.visibility="approved_investors" OR (r.visibility="selected_investors" AND EXISTS(SELECT 1 FROM investment_round_access a WHERE a.round_id=r.id AND a.investor_user_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW()))) OR (r.visibility="funded_investors" AND EXISTS(SELECT 1 FROM investor_round_interests ri WHERE ri.round_id=r.id AND ri.investor_user_id=? AND ri.funded_cents>0))) LIMIT 1');
    $q->execute([$roundPublicId, $userId, $userId]);
    $round = $q->fetch(PDO::FETCH_ASSOC);
    if (!$round) throw new MgInvestmentException('Investment round is not available.', 404);
    return $round;
}

function mg_investment_portal_event_v2_audited(PDO $pdo, array $user, array $input): array
{
    $event = (string)($input['event_type'] ?? '');
    if (!in_array($event, ['document_open','metric_view','round_view'], true)) {
        throw new MgInvestmentException('Invalid standard portal event.');
    }

    $roundPublicId = mg_investment_text($input['round_id'] ?? '', 36, 36, 'Round identifier');
    $subjectId = mg_investment_text($input['subject_id'] ?? '', 36, 1, 'Subject identifier');
    $round = mg_investment_portal_accessible_round_audited($pdo, $user, $roundPublicId);
    $publication = mg_investment_publication_get($pdo, (int)$round['id']);
    $sections = is_array($publication['sections'] ?? null) ? $publication['sections'] : [];
    $userId = (int)$user['id'];

    if ($event === 'round_view') {
        if (!hash_equals((string)$round['public_id'], $subjectId)) throw new MgInvestmentException('Round-view subject does not match the accessible round.', 409);
    } elseif ($event === 'metric_view') {
        if (empty($sections['evidence_metrics'])) throw new MgInvestmentException('Evidence metrics are not published for this round.', 404);
        $q = $pdo->prepare('SELECT COUNT(*) FROM investment_metrics WHERE public_id=? AND workspace_id=? AND investor_visible=1');
        $q->execute([$subjectId, (int)$round['workspace_id']]);
        if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('Evidence metric is not available.', 404);
    } else {
        if (empty($sections['documents'])) throw new MgInvestmentException('Documents are not published for this round.', 404);
        $allowed = ['approved_investors','public_summary'];
        $selectedQ = $pdo->prepare('SELECT COUNT(*) FROM investment_round_access WHERE round_id=? AND investor_user_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');
        $selectedQ->execute([(int)$round['id'], $userId]);
        if ((int)$selectedQ->fetchColumn() > 0) $allowed[] = 'selected_investors';
        $fundedQ = $pdo->prepare('SELECT COALESCE(SUM(funded_cents),0) FROM investor_round_interests WHERE round_id=? AND investor_user_id=? AND status NOT IN ("passed","declined","archived")');
        $fundedQ->execute([(int)$round['id'], $userId]);
        if ((int)$fundedQ->fetchColumn() > 0) $allowed[] = 'funded_investors';
        $marks = implode(',', array_fill(0, count($allowed), '?'));
        $q = $pdo->prepare('SELECT COUNT(*) FROM investment_documents WHERE public_id=? AND workspace_id=? AND status="published" AND visibility IN (' . $marks . ')');
        $q->execute([$subjectId, (int)$round['workspace_id'], ...$allowed]);
        if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('Investment document is not available.', 404);
    }

    mg_investment_portal_log($pdo, $userId, (int)$round['id'], $event, $subjectId, ['title'=>mg_investment_text($input['title'] ?? '', 220)]);
    return ['recorded'=>true];
}

function mg_investment_portal_event_final(PDO $pdo, array $user, array $input): array
{
    $event = (string)($input['event_type'] ?? '');
    if (in_array($event, ['document_open','metric_view','round_view'], true)) {
        return mg_investment_portal_event_v2_audited($pdo, $user, $input);
    }
    return mg_investment_portal_event_v5($pdo, $user, $input);
}
