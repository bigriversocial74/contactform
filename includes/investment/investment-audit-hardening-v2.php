<?php
declare(strict_types=1);

function mg_investment_governance_save_notice_audited_v2(PDO $pdo, array $actor, array $input): array
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
        $roundId = (int)$round['id'];

        if (in_array($audience, ['specific_investors','custom'], true)) {
            $raw = $safeInput['investor_user_ids'] ?? [];
            if (is_string($raw)) $raw = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$raw), static fn(int $id): bool => $id > 0)));
            if ($ids === []) throw new MgInvestmentException('Select at least one funded investor recipient.', 409);

            $marks = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare('SELECT DISTINCT investor_user_id FROM investor_closing_records WHERE round_id=? AND verified_funded_cents>0 AND status NOT IN ("withdrawn","declined") AND investor_user_id IN (' . $marks . ')');
            $q->execute([$roundId, ...$ids]);
            $eligible = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            sort($eligible);
            sort($ids);
            if ($eligible !== $ids) {
                throw new MgInvestmentException('Every specific material-notice recipient must be a verified funded investor for the selected round.', 409);
            }
            $safeInput['investor_user_ids'] = $eligible;
        } elseif ($audience === 'selected_investors') {
            $allQ = $pdo->prepare('SELECT COUNT(DISTINCT investor_user_id) FROM investment_round_access WHERE round_id=? AND status="granted" AND (expires_at IS NULL OR expires_at>NOW())');
            $allQ->execute([$roundId]);
            $allSelected = (int)$allQ->fetchColumn();

            $fundedQ = $pdo->prepare('SELECT COUNT(DISTINCT a.investor_user_id) FROM investment_round_access a INNER JOIN investor_closing_records c ON c.round_id=a.round_id AND c.investor_user_id=a.investor_user_id AND c.verified_funded_cents>0 AND c.status NOT IN ("withdrawn","declined") WHERE a.round_id=? AND a.status="granted" AND (a.expires_at IS NULL OR a.expires_at>NOW())');
            $fundedQ->execute([$roundId]);
            $fundedSelected = (int)$fundedQ->fetchColumn();

            if ($allSelected < 1) throw new MgInvestmentException('The selected-investor audience is empty.', 409);
            if ($fundedSelected !== $allSelected) {
                throw new MgInvestmentException('Selected-investor governance notices may only be published when every selected recipient is verified funded. Use a specific-investor audience otherwise.', 409);
            }
        } elseif (in_array($audience, ['major_investors','rights_holders'], true)) {
            $q = $pdo->prepare('SELECT COUNT(DISTINCT r.investor_user_id) FROM investment_investor_rights r INNER JOIN investor_closing_records c ON c.round_id=r.round_id AND c.investor_user_id=r.investor_user_id AND c.verified_funded_cents>0 AND c.status NOT IN ("withdrawn","declined") WHERE r.round_id=? AND r.status="active" AND (?="rights_holders" OR r.right_type="major_investor")');
            $q->execute([$roundId, $audience]);
            if ((int)$q->fetchColumn() < 1) throw new MgInvestmentException('The selected rights-based audience contains no verified funded investors.', 409);
        }
    }

    mg_investment_governance_save_notice($pdo, $actor, $safeInput);
    return mg_investment_governance_dashboard_audited($pdo, $safeInput);
}
