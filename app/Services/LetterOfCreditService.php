<?php

namespace App\Services;

use App\Models\LetterOfCredit;
use App\Models\LetterOfCreditDrawing;
use App\Models\LetterOfCreditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the LC lifecycle for UAE trade-finance flows. The
 * service keeps state transitions inside a single transaction so the
 * aggregate balance (available_amount / drawn_amount) can never drift
 * out of sync with the drawings table.
 */
class LetterOfCreditService
{
    public function issue(LetterOfCredit $lc, ?User $actor = null): LetterOfCredit
    {
        return DB::transaction(function () use ($lc, $actor) {
            $lc->forceFill([
                'status' => 'issued',
                'available_amount' => $lc->amount,
                'drawn_amount' => 0,
            ])->save();

            LetterOfCreditEvent::create([
                'letter_of_credit_id' => $lc->id,
                'event' => 'issued',
                'actor_user_id' => $actor?->id,
                'amount' => $lc->amount,
                'notes' => "LC {$lc->lc_number} issued by {$lc->issuing_bank}",
            ]);

            return $lc;
        });
    }

    public function present(LetterOfCredit $lc, float $amount, User $presenter, array $discrepancies = [], ?string $bundlePath = null): LetterOfCreditDrawing
    {
        abort_if($lc->isExpired(), 422, 'LC is expired');
        abort_if($amount <= 0, 422, 'Positive amount required');
        abort_if($amount > (float) $lc->available_amount, 422, 'Exceeds available LC amount');

        return DB::transaction(function () use ($lc, $amount, $presenter, $discrepancies, $bundlePath) {
            $drawing = LetterOfCreditDrawing::create([
                'letter_of_credit_id' => $lc->id,
                'amount' => $amount,
                'currency' => $lc->currency,
                'presentation_date' => now()->toDateString(),
                'presented_by_user_id' => $presenter->id,
                'discrepancies' => $discrepancies,
                'status' => empty($discrepancies) ? 'accepted' : 'presented',
                'document_bundle_path' => $bundlePath,
            ]);

            LetterOfCreditEvent::create([
                'letter_of_credit_id' => $lc->id,
                'event' => 'presented',
                'actor_user_id' => $presenter->id,
                'amount' => $amount,
                'metadata' => ['drawing_id' => $drawing->id, 'discrepancies' => $discrepancies],
            ]);

            return $drawing;
        });
    }

    public function honour(LetterOfCreditDrawing $drawing, User $actor): LetterOfCreditDrawing
    {
        abort_unless(in_array($drawing->status, ['presented', 'accepted'], true), 422);

        return DB::transaction(function () use ($drawing, $actor) {
            $lc = $drawing->letterOfCredit;
            $newDrawn = (float) $lc->drawn_amount + (float) $drawing->amount;
            $newAvail = max(0, (float) $lc->amount - $newDrawn);

            $lc->forceFill([
                'drawn_amount' => $newDrawn,
                'available_amount' => $newAvail,
                'status' => $newAvail <= 0 ? 'closed' : 'drawn',
            ])->save();

            $drawing->forceFill([
                'status' => 'honoured',
                'honoured_date' => now()->toDateString(),
            ])->save();

            LetterOfCreditEvent::create([
                'letter_of_credit_id' => $lc->id,
                'event' => 'honoured',
                'actor_user_id' => $actor->id,
                'amount' => $drawing->amount,
                'metadata' => ['drawing_id' => $drawing->id],
            ]);

            return $drawing;
        });
    }

    public function reject(LetterOfCreditDrawing $drawing, User $actor, string $reason): void
    {
        $drawing->update(['status' => 'rejected']);

        LetterOfCreditEvent::create([
            'letter_of_credit_id' => $drawing->letter_of_credit_id,
            'event' => 'rejected',
            'actor_user_id' => $actor->id,
            'amount' => $drawing->amount,
            'notes' => $reason,
            'metadata' => ['drawing_id' => $drawing->id],
        ]);
    }

    public function cancel(LetterOfCredit $lc, User $actor, string $reason): void
    {
        abort_if($lc->drawn_amount > 0, 422, 'Cannot cancel — already drawn');

        $lc->update(['status' => 'cancelled', 'available_amount' => 0]);

        LetterOfCreditEvent::create([
            'letter_of_credit_id' => $lc->id,
            'event' => 'cancelled',
            'actor_user_id' => $actor->id,
            'notes' => $reason,
        ]);
    }
}
