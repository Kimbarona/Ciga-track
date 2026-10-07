<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly FormOptions $options,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('expenses/form', [
            'expense' => null,
            'categories' => $this->options->expenseCategories(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $this->expenses->create($request->validated(), $request->user());

        return to_route('expenses.create')->with('success', 'Expense saved.');
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('expenses/form', [
            'expense' => [
                'id' => $expense->id,
                'date' => $expense->date->toDateString(),
                'expense_category_id' => $expense->expense_category_id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'paid_via' => $expense->paid_via,
                'notes' => $expense->notes,
            ],
            'categories' => $this->options->expenseCategories(),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->update($expense, $request->validated());

        return to_route('my-entries', ['tab' => 'expenses'])->with('success', 'Expense updated.');
    }
}
