<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ResponseBootstrapIdempotencyTest extends TestCase
{
    public function testResponseHelpersAreConditionallyDeclared(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/response.php');
        self::assertIsString($source);
        foreach(['mg_json','mg_ok','mg_fail','mg_input'] as $helper){
            self::assertStringContainsString("function_exists('{$helper}')",$source);
        }
    }
}
