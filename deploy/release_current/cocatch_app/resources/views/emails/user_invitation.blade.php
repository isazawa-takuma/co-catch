<p>{{ $user->email }} 様</p>

<p>
    コキャッチのアカウントを作成しました。<br>
    以下の情報でログインしてください。
</p>

<p>
    ログインURL: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a><br>
    メールアドレス: {{ $user->email }}<br>
    初期パスワード: {{ $initialPassword }}
</p>
