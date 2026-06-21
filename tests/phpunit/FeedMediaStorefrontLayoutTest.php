<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeedMediaStorefrontLayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testFeedUploadsInitializePersistentStorageBeforeMovingFile(): void
    {
        $storage = file_get_contents($this->root . '/includes/storage.php');
        $upload = file_get_contents($this->root . '/api/social/media-upload.php');

        self::assertIsString($storage);
        self::assertIsString($upload);
        self::assertStringContainsString('function mg_storage_store_uploaded_file', $storage);
        self::assertStringContainsString('mg_storage_assert_ready(true,false);', $storage);
        self::assertStringContainsString('move_uploaded_file($temporaryPath,$path)', $storage);
        self::assertStringContainsString('Persistent media storage sentinel could not be created.', $storage);
        self::assertStringContainsString('Persistent media storage is unavailable. The upload was not saved.', $upload);
    }

    public function testPersistentCopyAlsoInitializesStorage(): void
    {
        $storage = file_get_contents($this->root . '/includes/storage.php');

        self::assertIsString($storage);
        self::assertStringContainsString('function mg_storage_copy_file', $storage);
        self::assertStringContainsString('mg_storage_assert_ready(true,false);', $storage);
    }

    public function testPublicStorefrontRemovesMerchantStorefrontLabel(): void
    {
        $source = file_get_contents($this->root . '/assets/js/public-catalog.js');

        self::assertIsString($source);
        self::assertStringContainsString('function renderStore()', $source);
        self::assertStringNotContainsString('Merchant storefront', $source);
        self::assertStringNotContainsString('mg-product-eyebrow">Merchant storefront', $source);
        self::assertStringContainsString('<h1>' . "' + escapeHtml(store.display_name) + '" . '</h1>', $source);
    }

    public function testPublicStorefrontCoverIsFullWidthWithoutInnerPadding(): void
    {
        $css = file_get_contents($this->root . '/assets/css/public-catalog.css');

        self::assertIsString($css);
        self::assertStringContainsString('.mg-store-hero{padding:0;position:relative}', $css);
        self::assertStringContainsString('.mg-store-cover{width:100%;height:320px', $css);
        self::assertStringContainsString('border-radius:0;overflow:hidden', $css);
        self::assertStringContainsString('.mg-store-cover img{width:100%;height:100%;object-fit:cover;display:block}', $css);
        self::assertStringNotContainsString('.mg-store-hero{padding:44px', $css);
        self::assertStringNotContainsString('.mg-store-cover{height:260px', $css);
    }
}
