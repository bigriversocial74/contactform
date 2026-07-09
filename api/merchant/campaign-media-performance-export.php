<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$campaignRef = trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? ''));
$days = (int)($_GET['days'] ?? 30);
$segment = strtolower(trim((string)($_GET['segment'] ?? 'all')));
if (!in_array($days, [7, 30, 90, 180], true)) $days = 30;
if (!in_array($segment, ['all','started_incomplete','milestone_unclaimed','claimed_unredeemed','redeemed','no_activity'], true)) $segment = 'all';
if ($campaignRef === '' || strlen($campaignRef) > 180 || !preg_match('/^[a-zA-Z0-9_\-]+$/', $campaignRef)) mg_fail('Campaign is required.', 422);

function mg_media_export_json(mixed $json): array { $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null; return is_array($decoded) ? $decoded : []; }
function mg_media_export_progress(array $context): float { return min(100.0, max(0.0, max((float)($context['progress_percent'] ?? 0), (float)($context['watch_percent'] ?? 0), (float)($context['listen_percent'] ?? 0), (float)($context['max_progress_percent'] ?? 0)))); }
function mg_media_export_bucket(array $row): string { if ((int)$row['redeemed'] > 0) return 'redeemed'; if ((int)$row['claimed'] > 0) return 'claimed_unredeemed'; if ((int)$row['wallet_items'] > 0) return 'milestone_unclaimed'; if ((float)$row['max_progress_percent'] > 0 || (int)$row['starts'] > 0 || (int)$row['progress_events'] > 0) return 'started_incomplete'; return 'no_activity'; }
function mg_media_export_bucket_label(string $bucket): string { return match ($bucket) { 'redeemed'=>'Redeemed / completed', 'claimed_unredeemed'=>'Claimed, not redeemed', 'milestone_unclaimed'=>'Milestone hit, not claimed', 'started_incomplete'=>'Started, did not finish', 'no_activity'=>'No tracked activity', default=>'All contacts' }; }
function mg_media_export_attribution(array $metadata, array $context): array { $origin=(string)($metadata['origin_host'] ?? $context['origin_host'] ?? ''); $page=(string)($metadata['page_url'] ?? $context['page_url'] ?? ''); $mode=(string)($metadata['embed_mode'] ?? $context['embed_mode'] ?? ''); $source=(string)($metadata['embed_source'] ?? $context['embed_source'] ?? ''); $isEmbed=$origin!==''||$page!==''||$mode!==''||$source!==''; return ['source'=>$isEmbed?($source?:'website_embed'):'public_page','origin_host'=>$origin,'page_url'=>$page,'embed_mode'=>$mode]; }
function mg_media_export_csv_value(mixed $value): string { if (is_array($value)) $value = implode('; ', array_map('strval', $value)); return (string)$value; }

