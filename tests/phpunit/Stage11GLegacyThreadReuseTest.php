<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11GLegacyThreadReuseTest extends TestCase
{
    public function testLegacyThreadLookupUsesGiftAndPppmKeys(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/messages/_messaging.php');
        self::assertIsString($source);
        self::assertStringContainsString('pppm_item_id=?',$source);
        self::assertStringContainsString('gift_id=?',$source);
        self::assertStringContainsString('UPDATE message_threads SET microgift_instance_id=',$source);
    }
}
