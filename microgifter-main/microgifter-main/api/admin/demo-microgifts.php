<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

function mg_demo_microgift_prefix(int $adminUserId): string
{
    return 'demo-action-center-' . $adminUserId . '-';
}

function mg_demo_microgift_status(PDO $pdo, int $adminUserId): array
{
    $prefix = mg_demo_microgift_prefix($adminUserId) . '%';
    $stmt = $pdo->prepare("SELECT status,COUNT(*) total FROM microgift_instances WHERE source_type='administrator' AND source_reference LIKE ? GROUP BY status");
    $stmt->execute([$prefix]);
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }

    $visible = $pdo->prepare("SELECT COUNT(*) FROM microgift_inbox_items ac INNER JOIN microgift_instances i ON i.id=ac.instance_id WHERE ac.user_id=? AND ac.archived_at IS NULL AND i.source_type='administrator' AND i.source_reference LIKE ?");
    $visible->execute([$adminUserId,$prefix]);

    return [
        'enabled' => (int)$visible->fetchColumn() > 0,
        'source_prefix' => rtrim($prefix,'%'),
        'instances_by_status' => $counts,
    ];
}

function mg_demo_microgift_clear(PDO $pdo, int $adminUserId): int
{
    $prefix = mg_demo_microgift_prefix($adminUserId) . '%';
    $stmt = $pdo->prepare("SELECT id,public_id,status,template_id FROM microgift_instances WHERE source_type='administrator' AND source_reference LIKE ? FOR UPDATE");
    $stmt->execute([$prefix]);
    $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($instances as $instance) {
        $pdo->prepare('UPDATE microgift_inbox_items SET archived_at=COALESCE(archived_at,NOW()),updated_at=NOW() WHERE instance_id=?')->execute([(int)$instance['id']]);
        if (!in_array((string)$instance['status'], ['redeemed','cancelled','revoked','expired'], true)) {
            $pdo->prepare("UPDATE microgift_instances SET status='cancelled',cancelled_at=COALESCE(cancelled_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
            $pdo->prepare("UPDATE microgift_credentials SET status='revoked',updated_at=NOW() WHERE instance_id=? AND status IN ('active','verified','locked')")->execute([(int)$instance['id']]);
            mg_microgift_event($pdo,'microgift.demo_cancelled',(int)$instance['id'],(int)$instance['template_id'],$adminUserId,'admin_demo_clear',(string)$instance['public_id'],['sandbox_mode'=>'admin_demo']);
        }
    }
    return count($instances);
}

function mg_demo_microgift_seed(PDO $pdo, int $adminUserId): array
{
    $batch = bin2hex(random_bytes(4));
    $template = mg_microgift_create_template($pdo,$adminUserId,[
        'owner_type' => 'merchant',
        'name' => 'Demo Action Center Microgift',
        'slug' => 'demo-action-center-' . $adminUserId . '-' . $batch,
        'description' => 'Database-backed sandbox Microgift for exercising Action Center send, claim, message, and redemption flows.',
        'gift_type' => 'experience',
        'visibility' => 'private',
        'default_currency' => 'USD',
    ]);
    $version = mg_microgift_create_version($pdo,$adminUserId,(string)$template['template_id'],[
        'title' => 'Demo Coffee for Two',
        'description' => 'Functional sandbox Microgift. Uses normal Action Center records while remaining marked as admin demo data.',
        'currency' => 'USD',
        'face_value_cents' => 2500,
        'recipient_policy' => 'open_claim',
        'claim_policy' => ['mode'=>'sandbox_demo'],
        'redemption_policy' => ['mode'=>'sandbox_demo'],
        'location_policy' => ['mode'=>'unrestricted'],
        'expiration_policy' => ['mode'=>'none'],
        'terms_snapshot' => ['demo'=>true,'cash_value'=>false],
        'future_demand_metadata' => ['sandbox_mode'=>'admin_demo'],
    ]);
    mg_microgift_publish_version($pdo,$adminUserId,(string)$version['version_id']);

    $sourceReference = mg_demo_microgift_prefix($adminUserId) . $batch;
    $issued = mg_microgift_issue($pdo,$adminUserId,[
        'template_version_id' => (string)$version['version_id'],
        'source_type' => 'administrator',
        'source_reference' => $sourceReference,
        'idempotency_key' => $sourceReference,
        'metadata' => [
            'mg_demo' => true,
            'sandbox_mode' => 'admin_demo',
            'demo_batch_id' => $batch,
            'demo_scenario' => 'action_center_send_claim_message',
            'financial_side_effects' => 'disabled',
        ],
    ]);

    $instance = mg_microgift_load_instance($pdo,(string)$issued['instance_id']);
    $projection = mg_action_center_project_lifecycle($pdo,$instance);

    return [
        'batch_id' => $batch,
        'template_id' => $template['template_id'],
        'version_id' => $version['version_id'],
        'instance_id' => $issued['instance_id'],
        'claim_code' => $issued['credential']['code'] ?? null,
        'source_reference' => $sourceReference,
        'action_center' => $projection,
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method,['GET','POST'],true)) mg_fail('Method not allowed.',405);
$user = mg_require_permission('admin.users.view');
$pdo = mg_db();
$action = $method === 'GET' ? 'status' : trim((string)(mg_input()['action'] ?? 'seed'));

try {
    if ($method === 'GET' || $action === 'status') {
        mg_ok(['demo' => mg_demo_microgift_status($pdo,(int)$user['id'])], 'Demo Microgift status loaded.');
    }

    $input = mg_input();
    mg_require_csrf_for_write($input);

    if (in_array($action,['disable','clear'],true)) {
        $pdo->beginTransaction();
        $cleared = mg_demo_microgift_clear($pdo,(int)$user['id']);
        $status = mg_demo_microgift_status($pdo,(int)$user['id']);
        $pdo->commit();
        mg_audit('admin.demo_microgifts_disabled','microgift_demo',['cleared'=>$cleared],(int)$user['id']);
        mg_ok(['cleared'=>$cleared,'demo'=>$status], 'Demo Microgifts disabled.');
    }

    if (in_array($action,['enable','seed','reset'],true)) {
        $pdo->beginTransaction();
        $cleared = $action === 'reset' ? mg_demo_microgift_clear($pdo,(int)$user['id']) : 0;
        $seeded = mg_demo_microgift_seed($pdo,(int)$user['id']);
        $status = mg_demo_microgift_status($pdo,(int)$user['id']);
        $pdo->commit();
        mg_audit('admin.demo_microgifts_enabled','microgift_demo',['action'=>$action,'cleared'=>$cleared,'instance_id'=>$seeded['instance_id']],(int)$user['id']);
        mg_ok(['cleared'=>$cleared,'seeded'=>$seeded,'demo'=>$status], 'Demo Microgifts enabled.');
    }

    mg_fail('Unsupported demo action.',422);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','admin.demo_microgifts_failed','Admin demo Microgift action failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to update demo Microgifts.',500);
}
