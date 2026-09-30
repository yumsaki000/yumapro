#!/usr/bin/env bash
# サーバー（Xserver）の上で動く部分。bin/deploy.sh が仮置き場（<置き場所>.next）に送ってから呼ぶ。直接は使わない。
#   引数：1=置き場所（例 minatocrew.com/minato-event）2=php のパス 3=版の説明
set -euo pipefail

APP="${1%/}"
PHP="$2"
REVISION="$3"
STAGE="$APP.next"
PREV="$APP.prev"
DIRS=(bin database public src templates)

if ! "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    echo "✗ $PHP が PHP 8.3 以上ではありません（$("$PHP" -r 'echo PHP_VERSION;')）。.deploy.env の DEPLOY_PHP を直してください。"
    exit 1
fi

cd "$STAGE"

echo "  文法チェック（PHP $("$PHP" -r 'echo PHP_VERSION;')）"
failed=0
while IFS= read -r -d '' file; do
    if ! "$PHP" -l "$file" > /dev/null 2>&1; then
        "$PHP" -l "$file" || true
        failed=1
    fi
done < <(find "${DIRS[@]}" tests -name '*.php' -print0)
if [ "$failed" -ne 0 ]; then
    echo "✗ 文法エラーがあるため、反映をやめました（本番はそのまま）"
    exit 1
fi

echo "  テスト"
if ! output="$("$PHP" tests/run.php 2>&1)"; then
    echo "$output" | grep -v '^  ok' || true
    echo "✗ テストが通らないため、反映をやめました（本番はそのまま）"
    exit 1
fi
echo "$output" | tail -1 | sed 's/^/  /'

cd "$HOME"
mkdir -p "$APP" "$PREV"
# 今の本番のコードを控える（make rollback で戻せるように）
if [ -d "$APP/src" ]; then
    for dir in "${DIRS[@]}"; do
        if [ -d "$APP/$dir" ]; then
            rsync -a --delete "$APP/$dir/" "$PREV/$dir/"
        fi
    done
    [ -f "$APP/REVISION" ] && cp "$APP/REVISION" "$PREV/REVISION"
fi

echo "  入れ替え"
for dir in "${DIRS[@]}"; do
    rsync -a --delete "$STAGE/$dir/" "$APP/$dir/"
done
echo "$REVISION" > "$APP/REVISION"
mkdir -p "$APP/storage"
chmod 755 "$APP/public"

cd "$APP"
if [ ! -f .env ]; then
    echo "! $APP/.env がまだありません。docs/deploy-xserver.md の手順で作ってから、もう一度 make deploy してください（DB には触っていません）"
    exit 0
fi

pending="$("$PHP" bin/migrate.php --pending-count)"
if [ "$pending" = "init" ]; then
    echo "! DB が空です。最初だけ make server-init-db でテーブルを作ってください"
elif [ "$pending" != "0" ]; then
    echo "  DB の変更が ${pending}件あります。先にバックアップを取ります"
    "$PHP" bin/backup-db.php
    "$PHP" bin/migrate.php
else
    echo "  DB の変更はありません"
fi
echo "  版：$REVISION"
