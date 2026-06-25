<?php
declare(strict_types=1);

require_once __DIR__ . '/_delivery.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

function mg_delivery_decode_json(?string $raw): array
{
    if (!$raw) return [];
    try { $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); return is_array($decoded) ? $decoded : []; } catch (Throwable) { return []; }
}

function mg_delivery_claim_next_email(PDO $pdo): ?array
{
    mg_delivery_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query("SELECT * FROM message_delivery_jobs WHERE channel='email' AND status IN ('queued','retrying') AND next_attempt_at<=NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) { $pdo->commit(); return null; }
        $pdo->prepare("UPDATE message_delivery_jobs SET status='processing',updated_at=NOW() WHERE id=? AND status IN ('queued','retrying')")->execute([(int) $job['id']]);
        $job['status'] = 'processing';
        $pdo->commit();
        return $job;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_delivery_send_email_job(PDO $pdo, array $job): array
{
    $recipient = mg_delivery_decode_json($job['recipient_snapshot_json'] ?? null);
    $payload = mg_delivery_decode_json($job['payload_snapshot_json'] ?? null);
    $to = trim((string) ($recipient['email'] ?? $payload['email'] ?? ''));
    $subject = trim((string) ($payload['subject'] ?? 'Microgifter update'));
    $html = (string) ($payload['html'] ?? '');
    $text = (string) ($payload['text'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return mg_delivery_process_job($pdo, $job, ['provider_key'=>'microgifter_mail','status'=>'permanent_failure','error_code'=>'invalid_recipient','error_message'=>'Invalid email recipient.']);
    }
    if ($html === '') $html = mg_email_layout($subject, '<p style="margin:0;color:#334155;font-size:16px;line-height:1.6;">' . mg_mail_escape($text !== '' ? $text : $subject) . '</p>', $subject);
    $sent = mg_send_email($to, $subject, $html, $text !== '' ? $text : null, ['delivery_job_id'=>(string)($job['public_id'] ?? ''),'template_key'=>(string)($job['template_key'] ?? '')]);
    return mg_delivery_process_job($pdo, $job, $sent ? ['provider_key'=>mg_mail_provider(),'status'=>'success','provider_message_id'=>'microgifter-mail:' . (string)($job['public_id'] ?? '')] : ['provider_key'=>mg_mail_provider(),'status'=>'transient_failure','error_code'=>'send_failed','error_message'=>'Email provider did not accept the message.','retry_after_seconds'=>300]);
}

function mg_delivery_run_email_worker(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(100, $limit));
    $processed = [];
    for ($i = 0; $i < $limit; $i++) {
        $job = mg_delivery_claim_next_email($pdo);
        if (!$job) break;
        $processed[] = ['job_id'=>(string)$job['public_id']] + mg_delivery_send_email_job($pdo, $job);
    }
    return ['processed_count'=>count($processed),'processed'=>$processed];
}
