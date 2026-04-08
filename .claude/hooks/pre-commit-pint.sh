#!/bin/bash
# PreToolUse Bash: git commit 前に pint フォーマットチェック
# 失敗したらコミットをブロック（exit 2）

input=$(cat)

if echo "$input" | grep -q 'git commit'; then
    echo "[hook] pint --test を実行中..."
    ./vendor/bin/pint --test
    status=$?
    if [ $status -ne 0 ]; then
        echo "[hook] フォーマットエラーがあります。./vendor/bin/pint で修正してください。"
        exit 2
    fi
fi
