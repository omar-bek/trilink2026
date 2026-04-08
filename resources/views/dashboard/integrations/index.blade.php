@extends('layouts.dashboard', ['active' => 'integrations'])
@section('title', __('integrations.title'))

@section('content')

<x-dashboard.page-header :title="__('integrations.title')" :subtitle="__('integrations.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">{{ session('status') }}</div>
@endif
@if(session('plain_secret'))
<div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-400 px-4 py-3 text-[12px]">
    <strong>{{ __('integrations.copy_secret_now') }}</strong>
    <code class="block mt-2 font-mono text-[11px] break-all">{{ session('plain_secret') }}</code>
</div>
@endif
@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Webhook endpoints. --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-bold text-primary">{{ __('integrations.webhooks') }}</h3>
            <p class="text-[10px] text-muted">{{ count($endpoints) }} {{ __('integrations.endpoints') }}</p>
        </div>

        <form method="POST" action="{{ route('dashboard.integrations.webhooks.store') }}" class="mb-5 space-y-2">
            @csrf
            <input type="text" name="label" required maxlength="100" placeholder="{{ __('integrations.webhook_label_placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
            <input type="url" name="url" required placeholder="https://example.com/webhooks/trilink"
                   class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary font-mono"/>
            <input type="text" name="events" placeholder="{{ __('integrations.webhook_events_placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary font-mono"/>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 w-full h-10 rounded-lg bg-accent text-white text-[12px] font-bold hover:bg-accent-h transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('integrations.add_webhook') }}
            </button>
        </form>

        @forelse($endpoints as $ep)
        <div class="bg-page border border-th-border rounded-xl p-4 mb-2">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="min-w-0">
                    <p class="text-[13px] font-bold text-primary truncate">{{ $ep->label }}</p>
                    <p class="text-[10px] font-mono text-muted truncate">{{ $ep->url }}</p>
                </div>
                <span @class([
                    'text-[10px] font-bold rounded-full px-2 py-0.5 border',
                    'text-[#00d9b5] bg-[#00d9b5]/10 border-[#00d9b5]/20' => $ep->is_active && $ep->failure_count === 0,
                    'text-[#ffb020] bg-[#ffb020]/10 border-[#ffb020]/20' => $ep->is_active && $ep->failure_count > 0,
                    'text-muted bg-surface-2 border-th-border'           => !$ep->is_active,
                ])>
                    @if(!$ep->is_active){{ __('common.inactive') }}
                    @elseif($ep->failure_count > 0){{ __('integrations.failing') }}
                    @else{{ __('common.active') }}@endif
                </span>
            </div>
            <div class="text-[10px] text-muted mb-3">
                {{ $ep->success_count ?? 0 }} {{ __('integrations.success') }} ·
                {{ $ep->failure_count ?? 0 }} {{ __('integrations.failures') }} ·
                @if($ep->last_delivered_at)
                    {{ __('integrations.last_delivered') }}: {{ $ep->last_delivered_at->diffForHumans() }}
                @else
                    {{ __('integrations.never_delivered') }}
                @endif
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('dashboard.integrations.webhooks.test', $ep->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1 rounded text-[10px] font-bold bg-accent/10 border border-accent/30 text-accent hover:bg-accent/20">
                        {{ __('integrations.send_test') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('dashboard.integrations.webhooks.destroy', $ep->id) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1 rounded text-[10px] font-bold bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] hover:bg-[#ff4d7f]/20"
                            onclick="return confirm('{{ __('integrations.confirm_delete_webhook') }}');">
                        {{ __('common.delete') }}
                    </button>
                </form>
            </div>
        </div>
        @empty
        <p class="text-[12px] text-muted italic">{{ __('integrations.no_webhooks_yet') }}</p>
        @endforelse
    </div>

    {{-- ERP connectors. --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-bold text-primary">{{ __('integrations.erp_connectors') }}</h3>
            <p class="text-[10px] text-muted">{{ count($connectors) }} {{ __('integrations.connectors') }}</p>
        </div>

        <form method="POST" action="{{ route('dashboard.integrations.connectors.store') }}" class="mb-5 space-y-2">
            @csrf
            <select name="type" required class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary">
                <option value="odoo">Odoo</option>
                <option value="netsuite">NetSuite</option>
                <option value="sap">SAP</option>
                <option value="quickbooks">QuickBooks</option>
                <option value="custom">Custom</option>
            </select>
            <input type="text" name="label" required maxlength="100" placeholder="{{ __('integrations.connector_label_placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
            <input type="url" name="base_url" required placeholder="https://your-instance.odoo.com"
                   class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary font-mono"/>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 w-full h-10 rounded-lg bg-accent text-white text-[12px] font-bold hover:bg-accent-h transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('integrations.add_connector') }}
            </button>
        </form>

        @forelse($connectors as $c)
        <div class="bg-page border border-th-border rounded-xl p-4 mb-2">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <p class="text-[13px] font-bold text-primary">{{ $c->label }}</p>
                    <p class="text-[10px] font-mono text-muted">{{ $c->type }} · {{ $c->base_url }}</p>
                </div>
                <span class="text-[10px] font-bold rounded-full px-2 py-0.5 border text-[#00d9b5] bg-[#00d9b5]/10 border-[#00d9b5]/20">
                    {{ $c->is_active ? __('common.active') : __('common.inactive') }}
                </span>
            </div>
            <p class="text-[10px] text-muted mb-3">
                @if($c->last_sync_at)
                    {{ __('integrations.last_sync') }}: {{ $c->last_sync_at->diffForHumans() }}
                @else
                    {{ __('integrations.never_synced') }}
                @endif
            </p>
            <form method="POST" action="{{ route('dashboard.integrations.connectors.destroy', $c->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1 rounded text-[10px] font-bold bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] hover:bg-[#ff4d7f]/20"
                        onclick="return confirm('{{ __('integrations.confirm_delete_connector') }}');">
                    {{ __('common.delete') }}
                </button>
            </form>
        </div>
        @empty
        <p class="text-[12px] text-muted italic">{{ __('integrations.no_connectors_yet') }}</p>
        @endforelse
    </div>
</div>

@endsection
