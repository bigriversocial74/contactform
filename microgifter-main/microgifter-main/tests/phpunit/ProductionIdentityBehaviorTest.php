<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionIdentityBehaviorTest extends TestCase
{
    public function testIdentitySessionRecoveryAndPermissionsAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed identity validation requires MG_DB_HOST.');
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_identity_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('identity_session_recovery_permission_behavior',$result['suite']??null);
        foreach([
            'registered','normalized_duplicate_rejected','password_hash_safe','valid_login','generic_invalid_credentials',
            'inactive_login_blocked','sessions_recorded','session_replay_safe','logout_revoked','global_logout_revoked',
            'expired_session_rejected','reset_token_hashed','reset_token_single_use','reset_revoked_sessions',
            'verification_single_use','permission_enforced','role_audit_consistent','forced_failure_rolled_back','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testIdentityEndpointsAndSecurityUseCanonicalAuthorities(): void
    {
        $root=dirname(__DIR__,2);
        $register=file_get_contents($root.'/api/auth/register.php');
        $login=file_get_contents($root.'/api/auth/login.php');
        $core=file_get_contents($root.'/api/auth/_identity_core.php');
        $security=file_get_contents($root.'/api/security.php');
        $bootstrap=file_get_contents($root.'/api/bootstrap.php');
        foreach([$register,$login,$core,$security,$bootstrap] as $source)self::assertIsString($source);
        self::assertStringContainsString('mg_identity_register(',$register);
        self::assertStringContainsString('mg_identity_authenticate(',$login);
        self::assertStringContainsString('mg_identity_normalize_email(',$core);
        self::assertStringContainsString('password_hash(',$core);
        self::assertStringContainsString('password_verify(',$core);
        self::assertStringContainsString('mg_record_user_session(',$security);
        self::assertStringContainsString('mg_session_is_active(',$security);
        self::assertStringContainsString('mg_revoke_user_sessions(',$security);
        self::assertStringContainsString('mg_require_csrf_for_write(',$bootstrap);
        self::assertStringContainsString('mg_require_permission(',$bootstrap);
    }
}
