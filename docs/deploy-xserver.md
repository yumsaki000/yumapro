# Xserverへの配置（案）

> 「9. 進め方」の 1（空の公開環境）で使う予定のメモ。実際に作業したら、この手順を正しい内容に書き直す。

## 使うサーバー

- minatocrew.com と同じXserver（sv16086）。管理アカウントを借りていて、ドメインを追加できる
- `event.minatocrew.com` のDNSはすでにこのサーバーを向いているので、ドメイン管理者への依頼は不要の見込み。サーバーパネルでサブドメインを追加し、無料SSLを付ける

## 配置の考え方

Web から見えるのは `public/` だけにする。`.env`（パスワード）や `src/` は公開フォルダの外に置く。

```
/home/<サーバーID>/<ドメイン>/
├── minato-event/            ← このリポジトリ一式（Web から見えない）
│   ├── .env                 ← 本番用の設定
│   ├── public/
│   ├── src/
│   └── templates/
└── public_html/
    └── event/  →  minato-event/public へのシンボリックリンク
```

`event/` の位置はサブドメインを追加したときに Xserver が作るフォルダに合わせる。

## 手順（案）

1. **PHP のバージョン**：サーバーパネル「PHP Ver.切替」で対象ドメインを 8.3 にする
2. **サブドメイン**：`event.minatocrew.com` を追加し、無料SSLを有効にする
   - DNSはすでにこのサーバーを向いている（2026-09-28 確認）。つながらない場合だけドメイン管理者に確認する
3. **DB**：サーバーパネル「MySQL設定」で DB とユーザーを作る。phpMyAdmin で `database/init/` の SQL を番号順に流す
4. **コードを置く**：SSH（または SFTP）で `minato-event/` に一式を置く。`storage/` はPHPから書き込めるようにしておく（ログイン状態を `storage/sessions/` に保存する。作れない場合はサーバー既定の場所を使う）
5. **公開フォルダをつなぐ**（SSH）：

   ```sh
   cd ~/<ドメイン>
   mv public_html/event public_html/event.orig   # Xserver が作った空フォルダを退避
   ln -s ~/<ドメイン>/minato-event/public public_html/event
   ```

6. **設定**：`minato-event/.env` を作る

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://event.minatocrew.com
   MAIL_DRIVER=mail
   DB_HOST=（MySQL設定に表示されるホスト名）
   DB_PORT=3306
   DB_NAME=（作った DB 名）
   DB_USER=（作ったユーザー名）
   DB_PASSWORD=（そのパスワード）
   ```

7. **確認**：`https://event.minatocrew.com/` に準備中ページ、`/health` に `{"app":"ok","db":"ok"}` が出ればOK
8. **管理画面のアカウント**：SSHで `php bin/create-admin.php` を実行し、運営メンバー1人ずつに作る（SSHの `php` のバージョンがサーバーパネルの設定と違う場合があるので、`php -v` で 8.3 か確かめる）
9. **顧客の移行**：[migration.md](migration.md) の手順で、SSHから `php bin/import-customers.php` を実行する
10. **メールの差出人**：サーバーパネル「メールアカウント設定」で `noreply@event.minatocrew.com`（または minatocrew.com のアドレス）を作り、管理画面の「設定 → 差出人のメールアドレス」に入れる。自分宛てに1件申し込んで、確認メールが迷惑メールにならず届くか確かめる
11. **定期実行（前日リマインド・翌日お礼）**：サーバーパネル「Cron設定」で毎時0分に次を実行する（`php` の場所は SSH で `ls /usr/bin/php*` を見て 8.3 のものにする）

   ```
   /usr/bin/php8.3 /home/<サーバーID>/<ドメイン>/minato-event/bin/send-mails.php
   ```

## 本番で気をつけること

- `APP_DEBUG=false` にする（エラー内容を画面に出さない）
- `APP_URL` を `https://` で始める（ログインのCookieが https でしか送られなくなる）。メールに入る個人専用URLもこの値から作るので、正しいドメインにする
- `APP_ENV=production` にすると、イベント一覧と募集中の回のページが検索エンジンに載る（それ以外の画面は載せない）。`APP_URL` は `https://event.minatocrew.com` にする（共有のリンク・カレンダー・埋め込みに使う）
- 写真のアップロード：サーバーパネルの「php.ini 設定」で `upload_max_filesize` を 20M、`post_max_size` を 80M 以上にしておく（写真をまとめて選んだときに送れるように）。`storage/photos/` に書き込めるようにする
- 公式サイトへの埋め込みとリンクの差し替えは [official-site-integration.md](official-site-integration.md)
- `MAIL_DRIVER=mail` にする（`log` のままだとメールが送られず `storage/mail/` にファイルとして残る）
- ログイン失敗の制限はIPごとにもかけている。公開後、`REMOTE_ADDR` が利用者のIPになっているか確かめる（全員が同じIPに見えると、1人の失敗で全員が止まる）
- `.env` は Git に入れない。パスワードは個人ごとの管理にする
- データのバックアップは Xserver の自動バックアップに加え、契約終了時の CSV 引き渡しを想定しておく
