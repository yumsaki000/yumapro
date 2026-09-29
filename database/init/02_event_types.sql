-- イベント形式の初期データ（docs/requirements.md「2. 対象イベント形式」より）
-- 案内文テンプレートは案内文機能を作るときに埋める。

SET NAMES utf8mb4;

INSERT INTO event_types (code, name, payment_timing, form_fields, expense_items, message_templates, color, sort_order) VALUES
(
    'retreat', 'リトリート', 'prepaid',
    '[{"key": "room", "label": "部屋の希望", "type": "text", "required": false},
      {"key": "diet", "label": "食事制限・アレルギー", "type": "textarea", "required": false},
      {"key": "transport", "label": "交通手段", "type": "text", "required": false}]',
    '["宿泊費", "交通費", "食費", "備品"]',
    '{}',
    'blue',
    10
),
(
    'joshikai', '女子会', 'onsite',
    '[]',
    '["店への支払い"]',
    '{}',
    'pink',
    20
),
(
    'seminar', '自己啓発', 'prepaid',
    '[{"key": "question", "label": "事前に聞きたいこと", "type": "textarea", "required": false},
      {"key": "purpose", "label": "参加の目的", "type": "textarea", "required": false}]',
    '["会場費", "講師料", "資料代"]',
    '{}',
    'yellow',
    30
),
(
    'goukon', '合コン', 'onsite',
    '[]',
    '["店への支払い"]',
    '{}',
    'orange',
    40
);
