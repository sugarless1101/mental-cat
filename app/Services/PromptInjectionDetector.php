<?php

namespace App\Services;

/**
 * プロンプトインジェクション検知サービス
 *
 * LLMアプリ固有の攻撃パターンをスコアリングで検知する。
 * 閾値（DETECTION_THRESHOLD）以上のスコアで検知とみなし、
 * 高信頼スコア（BLOCK_THRESHOLD）以上の場合はAI呼び出し自体をブロックする。
 *
 * 防御の考え方:
 * - 単一キーワードブロックではなく重み付きスコアリングで偽陽性を抑える
 * - 検知してもすべてを即ブロックしない（UX優先）
 * - 検知結果は llm_logs.injection_detected に記録して可観測性を確保
 */
class PromptInjectionDetector
{
    /**
     * 検知パターン: [正規表現, 重み, ラベル]
     *
     * 重み3: 明確なインジェクション（単独でも検知）
     * 重み2: 疑わしい（組み合わせで検知）
     * 重み1: 軽微なシグナル
     */
    private const PATTERNS = [
        // ロール上書き・指示無視（高確度）
        ['/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|prompt)/ui', 3, 'role_override'],
        ['/前の(指示|プロンプト)を(無視|忘れ)/u', 3, 'role_override'],
        ['/disregard\s+(all\s+)?(previous|prior)\s+(instructions?|rules)/ui', 3, 'role_override'],

        // ペルソナ強制（中確度）
        ['/you\s+are\s+now\s+(a\s+|an\s+)?(?!\w*cat)\w+/ui', 2, 'persona_injection'],
        ['/pretend\s+(you\s+are|to\s+be)/ui', 2, 'persona_injection'],
        ['/あなたは(今から|これから).{0,20}(として行動|になってください|のように)/u', 2, 'persona_injection'],

        // システムプロンプト抽出
        ['/repeat\s+(your\s+)?(system\s+)?(prompt|instructions?)\s*(verbatim|exactly|back)?/ui', 3, 'extraction'],
        ['/what\s+(are|is)\s+your\s+(system\s+)?(instructions?|prompt|rules|constraints)/ui', 2, 'extraction'],
        ['/システムプロンプト(を|の)(教えて|見せて|繰り返して|出力して)/u', 3, 'extraction'],
        ['/あなたの(指示|プロンプト|設定)(を|の)(教えて|見せて|出力して)/u', 2, 'extraction'],

        // 特殊トークン注入（ChatML / Llama / OpenAI 形式）
        ['/<\|im_(start|end|sep)\|>/ui', 3, 'special_token'],
        ['/\[INST\]|\[\/INST\]/', 2, 'special_token'],
        ['/<\/?(?:system|assistant)>/ui', 2, 'delimiter_injection'],

        // ジェイルブレイク定番フレーズ
        ['/\bDAN\b/', 2, 'jailbreak'],
        ['/do\s+anything\s+now/ui', 3, 'jailbreak'],
        ['/developer\s+mode/ui', 2, 'jailbreak'],
        ['/開発者モード/u', 2, 'jailbreak'],
        ['/jailbreak/ui', 2, 'jailbreak'],

        // JSONコンテキスト脱出の試み
        ['/\}\s*,\s*\{\s*["\']role["\']/', 2, 'context_escape'],
        ['/"role"\s*:\s*"system"/', 2, 'context_escape'],
    ];

    /** このスコア以上で injection_detected = true（ログ記録・プロンプト強化） */
    private const DETECTION_THRESHOLD = 3;

    /** このスコア以上でAI呼び出しをブロック（高信頼の攻撃） */
    private const BLOCK_THRESHOLD = 6;

    /**
     * ユーザー入力を検査する
     *
     * @return array{detected: bool, blocked: bool, score: int, patterns: string[]}
     */
    public static function inspect(string $message): array
    {
        $score = 0;
        $matched = [];

        foreach (self::PATTERNS as [$pattern, $weight, $label]) {
            if (preg_match($pattern, $message)) {
                $score += $weight;
                $matched[] = $label;
            }
        }

        return [
            'detected' => $score >= self::DETECTION_THRESHOLD,
            'blocked' => $score >= self::BLOCK_THRESHOLD,
            'score' => $score,
            'patterns' => array_unique($matched),
        ];
    }
}
