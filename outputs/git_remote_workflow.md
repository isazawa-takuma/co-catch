# GitHubリモート運用手順

## 1. 目的

本ドキュメントは、コキャッチのローカルリポジトリをGitHubリポジトリへpushし、他メンバーと共通の運用認識を持つための手順をまとめたものです。

- 対象リポジトリ: `isazawa-takuma/co-catch`
- GitHub URL: `https://github.com/isazawa-takuma/co-catch`
- ローカル作業ディレクトリ: `/Users/torto/Documents/オペナビ_顧客管理`
- Laravelアプリ本体: `app/`
- 仕様書: `outputs/`

## 2. Gitのメール設定とGitHub認証の違い

Gitには、混同しやすい2種類のアカウント情報があります。

| 種別 | 役割 | 今回の注意点 |
|---|---|---|
| Gitコミット情報 | コミットに記録される `user.name` / `user.email` | `git config user.email` で確認・設定する |
| GitHub認証情報 | GitHubへpush/pullするためのログイン情報 | HTTPSのトークン、またはSSHキーで認証する |

`git config user.email` が会社メールでも、GitHub認証が個人アカウントのままだと、会社アカウントに権限があるリポジトリへpushできません。

逆に、GitHub認証が会社アカウントでも、`git config user.email` が個人メールのままだと、コミットの作成者メールが個人メールで記録されます。

## 3. 今回の前提

リポジトリ `isazawa-takuma/co-catch` へのアクセス権限があるGitHubアカウントは、以下のメールアドレスで登録されています。

```text
igarashi.m@illuvia-inc.com
```

このため、少なくともこのリポジトリでは、Gitのコミット用メールも会社メールに揃える方針を推奨します。

## 4. 現在のローカル状態を確認する

```bash
cd /Users/torto/Documents/オペナビ_顧客管理
git status
git remote -v
git config user.name
git config user.email
```

確認したいこと:

- `git status` が clean であること
- `origin` が `https://github.com/isazawa-takuma/co-catch.git` またはSSH URLを向いていること
- `user.email` が会社メールになっていること

## 5. このリポジトリだけ会社メールにする

グローバル設定を変えると他の個人開発リポジトリにも影響します。

そのため、このプロジェクトではローカルリポジトリ単位で設定します。

```bash
cd /Users/torto/Documents/オペナビ_顧客管理
git config user.name "igarashi-mitsunori-sub"
git config user.email "igarashi.m@illuvia-inc.com"
```

確認:

```bash
git config --local user.name
git config --local user.email
```

## 6. GitHub認証方法

GitHubでは、通常のパスワードによるGit操作は使えません。

pushするには、以下のどちらかを使います。

| 方法 | 概要 | 推奨 |
|---|---|---|
| HTTPS + Personal Access Token | GitHubのトークンをパスワード代わりに使う | 一時的には簡単 |
| SSHキー | PCのSSH公開鍵をGitHubに登録する | 継続運用では推奨 |

## 7. 推奨: SSHでpushする

### 7.1 SSHキーがあるか確認

```bash
ls -la ~/.ssh
```

`id_ed25519.pub` などの公開鍵があるか確認します。

### 7.2 SSH接続確認

```bash
ssh -T git@github.com
```

会社アカウントで認証できていれば、GitHubから認証成功のメッセージが返ります。

### 7.3 SSHキーを作る場合

既存のSSHキーがない場合は、以下で作成します。

```bash
ssh-keygen -t ed25519 -C "igarashi.m@illuvia-inc.com"
```

作成された公開鍵を表示します。

```bash
cat ~/.ssh/id_ed25519.pub
```

表示された内容をGitHubの会社アカウントに登録します。

GitHub画面:

```text
Settings > SSH and GPG keys > New SSH key
```

### 7.4 remoteをSSH形式に変更

```bash
cd /Users/torto/Documents/オペナビ_顧客管理
git remote set-url origin git@github.com:isazawa-takuma/co-catch.git
git remote -v
```

### 7.5 push

```bash
git push -u origin main
```

## 8. HTTPS + Tokenでpushする場合

HTTPSを使う場合、GitHubのPersonal Access Tokenを作成し、push時のパスワードとして使います。

GitHub画面:

```text
Settings > Developer settings > Personal access tokens
```

必要な権限:

- private repositoryの場合: `repo`
- fine-grained tokenの場合: 対象リポジトリ `isazawa-takuma/co-catch` への Contents read/write

push:

```bash
git push -u origin main
```

ユーザー名を聞かれたらGitHubユーザー名、パスワードを聞かれたらPersonal Access Tokenを入力します。

## 9. 日常運用

### 9.1 作業前

```bash
cd /Users/torto/Documents/オペナビ_顧客管理
git pull --ff-only
```

### 9.2 変更確認

```bash
git status
git diff
```

### 9.3 テスト

```bash
cd /Users/torto/Documents/オペナビ_顧客管理/app
php artisan test
```

### 9.4 コミット

```bash
cd /Users/torto/Documents/オペナビ_顧客管理
git add .
git commit -m "変更内容を短く書く"
```

### 9.5 push

```bash
git push
```

## 10. コミット対象にしないもの

以下はコミットしません。

- `.env`
- `.DS_Store`
- `vendor/`
- `node_modules/`
- `.phpunit.result.cache`
- ローカルログ
- 個人の認証情報、APIキー、パスワード

## 11. 今回の初回コミット状況

現時点でローカルには初回コミットがあります。

```text
abe9e8d Initial co-catch CRM implementation
```

ただし、GitHubへのpushは認証エラーで未完了です。

エラー:

```text
Invalid username or token. Password authentication is not supported for Git operations.
```

対応方針:

1. このリポジトリだけ `user.email` を `igarashi.m@illuvia-inc.com` に設定する。
2. 会社用GitHubアカウントでSSHまたはToken認証を設定する。
3. `git push -u origin main` を実行する。

