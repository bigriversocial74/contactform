<?php
/** Campaign Drop intercept tool helpers. */
declare(strict_types=1);

require_once __DIR__ . '/_delivery_runs.php';

function mg_world_intercept_tools_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'campaign_drop_tools') && mg_world_canvas_table($pdo, 'user_campaign_drop_tools') && mg_world_canvas_table($pdo, 'merchant_target_drop_intercepts');
}

function mg_world_intercept_public_id(string $prefix): string
{
    try { return $prefix . '_' . bin2hex(random_bytes(16)); }
    catch (Throwable) { return $prefix . '_' . str_replace('.', '', uniqid('', true)); }
}

function mg_world_intercept_catalog(PDO $pdo): array
{
    if (!mg_world_canvas_table($pdo, 'campaign_drop_tools')) return [];
    $rows = mg_world_canvas_rows($pdo, "SELECT public_id, tool_key, name, category, rarity, description, range_meters, speed_bonus_percent, success_bonus_percent, cooldown_seconds, uses_limit FROM campaign_drop_tools WHERE status='active' ORDER BY FIELD(rarity,'common','rare','epic','legendary'), category, name LIMIT 100");
    return array_map(static fn(array $r): array => [
        'id'=>(string)$r['public_id'],'key'=>(string)$r['tool_key'],'name'=>(string)$r['name'],'category'=>(string)$r['category'],'rarity'=>(string)$r['rarity'],'description'=>(string)($r['description'] ?? ''),'range_meters'=>(int)$r['range_meters'],'speed_bonus_percent'=>(int)$r['speed_bonus_percent'],'success_bonus_percent'=>(int)$r['success_bonus_percent'],'cooldown_seconds'=>(int)$r['cooldown_seconds'],'uses_limit'=>$r['uses_limit']===null?null:(int)$r['uses_limit']
    ], $rows);
}

function mg_world_intercept_grant_starter(PDO $pdo, int $userId): void
{
    if ($userId <= 0 || !mg_world_intercept_tools_ready($pdo)) return;
    $tools = mg_world_canvas_rows($pdo, "SELECT id, uses_limit FROM campaign_drop_tools WHERE tool_key='scanner_basic' AND status='active' LIMIT 1");
    if (!$tools) return;
    $tool = $tools[0];
    $existing = mg_world_canvas_rows($pdo, 'SELECT id FROM user_campaign_drop_tools WHERE user_id=? AND tool_id=? LIMIT 1', [$userId, (int)$tool['id']]);
    if ($existing) return;
    $pdo->prepare("INSERT INTO user_campaign_drop_tools (public_id,user_id,tool_id,status,source,uses_remaining,created_at,updated_at) VALUES (?,?,?,'equipped','grant',?,NOW(),NOW())")
        ->execute([mg_world_intercept_public_id('utool'), $userId, (int)$tool['id'], $tool['uses_limit'] === null ? null : (int)$tool['uses_limit']]);
}

function mg_world_intercept_user_tools(PDO $pdo, int $userId, bool $grantStarter = true): array
{
    if ($userId <= 0 || !mg_world_intercept_tools_ready($pdo)) return [];
    if ($grantStarter) mg_world_intercept_grant_starter($pdo, $userId);
    $rows = mg_world_canvas_rows($pdo, "SELECT ut.public_id AS user_tool_public_id, ut.status AS user_tool_status, ut.uses_remaining, ut.cooldown_until, ut.expires_at, t.public_id AS tool_public_id, t.tool_key, t.name, t.category, t.rarity, t.description, t.range_meters, t.speed_bonus_percent, t.success_bonus_percent, t.cooldown_seconds FROM user_campaign_drop_tools ut JOIN campaign_drop_tools t ON t.id=ut.tool_id WHERE ut.user_id=? AND ut.status IN ('owned','equipped','cooldown') AND t.status='active' ORDER BY FIELD(ut.status,'equipped','owned','cooldown'), FIELD(t.rarity,'legendary','epic','rare','common'), t.name", [$userId]);
    return array_map(static function(array $r): array {
        $cooldownUntil = $r['cooldown_until'] ?? null;
        return ['id'=>(string)$r['user_tool_public_id'],'tool_id'=>(string)$r['tool_public_id'],'key'=>(string)$r['tool_key'],'name'=>(string)$r['name'],'category'=>(string)$r['category'],'rarity'=>(string)$r['rarity'],'description'=>(string)($r['description'] ?? ''),'status'=>(string)$r['user_tool_status'],'range_meters'=>(int)$r['range_meters'],'speed_bonus_percent'=>(int)$r['speed_bonus_percent'],'success_bonus_percent'=>(int)$r['success_bonus_percent'],'cooldown_seconds'=>(int)$r['cooldown_seconds'],'uses_remaining'=>$r['uses_remaining']===null?null:(int)$r['uses_remaining'],'cooldown_until'=>$cooldownUntil,'cooldown_active'=>$cooldownUntil !== null && strtotime((string)$cooldownUntil) > time(),'expires_at'=>$r['expires_at'] ?? null];
    }, $rows);
}

