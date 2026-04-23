@if (session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900">
        <p class="font-bold">Please review the highlighted fields and try again.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach (collect($errors->all())->unique()->take(5) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
