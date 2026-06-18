<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4BBuilderPersistenceTest extends TestCase
{
    public function testBuilderSchemaDefinesDraftConcurrencyAndVideoRole(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_4b_builder_persistence.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS catalog_builder_drafts', $sql);
        self::assertStringContainsString('lock_version INT UNSIGNED NOT NULL DEFAULT 1', $sql);
        self::assertStringContainsString("'multimedia_greeting_card'", $sql);
        self::assertStringContainsString("'video'", $sql);
    }

    public function testBuilderIncludesFourTemplateTypesAndAudioVideoInputs(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/build.php');
        $sidebar = file_get_contents($root . '/includes/product-builder-sidebar.php');
        self::assertIsString($page);
        self::assertIsString($sidebar);
        self::assertStringContainsString("require __DIR__ . '/includes/product-builder-sidebar.php'", $page);
        foreach (['simple_product','greeting_card','multimedia_greeting_card','simple_collab'] as $type) {
            self::assertStringContainsString('value="' . $type . '"', $sidebar);
            self::assertStringContainsString('data-preview-template="' . $type . '"', $page);
        }
        self::assertStringContainsString('data-asset-role="audio"', $sidebar);
        self::assertStringContainsString('data-asset-role="video"', $sidebar);
        self::assertStringContainsString('mg-builder-canvas', $page);
        self::assertStringContainsString('header_mode', $page);
    }

    public function testDraftApiUsesOwnershipConcurrencyAndSharedPublishService(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/api/catalog/builder-draft.php');
        $distribution = file_get_contents($root . '/api/catalog/_publish_distribution.php');
        self::assertIsString($source);
        self::assertIsString($distribution);
        self::assertStringContainsString("mg_require_permission('catalog.products.manage')", $source);
        self::assertStringContainsString('mg_catalog_product_for_update', $source);
        self::assertStringContainsString('lock_version', $source);
        self::assertStringContainsString('This draft changed in another session.', $source);
        self::assertStringContainsString('mg_catalog_publish_distribution(', $source);
        self::assertStringContainsString('INSERT INTO catalog_pppm_templates', $distribution);
        self::assertStringNotContainsString('INSERT INTO pppm_items', $source);
        self::assertStringNotContainsString('INSERT INTO pppm_items', $distribution);
    }

    public function testMediaUploadValidatesMimeSizeAndPrivateStorage(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/upload.php');
        self::assertIsString($source);
        self::assertStringContainsString('is_uploaded_file', $source);
        self::assertStringContainsString('FILEINFO_MIME_TYPE', $source);
        self::assertStringContainsString("'private_local'", $source);
        self::assertStringContainsString("'/storage/private'", $source);
        self::assertStringContainsString('157286400', $source);
        self::assertStringNotContainsString('public/uploads', $source);
    }

    public function testMediaStreamingIsAuthenticatedAndPathContained(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/asset-file.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('catalog.assets.manage')", $source);
        self::assertStringContainsString('realpath', $source);
        self::assertStringContainsString('str_starts_with', $source);
        self::assertStringContainsString('X-Content-Type-Options: nosniff', $source);
    }

    public function testBuilderJavascriptPersistsAndRoutesMediaRoles(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/builder-stage4b.js');
        self::assertIsString($source);
        self::assertStringContainsString('/api/catalog/builder-draft.php', $source);
        self::assertStringContainsString('/api/catalog/upload.php', $source);
        self::assertStringContainsString("assets = { cover: '', inside_cover: '', audio: '', video: '' }", $source);
        self::assertStringContainsString('saveDraft(true)', $source);
    }
}
