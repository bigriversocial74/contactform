<?php
declare(strict_types=1);
return [
    'enabled' => true,
    'page' => 'home',
    'autoplay' => true,
    'selector' => '.hero,[data-sticky-section],.revenue-sticky,[data-presentation-section]',
    'scrollDurationMs' => 780,
    'customTimeline' => 'home-hero-revenue',
    'sections' => [
        ['type' => 'custom', 'minReadMs' => 0, 'maxReadMs' => 0],
    ],
];
