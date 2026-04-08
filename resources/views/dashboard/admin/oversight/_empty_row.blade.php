@props(['cols' => 6])
<tr>
    <td colspan="{{ $cols }}" class="px-5 py-12 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></svg>
        </div>
        <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
    </td>
</tr>
