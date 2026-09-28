# よく使うコマンドのまとめ。`make` だけで一覧を表示する
.DEFAULT_GOAL := help
.PHONY: help setup up down restart logs db db-reset lint test admin admin-reset import-customers

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
