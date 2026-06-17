<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ControlPlaneDocsTest extends TestCase
{
    public function testControlScopeNotesExist(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/docs/control-scope.md');
        self::assertIsString($source);
        self::assertStringContainsString('permission boundary',$source);
        self::assertStringContainsString('audit write',$source);
    }
}
