<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class LearnMoreAgentQuestionnaireTest extends TestCase
{
    public function testLearnMoreUsesFullWidthStickyQuestionStages(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/learn-more.php');
        self::assertIsString($page);
        self::assertStringContainsString('data-learn-more-agent',$page);
        self::assertStringContainsString('class="lm-question"',$page);
        self::assertStringContainsString('class="lm-agent-pin"',$page);
        self::assertStringContainsString('position:sticky',$page);
        self::assertStringContainsString('min-height:260vh',$page);
    }

    public function testQuestionnairePreservesOriginalLeadFields(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/learn-more.php');
        self::assertIsString($page);

        foreach(['name','email','phone','zip_code','business_name','website_url','category'] as $field){
            self::assertStringContainsString("['{$field}',",$page);
        }

        self::assertStringContainsString('name="<?= $step[0] ?>"',$page);
        self::assertStringContainsString('name="lead_type"',$page);
        self::assertStringContainsString('name="message"',$page);
        self::assertStringContainsString('data-learn-more-form',$page);
        self::assertStringContainsString('data-learn-more-status',$page);
    }

    public function testQuestionnaireSupportsNextBackSkipReviewAndReplay(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/learn-more.php');
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/learn-more.js');
        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString('data-lm-next',$page);
        self::assertStringContainsString('data-lm-back',$page);
        self::assertStringContainsString('data-lm-skip',$page);
        self::assertStringContainsString('data-lm-review',$page);
        self::assertStringContainsString('data-lm-replay',$page);
        self::assertStringContainsString('function renderReview()',$script);
        self::assertStringContainsString('function activate(nextIndex)',$script);
    }

    public function testLeadSubmissionEndpointAndTrackingRemainIntact(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/learn-more.js');
        self::assertIsString($script);
        self::assertStringContainsString('/api/crm/leads/create.php',$script);
        self::assertStringContainsString('/api/crm/analytics/page-view.php',$script);
        self::assertStringContainsString('applyTrackingFields(form)',$script);
        self::assertStringContainsString('MG.readForm(form)',$script);
    }
}
