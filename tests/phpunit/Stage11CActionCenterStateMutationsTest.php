<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11CActionCenterStateMutationsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($content);
        return $content;
    }

    public function testMutationEndpointsCallExpectedHelpers(): void
    {
        $endpoints = [
            'api/account/action-center-read.php' => 'mg_action_center_mark_read(',
            'api/account/action-center-unread.php' => 'mg_action_center_mark_unread(',
            'api/account/action-center-archive.php' => 'mg_action_center_archive(',
            'api/account/action-center-restore.php' => 'mg_action_center_restore(',
        ];

        foreach ($endpoints as $file => $expectedCall) {
            $source = $this->read($file);
            self::assertStringContainsString("mg_require_method('POST')", $source);
            self::assertStringContainsString($expectedCall, $source);
        }
    }

    public function testMutationHelpersExistAndRemainUserScoped(): void
    {
        $source = $this->read('api/account/_action_center.php');
        $helpers = [
            'function mg_action_center_mark_read(',
            'function mg_action_center_mark_unread(',
            'function mg_action_center_archive(',
            'function mg_action_center_restore(',
        ];

        foreach ($helpers as $helper) {
            self::assertStringContainsString($helper, $source);
        }

        self::assertGreaterThanOrEqual(
            4,
            substr_count($source, 'WHERE user_id=? AND public_id=?'),
            'Every Action Center mutation must remain scoped to the authenticated user and public item id.'
        );
    }
}
