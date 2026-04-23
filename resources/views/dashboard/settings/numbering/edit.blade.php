@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.numbering_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.numbering_title')" :subtitle="__('settings.numbering_subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <form method="POST" action="{{ route('settings.numbering.update') }}">
        @csrf @method('PATCH')

        <table class="w-full text-[13px]">
            <thead class="text-muted border-b border-th-border">
                <tr>
                    <th class="text-start py-2">{{ __('settings.document_type') }}</th>
                    <th class="text-start py-2">{{ __('settings.prefix') }}</th>
                    <th class="text-start py-2">{{ __('settings.format_template') }}</th>
                    <th class="text-start py-2">{{ __('settings.next_sequence') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($types as $type)
                @php $row = $existing[$type] ?? null; @endphp
                <tr class="border-b border-th-border/50">
                    <td class="py-3 font-semibold text-primary">{{ __('settings.doc_'.$type) }}</td>
                    <td class="py-2">
                        <input type="text" name="series[{{ $type }}][prefix]" maxlength="32"
                               value="{{ old("series.{$type}.prefix", $row?->prefix) }}"
                               class="w-32 bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                    </td>
                    <td class="py-2">
                        <input type="text" name="series[{{ $type }}][format_template]" maxlength="64"
                               value="{{ old("series.{$type}.format_template", $row?->format_template ?? '{PREFIX}-{YEAR}-{SEQ:6}') }}"
                               class="w-64 bg-page border border-th-border rounded px-2 py-1 text-[12px] font-mono">
                    </td>
                    <td class="py-2">
                        <input type="number" min="0" name="series[{{ $type }}][current_sequence]"
                               value="{{ old("series.{$type}.current_sequence", $row?->current_sequence ?? 0) }}"
                               class="w-24 bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <p class="text-[12px] text-muted mt-4">{{ __('settings.numbering_tokens_hint') }}</p>

        <div class="pt-4 mt-4 border-t border-th-border">
            <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('settings.save') }}</button>
        </div>
    </form>
</div>
@endsection
