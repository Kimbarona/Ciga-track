<?php

namespace App\Http\Requests\Staff;

use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $purchase
            ? $this->user()->can('update', $purchase)
            : $this->user()->can('create', Purchase::class);
    }

    public function rules(): array
    {
        $editing = $this->route('purchase') !== null;

        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            // Staff never receive the saved cost back, so on edit a blank cost keeps the old one.
            'unit_cost' => [$editing ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999999'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return collect($this->validated())->reject(fn ($value, $key) => $key === 'unit_cost' && $value === null)->all();
    }
}
