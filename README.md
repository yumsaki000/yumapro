# MINATOイベント管理アプリ

MINATO のイベントの告知・申込・当日受付・会計を少人数で回すための Web アプリ。

- 要件・背景：[docs/requirements.md](docs/requirements.md)
- 申込の取り込みと顧客台帳（案）：[docs/data-intake.md](docs/data-intake.md)
- 今のイベント管理シートの分析：[docs/current-sheet-analysis.md](docs/current-sheet-analysis.md)
- 公式サイトの調査：[docs/official-site.md](docs/official-site.md)
- ヒアリングシート（統合版）の質問一覧：[docs/hearing-sheet.md](docs/hearing-sheet.md)
- Mac での開発環境づくり：[docs/setup-mac.md](docs/setup-mac.md)
- Xserver への配置（案）：[docs/deploy-xserver.md](docs/deploy-xserver.md)

## すぐ動かす

Docker Desktop が起動している状態で：

```sh
make setup
```

- アプリ：<http://localhost:8080>
- 動作確認：<http://localhost:8080/health>
- phpMyAdmin：<http://localhost:8081>

## 構成

PHP 8.3（フレームワークなし）＋ MariaDB 10.5。本番は Xserver。詳しくは [CLAUDE.md](CLAUDE.md)。
