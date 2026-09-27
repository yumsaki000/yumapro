# Macで開発を始める手順

所要時間：初回 30分ほど（ダウンロード待ちを含む）。2回目以降は `make up` だけ。

---

## 0. 入れておくもの（初回だけ）

ターミナル（アプリケーション → ユーティリティ → ターミナル）で順に実行する。すでに入っているものは飛ばしてよい。

### 0-1. Xcode Command Line Tools（git と make）

```sh
xcode-select --install
```

「すでにインストールされています」と出たらOK。

### 0-2. Homebrew

```sh
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

最後に表示される「Next steps」の2行（`echo ... >> ~/.zprofile` など）も実行する。

### 0-3. Docker Desktop

<https://www.docker.com/products/docker-desktop/> からダウンロードしてインストール（Apple シリコンの Mac なら「Apple Silicon」版）。
起動して、メニューバーのクジラのアイコンが「Docker Desktop is running」になればOK。

> 個人・小規模事業での利用は無料。

### 0-4. GitHub CLI（リポジトリの取得用）

```sh
brew install gh
gh auth login
```

質問には「GitHub.com」→「HTTPS」→「Login with a web browser」と答え、ブラウザでログインする。

### 0-5. エディタ（任意）

```sh
brew install --cask visual-studio-code
```

VS Code の拡張機能「PHP Intelephense」を入れると、PHP の補完やエラー表示が効く。

### 0-6. Claude Code（任意）

```sh
curl -fsSL https://claude.ai/install.sh | bash
```

---

## 1. コードを取得する

```sh
mkdir -p ~/dev && cd ~/dev
gh repo clone yumsaki000/yumapro
cd yumapro
git switch claude/minato-event-app-biqcvo
```

> 開発ブランチが master に取り込まれたら、`git switch` は不要になる。

---

## 2. 起動する

```sh
make setup
```

初回はイメージのダウンロードで数分かかる。終わったらブラウザで確認する。

| URL | 見えるもの |
|---|---|
| <http://localhost:8080> | 準備中ページ |
| <http://localhost:8080/health> | `{"app":"ok","db":"ok"}` なら DB までつながっている |
| <http://localhost:8081> | phpMyAdmin（テーブルの中身を見る・直す） |

DBクライアント（TablePlus、Sequel Ace など）からつなぐ場合：
ホスト `127.0.0.1`／ポート `3307`／ユーザー `minato`／パスワード `minato`／DB `minato_event`

---

## 3. ふだんの流れ

```sh
cd ~/dev/yumapro
make up        # 起動
# … 開発 …
make down      # 終了（DBの中身は残る）
```

- PHP・HTML・CSS は保存してブラウザを再読み込みすれば反映される（ビルド不要）
- テーブルを変えたいとき：`database/init/01_schema.sql` を直して `make db-reset`（DBの中身は消える）
- `make` だけ打つとコマンド一覧が出る

Claude Code を使う場合は、このフォルダで `claude` を起動する。`CLAUDE.md` の内容を読んだ状態で始まる。

---

## 困ったとき

| 症状 | 対処 |
|---|---|
| `Cannot connect to the Docker daemon` | Docker Desktop が起動していない。アプリを開く |
| `port is already allocated` | 8080／8081／3307 番を別のアプリが使っている。`compose.yaml` の左側の番号（例 `"8080:80"` の 8080）を空いている番号に変える |
| `/health` が `db` のエラーを返す | `make logs` で `db` のログを見る。直らなければ `make db-reset` |
| SQL ファイルを直したのに反映されない | `database/init/` は DB を最初に作るときだけ流れる。`make db-reset` |
| `.env` がないと言われる | `cp .env.example .env` |

---

## Homebrew だけで動かす場合（Docker を使わない）

Docker を入れたくない場合の代わりの方法。

```sh
brew install php mariadb
brew services start mariadb

# DB とユーザーを作ってテーブルを流す
mariadb -u root -e "CREATE DATABASE minato_event CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'minato'@'localhost' IDENTIFIED BY 'minato'; GRANT ALL ON minato_event.* TO 'minato'@'localhost';"
cat database/init/*.sql | mariadb -u root minato_event

# .env の DB_HOST を 127.0.0.1 に変えてから
cp .env.example .env
php -S localhost:8080 -t public
```

Homebrew の PHP は本番（Xserver）とバージョンがずれることがあるので、基本は Docker の方を使う。
