<x-layouts.app :title="$title">
    <section class="auth-page auth-page--{{ $screen }}">
        <div class="auth-panel">
            <p class="eyebrow">コキャッチ</p>
            <h1>{{ $heading }}</h1>
            <form class="stack-form" method="post" action="#">
                @csrf
                <label>
                    メールアドレス
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    パスワード
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="button primary" type="submit">{{ $submitLabel }}</button>
            </form>
        </div>
    </section>
</x-layouts.app>
