<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    private const FIELDS = ['date', 'expense_category_id', 'description', 'amount', 'paid_via', 'notes'];

    public function create(array $data, ?User $actor): Expense
    {
        return Expense::query()->create([...Arr::only($data, self::FIELDS), 'created_by' => $actor?->id]);
    }

    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->isVoided()) {
            throw ValidationException::withMessages(['expense' => 'A voided expense cannot be edited.']);
        }

        $expense->update(Arr::only($data, self::FIELDS));

        return $expense;
    }

    public function void(Expense $expense, string $reason, User $actor): Expense
    {
        if ($expense->isVoided()) {
            throw ValidationException::withMessages(['expense' => 'This expense is already voided.']);
        }

        $expense->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();

        return $expense;
    }
}
