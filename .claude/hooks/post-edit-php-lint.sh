#!/bin/bash
# PostToolUse Edit: PHP ファイル編集後に構文チェック
# エラーがあればユーザーに通知

input=$(cat)

# file_path を JSON から抽出
file=$(echo "$input" | sed -n 's/.*"file_path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)

if [[ "$file" == *.php ]]; then
    echo "[hook] php -l: $file"
    php -l "$file"
    if [ $? -ne 0 ]; then
        echo "[hook] 構文エラーがあります。確認してください。"
        exit 2
    fi
fi
