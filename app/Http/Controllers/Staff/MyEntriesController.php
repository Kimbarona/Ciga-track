<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Remittance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own entries. Same-day entries can be edited until they are
 * confirmed or voided (the policies decide; the UI just shows the flag).
 */
class MyEntriesController extends Controller
{
    private const LIMIT = 100;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['distributions', 'remittances', 'purchases', 'expenses'], true)
            ? $request->query('tab')
            : 'distributions';

        return Inertia::render('my-entries', [
            'tab' => $tab,
            'distributions' => Inertia::defer(fn () => Distribution::query()
                ->where('created_by', $user->id)->with(['reseller', 'product'])
                ->latest('date')->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Distribution $d) => [
                    'id' => $d->id,
                    'date' => $d->date->format('M d, Y'),
                    'title' => $d->reseller->name,
                    'subtitle' => "{$d->qty} × {$d->product->code} @ ₱".number_format((float) $d->unit_price, 2),
                    'amount' => (float) $d->total_amount,
                    'badge' => $d->isVoided() ? 'voided' : $d->price_source->value,
                    'note' => $d->void_reason,
                    'can_edit' => $user->can('update', $d),
                    'edit_url' => route('distributions.edit', $d),
                ])->all(), 'entries'),
            'remittances' => Inertia::defer(fn () => Remittance::query()
                ->where('recorded_by', $user->id)->with('reseller')
                ->latest('date')->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Remittance $r) => [
                    'id' => $r->id,
                    'date' => $r->date->format('M d, Y'),
                    'title' => $r->reseller->name,
                    'subtitle' => $r->mode->getLabel().($r->reference_no ? " · #{$r->reference_no}" : ''),
                    'amount' => (float) $r->amount,
                    'badge' => $r->status->value,
                    'note' => $r->rejection_reason,
                    'can_edit' => $user->can('update', $r),
                    'edit_url' => route('remittances.edit', $r),
                ])->all(), 'entries'),
            'purchases' => Inertia::defer(fn () => Purchase::query()
                ->where('created_by', $user->id)->with('product')
                ->latest('date')->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Purchase $p) => [
                    'id' => $p->id,
                    'date' => $p->date->format('M d, Y'),
                    'title' => "{$p->qty} × {$p->product->code}",
                    'subtitle' => $p->supplier ?: 'No supplier',
                    // Purchase cost is cost data: admins only.
                    'amount' => $user->can('view-financials') ? (float) $p->total_cost : null,
                    'badge' => $p->isVoided() ? 'voided' : null,
                    'note' => $p->void_reason,
                    'can_edit' => $user->can('update', $p),
                    'edit_url' => route('purchases.edit', $p),
                ])->all(), 'entries'),
            'expenses' => Inertia::defer(fn () => Expense::query()
                ->where('created_by', $user->id)->with('category')
                ->latest('date')->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Expense $e) => [
                    'id' => $e->id,
                    'date' => $e->date->format('M d, Y'),
                    'title' => $e->description,
                    'subtitle' => $e->category->name.($e->paid_via ? " · {$e->paid_via}" : ''),
                    'amount' => (float) $e->amount,
                    'badge' => $e->isVoided() ? 'voided' : null,
                    'note' => $e->void_reason,
                    'can_edit' => $user->can('update', $e),
                    'edit_url' => route('expenses.edit', $e),
                ])->all(), 'entries'),
        ]);
    }
}
