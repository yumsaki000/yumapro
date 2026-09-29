# MINATO BRIDGE（MINATOイベント管理アプリ）

MINATO のイベント（リトリート／女子会／自己啓発／合コン）の告知・申込・当日受付・会計を少人数で回すための Web アプリ。
名前は **MINATO BRIDGE**（船橋。港の船の舵を取る場所。2026-09-29 Yumaさんが決定）。画面の見出しに出す名前は `.env` の `APP_NAME` で変えられる。
要件・背景は `docs/requirements.md` が正。迷ったらまずそこを読む。

## やりとりのルール

- **返答は必ず日本語で書く**（英語で返さない。コードやコマンド、コミットメッセージ以外はすべて日本語）
- ユーザーに渡すターミナルのコマンドには、行の途中に `# コメント` を付けない（Macの zsh はコメントとして扱わず、エラーになる）。1行ずつ貼れる形で渡す
- GitHub のリポジトリは `yumsaki000/yumapro`。アカウント `yumsaki000` の表示名は「hobcraft」（ブラウザで「hobcraft」と出るのは同じアカウント）。確かめるときは `gh auth status`
- リポジトリには個人情報（顧客の名前・連絡先）、金額、振込先、元のスプレッドシートを入れない（2026-09-29 時点でリポジトリが公開になっており、非公開に切り替えるよう伝えている）

## 現状

- 要件整理前（林さんの回答待ち）
- できているもの：`/health`、テーブル定義（案）、管理画面のログイン（`/admin`）、CSRF対策の共通の仕組み、顧客の移行スクリプト（`docs/migration.md`）、管理画面の各機能（運営メンバー管理、回の作成・複製、申込の手入力、顧客台帳・名寄せ、出禁リスト（登録・判定・確認待ちの処理・通知）、当日受付、会計、集計、設定）、参加者向けの掲示板と申込フォーム（名寄せ・出禁チェック・定員でのキャンセル待ち・残席表示・締切）、個人専用ページ（確認・キャンセル・アンケート）、自動メール（確認・キャンセル待ち・繰り上げ・前日リマインド・翌日お礼）、キャンセル待ちの自動繰り上げ、クルー（名簿・クルー料金・募集ページ・承認）、参加者のログイン（メールのリンク）、講座・動画（YouTube埋め込み、全員／クルー限定／購入の出し分け、購入の入金確認）。使い方は `docs/admin-guide.md`
- まだのもの：問い合わせ、案内メールの一斉送信、QRコード受付、写真アルバム、オンライン決済（月額・講座）。申込フォームの項目・同意文・メール文面はヒアリングの回答で調整する（文言は設定画面で変えられる）
- 方針：こくちーずのような自前のイベント掲示板＋申込フォームで新規集客（特に女子会）をする。申込は掲示板のフォームに一本化し、集客の窓口（こくちーず・公式サイト・SNS）からはリンクする。スタッフの手入力と、今のスプレッドシートからの1回だけの移行も受ける（`docs/requirements.md` の 1-2・1-3・決定事項）。受け付け・名寄せ・出禁チェックの設計は `docs/data-intake.md`
- 最初に作る形式は女子会の見込み（ヒアリングで確認中）
- 形式が決まるまでは、形式に依存しない部分（管理画面ログイン、回の作成・複製、顧客台帳、当日受付、会計）から作る
- 今のスプレッドシートとGoogleフォームは刷新してよい。こくちーず・PeatixのCSV取り込みはしない（こくちーずは掲載だけ、Peatixは未使用）
- 先方の今の運用・公式サイトの調査は `docs/current-sheet-analysis.md`・`docs/official-site.md`。ヒアリングの質問一覧は `docs/hearing-sheet.md`

## デザイン

