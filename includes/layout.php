<?php
/**
 * Layout helper for simple server-rendered pages.
 */

declare(strict_types=1);

function mg_render_page(string $title, string $section, callable $content): void
{
    $page_title = $title;
    $page_section = $section;
    require __DIR__ . '/header.php';
    $content();
    require __DIR__ . '/footer.php';
}
