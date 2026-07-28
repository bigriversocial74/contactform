<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LearnMoreAgentQuestionnaireTest extends TestCase
{
    public function testQualificationFormPreservesExistingCrmSubmissionContract(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/learn-more.php');
        $script = file_get_contents($root . '/assets/js/learn-more.js');
        $styles = file_get_contents($root . '/assets/css/learn-more-v2.css');
        $illustration = file_get_contents($root . '/assets/images/learn-more-merchant-customer.svg');

        self::assertIsString($page);
        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertIsString($illustration);

        foreach (['name', 'email', 'phone', 'business_name', 'website_url', 'category', 'lead_type', 'message'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $page);
        }

        foreach (['use_cases[]', 'audiences[]', 'organization_type', 'location_count', 'start_preference', 'first_name', 'last_name', 'work_email', 'company_name', 'team_size', 'website', 'goals'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $page);
        }

        self::assertStringContainsString('/assets/css/learn-more-v2.css', $page);
        self::assertStringContainsString('/assets/images/learn-more-merchant-customer.svg', $page);
        self::assertStringContainsString('data-learn-more-form', $page);
        self::assertStringContainsString('data-learn-more-status', $page);

        self::assertStringContainsString('prepareCrmFields(form)', $script);
        self::assertStringContainsString("form.elements.name.value", $script);
        self::assertStringContainsString("form.elements.email.value", $script);
        self::assertStringContainsString("form.elements.message.value", $script);
        self::assertStringContainsString('/api/crm/leads/create.php', $script);
        self::assertStringContainsString('/api/crm/analytics/page-view.php', $script);
        self::assertStringContainsString('applyTrackingFields(form)', $script);
        self::assertStringContainsString('MG.readForm(form)', $script);
    }
}
