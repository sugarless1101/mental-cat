## bash

- composer install
- php -r "file_exists('.env') || copy('.env.example', '.env');"
- php artisan key:generate
- touch database/database.sqlite
- php artisan migrate --force
- php artisan optimize:clear
- php artisan storage:link
- npm ci
- npm run dev   # or npm run build
- php artisan serve


## PS

# 1. PHP依存
composer install

# 2. .env作成（無ければコピー）
if (!(Test-Path .env)) { Copy-Item .env.example .env }

# 3. APP_KEY生成
php artisan key:generate

# 4. SQLite準備（sqlite運用の場合）
New-Item -ItemType File -Path database\database.sqlite -Force | Out-Null

# 5. マイグレーション（スクリプト実行時は --force）
php artisan migrate --force

# 6. キャッシュクリア（安全のため）
php artisan optimize:clear

# 7. 必要ならストレージリンク
php artisan storage:link

# 8. Node（開発）
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process
cmd /c "npm install"
cmd /c "npm run dev"

# 9. サーバ起動（開発）
php artisan serve