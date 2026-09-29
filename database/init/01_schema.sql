-- MINATOイベント管理アプリ テーブル定義（案）
-- docs/requirements.md「5. データ構造（案）」をSQLにしたもの。
-- 要件確定までは、このファイルを直接直してよい（ローカルは `make db-reset` で作り直し）。
-- 本番に入れたあとの変更は database/migrations/ に差分SQLを追加する。

SET NAMES utf8mb4;

-- 管理画面のアカウント（個人ごとに発行。共有しない）
CREATE TABLE admins (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id        VARCHAR(64)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL COMMENT 'password_hash() の結果',
    display_name    VARCHAR(100) NOT NULL,
    role            ENUM('owner', 'staff') NOT NULL DEFAULT 'staff' COMMENT 'owner: アカウント管理もできる',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理画面のログイン試行（総当たり対策。一定回数失敗したら一時的に止める）
CREATE TABLE admin_login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id        VARCHAR(64)  NOT NULL,
    ip_address      VARCHAR(45)  NOT NULL,
    succeeded       TINYINT(1)   NOT NULL,
    attempted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_login_attempts_login (login_id, attempted_at),
    KEY idx_admin_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- イベント形式（リトリート／女子会／自己啓発／合コン）
CREATE TABLE event_types (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(32)  NOT NULL COMMENT 'プログラムから参照する名前',
    name                VARCHAR(100) NOT NULL,
    payment_timing      ENUM('prepaid', 'onsite') NOT NULL COMMENT '既定の支払い: 前払い／当日払い',
    form_fields         JSON         NOT NULL COMMENT '追加の申込項目の定義',
    expense_items       JSON         NOT NULL COMMENT '経費項目の定義',
    message_templates   JSON         NOT NULL COMMENT '案内文テンプレート（募集／リマインド／締切／お礼）',
    color               VARCHAR(20)  NOT NULL DEFAULT 'navy' COMMENT '掲示板の色分け: pink / blue / yellow / orange / navy',
    sort_order          INT          NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 画面やメールの文言など、管理画面で変えられる設定（既定値は src/Settings.php。ここには変えた値だけ入る）
CREATE TABLE settings (
    `key`       VARCHAR(50)  NOT NULL,
    value       TEXT         NOT NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 「どこで知りましたか」の選択肢（管理画面で増減できる。過去の記録は名前で持つので、消さずに無効にする）
CREATE TABLE channels (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)  NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 にすると選択肢に出さない',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_channels_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 開催回
CREATE TABLE events (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type_id       INT UNSIGNED NOT NULL,
    slug                VARCHAR(32)  NOT NULL COMMENT '公開ページURL用のランダム文字列（連番IDを見せない）',
    title               VARCHAR(200) NOT NULL,
    round_no            INT UNSIGNED NULL COMMENT '第n回',
    starts_at           DATETIME     NOT NULL,
    ends_at             DATETIME     NULL,
    venue_name          VARCHAR(200) NULL,
    venue_address       VARCHAR(255) NULL,
    venue_url           VARCHAR(500) NULL,
    capacity            INT UNSIGNED NULL COMMENT '定員（合計）。NULL は上限なし',
    capacity_male       INT UNSIGNED NULL COMMENT '男女別定員（合コン用）',
    capacity_female     INT UNSIGNED NULL,
    fee                 INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加費（円）',
    fee_male            INT UNSIGNED NULL COMMENT '男女別料金（合コン用）',
    fee_female          INT UNSIGNED NULL,
    fee_crew            INT UNSIGNED NULL COMMENT 'クルー料金（円）。NULL ならクルー割引なし',
    payment_timing      ENUM('prepaid', 'onsite') NOT NULL,
    apply_deadline      DATETIME     NULL COMMENT '申込締切',
    cancel_deadline     DATETIME     NULL COMMENT 'キャンセル期限（自動計算して保存）',
    cancel_policy       TEXT         NULL COMMENT 'キャンセル規定',
    organizer_amount    INT          NOT NULL DEFAULT 0 COMMENT '主催分（円）。収支 = 集金 - 経費 - 主催分',
    description         TEXT         NULL COMMENT '公開ページの説明文',
    photo               VARCHAR(64)  NULL COMMENT '写真のファイル名（storage/photos に保存。/photos/{名前} で表示）',
    extra               JSON         NULL COMMENT '形式ごとの追加設定',
    status              ENUM('draft', 'open', 'closed', 'done', 'cancelled') NOT NULL DEFAULT 'draft'
                        COMMENT '下書き／募集中／締切／終了／中止',
    copied_from_id      INT UNSIGNED NULL COMMENT '複製元の回',
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_events_slug (slug),
    KEY idx_events_type_starts (event_type_id, starts_at),
    CONSTRAINT fk_events_type FOREIGN KEY (event_type_id) REFERENCES event_types (id),
    CONSTRAINT fk_events_copied_from FOREIGN KEY (copied_from_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_events_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 顧客（1人1件。掲示板の申込・手入力・今のスプレッドシートからの移行を名寄せしてまとめる）
-- 持つ情報は最小限。項目を増やす前に docs/requirements.md の 6・8 を確認する
CREATE TABLE customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    name_kana       VARCHAR(100) NULL COMMENT 'フリガナ（名寄せと五十音順の並べ替え用）',
    email           VARCHAR(255) NULL COMMENT '保存時に小文字・前後空白なしにそろえる（名寄せ用）',
    phone           VARCHAR(30)  NULL COMMENT '保存時に数字だけにそろえる（名寄せ用）',
    sns_account     VARCHAR(100) NULL COMMENT 'タグ付け用SNSアカウント。@なし・小文字で保存（出禁チェックの照合にも使う）',
    gender          ENUM('male', 'female') NULL COMMENT '男女比の把握、男女別定員・料金の回で使う',
    line_name       VARCHAR(100) NULL COMMENT 'オープンチャットでの表示名',
    first_channel   VARCHAR(50)  NULL COMMENT '最初に来たきっかけ（集客媒体）',
    mail_opt_in_at  DATETIME     NULL COMMENT '案内メールの受け取りに同意した日時（特定電子メール法のため記録）',
    mail_opt_out_at DATETIME     NULL COMMENT '案内メールの配信を停止した日時',
    note            TEXT         NULL COMMENT '運営メモ',
    banned_at       DATETIME     NULL COMMENT '出禁にした日時（NULL なら出禁でない）',
    ban_reason      VARCHAR(255) NULL COMMENT '出禁の理由（運営向け・短く）',
    ban_note        TEXT         NULL COMMENT '出禁の経緯など（運営向け。証拠の画像は貼らず、要点や保管場所だけ）',
    banned_by       INT UNSIGNED NULL COMMENT '出禁にした運営メンバー',
    crew_status     ENUM('none', 'applied', 'active', 'left') NOT NULL DEFAULT 'none' COMMENT 'クルー: 未加入／申込中／加入中／脱退',
    crew_joined_at  DATE         NULL COMMENT 'クルー加入日',
    crew_left_at    DATE         NULL COMMENT 'クルー脱退日',
    crew_note       VARCHAR(255) NULL COMMENT 'クルーについての運営メモ（連絡の希望など）',
    access_token    VARCHAR(64)  NOT NULL COMMENT '個人専用URL用のランダム文字列',
    legacy_no       INT UNSIGNED NULL COMMENT '移行元（今のスプレッドシートの声掛けリスト）の番号',
    legacy_data     JSON         NULL COMMENT '移行元の行をそのまま（使い道が決まっていない列も失わないため）',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_access_token (access_token),
    UNIQUE KEY uq_customers_legacy_no (legacy_no),
    KEY idx_customers_email (email),
    KEY idx_customers_phone (phone),
    KEY idx_customers_sns (sns_account),
    KEY idx_customers_name_kana (name_kana),
    KEY idx_customers_banned (banned_at),
    KEY idx_customers_crew (crew_status),
    CONSTRAINT fk_customers_banned_by FOREIGN KEY (banned_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- クルーの申込（募集ページのフォームから。運営が承認すると customers.crew_status が加入中になる）
CREATE TABLE crew_applications (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     INT UNSIGNED NOT NULL,
    status          ENUM('applied', 'approved', 'declined') NOT NULL DEFAULT 'applied' COMMENT '申込中／承認／お断り',
    answers         JSON         NULL COMMENT '地域・連絡の希望・コメントなど',
    consented_at    DATETIME     NULL COMMENT '規約などに同意した日時',
    decided_by      INT UNSIGNED NULL,
    decided_at      DATETIME     NULL,
    note            VARCHAR(255) NULL COMMENT '運営メモ',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crew_applications_status (status, created_at),
    CONSTRAINT fk_crew_applications_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_crew_applications_admin FOREIGN KEY (decided_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 参加者のログイン用リンク（メールで届く。30分で失効、1回だけ使える）
CREATE TABLE login_tokens (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     INT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL COMMENT 'リンクの文字列の SHA-256（文字列そのものは保存しない）',
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_login_tokens_hash (token_hash),
    KEY idx_login_tokens_customer (customer_id, created_at),
    CONSTRAINT fk_login_tokens_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 講座（動画研修・記事などのコンテンツのまとまり）
CREATE TABLE courses (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(32)  NOT NULL COMMENT '公開ページURL用のランダム文字列',
    title           VARCHAR(200) NOT NULL,
    description     TEXT         NULL COMMENT '講座の説明（一覧と講座ページに出す）',
    access          ENUM('public', 'crew', 'paid') NOT NULL DEFAULT 'crew' COMMENT '誰が見られるか: 全員／クルー／購入した人',
    price           INT UNSIGNED NULL COMMENT '購入の料金（円）。access=paid のとき',
    crew_included   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'access=paid の講座をクルーは購入なしで見られるか',
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'draft' COMMENT '下書き／公開',
    sort_order      INT          NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_slug (slug),
    CONSTRAINT fk_courses_admin FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 講座の各回（本文と YouTube の動画）
CREATE TABLE lessons (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id       INT UNSIGNED NOT NULL,
    title           VARCHAR(200) NOT NULL,
    body            TEXT         NULL COMMENT '本文（改行そのまま。URLはリンクになる）',
    youtube_id      VARCHAR(20)  NULL COMMENT 'YouTube の動画ID（限定公開の動画）',
    is_preview      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 なら誰でも見られる（お試し）',
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lessons_course (course_id, sort_order, id),
    CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 講座の購入（前払い。運営が入金を確認すると見られるようになる）
CREATE TABLE course_purchases (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id       INT UNSIGNED NOT NULL,
    customer_id     INT UNSIGNED NOT NULL,
    amount          INT UNSIGNED NOT NULL COMMENT '料金（申込時点の金額・円）',
    status          ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT '入金待ち／入金確認済み／取り消し',
    paid_at         DATETIME     NULL,
    payment_method  VARCHAR(20)  NULL,
    confirmed_by    INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_course_purchases (course_id, customer_id),
    KEY idx_course_purchases_status (status, created_at),
    CONSTRAINT fk_course_purchases_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_course_purchases_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_course_purchases_admin FOREIGN KEY (confirmed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 取り込み履歴（今のスプレッドシートからの移行など、まとめて取り込んだ1回につき1行）
CREATE TABLE import_batches (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source          VARCHAR(20)  NOT NULL COMMENT 'legacy_customers（声掛けリスト）/ legacy_responses（フォームの回答）など',
    event_id        INT UNSIGNED NULL COMMENT '取り込み先の回（顧客の移行では NULL）',
    filename        VARCHAR(255) NULL,
    total_rows      INT UNSIGNED NOT NULL DEFAULT 0,
    created_rows    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新しく登録した件数',
    updated_rows    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '既存の申込を更新した件数',
    skipped_rows    INT UNSIGNED NOT NULL DEFAULT 0,
    imported_by     INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_import_batches_event (event_id),
    CONSTRAINT fk_import_batches_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_import_batches_admin FOREIGN KEY (imported_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 申込（回 × 顧客）
CREATE TABLE registrations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id            INT UNSIGNED NOT NULL,
    customer_id         INT UNSIGNED NOT NULL,
    source              VARCHAR(20)  NOT NULL DEFAULT 'manual'
                        COMMENT '申込元: own_form（掲示板の申込フォーム）/ manual（手入力）/ legacy（移行）',
    external_id         VARCHAR(100) NULL COMMENT '申込元での番号（移行元の行など。同じものを再度取り込んでも重複させない）',
    status              ENUM('applied', 'waitlisted', 'cancelled') NOT NULL DEFAULT 'applied'
                        COMMENT '申込／キャンセル待ち／キャンセル',
    fee                 INT UNSIGNED NULL COMMENT 'この人の参加費（申込時点の金額）',
    answers             JSON         NULL COMMENT '追加項目の回答（event_types.form_fields に対応）',
    raw_data            JSON         NULL COMMENT '移行元の行そのまま（列の対応を後から直せるように）',
    payment_method      VARCHAR(20)  NULL COMMENT 'cash / bank_transfer / paypay / other',
    prepaid_at          DATETIME     NULL COMMENT '前払いの入金を確認した日時',
    channel             VARCHAR(50)  NULL COMMENT 'どこで知ったか（申込時の選択。channels.name）',
    entry_from          VARCHAR(30)  NULL COMMENT 'どの窓口のリンクから来たか（?from= の値。集客の集計用）',
    ban_check           ENUM('none', 'suspect', 'confirmed', 'cleared') NOT NULL DEFAULT 'none'
                        COMMENT '申込時の出禁チェック: 該当なし／名前だけ一致（要確認）／連絡先が一致（確定）／運営が確認して別人と判断',
    consented_at        DATETIME     NULL COMMENT '申込フォームで注意事項などに同意した日時',
    note                VARCHAR(255) NULL COMMENT '運営メモ',
    applied_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申込元での申込日時',
    cancelled_at        DATETIME     NULL,
    import_batch_id     INT UNSIGNED NULL COMMENT '移行で取り込んだ場合の取り込み履歴',
    created_by          INT UNSIGNED NULL COMMENT '手入力した担当者',
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registrations_event_customer (event_id, customer_id),
    UNIQUE KEY uq_registrations_external (event_id, source, external_id),
    KEY idx_registrations_event_status (event_id, status, applied_at),
    KEY idx_registrations_customer (customer_id, applied_at),
    CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES events (id),
    CONSTRAINT fk_registrations_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_registrations_import_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches (id) ON DELETE SET NULL,
    CONSTRAINT fk_registrations_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 当日受付（1申込につき1行。到着と入金を同時に記録）
CREATE TABLE checkins (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id     INT UNSIGNED NOT NULL,
    arrived_at          DATETIME     NULL,
    paid_amount         INT UNSIGNED NULL COMMENT '当日の入金額（円）',
    payment_method      VARCHAR(20)  NULL,
    checked_in_by       INT UNSIGNED NULL COMMENT '受付した担当者',
    note                VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_checkins_registration (registration_id),
    CONSTRAINT fk_checkins_registration FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE,
    CONSTRAINT fk_checkins_admin FOREIGN KEY (checked_in_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 事後アンケート（1申込につき1回）
CREATE TABLE surveys (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id     INT UNSIGNED NOT NULL,
    satisfaction        TINYINT UNSIGNED NOT NULL COMMENT '満足度 1〜5',
    return_intent       ENUM('yes', 'maybe', 'no') NOT NULL COMMENT 'また参加したい／わからない／しない',
    comment             TEXT         NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_surveys_registration (registration_id),
    CONSTRAINT fk_surveys_registration FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- メールの送信記録（同じメールを二度送らないため、と、送れなかったときの確認用）
CREATE TABLE mail_log (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind                VARCHAR(30)  NOT NULL COMMENT 'confirm / waitlist / promoted / cancelled / reminder / thanks',
    registration_id     INT UNSIGNED NULL,
    customer_id         INT UNSIGNED NULL,
    to_email            VARCHAR(255) NOT NULL,
    subject             VARCHAR(255) NOT NULL,
    status              ENUM('sent', 'failed') NOT NULL,
    error               VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mail_log_registration (registration_id, kind),
    CONSTRAINT fk_mail_log_registration FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE SET NULL,
    CONSTRAINT fk_mail_log_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 経費（1回に複数項目）
CREATE TABLE expenses (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id        INT UNSIGNED NOT NULL,
    item            VARCHAR(100) NOT NULL,
    amount          INT          NOT NULL COMMENT '円',
    memo            VARCHAR(255) NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expenses_event (event_id),
    CONSTRAINT fk_expenses_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_expenses_admin FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 問い合わせ
CREATE TABLE inquiries (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id        INT UNSIGNED NULL,
    customer_id     INT UNSIGNED NULL COMMENT '個人専用URLから来た場合',
    name            VARCHAR(100) NULL COMMENT '未申込の人から来た場合',
    contact         VARCHAR(255) NULL,
    body            TEXT         NOT NULL,
    reply           TEXT         NULL,
    status          ENUM('open', 'answered', 'closed') NOT NULL DEFAULT 'open' COMMENT '未対応／返信済み／完了',
    replied_by      INT UNSIGNED NULL,
    replied_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiries_status (status, created_at),
    CONSTRAINT fk_inquiries_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_inquiries_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_inquiries_admin FOREIGN KEY (replied_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
