<?php
declare(strict_types=1);

function mg_personal_workflows_skip_recurring_run(
    PDO $pdo,
    int $userId,
    string $publicId,
    string $expectedNextRunAt = ''
): array {
    mg_personal_workflows_require_schema($pdo);
    if($expectedNextRunAt==='') throw new InvalidArgumentException('Refresh the recurring program before skipping its next cycle.');
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM user_recurring_gift_programs WHERE owner_user_id=? AND public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId,$publicId]);
        $program=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$program) throw new RuntimeException('Recurring gift program not found.');
        if((string)$program['status']!=='active') throw new RuntimeException('Only an active recurring program can skip its next cycle.');

        $scheduledFor=(string)$program['next_run_at'];
        if(!hash_equals($scheduledFor,$expectedNextRunAt)) {
            throw new RuntimeException('The recurring program changed. Refresh it before skipping the next cycle.');
        }
        if(!empty($program['end_at']) && strtotime($scheduledFor)>strtotime((string)$program['end_at'])) {
            $pdo->prepare("UPDATE user_recurring_gift_programs SET status='completed',updated_at=NOW() WHERE id=? AND owner_user_id=?")
                ->execute([(int)$program['id'],$userId]);
            throw new RuntimeException('Recurring gift program has reached its end date.');
        }

        $sequence=(int)$program['run_sequence']+1;
        $idempotency=hash('sha256',$userId.'|'.$program['id'].'|'.$sequence.'|'.$scheduledFor.'|skip');
        $pdo->prepare("INSERT INTO user_recurring_gift_runs
            (public_id,program_id,owner_user_id,run_sequence,scheduled_for,plan_id,status,idempotency_key,generated_at,created_at,updated_at)
            VALUES (?,?,?,?,?,NULL,'skipped',?,NULL,NOW(),NOW())")
            ->execute([mg_public_uuid(),(int)$program['id'],$userId,$sequence,$scheduledFor,$idempotency]);

        $nextRun=mg_personal_workflows_next_run($scheduledFor,(string)$program['cadence'],(int)$program['interval_count']);
        $nextStatus=(!empty($program['end_at']) && strtotime($nextRun)>strtotime((string)$program['end_at']))?'completed':'active';
        $pdo->prepare('UPDATE user_recurring_gift_programs SET run_sequence=?,next_run_at=?,status=?,updated_at=NOW() WHERE id=? AND owner_user_id=?')
            ->execute([$sequence,$nextRun,$nextStatus,(int)$program['id'],$userId]);
        mg_audit('user_recurring_gift_program.cycle_skipped','user_recurring_gift_program',[
            'program_id'=>$publicId,
            'run_sequence'=>$sequence,
            'scheduled_for'=>$scheduledFor,
            'next_run_at'=>$nextRun,
            'commerce_executed'=>false,
        ],$userId);
        $pdo->commit();
        return [
            'skipped'=>true,
            'run_sequence'=>$sequence,
            'scheduled_for'=>$scheduledFor,
            'next_run_at'=>$nextRun,
            'program_status'=>$nextStatus,
            'commerce_executed'=>false,
        ];
    } catch(Throwable $error) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
