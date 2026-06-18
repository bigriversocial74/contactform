<?php
declare(strict_types=1);

require_once __DIR__ . '/_reports.php';

mg_require_method('POST');
$user = mg_require_permission('social.engage');
$input = mg_input();
mg_require_csrf_for_write($input);
$actorId = (int)$user['id'];
$type = strtolower(trim((string)($input['subject_type'] ?? '')));
$reference = trim((string)($input['subject_reference'] ?? ''));
$reason = strtolower(trim((string)($input['reason_code'] ?? '')));
$details = mb_substr(trim((string)($input['details'] ?? '')), 0, 1000);

if (!in_array($type, ['profile','post','comment','media','message','user'], true)
    || $reference === '' || mb_strlen($reference) > 190
    || $reason === '' || strlen($reason) > 100
    || preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $reason) !== 1) {
    mg_fail('Valid report subject and reason are required.', 422);
}
mg_rate_limit('social.report.write', 'user:' . $actorId, 20, 3600);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $subject = mg_social_report_subject($pdo, $actorId, $type, $reference);
    $existing = $pdo->prepare(
        "SELECT public_id,status FROM social_reports
         WHERE reporter_user_id=? AND subject_type=? AND subject_reference=?
           AND status IN ('open','reviewing')
         LIMIT 1 FOR UPDATE"
    );
    $existing->execute([$actorId, $subject['subject_type'], $subject['subject_reference']]);
    $existingReport = $existing->fetch(PDO::FETCH_ASSOC);
    if ($existingReport) {
        $pdo->commit();
        mg_ok([
            'report_id'=>(string)$existingReport['public_id'],
            'status'=>(string)$existingReport['status'],
            'duplicate'=>true,
        ], 'Existing report returned.');
    }

    $publicId = mg_public_uuid();
    $severity = mg_social_report_severity($reason);
    $insert = $pdo->prepare(
        "INSERT INTO social_reports
         (public_id,reporter_user_id,source,subject_type,subject_reference,subject_user_id,
          feed_post_id,comment_id,asset_id,message_id,reason_code,severity,details,status,
          subject_snapshot_json,created_at,updated_at)
         VALUES (?,?, 'user',?,?,?,?,?,?,?,?,?,?,'open',?,NOW(),NOW())"
    );
    $insert->execute([
        $publicId,
        $actorId,
        $subject['subject_type'],
        $subject['subject_reference'],
        $subject['subject_user_id'],
        $subject['feed_post_id'],
        $subject['comment_id'],
        $subject['asset_id'],
        $subject['message_id'],
        $reason,
        $severity,
        $details !== '' ? $details : null,
        $subject['snapshot'],
    ]);
    $reportId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO content_moderation_actions
         (public_id,report_id,actor_user_id,action_type,reason,metadata_json,created_at)
         VALUES (?,?,?,'report_opened',?,?,NOW())"
    )->execute([
        mg_public_uuid(),
        $reportId,
        $actorId,
        $details !== '' ? $details : null,
        json_encode([
            'reason_code'=>$reason,
            'severity'=>$severity,
            'source'=>'user',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    mg_social_report_flag_subject($pdo, $subject);
    $pdo->commit();

    mg_audit('social.report_created', 'social_report', [
        'report_id' => $publicId,
        'subject_type' => $subject['subject_type'],
        'subject_reference' => $subject['subject_reference'],
        'reason_code' => $reason,
        'severity' => $severity,
    ], $actorId);
    mg_event('social.report_created', [
        'report_id'=>$publicId,
        'subject_type'=>$subject['subject_type'],
        'severity'=>$severity,
    ], $actorId);
    mg_ok(['report_id'=>$publicId,'status'=>'open','severity'=>$severity,'duplicate'=>false], 'Report submitted.', 201);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'social.report_failed', 'Social report failed.', [
        'exception_class'=>$error::class,
        'subject_type'=>$type,
    ], $actorId);
    mg_fail('Unable to submit report.', 500);
}
