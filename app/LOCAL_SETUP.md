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

## メールサーバーなしでのユーザー追加

管理者の「ユーザー管理」→「追加」からメールアドレス（ログインID）・初期パスワード・権限を登録します。登録後の画面に表示される「ログイン情報をコピー」で、本人の個別チャットへ共有してください。メール送信やSMTP設定は不要です。

初期パスワードの有効期限は7日間です。本人が初回ログインすると氏名・新しいパスワードの設定画面へ進み、完了するまで通常画面を利用できません。共有情報は一度だけ表示され、確認できなくなった場合はユーザー行の三点メニューから「初期パスワード再発行」を実行します。再発行もメールを送信せず、同じ共有欄を表示します。

Mailpitは通常の起動には含まれません。別機能のメール送信を開発中に確認する場合のみ、明示して起動できます。

```bash
docker compose --profile mail up -d mailpit
```

すでに起動済みのMailpitを停止する場合は `docker compose --profile mail stop mailpit` を実行します。

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

## 取込済み顧客の掲載OTAを補正する場合

掲載数はCSV「国内掲載OTA」の媒体数です。「その他1」「その他2」等は「その他OTA名」の同じ順番の名前を使用します。
同じ媒体の複数URLは件数を増やしません。URLがない媒体は「URL未登録」、掲載媒体欄にない媒体のURLは「参考リンク」として表示します。リンク先の公開状況は自動検証しません。

本番ではDBをバックアップし、更新したアプリとマイグレーションを配備してください。CSVは**実際に取り込んだコピー**を公開ディレクトリ外に置きます。以下はLaravelのアプリディレクトリで実行します。

```bash
php artisan migrate --force
php artisan opnavi:repair-listings "/path/to/import.csv"
```

2つ目のコマンドは確認のみです。対象ごとの掲載数、リンクの変更前後、Web URLの補正を確認してください。問題がなければ次で適用します。

```bash
php artisan opnavi:repair-listings "/path/to/import.csv" --apply
```

営業日数などは通常の取り込みと同じ判定です。事業者名・住所で照合し、未登録の顧客は作成しません。CSVの重複、掲載媒体の不一致、CSVにない既存リンクなどは「要確認」としてスキップします。既存のWeb URLはCSVと同じ無効な値だけが補正対象です。担当者・営業メモ・架電履歴は更新しません。適用後は再び確認のみで実行し、「差分あり」が0件になったことと「要確認」の内訳を確認してください。通常のCSV再インポートで代用しないでください。
