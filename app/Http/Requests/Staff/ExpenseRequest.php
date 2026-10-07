<?php

namespace App\Http\Requests\Staff;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_via' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