- 公式サイトに合わせる：フォント Noto Sans JP、紺 `#000c2e`・オレンジ `#dd8b0f`、アクセントに淡いピンク `#ffdfdf`／`#ff99b8`・水色 `#ccf4ff`・黄色 `#fff799`。ロゴは `public/assets/logo.png`（公式サイトのもの）
- 参加者向け（`body.public`）は「海辺の午後」案：白と生成りの明るい下地、丸いボタン、カードは角丸18px、見出し帯にロゴの「重なる3つの円」。管理画面は紺のヘッダーのまま（`app.css` の `body.public` の中だけが参加者向けの見た目）
- 形式ごとの色分けは `event_types.color`（pink / blue / yellow / orange / navy）。回の写真は `storage/photos/`（`Photos`）

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
make test      # テスト（tests/*Test.php。DBを使わないものだけ）
make db        # DB に SQL で入る
make admin     # 管理画面のアカウントを作る（パスワード再設定は make admin-reset）
make import-customers LIST=… RESPONSES=… [COMMIT=1]  # 今のスプレッドシートから顧客を移す（docs/migration.md）
make import-banned LIST=… [COMMIT=1]                   # 出禁リストを移す（顧客のあとに）
php bin/send-mails.php   # 前日リマインド・翌日お礼を送る（本番では cron で毎時。ローカルは MAIL_DRIVER=log で storage/mail/ に書く）
```

## ディレクトリ

```
public/      ドキュメントルート（index.php・.htaccess・assets）。ここ以外は Web に出さない
src/         PHP コード。App\ 名前空間 → src/ に対応（bootstrap.php の自前オートローダー）
  routes.php URL と処理の対応
  Auth.php / Csrf.php / Session.php  管理画面のログイン・CSRF・セッション
  CustomerAuth.php  参加者のログイン（メールで届くリンク。パスワードなし）
  Form.php       フォームの値の受け取り方（文字列・整数・日時・選択肢）
  Normalize.php  電話・メール・名前・フリガナ・SNSのそろえ方（名寄せ・出禁チェック・移行で共通）
  Events.php / Customers.php / Registrations.php / Checkins.php / Expenses.php / Admins.php / Channels.php / Bans.php / Crew.php / Courses.php
                 テーブルごとのDB処理。定員判定・受付などトランザクションが要る処理はここに置く
  Applications.php  申込フォームの受け付け（入力の確認・名寄せ・出禁チェック・申込の作成）
  Settings.php / Mailer.php / MailTemplates.php / MailJobs.php  文言の設定とメール（送信・文面・定期送信）
  Admin/         管理画面の各画面の処理（URLごとに routes.php から呼ぶ）
  Web/           参加者向けの画面の処理：掲示板・申込フォーム・個人専用ページ・ログイン・クルー募集・講座
  Migration/     今のスプレッドシートからの移行
bin/         コマンドラインで使うもの（アカウント作成、移行）。Web には出ない
tests/       テスト（依存なしの tests/run.php で流す）
templates/   画面。参加者向けは layout.php、管理画面は admin/layout.php で包む。View::render('名前', [...])
  board/ my/ auth/ learn/   参加者向け（掲示板・申込フォーム・クルー募集・個人専用ページ・ログイン・講座）
  admin/       管理画面
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
- POST はすべて `public/index.php` で CSRF トークンを確かめる。フォームには `<?= csrf_field() ?>` を必ず入れる
- 管理画面の処理は先頭で `Auth::requireAdmin()` を呼ぶ
- 電話・メール・名前・フリガナ・SNS は保存も比較も `Normalize` を通す
- 管理者パスワードは `password_hash()` / `password_verify()`。アカウントは個人ごと
- 個人情報は最小限。参加者の項目を増やす前に要件（`docs/requirements.md` の 6・8）を確認する
- 定員判定・当日受付は同時操作を前提に、トランザクション＋`SELECT ... FOR UPDATE` で守る
- 金額は円の整数（INT）で持つ
- テーブル変更：本番投入前は `database/init/01_schema.sql` を直接直す。投入後は `database/migrations/` に差分を追加し、`01_schema.sql` にも反映する
- 秘密情報は `.env` にだけ書く（Git に入れない）。振込先は管理画面の「設定」（DB）にだけ持つ
- 参加者向けでログインが要る画面は先頭で `CustomerAuth::requireLogin()`（または `current()`）を使う。講座が見られるかは `Courses::canView()` で判定する
- メールは `Mailer::send()` を通す（送信記録が `mail_log` に残り、同じメールを二度送らない判定に使う）。文面は `Settings` の既定値を管理画面で上書きする形にする
