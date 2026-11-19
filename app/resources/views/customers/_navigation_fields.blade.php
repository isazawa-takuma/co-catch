@foreach ($detailQuery as $key => $value)
    <input type="hidden" name="list_context[{{ $key }}]" value="{{ $value }}">
@endforeach
<input type="hidden" name="nav_previous" value="{{ $previousCustomer?->id ?? 0 }}">
<input type="hidden" name="nav_next" value="{{ $nextCustomer?->id ?? 0 }}">
@if (request()->boolean('modal'))
    <input type="hidden" name="modal" value="1">
@endif