function mg_world_intercept_active_run(PDO $pdo, string $runPublicId): ?array
{
    if (!mg_world_delivery_runs_ready($pdo)) return null;
    $rows = mg_world_canvas_rows($pdo, "SELECT r.*, d.public_id AS drop_public_id, d.drop_name, d.campaign_title FROM merchant_target_drop_delivery_runs r JOIN merchant_target_drops d ON d.id=r.target_drop_id WHERE r.public_id=? AND r.status IN ('queued','sending') LIMIT 1", [$runPublicId]);
    return $rows[0] ?? null;
}

function mg_world_intercept_attempt(PDO $pdo, array $user, array $input): array
{
    if (!mg_world_intercept_tools_ready($pdo)) throw new RuntimeException('Intercept tools are not installed.');
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) throw new RuntimeException('Sign in required.');
    mg_world_intercept_grant_starter($pdo, $userId);
    $runId = trim((string)($input['run_id'] ?? $input['delivery_run_id'] ?? ''));
    $userToolPublicId = trim((string)($input['user_tool_id'] ?? ''));
    if ($runId === '') throw new RuntimeException('Delivery run is required.');
    $run = mg_world_intercept_active_run($pdo, $runId);
    if (!$run) throw new RuntimeException('This delivery is no longer interceptable.');
    $toolRows = mg_world_canvas_rows($pdo, "SELECT ut.id AS user_tool_id, ut.uses_remaining, ut.cooldown_until, t.id AS tool_id, t.name, t.range_meters, t.speed_bonus_percent, t.success_bonus_percent, t.cooldown_seconds FROM user_campaign_drop_tools ut JOIN campaign_drop_tools t ON t.id=ut.tool_id WHERE ut.user_id=? AND ut.public_id=? AND ut.status IN ('owned','equipped','cooldown') AND t.status='active' LIMIT 1", [$userId, $userToolPublicId]);
    if (!$toolRows) throw new RuntimeException('Select an available tool.');
    $tool = $toolRows[0];
    if (!empty($tool['cooldown_until']) && strtotime((string)$tool['cooldown_until']) > time()) throw new RuntimeException('Tool is cooling down.');
    if ($tool['uses_remaining'] !== null && (int)$tool['uses_remaining'] <= 0) throw new RuntimeException('Tool has no uses remaining.');
    $required = 55;
    $score = min(100, 35 + (int)$tool['success_bonus_percent'] + (int)floor(((int)$tool['range_meters']) / 400));
    $status = $score >= $required ? 'success' : 'failed';
    $reason = $status === 'success' ? 'caught_in_window' : 'missed_timing';
    $pdo->beginTransaction();
    try {
        $publicId = mg_world_intercept_public_id('intercept');
        $pdo->prepare("INSERT INTO merchant_target_drop_intercepts (public_id,delivery_run_id,target_drop_id,merchant_user_id,user_id,user_tool_id,tool_id,status,result_reason,success_score,required_score,resolved_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())")
            ->execute([$publicId, (int)$run['id'], (int)$run['target_drop_id'], (int)$run['merchant_user_id'], $userId, (int)$tool['user_tool_id'], (int)$tool['tool_id'], $status, $reason, $score, $required, json_encode(['tool_name'=>(string)$tool['name']], JSON_UNESCAPED_SLASHES)]);
        $cooldownUntil = date('Y-m-d H:i:s', time() + (int)$tool['cooldown_seconds']);
        $usesSql = $tool['uses_remaining'] === null ? 'uses_remaining=uses_remaining' : 'uses_remaining=GREATEST(uses_remaining-1,0)';
        $pdo->prepare("UPDATE user_campaign_drop_tools SET status='cooldown', cooldown_until=?, {$usesSql}, updated_at=NOW() WHERE id=?")->execute([$cooldownUntil, (int)$tool['user_tool_id']]);
        if ($status === 'success') $pdo->prepare("UPDATE merchant_target_drop_delivery_runs SET status='intercepted', intercepted_at=NOW(), intercepted_by_user_id=?, intercept_tool_public_id=?, updated_at=NOW() WHERE id=?")->execute([$userId, $userToolPublicId, (int)$run['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['status'=>$status,'result_reason'=>$reason,'success_score'=>$score,'required_score'=>$required,'tool_name'=>(string)$tool['name'],'cooldown_until'=>$cooldownUntil];
}
