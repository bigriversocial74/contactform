<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminContentReviewContractTest extends TestCase
{
    public function testContentReviewMigrationPrecedesLaterStagesAndCreatesUnifiedSafetyTables(): void
    {
        $root=dirname(__DIR__,2);
        $manifest=require $root.'/config/migrations.php';
        $migration=file_get_contents($root.'/database/stage_18j_content_moderation.sql');
        self::assertIsArray($manifest);
        $ordered=array_values($manifest['ordered_files']);
        $contentIndex=array_search('stage_18j_content_moderation.sql',$ordered,true);
        $accountIndex=array_search('stage_18k_admin_account_management.sql',$ordered,true);
        self::assertNotFalse($contentIndex);
        self::assertNotFalse($accountIndex);
        self::assertTrue($contentIndex<$accountIndex);
        self::assertIsString($migration);
        foreach([
            "ENUM('profile','post','comment','media','message','user')",
            'subject_snapshot_json',
            'CREATE TABLE IF NOT EXISTS content_moderation_actions',
            'CREATE TABLE IF NOT EXISTS user_moderation_restrictions',
            'moderation_status',
            'trg_catalog_assets_review_state',
            "'admin.moderation.view'",
            "'admin.moderation.manage'",
            "'stage_18j_content_moderation'",
        ] as $needle) self::assertStringContainsString($needle,$migration);
    }

    public function testUserReportsResolveAuthorizedSubjectsAndDeduplicate(): void
    {
        $root=dirname(__DIR__,2);
        $helper=file_get_contents($root.'/api/social/_reports.php');
        $endpoint=file_get_contents($root.'/api/social/report.php');
        self::assertIsString($helper);
        self::assertIsString($endpoint);
        foreach(['profile','post','comment','media','message','user'] as $type) {
            self::assertStringContainsString("'{$type}'",$endpoint);
        }
        self::assertStringContainsString('message_thread_participants',$helper);
        self::assertStringContainsString('mg_engagement_post',$helper);
        self::assertStringContainsString('You cannot report your own',$helper);
        self::assertStringContainsString('subject_snapshot_json',$endpoint);
        self::assertStringContainsString("status IN ('open','reviewing')",$endpoint);
        self::assertStringContainsString('content_moderation_actions',$endpoint);
        self::assertStringContainsString('mg_social_report_flag_subject',$endpoint);
    }

    public function testQueueAndEvidenceArePermissionCheckedAndPrivate(): void
    {
        $root=dirname(__DIR__,2);
        $common=file_get_contents($root.'/api/admin/content-review/_common.php');
        $queue=file_get_contents($root.'/api/admin/content-review/queue.php');
        $detail=file_get_contents($root.'/api/admin/content-review/detail.php');
        $evidence=file_get_contents($root.'/api/admin/content-review/_evidence.php');
        foreach([$common,$queue,$detail,$evidence] as $source) self::assertIsString($source);
        self::assertStringContainsString('admin.moderation.view',$common);
        self::assertStringContainsString('admin.moderation.manage',$common);
        self::assertStringContainsString("mg_require_method('GET')",$queue);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0',$queue);
        self::assertStringContainsString('LIMIT {$limit} OFFSET {$offset}',$queue);
        self::assertStringContainsString("FIELD(r.severity,'urgent','high','normal','low')",$queue);
        self::assertStringContainsString('mg_review_evidence',$detail);
        self::assertStringContainsString('pp.bio biography',$evidence);
        self::assertStringContainsString('user_moderation_restrictions',$evidence);
        self::assertStringContainsString('content_moderation_actions',$evidence);
    }

    public function testActionsAreTransactionalRateLimitedAndAudited(): void
    {
        $root=dirname(__DIR__,2);
        $endpoint=file_get_contents($root.'/api/admin/content-review/action.php');
        $actions=file_get_contents($root.'/api/admin/content-review/_actions.php');
        self::assertIsString($endpoint);
        self::assertIsString($actions);
        self::assertStringContainsString("mg_require_method('POST')",$endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write',$endpoint);
        self::assertStringContainsString('mg_rate_limit',$endpoint);
        self::assertStringContainsString('beginTransaction',$endpoint);
        self::assertStringContainsString('mg_audit',$endpoint);
        foreach(['hide_content','restore_content','quarantine_media','warn_user','restrict_posting','suspend_user','reactivate_user'] as $action) {
            self::assertStringContainsString("'{$action}'",$endpoint.$actions);
        }
        self::assertStringContainsString('mg_content_review_target_is_super_admin',$actions);
        self::assertStringContainsString('user_moderation_restrictions',$actions);
        self::assertStringContainsString('mg_create_notification',$actions);
    }

    public function testPostingMessagingAndMediaRestrictionsAreEnforced(): void
    {
        $root=dirname(__DIR__,2);
        $restrictions=file_get_contents($root.'/api/social/_account_restrictions.php');
        $posts=file_get_contents($root.'/api/social/posts.php');
        $upload=file_get_contents($root.'/api/social/media-upload.php');
        $messages=file_get_contents($root.'/api/messages/send.php');
        $thread=file_get_contents($root.'/api/messages/thread.php');
        foreach([$restrictions,$posts,$upload,$messages,$thread] as $source) self::assertIsString($source);
        self::assertStringContainsString('user_moderation_restrictions',$restrictions);
        self::assertStringContainsString("?='uploading' AND restriction_type='posting'",$restrictions);
        self::assertStringContainsString("mg_require_user_not_restricted(\$pdo, \$actorId, 'posting')",$posts);
        self::assertStringContainsString("mg_require_user_not_restricted(\$pdo,\$userId,'uploading')",$upload);
        self::assertStringContainsString("mg_require_user_not_restricted(\$pdo, (int)\$user['id'], 'messaging')",$messages);
        self::assertStringContainsString("m.moderation_status NOT IN ('hidden','removed')",$thread);
    }

    public function testAdminInterfaceLoadsQueueEvidenceAndConfirmedActions(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/admin/moderation.php');
        $dashboard=file_get_contents($root.'/includes/account/admin-dashboard.php');
        $footer=file_get_contents($root.'/includes/footer.php');
        $client=file_get_contents($root.'/assets/js/admin-moderation.js');
        $actions=file_get_contents($root.'/assets/js/content-review-actions.js');
        $style=file_get_contents($root.'/assets/css/admin-moderation.css');
        foreach([$page,$dashboard,$footer,$client,$actions,$style] as $source) self::assertIsString($source);
        self::assertStringContainsString('/admin/moderation.php',$dashboard);
        self::assertStringContainsString('mg-admin-moderation-page',$page);
        self::assertStringContainsString('/assets/js/admin-moderation.js',$footer);
        self::assertStringContainsString("'/assets/js/content-' . 'review-actions.js'",$footer);
        self::assertStringContainsString('/api/admin/content-review/queue.php',$client);
        self::assertStringContainsString('/api/admin/content-review/detail.php',$client);
        self::assertStringContainsString('/api/admin/content-review/action.php',$actions);
        self::assertStringContainsString('window.confirm',$actions);
        self::assertStringContainsString('.mg-admin-moderation-workspace',$style);
    }
}
