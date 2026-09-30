#!/usr/bin/env bash
# 本番（Xserver）へ反映する。Mac で実行する：make deploy（確認だけなら make deploy-dry）
#
# 流れ：
#   1. 送るファイル（bin database public src templates tests）を、サーバーの仮置き場（<置き場所>.next）へ送る
#   2. サーバーの上で、文法チェックとテストを流す。失敗したら本番はそのままで止める
#   3. 今の本番のコードを <置き場所>.prev に控えてから、新しいコードに入れ替える（make rollback で戻せる）
#   4. DB の変更（database/migrations）があれば、バックアップを取ってから流す
#   5. /health で動いているか確かめる
#
# .env（パスワード）と storage/（写真・バックアップ）はサーバーのものをそのまま使い、送らない・消さない。
# 接続先は .deploy.env に書く（.deploy.env.example を写して作る。Git には入れない）。手順は docs/deploy-xserver.md。
set -euo pipefail

cd "$(dirname "$0")/.."

DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

if [ ! -f .deploy.env ]; then
    echo "✗ .deploy.env がありません。次の1行で作ってから、中の値を書き換えてください："
    echo "  cp .deploy.env.example .deploy.env"
    exit 1
fi
# shellcheck disable=SC1091
source .deploy.env
: "${DEPLOY_HOST:?.deploy.env に DEPLOY_HOST を書いてください}"
: "${DEPLOY_PATH:?.deploy.env に DEPLOY_PATH を書いてください}"
DEPLOY_PHP="${DEPLOY_PHP:-/usr/bin/php8.3}"
DEPLOY_URL="${DEPLOY_URL:-}"
SSH="${DEPLOY_SSH:-ssh}"

DIRS=(bin database public src templates tests)
STAGE="${DEPLOY_PATH%/}.next"

# 反映する版（コミット）を記録する。未コミットの変更があれば確かめる
REVISION="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"
BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
if [ -n "$(git status --porcelain -- "${DIRS[@]}" 2>/dev/null)" ]; then
    echo "! まだコミットしていない変更があります（下の一覧）。このまま送ると、コミット前の内容が本番に出ます。"
    git status --short -- "${DIRS[@]}"
    if [ "$DRY_RUN" -eq 0 ]; then
        read -r -p "このまま反映しますか？ [y/N] " answer
        [ "$answer" = "y" ] || [ "$answer" = "Y" ] || { echo "やめました。"; exit 1; }
    fi
    REVISION="${REVISION}+未コミットの変更あり"
fi

echo "▶ 反映するもの：${BRANCH} ${REVISION}"
echo "▶ 送り先：${DEPLOY_HOST}:${DEPLOY_PATH}"

if ! "$SSH" -o BatchMode=yes -o ConnectTimeout=15 "$DEPLOY_HOST" true; then
    echo "✗ サーバーにつながりません。~/.ssh/config と鍵の置き場所を確かめてください（docs/deploy-xserver.md の「SSHの準備」）。"
    exit 1
fi

RSYNC_OPTS=(-rlptz --delete --exclude .DS_Store --exclude '*.swp')
if [ "$DRY_RUN" -eq 1 ]; then
    echo "▶ 送るファイルの確認だけ（本番は変えません）"
    "$SSH" "$DEPLOY_HOST" "mkdir -p '$STAGE'"
    rsync "${RSYNC_OPTS[@]}" -n -v -e "$SSH" "${DIRS[@]}" "$DEPLOY_HOST:$STAGE/"
    exit 0
fi

echo "▶ 1. ファイルを送る"
"$SSH" "$DEPLOY_HOST" "mkdir -p '$STAGE'"
rsync "${RSYNC_OPTS[@]}" -e "$SSH" "${DIRS[@]}" "$DEPLOY_HOST:$STAGE/"

echo "▶ 2〜4. サーバーで確かめてから入れ替える"
"$SSH" "$DEPLOY_HOST" "bash '$STAGE/bin/deploy-remote.sh' '$DEPLOY_PATH' '$DEPLOY_PHP' '$REVISION $(date '+%Y-%m-%d %H:%M')'"

if [ -n "$DEPLOY_URL" ]; then
    echo "▶ 5. 動いているか確かめる"
    if curl -fsS --max-time 20 "${DEPLOY_URL%/}/health"; then
        echo ""
        echo "✓ 反映しました：${DEPLOY_URL}"
    else
        echo ""
        echo "✗ /health が正しく返りません。make rollback で1つ前に戻せます（DB は戻りません）。"
        exit 1
    fi
else
    echo "✓ 反映しました（.deploy.env に DEPLOY_URL を書くと、最後に動作確認もします）"
fi
