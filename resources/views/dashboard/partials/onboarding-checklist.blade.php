{{--
    Sprint B.6 — onboarding checklist widget. Rendered on every role's
    dashboard until the OnboardingChecklistService says every required
    step is complete. Each step derives its done state from the actual
    state of the data, so there is no completion column to keep in
    sync — fixing the underlying gap (uploading a license, inviting a
    team member, etc.) automatically advances the bar.

    Required props:
        $onboarding — array shape from OnboardingChecklistService::for()
--}}
@props(['onboarding'])

<div class="mb-6 sm:mb-8 bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.08)] rounded-2xl overflow-hidden"
     x-data="{ collapsed: false }">
    {{-- Header bar with progress --}}
    <div class="flex items-center justify-between gap-4 p-5 sm:p-6 border-b border-th-border dark:border-[rgba(255,255,255,0.06)]">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-accent/15 text-accent flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-[15px] sm:text-[16px] font-bold text-primary dark:text-white leading-tight">
                    {{ __('onboarding.widget_title') }}
                </h2>
                <p class="text-[12px] text-muted dark:text-[#b4b6c0] mt-0.5">
                    {{ __('onboarding.widget_progress', [
                        'completed' => $onboarding['completed'],
                        'total'     => $onboarding['total'],
                    ]) }}
                </p>
            </div>
        </div>
        <button type="button" @click="collapsed = !collapsed"
                class="text-[12px] font-semibold text-muted hover:text-primary dark:hover:text-white px-3 py-1.5 rounded-lg hover:bg-page dark:hover:bg-[#0f1117] transition-colors flex-shrink-0">
            <span x-show="!collapsed">{{ __('onboarding.widget_collapse') }}</span>
            <span x-show="collapsed" x-cloak>{{ __('onboarding.widget_expand') }}</span>
        </button>
    </div>

    {{-- Progress bar --}}
    <div class="px-5 sm:px-6 pt-4">
        <div class="h-1.5 w-full rounded-full bg-page dark:bg-[#0f1117] overflow-hidden">
            <div class="h-full bg-accent rounded-full transition-all duration-500"
                 style="width: {{ $onboarding['percent'] }}%"
                 role="progressbar"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="{{ $onboarding['percent'] }}"
                 aria-label="{{ __('onboarding.widget_aria_progress') }}"></div>
        </div>
    </div>

    {{-- Steps --}}
    <div class="p-5 sm:p-6 space-y-3" x-show="!collapsed" x-collapse>
        @foreach($onboarding['steps'] as $index => $step)
            <div class="flex items-start gap-4 p-4 rounded-xl border transition-colors {{ $step['done'] ? 'bg-accent-success/[0.04] border-accent-success/30' : 'bg-page dark:bg-[#0f1117] border-th-border dark:border-[rgba(255,255,255,0.08)]' }}">
                {{-- Status indicator: filled green tick when done,
                     numbered circle when pending. Both reach the
                     same accessible information via aria-label. --}}
                <div class="flex-shrink-0">
                    @if($step['done'])
                        <div class="w-8 h-8 rounded-full bg-accent-success/15 text-accent-success flex items-center justify-center"
                             aria-label="{{ __('onboarding.step_done_aria') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-full bg-elevated dark:bg-[#252932] text-muted dark:text-[#b4b6c0] flex items-center justify-center text-[12px] font-bold"
                             aria-label="{{ __('onboarding.step_pending_aria', ['n' => $index + 1]) }}">
                            {{ $index + 1 }}
                        </div>
                    @endif
                </div>

                {{-- Title + description --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-[13px] sm:text-[14px] font-bold text-primary dark:text-white {{ $step['done'] ? 'line-through text-muted' : '' }}">
                            {{ $step['title'] }}
                        </h3>
                        @if($step['optional'])
                            <span class="text-[10px] font-semibold text-muted bg-elevated dark:bg-[#252932] px-2 py-0.5 rounded-full uppercase tracking-wider">
                                {{ __('onboarding.step_optional_badge') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[12px] text-muted dark:text-[#b4b6c0] mt-1 leading-relaxed">{{ $step['description'] }}</p>
                </div>

                {{-- CTA only for steps that are still pending --}}
                @unless($step['done'])
                    <a href="{{ $step['route'] }}"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
                        {{ $step['cta'] }}
                        <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @endunless
            </div>
        @endforeach
    </div>
</div>
