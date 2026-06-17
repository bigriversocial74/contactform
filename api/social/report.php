<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';

mg_require_method('POST');
$user = mg_require_permission('social.engage');
$input = mg_input();
mg_require_csrf_for_write($input);
$actorId = (int)$user['id'];
$type = strtolower(trim((string)($input['subject_type'] ?? '')));
$reference = trim((string)($input['subject_reference'] ?? ''));
$reason = strtolower(trim((string)($input['reason_code'] ?? '')));
$details = mb_substr(trim((string)($input['details'] ?? '')), 0, 1000);

if (!in_array($type, ['post','comment','user'], true) || $reference === '' || $reason === '' || strlen($reason) > 100) {
    mg_fail('Valid report subject and reason are required.', 422);
}
mg_rate_limit('social.report.write', 'user:' . $actorId, 20, 3600);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    if ($type === 'post') {
        $post = mg_engagement_post($pdo, $reference, $actorId, true);
        if ((int)$post['created_by_user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own post.');
    } elseif ($type === 'comment') {
        $stmt = $pdo->prepare('SELECT c.id,c.user_id,fp.public_id post_public_id FROM feed_post_comments c INNER JOIN feed_posts fp ON fp.id=c.feed_post_id WHERE c.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$reference]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$comment) throw new RuntimeException('Comment not found.');
        if ((int)$comment['user_id'] === $actorId) throw new InvalidArgumentException('You cannot report your own comment.');
        mg_engagement_post($pdo, (string)$comment['post_public_id'], $actorId, false);
    } else {
        $stmt = $pdo->prepare("SELECT pp.user_id FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE (pp.public_id=? OR u.public_id=?) AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active' LIMIT 1");
        $stmt->execute([$reference, $reference]);
        $targetId = (int)($stmt->fetchColumn() ?: 0);
        if ($targetId < 1) throw new RuntimeException('Profile not found.');
        if ($targetId === $actorId) throw new InvalidArgumentException('You cannot report your own profile.');
        if (mg_social_is_blocked($pdo, $actorId, $targetId)) throw new RuntimeException('Profile not found.');
    }

    $existing = $pdo->prepare("SELECT public_id FROM social_reports WHERE reporter_user_id=? AND subject_type=? AND subject_reference=? AND status IN ('open','reviewing') LIMIT 1 FOR UPDATE");
    $existing->execute([$actorId, $type, $reference]);
    $existingId = $existing->fetchColumn();
    if ($existingId) {
        $pdo->commit();
        mg_ok(['report_id'=>(string)$existingId,'status'=>'open','duplicate'=>true], 'Existing report returned.');
    }

    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO social_reports (public_id,reporter_user_id,subject_type,subject_reference,reason_code,details,status,created_at) VALUES (?,?,?,?,?,?,'open',NOW())")
        ->execute([$publicId, $actorId, $type, $reference, $reason, $details]);
    if ($type === 'post') $pdo->prepare("UPDATE feed_posts SET moderation_status=IF(moderation_status='clear','flagged',moderation_status),updated_at=NOW() WHERE public_id=?")->execute([$reference]);
    if ($type === 'comment') $pdo->prepare("UPDATE feed_post_comments SET status=IF(status='visible','flagged',status),updated_at=NOW() WHERE public_id=?")->execute([$reference]);
    $pdo->commit();

    mg_audit('social.report_created', 'social_report', [
        'report_id' => $publicId,
        'subject_type' => $type,
        'subject_reference' => $reference,
        'reason_code' => $reason,
    ], $actorId);
    mg_event('social.report_created', ['report_id'=>$publicId,'subject_type'=>$type], $actorId);
    mg_ok(['report_id'=>$publicId,'status'=>'open','duplicate'=>false], 'Report submitted.', 201);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 404);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'social.report_failed', 'Social report failed.', ['exception_class'=>$error::class,'subject_type'=>$type], $actorId);
    mg_fail('Unable to submit report.', 500);
}
