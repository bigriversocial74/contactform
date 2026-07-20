<?php
declare(strict_types=1);

function mg_task_agent_plan_selection_lower(string $value): string
{
    return mb_strtolower(trim($value));
}

function mg_task_agent_plan_selection_intent(string $message): string
{
    $text = mg_task_agent_plan_selection_lower($message);
    if (preg_match('/\b(show|view|open|review|continue)\b/u', $text) && preg_match('/\b(selected product|plan product|cart handoff)\b/u', $text)) return 'show_selection';
    if (preg_match('/\b(remove|detach|clear|change)\b/u', $text) && preg_match('/\b(selected product|plan product|from plan)\b/u', $text)) return 'remove_selection';
    if (preg_match('/\b(use|select|choose|attach|add)\b/u', $text) && preg_match('/\b(plan|gift plan|shortlist|shortlisted|product|gift)\b/u', $text)) return 'select_product';
    return '';
}
