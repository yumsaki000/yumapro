# MINATOイベント管理アプリ

MINATO のイベント（リトリート／女子会／自己啓発／合コン）の告知・申込・当日受付・会計を少人数で回すための Web アプリ。
要件・背景は `docs/requirements.md` が正。迷ったらまずそこを読む。

## 現状

- 要件整理前（林さんの回答待ち）。最初に作る形式は未定
- あるのは土台だけ：準備中ページ、`/health`、テーブル定義（案）
- 方針：申込の入口（こくちーず・Googleフォーム等）は今のまま使い、このアプリは申込と顧客をまとめる台帳にする。取り込みと名寄せの設計は `docs/data-intake.md`
- 形式が決まるまでは、形式に依存しない部分（管理画面ログイン、回の作成・複製、顧客台帳、当日受付、会計）から作る
- こくちーずCSVの取り込みは、サンプルCSVをもらうまで列の対応を決め打ちしない

## 技術構成

- PHP 8.3（フレームワークなし、Composer 依存なし。本番はファイルを置くだけで動く状態を保つ）
- MariaDB 10.5（Xserver 付属）。文字コードは utf8mb4、時刻は日本時間
- 本番：Xserver、`event.minatocrew.com`（仮）。配置は `docs/deploy-xserver.md`
- ローカル：Docker Compose（`compose.yaml`）。手順は `docs/setup-mac.md`

## コマンド

```sh
make setup     # 初回（.env 作成 → 起動）
make up        # 起動  http://localhost:8080 / phpMyAdmin http://localhost:8081
make down      # 停止
make db-reset  # DB を database/init/*.sql から作り直す（データは消える）
make lint      # PHP の文法チェック
make db        # DB に SQL で入る
```

## ディレクトリ

```
public/      ドキュメントルート（index.php・.htaccess・assets）。ここ以外は Web に出さない
src/         PHP コード。App\ 名前空間 → src/ に対応（bootstrap.php の自前オートローダー）
  routes.php URL と処理の対応
templates/   画面（layout.php で包む）。View::render('名前', [...])
database/
  init/        テーブル定義と初期データ。Docker の初回起動で番号順に流れる
  migrations/  本番投入後の差分 SQL
docs/        要件・手順書
storage/     アップロード写真など（Git に入れない）
```

## 決まりごと

- 画面の文言は日本語。参加者も受付担当もスマホで使う前提で作る
- HTML に出す値は必ず `e()` を通す
- SQL は必ずプリペアドステートメントで値を渡す（文字列連結しない）
- フォームには CSRF トークンを付ける（最初のフォームを作るときに共通の仕組みにする）
- 管理者パスワードは `password_hash()` / `password_verify()`。アカウントは個人ごと
- 個人情報は最小限。参加者の項目を増やす前に要件（`docs/requirements.md` の 6・8）を確認する
- 定員判定・当日受付は同時操作を前提に、トランザクション＋`SELECT ... FOR UPDATE` で守る
- 金額は円の整数（INT）で持つ
- テーブル変更：本番投入前は `database/init/01_schema.sql` を直接直す。投入後は `database/migrations/` に差分を追加し、`01_schema.sql` にも反映する
- 秘密情報は `.env` にだけ書く（Git に入れない）
