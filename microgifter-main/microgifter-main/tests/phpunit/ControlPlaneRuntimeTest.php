<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/admin/_controls.php';

final class ControlPlaneRuntimeTest extends TestCase
{
    public function testSchemaGuardDoesNotRunInsideActiveTransaction(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed control validation requires MG_DB_HOST.');
        $pdo=mg_db();
        mg_control_install($pdo);
        $pdo->beginTransaction();
        try{
            $pdo->exec('SAVEPOINT cp_guard');
            mg_control_ensure($pdo);
            $pdo->exec('ROLLBACK TO SAVEPOINT cp_guard');
            self::assertTrue(true);
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
        }
    }
}
