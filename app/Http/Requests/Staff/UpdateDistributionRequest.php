<?php

namespace App\Http\Requests\Staff;

use App\Models\Distribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('distribution'));
    }

    public function rules(): array
    {
        $canOverride = $this->user()->can('overridePrice', Distribution::class);

        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reseller_id' => ['required', Rule::exists('resellers', 'id')->whereNull('deleted_at')],
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'override_price' => $canOverride ? ['sometimes', 'boolean'] : ['prohibited'],
            'unit_price' => $canOverride ? ['nullable', 'required_if_accepted:override_price', 'numeric', 'min:0'] : ['prohibited'],
            'override_reason' => $canOverride ? ['nullable', 'required_if_accepted:override_price', 'string', 'max:255'] : ['prohibited'],
        ];
    }
}
