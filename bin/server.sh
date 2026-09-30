#!/usr/bin/env bash
# 本番（Xserver）で使うコマンドを Mac から実行する。make server-… から呼ぶ（make だけで一覧が出る）。
#   ssh                 サーバーに入る（アプリの場所で）
#   admin / admin-reset 管理画面のアカウントを作る／パスワードを再設定する
#   init-db             空の DB に最初のテーブルを作る（初回だけ）
#   db-status           DB の変更をどこまで流したか
#   backup              DB のバックアップを取る（サーバーの storage/backups に置く）
#   send-mails          前日リマインド・翌日お礼の定期送信を今すぐ1回動かす
#   import-customers LIST [RESPONSES] [COMMIT]  顧客の移行（CSV を送って取り込み、送った CSV は消す）
#   import-banned LIST [COMMIT]                 出禁リストの移行（同上）
#   rollback            コードを1つ前の版に戻す（DB は戻らない）
#   revision            今の本番の版を見る
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .deploy.env ]; then
    echo "✗ .deploy.env がありません。次の1行で作ってから、中の値を書き換えてください："
    echo "  cp .deploy.env.example .deploy.env"
    exit 1
fi
# shellcheck disable=SC1091
source .deploy.env
: "${DEPLOY_HOST:?.deploy.env に DEPLOY_HOST を書いてください}"
: "${DEPLOY_PATH:?.deploy.env に DEPLOY_PATH を書いてください}"
APP="${DEPLOY_PATH%/}"
PHP="${DEPLOY_PHP:-/usr/bin/php8.3}"
SSH="${DEPLOY_SSH:-ssh}"

# サーバーのアプリの場所でコマンドを動かす。-t は入力のやりとりが要るとき（パスワードを聞くなど）
remote() { "$SSH" "$DEPLOY_HOST" "cd '$APP' && $1"; }
remote_tty() { "$SSH" -t "$DEPLOY_HOST" "cd '$APP' && $1"; }

# CSV をサーバーの storage/import に送り、終わったら（失敗しても）消す
upload() {
    local src="$1" name="$2"
    if [ ! -f "$src" ]; then
        echo "✗ ファイルがありません：$src"
        exit 1
    fi
    remote "umask 077 && mkdir -p storage/import && cat > 'storage/import/$name'" < "$src"
}
cleanup_import() { remote "rm -f storage/import/*.csv" || true; }

command="${1:-}"
shift || true
case "$command" in
    ssh)
        "$SSH" -t "$DEPLOY_HOST" "cd '$APP' && exec \$SHELL -l"
        ;;
    admin)
        remote_tty "$PHP bin/create-admin.php"
        ;;
    admin-reset)
        remote_tty "$PHP bin/create-admin.php --reset"
        ;;
    init-db)
        remote "$PHP bin/migrate.php --init"
        ;;
    db-status)
        remote "$PHP bin/migrate.php --status"
        ;;
    backup)
        remote "$PHP bin/backup-db.php && ls -1t storage/backups | head -5"
        ;;
    send-mails)
        remote "$PHP bin/send-mails.php"
        ;;
    import-customers)
        list="${1:-}"; responses="${2:-}"; commit="${3:-}"
        [ -n "$list" ] || { echo "使い方：make server-import-customers LIST=声掛けリスト.csv RESPONSES=フォームの回答.csv [COMMIT=1]"; exit 1; }
        trap cleanup_import EXIT
        upload "$list" list.csv
        args="storage/import/list.csv"
        if [ -n "$responses" ]; then
            upload "$responses" responses.csv
            args="$args --responses=storage/import/responses.csv"
        fi
        [ -n "$commit" ] && args="$args --commit"
        remote "$PHP bin/import-customers.php $args"
        ;;
    import-banned)
        list="${1:-}"; commit="${2:-}"
        [ -n "$list" ] || { echo "使い方：make server-import-banned LIST=出禁リスト.csv [COMMIT=1]"; exit 1; }
        trap cleanup_import EXIT
        upload "$list" banned.csv
        args="storage/import/banned.csv"
        [ -n "$commit" ] && args="$args --commit"
        remote "$PHP bin/import-banned.php $args"
        ;;
    rollback)
        "$SSH" "$DEPLOY_HOST" "set -e
            if [ ! -d '$APP.prev/src' ]; then echo '✗ 戻せる版がありません（まだ1回しか反映していない）'; exit 1; fi
            echo \"今の版：\$(cat '$APP/REVISION' 2>/dev/null || echo 不明)\"
            echo \"戻す版：\$(cat '$APP.prev/REVISION' 2>/dev/null || echo 不明)\"
            for dir in bin database public src templates; do rsync -a --delete '$APP.prev/'\$dir/ '$APP/'\$dir/; done
            cp '$APP.prev/REVISION' '$APP/REVISION' 2>/dev/null || true
            echo '✓ コードを1つ前の版に戻しました（DB はそのまま）'"
        ;;
    revision)
        remote "cat REVISION 2>/dev/null || echo 'まだ反映していません'"
        ;;
    *)
        sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
