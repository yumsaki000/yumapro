# よく使うコマンドのまとめ。`make` だけで一覧を表示する
.DEFAULT_GOAL := help
.PHONY: help setup up down restart logs db db-reset lint test admin admin-reset import-customers import-banned \
	deploy deploy-dry rollback server-ssh server-admin server-admin-reset server-init-db server-db-status server-backup \
	server-send-mails server-import-customers server-import-banned server-revision

help: ## コマンド一覧
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-18s %s\n", $$1, $$2}'

setup: ## 初回準備（.env作成 → 起動）
	@test -f .env || (cp .env.example .env && echo ".env を作成しました")
	docker compose up -d --build
	@echo ""
	@echo "アプリ       http://localhost:8080"
	@echo "phpMyAdmin   http://localhost:8081"

up: ## 起動
	docker compose up -d

down: ## 停止（DBの中身は残る）
	docker compose down

restart: ## 再起動
	docker compose restart

logs: ## ログを表示（Ctrl+C で抜ける）
	docker compose logs -f

db: ## DBにSQLで入る
	docker compose exec db sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"'

db-reset: ## DBを空にして database/init/*.sql から作り直す（データは消える）
	docker compose down -v
	docker compose up -d

lint: ## PHPの文法チェック
	docker compose exec app sh -c 'find public src templates bin tests -name "*.php" -print0 | xargs -0 -n1 php -l'

test: ## テストを流す
	docker compose exec app php tests/run.php

admin: ## 管理画面のアカウントを作る
	docker compose exec app php bin/create-admin.php

admin-reset: ## 管理画面のパスワードを再設定する
	docker compose exec app php bin/create-admin.php --reset

import-customers: ## 顧客の移行。LIST=声掛けリストのCSV RESPONSES=フォームの回答のCSV（書き込むときは COMMIT=1）
	docker compose exec app php bin/import-customers.php "$(LIST)" $(if $(RESPONSES),"--responses=$(RESPONSES)") $(if $(COMMIT),--commit) $(if $(VERBOSE),--verbose)

import-banned: ## 出禁リストの移行。LIST=出禁リストのCSV（書き込むときは COMMIT=1）
	docker compose exec app php bin/import-banned.php "$(LIST)" $(if $(COMMIT),--commit) $(if $(VERBOSE),--verbose)

# ── 本番（Xserver）：Mac から SSH で。.deploy.env と ~/.ssh/config が要る（docs/deploy-xserver.md） ──

deploy: ## 本番へ反映（チェック → 入れ替え → DBの変更。前の版は控える）
	bash bin/deploy.sh

deploy-dry: ## 本番へ送るファイルの確認だけ（本番は変えない）
	bash bin/deploy.sh --dry-run

rollback: ## 本番のコードを1つ前の版に戻す（DBは戻らない）
	bash bin/server.sh rollback

server-ssh: ## 本番のサーバーに入る
	bash bin/server.sh ssh

server-admin: ## 本番の管理画面のアカウントを作る
	bash bin/server.sh admin

server-admin-reset: ## 本番の管理画面のパスワードを再設定する
	bash bin/server.sh admin-reset

server-init-db: ## 本番の空のDBに最初のテーブルを作る（初回だけ）
	bash bin/server.sh init-db

server-db-status: ## 本番のDBの変更をどこまで流したか
	bash bin/server.sh db-status

server-backup: ## 本番のDBのバックアップを取る（サーバーの storage/backups）
	bash bin/server.sh backup

server-send-mails: ## 本番で前日リマインド・翌日お礼を今すぐ1回送る
	bash bin/server.sh send-mails

server-import-customers: ## 本番へ顧客を移す。LIST=… RESPONSES=…（書き込むときは COMMIT=1）
	bash bin/server.sh import-customers "$(LIST)" "$(RESPONSES)" "$(COMMIT)"

server-import-banned: ## 本番へ出禁リストを移す。LIST=…（書き込むときは COMMIT=1）
	bash bin/server.sh import-banned "$(LIST)" "$(COMMIT)"

server-revision: ## 本番の今の版を見る
	bash bin/server.sh revision
