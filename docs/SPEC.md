# mental-cat SPEC (MVP)

## 目的
会話(自由入力) → AIで構造化(JSON) → DB保存 → タスク自動生成/完了 → 継続しやすい体験を作る

## データモデル（最小）
users: Breeze標準

mood_logs:
- id, user_id, mood (good|neutral|bad), logged_at(datetime)

chat_messages:
- id, user_id, role(user|assistant), content(text), mood(nullable), memory_summary(nullable), created_at

tasks:
- id, user_id, title(string), status(todo|done), source(ai|manual), chat_message_id(nullable), done_at(nullable), created_at

※ すべて user_id で必ずスコープする（他ユーザー閲覧/更新禁止）

## AIの返答形式（JSON固定）
AIは必ず JSON のみ返す。キーは以下。

- reply: string（猫の返事）
- mood_guess: "good"|"neutral"|"bad"（任意）
- memory_summary: string（会話要点1行）
- tasks_to_add: [{ title: string, reason: string }] （最大3）
- tasks_to_complete: [{ match_hint: string }] （最大1、誤爆防止）
- bgm_key: "calm"|"focus"|"refresh"|"sleep"（任意）

システムプロンプト案：
「あなたは心身を整えるためのアドバイザー（猫キャラ）です。
 - 診断・治療はしない
 - セルフケア（水を飲む，深呼吸，散歩など）を提案
 - ユーザーの気分状態を観察してタスクを最大3つ提案
 - JSON形式で常に返す」

## タスク生成ルール
- tasks_to_add は最大3
- titleは短く（20-40文字程度）
- メンタル的に有益で、小さく実行できる行動を優先
- 予定タスク（例：課題/バイト）とセルフケアタスク（例：水を飲む/散歩）を混ぜてよい

## 自動完了ルール（最短実装）
- ユーザー発言に「やった/終わった/できた」などが含まれる時だけ tasks_to_complete を許可
- サーバ側は tasks_to_complete が出たら「最新todoを1件だけdone」にしてよい（まず動くこと優先）
- 手動完了はconfirm後にdoneにし、返事は定型文

## BGM（余裕があれば）
- bgm_key -> youtube_id の固定マップ
- bgm_key に応じて埋め込みを差し替える

## メモリ戦略（低コスト）
AIに毎回全履歴を送らない。以下のみを送る。

- Long-term summary: memory_summaries の最新1件
- Short-term context: chat_messages の最新10件（user/assistant）
- Task context: tasks の未完了上位5件（titleのみ）

## コンパクション（要約圧縮）
chat_messages が 50件を超えたら、古い30件を要約して memory_summaries に保存し、対象メッセージは削除する。
memory_summaries が 10件を超えたら、古い5件を再要約して1件に置換する。

## memory_summary の活用
AIレスポンスの memory_summary は chat_messages に保存する。
次回以降の会話では、最新の long-term summary + 最新10件 + 未完了タスク上位5件をプロンプト先頭に付与し、会話の連続性を保つ。
