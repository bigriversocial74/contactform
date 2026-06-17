<?php
declare(strict_types=1);
return [
    'enabled' => true,
    'page' => 'learn-more',
    'autoplay' => true,
    'selector' => '[data-lm-stage]',
    'scrollDurationMs' => 700,
    'sections' => array_merge(
        [['type' => 'content', 'minReadMs' => 1800, 'maxReadMs' => 2600]],
        array_fill(0, 9, ['type' => 'questionnaire', 'waitForUser' => true]),
        [['type' => 'questionnaire', 'waitForUser' => true]]
    ),
];
