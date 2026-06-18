<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

mg_require_method('GET');
$user = mg_content_review_require(false);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 24)));
$status = strtolower(trim((string)($_GET['status'] ?? 'active')));
$type = strtolower(trim((string)($_GET['subject_type'] ?? 'all')));
$severity = strtolower(trim((string)($_GET['severity'] ?? 'all')));
$assignee = strtolower(trim((string)($_GET['assignee'] ?? 'all')));
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 190);

if (!in_array($status, ['active','open','reviewing','resolved','dismissed','all'], true)
    || !in_array($type, ['all','profile','post','comment','media','message','user'], true)
    || !in_array($severity, ['all','low','normal','high','urgent'], true)
    || !in_array($assignee, ['all','me','unassigned'], true)) {
    mg_fail('Invalid review queue filter.', 422);
}

$where = [];
$params = [];
if ($status === 'active') $where[] = "r.status IN ('open','reviewing')";
elseif ($status !== 'all') { $where[] = 'r.status=?'; $params[] = $status; }
if ($type !== 'all') { $where[] = 'r.subject_type=?'; $params[] = $type; }
if ($severity !== 'all') { $where[] = 'r.severity=?'; $params[] = $severity; }
if ($assignee === 'me') { $where[] = 'r.assigned_user_id=?'; $params[] = (int)$user['id']; }
elseif ($assignee === 'unassigned') $where[] = 'r.assigned_user_id IS NULL';
if ($search !== '') {
    $where[] = '(r.public_id LIKE ? OR r.subject_reference LIKE ? OR r.reason_code LIKE ? OR subject.display_name LIKE ? OR subject.email LIKE ? OR reporter.display_name LIKE ? OR reporter.email LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 7; $i++) $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$joinSql = ' FROM social_reports r LEFT JOIN users reporter ON reporter.id=r.reporter_user_id LEFT JOIN users subject ON subject.id=r.subject_user_id LEFT JOIN users assignee ON assignee.id=r.assigned_user_id ';

try {
    $pdo = mg_db();
    $count = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $whereSql);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $limit));
    $page = min($page, $pages);
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT r.*,
          reporter.public_id reporter_public_id,reporter.display_name reporter_display_name,reporter.full_name reporter_full_name,reporter.email reporter_email,reporter.status reporter_status,
          subject.public_id subject_public_id,subject.display_name subject_display_name,subject.full_name subject_full_name,subject.email subject_email,subject.status subject_status,
          assignee.public_id assignee_public_id,assignee.display_name assignee_display_name,assignee.full_name assignee_full_name,assignee.email assignee_email,assignee.status assignee_status"
        . $joinSql . $whereSql
        . " ORDER BY FIELD(r.severity,'urgent','high','normal','low'),r.created_at,r.id LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $reports = array_map('mg_content_review_report_public', $stmt->fetchAll(PDO::FETCH_ASSOC));

    $summary = $pdo->query(
        "SELECT SUM(status='open') open,SUM(status='reviewing') reviewing,
         SUM(status IN ('open','reviewing') AND severity='urgent') urgent,
         SUM(status IN ('open','reviewing') AND assigned_user_id IS NULL) unassigned
         FROM social_reports"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $appealed = mg_content_review_table_exists($pdo, 'profile_moderation_cases')
        ? (int)$pdo->query("SELECT COUNT(*) FROM profile_moderation_cases WHERE status='appealed'")->fetchColumn()
        : 0;

    header('Cache-Control: private, no-store, max-age=0');
    mg_ok([
        'reports'=>$reports,
        'summary'=>[
            'open'=>(int)($summary['open'] ?? 0),
            'reviewing'=>(int)($summary['reviewing'] ?? 0),
            'urgent'=>(int)($summary['urgent'] ?? 0),
            'unassigned'=>(int)($summary['unassigned'] ?? 0),
            'appealed'=>$appealed,
        ],
        'pagination'=>['page'=>$page,'pages'=>$pages,'limit'=>$limit,'total'=>$total],
        'access'=>$user['content_review_access'],
        'generated_at'=>gmdate('c'),
    ], 'Review queue loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.content_review.queue_failed', 'Content review queue failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load review queue.', 500);
}