try {
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare("SELECT id, public_id, public_slug, title, campaign_type FROM campaigns WHERE merchant_user_id = ? AND campaign_type IN ('watch_video_reward','listen_music_reward') AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1");
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Media reward campaign not found.', 404);
    $campaignId = (int)$campaign['id'];
    $campaignType = (string)$campaign['campaign_type'];
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

    $stmt = $pdo->prepare('SELECT id, public_id, email, phone, name, metadata_json, created_at, updated_at FROM campaign_contacts WHERE merchant_user_id = ? AND campaign_id = ? ORDER BY updated_at DESC LIMIT 1000');
    $stmt->execute([$merchantId, $campaignId]);
    $contacts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $contact) {
        $contacts[(int)$contact['id']] = ['public_id'=>(string)$contact['public_id'], 'email'=>(string)$contact['email'], 'phone'=>(string)($contact['phone'] ?? ''), 'name'=>(string)($contact['name'] ?? ''), 'metadata'=>mg_media_export_json($contact['metadata_json'] ?? null), 'starts'=>0, 'progress_events'=>0, 'max_progress_percent'=>0.0, 'milestones'=>[], 'wallet_items'=>0, 'claimed'=>0, 'redeemed'=>0, 'inbox_status'=>'Not issued', 'pppm_handoff'=>false, 'last_activity_at'=>$contact['updated_at'] ?? $contact['created_at'] ?? null, 'attribution'=>['source'=>'public_page','origin_host'=>'','page_url'=>'','embed_mode'=>'']];
    }

    $stmt = $pdo->prepare('SELECT contact_id, event_type, event_context_json, created_at FROM campaign_events WHERE merchant_user_id = ? AND campaign_id = ? AND created_at >= ? ORDER BY id DESC LIMIT 3000');
    $stmt->execute([$merchantId, $campaignId, $cutoff]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
        $contactId = (int)($event['contact_id'] ?? 0);
        if ($contactId <= 0 || !isset($contacts[$contactId])) continue;
        $context = mg_media_export_json($event['event_context_json'] ?? null);
        $eventType = (string)$event['event_type'];
        if (str_ends_with($eventType, '.started')) $contacts[$contactId]['starts']++;
        if (str_ends_with($eventType, '.progress')) $contacts[$contactId]['progress_events']++;
        $progress = mg_media_export_progress($context);
        if ($progress > $contacts[$contactId]['max_progress_percent']) $contacts[$contactId]['max_progress_percent'] = $progress;
        $milestone = (int)($context['milestone_percent'] ?? 0);
        if ($milestone > 0) $contacts[$contactId]['milestones'][$milestone] = $milestone;
        if (!empty($context['pppm_bridge'])) $contacts[$contactId]['pppm_handoff'] = true;
        $attr = mg_media_export_attribution($contacts[$contactId]['metadata'], $context);
        if ($attr['source'] !== 'public_page') $contacts[$contactId]['attribution'] = $attr;
        $contacts[$contactId]['last_activity_at'] = max((string)$contacts[$contactId]['last_activity_at'], (string)($event['created_at'] ?? '')) ?: $contacts[$contactId]['last_activity_at'];
    }

    $stmt = $pdo->prepare("SELECT contact_id, status, metadata_json, updated_at, issued_at FROM wallet_items WHERE merchant_user_id = ? AND campaign_id = ? AND source_type = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 2000");
    $stmt->execute([$merchantId, $campaignId, $campaignType]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $wallet) {
        $contactId = (int)($wallet['contact_id'] ?? 0);
        if ($contactId <= 0 || !isset($contacts[$contactId])) continue;
        $meta = mg_media_export_json($wallet['metadata_json'] ?? null);
        $contacts[$contactId]['wallet_items']++;
        if ((string)$wallet['status'] === 'claimed') $contacts[$contactId]['claimed']++;
        if ((string)$wallet['status'] === 'redeemed') $contacts[$contactId]['redeemed']++;
        if ((string)($meta['pppm_destination'] ?? '') === 'inbox') $contacts[$contactId]['pppm_handoff'] = true;
        $milestone = (int)($meta['milestone_percent'] ?? 0);
        if ($milestone > 0) $contacts[$contactId]['milestones'][$milestone] = $milestone;
        $contacts[$contactId]['inbox_status'] = $contacts[$contactId]['redeemed'] > 0 ? 'Redeemed' : ($contacts[$contactId]['claimed'] > 0 ? 'Claimed' : 'Inbox issued');
        $contacts[$contactId]['last_activity_at'] = max((string)$contacts[$contactId]['last_activity_at'], (string)($wallet['updated_at'] ?? $wallet['issued_at'] ?? '')) ?: $contacts[$contactId]['last_activity_at'];
    }

    $filename = 'microgifter-media-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string)($campaign['public_slug'] ?: $campaign['public_id'])) . '-' . $segment . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['campaign_title','campaign_type','segment','name','email','phone','progress_percent','starts','progress_events','milestones','rewards_issued','claimed','redeemed','inbox_status','pppm_handoff','source','origin_host','embed_mode','page_url','last_activity_at']);
    foreach ($contacts as $row) {
        $bucket = mg_media_export_bucket($row);
        if ($segment !== 'all' && $bucket !== $segment) continue;
        $milestones = array_values($row['milestones']); sort($milestones);
        $attr = $row['attribution'];
        fputcsv($out, array_map('mg_media_export_csv_value', [(string)$campaign['title'], $campaignType, mg_media_export_bucket_label($bucket), $row['name'] ?: 'Customer', $row['email'], $row['phone'], round((float)$row['max_progress_percent'], 2), $row['starts'], $row['progress_events'], $milestones, $row['wallet_items'], $row['claimed'], $row['redeemed'], $row['inbox_status'], $row['pppm_handoff'] ? 'yes' : 'no', $attr['source'], $attr['origin_host'], $attr['embed_mode'], $attr['page_url'], $row['last_activity_at']]));
    }
    fclose($out);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_media_performance_export.failed', 'Campaign media performance export failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    mg_fail('Unable to export campaign media contacts.', 500);
}
