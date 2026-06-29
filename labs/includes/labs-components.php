<?php
/**
 * Training Lab Stage 1 reusable UI component helpers.
 *
 * Components are static markup helpers only. Do not add backend writes,
 * real uploads, payment processing, or reward issuing here.
 */

if (!function_exists('labs_e')) {
    function labs_e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('labs_stat_card')) {
    function labs_stat_card(string $label, string $value, string $note = ''): void
    {
        echo '<div class="labs-kpi"><span class="labs-muted">' . labs_e($label) . '</span><strong>' . labs_e($value) . '</strong>';
        if ($note !== '') {
            echo '<small>' . labs_e($note) . '</small>';
        }
        echo '</div>';
    }
}

if (!function_exists('labs_status_pill')) {
    function labs_status_pill(string $label): void
    {
        echo '<span class="labs-pill">' . labs_e($label) . '</span>';
    }
}

if (!function_exists('labs_empty_state')) {
    function labs_empty_state(string $title, string $copy, string $actionLabel = '', string $actionHref = '#'): void
    {
        echo '<section class="labs-card labs-empty-state">';
        echo '<span class="labs-mini-icon">+</span>';
        echo '<h2>' . labs_e($title) . '</h2>';
        echo '<p class="labs-muted">' . labs_e($copy) . '</p>';
        if ($actionLabel !== '') {
            echo '<a class="labs-btn labs-btn-primary" href="' . labs_e($actionHref) . '">' . labs_e($actionLabel) . '</a>';
        }
        echo '</section>';
    }
}

if (!function_exists('labs_table_start')) {
    function labs_table_start(array $headers): void
    {
        echo '<div class="labs-table-wrap"><table class="labs-table"><thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . labs_e($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
    }
}

if (!function_exists('labs_table_end')) {
    function labs_table_end(): void
    {
        echo '</tbody></table></div>';
    }
}
