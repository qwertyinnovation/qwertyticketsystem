@if (session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
        {{ session('status') }}
    </div>
@endif
