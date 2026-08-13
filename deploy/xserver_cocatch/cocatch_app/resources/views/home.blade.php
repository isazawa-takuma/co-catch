<x-layouts.app title="サービス選択">
    <section class="service-selector">
        <div class="service-selector__inner">
            <h1>コキャッチ</h1>
            <div class="service-buttons">
                <a class="service-button" href="{{ route('customers.index') }}">オペナビ</a>
                <button class="service-button muted" type="button" data-coming-soon>送客</button>
            </div>
            <p class="notice" data-coming-soon-message hidden>送客機能は準備中です</p>
        </div>
    </section>
</x-layouts.app>
