<?php

use App\Services\MentalCatAiService;

// 有効な最小レスポンス
function validResponse(): array
{
    return [
        'reply' => 'テストにゃ。',
        'mood_guess' => 'neutral',
        'memory_summary' => 'テスト会話',
        'tasks_to_add' => [
            ['title' => 'お水を飲む', 'reason' => 'hydration'],
        ],
        'tasks_to_complete' => [],
        'bgm_key' => 'calm',
    ];
}

it('reply は必ず文字列で返る', function () {
    $result = MentalCatAiService::validateAndSanitize(validResponse());
    expect($result['reply'])->toBeString()->not->toBeEmpty();
});

it('reply が空の場合はフォールバックになる', function () {
    $input = array_merge(validResponse(), ['reply' => '']);
    $result = MentalCatAiService::validateAndSanitize($input);
    expect($result['reply'])->toBe(MentalCatAiService::getFallbackReply());
});

it('tasks_to_add は最大3件に切り捨てられる', function () {
    $input = array_merge(validResponse(), [
        'tasks_to_add' => [
            ['title' => 'タスク1', 'reason' => 'r'],
            ['title' => 'タスク2', 'reason' => 'r'],
            ['title' => 'タスク3', 'reason' => 'r'],
            ['title' => 'タスク4', 'reason' => 'r'],
        ],
    ]);
    $result = MentalCatAiService::validateAndSanitize($input);
    expect(count($result['tasks_to_add']))->toBe(3);
});

it('tasks_to_complete は常に空配列になる', function () {
    $input = array_merge(validResponse(), [
        'tasks_to_complete' => [1, 2, 3],
    ]);
    $result = MentalCatAiService::validateAndSanitize($input);
    expect($result['tasks_to_complete'])->toBe([]);
});

it('mood_guess は good/neutral/bad の3値のみ', function () {
    foreach (['good', 'neutral', 'bad'] as $valid) {
        $result = MentalCatAiService::validateAndSanitize(array_merge(validResponse(), ['mood_guess' => $valid]));
        expect($result['mood_guess'])->toBe($valid);
    }

    $result = MentalCatAiService::validateAndSanitize(array_merge(validResponse(), ['mood_guess' => 'happy']));
    expect($result['mood_guess'])->toBeNull();
});

it('bgm_key は calm/focus/refresh/sleep の4値のみ', function () {
    foreach (['calm', 'focus', 'refresh', 'sleep'] as $valid) {
        $result = MentalCatAiService::validateAndSanitize(array_merge(validResponse(), ['bgm_key' => $valid]));
        expect($result['bgm_key'])->toBe($valid);
    }

    $result = MentalCatAiService::validateAndSanitize(array_merge(validResponse(), ['bgm_key' => 'party']));
    expect($result['bgm_key'])->toBeNull();
});

it('医療用語を含む reply はフォールバックになる', function () {
    $medicalTerms = ['診断', '治療', '症状', 'うつ病', '障害', '投薬', '処方'];
    foreach ($medicalTerms as $term) {
        $input = array_merge(validResponse(), ['reply' => "これは{$term}に関することにゃ。"]);
        $result = MentalCatAiService::validateAndSanitize($input);
        expect($result['reply'])->toBe(MentalCatAiService::getFallbackReply());
    }
});

it('JSON としてパース可能な形式で返る', function () {
    $result = MentalCatAiService::validateAndSanitize(validResponse());
    $encoded = json_encode($result);
    expect($encoded)->toBeString();
    expect(json_decode($encoded, true))->toBeArray();
});
