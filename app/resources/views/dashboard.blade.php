<x-layouts.app title="営業ダッシュボード">
    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ</p>
            <h1>営業ダッシュボード</h1>
        </div>
    </div>

    <div class="metric-grid">
        <a class="metric" href="{{ route('customers.index') }}">
            <span>総顧客数</span>
            <strong>{{ number_format($metrics['total']) }}</strong>
        </a>
        <a class="metric" href="{{ route('customers.index', ['chip' => 'not_started']) }}">
            <span>未対応件数</span>
            <strong>{{ number_format($metrics['not_started']) }}</strong>
        </a>
        <a class="metric danger" href="{{ route('customers.index', ['chip' => 'today']) }}">
            <span>本日対応件数</span>
            <strong>{{ number_format($metrics['today_actions']) }}</strong>
        </a>
        <a class="metric danger" href="{{ route('customers.index', ['chip' => 'overdue']) }}">
            <span>期限切れ件数</span>
            <strong>{{ number_format($metrics['overdue']) }}</strong>
        </a>
        <a class="metric" href="{{ route('customers.index', ['chip' => 'unassigned']) }}">
            <span>未担当件数</span>
            <strong>{{ number_format($metrics['unassigned']) }}</strong>
        </a>
        <a class="metric" href="{{ route('customers.index', ['status' => '契約']) }}">
            <span>契約件数</span>
            <strong>{{ number_format($metrics['contracted']) }}</strong>
        </a>
        <a class="metric" href="{{ route('customers.index', ['status' => '失注']) }}">
            <span>失注件数</span>
            <strong>{{ number_format($metrics['lost']) }}</strong>
        </a>
    </div>
</x-layouts.app>
