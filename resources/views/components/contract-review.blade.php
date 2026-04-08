@props([
    'contractId',
    'existing' => null,  // ['rating', 'comment'] or null
])

@php
    $hasReview = is_array($existing);
@endphp

<div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
    <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-[10px] bg-[rgba(255,176,32,0.1)] flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <div>
            <h3 class="text-[16px] font-semibold text-white">
                {{ $hasReview ? 'Your Review' : 'Rate This Contract' }}
            </h3>
            <p class="text-[12px] text-[#b4b6c0] mt-0.5">
                {{ $hasReview
                    ? 'Your feedback is visible on the other side\'s profile.'
                    : 'Share your experience. Your rating appears on their company profile.' }}
            </p>
        </div>
    </div>

    @if($hasReview)
        {{-- Read-only display of existing review. --}}
        <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
            <div class="flex items-center gap-1 mb-2">
                @for($i = 1; $i <= 5; $i++)
                    <svg class="w-5 h-5 {{ $i <= $existing['rating'] ? 'text-[#ffb020]' : 'text-[#252932]' }}" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                @endfor
                <span class="ms-2 text-[14px] font-semibold text-white">{{ $existing['rating'] }}/5</span>
            </div>
            @if($existing['comment'])
                <p class="text-[13px] text-[#b4b6c0] leading-[20px]">{{ $existing['comment'] }}</p>
            @endif
        </div>
    @else
        {{-- Rating form. Alpine manages the star-picker state; submit is a
             vanilla POST. --}}
        <form x-data="{ rating: 0 }" method="POST"
              action="{{ route('dashboard.contracts.feedback.store', ['id' => $contractId]) }}"
              class="space-y-4">
            @csrf

            <div>
                <label class="block text-[13px] text-[#b4b6c0] mb-2">Overall Rating <span class="text-[#ff4d7f]">*</span></label>
                <div class="flex items-center gap-1">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" @click="rating = {{ $i }}" class="p-1 transition-transform hover:scale-110">
                            <svg class="w-7 h-7 transition-colors"
                                 :class="rating >= {{ $i }} ? 'text-[#ffb020]' : 'text-[#252932] hover:text-[#ffb020]/50'"
                                 fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ms-3 text-[13px] text-[#b4b6c0]" x-text="rating === 0 ? 'No rating' : rating + '/5'"></span>
                </div>
                <input type="hidden" name="rating" :value="rating" required>
            </div>

            <div>
                <label class="block text-[13px] text-[#b4b6c0] mb-1.5">Comment (optional)</label>
                <textarea name="comment" rows="3"
                          placeholder="What went well? What could be better?"
                          class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] px-4 py-3 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors resize-none"></textarea>
            </div>

            <button type="submit" :disabled="rating === 0"
                    :class="rating === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#00c9a5]'"
                    class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                Submit Review
            </button>
        </form>
    @endif
</div>
