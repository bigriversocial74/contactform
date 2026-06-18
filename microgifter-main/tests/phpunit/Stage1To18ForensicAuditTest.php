<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage1To18ForensicAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testBootstrapIsSafeForCliAndMissingRequestMethod(): void
    {
        $source=$this->read('api/bootstrap.php');
        self::assertStringContainsString("\$_SERVER['REQUEST_METHOD'] ?? 'GET'",$source);
        self::assertStringNotContainsString("if (\$_SERVER['REQUEST_METHOD'] === 'OPTIONS')",$source);
    }

    public function testRegistrationPersistsRegeneratedSessionOnce(): void
    {
        $bootstrap=$this->read('api/bootstrap.php');
        $register=$this->read('api/auth/register.php');
        self::assertStringContainsString('mg_record_user_session((int) $user[\'id\']);',$bootstrap);
        self::assertSame(1,substr_count($register,'mg_set_session_user($user)'));
        self::assertStringNotContainsString('mg_record_user_session($userId)',$register);
    }

    public function testLegacyLedgerWriterCannotBypassStage7Authority(): void
    {
        $legacy=$this->read('api/payments/_payments.php');
        $canonical=$this->read('api/finance/_posting.php');
        self::assertStringContainsString('Legacy financial_ledger_entries posting is disabled.',$legacy);
        self::assertStringNotContainsString('INSERT INTO financial_ledger_entries',$legacy);
        self::assertStringContainsString('mg_ledger_post(',$canonical);
        self::assertStringContainsString("'idempotency_key'=>'order:paid:'",$canonical);
        self::assertStringContainsString("'idempotency_key'=>'refund:'",$canonical);
    }

    public function testCanonicalMicrogiftEndpointsProjectWithinTransactions(): void
    {
        foreach(['api/microgifts/issue.php','api/microgifts/claim.php'] as $path){
            $source=$this->read($path);
            self::assertStringContainsString('$pdo->beginTransaction()',$source);
            self::assertStringContainsString('mg_action_center_project_lifecycle(',$source);
            self::assertLessThan(strpos($source,'$pdo->commit()'),strpos($source,'mg_action_center_project_lifecycle('));
        }
    }
}
