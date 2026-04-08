@extends('layouts.dashboard', ['active' => 'beneficial-owners'])
@section('title', __('beneficial_owners.title'))

@section('content')

<x-dashboard.page-header
    :title="__('beneficial_owners.title')"
    :subtitle="__('beneficial_owners.subtitle')" />

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

{{-- Disclosure progress strip --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6 mb-6">
    <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-[15px] font-bold text-primary leading-tight">{{ __('beneficial_owners.disclosure_status') }}</h3>
                <p class="text-[12px] text-muted mt-0.5">{{ __('beneficial_owners.disclosure_explainer') }}</p>
            </div>
        </div>
        @if($isComplete)
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('beneficial_owners.complete') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5"/></svg>
                {{ __('beneficial_owners.incomplete') }}
            </span>
        @endif
    </div>
    <div class="flex items-center justify-between text-[12px] mb-2">
        <span class="text-muted">{{ __('beneficial_owners.total_disclosed') }}</span>
        <span class="text-primary font-semibold">{{ number_format($totalOwnership, 2) }}%</span>
    </div>
    <div class="w-full h-2 bg-surface-2 rounded-full overflow-hidden">
        <div class="h-full {{ $isComplete ? 'bg-emerald-400' : 'bg-amber-400' }} rounded-full" style="width: {{ min(100, $totalOwnership) }}%"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Existing owners list --}}
    <div class="lg:col-span-2 space-y-3">
        <h3 class="text-[14px] font-bold text-primary">{{ __('beneficial_owners.declared') }} ({{ $owners->count() }})</h3>
        @forelse($owners as $owner)
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                <div>
                    <p class="text-[14px] font-bold text-primary">{{ $owner->full_name }}</p>
                    <div class="flex items-center gap-2 flex-wrap mt-1">
                        @if($owner->role)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-accent/10 text-accent border border-accent/20">{{ __('beneficial_owners.role_' . $owner->role) }}</span>
                        @endif
                        @if($owner->is_pep)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ __('beneficial_owners.pep') }}</span>
                        @endif
                    </div>
                </div>
                <p class="text-[20px] font-bold text-[#00d9b5]">{{ number_format((float) $owner->ownership_percentage, 2) }}%</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-[11px] text-muted mt-3 pt-3 border-t border-th-border">
                <div>
                    <p class="uppercase tracking-wider mb-0.5">{{ __('beneficial_owners.nationality') }}</p>
                    <p class="text-primary font-semibold">{{ $owner->nationality ?? '—' }}</p>
                </div>
                <div>
                    <p class="uppercase tracking-wider mb-0.5">{{ __('beneficial_owners.id_type') }}</p>
                    <p class="text-primary font-semibold">{{ $owner->id_type ? __('beneficial_owners.idtype_' . $owner->id_type) : '—' }}</p>
                </div>
                <div>
                    <p class="uppercase tracking-wider mb-0.5">{{ __('beneficial_owners.dob') }}</p>
                    <p class="text-primary font-semibold">{{ optional($owner->date_of_birth)->format('M j, Y') ?? '—' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('dashboard.beneficial-owners.destroy', $owner->id) }}"
                  class="mt-4" onsubmit="return confirm('{{ __('beneficial_owners.confirm_delete') }}');">
                @csrf @method('DELETE')
                <button type="submit" class="text-[12px] text-[#ff4d7f] hover:underline font-semibold">{{ __('common.delete') }}</button>
            </form>
        </div>
        @empty
        <div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            </div>
            <p class="text-[14px] font-bold text-primary">{{ __('beneficial_owners.empty') }}</p>
        </div>
        @endforelse
    </div>

    {{-- Add-new sidebar --}}
    <div>
        <div class="bg-surface border border-th-border rounded-2xl p-6 sticky top-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('beneficial_owners.add_new') }}</h3>
            <form method="POST" action="{{ route('dashboard.beneficial-owners.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.full_name') }}</label>
                    <input type="text" name="full_name" required maxlength="191"
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.ownership') }} %</label>
                        <input type="number" step="0.01" min="0" max="100" name="ownership_percentage" required
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.role') }}</label>
                        <select name="role" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                            <option value="">—</option>
                            @foreach(\App\Models\BeneficialOwner::ROLES as $role)
                                <option value="{{ $role }}">{{ __('beneficial_owners.role_' . $role) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.nationality') }}</label>
                    <input type="text" name="nationality" maxlength="64"
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.id_type') }}</label>
                        <select name="id_type" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                            <option value="">—</option>
                            @foreach(\App\Models\BeneficialOwner::ID_TYPES as $type)
                                <option value="{{ $type }}">{{ __('beneficial_owners.idtype_' . $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.id_number') }}</label>
                        <input type="text" name="id_number" maxlength="64"
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('beneficial_owners.dob') }}</label>
                    <input type="date" name="date_of_birth"
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <label class="flex items-center gap-2 text-[12px] text-primary">
                    <input type="hidden" name="is_pep" value="0">
                    <input type="checkbox" name="is_pep" value="1">
                    {{ __('beneficial_owners.pep_label') }}
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('beneficial_owners.add_button') }}
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
