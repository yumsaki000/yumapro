-- 「どこで知りましたか」の初期の選択肢
-- 今のGoogleフォームの6つ＋今のスプレッドシートの集客媒体（docs/data-intake.md）。管理画面で増やせる。

SET NAMES utf8mb4;

INSERT INTO channels (name, sort_order) VALUES
('MINATO公式LINE', 10),
('友人・知人の紹介', 20),
('こくちーず', 30),
('Instagram', 40),
('MINATO公式ホームページ', 50),
('他のMINATOイベント', 60),
('つなげーと', 70),
('X', 80),
('Threads', 90),
('GOAL-B', 100),
('フレバブ', 110),
('可能性大学', 120),
('アオイエシェアハウス', 130),
('ココナラ', 140),
('その他', 900);
