#!/bin/bash
# PreToolUse Bash: git push 前にテスト全実行
# 失敗したらプッシュをブロック（exit 2）

input=$(cat)

if echo "$input" | grep -q 'git push'; then
    echo "[hook] pest --bail を実行中..."
    ./vendor/bin/pest --bail
    status=$?
    if [ $status -ne 0 ]; then
        echo "[hook] テストが失敗しています。修正してから push してください。"
        exit 2
    fi
fi
