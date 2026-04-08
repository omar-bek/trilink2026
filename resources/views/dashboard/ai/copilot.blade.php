@extends('layouts.dashboard', ['active' => 'ai-copilot'])
@section('title', __('ai.copilot_title'))

@section('content')

<x-dashboard.page-header :title="__('ai.copilot_title')" :subtitle="__('ai.copilot_subtitle')" />

<div class="bg-surface border border-th-border rounded-2xl flex flex-col" style="height: calc(100vh - 240px); min-height: 500px;">

    {{-- Conversation log. Scrolls independently of the input. --}}
    <div class="flex-1 overflow-y-auto p-6 space-y-4" id="copilot-log">
        @if(empty($history))
        <div class="text-center text-muted py-8">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </div>
            <p class="text-[14px] font-semibold text-primary">{{ __('ai.copilot_empty_title') }}</p>
            <p class="text-[12px] text-muted mt-1">{{ __('ai.copilot_empty_subtitle') }}</p>
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-xl mx-auto text-start">
                @foreach([__('ai.copilot_example_1'), __('ai.copilot_example_2'), __('ai.copilot_example_3'), __('ai.copilot_example_4')] as $ex)
                <form method="POST" action="{{ route('dashboard.ai.copilot.chat') }}" class="block">
                    @csrf
                    <input type="hidden" name="message" value="{{ $ex }}">
                    <button type="submit" class="w-full text-start bg-page border border-th-border hover:border-accent/40 rounded-lg p-3 text-[12px] text-primary transition-colors">
                        “{{ $ex }}”
                    </button>
                </form>
                @endforeach
            </div>
        </div>
        @endif

        @foreach($history as $msg)
        <div class="flex items-start gap-3 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
            <div @class([
                'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-[11px] font-bold',
                'bg-accent/10 text-accent' => $msg['role'] === 'assistant',
                'bg-surface-2 text-primary' => $msg['role'] === 'user',
            ])>
                {{ $msg['role'] === 'user' ? 'U' : 'AI' }}
            </div>
            <div @class([
                'max-w-[75%] rounded-2xl px-4 py-3 text-[13px] leading-relaxed whitespace-pre-line',
                'bg-page border border-th-border text-body' => $msg['role'] === 'assistant',
                'bg-accent/10 border border-accent/20 text-primary' => $msg['role'] === 'user',
            ])>{{ $msg['content'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Compose box. Submitting reloads the page (server-rendered chat
         keeps the surface area minimal — no JS state to lose). --}}
    <form method="POST" action="{{ route('dashboard.ai.copilot.chat') }}" class="border-t border-th-border p-3 sm:p-4 flex items-end gap-2 sm:gap-3">
        @csrf
        <textarea name="message" rows="2" maxlength="1000" required
                  placeholder="{{ __('ai.copilot_placeholder') }}"
                  class="flex-1 bg-page border border-th-border rounded-xl px-3 sm:px-4 py-3 text-[13px] text-primary resize-none focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition"></textarea>
        <button type="submit" class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-xl bg-accent text-white text-[13px] font-bold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
            <span class="hidden sm:inline">{{ __('ai.send') }}</span>
        </button>
        @if(!empty($history))
        <button type="submit" formaction="{{ route('dashboard.ai.copilot.reset') }}"
                class="inline-flex items-center justify-center h-11 px-3 rounded-xl bg-page border border-th-border text-muted hover:text-primary hover:border-th-border text-[12px] transition-colors"
                title="{{ __('ai.reset') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
        </button>
        @endif
    </form>
</div>

<script>
// Auto-scroll the log to the latest message after every page load.
document.addEventListener('DOMContentLoaded', () => {
    const log = document.getElementById('copilot-log');
    if (log) log.scrollTop = log.scrollHeight;
});
</script>

@endsection
