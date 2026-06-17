<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/api/ops/_queue_api.php';

final class OpsQueueApiBehaviorTest extends TestCase
{
    private function makeUser(PDO $pdo,string $email): int{$pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?, 'active',NOW(),NOW())")->execute([$email,password_hash('Pass123!',PASSWORD_DEFAULT),$email,$email]);return (int)$pdo->lastInsertId();}
    private function give(PDO $pdo,int $userId,string $role,array $perms): void{$pdo->prepare('INSERT IGNORE INTO roles (slug,name,created_at) VALUES (?,?,NOW())')->execute([$role,$role]);foreach($perms as $p){$pdo->prepare('INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES (?,?,?,NOW())')->execute([$p,$p,$p]);$pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at) SELECT r.id,p.id,NOW() FROM roles r, permissions p WHERE r.slug=? AND p.slug=?')->execute([$role,$p]);}$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug=?')->execute([$userId,$role]);}
    private function countRows(PDO $pdo,string $sql,array $p=[]): int{$s=$pdo->prepare($sql);$s->execute($p);return (int)$s->fetchColumn();}
    public function testOpsQueueListDetailAssignResolveAndReplayAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed ops queue API validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_ops_alert_install($pdo);$run='oq_'.bin2hex(random_bytes(6));$pdo->beginTransaction();
        try{$admin=$this->makeUser($pdo,$run.'-admin@example.test');$blocked=$this->makeUser($pdo,$run.'-blocked@example.test');$assignee=$this->makeUser($pdo,$run.'-assignee@example.test');$this->give($pdo,$admin,'ops_api_admin',['ops.alerts.assign','ops.alerts.resolve']);
            $alert=mg_ops_alert_upsert($pdo,['alert_key'=>'api:'.$run,'source_type'=>'provider_event','source_id'=>'src-'.$run,'severity'=>'critical','title'=>'Needs review','body'=>'Redacted safe body']);
            $denied=false;try{mg_ops_queue_list($pdo,['actor_user_id'=>$blocked]);}catch(MgOpsQueueApiException $e){$denied=$e->httpStatus===403;}self::assertTrue($denied);
            $list=mg_ops_queue_list($pdo,['actor_user_id'=>$admin,'status'=>'open','severity'=>'critical','source_type'=>'provider_event']);self::assertGreaterThanOrEqual(1,$list['count']);
            $assign=mg_ops_queue_route($pdo,'assign',['actor_user_id'=>$admin,'alert_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'request_key'=>'api:'.$run.':assign']);self::assertSame('assigned',$assign['status']);
            $assignReplay=mg_ops_queue_route($pdo,'assign',['actor_user_id'=>$admin,'alert_id'=>$alert['alert_id'],'assigned_to_user_id'=>$assignee,'request_key'=>'api:'.$run.':assign']);self::assertTrue($assignReplay['duplicate']);
            $detail=mg_ops_queue_detail($pdo,['actor_user_id'=>$admin,'alert_id'=>$alert['alert_id']]);self::assertSame($alert['alert_id'],$detail['alert']['alert_id']);self::assertGreaterThanOrEqual(1,count($detail['events']));
            $resolve=mg_ops_queue_route($pdo,'resolve',['actor_user_id'=>$admin,'alert_id'=>$alert['alert_id'],'resolution_reason'=>'handled','request_key'=>'api:'.$run.':resolve']);self::assertSame('resolved',$resolve['status']);
            $conflict=false;try{mg_ops_queue_route($pdo,'resolve',['actor_user_id'=>$admin,'alert_id'=>$alert['alert_id'],'resolution_reason'=>'different','request_key'=>'api:'.$run.':resolve']);}catch(MgOpsAlertException $e){$conflict=$e->httpStatus===409;}self::assertTrue($conflict);
            self::assertGreaterThanOrEqual(2,$this->countRows($pdo,"SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action IN ('ops.alert_assigned','ops.alert_resolved')",[$admin]));
        }finally{if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare('DELETE e FROM ops_alert_events e INNER JOIN ops_alerts a ON a.id=e.alert_id WHERE a.alert_key LIKE ?')->execute(['api:'.$run.'%']);$pdo->prepare('DELETE FROM ops_alerts WHERE alert_key LIKE ?')->execute(['api:'.$run.'%']);$pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([$run.'%']);}
    }
}
