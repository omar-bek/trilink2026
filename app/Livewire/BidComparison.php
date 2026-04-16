<?php

namespace App\Livewire;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Rfq;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Side-by-side bid comparison UI for an RFQ.
 *
 * - Lists all bids on the RFQ
 * - Lets the buyer toggle which bids to compare (max 4)
 * - Sortable by price / delivery time / AI score
 * - Buyer can accept a bid, which auto-rejects others
 *
 * Mounted via:  <livewire:bid-comparison :rfq-id="$rfq->id" />
 */
class BidComparison extends Component
{
    public int $rfqId;

    public string $sortBy = 'price';      // price | delivery | ai_score

    public string $sortDir = 'asc';

    /** @var array<int> */
    public array $selected = [];

    public function mount(int $rfqId): void
    {
        $this->rfqId = $rfqId;
    }

    public function toggle(int $bidId): void
    {
        if (in_array($bidId, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$bidId]));

            return;
        }

        if (count($this->selected) >= 4) {
            // Drop the oldest selection — keep the comparison panel readable.
            array_shift($this->selected);
        }

        $this->selected[] = $bidId;
    }

    public function sort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';

            return;
        }
        $this->sortBy = $field;
        $this->sortDir = 'asc';
    }

    public function accept(int $bidId): void
    {
        $bid = Bid::with('rfq')->findOrFail($bidId);

        // Only buyers who own the RFQ may accept.
        $user = auth()->user();
        abort_unless(
            $user && in_array($user->role?->value, ['buyer', 'company_manager'], true)
                  && $bid->rfq?->company_id === $user->company_id,
            403
        );

        $bid->update(['status' => BidStatus::ACCEPTED]);

        Bid::where('rfq_id', $bid->rfq_id)
            ->where('id', '!=', $bid->id)
            ->update(['status' => BidStatus::REJECTED->value]);

        $this->dispatch('bid-accepted', bidId: $bid->id);
    }

    #[Computed]
    public function rfq(): Rfq
    {
        return Rfq::with('company')->findOrFail($this->rfqId);
    }

    /**
     * @return Collection<int, Bid>
     */
    #[Computed]
    public function bids(): Collection
    {
        $column = match ($this->sortBy) {
            'delivery' => 'delivery_time_days',
            'ai_score' => 'ai_score',
            default => 'price',
        };

        return Bid::with('company')
            ->where('rfq_id', $this->rfqId)
            ->whereNotIn('status', [BidStatus::WITHDRAWN->value, BidStatus::REJECTED->value])
            ->orderBy($column, $this->sortDir)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.bid-comparison');
    }
}
