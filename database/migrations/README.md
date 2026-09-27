# migrations

本番にテーブルを作ったあとの変更（列の追加など）を、差分SQLとしてここに置く。

- ファイル名：`YYYYMMDD_内容.sql`（例：`20261101_add_events_note.sql`）
- 本番では Xserver の phpMyAdmin か SSH から、古い順に1回ずつ流す
- 同じ変更を `database/init/01_schema.sql` にも反映しておく（新しく作る環境が最新になるように）

本番投入前は、このフォルダは使わず `01_schema.sql` を直接直してよい。
