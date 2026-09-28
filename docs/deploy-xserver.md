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
4. **コードを置く**：SSH（または SFTP）で `minato-event/` に一式を置く
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
   DB_HOST=（MySQL設定に表示されるホスト名）
   DB_PORT=3306
   DB_NAME=（作った DB 名）
   DB_USER=（作ったユーザー名）
   DB_PASSWORD=（そのパスワード）
   ```

7. **確認**：`https://event.minatocrew.com/` に準備中ページ、`/health` に `{"app":"ok","db":"ok"}` が出ればOK

## 本番で気をつけること

- `APP_DEBUG=false` にする（エラー内容を画面に出さない）
- `.env` は Git に入れない。パスワードは個人ごとの管理にする
- データのバックアップは Xserver の自動バックアップに加え、契約終了時の CSV 引き渡しを想定しておく
