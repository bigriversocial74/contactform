<?php
declare(strict_types=1);

function mg_creator_campaign_application_answer_map(array $answers): array
{
    $map = [];
    foreach ($answers as $key => $value) {
        if (is_array($value) && array_key_exists('question_id', $value)) {
            $questionId = trim((string) $value['question_id']);
            $answer = $value['answer'] ?? null;
        } else {
            $questionId = is_string($key) ? trim($key) : '';
            $answer = $value;
        }
        if ($questionId !== '') $map[$questionId] = $answer;
    }
    return $map;
}

function mg_creator_campaign_application_normalize_answer(array $question, mixed $answer, bool $requireComplete): mixed
{
    $type = (string) $question['question_type'];
    $required = $requireComplete && !empty($question['is_required']);
    $options = mg_creator_campaign_participation_decode_json($question['options_json'] ?? null) ?: [];

    if ($type === 'multiple_choice') {
        $value = is_array($answer) ? array_values(array_unique(array_filter(array_map(
            static fn(mixed $item): string => trim((string) $item),
            $answer
        ), static fn(string $item): bool => $item !== ''))) : [];
        foreach ($value as $item) {
            if (!in_array($item, $options, true)) throw new InvalidArgumentException('Application answer contains an invalid option.');
        }
        if ($required && $value === []) throw new InvalidArgumentException((string) $question['prompt'] . ' is required.');
        return $value;
    }

    if ($type === 'boolean') {
        if ($answer === null || $answer === '') {
            if ($required) throw new InvalidArgumentException((string) $question['prompt'] . ' is required.');
            return null;
        }
        return filter_var($answer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw new InvalidArgumentException('Application boolean answer is invalid.');
    }

    if ($type === 'number') {
        if ($answer === null || $answer === '') {
            if ($required) throw new InvalidArgumentException((string) $question['prompt'] . ' is required.');
            return null;
        }
        if (!is_numeric($answer)) throw new InvalidArgumentException('Application number answer is invalid.');
        return (float) $answer;
    }

    $value = trim((string) ($answer ?? ''));
    if ($required && $value === '') throw new InvalidArgumentException((string) $question['prompt'] . ' is required.');
    if ($value === '') return null;

    $max = $type === 'long_text' ? 8000 : 1000;
    if (mb_strlen($value) > $max) throw new InvalidArgumentException('Application answer is too long.');
    if (in_array($type, ['url','portfolio_link'], true) && filter_var($value, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Application URL answer is invalid.');
    }
    if ($type === 'single_choice' && !in_array($value, $options, true)) {
        throw new InvalidArgumentException('Application answer contains an invalid option.');
    }
    return $value;
}

function mg_creator_campaign_application_write_answers(
    PDO $pdo,
    int $applicationId,
    int $campaignId,
    array $answers,
    bool $requireComplete
): void {
    $questions = $pdo->prepare(
        'SELECT id,public_id,prompt,question_type,options_json,is_required,sort_order
         FROM creator_campaign_application_questions WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $questions->execute([$campaignId]);
    $rows = $questions->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = mg_creator_campaign_application_answer_map($answers);
    $known = [];

    $pdo->prepare('DELETE FROM creator_campaign_application_answers WHERE application_id=?')->execute([$applicationId]);
    $insert = $pdo->prepare(
        'INSERT INTO creator_campaign_application_answers
         (public_id,application_id,question_id,answer_json,created_at,updated_at)
         VALUES (?,?,?,?,NOW(),NOW())'
    );

    foreach ($rows as $question) {
        $publicId = (string) $question['public_id'];
        $known[$publicId] = true;
        $answer = mg_creator_campaign_application_normalize_answer(
            $question,
            $map[$publicId] ?? null,
            $requireComplete
        );
        if ($answer === null && !$requireComplete) continue;
        $insert->execute([
            mg_creator_campaign_public_id('ccaa'),
            $applicationId,
            (int) $question['id'],
            mg_creator_campaign_json_encode($answer),
        ]);
    }
    $unknown = array_values(array_diff(array_keys($map), array_keys($known)));
    if ($unknown !== []) throw new InvalidArgumentException('Application contains answers for unknown questions.');
}

function mg_creator_campaign_application_count_capacity(PDO $pdo, array $campaign): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM creator_campaign_applications
         WHERE campaign_id=? AND status<>'withdrawn'"
    );
    $stmt->execute([(int) $campaign['id']]);
    $count = (int) $stmt->fetchColumn();
    $maximum = $campaign['maximum_applications'] === null ? null : (int) $campaign['maximum_applications'];
    return [
        'application_count' => $count,
        'maximum_applications' => $maximum,
        'available' => $maximum === null || $count < $maximum,
        'remaining' => $maximum === null ? null : max(0, $maximum - $count),
    ];
}

