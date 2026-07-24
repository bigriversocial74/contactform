<?php
declare(strict_types=1);

function mg_investment_access_public_audited(array $row): array
{
    $public = mg_investment_access_public($row);
    unset($public['review_notes']);
    return $public;
}

function mg_investment_access_result_public_audited(array $result): array
{
    unset($result['review_notes']);
    return $result;
}
