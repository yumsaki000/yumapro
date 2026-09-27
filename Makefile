# よく使うコマンドのまとめ。`make` だけで一覧を表示する
.DEFAULT_GOAL := help
.PHONY: help setup up down restart logs db db-reset lint

help: ## コマンド一覧
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-10s %s\n", $$1, $$2}'

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
	docker compose exec app sh -c 'find public src templates -name "*.php" -print0 | xargs -0 -n1 php -l'
