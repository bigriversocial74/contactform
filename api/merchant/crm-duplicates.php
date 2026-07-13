<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-identity.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST'
    ? mg_require_permission('merchant.campaigns.manage')
    : mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

function mg_crm_duplicate_recent_merges(PDO $pdo, int $merchantId): array
{
    if (!mg_crm_identity_table_exists($pdo, 'merchant_crm_contact_merges')) return [];
    try {
        $stmt = $pdo->prepare("SELECT m.merge_batch_public_id,m.match_type,m.confidence_score,m.reason,m.moved_counts_json,m.created_at,
                                      c.public_id canonical_public_id,COALESCE(NULLIF(c.display_name,''),NULLIF(c.primary_email,''),'Customer') canonical_name,
                                      s.public_id source_public_id,COALESCE(NULLIF(s.display_name,''),'Merged profile') source_name
                               FROM merchant_crm_contact_merges m
                               INNER JOIN merchant_crm_contacts c ON c.id=m.canonical_contact_id
                               INNER JOIN merchant_crm_contacts s ON s.id=m.source_contact_id
                               WHERE m.merchant_user_id=?
                               ORDER BY m.created_at DESC,m.id DESC
                               LIMIT 20");
        $stmt->execute([$merchantId]);
        return array_map(static function (array $row): array {
            return [
                'merge_batch_id'=>(string)$row['merge_batch_public_id'],
                'canonical_contact_id'=>(string)$row['canonical_public_id'],
                'canonical_name'=>(string)$row['canonical_name'],
                'source_contact_id'=>(string)$row['source_public_id'],
                'source_name'=>(string)$row['source_name'],
                'match_type'=>(string)$row['match_type'],
                'confidence_score'=>(int)$row['confidence_score'],
                'reason'=>(string)($row['reason'] ?? ''),
                'moved'=>mg_crm_identity_json($row['moved_counts_json'] ?? null),
                'created_at'=>$row['created_at'] ?? null,
                'profile_url'=>'/merchant-customer.php?contact_id=' . rawurlencode((string)$row['canonical_public_id']),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return [];
    }
}

if ($method === 'GET') {
    try {
        $report = mg_crm_identity_duplicate_groups($pdo, $merchantId);
        $report['recent_merges'] = mg_crm_duplicate_recent_merges($pdo, $merchantId);
        mg_ok($report, $report['schema_ready'] ? 'CRM duplicate analysis complete.' : 'CRM identity schema is not installed.');
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.crm_duplicates.analysis_failed', 'CRM duplicate analysis failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
        mg_fail('Unable to analyze CRM contact identities.', 500);
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'merge')));
if ($action !== 'merge') mg_fail('Unsupported CRM identity action.', 422);
$canonicalId = (string)($input['canonical_contact_id'] ?? '');
$sourceIds = is_array($input['source_contact_ids'] ?? null) ? $input['source_contact_ids'] : [];
$reason = trim((string)($input['reason'] ?? ''));

try {
    $result = mg_crm_identity_merge_contacts($pdo, $merchantId, $merchantId, $canonicalId, $sourceIds, $reason);
    mg_security_log('notice', 'merchant.crm_duplicates.merged', 'CRM duplicate profiles merged.', [
        'merge_batch_id'=>$result['merge_batch_id'],
        'canonical_contact_id'=>$result['canonical_contact_id'],
        'source_count'=>count($result['merged_source_contact_ids']),
        'match_type'=>$result['match_type'],
        'confidence_score'=>$result['confidence_score'],
    ], $merchantId);
    $result['report'] = mg_crm_identity_duplicate_groups($pdo, $merchantId);
    mg_ok($result, count($result['merged_source_contact_ids']) . ' duplicate profile' . (count($result['merged_source_contact_ids']) === 1 ? '' : 's') . ' merged successfully.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('warning', 'merchant.crm_duplicates.merge_blocked', 'CRM duplicate merge was blocked.', ['message'=>$error->getMessage()], $merchantId);
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.crm_duplicates.merge_failed', 'CRM duplicate merge failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    mg_fail('Unable to merge CRM profiles safely.', 500);
}
