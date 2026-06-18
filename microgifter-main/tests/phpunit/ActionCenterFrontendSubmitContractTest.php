<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterFrontendSubmitContractTest extends TestCase
{
    public function testFrontendActionPayloadsIncludeBackendRequiredFields(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-actions.js');
        self::assertIsString($source);

        self::assertStringContainsString('action_item_id:item.action_item_id',$source);
        self::assertStringContainsString('idempotency_key:key(type,item)',$source);
        self::assertStringContainsString('request.recipient_user_id=data.recipient_user_id',$source);
        self::assertStringContainsString('request.recipient=data.recipient_user_id||data.recipient',$source);
        self::assertStringContainsString('request.code=data.claim_code',$source);
        self::assertStringContainsString('request.message=data.message',$source);
        self::assertStringContainsString('Microgifter.post(endpoint(type),payload(type,item,data))',$source);
    }

    public function testActionScriptInterceptsModalSubmitsBeforeBasePreview(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-actions.js');
        self::assertIsString($source);

        self::assertStringContainsString("modalBody.addEventListener('submit',function(event)",$source);
        self::assertStringContainsString("form.dataset.actionForm==='redeem'",$source);
        self::assertStringContainsString('event.stopImmediatePropagation()',$source);
        self::assertStringContainsString('dispatchActionSubmit(type,item,data)',$source);
    }
}
