<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/admin/_controls.php';

final class ControlPlaneBehaviorTest extends TestCase
{
    public function testPermissionBoundaryAndReplayAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed control validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_control_install($pdo);
        $run='cp_'.bin2hex(random_bytes(6));
        $pdo->beginTransaction();
        try{
            $makeUser=function(string $suffix) use ($pdo,$run): int{
                $email=$run.'-'.$suffix.'@example.test';
                $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?, 'active',NOW(),NOW())")->execute([$email,password_hash('ControlPass!123',PASSWORD_DEFAULT),$email,$email]);
                return (int)$pdo->lastInsertId();
            };
            $giveRole=function(int $userId,string $role,array $perms) use ($pdo): void{
                $pdo->prepare('INSERT IGNORE INTO roles (slug,name,created_at) VALUES (?,?,NOW())')->execute([$role,$role]);
                foreach($perms as $perm){
                    $pdo->prepare('INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES (?,?,?,NOW())')->execute([$perm,$perm,$perm]);
                    $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at) SELECT r.id,p.id,NOW() FROM roles r, permissions p WHERE r.slug=? AND p.slug=?')->execute([$role,$perm]);
                }
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug=?')->execute([$userId,$role]);
            };
            $actor=$makeUser('actor');$limited=$makeUser('limited');$target=$makeUser('target');
            $giveRole($actor,'cp_actor',['control.user.status']);$giveRole($limited,'cp_limited',['control.support.note']);
            $blocked=false;
            try{mg_control_apply($pdo,['actor_user_id'=>$limited,'target_type'=>'user','target_id'=>$target,'action_type'=>'status','desired_state'=>'disabled','reason'=>'boundary','idempotency_key'=>'cp:'.$run.':blocked']);}catch(MgControlException $e){$blocked=$e->httpStatus===403;}
            self::assertTrue($blocked);
            $first=mg_control_apply($pdo,['actor_user_id'=>$actor,'target_type'=>'user','target_id'=>$target,'action_type'=>'status','desired_state'=>'disabled','reason'=>'hold','idempotency_key'=>'cp:'.$run.':status']);
            self::assertSame('disabled',(string)mg_control_scalar($pdo,'SELECT status FROM users WHERE id=?',[$target]));
            $again=mg_control_apply($pdo,['actor_user_id'=>$actor,'target_type'=>'user','target_id'=>$target,'action_type'=>'status','desired_state'=>'disabled','reason'=>'hold','idempotency_key'=>'cp:'.$run.':status']);
            self::assertTrue($again['duplicate']);
            self::assertSame($first['action_id'],$again['action_id']);
            $conflict=false;
            try{mg_control_apply($pdo,['actor_user_id'=>$actor,'target_type'=>'user','target_id'=>$target,'action_type'=>'status','desired_state'=>'active','reason'=>'hold','idempotency_key'=>'cp:'.$run.':status']);}catch(MgControlException $e){$conflict=$e->httpStatus===409;}
            self::assertTrue($conflict);
            self::assertGreaterThan(0,(int)mg_control_scalar($pdo,"SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action='control.action_applied'",[$actor]));
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare('DELETE FROM control_action_events WHERE idempotency_key LIKE ?')->execute(['cp:'.$run.'%']);
            $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([$run.'%']);
        }
    }
}
