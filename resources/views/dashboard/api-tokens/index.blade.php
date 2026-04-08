@extends('layouts.dashboard', ['active' => 'api-tokens'])
@section('title', __('api.tokens_title'))

@section('content')

<x-dashboard.page-header :title="__('api.tokens_title')" :subtitle="__('api.tokens_subtitle')" />

@if(session('plain_token'))
<div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
    <p class="text-[12px] font-bold text-amber-400 mb-2">{{ __('api.token_one_time_warning') }}</p>
    <code class="block text-[12px] font-mono text-primary break-all bg-page p-3 rounded-lg border border-th-border">{{ session('plain_token') }}</code>
</div>
@endif

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-3">
        <h3 class="text-[15px] font-bold text-primary mb-2">{{ __('api.your_tokens') }}</h3>
        @forelse($tokens as $t)
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[14px] font-bold text-primary mb-1">{{ $t->name }}</div>
                    <div class="text-[11px] text-muted mb-2">{{ __('api.created') }}: {{ $t->created_at->format('M j, Y') }} · {{ __('api.last_used') }}: {{ $t->last_used_at?->format('M j, Y') ?? '—' }}</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach((array) ($t->abilities ?? []) as $a)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-mono bg-accent/10 text-accent border border-accent/20">{{ $a }}</span>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('dashboard.api-tokens.destroy', $t->id) }}" onsubmit="return confirm('{{ __('api.confirm_revoke') }}');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold">{{ __('api.revoke') }}</button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
            </div>
            <p class="text-[14px] font-bold text-primary">{{ __('api.no_tokens') }}</p>
        </div>
        @endforelse

        <div class="mt-6 bg-surface border border-th-border rounded-2xl p-5">
            <h4 class="text-[13px] font-bold text-primary mb-2">{{ __('api.docs_title') }}</h4>
            <p class="text-[12px] text-muted leading-relaxed mb-3">{{ __('api.docs_description') }}</p>
            <a href="{{ url('/api/v1/public/openapi.json') }}" target="_blank" class="text-[12px] text-accent hover:underline font-semibold">/api/v1/public/openapi.json</a>
        </div>
    </div>

    <div>
        <div class="bg-surface border border-th-border rounded-2xl p-6 sticky top-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('api.create_token') }}</h3>
            <form method="POST" action="{{ route('dashboard.api-tokens.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('api.token_name') }}</label>
                    <input type="text" name="name" required maxlength="64" placeholder="Zapier integration"
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-2">{{ __('api.abilities') }}</label>
                    <div class="space-y-1.5 max-h-64 overflow-y-auto">
                        @foreach($abilities as $a)
                        <label class="flex items-center gap-2 text-[12px] text-primary">
                            <input type="checkbox" name="abilities[]" value="{{ $a }}" @checked(str_starts_with($a, 'read:'))>
                            <span class="font-mono">{{ $a }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                    {{ __('api.create_token') }}
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
