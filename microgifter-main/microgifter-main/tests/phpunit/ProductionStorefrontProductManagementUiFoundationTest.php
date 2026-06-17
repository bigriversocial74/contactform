<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionStorefrontProductManagementUiFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testRealDatabaseBehaviorMatrix(): void
    {
        if ((string)getenv('MG_RUN_STOREFRONT_PRODUCT_BEHAVIOR') !== '1') {
            self::markTestSkipped('Real-database storefront/product behavior runs in focused validation.');
        }
        if ((string)getenv('MG_DB_HOST') === '') {
            self::markTestSkipped('Database-backed storefront/product validation requires MG_DB_HOST.');
        }
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/validate_storefront_product_management_behavior.php') . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $raw = implode("\n", $output);
        self::assertSame(0, $exitCode, $raw);
        $result = json_decode($raw, true);
        self::assertIsArray($result, $raw);
        self::assertSame('storefront_product_management_ui_foundation', $result['suite'] ?? null);
        foreach ([
            'asset_projection', 'published_product_visible', 'storefront_ready',
            'draft_preserves_live_status', 'replacement_version_atomic',
            'version_history_immutable', 'storefront_tracks_current_version', 'rollback_clean',
        ] as $key) {
            self::assertTrue((bool)($result[$key] ?? false), $key . ' failed: ' . $raw);
        }
    }

    public function testCanonicalMerchantRoutesLoadDedicatedManagementAssets(): void
    {
        $storefront = $this->read('merchant-storefront.php');
        self::assertStringContainsString('/assets/css/merchant-storefront.css', $storefront);
        self::assertStringContainsString('/assets/js/merchant-storefront.js', $storefront);
        self::assertStringContainsString("\$merchantView='storefront'", $storefront);

        $products = $this->read('merchant-products.php');
        self::assertStringContainsString('/assets/css/merchant-products.css', $products);
        self::assertStringContainsString('/assets/js/merchant-products.js', $products);
        self::assertStringContainsString("\$merchantView='products'", $products);

        $detail = $this->read('merchant-product.php');
        self::assertStringContainsString('/assets/css/merchant-products.css', $detail);
        self::assertStringContainsString('/assets/js/merchant-products.js', $detail);
        self::assertStringContainsString("\$merchantView='product_detail'", $detail);
    }

    public function testStorefrontWorkspaceCoversIdentityMediaProductsPreviewAndReadiness(): void
    {
        $view = $this->read('includes/merchant-storefront-view.php');
        foreach ([
            'data-storefront-form', 'data-storefront-status', 'data-storefront-upload="storefront_logo"',
            'data-storefront-upload="storefront_cover"', 'data-storefront-products',
            'data-storefront-live-preview', 'data-storefront-readiness', 'data-storefront-publish',
            'data-storefront-archive', 'data-storefront-dirty-bar', 'data-storefront-error',
        ] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
    }

    public function testProductListAndEditorCoverFiltersLifecycleMediaAndVersions(): void
    {
        $list = $this->read('includes/merchant-products-view.php');
        foreach ([
            'data-product-search', 'data-product-status', 'data-product-type', 'data-builder-type',
            'data-product-sort', 'data-product-list', 'data-product-pagination', 'data-products-error',
        ] as $marker) {
            self::assertStringContainsString($marker, $list);
        }

        $detail = $this->read('includes/merchant-product-detail-view.php');
        foreach ([
            'data-product-editor-form', 'data-product-publish', 'data-product-archive',
            'data-product-upload="cover"', 'data-product-upload="inside_cover"',
            'data-product-upload="audio"', 'data-product-upload="video"',
            'data-product-readiness', 'data-product-versions', 'data-product-published-assets',
            'data-product-dirty-bar', 'data-product-detail-error',
        ] as $marker) {
            self::assertStringContainsString($marker, $detail);
        }
    }

    public function testStorefrontApiUsesExistingRevisionAuthorityAndReadiness(): void
    {
        $helper = $this->read('api/merchant/_storefront.php');
        foreach ([
            'function mg_storefront_revision_management(',
            'function mg_storefront_readiness(',
            'function mg_storefront_normalize_products(',
            "status='ready' AND asset_type='image'",
            'logo_asset_public_id', 'cover_asset_public_id', 'cover_preview_url',
        ] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }

        $endpoint = $this->read('api/merchant/storefront.php');
        foreach ([
            "mg_require_permission('storefront.manage')", 'mg_require_csrf_for_write(',
            'mg_storefront_readiness(', "action === 'publish'", "action === 'archive'",
            "revision_status='retired'", "revision_status='published'",
            'Complete the required storefront fields before publishing.',
        ] as $needle) {
            self::assertStringContainsString($needle, $endpoint);
        }
        self::assertStringNotContainsString('DROP TABLE', $endpoint);
        self::assertStringNotContainsString('TRUNCATE TABLE', $endpoint);
    }

    public function testProductQueryIsBoundedFilteredAndSearchEscaped(): void
    {
        $endpoint = $this->read('api/merchant/products.php');
        foreach ([
            "min(50,(int)(\$_GET['limit'] ?? 20))",
            "ESCAPE '='", "str_replace(['=','%','_']",
            'LIMIT {$limit} OFFSET {$offset}',
            'storefront_placement_count', 'has_draft_changes',
            "catalog.products.view", "catalog.products.manage", "catalog.products.publish",
        ] as $needle) {
            self::assertStringContainsString($needle, $endpoint);
        }
    }

    public function testBuilderDraftPreservesLivePublishedProductUntilExplicitPublish(): void
    {
        $builder = $this->read('api/catalog/builder-draft.php');
        foreach ([
            "\$productStatus = (string)\$product['status']",
            "if (\$productStatus === 'draft')",
            "live_status_preserved",
            "if (\$action === 'save')",
            "if (\$action !== 'publish')",
            "UPDATE catalog_products SET product_type=?,slug=?,current_version_id=?,status='published'",
            "UPDATE catalog_product_versions SET version_status='retired'",
            'mg_require_permission(\'catalog.products.publish\')',
        ] as $needle) {
            self::assertStringContainsString($needle, $builder);
        }
        self::assertStringNotContainsString("UPDATE catalog_products SET product_type = ?, slug = ?, status = ?", $builder);
    }

    public function testUploadRolesSupportStorefrontAndProductManagement(): void
    {
        $upload = $this->read('api/catalog/upload.php');
        foreach ([
            "'storefront_logo' => ['image']", "'storefront_cover' => ['image']",
            "'product_gallery' => ['image']", 'is_uploaded_file(', 'getimagesize(',
            "'private_local'", "status,metadata_json", 'mg_require_csrf_for_write(',
        ] as $needle) {
            self::assertStringContainsString($needle, $upload);
        }
    }

    public function testManagementControllersUseSafeDomProjection(): void
    {
        foreach (['assets/js/merchant-storefront.js', 'assets/js/merchant-products.js'] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('textContent', $source, $path);
            self::assertStringContainsString('replaceChildren', $source, $path);
            self::assertStringContainsString('safeUrl(', $source, $path);
            foreach (['.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval('] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $source, $path);
            }
        }
    }

    public function testResponsiveStylesCoverDesktopTabletMobileAndReducedMotion(): void
    {
        $storefront = $this->read('assets/css/merchant-storefront.css');
        foreach ([
            '.mg-storefront-workspace', '.mg-storefront-dirty-bar', '.mg-storefront-readiness',
            '@media(max-width:1180px)', '@media(max-width:820px)', '@media(max-width:620px)',
            '@media(prefers-reduced-motion:reduce)',
        ] as $needle) {
            self::assertStringContainsString($needle, $storefront);
        }

        $products = $this->read('assets/css/merchant-products.css');
        foreach ([
            '.mg-product-kpi-grid', '.mg-product-editor-workspace', '.mg-product-dirty-bar',
            '@media(max-width:1280px)', '@media(max-width:1080px)', '@media(max-width:760px)',
            '@media(max-width:520px)', '@media(prefers-reduced-motion:reduce)',
        ] as $needle) {
            self::assertStringContainsString($needle, $products);
        }
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $workflow = $this->read('.github/workflows/storefront-product-management-validation.yml');
        self::assertStringContainsString('test-storefront-product-management-behavior', $composer);
        foreach ([
            'MG_RUN_STOREFRONT_PRODUCT_BEHAVIOR',
            'composer test-storefront-product-management-behavior',
            'ProductionStorefrontProductManagementUiFoundationTest',
            'storefront-product-management-foundation.spec.js',
            'build_full_upgrade_sql.php', 'composer test-frontend-contracts',
            'composer test', 'npm run test:browser',
        ] as $needle) {
            self::assertStringContainsString($needle, $workflow);
        }
    }
}
