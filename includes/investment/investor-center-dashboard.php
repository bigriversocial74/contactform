<?php
declare(strict_types=1);

function mg_investor_center_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_investor_center_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_investor_center_snapshot(PDO $pdo): array
{
    $access = [
        'pending' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_access_requests WHERE status="pending"'),
        'more_information' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_access_requests WHERE status="more_information_requested"'),
        'active' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_profiles WHERE status="active"'),
        'revoked' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_profiles WHERE status="revoked"'),
        'inconsistent' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_profiles ip LEFT JOIN user_roles ur ON ur.user_id=ip.user_id LEFT JOIN roles r ON r.id=ur.role_id AND r.slug="investor" WHERE (ip.status="active" AND r.id IS NULL) OR (ip.status<>"active" AND r.id IS NOT NULL)'),
    ];

    $pipeline = [
        'active' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE stage NOT IN ("passed","declined","archived")'),
        'overdue_followups' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE next_follow_up_at IS NOT NULL AND next_follow_up_at<NOW() AND stage NOT IN ("passed","declined","archived")'),
        'meetings' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE stage="meeting_scheduled"'),
        'diligence' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE stage="due_diligence"'),
        'soft_committed' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE stage="soft_committed"'),
        'funded' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_pipeline_records WHERE stage="funded"'),
    ];

    $diligence = [
        'open_requests' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_diligence_requests WHERE status NOT IN ("answered","closed","withdrawn","declined")'),
        'urgent_requests' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_diligence_requests WHERE priority="urgent" AND status NOT IN ("answered","closed","withdrawn","declined")'),
        'overdue_requests' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_diligence_requests WHERE due_at IS NOT NULL AND due_at<NOW() AND status NOT IN ("answered","closed","withdrawn","declined")'),
        'published_documents' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_dataroom_documents WHERE status="published" AND (expires_at IS NULL OR expires_at>NOW())'),
        'expiring_documents' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_dataroom_documents WHERE status="published" AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)'),
        'draft_communications' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_communications WHERE status IN ("draft","in_review","changes_required")'),
    ];

    $closing = [
        'active_records' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_closing_records WHERE status NOT IN ("closing_complete","withdrawn","declined")'),
        'pending_verifications' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_financial_verification_requests WHERE status="pending"'),
        'funded_investors' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investor_closing_records WHERE verified_funded_cents>0 AND status NOT IN ("withdrawn","declined")'),
        'incomplete_packets' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_closing_packets WHERE status NOT IN ("complete","completed","archived")'),
        'open_compliance' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_compliance_requirements WHERE status NOT IN ("confirmed","approved","not_applicable")'),
        'overdue_compliance' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_compliance_requirements WHERE (status="overdue" OR (due_at IS NOT NULL AND due_at<NOW())) AND status NOT IN ("confirmed","approved","not_applicable")'),
        'verified_funded_cents' => mg_investor_center_scalar($pdo, 'SELECT COALESCE(SUM(verified_funded_cents),0) FROM investor_closing_records WHERE status NOT IN ("withdrawn","declined")'),
    ];

    $governance = [
        'upcoming_meetings' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_board_meetings WHERE starts_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) AND status NOT IN ("cancelled","completed")'),
        'due_obligations' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_reporting_obligations WHERE due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) AND status NOT IN ("completed","cancelled","not_applicable")'),
        'overdue_obligations' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_reporting_obligations WHERE due_at<NOW() AND status NOT IN ("completed","cancelled","not_applicable")'),
        'unacknowledged_notices' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_material_notice_recipients WHERE status IN ("published","viewed")'),
        'published_tax_documents' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_tax_documents WHERE status="published"'),
        'draft_tax_documents' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_tax_documents WHERE status IN ("draft","in_review","changes_required","approved")'),
    ];

    $rounds = [
        'official' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_rounds WHERE status NOT IN ("draft","archived","cancelled")'),
        'published' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_round_publication WHERE publication_status IN ("private_preview","published")'),
        'open' => mg_investor_center_scalar($pdo, 'SELECT COUNT(*) FROM investment_rounds WHERE status IN ("open","minimum_reached","closing")'),
    ];

    $work = [];
    $definitions = [
        ['count' => $access['pending'], 'severity' => 'high', 'label' => 'Investor access requests await review', 'href' => '/admin/investor-access-requests.php?status=pending'],
        ['count' => $access['inconsistent'], 'severity' => 'critical', 'label' => 'Investor role/profile records require repair', 'href' => '/admin/investor-access-requests.php'],
        ['count' => $pipeline['overdue_followups'], 'severity' => 'high', 'label' => 'Investor follow-ups are overdue', 'href' => '/admin/investor-pipeline.php'],
        ['count' => $diligence['urgent_requests'], 'severity' => 'critical', 'label' => 'Urgent diligence requests are open', 'href' => '/admin/investor-diligence.php'],
        ['count' => $diligence['overdue_requests'], 'severity' => 'high', 'label' => 'Diligence requests are overdue', 'href' => '/admin/investor-diligence.php'],
        ['count' => $diligence['expiring_documents'], 'severity' => 'normal', 'label' => 'Data Room documents expire within 30 days', 'href' => '/admin/investor-diligence.php'],
        ['count' => $closing['pending_verifications'], 'severity' => 'critical', 'label' => 'Financial verification requests await maker/checker review', 'href' => '/admin/investment-closing.php'],
        ['count' => $closing['overdue_compliance'], 'severity' => 'critical', 'label' => 'Closing compliance requirements are overdue', 'href' => '/admin/investment-closing.php'],
        ['count' => $governance['overdue_obligations'], 'severity' => 'high', 'label' => 'Investor reporting obligations are overdue', 'href' => '/admin/investor-governance.php'],
        ['count' => $governance['unacknowledged_notices'], 'severity' => 'normal', 'label' => 'Published material notices remain unacknowledged', 'href' => '/admin/investor-governance.php'],
    ];
    foreach ($definitions as $item) {
        if ((int)$item['count'] > 0) {
            $work[] = $item;
        }
    }

    return compact('access', 'pipeline', 'diligence', 'closing', 'governance', 'rounds', 'work');
}
