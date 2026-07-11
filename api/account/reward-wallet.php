<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_reward_wallet.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$rewardId = strtolower(trim((string)($_GET['reward_id'] ?? $_GET['id'] ?? '')));
if ($rewardId !== '') {
    try {
        $row = mg_rw_find($pdo, (int)$user['id'], $rewardId, false);
        mg_ok(['reward'=>mg_rw_row($pdo, $row, true),'schema_ready'=>true]);
    } catch (Throwable $error) {
        mg_security_log('warning','account.reward_wallet.detail_unavailable','Reward detail unavailable.',['exception_class'=>$error::class],(int)$user['id']);
        mg_fail('Reward detail unavailable.',500);
    }
}

$state = strtolower(trim((string)($_GET['state'] ?? 'all')));
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
if (!in_array($state, ['all','available','issued','viewed','claimed','redeemed','expired','cancelled'], true)) $state = 'all';
if (mb_strlen($q) > 180) mg_fail('Search is too long.',422);

try {
    $sql = mg_rw_base_select() . ' WHERE wi.user_id=?';
    $params = [(int)$user['id']];
    if ($q !== '') {
        $sql .= ' AND (LOWER(COALESCE(rt.title,wi.title_snapshot,\'\')) LIKE ? OR LOWER(COALESCE(mw.display_name,mu.display_name,mu.full_name,\'\')) LIKE ? OR LOWER(COALESCE(c.title,\'\')) LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params,$like,$like,$like);
    }
    $sql .= ' ORDER BY COALESCE(wi.issued_at,wi.created_at) DESC,wi.id DESC LIMIT 250';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all = [];
    $totals = ['all'=>0,'available'=>0,'issued'=>0,'viewed'=>0,'claimed'=>0,'redeemed'=>0,'expired'=>0,'cancelled'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $effective = mg_rw_effective_status($row);
        $totals['all']++;
        if (isset($totals[$effective])) $totals[$effective]++;
        if (in_array($effective,['issued','viewed'],true)) $totals['available']++;
        if ($state !== 'all' && !($state === 'available' && in_array($effective,['issued','viewed'],true)) && $effective !== $state) continue;
        $all[] = mg_rw_row($pdo,$row,false);
    }
    $offset = ($page - 1) * $limit;
    $items = array_slice($all,$offset,$limit);
    mg_ok(['rewards'=>$items,'totals'=>$totals,'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>count($all),'has_more'=>$offset+$limit<count($all)],'filter'=>$state,'schema_ready'=>true]);
} catch (Throwable $error) {
    mg_security_log('warning','account.reward_wallet.unavailable','Reward wallet unavailable.',['exception_class'=>$error::class],(int)$user['id']);
    mg_ok(['rewards'=>[],'totals'=>['all'=>0,'available'=>0,'issued'=>0,'viewed'=>0,'claimed'=>0,'redeemed'=>0,'expired'=>0,'cancelled'=>0],'pagination'=>['page'=>1,'limit'=>$limit,'total'=>0,'has_more'=>false],'filter'=>$state,'schema_ready'=>false],'Reward wallet is temporarily unavailable.');
}
