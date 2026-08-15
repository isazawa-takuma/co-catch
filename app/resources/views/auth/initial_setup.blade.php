<x-layouts.app title="初回設定">
    <section class="auth-page">
        <div class="auth-panel">
            <p class="eyebrow">コキャッチ</p>
            <h1>初回設定</h1>
            <form class="stack-form" method="post" action="{{ route('initial-setup.update') }}">
                @csrf
                <label>
                    姓
                    <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" autocomplete="family-name" required>
                </label>
                <label>
                    名
                    <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" autocomplete="given-name" required>
                </label>
                <label>
                    新しいパスワード
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    新しいパスワード 確認
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button class="button primary" type="submit">設定を完了</button>
            </form>
        </div>
    </section>
</x-layouts.app>
