<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterModalPortalCloseTest extends TestCase
{
    public function testPortalScriptKeepsHeaderCloseWorkingAfterMovingModalToBody(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-modal-portal.js');
        self::assertIsString($source);

        foreach([
            'function closeActionModal()',
            "document.querySelectorAll('.mg-action-modal')",
            "modal.setAttribute('aria-hidden', 'true')",
            'backdrop.hidden = true',
            "document.body.classList.remove('mg-modal-lock', 'mg-action-modal-open')",
            "event.target.closest('[data-action-modal-close]')",
            "event.target.closest('[data-action-modal-backdrop]')",
            "close.setAttribute('data-action-modal-close', '')",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testRegiftUsesOnlyTheHeaderCloseAndPrimaryReviewAction(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-modal-portal.js');
        self::assertIsString($source);

        self::assertStringContainsString(
            "const cancel = actions && actions.querySelector('.mg-send-exact-secondary,[data-action-modal-close]')",
            $source
        );
        self::assertStringContainsString('if (cancel) cancel.remove();',$source);
        self::assertStringContainsString("actions.dataset.singleAction = 'true'",$source);
        self::assertStringNotContainsString("cancel.textContent = 'Cancel'",$source);
    }
}
