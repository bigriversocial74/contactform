<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterModalPortalCloseTest extends TestCase
{
    public function testPortalScriptKeepsCloseButtonsWorkingAfterMovingModalToBody(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-modal-portal.js');
        self::assertIsString($source);

        foreach([
            'function closeActionModal()',
            "document.querySelectorAll('.mg-action-modal')",
            "modal.setAttribute('aria-hidden', 'true')",
            "backdrop.hidden = true",
            "document.body.classList.remove('mg-modal-lock', 'mg-action-modal-open')",
            "event.target.closest('[data-action-modal-close]')",
            "event.target.closest('[data-action-modal-backdrop]')",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
