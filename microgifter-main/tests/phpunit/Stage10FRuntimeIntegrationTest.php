<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10FRuntimeIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if((string)getenv('MG_DB_NAME')==='')self::markTestSkipped('Database integration environment is not configured.');
        require_once dirname(__DIR__,2).'/api/microgifts/_claim_operations.php';
        $this->pdo=mg_db();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

        foreach(['microgift_operational_outbox','microgift_claim_attempts','microgift_claim_attempt_security','microgift_inbox_items'] as $table){
            $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            self::assertSame(1,(int)$stmt->fetchColumn(),'Missing required Stage 10F table: '.$table);
        }

        $column=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='folder'");
        $column->execute();
        self::assertSame(1,(int)$column->fetchColumn(),'Missing Stage 10F Action Center folder column.');
    }

    protected function tearDown(): void
    {
        if(isset($this->pdo) && $this->pdo->inTransaction()){
            $this->pdo->rollBack();
        }
    }

    public function testOutboxParticipatesInOwnerTransaction(): void
    {
        $aggregate='phpunit-'.bin2hex(random_bytes(8));
        $this->pdo->beginTransaction();
        try{
            $publicId=mg_claim_operational_outbox($this->pdo,'stage10f.transaction_test','phpunit',$aggregate,['test'=>true]);
            $this->pdo->rollBack();
        }catch(Throwable $error){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }

        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM microgift_operational_outbox WHERE public_id=?');
        $stmt->execute([$publicId]);
        self::assertSame(0,(int)$stmt->fetchColumn());
    }

    public function testFailedAttemptPersistsAfterDomainRollbackWithSeparateSecurityEnvelope(): void
    {
        $correlation='phpunit-'.bin2hex(random_bytes(8));
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("INSERT INTO microgift_operational_outbox (public_id,topic,aggregate_type,aggregate_public_id,payload_json,status,available_at,created_at,updated_at) VALUES (?,?,?,?,?,'pending',NOW(),NOW(),NOW())")
                ->execute([mg_microgift_uuid(),'stage10f.rollback_test','phpunit',$correlation,'{}']);
            $this->pdo->rollBack();
        }catch(Throwable $error){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }

        $attemptPublicId=mg_location_claim_record_attempt($this->pdo,[
            'result'=>'internal_error',
            'reason_code'=>'phpunit_rollback_test',
            'correlation_id'=>$correlation,
            'request_fingerprint'=>hash('sha256',$correlation),
            'metadata'=>['test'=>true],
        ]);

        try{
            $stmt=$this->pdo->prepare('SELECT a.id,COUNT(s.id) security_rows FROM microgift_claim_attempts a LEFT JOIN microgift_claim_attempt_security s ON s.attempt_id=a.id WHERE a.public_id=? GROUP BY a.id');
            $stmt->execute([$attemptPublicId]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($row);
            self::assertSame(1,(int)$row['security_rows']);
        }finally{
            $this->pdo->prepare('DELETE FROM microgift_claim_attempts WHERE public_id=?')->execute([$attemptPublicId]);
        }
    }

    public function testActionCenterFolderSchemaSupportsProductContract(): void
    {
        $type=$this->pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='folder'")->fetchColumn();
        self::assertIsString($type);
        foreach(['inbox','sent','claimed'] as $folder)self::assertStringContainsString("'{$folder}'",$type);
    }
}
