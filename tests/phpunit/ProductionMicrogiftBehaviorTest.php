<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionMicrogiftBehaviorTest extends TestCase
{
    public function testMicrogiftLifecycleAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed Microgift validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_microgift_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);

        $buildDir=$root.'/build';
        if(!is_dir($buildDir))mkdir($buildDir,0775,true);
        file_put_contents($buildDir.'/microgift_behavior_result.log',$raw.PHP_EOL);

        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('microgift_lifecycle_behavior',$result['suite']??null);
        foreach([
            'issued','sent','send_replay','claimed','claim_replay','redeemed','redemption_replay',
            'action_center_consistent','invalid_transition_rolled_back','self_owned_projection','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testRunnerUsesCanonicalAuthoritiesAndOwningTransaction(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_microgift_behavior.php');
        self::assertIsString($source);
        foreach([
            'mg_microgift_issue(','mg_pppm_transfer_owner_canonical(','mg_action_center_sent(',
            'mg_microgift_claim(','mg_microgift_atomic_merchant_redeem(','mg_action_center_project_lifecycle(',
        ] as $authority){
            self::assertStringContainsString($authority,$source);
        }
        self::assertStringContainsString('SAVEPOINT invalid_redeem',$source);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT invalid_redeem',$source);
        self::assertStringContainsString('$pdo->rollBack()',$source);
    }
}
