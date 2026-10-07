<?php

namespace App\Http\Requests\Staff;

use App\Models\Distribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Multi-line entry: one reseller + date, several product lines.
 */
class StoreDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Distribution::class);
    }

    public function rules(): array
    {
        $canOverride = $this->user()->can('overridePrice', Distribution::class);

        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reseller_id' => ['required', Rule::exists('resellers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.override_price' => $canOverride ? ['sometimes', 'boolean'] : ['prohibited'],
            'lines.*.unit_price' => $canOverride ? ['nullable', 'required_if_accepted:lines.*.override_price', 'numeric', 'min:0'] : ['prohibited'],
            'lines.*.override_reason' => $canOverride ? ['nullable', 'required_if_accepted:lines.*.override_price', 'string', 'max:255'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.override_price.prohibited' => 'Only admins can override prices.',
            'lines.*.unit_price.prohibited' => 'Only admins can override prices.',
            'lines.*.product_id.required' => 'Pick a product.',
            'lines.*.qty.min' => 'Qty must be at least 1.',
            'date.before_or_equal' => 'The date cannot be in the future.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return collect($this->validated('lines'))
            ->map(fn (array $line) => [...$line, 'notes' => $this->validated('notes')])
            ->values()
            ->all();
    }
}
