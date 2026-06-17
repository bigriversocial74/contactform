<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActivityPersistenceTest extends TestCase
{
    public function testGiftActivityMigrationDefinesAuthoritativeTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_gift_activity_persistence.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gifts', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_events', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS message_threads', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS message_thread_participants', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS messages', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS notifications', $sql);
        self::assertStringContainsString('ON DELETE RESTRICT', $sql);
    }

    public function testMigrationRunnerIncludesGiftActivityMigration(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');
        self::assertIsString($runner);
        self::assertStringContainsString("'stage_3_gift_activity_persistence.sql'", $runner);
    }

    public function testGiftQueriesAreScopedToSenderOrRecipient(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/_gift.php');
        $list = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/list.php');
        $item = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/item.php');
        self::assertIsString($helper);
        self::assertIsString($list);
        self::assertIsString($item);
        self::assertStringContainsString('sender_user_id = ? OR', $helper);
        self::assertStringContainsString('recipient_user_id = ?', $helper);
        self::assertStringContainsString("mg_require_permission('gift.activity.view')", $list);
        self::assertStringContainsString("mg_require_permission('gift.activity.view')", $item);
        self::assertStringContainsString('mg_gift_require_accessible', $item);
    }

    public function testMessageEndpointsRequireParticipantMembership(): void
    {
        $threads = file_get_contents(dirname(__DIR__, 2) . '/api/messages/threads.php');
        $thread = file_get_contents(dirname(__DIR__, 2) . '/api/messages/thread.php');
        $send = file_get_contents(dirname(__DIR__, 2) . '/api/messages/send.php');
        foreach ([$threads, $thread, $send] as $source) {
            self::assertIsString($source);
            self::assertStringContainsString("mg_require_permission('gift.message.send')", $source);
            self::assertStringContainsString('message_thread_participants', $source);
        }
        self::assertStringContainsString('mtp.user_id = ?', $thread);
        self::assertStringContainsString('mtp.user_id = ?', $send);
        self::assertStringContainsString('mg_gift_require_accessible', $send);
    }

    public function testNotificationEndpointsAreUserScopedAndBadgesHideAtZero(): void
    {
        $root = dirname(__DIR__, 2);
        $index = file_get_contents($root . '/api/notifications/index.php');
        $read = file_get_contents($root . '/api/notifications/read.php');
        $signals = file_get_contents($root . '/assets/js/header-signals.js');
        $header = file_get_contents($root . '/includes/header.php');
        $appHeader = file_get_contents($root . '/includes/header-components/app-header.php');
        $loggedInHeader = file_get_contents($root . '/includes/header-templates/logged-in.php');
        foreach ([$index, $read, $signals, $header, $appHeader, $loggedInHeader] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('WHERE user_id = ?', $index);
        self::assertStringContainsString('WHERE public_id = ? AND user_id = ?', $read);
        self::assertStringContainsString("badge.hidden = value === 0", $signals);
        self::assertStringContainsString('app-header.php', $header);
        self::assertStringContainsString('logged-in.php', $appHeader);
        self::assertStringContainsString('data-notification-badge hidden>0', $loggedInHeader);
        self::assertStringContainsString('data-message-badge hidden>0', $loggedInHeader);
    }

    public function testGiftActivityUiUsesApisInsteadOfServerPlaceholderRows(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2) . '/includes/agent-list-workspace.php');
        $items = file_get_contents(dirname(__DIR__, 2) . '/assets/js/agent-items.js');
        $signals = file_get_contents(dirname(__DIR__, 2) . '/assets/js/header-signals.js');
        self::assertIsString($workspace);
        self::assertIsString($items);
        self::assertIsString($signals);
        self::assertStringNotContainsString('foreach ($workspaceItems', $workspace);
        self::assertStringContainsString('/api/gifts/list.php?box=', $items);
        self::assertStringContainsString('/api/gifts/item.php?id=', $items);
        self::assertStringContainsString('/api/messages/send.php', $items);
        self::assertStringContainsString('/api/notifications/index.php', $signals);
        self::assertStringContainsString('/api/messages/threads.php', $signals);
    }
}
