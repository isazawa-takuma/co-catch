<x-layouts.app title="パスワード変更">
    <section class="auth-page">
        <div class="auth-panel">
            <p class="eyebrow">コキャッチ</p>
            <h1>パスワード変更</h1>
            <form class="stack-form" method="post" action="{{ route('password.update') }}">
                @csrf
                <label>
                    現在のパスワード
                    <input type="password" name="current_password" autocomplete="current-password" required>
                </label>
                <label>
                    新しいパスワード
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    新しいパスワード 確認
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <div class="form-actions">
                    <a class="button" href="{{ route(auth()->user()->role === 'admin' ? 'customers.index' : 'user.customers.index') }}">戻る</a>
                    <button class="button primary" type="submit">変更する</button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.app>
