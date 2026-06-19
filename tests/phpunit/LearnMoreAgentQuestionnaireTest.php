<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LearnMoreAgentQuestionnaireTest extends TestCase
{
    public function testLeadFormPreservesSubmissionFieldsAndApiWiring(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/learn-more.php');
        $script = file_get_contents($root . '/assets/js/learn-more.js');

        self::assertIsString($page);
        self::assertIsString($script);

        foreach (['name','email','phone','zip_code','business_name','website_url','category'] as $field) {
            self::assertStringContainsString("['{$field}',", $page);
        }

        self::assertStringContainsString('name="lead_type"', $page);
        self::assertStringContainsString('name="message"', $page);
        self::assertStringContainsString('data-learn-more-form', $page);
        self::assertStringContainsString('data-learn-more-status', $page);
        self::assertStringContainsString('/api/crm/leads/create.php', $script);
        self::assertStringContainsString('/api/crm/analytics/page-view.php', $script);
        self::assertStringContainsString('applyTrackingFields(form)', $script);
        self::assertStringContainsString('MG.readForm(form)', $script);
    }
}
