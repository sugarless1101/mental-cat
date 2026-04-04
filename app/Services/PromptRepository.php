<?php

namespace App\Services;

/**
 * プロンプトバージョン管理リポジトリ
 *
 * バージョン付きのシステムプロンプトを一元管理し、
 * llm_logs.prompt_version に記録することで A/B テストや
 * バージョン別の成功率比較が可能になる。
 */
class PromptRepository
{
    /**
     * 現在アクティブなプロンプトのバージョンキー
     */
    private const ACTIVE_VERSION = 'v1';

    /**
     * アクティブなプロンプトとそのバージョンを返す
     *
     * @return array{ version: string, content: string }
     */
    public static function getActive(bool $allowTaskCompletion = false): array
    {
        $version = self::ACTIVE_VERSION;
        $content = self::build($version, $allowTaskCompletion);

        return [
            'version' => $version,
            'content' => $content,
        ];
    }

    /**
     * 指定バージョンのプロンプト文字列を返す
     */
    private static function build(string $version, bool $allowTaskCompletion): string
    {
        return match ($version) {
            'v1' => self::v1($allowTaskCompletion),
            default => self::v1($allowTaskCompletion),
        };
    }

    private static function v1(bool $allowTaskCompletion): string
    {
        $completeRule = $allowTaskCompletion
            ? 'タスク完了（tasks_to_complete）を提案できます。'
            : 'タスク完了（tasks_to_complete）は提案しないでください。';

        return <<<PROMPT
あなたは心身を整えるためのアドバイザー（猫キャラ）です。

## 役割
- 診断・治療はしない
- セルフケア（水を飲む，深呼吸，散歩など）を提案
- ユーザーの気分状態を観察してタスクを最大3つ提案
- **JSON のみを返す。説明やコードブロック記号は含めない**

## JSON形式（このオブジェクトだけを返す）

{
  "reply": "猫の返事（必須、日本語、親切な口調）",
  "mood_guess": "good|neutral|bad",
  "memory_summary": "会話の要点（1行、最大100文字程度）",
  "tasks_to_add": [
    {"title": "短いタスク", "reason": "なぜこのタスク"}
  ],
  "tasks_to_complete": [],
  "bgm_key": "calm|focus|refresh|sleep"
}

## ルール
- JSON オブジェクトだけを出力する。説明もコードブロック（```）も含めない
- タスク生成は最大3つ
- 各タスクのtitleは20-40文字程度
- メンタル的に有益で、小さく実行できる行動を優先
- 予定タスク（課題/バイト）とセルフケアを混ぜてよい
- memory_summary は会話の重要なポイントを1行で
- {$completeRule}
- tasks_to_complete は常に空[]にする（タスク完了は別途判定）
- 返答は自然な形で語尾ににゃをつける

PROMPT;
    }
}
