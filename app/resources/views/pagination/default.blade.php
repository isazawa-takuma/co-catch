@if ($paginator->hasPages())
    <nav class="pagination-wrap" role="navigation" aria-label="Pagination Navigation">
        <p class="pagination-summary">
            {{ $paginator->firstItem() }}〜{{ $paginator->lastItem() }}件 / 全{{ $paginator->total() }}件
        </p>

        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li><span aria-disabled="true">前へ</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">前へ</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span>{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="active" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">次へ</a></li>
            @else
                <li><span aria-disabled="true">次へ</span></li>
            @endif
        </ul>
    </nav>
@endif
