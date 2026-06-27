<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_permission('admin.audit.view');
mg_rate_limit('admin.scanner_risk.read', 'user:' . (int)$user['id'], 120, 60);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = max(1, min($limit, 100));

$pdo = mg_db();
$stmt = $pdo->prepare('SELECT sre.*, ml.name location_name, u.email scanner_email
    FROM scanner_risk_events sre
    LEFT JOIN merchant_locations ml ON ml.id=sre.scanner_location_id
    LEFT JOIN users u ON u.id=sre.scanner_user_id
    ORDER BY sre.created_at DESC, sre.id DESC
    LIMIT :limit');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$events = array_map(static function (array $row): array {
    $row['id'] = (int)$row['id'];
    $row['risk_score'] = (int)$row['risk_score'];
    $row['details'] = !empty($row['details_json']) ? json_decode((string)$row['details_json'], true) : null;
    unset($row['details_json'], $row['scan_hash'], $row['ip_hash'], $row['user_agent_hash']);
    return $row;
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

mg_ok(['scanner_risk_events' => $events, 'limit' => $limit], 'Scanner risk events loaded.');
