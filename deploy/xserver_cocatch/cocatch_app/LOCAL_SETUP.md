# ローカル起動手順

Docker Desktopを起動した状態で、`app` ディレクトリに移動して実行します。

```bash
cd app
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

ブラウザで以下を開きます。

```text
http://localhost:8080
```

停止する場合:

```bash
./vendor/bin/sail down
```

## よくあるエラー

### `Table 'opnavi_crm.opnavi_customers' doesn't exist`

原因は、MySQLには接続できているが、Laravelのマイグレーションがまだ実行されておらず、必要なテーブルが作成されていない状態です。

以下を実行してください。

```bash
cd /Users/torto/Documents/Codex/2026-07-12/new-chat/app
./vendor/bin/sail artisan migrate --seed
```

MySQL起動直後で失敗する場合は、10秒ほど待ってからもう一度実行してください。

開発初期でデータを消して作り直してよい場合のみ、以下でも復旧できます。

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

`migrate:fresh` は既存データを削除するため、運用データが入った後は使わないでください。

初期担当者:

- 砂澤
- 荒

CSVインポートテンプレート:

```text
public/templates/opnavi_import_template.csv
```
