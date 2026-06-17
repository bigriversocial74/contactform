<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/ops/_alerts.php';

final class OpsAlertResolutionBehaviorTest extends TestCase
{
    private function scalar(PDO $pdo,string $sql,array $params=[]): mixed{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetchColumn();}
    private function makeUser(PDO $pdo,string $email): int{$pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?, 'active',NOW(),NOW())")->execute([$email,password_hash('OpsPass!123',PASSWORD_DEFAULT),$email,$email]);return (int)$pdo->lastInsertId();}
    private function giveRole(PDO $pdo,int $userId,string $role,array $perms): void{$pdo->prepare('INSERT IGNORE INTO roles (slug,name,created_at) VALUES (?,?,NOW())')->execute([$role,$role]);foreach($perms as $perm){$pdo->prepare('INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES (?,?,?,NOW())')->execute([$perm,$perm,$perm]);$pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at) SELECT r.id,p.id,NOW() FROM roles r, permissions p WHERE r.slug=? AND p.slug=?')->execute([$role,$perm]);}$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug=?')->execute([$userId,$role]);}

    public function testAlertAssignmentResolutionReplayConflictAndRollbackAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed ops validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_ops_alert_install($pdo);$run='ops_'.bin2hex(random_bytes(6));$pdo->beginTransaction();
        try{
            $actor=$this->makeUser($pdo,$run.'-actor@example.test');$limited=$this->makeUser($pdo,$run.'-limited@example.test');$assignee=$this->makeUser($pdo,$run.'-assignee@example.test');
            $this->giveRole($pdo,$actor,'ops_actor',['ops.alerts.assign','ops.alerts.resolve']);$this->giveRole($pdo,$limited,'ops_limited',[]);
            $alert=mg_ops_alert_upsert($pdo,['alert_key'=>'ops:'.$run.':delivery','source_type'=>'message_delivery_job','source_id'=>'job-'.$run,'severity'=>'critical','title'=>'Delivery dead letter','body'=>'Delivery failed after retries.']);
            self::assertFalse($alert['duplicate']);
            $replay=mg_ops_alert_upsert($pdo,['alert_key'=>'ops:'.$run.':delivery','source_type'=>'message_delivery_job','source_id'=>'job-'.$run,'severity'=>'critical','title'=>'Delivery dead letter','body'=>'Delivery failed after retries.']);
            self::assertTrue($replay['duplicate']);self::assertSame($alert['alert_id'],$replay['alert_id']);
            $conflict=false;try{mg_ops_alert_upsert($pdo,['alert_key'=>'ops:'.$run.':delivery','source_type'=>'message_delivery_job','source_id'=>'job-'.$run,'severity'=>'warning','title'=>'Delivery dead letter','body'=>'Changed.']);}catch(MgOpsAlertException $e){$conflict=$e->httpStatus===409;}self::assertTrue($conflict);
            $blocked=false;try{mg_ops_assign_alert($pdo,['actor_user_id'=>$limited,'alert_public_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'event_key'=>'ops:'.$run.':assign-blocked']);}catch(MgOpsAlertException $e){$blocked=$e->httpStatus===403;}self::assertTrue($blocked);
            $assign=mg_ops_assign_alert($pdo,['actor_user_id'=>$actor,'alert_public_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'event_key'=>'ops:'.$run.':assign']);self::assertSame('assigned',$assign['status']);
            self::assertSame('assigned',(string)$this->scalar($pdo,'SELECT status FROM ops_alerts WHERE public_id=?',[$alert['alert_id']]));
            $assignReplay=mg_ops_assign_alert($pdo,['actor_user_id'=>$actor,'alert_public_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'event_key'=>'ops:'.$run.':assign']);self::assertTrue($assignReplay['duplicate']);
            $resolve=mg_ops_resolve_alert($pdo,['actor_user_id'=>$actor,'alert_public_id'=>$alert['alert_id'],'resolution_reason'=>'handled by ops','event_key'=>'ops:'.$run.':resolve']);self::assertSame('resolved',$resolve['status']);
            self::assertSame('resolved',(string)$this->scalar($pdo,'SELECT status FROM ops_alerts WHERE public_id=?',[$alert['alert_id']]));
            $resolveConflict=false;try{mg_ops_resolve_alert($pdo,['actor_user_id'=>$actor,'alert_public_id'=>$alert['alert_id'],'resolution_reason'=>'different','event_key'=>'ops:'.$run.':resolve']);}catch(MgOpsAlertException $e){$resolveConflict=$e->httpStatus===409;}self::assertTrue($resolveConflict);
            self::assertGreaterThanOrEqual(2,(int)$this->scalar($pdo,"SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action IN ('ops.alert_assigned','ops.alert_resolved')",[$actor]));
            $before=(int)$this->scalar($pdo,'SELECT COUNT(*) FROM ops_alert_events');$pdo->exec('SAVEPOINT ops_failure');$forced=false;try{mg_ops_assign_alert($pdo,['actor_user_id'=>$actor,'alert_public_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'event_key'=>'ops:'.$run.':forced'],static function(string $stage): void{if($stage==='after_audit')throw new RuntimeException('forced');});}catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT ops_failure');}self::assertTrue($forced);self::assertSame($before,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM ops_alert_events'));
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare('DELETE e FROM ops_alert_events e INNER JOIN ops_alerts a ON a.id=e.alert_id WHERE a.alert_key LIKE ?')->execute(['ops:'.$run.'%']);
            $pdo->prepare('DELETE FROM ops_alerts WHERE alert_key LIKE ?')->execute(['ops:'.$run.'%']);
            $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([$run.'%']);
        }
    }
}
